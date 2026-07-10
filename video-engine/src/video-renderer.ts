import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import winston from 'winston';
import * as log from './logger';
import { ffmpegBin, ffprobeBin } from './runtime-deps';

export interface RenderOptions {
  videoPath: string;
  audioPath: string | null;
  srtPath: string | null;
  sonicLogoPath?: string | null;
  outputPath: string;
  quality: 'high' | 'medium' | 'low';
  logger: winston.Logger;
}

const QUALITY_PRESETS: Record<string, { crf: number; preset: string }> = {
  high: { crf: 18, preset: 'slow' },
  medium: { crf: 23, preset: 'medium' },
  low: { crf: 28, preset: 'fast' },
};

/**
 * Fügt Video, Audio und Untertitel zu einem fertigen Video zusammen.
 */
export async function renderVideo(opts: RenderOptions): Promise<void> {
  const { videoPath, srtPath, outputPath, quality, logger } = opts;
  let { audioPath, sonicLogoPath } = opts;
  const preset = QUALITY_PRESETS[quality] || QUALITY_PRESETS.medium;

  // Sonic Logo + Voiceover zusammenführen (falls beides vorhanden)
  if (sonicLogoPath && fs.existsSync(sonicLogoPath) && audioPath && fs.existsSync(audioPath)) {
    log.render(logger, 'Sonic Logo + Voiceover zusammenführen...');
    const tmpDir = path.dirname(audioPath);
    const mergedAudioPath = path.join(tmpDir, 'audio-with-sonic.mp3');
    try {
      // Sonic Logo am Anfang (volle Lautstärke), Voiceover ohne Verzögerung
      // volume=2 kompensiert amix-Normalisierung (amix teilt durch Anzahl Inputs)
      execSync(
        `${ffmpegBin()} -y -i "${sonicLogoPath}" -i "${audioPath}" ` +
        `-filter_complex "[0]volume=3.0[sonic];[1]volume=2.0[voice];[sonic][voice]amix=inputs=2:duration=longest" ` +
        `-b:a 192k "${mergedAudioPath}"`,
        { timeout: 30000, stdio: 'pipe' },
      );
      audioPath = mergedAudioPath;
      log.render(logger, 'Sonic Logo eingemischt');
    } catch (err) {
      const errMsg = err instanceof Error ? err.message : String(err);
      log.render(logger, `Sonic Logo Merge fehlgeschlagen: ${errMsg.split('\n')[0]}`);
    }
  } else if (sonicLogoPath && fs.existsSync(sonicLogoPath) && (!audioPath || !fs.existsSync(audioPath))) {
    audioPath = sonicLogoPath;
  }

  if (!fs.existsSync(videoPath)) {
    throw new Error(`Video nicht gefunden: ${videoPath}`);
  }

  // Sicherstellen, dass Output-Verzeichnis existiert
  const outputDir = path.dirname(outputPath);
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  // Dauer ermitteln
  const videoDuration = getMediaDuration(videoPath);
  const audioDuration = audioPath ? getMediaDuration(audioPath) : 0;

  log.render(logger, `Video: ${videoDuration.toFixed(1)}s, Audio: ${audioDuration.toFixed(1)}s`);

  const args: string[] = ['-y'];

  // Inputs
  args.push('-i', videoPath);

  if (audioPath && fs.existsSync(audioPath)) {
    args.push('-i', audioPath);
  }

  // Filter: Audio-/Video-Padding bei unterschiedlichen Längen
  const filters: string[] = [];

  if (audioPath && fs.existsSync(audioPath)) {
    if (audioDuration > videoDuration) {
      // Audio länger → Video mit letztem Frame padden
      const padDuration = audioDuration - videoDuration;
      log.render(logger, `Video wird um ${padDuration.toFixed(1)}s gepaddet (letztes Standbild)`);
      args.push('-shortest');
      // tpad: wiederhole letzten Frame
      filters.push(`[0:v]tpad=stop_mode=clone:stop_duration=${padDuration.toFixed(2)}[v]`);
    } else if (videoDuration > audioDuration + 1) {
      // Video länger → Audio mit Stille padden
      const padDuration = videoDuration - audioDuration;
      log.render(logger, `Audio wird um ${padDuration.toFixed(1)}s mit Stille gepaddet`);
      filters.push(`[1:a]apad=pad_dur=${padDuration.toFixed(2)}[a]`);
    }
  }

  // Untertitel einbrennen (optional)
  if (srtPath && fs.existsSync(srtPath)) {
    const escapedSrt = srtPath.replace(/[\\:]/g, '\\$&');
    // Ohne explizite original_size nimmt libass für ein reines .srt (ohne ASS-Header)
    // eine viel zu kleine interne Referenzauflösung an (klassischer SSA-Default) — die
    // Schrift wird beim Hochskalieren auf die tatsächliche Videoauflösung riesig.
    const { width, height } = getVideoDimensions(videoPath);
    const originalSize = width && height ? `:original_size=${width}x${height}` : '';
    const subtitleFilter = `subtitles='${escapedSrt}'${originalSize}:force_style='FontSize=22,PrimaryColour=&HFFFFFF,OutlineColour=&H000000,BorderStyle=3,Outline=2'`;
    if (filters.length > 0 && filters.some(f => f.includes('[v]'))) {
      filters.push(`[v]${subtitleFilter}[vout]`);
    } else {
      filters.push(`[0:v]${subtitleFilter}[vout]`);
    }
  }

  // Filter-Graph anwenden
  if (filters.length > 0) {
    args.push('-filter_complex', filters.join(';'));
    // Output-Streams mappen
    if (filters.some(f => f.includes('[vout]'))) {
      args.push('-map', '[vout]');
    } else if (filters.some(f => f.includes('[v]'))) {
      args.push('-map', '[v]');
    } else {
      args.push('-map', '0:v');
    }
    if (audioPath && filters.some(f => f.includes('[a]'))) {
      args.push('-map', '[a]');
    } else if (audioPath) {
      args.push('-map', '1:a');
    }
  } else {
    args.push('-map', '0:v');
    if (audioPath && fs.existsSync(audioPath)) {
      args.push('-map', '1:a');
    }
  }

  // Encoding-Einstellungen
  args.push('-c:v', 'libx264');
  args.push('-crf', String(preset.crf));
  args.push('-preset', preset.preset);
  args.push('-pix_fmt', 'yuv420p');

  if (audioPath && fs.existsSync(audioPath)) {
    args.push('-c:a', 'aac');
    args.push('-b:a', '192k');
  }

  args.push(outputPath);

  log.render(logger, `ffmpeg merge: video + audio + srt`);

  try {
    execSync(`${ffmpegBin()} ${args.map(a => `"${a}"`).join(' ')}`, {
      timeout: 300000, // 5 Minuten Timeout
      stdio: 'pipe',
    });
  } catch (err) {
    // Original-Fehler loggen für Diagnose
    const errMsg = err instanceof Error ? err.message : String(err);
    log.render(logger, `Komplexer Render fehlgeschlagen: ${errMsg.split('\n')[0]}`);
    log.render(logger, 'Versuche einfachen Merge als Fallback...');
    const simpleArgs = ['-y', '-i', videoPath];
    if (audioPath && fs.existsSync(audioPath)) {
      simpleArgs.push('-i', audioPath);
    }
    simpleArgs.push('-c:v', 'libx264', '-crf', String(preset.crf), '-preset', preset.preset);
    simpleArgs.push('-pix_fmt', 'yuv420p');
    if (audioPath && fs.existsSync(audioPath)) {
      simpleArgs.push('-c:a', 'aac', '-b:a', '192k', '-shortest');
    }
    simpleArgs.push(outputPath);

    execSync(`${ffmpegBin()} ${simpleArgs.map(a => `"${a}"`).join(' ')}`, {
      timeout: 300000,
      stdio: 'pipe',
    });
  }

  // Ergebnis prüfen
  if (!fs.existsSync(outputPath)) {
    throw new Error('Video-Rendering fehlgeschlagen – Output-Datei nicht erstellt');
  }

  const fileSize = fs.statSync(outputPath).size;
  const duration = getMediaDuration(outputPath);
  const sizeMB = (fileSize / (1024 * 1024)).toFixed(1);

  const minutes = Math.floor(duration / 60);
  const seconds = Math.floor(duration % 60);

  log.output(logger, `✓ ${outputPath} (${sizeMB} MB, ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')})`);
}

/** Gibt die Dauer einer Medien-Datei in Sekunden zurück */
function getMediaDuration(filePath: string): number {
  try {
    const output = execSync(
      `${ffprobeBin()} -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${filePath}"`,
      { encoding: 'utf-8', timeout: 5000 },
    ).trim();
    return parseFloat(output) || 0;
  } catch {
    return 0;
  }
}

/** Gibt Breite/Höhe des Video-Streams zurück (für den subtitles-Filter original_size) */
function getVideoDimensions(filePath: string): { width: number; height: number } {
  try {
    const output = execSync(
      `${ffprobeBin()} -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 "${filePath}"`,
      { encoding: 'utf-8', timeout: 5000 },
    ).trim();
    const [width, height] = output.split('x').map(Number);
    return { width: width || 0, height: height || 0 };
  } catch {
    return { width: 0, height: 0 };
  }
}
