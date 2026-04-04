import fs from 'fs';
import type { Story, StoryStep } from './story-validator';

/** Timestamp-Eintrag aus dem Playwright-Script */
export interface RecordedTimestamp {
  id: string;
  sprecher: string;
  startMs: number;
  endMs?: number;
}

interface SrtEntry {
  index: number;
  startMs: number;
  endMs: number;
  text: string;
}

/**
 * Berechnet die Dauer eines Steps in Millisekunden.
 */
function stepDuration(step: StoryStep, slowMo: number): number {
  let duration = slowMo;

  // Tipp-Dauer bei fill-Action
  if (step.action === 'fill' && step.value) {
    const typeSpeed = step.type_speed || 0;
    duration += typeSpeed * step.value.length;
  }

  // pause_after
  if (step.pause_after) {
    duration += step.pause_after;
  }

  // wait-Action hat eigene Duration
  if (step.action === 'wait' && step.duration) {
    duration = step.duration;
  }

  return Math.max(duration, 500);
}

/**
 * Berechnet die Anzeigedauer eines Texts basierend auf Lesegeschwindigkeit.
 * ~60ms pro Zeichen, mindestens 2 Sekunden.
 */
function textDisplayDuration(text: string): number {
  return Math.max(text.length * 60, 2000);
}

/**
 * Extrahiert Sprecher-Texte aus einer Story und erzeugt SRT-Daten.
 */
export function extractSubtitles(story: Story): SrtEntry[] {
  const entries: SrtEntry[] = [];
  const slowMo = story.meta.slow_mo || 600;
  let currentTimeMs = 0;
  let entryIndex = 1;

  for (const step of story.steps) {
    const duration = stepDuration(step, slowMo);

    if (step.sprecher && step.sprecher.trim() !== '') {
      const displayDuration = Math.max(textDisplayDuration(step.sprecher), duration);

      entries.push({
        index: entryIndex++,
        startMs: currentTimeMs,
        endMs: currentTimeMs + displayDuration,
        text: step.sprecher,
      });
    }

    currentTimeMs += duration;
  }

  // Überlappungen auflösen
  for (let i = 1; i < entries.length; i++) {
    if (entries[i].startMs < entries[i - 1].endMs) {
      entries[i - 1].endMs = entries[i].startMs - 100;
      if (entries[i - 1].endMs <= entries[i - 1].startMs) {
        entries[i - 1].endMs = entries[i - 1].startMs + 500;
      }
    }
  }

  return entries;
}

/**
 * Formatiert Millisekunden als SRT-Timecode (HH:MM:SS,mmm)
 */
function formatTimecode(ms: number): string {
  const hours = Math.floor(ms / 3600000);
  const minutes = Math.floor((ms % 3600000) / 60000);
  const seconds = Math.floor((ms % 60000) / 1000);
  const millis = ms % 1000;

  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')},${String(millis).padStart(3, '0')}`;
}

/**
 * Erzeugt SRT-Dateiinhalt aus SRT-Entries.
 */
export function generateSrt(entries: SrtEntry[]): string {
  return entries
    .map((entry) => {
      return `${entry.index}\n${formatTimecode(entry.startMs)} --> ${formatTimecode(entry.endMs)}\n${entry.text}\n`;
    })
    .join('\n');
}

/**
 * Gibt die Sprecher-Texte als Array zurück (für TTS).
 */
export function extractSprecherTexts(story: Story): { stepId: string; text: string; startMs: number }[] {
  const texts: { stepId: string; text: string; startMs: number }[] = [];
  const slowMo = story.meta.slow_mo || 600;
  let currentTimeMs = 0;

  for (const step of story.steps) {
    const duration = stepDuration(step, slowMo);

    if (step.sprecher && step.sprecher.trim() !== '') {
      texts.push({
        stepId: step.id,
        text: step.sprecher,
        startMs: currentTimeMs,
      });
    }

    currentTimeMs += duration;
  }

  return texts;
}

/**
 * Erzeugt SRT-Einträge aus echten Aufnahme-Timestamps (statt geschätzten Dauern).
 * Die Timestamps werden vom Playwright-Script während der Aufnahme geschrieben.
 */
export function extractSubtitlesFromTimestamps(timestampsFile: string): SrtEntry[] {
  if (!fs.existsSync(timestampsFile)) {
    return [];
  }

  const timestamps: RecordedTimestamp[] = JSON.parse(fs.readFileSync(timestampsFile, 'utf-8'));
  const entries: SrtEntry[] = [];
  let entryIndex = 1;

  for (const ts of timestamps) {
    if (!ts.sprecher || ts.sprecher.trim() === '') continue;

    const startMs = ts.startMs;
    // Ende = endMs des Steps, oder Start + Lesezeit (min 3s)
    const readDuration = Math.max(ts.sprecher.length * 60, 3000);
    const endMs = ts.endMs ? Math.min(ts.endMs, startMs + readDuration) : startMs + readDuration;

    entries.push({
      index: entryIndex++,
      startMs,
      endMs,
      text: ts.sprecher,
    });
  }

  // Überlappungen auflösen
  for (let i = 1; i < entries.length; i++) {
    if (entries[i].startMs < entries[i - 1].endMs) {
      entries[i - 1].endMs = entries[i].startMs - 100;
      if (entries[i - 1].endMs <= entries[i - 1].startMs) {
        entries[i - 1].endMs = entries[i - 1].startMs + 500;
      }
    }
  }

  return entries;
}
