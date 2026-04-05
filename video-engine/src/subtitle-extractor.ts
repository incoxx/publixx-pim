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

/** Audio-Segment-Info (geschrieben vom VoiceSynthesizer) */
export interface AudioSegmentInfo {
  stepId: string;
  startMs: number;
  durationMs: number;
}

/**
 * Erzeugt SRT-Einträge aus echten Aufnahme-Timestamps.
 * Wenn Audio-Segment-Infos vorhanden: Untertitel-Dauer = Audio-Dauer (synchron mit Stimme).
 * Ohne Audio: Dauer aus Textlänge geschätzt (100ms pro Zeichen, min 3s).
 */
export function extractSubtitlesFromTimestamps(
  timestampsFile: string,
  audioSegmentsFile?: string,
): SrtEntry[] {
  if (!fs.existsSync(timestampsFile)) {
    return [];
  }

  const timestamps: RecordedTimestamp[] = JSON.parse(fs.readFileSync(timestampsFile, 'utf-8'));

  // Audio-Segment-Dauern laden (falls vorhanden)
  let audioSegments: Map<string, AudioSegmentInfo> | null = null;
  if (audioSegmentsFile && fs.existsSync(audioSegmentsFile)) {
    const segments: AudioSegmentInfo[] = JSON.parse(fs.readFileSync(audioSegmentsFile, 'utf-8'));
    audioSegments = new Map(segments.map(s => [s.stepId, s]));
  }

  const entries: SrtEntry[] = [];
  let entryIndex = 1;

  // Audio-Verarbeitungslatenz ausgleichen: Untertitel leicht verzögern
  // damit sie synchron mit dem hörbaren Audio erscheinen
  const AUDIO_SYNC_OFFSET_MS = 1500;

  for (const ts of timestamps) {
    if (!ts.sprecher || ts.sprecher.trim() === '') continue;

    const startMs = ts.startMs + AUDIO_SYNC_OFFSET_MS;
    let endMs: number;

    if (audioSegments?.has(ts.id)) {
      // Audio-Dauer verwenden → Untertitel so lang wie die Stimme spricht
      const segInfo = audioSegments.get(ts.id)!;
      endMs = startMs + segInfo.durationMs;
    } else {
      // Fallback: 100ms pro Zeichen (deutsche Sprechgeschwindigkeit), min 3s
      endMs = startMs + Math.max(ts.sprecher.length * 100, 3000);
    }

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
      entries[i - 1].endMs = entries[i].startMs - 200;
      if (entries[i - 1].endMs <= entries[i - 1].startMs) {
        entries[i - 1].endMs = entries[i - 1].startMs + 1000;
      }
    }
  }

  return entries;
}
