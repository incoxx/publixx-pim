import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { execSync, spawnSync } from 'child_process';
import winston from 'winston';
import * as log from './logger';
import type { Story } from './story-validator';
import { extractSprecherTexts, type RecordedTimestamp } from './subtitle-extractor';

interface VoiceConfig {
  lang: string;
  gender: string;
  provider: string;
  voiceId: string;
}

/**
 * Synthesiert Sprechertext zu Audio via ElevenLabs oder gTTS Fallback.
 */
export class VoiceSynthesizer {
  private apiKey: string;
  private fallback: string;
  private logger: winston.Logger;
  private cacheDir: string;

  constructor(logger: winston.Logger) {
    this.apiKey = process.env.ELEVENLABS_API_KEY || '';
    this.fallback = process.env.ELEVENLABS_FALLBACK || 'gtts';
    this.logger = logger;
    this.cacheDir = path.resolve(__dirname, '../../storage/video-engine/audio-cache');
    if (!fs.existsSync(this.cacheDir)) {
      fs.mkdirSync(this.cacheDir, { recursive: true });
    }
  }

  /** Erzeugt einen Cache-Key aus Text + Voice-Config */
  private getCacheKey(text: string, voiceConfig: VoiceConfig): string {
    const input = `${text}|${voiceConfig.provider}|${voiceConfig.voiceId}|${voiceConfig.lang}|${voiceConfig.gender}`;
    return crypto.createHash('sha256').update(input).digest('hex').substring(0, 32);
  }

  /** Prüft ob ein gecachtes Audio-Segment existiert */
  private getCached(text: string, voiceConfig: VoiceConfig): string | null {
    const key = this.getCacheKey(text, voiceConfig);
    const cachedPath = path.join(this.cacheDir, `${key}.mp3`);
    if (fs.existsSync(cachedPath)) {
      return cachedPath;
    }
    return null;
  }

  /** Speichert ein Audio-Segment im Cache */
  private saveToCache(text: string, voiceConfig: VoiceConfig, audioPath: string): void {
    const key = this.getCacheKey(text, voiceConfig);
    const cachedPath = path.join(this.cacheDir, `${key}.mp3`);
    fs.copyFileSync(audioPath, cachedPath);
  }

  /** Erzeugt Audio für alle Sprecher-Texte einer Story, synchronisiert mit Timestamps */
  async synthesize(story: Story, outputDir: string, timestampsFile?: string): Promise<string | null> {
    // Timestamps laden (falls vorhanden)
    let timestamps: RecordedTimestamp[] | null = null;
    if (timestampsFile && fs.existsSync(timestampsFile)) {
      timestamps = JSON.parse(fs.readFileSync(timestampsFile, 'utf-8'));
      log.audio(this.logger, 'Synchronisiere Audio mit echten Aufnahme-Timestamps');
    }

    // Sprecher-Texte mit Timestamps ermitteln
    const sprecherEntries: { stepId: string; text: string; startMs: number }[] = [];

    if (timestamps) {
      // Echte Timestamps verwenden
      for (const ts of timestamps) {
        if (ts.sprecher && ts.sprecher.trim() !== '') {
          sprecherEntries.push({ stepId: ts.id, text: ts.sprecher, startMs: ts.startMs });
        }
      }
    } else {
      // Fallback: geschätzte Timestamps
      const texts = extractSprecherTexts(story);
      sprecherEntries.push(...texts);
    }

    if (sprecherEntries.length === 0) {
      log.audio(this.logger, 'Keine Sprechertexte vorhanden – kein Audio');
      return null;
    }

    const voiceConfig = this.getVoiceConfig(story);
    const audioSegments: { path: string; startMs: number }[] = [];
    let elevenLabsFailed = false; // Bei 401/403 alle weiteren Versuche überspringen

    log.audio(this.logger, `${sprecherEntries.length} Sprechertexte, Provider: ${voiceConfig.provider}`);

    // Einzelne Audio-Segmente erzeugen (mit Cache)
    let cacheHits = 0;
    let cacheMisses = 0;

    for (let i = 0; i < sprecherEntries.length; i++) {
      const { stepId, text, startMs } = sprecherEntries[i];
      const outputPath = path.join(outputDir, `audio-${String(i).padStart(3, '0')}-${stepId}.mp3`);

      // Cache prüfen
      const cached = this.getCached(text, voiceConfig);
      if (cached) {
        fs.copyFileSync(cached, outputPath);
        audioSegments.push({ path: outputPath, startMs });
        cacheHits++;
        continue;
      }

      try {
        if (voiceConfig.provider === 'elevenlabs' && this.apiKey && !elevenLabsFailed) {
          await this.synthesizeElevenLabs(text, voiceConfig, outputPath);
        } else {
          await this.synthesizeGtts(text, voiceConfig.lang, outputPath);
        }
        // Im Cache speichern
        this.saveToCache(text, voiceConfig, outputPath);
        audioSegments.push({ path: outputPath, startMs });
        cacheMisses++;
      } catch (err) {
        const errMsg = err instanceof Error ? err.message : String(err);
        log.audio(this.logger, `WARN: Audio für "${stepId}" fehlgeschlagen: ${errMsg}`);

        // Bei Auth-Fehler: ElevenLabs komplett deaktivieren
        if (errMsg.includes('401') || errMsg.includes('403') || errMsg.includes('Unauthorized')) {
          elevenLabsFailed = true;
          log.audio(this.logger, 'ElevenLabs API Key ungültig – deaktiviere für alle weiteren Segmente');
        }

        // Fallback versuchen
        try {
          if (this.fallback === 'gtts') {
            log.audio(this.logger, `Fallback zu gTTS für "${stepId}"`);
            await this.synthesizeGtts(text, voiceConfig.lang, outputPath);
          } else {
            await this.createSilence(outputPath, text.length * 100);
          }
          audioSegments.push({ path: outputPath, startMs });
        } catch {
          // gTTS auch fehlgeschlagen → Stille
          log.audio(this.logger, `gTTS fehlgeschlagen – erzeuge Stille für "${stepId}"`);
          await this.createSilence(outputPath, text.length * 100);
          audioSegments.push({ path: outputPath, startMs });
        }
      }
    }

    // Cache-Statistik
    if (cacheHits > 0 || cacheMisses > 0) {
      log.audio(this.logger, `Cache: ${cacheHits} Treffer, ${cacheMisses} neu generiert`);
    }

    if (audioSegments.length === 0) {
      return null;
    }

    // Audio-Segment-Dauern messen und speichern (für SRT-Synchronisierung)
    const segmentInfos = audioSegments.map(seg => ({
      stepId: sprecherEntries.find(e => seg.path.includes(e.stepId))?.stepId || '',
      startMs: seg.startMs,
      durationMs: Math.round(this.getAudioDuration(seg.path) * 1000),
    }));
    const segmentsInfoFile = path.join(outputDir, 'audio-segments.json');
    fs.writeFileSync(segmentsInfoFile, JSON.stringify(segmentInfos, null, 2));
    log.audio(this.logger, `Audio-Segment-Dauern gespeichert: ${segmentsInfoFile}`);

    // Audio-Segmente an den richtigen Zeitpunkten positionieren
    const mergedPath = path.join(outputDir, 'voiceover.mp3');
    await this.mergeWithTimestamps(audioSegments, mergedPath);

    const totalDuration = this.getAudioDuration(mergedPath);
    log.audio(this.logger, `Voiceover erzeugt: ${totalDuration.toFixed(1)}s (${audioSegments.length} Segmente)`);

    return mergedPath;
  }

  private getVoiceConfig(story: Story): VoiceConfig {
    const voice = story.meta.voice || {};
    const lang = voice.lang || 'de';
    const gender = voice.gender || 'female';

    // Voice ID aus Config oder Environment
    let voiceId = voice.voice_id || '';
    if (!voiceId) {
      const envKey = `ELEVENLABS_VOICE_${lang.toUpperCase()}_${gender.toUpperCase()}`;
      voiceId = process.env[envKey] || '';
    }

    return {
      lang,
      gender,
      provider: (this.apiKey && voice.provider !== 'gtts') ? 'elevenlabs' : 'gtts',
      voiceId,
    };
  }

  /** ElevenLabs API Aufruf mit Retry */
  private async synthesizeElevenLabs(text: string, config: VoiceConfig, outputPath: string): Promise<void> {
    const voiceId = config.voiceId || 'EXAVITQu4vr4xnSDxMaL'; // Default: Bella
    const url = `https://api.elevenlabs.io/v1/text-to-speech/${voiceId}`;

    const delays = [5000, 15000, 45000];
    let lastError: Error | null = null;

    for (let attempt = 0; attempt <= delays.length; attempt++) {
      try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);

        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'xi-api-key': this.apiKey,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            text,
            model_id: 'eleven_multilingual_v2',
            voice_settings: {
              stability: 0.5,
              similarity_boost: 0.75,
            },
          }),
          signal: controller.signal,
        });

        clearTimeout(timeout);

        if (res.status === 429) {
          // Rate Limit
          if (attempt < delays.length) {
            log.audio(this.logger, `Rate Limit – Retry in ${delays[attempt] / 1000}s`);
            await new Promise(resolve => setTimeout(resolve, delays[attempt]));
            continue;
          }
          throw new Error('ElevenLabs Rate Limit erschöpft');
        }

        if (!res.ok) {
          throw new Error(`ElevenLabs HTTP ${res.status}`);
        }

        const buffer = Buffer.from(await res.arrayBuffer());
        fs.writeFileSync(outputPath, buffer);
        return;
      } catch (err) {
        lastError = err as Error;
        if (attempt < delays.length && (err as Error).name !== 'AbortError') {
          await new Promise(resolve => setTimeout(resolve, delays[attempt]));
          continue;
        }
      }
    }

    throw lastError || new Error('ElevenLabs Synthese fehlgeschlagen');
  }

  /** gTTS Fallback (Google Text-to-Speech, kostenlos) */
  private async synthesizeGtts(text: string, lang: string, outputPath: string): Promise<void> {
    // Sprache gegen Whitelist validieren
    if (!/^[a-z]{2,5}(-[A-Z]{2})?$/.test(lang)) {
      throw new Error(`Ungültige Sprache: ${lang}`);
    }
    // gTTS über Python aufrufen – spawnSync vermeidet Shell-Injection
    const result = spawnSync('python3', ['-m', 'gtts', '--lang', lang, '--output', outputPath, text], {
      timeout: 30000,
    });
    if (result.status !== 0) {
      throw new Error(`gTTS fehlgeschlagen: ${result.stderr?.toString() || 'unbekannter Fehler'}`);
    }
  }

  /**
   * Fügt Audio-Segmente an den richtigen Zeitpunkten zusammen.
   * Zwischen den Segmenten wird Stille eingefügt basierend auf den echten Timestamps.
   */
  private async mergeWithTimestamps(
    segments: { path: string; startMs: number }[],
    outputPath: string,
  ): Promise<void> {
    if (segments.length === 0) return;

    if (segments.length === 1) {
      // Einzelnes Segment: nur Stille am Anfang einfügen
      const startSilence = segments[0].startMs / 1000;
      if (startSilence > 0.1) {
        const silencePath = path.join(path.dirname(outputPath), 'silence-start.mp3');
        await this.createSilence(silencePath, segments[0].startMs);
        const listFile = path.join(path.dirname(outputPath), 'concat-ts.txt');
        fs.writeFileSync(listFile, `file '${silencePath}'\nfile '${segments[0].path}'`);
        execSync(`ffmpeg -y -f concat -safe 0 -i "${listFile}" -c copy "${outputPath}"`, { timeout: 30000 });
      } else {
        fs.copyFileSync(segments[0].path, outputPath);
      }
      return;
    }

    // Mehrere Segmente: Stille zwischen den Segmenten basierend auf Timestamps
    const listFile = path.join(path.dirname(outputPath), 'concat-ts.txt');
    const lines: string[] = [];
    let currentPositionMs = 0;

    for (let i = 0; i < segments.length; i++) {
      const segment = segments[i];

      // Stille vor dem Segment einfügen (Differenz zu Ziel-Startzeit)
      const gapMs = segment.startMs - currentPositionMs;
      if (gapMs > 100) {
        const silencePath = path.join(path.dirname(outputPath), `silence-ts-${i}.mp3`);
        await this.createSilence(silencePath, gapMs);
        lines.push(`file '${silencePath}'`);
        currentPositionMs += gapMs;
      }

      // Audio-Segment einfügen
      lines.push(`file '${segment.path}'`);
      const segmentDuration = this.getAudioDuration(segment.path) * 1000;
      currentPositionMs += segmentDuration;
    }

    fs.writeFileSync(listFile, lines.join('\n'));
    execSync(`ffmpeg -y -f concat -safe 0 -i "${listFile}" -c copy "${outputPath}"`, { timeout: 60000 });
  }

  /** Erstellt eine stille MP3-Datei */
  private async createSilence(outputPath: string, durationMs: number): Promise<void> {
    const seconds = Math.max(durationMs / 1000, 0.5);
    execSync(
      `ffmpeg -y -f lavfi -i anullsrc=r=44100:cl=mono -t ${seconds} -q:a 9 "${outputPath}"`,
      { timeout: 10000 },
    );
  }

  /** Führt Audio-Dateien mit Pausen zusammen */
  private async mergeAudioFiles(
    files: string[],
    texts: { startMs: number }[],
    outputPath: string,
  ): Promise<void> {
    if (files.length === 1) {
      fs.copyFileSync(files[0], outputPath);
      return;
    }

    // ffmpeg concat mit Stille-Padding zwischen Segmenten
    const listFile = path.join(path.dirname(outputPath), 'concat-list.txt');
    const lines: string[] = [];

    for (let i = 0; i < files.length; i++) {
      lines.push(`file '${files[i]}'`);

      // Stille zwischen aktuellem und nächstem Segment
      if (i < files.length - 1 && i + 1 < texts.length) {
        const currentDuration = this.getAudioDuration(files[i]);
        const gap = (texts[i + 1].startMs - texts[i].startMs) / 1000 - currentDuration;
        if (gap > 0.1) {
          const silencePath = path.join(path.dirname(outputPath), `silence-${i}.mp3`);
          execSync(`ffmpeg -y -f lavfi -i anullsrc=r=44100:cl=mono -t ${gap.toFixed(2)} -q:a 9 "${silencePath}"`, { timeout: 5000 });
          lines.push(`file '${silencePath}'`);
        }
      }
    }

    fs.writeFileSync(listFile, lines.join('\n'));
    execSync(`ffmpeg -y -f concat -safe 0 -i "${listFile}" -c copy "${outputPath}"`, { timeout: 60000 });
  }

  /** Gibt die Dauer einer Audio-Datei in Sekunden zurück */
  private getAudioDuration(filePath: string): number {
    try {
      const output = execSync(
        `ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${filePath}"`,
        { encoding: 'utf-8', timeout: 5000 },
      ).trim();
      return parseFloat(output) || 0;
    } catch {
      return 0;
    }
  }
}
