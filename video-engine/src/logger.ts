import winston from 'winston';
import path from 'path';
import fs from 'fs';

const LOG_DIR = path.resolve(__dirname, '../../storage/video-engine/logs');

// Sicherstellen, dass Log-Verzeichnis existiert
if (!fs.existsSync(LOG_DIR)) {
  fs.mkdirSync(LOG_DIR, { recursive: true });
}

/**
 * Erstellt einen Logger für eine bestimmte Story.
 * Schreibt nach storage/video-engine/logs/{storyId}-{timestamp}.log
 */
export function createStoryLogger(storyId: string): winston.Logger {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const logFile = path.join(LOG_DIR, `${storyId}-${timestamp}.log`);

  const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
      winston.format.timestamp({ format: 'HH:mm:ss.SSS' }),
      winston.format.printf(({ timestamp, level, message, prefix }) => {
        const pfx = prefix ? `[${prefix}]`.padEnd(12) : '';
        return `${timestamp} ${pfx} ${message}`;
      }),
    ),
    transports: [
      new winston.transports.File({ filename: logFile }),
      new winston.transports.Console({
        format: winston.format.combine(
          winston.format.colorize(),
          winston.format.timestamp({ format: 'HH:mm:ss' }),
          winston.format.printf(({ timestamp, message, prefix }) => {
            const pfx = prefix ? `[${prefix}]`.padEnd(12) : '';
            return `${timestamp} ${pfx} ${message}`;
          }),
        ),
      }),
    ],
  });

  return logger;
}

/** Hilfsfunktionen für prefixed Logging */
export function preflight(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'PREFLIGHT' });
}

export function seeder(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'SEEDER' });
}

export function record(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'RECORD' });
}

export function step(logger: winston.Logger, current: number, total: number, id: string, msg: string): void {
  logger.info(`${id} → ${msg}`, { prefix: `STEP ${current}/${total}` });
}

export function stepWarn(logger: winston.Logger, current: number, total: number, msg: string): void {
  logger.warn(msg, { prefix: `STEP ${current}/${total}` });
}

export function audio(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'AUDIO' });
}

export function render(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'RENDER' });
}

export function output(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'OUTPUT' });
}

export function done(logger: winston.Logger, msg: string): void {
  logger.info(msg, { prefix: 'DONE' });
}

export function error(logger: winston.Logger, msg: string): void {
  logger.error(msg, { prefix: 'ERROR' });
}
