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
import { generateReelScript, type ReelDefinition } from './reel-renderer';
import { Recorder } from './recorder';
import { VoiceSynthesizer } from './voice-synthesizer';
import { renderVideo } from './video-renderer';
import { createStoryLogger } from './logger';
import { resolveFfmpeg, resolveChromium } from './runtime-deps';

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
  const viewport = reel.meta.viewport || { width: 1080, height: 1920 };
  const display = process.env.VIDEO_DISPLAY || process.env.VIDEO_ENGINE_DISPLAY || ':99';
  const fps = parseInt(process.env.VIDEO_FPS || process.env.VIDEO_ENGINE_FPS || '30', 10);
  const quality = (process.env.VIDEO_QUALITY || process.env.VIDEO_ENGINE_QUALITY || 'high') as 'high' | 'medium' | 'low';

  // 1. Playwright-Script aus Reel-Definition erzeugen
  const recorder = new Recorder({
    storyId: reel.meta.id || 'reel',
    scriptPath: '', // wird gleich gesetzt
    display,
    fps,
    width: viewport.width,
    height: viewport.height,
    logger,
  });
  const tmpDir = recorder.getTmpDir();

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
  (recorder as unknown as { opts: { scriptPath: string } }).opts.scriptPath = scriptPath;

  // Timestamps-Datei, die das Script schreibt (vom Kindprozess geerbt)
  const timestampsFile = path.join(tmpDir, 'timestamps.json');
  process.env.TIMESTAMPS_FILE = timestampsFile;

  // Bare-Imports (playwright) im generierten Script über NODE_PATH auflösbar machen
  // (das generierte Script liegt außerhalb von video-engine/) – analog generate-one.sh.
  process.env.NODE_PATH = path.resolve(__dirname, '../node_modules');

  // 2. Aufnahme (Xvfb + ffmpeg + Playwright)
  let recordingPath: string;
  try {
    const usedDisplay = await recorder.startXvfb();
    recorder.startRecording(usedDisplay);
    await recorder.runScript(usedDisplay);
    recordingPath = await recorder.stop();
  } catch (err) {
    recorder.cleanup();
    throw err;
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
