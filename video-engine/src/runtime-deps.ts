import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

/**
 * Löst die Pfade zu ffmpeg und Chromium auf – robust gegenüber dem üblichen
 * Versions-Versatz zwischen der npm-Playwright-Version und den im System
 * vorab bereitgestellten Browsern (z.B. wenn `npm install` Playwright anhebt,
 * die Umgebung aber eine ältere Chromium-Revision bereitstellt).
 *
 * Auflösungsreihenfolge:
 *   ffmpeg   : FFMPEG_PATH → `which ffmpeg` → gebündeltes Playwright-ffmpeg
 *   chromium : PLAYWRIGHT_CHROMIUM_PATH → bereitgestelltes Chromium unter
 *              PLAYWRIGHT_BROWSERS_PATH → (null = Playwright-Standardauflösung)
 */

/** Sucht die erste existierende Datei, die zu einem Glob-ähnlichen Muster passt. */
function firstMatch(dir: string, predicate: (name: string) => boolean, ...sub: string[]): string | null {
  if (!dir || !fs.existsSync(dir)) return null;
  const matches = fs.readdirSync(dir).filter(predicate).sort().reverse(); // höchste Revision zuerst
  for (const name of matches) {
    const candidate = path.join(dir, name, ...sub);
    if (fs.existsSync(candidate)) return candidate;
  }
  return null;
}

/**
 * Liefert den ffmpeg-Pfad oder null, wenn keiner gefunden wird.
 *
 * Wichtig: Das von Playwright gebündelte ffmpeg ist ein Minimal-Build (kein
 * x11grab, kein mp3/mp4-Muxing) und wird daher bewusst NICHT als Fallback
 * genutzt – die Pipeline benötigt ein vollständiges ffmpeg.
 */
export function resolveFfmpeg(): string | null {
  const explicit = process.env.FFMPEG_PATH?.trim();
  if (explicit && fs.existsSync(explicit)) return explicit;

  try {
    const found = execSync('command -v ffmpeg', { encoding: 'utf-8', timeout: 3000 }).trim();
    if (found && fs.existsSync(found)) return found;
  } catch {
    // nicht auf PATH
  }

  return null;
}

/**
 * Liefert die zu verwendende ffmpeg-Binary für In-Process-Aufrufe (execSync).
 * Greift auf den von reel-cli aufgelösten und in FFMPEG_PATH gesetzten Pfad zurück.
 */
export function ffmpegBin(): string {
  return process.env.FFMPEG_PATH?.trim() || 'ffmpeg';
}

/**
 * Liefert die passende ffprobe-Binary. Leitet sie aus dem ffmpeg-Pfad ab
 * (gleiches Verzeichnis), da ffprobe üblicherweise neben ffmpeg liegt.
 */
export function ffprobeBin(): string {
  const ff = ffmpegBin();
  if (ff === 'ffmpeg') return 'ffprobe';
  const probe = path.join(path.dirname(ff), path.basename(ff).replace('ffmpeg', 'ffprobe'));
  return fs.existsSync(probe) ? probe : 'ffprobe';
}

/**
 * Liefert einen expliziten Chromium-Pfad oder null. Bei null nutzt Playwright
 * seine Standardauflösung (passende Revision unter PLAYWRIGHT_BROWSERS_PATH).
 */
export function resolveChromium(): string | null {
  const explicit = process.env.PLAYWRIGHT_CHROMIUM_PATH?.trim();
  if (explicit && fs.existsSync(explicit)) return explicit;

  const browsersPath = process.env.PLAYWRIGHT_BROWSERS_PATH?.trim();
  if (browsersPath) {
    // Übliche Layouts: chrome-linux64/chrome (neuer) und chrome-linux/chrome (älter)
    const candidate =
      firstMatch(browsersPath, (n) => n.startsWith('chromium-'), 'chrome-linux64', 'chrome') ||
      firstMatch(browsersPath, (n) => n.startsWith('chromium-'), 'chrome-linux', 'chrome');
    if (candidate) return candidate;
  }

  return null;
}
