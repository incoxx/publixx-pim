import { execSync, spawn, type ChildProcess } from 'child_process';
import fs from 'fs';
import path from 'path';
import winston from 'winston';
import * as log from './logger';

const TMP_DIR = path.resolve(__dirname, '../../storage/video-engine/tmp');

export interface RecorderOptions {
  storyId: string;
  scriptPath: string;
  display: string;
  fps: number;
  width: number;
  height: number;
  logger: winston.Logger;
}

/**
 * Orchestriert Xvfb + ffmpeg + Playwright für die Video-Aufnahme.
 */
export class Recorder {
  private xvfbProcess: ChildProcess | null = null;
  private ffmpegProcess: ChildProcess | null = null;
  private opts: RecorderOptions;
  private tmpDir: string;
  private outputPath: string;
  private spaceCheckInterval: NodeJS.Timeout | null = null;

  constructor(opts: RecorderOptions) {
    this.opts = opts;
    this.tmpDir = path.join(TMP_DIR, `${opts.storyId}-${Date.now()}`);
    this.outputPath = path.join(this.tmpDir, 'recording.mp4');

    if (!fs.existsSync(this.tmpDir)) {
      fs.mkdirSync(this.tmpDir, { recursive: true });
    }
  }

  /** Startet Xvfb mit Fallback-Displays */
  async startXvfb(): Promise<string> {
    const displays = [this.opts.display, ':100', ':101', ':102'];

    for (const display of displays) {
      try {
        // Prüfe ob Display bereits belegt ist
        try {
          execSync(`xdpyinfo -display ${display} 2>/dev/null`, { timeout: 2000 });
          log.record(this.opts.logger, `Display ${display} bereits belegt – versuche nächstes`);
          continue;
        } catch {
          // Display frei – nutzen
        }

        this.xvfbProcess = spawn('Xvfb', [
          display,
          '-screen', '0', `${this.opts.width}x${this.opts.height}x24`,
          '-ac',
        ], {
          stdio: 'ignore',
          detached: true,
        });

        // Kurz warten, damit Xvfb starten kann
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Prüfen ob Xvfb noch läuft
        if (this.xvfbProcess.exitCode !== null) {
          log.record(this.opts.logger, `Xvfb konnte nicht auf ${display} starten`);
          this.xvfbProcess = null;
          continue;
        }

        log.record(this.opts.logger, `Xvfb gestartet auf Display ${display}`);
        return display;
      } catch {
        continue;
      }
    }

    throw new Error('Xvfb konnte auf keinem Display gestartet werden');
  }

  /** Startet ffmpeg Screen Capture */
  startRecording(display: string): void {
    const args = [
      '-y',
      '-f', 'x11grab',
      '-video_size', `${this.opts.width}x${this.opts.height}`,
      '-framerate', String(this.opts.fps),
      '-i', display,
      '-c:v', 'libx264',
      '-preset', 'ultrafast',
      '-pix_fmt', 'yuv420p',
      this.outputPath,
    ];

    const ffmpegBin = process.env.FFMPEG_PATH?.trim() || 'ffmpeg';
    this.ffmpegProcess = spawn(ffmpegBin, args, {
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    // 'error' abfangen, sonst beendet eine fehlende Binary den Prozess mit
    // einer Unhandled-Exception statt einer verwertbaren Fehlermeldung.
    this.ffmpegProcess.on('error', (err) => {
      log.record(this.opts.logger, `ffmpeg konnte nicht gestartet werden (${ffmpegBin}): ${err.message}`);
    });

    this.ffmpegProcess.stderr?.on('data', (data: Buffer) => {
      const msg = data.toString().trim();
      if (msg.includes('frame=') || msg.includes('fps=')) {
        // Fortschritt – nur bei Debug loggen
      }
    });

    log.record(this.opts.logger, `ffmpeg läuft, Output: ${this.outputPath}`);

    // Speicherplatz-Überwachung alle 30s
    this.spaceCheckInterval = setInterval(() => {
      this.checkDiskSpace();
    }, 30000);
  }

  /** Führt das Playwright-Script aus */
  async runScript(display: string): Promise<void> {
    return new Promise((resolve, reject) => {
      const env = {
        ...process.env,
        DISPLAY: display,
      };

      // Lokales tsx statt `npx tsx` – vermeidet npm-Notices auf stderr und
      // einen möglichen Netzwerk-Download in restriktiven Umgebungen.
      const tsxBin = path.resolve(__dirname, '../node_modules/.bin/tsx');
      const child = spawn(tsxBin, [this.opts.scriptPath], {
        cwd: path.resolve(__dirname, '..'),
        env,
        stdio: 'inherit',
      });

      child.on('close', (code) => {
        if (code === 0) {
          resolve();
        } else {
          reject(new Error(`Playwright-Script beendet mit Code ${code}`));
        }
      });

      child.on('error', (err) => {
        reject(new Error(`Playwright-Script Fehler: ${err.message}`));
      });
    });
  }

  /** Stoppt ffmpeg und Xvfb */
  async stop(): Promise<string> {
    // Speichercheck stoppen
    if (this.spaceCheckInterval) {
      clearInterval(this.spaceCheckInterval);
      this.spaceCheckInterval = null;
    }

    // ffmpeg beenden (SIGINT für sauberes Ende)
    if (this.ffmpegProcess && this.ffmpegProcess.exitCode === null) {
      this.ffmpegProcess.kill('SIGINT');
      await new Promise<void>((resolve) => {
        const timeout = setTimeout(() => {
          this.ffmpegProcess?.kill('SIGKILL');
          resolve();
        }, 10000);

        this.ffmpegProcess!.on('close', () => {
          clearTimeout(timeout);
          resolve();
        });
      });
      log.record(this.opts.logger, 'ffmpeg gestoppt');
    }

    // Xvfb beenden
    if (this.xvfbProcess && this.xvfbProcess.exitCode === null) {
      this.xvfbProcess.kill('SIGTERM');
      await new Promise(resolve => setTimeout(resolve, 500));
      log.record(this.opts.logger, 'Xvfb gestoppt');
    }

    return this.outputPath;
  }

  /** Cleanup bei Fehler */
  cleanup(): void {
    if (this.spaceCheckInterval) {
      clearInterval(this.spaceCheckInterval);
    }
    if (this.ffmpegProcess && this.ffmpegProcess.exitCode === null) {
      this.ffmpegProcess.kill('SIGKILL');
    }
    if (this.xvfbProcess && this.xvfbProcess.exitCode === null) {
      this.xvfbProcess.kill('SIGKILL');
    }
  }

  /** Gibt den tmp-Verzeichnispfad zurück */
  getTmpDir(): string {
    return this.tmpDir;
  }

  private checkDiskSpace(): void {
    try {
      const output = execSync(`df -BG "${this.tmpDir}" | tail -1 | awk '{print $4}'`, {
        encoding: 'utf-8',
      }).trim();
      const available = parseInt(output.replace('G', ''), 10);
      if (available < 1) {
        log.error(this.opts.logger, `WARNUNG: Nur noch ${available} GB Speicherplatz!`);
      }
    } catch {
      // Ignorieren
    }
  }
}
