/**
 * Reel-Renderer – erzeugt ein ausführbares Playwright-Script aus einer Reel-Definition.
 *
 * Im Gegensatz zum script-generator (der die PIM-Oberfläche aufnimmt) rendert dieses
 * Script reine, animierte HTML-Szenen via page.setContent() – ganz ohne Login/Navigation.
 * Genutzt für Social-Media-Produktvideos (9:16). Die bestehende Pipeline (Recorder,
 * VoiceSynthesizer, video-renderer) verarbeitet das Ergebnis unverändert weiter.
 */

export interface ReelStyle {
  brief?: string;
  tonality?: string;
  accent?: string;
  background?: string;
  transition?: 'fade' | 'slide' | 'zoom' | 'cut';
  media_animation?: 'kenburns' | 'zoom-in' | 'zoom-out' | 'fade-in' | 'fade-out' | 'pan' | 'none';
}

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
    style?: ReelStyle;
    languages?: string[];
    product_count?: number;
  };
  scenes: ReelScene[];
}

const STYLE_DEFAULTS: Required<Pick<ReelStyle, 'accent' | 'background' | 'transition' | 'media_animation'>> = {
  accent: '#06b6d4',
  background: '#0f0f23',
  transition: 'fade',
  media_animation: 'kenburns',
};

/** Übersetzt den gewählten Übergang in eine CSS-Animation für die Szene. */
function transitionAnimation(transition: string): string {
  switch (transition) {
    case 'slide': return 'reelSlide 0.6s cubic-bezier(.2,.7,.2,1) both';
    case 'zoom':  return 'reelZoom 0.7s ease-out both';
    case 'cut':   return 'none';
    default:      return 'reelFade 0.6s ease-out both';
  }
}

/**
 * CSS-Deklaration für die Medien-Animation des Hintergrundbildes.
 * Die Dauer wird pro Szene inline gesetzt (animation-duration), damit die Bewegung
 * exakt die jeweilige Szenenlänge ausfüllt.
 */
function mediaAnimationDeclaration(media: string): string {
  const base = 'animation-timing-function: ease-out; animation-fill-mode: both;';
  switch (media) {
    case 'zoom-in':  return `animation-name: reelMZoomIn; ${base}`;
    case 'zoom-out': return `animation-name: reelMZoomOut; ${base}`;
    case 'fade-in':  return `animation-name: reelMFadeIn; ${base}`;
    case 'fade-out': return `animation-name: reelMFadeOut; ${base}`;
    case 'pan':      return `animation-name: reelMPan; animation-timing-function: linear; animation-fill-mode: both;`;
    case 'none':     return 'animation: none;';
    default:         return `animation-name: reelKenburns; ${base}`; // kenburns
  }
}

/**
 * Baut das vollständige Szenen-CSS aus dem Stil-Briefing. Akzent-/Hintergrundfarbe und
 * Übergang werden zur Generierungszeit eingesetzt (color-mix für die Verläufe).
 */
function buildSceneCss(style: ReelStyle): string {
  const accent = style.accent || STYLE_DEFAULTS.accent;
  const bg = style.background || STYLE_DEFAULTS.background;
  const anim = transitionAnimation(style.transition || STYLE_DEFAULTS.transition);
  const mediaRule = mediaAnimationDeclaration(style.media_animation || STYLE_DEFAULTS.media_animation);
  const gradient = `linear-gradient(135deg, color-mix(in srgb, ${accent} 65%, #000), ${accent})`;

  return `
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { width: 100vw; height: 100vh; overflow: hidden; background: ${bg};
    font-family: system-ui, -apple-system, sans-serif; color: #fff; }
  .stage { position: relative; width: 100vw; height: 100vh; display: flex;
    flex-direction: column; align-items: center; justify-content: flex-end;
    animation: ${anim}; }
  @keyframes reelFade { from { opacity: 0; } to { opacity: 1; } }
  @keyframes reelSlide { from { opacity: 0; transform: translateY(8%); } to { opacity: 1; transform: none; } }
  @keyframes reelZoom { from { opacity: 0; transform: scale(1.12); } to { opacity: 1; transform: none; } }
  @keyframes reelKenburns { from { transform: scale(1.08); } to { transform: scale(1); } }
  @keyframes reelMZoomIn  { from { transform: scale(1); }    to { transform: scale(1.14); } }
  @keyframes reelMZoomOut { from { transform: scale(1.14); } to { transform: scale(1); } }
  @keyframes reelMFadeIn  { from { opacity: 0; } to { opacity: 1; } }
  @keyframes reelMFadeOut { from { opacity: 1; } to { opacity: 0; } }
  @keyframes reelMPan { from { transform: scale(1.18) translateX(-4%); } to { transform: scale(1.18) translateX(4%); } }
  .bg { position: absolute; inset: 0; z-index: 0; }
  .bg img { width: 100%; height: 100%; object-fit: cover; ${mediaRule} }
  .bg-blur { position: absolute; inset: 0; background-size: cover; background-position: center;
    filter: blur(40px) brightness(0.5); transform: scale(1.2); z-index: -1; }
  .scrim { position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(to top,
      color-mix(in srgb, ${bg}, transparent 5%) 0%,
      color-mix(in srgb, ${bg}, transparent 90%) 55%,
      color-mix(in srgb, ${bg}, transparent 70%) 100%); }
  .content { position: relative; z-index: 2; width: 100%; padding: 0 64px 140px; text-align: center; }
  .badge { display: inline-block; font-size: 26px; font-weight: 700; letter-spacing: 4px;
    text-transform: uppercase; color: ${accent}; margin-bottom: 20px; }
  .headline { font-size: 76px; font-weight: 800; line-height: 1.05; letter-spacing: -1px;
    text-shadow: 0 4px 24px rgba(0,0,0,0.5); }
  .subline { font-size: 36px; font-weight: 400; color: #cbd5e1; margin-top: 24px; line-height: 1.3; }
  .price { font-size: 120px; font-weight: 900;
    background: ${gradient}; -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent; }
  .price-cur { font-size: 48px; font-weight: 700; color: #94a3b8; }
  .cta-box { display: flex; flex-direction: column; align-items: center; gap: 28px;
    justify-content: center; height: 100%; padding-bottom: 0; }
  .cta-btn { font-size: 44px; font-weight: 800; padding: 28px 64px; border-radius: 999px;
    background: ${gradient}; }
  .cta-url { font-size: 30px; color: #94a3b8; letter-spacing: 2px; }
  .center { justify-content: center !important; }
`;
}

/**
 * Baut das Playwright-Script. Szenen-Daten und CSS werden als JSON eingebettet, die
 * HTML-Erzeugung passiert zur Laufzeit – dadurch entfällt fehleranfälliges Escaping.
 */
export function generateReelScript(reel: ReelDefinition, baseUrl: string): string {
  const viewport = reel.meta.viewport || { width: 1080, height: 1920 };
  const scenesJson = JSON.stringify(reel.scenes);
  const cssText = buildSceneCss(reel.meta.style || {});

  return `// Auto-generiert von anyPIM Reel-Renderer
// Reel: ${reel.meta.id} (${reel.scenes.length} Szenen, ${viewport.width}x${viewport.height})
import { chromium, type Page, type Browser } from 'playwright';
import { writeFileSync } from 'fs';

const BASE_URL = ${JSON.stringify(baseUrl.replace(/\/+$/, ''))};
const VIEWPORT = ${JSON.stringify(viewport)};
const SCENES = ${scenesJson} as Scene[];
const BASE_CSS = ${JSON.stringify(cssText)};
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

function sceneHtml(s: Scene): string {
  const url = imgUrl(s.image);
  const dur = s.duration && s.duration > 0 ? s.duration : 2500;
  const bg = url
    ? \`<div class="bg-blur" style="background-image:url('\${url}')"></div><div class="bg"><img src="\${url}" alt="" style="animation-duration:\${dur}ms"></div><div class="scrim"></div>\`
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
