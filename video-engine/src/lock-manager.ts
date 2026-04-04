import fs from 'fs';
import path from 'path';

const LOCK_DIR = path.resolve(__dirname, '../../storage/video-engine/lock');

/**
 * Verhindert parallele Ausführung der Video-Engine.
 * Nutzt PID-basierte Lock-Files mit Stale-Detection.
 */
export class LockManager {
  private lockFile: string;

  constructor(storyId: string) {
    if (!fs.existsSync(LOCK_DIR)) {
      fs.mkdirSync(LOCK_DIR, { recursive: true });
    }
    this.lockFile = path.join(LOCK_DIR, `${storyId}.lock`);
  }

  /** Prüft ob ein aktiver Lock existiert */
  isLocked(): boolean {
    if (!fs.existsSync(this.lockFile)) return false;

    const content = fs.readFileSync(this.lockFile, 'utf-8').trim();
    const pid = parseInt(content, 10);

    if (isNaN(pid)) {
      // Ungültiges Lock-File → löschen
      this.removeLock();
      return false;
    }

    // Prüfe ob PID noch aktiv ist
    if (this.isPidRunning(pid)) {
      return true;
    }

    // Stale Lock → automatisch löschen
    console.warn(`[LOCK] Stale Lock gefunden (PID ${pid} nicht mehr aktiv) – wird entfernt`);
    this.removeLock();
    return false;
  }

  /** Erstellt einen Lock */
  acquire(): boolean {
    if (this.isLocked()) {
      return false;
    }

    fs.writeFileSync(this.lockFile, String(process.pid), 'utf-8');
    return true;
  }

  /** Entfernt den Lock */
  release(): void {
    this.removeLock();
  }

  private removeLock(): void {
    try {
      if (fs.existsSync(this.lockFile)) {
        fs.unlinkSync(this.lockFile);
      }
    } catch {
      // Ignorieren – Lock ist ggf. bereits entfernt
    }
  }

  private isPidRunning(pid: number): boolean {
    try {
      // Signal 0 prüft ob Prozess existiert, ohne ihn zu beenden
      process.kill(pid, 0);
      return true;
    } catch {
      return false;
    }
  }
}

/**
 * Cleanup-Handler: Entfernt Lock bei Prozess-Beendigung
 */
export function registerCleanup(lock: LockManager): void {
  const cleanup = () => {
    lock.release();
    process.exit();
  };

  process.on('SIGINT', cleanup);
  process.on('SIGTERM', cleanup);
  process.on('uncaughtException', (err) => {
    console.error('[LOCK] Uncaught Exception – Lock wird entfernt:', err.message);
    lock.release();
    process.exit(1);
  });
}
