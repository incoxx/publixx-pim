import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { config } from 'dotenv';
import { validateStory, type Story } from './story-validator';
import { createStoryLogger, preflight as logPreflight } from './logger';

// .env laden (video-engine/.env oder Root .env)
config({ path: path.resolve(__dirname, '../.env') });
config({ path: path.resolve(__dirname, '../../.env') });

interface CheckResult {
  name: string;
  ok: boolean;
  detail: string;
}

/** Prüft ob ein Kommando verfügbar ist */
function checkCommand(name: string, cmd: string): CheckResult {
  try {
    const output = execSync(cmd, { encoding: 'utf-8', timeout: 5000 }).trim();
    return { name, ok: true, detail: output.split('\n')[0] };
  } catch {
    return { name, ok: false, detail: 'Nicht gefunden' };
  }
}

/** Prüft ob eine URL erreichbar ist */
async function checkUrl(name: string, url: string): Promise<CheckResult> {
  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 5000);
    const res = await fetch(url, { signal: controller.signal });
    clearTimeout(timeout);
    return { name, ok: res.ok, detail: `HTTP ${res.status}` };
  } catch (e) {
    return { name, ok: false, detail: (e as Error).message };
  }
}

/** Prüft ob eine Umgebungsvariable gesetzt ist */
function checkEnv(name: string, envVar: string): CheckResult {
  const value = process.env[envVar];
  if (value && value.trim() !== '') {
    return { name, ok: true, detail: `${envVar} gesetzt` };
  }
  return { name, ok: false, detail: `${envVar} fehlt oder leer` };
}

/** Prüft verfügbaren Speicherplatz */
function checkDiskSpace(name: string, dirPath: string, minGB: number): CheckResult {
  try {
    const resolvedPath = path.resolve(__dirname, '../..', dirPath);
    if (!fs.existsSync(resolvedPath)) {
      fs.mkdirSync(resolvedPath, { recursive: true });
    }
    const output = execSync(`df -BG "${resolvedPath}" | tail -1 | awk '{print $4}'`, {
      encoding: 'utf-8',
    }).trim();
    const available = parseInt(output.replace('G', ''), 10);
    return {
      name,
      ok: available >= minGB,
      detail: `${available} GB verfügbar (min: ${minGB} GB)`,
    };
  } catch {
    return { name, ok: false, detail: 'Speicherplatz konnte nicht geprüft werden' };
  }
}

/** Prüft ob Story-Dateien vorhanden und valide sind */
function checkStory(storyPath: string): CheckResult {
  const result = validateStory(storyPath);
  if (result.valid) {
    return { name: 'Story YAML valide', ok: true, detail: result.story!.meta.title };
  }
  return {
    name: 'Story YAML valide',
    ok: false,
    detail: result.errors.join('; '),
  };
}

/** Prüft ob Demo-Assets vorhanden sind */
function checkDemoAssets(story: Story): CheckResult {
  const storiesDir = path.resolve(__dirname, '../../video-stories');
  const missingFiles: string[] = [];

  for (const step of story.steps) {
    if (step.action === 'upload' && step.file) {
      const filePath = path.resolve(storiesDir, step.file);
      if (!fs.existsSync(filePath)) {
        missingFiles.push(step.file);
      }
    }
  }

  if (missingFiles.length === 0) {
    return { name: 'Demo-Assets vorhanden', ok: true, detail: 'Alle Dateien vorhanden' };
  }
  return {
    name: 'Demo-Assets vorhanden',
    ok: false,
    detail: `Fehlend: ${missingFiles.join(', ')}`,
  };
}

/** Führt alle Preflight-Checks durch */
export async function runPreflight(storyPath?: string): Promise<{ allOk: boolean; results: CheckResult[] }> {
  const results: CheckResult[] = [];

  // System-Tools
  results.push(checkCommand('ffmpeg installiert', 'which ffmpeg'));
  results.push(checkCommand('Xvfb installiert', 'which Xvfb'));
  results.push(checkCommand('Node.js >= 18', 'node --version'));
  results.push(checkCommand('Playwright installiert', 'npx playwright --version 2>/dev/null'));

  // anyPIM erreichbar
  const baseUrl = process.env.ANYPIM_BASE_URL || process.env.VIDEO_ENGINE_BASE_URL || 'http://localhost:8000';
  results.push(await checkUrl('anyPIM erreichbar', baseUrl));

  // ElevenLabs API Key (optional – Warnung statt Fehler)
  const elevenLabsCheck = checkEnv('ElevenLabs API Key', 'ELEVENLABS_API_KEY');
  if (!elevenLabsCheck.ok) {
    elevenLabsCheck.detail += ' (Video ohne Voiceover)';
  }
  results.push(elevenLabsCheck);

  // Speicherplatz
  results.push(checkDiskSpace('Speicherplatz >= 2GB', 'storage/video-engine', 2));

  // Story-spezifische Checks
  if (storyPath) {
    const storyCheck = checkStory(storyPath);
    results.push(storyCheck);

    if (storyCheck.ok) {
      const { story } = validateStory(storyPath);
      if (story) {
        results.push(checkDemoAssets(story));
      }
    }
  }

  const allOk = results.every((r) => r.ok || r.name === 'ElevenLabs API Key');
  return { allOk, results };
}

// CLI-Modus
if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(__filename)) {
  const storyArg = process.argv[2];
  const storiesDir = path.resolve(__dirname, '../../video-stories');
  const storyPath = storyArg ? path.join(storiesDir, storyArg, 'story.yaml') : undefined;

  runPreflight(storyPath).then(({ allOk, results }) => {
    console.log('\n=== anyPIM Video Engine – Preflight Check ===\n');

    for (const r of results) {
      const icon = r.ok ? '✓' : '✗';
      console.log(`  ${icon} ${r.name}: ${r.detail}`);
    }

    console.log('');
    if (allOk) {
      console.log('✓ Alle Checks bestanden – bereit zur Aufnahme.\n');
      process.exit(0);
    } else {
      console.log('✗ Preflight fehlgeschlagen – bitte Fehler beheben.\n');
      process.exit(1);
    }
  });
}
