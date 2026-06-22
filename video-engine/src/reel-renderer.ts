/**
 * Reel-Renderer – erzeugt ein ausführbares Playwright-Script aus einer Reel-Definition.
 *
 * Im Gegensatz zum script-generator (der die PIM-Oberfläche aufnimmt) rendert dieses
 * Script reine, animierte HTML-Szenen via page.setContent() – ganz ohne Login/Navigation.
 * Genutzt für Social-Media-Produktvideos (9:16). Die bestehende Pipeline (Recorder,
 * VoiceSynthesizer, video-renderer) verarbeitet das Ergebnis unverändert weiter.
 */

export interface ReelScene {
  type: 'hero' | 'feature' | 'price' | 'cta';
  image?: string | null;
  headline?: string | null;
  subline?: string | null;
  value?: number;
  currency?: string;
  sprecher?: string;
  duration?: number;
}

export interface ReelDefinition {
  meta: {
    id: string;
    format?: string;
    viewport?: { width: number; height: number };
    voice?: { lang?: string; gender?: string; provider?: string; voice_id?: string };
    template?: string;
    languages?: string[];
    product_count?: number;
  };
  scenes: ReelScene[];
}

/**
 * Baut das Playwright-Script. Szenen-Daten werden als JSON eingebettet, die
 * HTML-Erzeugung passiert zur Laufzeit – dadurch entfällt fehleranfälliges Escaping.
 */
export function generateReelScript(reel: ReelDefinition, baseUrl: string): string {
  const viewport = reel.meta.viewport || { width: 1080, height: 1920 };
  const scenesJson = JSON.stringify(reel.scenes);

  return `// Auto-generiert von anyPIM Reel-Renderer
// Reel: ${reel.meta.id} (${reel.scenes.length} Szenen, ${viewport.width}x${viewport.height})
import { chromium, type Page, type Browser } from 'playwright';
import { writeFileSync } from 'fs';

const BASE_URL = ${JSON.stringify(baseUrl.replace(/\/+$/, ''))};
const VIEWPORT = ${JSON.stringify(viewport)};
const SCENES = ${scenesJson} as Scene[];
const TIMESTAMPS_FILE = process.env.TIMESTAMPS_FILE || 'timestamps.json';

interface Scene {
  type: string;
  image?: string | null;
  headline?: string | null;
  subline?: string | null;
  value?: number;
  currency?: string;
  sprecher?: string;
  duration?: number;
}

const recordingStart = Date.now();
const timestamps: { id: string; sprecher: string; startMs: number; endMs?: number }[] = [];
function elapsed(): number { return Date.now() - recordingStart; }
function sleep(ms: number): Promise<void> { return new Promise(r => setTimeout(r, ms)); }

function esc(s: unknown): string {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function imgUrl(src?: string | null): string {
  if (!src) return '';
  if (/^https?:\\/\\//.test(src)) return src;
  return BASE_URL + (src.startsWith('/') ? src : '/' + src);
}

const BASE_CSS = \`
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { width: 100vw; height: 100vh; overflow: hidden; background: #0f0f23;
    font-family: system-ui, -apple-system, sans-serif; color: #fff; }
  .stage { position: relative; width: 100vw; height: 100vh; display: flex;
    flex-direction: column; align-items: center; justify-content: flex-end;
    animation: fadeIn 0.6s ease-out both; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes slowZoom { from { transform: scale(1.08); } to { transform: scale(1); } }
  .bg { position: absolute; inset: 0; z-index: 0; }
  .bg img { width: 100%; height: 100%; object-fit: cover; animation: slowZoom 5s ease-out both; }
  .bg-blur { position: absolute; inset: 0; background-size: cover; background-position: center;
    filter: blur(40px) brightness(0.5); transform: scale(1.2); z-index: -1; }
  .scrim { position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(to top, rgba(10,10,30,0.95) 0%, rgba(10,10,30,0.1) 55%, rgba(10,10,30,0.3) 100%); }
  .content { position: relative; z-index: 2; width: 100%; padding: 0 64px 140px; text-align: center; }
  .badge { display: inline-block; font-size: 26px; font-weight: 700; letter-spacing: 4px;
    text-transform: uppercase; color: #06b6d4; margin-bottom: 20px; }
  .headline { font-size: 76px; font-weight: 800; line-height: 1.05; letter-spacing: -1px;
    text-shadow: 0 4px 24px rgba(0,0,0,0.5); }
  .subline { font-size: 36px; font-weight: 400; color: #cbd5e1; margin-top: 24px; line-height: 1.3; }
  .price { font-size: 120px; font-weight: 900;
    background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text;
    -webkit-text-fill-color: transparent; }
  .price-cur { font-size: 48px; font-weight: 700; color: #94a3b8; }
  .cta-box { display: flex; flex-direction: column; align-items: center; gap: 28px;
    justify-content: center; height: 100%; padding-bottom: 0; }
  .cta-btn { font-size: 44px; font-weight: 800; padding: 28px 64px; border-radius: 999px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4); }
  .cta-url { font-size: 30px; color: #94a3b8; letter-spacing: 2px; }
  .center { justify-content: center !important; }
\`;

function sceneHtml(s: Scene): string {
  const url = imgUrl(s.image);
  const bg = url
    ? \`<div class="bg-blur" style="background-image:url('\${url}')"></div><div class="bg"><img src="\${url}" alt=""></div><div class="scrim"></div>\`
    : '';

  if (s.type === 'price') {
    const formatted = typeof s.value === 'number'
      ? s.value.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
      : '';
    return \`<div class="stage center">\${bg}<div class="content"><div class="badge">Preis</div>
      <div><span class="price">\${esc(formatted)}</span> <span class="price-cur">\${esc(s.currency || 'EUR')}</span></div></div></div>\`;
  }

  if (s.type === 'cta') {
    return \`<div class="stage center"><div class="content cta-box">
      <div class="cta-btn">\${esc(s.headline || 'Jetzt entdecken')}</div>
      <div class="cta-url">www.incoxx.com</div></div></div>\`;
  }

  const badge = s.type === 'feature' ? '<div class="badge">Highlight</div>' : '';
  const sub = s.subline ? \`<div class="subline">\${esc(s.subline)}</div>\` : '';
  return \`<div class="stage">\${bg}<div class="content">\${badge}
    <div class="headline">\${esc(s.headline)}</div>\${sub}</div></div>\`;
}

function pageHtml(s: Scene): string {
  return \`<!DOCTYPE html><html><head><meta charset="utf-8"><style>\${BASE_CSS}</style></head>
    <body>\${sceneHtml(s)}</body></html>\`;
}

export async function run(): Promise<void> {
  const browser: Browser = await chromium.launch({ headless: false });
  const context = await browser.newContext({ viewport: VIEWPORT, locale: 'de-DE' });
  const page: Page = await context.newPage();

  for (let i = 0; i < SCENES.length; i++) {
    const s = SCENES[i];
    const id = 'scene-' + (i + 1);
    const duration = s.duration && s.duration > 0 ? s.duration : 2500;
    console.log('[SCENE ' + (i + 1) + '/' + SCENES.length + '] ' + s.type);

    timestamps.push({ id, sprecher: s.sprecher || '', startMs: elapsed() });
    try {
      await page.setContent(pageHtml(s), { waitUntil: 'load' });
      // Bilder kurz laden lassen
      await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
      await sleep(duration);
    } catch (err) {
      console.warn('[SCENE ' + (i + 1) + '] WARN:', (err as Error).message);
    }
    timestamps[timestamps.length - 1].endMs = elapsed();
  }

  await sleep(500);
  writeFileSync(TIMESTAMPS_FILE, JSON.stringify(timestamps, null, 2));
  console.log('Timestamps: ' + TIMESTAMPS_FILE + ' (' + timestamps.length + ' Eintraege)');
  await browser.close();
}

run().catch(err => {
  try { writeFileSync(TIMESTAMPS_FILE, JSON.stringify(timestamps, null, 2)); } catch {}
  console.error('Reel-Script fehlgeschlagen:', err);
  process.exit(1);
});
`;
}
