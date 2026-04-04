import Ajv from 'ajv';
import fs from 'fs';
import path from 'path';
import { parse as parseYaml } from 'yaml';

const SCHEMA_PATH = path.resolve(__dirname, '../../video-stories/_schema/story.schema.json');
const STORIES_DIR = path.resolve(__dirname, '../../video-stories');

/** Story-Datentypen */
export interface StoryMeta {
  id: string;
  title: string;
  description: string;
  version: string;
  duration_estimate?: number;
  tags?: string[];
  voice?: {
    lang?: string;
    gender?: 'male' | 'female';
    provider?: 'elevenlabs' | 'gtts';
    voice_id?: string;
  };
  viewport?: { width?: number; height?: number };
  slow_mo?: number;
  seeder?: string;
  seeder_reset?: boolean;
}

export interface StoryStep {
  id: string;
  action: string;
  target?: string;
  selector?: string;
  value?: string;
  file?: string;
  direction?: 'up' | 'down';
  duration?: number;
  wait_for?: string;
  wait_timeout?: number;
  sprecher?: string;
  pause_after?: number;
  type_speed?: number;
}

export interface Story {
  meta: StoryMeta;
  steps: StoryStep[];
}

/** Validiert eine Story-YAML-Datei gegen das JSON Schema */
export function validateStory(storyPath: string): { valid: boolean; errors: string[]; story?: Story } {
  const errors: string[] = [];

  // YAML laden
  if (!fs.existsSync(storyPath)) {
    return { valid: false, errors: [`Datei nicht gefunden: ${storyPath}`] };
  }

  let story: Story;
  try {
    const content = fs.readFileSync(storyPath, 'utf-8');
    story = parseYaml(content) as Story;
  } catch (e) {
    return { valid: false, errors: [`YAML Parse-Fehler: ${(e as Error).message}`] };
  }

  // JSON Schema laden und validieren
  if (!fs.existsSync(SCHEMA_PATH)) {
    return { valid: false, errors: [`Schema nicht gefunden: ${SCHEMA_PATH}`] };
  }

  const schema = JSON.parse(fs.readFileSync(SCHEMA_PATH, 'utf-8'));
  const ajv = new Ajv({ allErrors: true });
  const validate = ajv.compile(schema);

  if (!validate(story)) {
    const schemaErrors = (validate.errors || []).map(
      (e) => `${e.instancePath || '/'}: ${e.message}`
    );
    return { valid: false, errors: schemaErrors };
  }

  // Verzeichnisname = meta.id prüfen
  const dirName = path.basename(path.dirname(storyPath));
  if (dirName !== story.meta.id && dirName !== '_template') {
    errors.push(`Verzeichnisname "${dirName}" stimmt nicht mit meta.id "${story.meta.id}" überein`);
  }

  // Eindeutige Step-IDs prüfen
  const stepIds = new Set<string>();
  for (const step of story.steps) {
    if (stepIds.has(step.id)) {
      errors.push(`Doppelte Step-ID: "${step.id}"`);
    }
    stepIds.add(step.id);
  }

  // Referenzierte Dateien prüfen (upload-Action)
  for (const step of story.steps) {
    if (step.action === 'upload' && step.file) {
      const filePath = path.resolve(STORIES_DIR, step.file);
      if (!fs.existsSync(filePath)) {
        errors.push(`Upload-Datei nicht gefunden: ${step.file} (erwartet: ${filePath})`);
      }
    }
  }

  return {
    valid: errors.length === 0,
    errors,
    story,
  };
}

/** Ersetzt ${ENV_VAR} Platzhalter in Story-Werten */
export function interpolateEnv(story: Story): Story {
  const envPattern = /\$\{([A-Z_][A-Z0-9_]*)\}/g;

  function replaceEnv(value: string): string {
    return value.replace(envPattern, (_, varName) => {
      return process.env[varName] || '';
    });
  }

  const interpolated: Story = JSON.parse(JSON.stringify(story));

  for (const step of interpolated.steps) {
    if (step.value) step.value = replaceEnv(step.value);
    if (step.target) step.target = replaceEnv(step.target);
    if (step.file) step.file = replaceEnv(step.file);
  }

  return interpolated;
}

/** Listet alle verfügbaren Stories auf */
export function listStories(): { id: string; title: string; path: string }[] {
  const stories: { id: string; title: string; path: string }[] = [];

  if (!fs.existsSync(STORIES_DIR)) return stories;

  for (const entry of fs.readdirSync(STORIES_DIR, { withFileTypes: true })) {
    if (!entry.isDirectory() || entry.name.startsWith('_')) continue;

    const storyFile = path.join(STORIES_DIR, entry.name, 'story.yaml');
    if (!fs.existsSync(storyFile)) continue;

    try {
      const content = fs.readFileSync(storyFile, 'utf-8');
      const story = parseYaml(content) as Story;
      stories.push({
        id: story.meta.id,
        title: story.meta.title,
        path: storyFile,
      });
    } catch {
      stories.push({
        id: entry.name,
        title: '(YAML-Fehler)',
        path: storyFile,
      });
    }
  }

  return stories.sort((a, b) => a.id.localeCompare(b.id));
}

// CLI-Modus: Direkt aufrufen zur Validierung
if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(__filename)) {
  const storyArg = process.argv[2];

  if (!storyArg) {
    console.log('Verfügbare Stories:');
    for (const s of listStories()) {
      console.log(`  ${s.id} – ${s.title}`);
    }
    process.exit(0);
  }

  const storyPath = path.resolve(STORIES_DIR, storyArg, 'story.yaml');
  const result = validateStory(storyPath);

  if (result.valid) {
    console.log(`✓ Story "${storyArg}" ist valide`);
    process.exit(0);
  } else {
    console.error(`✗ Story "${storyArg}" hat Fehler:`);
    for (const err of result.errors) {
      console.error(`  - ${err}`);
    }
    process.exit(1);
  }
}
