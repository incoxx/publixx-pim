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

export interface CameraMotion {
  from: { x: number; y: number; zoom: number };
  to: { x: number; y: number; zoom: number };
}

export interface ReelScene {
  type: 'hero' | 'feature' | 'price' | 'cta';
  image?: string | null;
  badge?: string | null;
  headline?: string | null;
  subline?: string | null;
  value?: number;
  currency?: string;
  sprecher?: string;
  duration?: number;
  motion?: CameraMotion;
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
  @keyframes reelKenburns { from { transform: scale(1.0) translate(0, 0); } to { transform: scale(1.18) translate(-3%, -2%); } }
  @keyframes reelMZoomIn  { from { transform: scale(1.0); }  to { transform: scale(1.22); } }
  @keyframes reelMZoomOut { from { transform: scale(1.22); } to { transform: scale(1.0); } }
  @keyframes reelMFadeIn  { from { opacity: 0; } to { opacity: 1; } }
  @keyframes reelMFadeOut { from { opacity: 1; } to { opacity: 0; } }
  @keyframes reelMPan { from { transform: scale(1.22) translateX(-6%); } to { transform: scale(1.22) translateX(6%); } }
  @keyframes reelTextIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  .bg { position: absolute; inset: 0; z-index: 0; }
  .bg img { width: 100%; height: 100%; object-fit: cover; ${mediaRule} }
  .bg-blur { position: absolute; inset: 0; background-size: cover; background-position: center;
    filter: blur(40px) brightness(0.5); transform: scale(1.2); z-index: -1; }
  .scrim { position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(to top,
      color-mix(in srgb, ${bg}, transparent 5%) 0%,
      color-mix(in srgb, ${bg}, transparent 90%) 55%,
      color-mix(in srgb, ${bg}, transparent 70%) 100%); }
  /* Text-Overlay-Wirt: layout-transparent, damit .stage-Flex direkt den .content ausrichtet. */
  .content-host { display: contents; }
  .content { position: relative; z-index: 2; width: 100%; padding: 0 64px 140px; text-align: center; }
  /* Gestaffelte Text-Animationen: jedes Element fährt einzeln ein. */
  @keyframes reelHeadlineIn { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: none; } }
  @keyframes reelPriceIn { 0% { opacity: 0; transform: scale(0.8); } 60% { transform: scale(1.04); } 100% { opacity: 1; transform: scale(1); } }
  @keyframes reelPop { 0% { opacity: 0; transform: scale(0.85); } 70% { transform: scale(1.05); } 100% { opacity: 1; transform: scale(1); } }
  .badge { display: inline-block; font-size: 26px; font-weight: 700; letter-spacing: 4px;
    text-transform: uppercase; color: ${accent}; margin-bottom: 20px;
    animation: reelTextIn 0.5s ease both; }
  .headline { font-size: 76px; font-weight: 800; line-height: 1.05; letter-spacing: -1px;
    text-shadow: 0 4px 24px rgba(0,0,0,0.5);
    animation: reelHeadlineIn 0.6s cubic-bezier(.2,.7,.2,1) both; animation-delay: 0.08s; }
  .subline { font-size: 36px; font-weight: 400; color: #cbd5e1; margin-top: 24px; line-height: 1.3;
    animation: reelTextIn 0.6s ease both; animation-delay: 0.22s; }
  .price { display: inline-block; font-size: 120px; font-weight: 900;
    background: ${gradient}; -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: reelPriceIn 0.7s cubic-bezier(.2,.7,.2,1) both; }
  .price-cur { font-size: 48px; font-weight: 700; color: #94a3b8;
    animation: reelTextIn 0.6s ease both; animation-delay: 0.18s; }
  .cta-box { display: flex; flex-direction: column; align-items: center; gap: 28px;
    justify-content: center; height: 100%; padding-bottom: 0; }
  .cta-btn { font-size: 44px; font-weight: 800; padding: 28px 64px; border-radius: 999px;
    background: ${gradient}; animation: reelPop 0.55s cubic-bezier(.2,.7,.2,1) both; }
  .cta-url { font-size: 30px; color: #94a3b8; letter-spacing: 2px;
    animation: reelTextIn 0.6s ease both; animation-delay: 0.15s; }
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
import { writeFileSync, copyFileSync } from 'fs';

const BASE_URL = ${JSON.stringify(baseUrl.replace(/\/+$/, ''))};
const VIEWPORT = ${JSON.stringify(viewport)};
const SCENES = ${scenesJson} as Scene[];
const BASE_CSS = ${JSON.stringify(cssText)};
const TIMESTAMPS_FILE = process.env.TIMESTAMPS_FILE || 'timestamps.json';
const VIDEO_DIR = process.env.REEL_VIDEO_DIR || '.';

interface Motion { from: { x: number; y: number; zoom: number }; to: { x: number; y: number; zoom: number }; }
interface Scene {
  type: string;
  image?: string | null;
  badge?: string | null;
  headline?: string | null;
  subline?: string | null;
  value?: number;
  currency?: string;
  sprecher?: string;
  duration?: number;
  motion?: Motion;
}

let recordingStart = Date.now();
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

function sceneDur(s: Scene): number {
  return s.duration && s.duration > 0 ? s.duration : 2500;
}

// Transform, das einen Fokuspunkt (x/y in % des Bildes) bei gegebenem Zoom in die
// Bildmitte rückt (transform-origin: 0 0). So entsteht eine echte Kamerafahrt.
function motionTransform(p: { x: number; y: number; zoom: number }): string {
  return 'translate(' + (50 - p.zoom * p.x).toFixed(3) + '%, ' + (50 - p.zoom * p.y).toFixed(3) + '%) scale(' + p.zoom + ')';
}

// Hintergrund (Bild + Blur + Scrim). imgStyle steuert die Animation (Preset über
// CSS-Klasse ODER eine pro Segment generierte Fokuspunkt-Fahrt).
function bgHtml(url: string, imgStyle: string): string {
  if (!url) return '';
  return \`<div class="bg-blur" style="background-image:url('\${url}')"></div>\`
    + \`<div class="bg"><img src="\${url}" alt="" style="\${imgStyle}"></div>\`
    + \`<div class="scrim"></div>\`;
}

// Nur der Text-Overlay einer Szene (ohne Stage/Hintergrund) – wird je Szene in den
// persistenten .content-host getauscht, ohne das Bild neu zu laden.
function contentInner(s: Scene): string {
  if (s.type === 'price') {
    const formatted = typeof s.value === 'number'
      ? s.value.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
      : '';
    return \`<div class="content"><div class="badge">Preis</div>
      <div><span class="price">\${esc(formatted)}</span> <span class="price-cur">\${esc(s.currency || 'EUR')}</span></div></div>\`;
  }
  if (s.type === 'cta') {
    return \`<div class="content cta-box">
      <div class="cta-btn">\${esc(s.headline || 'Jetzt entdecken')}</div>
      <div class="cta-url">www.incoxx.com</div></div>\`;
  }
  const badge = s.type === 'feature' ? \`<div class="badge">\${esc(s.badge || 'Highlight')}</div>\` : '';
  const sub = s.subline ? \`<div class="subline">\${esc(s.subline)}</div>\` : '';
  return \`<div class="content">\${badge}<div class="headline">\${esc(s.headline)}</div>\${sub}</div>\`;
}

// Persistente Segment-Seite: Hintergrund einmal gerendert, Text-Wirt zunächst leer.
// Liegt eine Kamerafahrt (motion) vor, wird dafür ein eigenes Keyframe erzeugt,
// das über die GESAMTE Segmentdauer läuft; sonst greift das Preset (.bg img).
function segmentPage(seg: Segment, segIdx: number, totalMs: number): string {
  let headExtra = '';
  let imgStyle = 'animation-duration:' + totalMs + 'ms';
  const motion = SCENES[seg.scenes[0]].motion;
  if (motion) {
    const name = 'reelMotion' + segIdx;
    headExtra = '<style>@keyframes ' + name + ' { from { transform: ' + motionTransform(motion.from)
      + '; } to { transform: ' + motionTransform(motion.to) + '; } }</style>';
    imgStyle = 'transform-origin:0 0; animation:' + name + ' ' + totalMs + 'ms ease-out both';
  }
  return \`<!DOCTYPE html><html><head><meta charset="utf-8"><style>\${BASE_CSS}</style>\${headExtra}</head>\`
    + \`<body><div class="stage\${seg.centered ? ' center' : ''}">\${bgHtml(seg.url, imgStyle)}<div class="content-host"></div></div></body></html>\`;
}

interface Segment { url: string; centered: boolean; scenes: number[]; }

// Aufeinanderfolgende Szenen mit GLEICHEM Bild (und gleicher Zentrierung) zu einem
// Segment bündeln → Bild lädt nur einmal, der Zoom läuft durchgehend weiter.
function buildSegments(): Segment[] {
  const segments: Segment[] = [];
  for (let i = 0; i < SCENES.length; i++) {
    const url = imgUrl(SCENES[i].image);
    const centered = SCENES[i].type === 'price' || SCENES[i].type === 'cta';
    const last = segments[segments.length - 1];
    if (last && url !== '' && last.url === url && last.centered === centered) {
      last.scenes.push(i);
    } else {
      segments.push({ url, centered, scenes: [i] });
    }
  }
  return segments;
}

export async function run(): Promise<void> {
  // Optionaler expliziter Chromium-Pfad (vom reel-cli aufgelöst), damit der
  // Versions-Versatz zwischen npm-Playwright und bereitgestelltem Browser nicht stört.
  const chromiumPath = process.env.PLAYWRIGHT_CHROMIUM_PATH;
  // Headless + recordVideo: nimmt NUR den Seiteninhalt in exakter Viewport-Größe
  // auf – ohne Browserleiste und ohne Xvfb/Screengrab.
  const browser: Browser = await chromium.launch({
    headless: true,
    ...(chromiumPath ? { executablePath: chromiumPath } : {}),
  });
  const context = await browser.newContext({
    viewport: VIEWPORT,
    locale: 'de-DE',
    recordVideo: { dir: VIDEO_DIR, size: VIEWPORT },
  });
  const page: Page = await context.newPage();
  const video = page.video();

  // Zeitbasis auf den Aufnahmestart legen (Audio wird daran ausgerichtet).
  recordingStart = Date.now();
  const allSegments = buildSegments();
  for (let segIdx = 0; segIdx < allSegments.length; segIdx++) {
    const seg = allSegments[segIdx];
    // Gesamtdauer des Segments = Summe der enthaltenen Szenen → Zoom-Länge.
    const totalMs = seg.scenes.reduce((sum, idx) => sum + sceneDur(SCENES[idx]), 0);
    try {
      // Hintergrund EINMAL pro Segment setzen und das Bild laden lassen.
      await page.setContent(segmentPage(seg, segIdx, totalMs), { waitUntil: 'load' });
      await page.waitForLoadState('networkidle', { timeout: 4000 }).catch(() => {});
    } catch (err) {
      console.warn('[SEGMENT] WARN:', (err as Error).message);
    }

    for (const idx of seg.scenes) {
      const s = SCENES[idx];
      const id = 'scene-' + (idx + 1);
      console.log('[SCENE ' + (idx + 1) + '/' + SCENES.length + '] ' + s.type);

      timestamps.push({ id, sprecher: s.sprecher || '', startMs: elapsed() });
      try {
        // Nur den Text-Overlay tauschen – Bild und laufender Zoom bleiben erhalten.
        await page.evaluate((html) => {
          const host = document.querySelector('.content-host');
          if (host) host.innerHTML = html;
        }, contentInner(s));
        await sleep(sceneDur(s));
      } catch (err) {
        console.warn('[SCENE ' + (idx + 1) + '] WARN:', (err as Error).message);
      }
      timestamps[timestamps.length - 1].endMs = elapsed();
    }
  }

  await sleep(500);
  writeFileSync(TIMESTAMPS_FILE, JSON.stringify(timestamps, null, 2));
  console.log('Timestamps: ' + TIMESTAMPS_FILE + ' (' + timestamps.length + ' Eintraege)');

  // Video finalisieren: Kontext schließen, dann den (zufällig benannten) Pfad
  // auf den festen Namen recording.webm kopieren, den die reel-cli erwartet.
  await page.close();
  await context.close();
  const videoPath = await video?.path();
  if (videoPath) {
    copyFileSync(videoPath, VIDEO_DIR + '/recording.webm');
    console.log('Video: ' + VIDEO_DIR + '/recording.webm');
  } else {
    console.error('Kein Video aufgezeichnet.');
  }
  await browser.close();
}

run().catch(err => {
  try { writeFileSync(TIMESTAMPS_FILE, JSON.stringify(timestamps, null, 2)); } catch {}
  console.error('Reel-Script fehlgeschlagen:', err);
  process.exit(1);
});
`;
}
