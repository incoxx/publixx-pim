import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import winston from 'winston';
import * as log from './logger.js';
import type { Story } from './story-validator.js';
import { extractSprecherTexts } from './subtitle-extractor.js';

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

  constructor(logger: winston.Logger) {
    this.apiKey = process.env.ELEVENLABS_API_KEY || '';
    this.fallback = process.env.ELEVENLABS_FALLBACK || 'gtts';
    this.logger = logger;
  }

  /** Erzeugt Audio für alle Sprecher-Texte einer Story */
  async synthesize(story: Story, outputDir: string): Promise<string | null> {
    const texts = extractSprecherTexts(story);

    if (texts.length === 0) {
      log.audio(this.logger, 'Keine Sprechertexte vorhanden – kein Audio');
      return null;
    }

    const voiceConfig = this.getVoiceConfig(story);
    const audioFiles: string[] = [];

    log.audio(this.logger, `${texts.length} Sprechertexte, Provider: ${voiceConfig.provider}`);

    for (let i = 0; i < texts.length; i++) {
      const { stepId, text } = texts[i];
      const outputPath = path.join(outputDir, `audio-${String(i).padStart(3, '0')}-${stepId}.mp3`);

      try {
        if (voiceConfig.provider === 'elevenlabs' && this.apiKey) {
          await this.synthesizeElevenLabs(text, voiceConfig, outputPath);
        } else {
          await this.synthesizeGtts(text, voiceConfig.lang, outputPath);
        }
        audioFiles.push(outputPath);
      } catch (err) {
        log.audio(this.logger, `WARN: Audio für "${stepId}" fehlgeschlagen: ${(err as Error).message}`);

        // Fallback versuchen
        if (voiceConfig.provider === 'elevenlabs') {
          try {
            log.audio(this.logger, `Fallback zu ${this.fallback} für "${stepId}"`);
            if (this.fallback === 'gtts') {
              await this.synthesizeGtts(text, voiceConfig.lang, outputPath);
              audioFiles.push(outputPath);
            } else {
              // silent – leere Datei
              await this.createSilence(outputPath, text.length * 60);
              audioFiles.push(outputPath);
            }
          } catch {
            await this.createSilence(outputPath, text.length * 60);
            audioFiles.push(outputPath);
          }
        }
      }
    }

    if (audioFiles.length === 0) {
      return null;
    }

    // Audio-Dateien zusammenführen
    const mergedPath = path.join(outputDir, 'voiceover.mp3');
    await this.mergeAudioFiles(audioFiles, texts, mergedPath);

    const totalDuration = this.getAudioDuration(mergedPath);
    log.audio(this.logger, `Voiceover erzeugt: ${totalDuration}s`);

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
    // gTTS über Python aufrufen
    const escapedText = text.replace(/'/g, "'\\''");
    execSync(`python3 -m gtts --lang ${lang} --output "${outputPath}" '${escapedText}'`, {
      timeout: 30000,
    });
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
      if (i < files.length - 1) {
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
