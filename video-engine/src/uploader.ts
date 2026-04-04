import fs from 'fs';
import path from 'path';
import winston from 'winston';
import * as log from './logger';

export interface UploadOptions {
  sourcePath: string;
  storyId: string;
  outputDir: string;
  s3Upload: boolean;
  logger: winston.Logger;
}

/**
 * Kopiert das fertige Video ins Ausgabeverzeichnis.
 * Optional: S3-Upload (wenn konfiguriert).
 */
export async function uploadVideo(opts: UploadOptions): Promise<string> {
  const { sourcePath, storyId, outputDir, s3Upload, logger } = opts;

  if (!fs.existsSync(sourcePath)) {
    throw new Error(`Quell-Video nicht gefunden: ${sourcePath}`);
  }

  // Lokale Kopie
  const resolvedOutputDir = path.resolve(__dirname, '../..', outputDir);
  if (!fs.existsSync(resolvedOutputDir)) {
    fs.mkdirSync(resolvedOutputDir, { recursive: true });
  }

  const fileName = `${storyId}.mp4`;
  const destPath = path.join(resolvedOutputDir, fileName);

  fs.copyFileSync(sourcePath, destPath);
  log.output(logger, `Video kopiert: ${destPath}`);

  // SRT-Datei mitkopieren (falls vorhanden)
  const srtSource = sourcePath.replace(/\.mp4$/, '.srt');
  if (fs.existsSync(srtSource)) {
    const srtDest = path.join(resolvedOutputDir, `${storyId}.srt`);
    fs.copyFileSync(srtSource, srtDest);
    log.output(logger, `Untertitel kopiert: ${srtDest}`);
  }

  // S3-Upload (optional)
  if (s3Upload) {
    log.output(logger, 'S3-Upload ist konfiguriert aber noch nicht implementiert');
    // Platzhalter für zukünftige S3-Integration
  }

  return destPath;
}
