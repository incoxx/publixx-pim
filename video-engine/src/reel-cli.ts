/**
 * Reel-CLI – Orchestriert die Erzeugung eines Social-Media-Produktvideos aus einer
 * Reel-Definition (JSON). Wird vom Laravel-Job RenderSocialVideoJob aufgerufen:
 *
 *   npx tsx src/reel-cli.ts <reel.json> <output.mp4>
 *
 * Wiederverwendung der bestehenden Pipeline:
 *   reel-renderer (HTML-Szenen) → Recorder (Xvfb+ffmpeg) → VoiceSynthesizer → video-renderer
 */
import fs from 'fs';
import path from 'path';
import { spawn } from 'child_process';
import { generateReelScript, type ReelDefinition } from './reel-renderer';
import { VoiceSynthesizer } from './voice-synthesizer';
import { renderVideo } from './video-renderer';
import { createStoryLogger } from './logger';
import { resolveFfmpeg, resolveChromium } from './runtime-deps';

/**
 * Führt das generierte Reel-Script headless aus (Playwright recordVideo).
 * Kein Xvfb/Screengrab nötig – das Script legt recording.webm in tmpDir ab.
 */
function runReelScript(scriptPath: string, tmpDir: string, timestampsFile: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const env = {
      ...process.env,
      NODE_PATH: path.resolve(__dirname, '../node_modules'),
      TIMESTAMPS_FILE: timestampsFile,
      REEL_VIDEO_DIR: tmpDir,
    };
    const tsxBin = path.resolve(__dirname, '../node_modules/.bin/tsx');
    const child = spawn(tsxBin, [scriptPath], { cwd: path.resolve(__dirname, '..'), env, stdio: 'inherit' });
    child.on('close', (code) => (code === 0 ? resolve() : reject(new Error('Reel-Script beendet mit Code ' + code))));
    child.on('error', (e) => reject(new Error('Reel-Script Fehler: ' + e.message)));
  });
}

async function main(): Promise<void> {
  const reelPath = process.argv[2];
  const outputPath = process.argv[3];

  if (!reelPath || !outputPath) {
    console.error('Usage: tsx src/reel-cli.ts <reel.json> <output.mp4>');
    process.exit(1);
  }
  if (!fs.existsSync(reelPath)) {
    console.error('Reel-Definition nicht gefunden: ' + reelPath);
    process.exit(1);
  }

  // Laufzeit-Abhängigkeiten vorab auflösen, damit der Nutzer eine klare,
  // umsetzbare Fehlermeldung erhält statt eines kryptischen "Code 1".
  const ffmpegPath = resolveFfmpeg();
  if (!ffmpegPath) {
    console.error(
      'ffmpeg nicht gefunden. Bitte ffmpeg installieren (z.B. `apt-get install ffmpeg`) '
      + 'oder FFMPEG_PATH auf eine ffmpeg-Binary setzen.',
    );
    process.exit(1);
  }
  const chromiumPath = resolveChromium();
  if (!chromiumPath) {
    console.error(
      'Chromium für Playwright nicht gefunden. Bitte `npx playwright install chromium` ausführen '
      + 'oder PLAYWRIGHT_CHROMIUM_PATH auf eine Chrome-/Chromium-Binary setzen.',
    );
    process.exit(1);
  }
  // An Recorder (ffmpeg) und das generierte Script (chromium) vererben.
  process.env.FFMPEG_PATH = ffmpegPath;
  process.env.PLAYWRIGHT_CHROMIUM_PATH = chromiumPath;

  const reel: ReelDefinition = JSON.parse(fs.readFileSync(reelPath, 'utf-8'));
  const logger = createStoryLogger(reel.meta.id || 'reel');

  const baseUrl = process.env.ANYPIM_BASE_URL || process.env.VIDEO_ENGINE_BASE_URL || 'http://localhost:8000';
  const quality = (process.env.VIDEO_QUALITY || process.env.VIDEO_ENGINE_QUALITY || 'high') as 'high' | 'medium' | 'low';

  // 1. Arbeitsverzeichnis für diesen Render-Lauf
  const tmpDir = path.join(
    path.resolve(__dirname, '../../storage/video-engine/tmp'),
    (reel.meta.id || 'reel') + '-' + Date.now(),
  );
  fs.mkdirSync(tmpDir, { recursive: true });

  // 1a. Voiceover VORAB synthetisieren und Szenendauer an die Sprechlänge koppeln,
  //     damit Bild/Untertitel synchron zum (KI-)Text laufen und nicht zu früh enden.
  try {
    const preSynth = new VoiceSynthesizer(logger);
    const durations = await preSynth.measureSceneDurations(reel.scenes, tmpDir, reel.meta.voice || {});
    const MIN_MS = 1800;   // Mindestdauer je Szene (Lesbarkeit)
    const PAD_MS = 700;    // Puffer nach dem Sprechen (Atempause)
    let coupled = 0;
    reel.scenes.forEach((scene, i) => {
      const audioMs = durations[i] || 0;
      if (audioMs > 0) {
        scene.duration = Math.max(MIN_MS, audioMs + PAD_MS);
        coupled++;
      }
    });
    logger.info(`Szenendauer an Sprechlänge gekoppelt: ${coupled}/${reel.scenes.length} Szenen`);
  } catch (err) {
    logger.warn('Vorab-Voiceover fehlgeschlagen, nutze Default-Szenendauern: ' + (err as Error).message);
  }

  const scriptPath = path.join(tmpDir, 'reel-script.ts');
  fs.writeFileSync(scriptPath, generateReelScript(reel, baseUrl));

  const timestampsFile = path.join(tmpDir, 'timestamps.json');

  // 2. Aufnahme: generiertes Script headless ausführen (Playwright recordVideo) →
  //    sauberer Seiteninhalt ohne Browserleiste, kein Xvfb/Screengrab nötig.
  await runReelScript(scriptPath, tmpDir, timestampsFile);
  const recordingPath = path.join(tmpDir, 'recording.webm');
  if (! fs.existsSync(recordingPath)) {
    throw new Error('Aufnahme fehlgeschlagen: recording.webm wurde nicht erzeugt.');
  }

  // 3. Voiceover an den Aufnahme-Timestamps ausrichten
  let audioPath: string | null = null;
  try {
    const fakeStory = { meta: { voice: reel.meta.voice || {} }, steps: [] } as never;
    const synth = new VoiceSynthesizer(logger);
    audioPath = await synth.synthesize(fakeStory, tmpDir, fs.existsSync(timestampsFile) ? timestampsFile : undefined);
  } catch (err) {
    logger.warn('Voiceover fehlgeschlagen, Video ohne Audio: ' + (err as Error).message);
  }

  // 4. Finales Video rendern (Video + Audio + optional Sonic-Logo)
  const sonicLogo = path.resolve(__dirname, '../../video-stories/demo-assets/anypim-sonic-logo.mp3');
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });

  await renderVideo({
    videoPath: recordingPath,
    audioPath,
    srtPath: null,
    sonicLogoPath: fs.existsSync(sonicLogo) ? sonicLogo : null,
    outputPath,
    quality,
    logger,
  });

  // Aufräumen
  try { fs.rmSync(tmpDir, { recursive: true, force: true }); } catch { /* ignore */ }

  console.log('OK: ' + outputPath);
}

main().catch(err => {
  console.error('Reel-Rendering fehlgeschlagen:', err?.message || err);
  process.exit(1);
});
