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
