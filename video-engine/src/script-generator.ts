import type { Story, StoryStep } from './story-validator';
import type { AudioSegmentInfo } from './subtitle-extractor';

/**
 * Generiert ein ausführbares Playwright-Script aus einer Story.
 * Wenn audioSegments übergeben werden, wird pause_after auf die Audio-Dauer verlängert.
 */
export function generatePlaywrightScript(
  story: Story,
  baseUrl: string,
  audioSegments?: AudioSegmentInfo[],
): string {
  const viewport = story.meta.viewport || { width: 1920, height: 1080 };
  const slowMo = story.meta.slow_mo || 600;

  // Audio-Dauern als Map (stepId → durationMs)
  const audioDurations = new Map<string, number>();
  if (audioSegments) {
    for (const seg of audioSegments) {
      audioDurations.set(seg.stepId, seg.durationMs);
    }
  }

  const lines: string[] = [];

  lines.push(`// Auto-generiert von anyPIM Video Engine`);
  lines.push(`// Story: ${story.meta.title} (${story.meta.id})`);
  lines.push(`// Version: ${story.meta.version}`);
  lines.push(``);
  lines.push(`import { chromium, type Page, type Browser } from 'playwright';`);
  lines.push(`import { writeFileSync } from 'fs';`);
  lines.push(``);
  lines.push(`const BASE_URL = ${JSON.stringify(baseUrl)};`);
  lines.push(`const SLOW_MO = ${slowMo};`);
  lines.push(`const TIMESTAMPS_FILE = process.env.TIMESTAMPS_FILE || 'timestamps.json';`);
  lines.push(`const recordingStart = Date.now();`);
  lines.push(`const timestamps: { id: string; sprecher: string; startMs: number; endMs?: number }[] = [];`);
  lines.push(``);
  lines.push(`function elapsed(): number { return Date.now() - recordingStart; }`);
  lines.push(``);
  lines.push(`async function sleep(ms: number): Promise<void> {`);
  lines.push(`  return new Promise(resolve => setTimeout(resolve, ms));`);
  lines.push(`}`);
  lines.push(``);
  lines.push(`async function highlight(page: Page, selector: string): Promise<void> {`);
  lines.push(`  await page.evaluate((sel) => {`);
  lines.push(`    const el = document.querySelector(sel);`);
  lines.push(`    if (!el) return;`);
  lines.push(`    (el as HTMLElement).style.outline = '3px solid #3b82f6';`);
  lines.push(`    (el as HTMLElement).style.outlineOffset = '2px';`);
  lines.push(`    (el as HTMLElement).style.transition = 'outline 0.3s ease';`);
  lines.push(`    setTimeout(() => {`);
  lines.push(`      (el as HTMLElement).style.outline = '';`);
  lines.push(`      (el as HTMLElement).style.outlineOffset = '';`);
  lines.push(`    }, 3000);`);
  lines.push(`  }, selector);`);
  lines.push(`}`);
  lines.push(``);
  lines.push(`async function typeSlowly(page: Page, selector: string, text: string, speed: number): Promise<void> {`);
  lines.push(`  await page.click(selector);`);
  lines.push(`  await page.fill(selector, '');`);
  lines.push(`  for (const char of text) {`);
  lines.push(`    await page.type(selector, char, { delay: speed });`);
  lines.push(`  }`);
  lines.push(`}`);
  lines.push(``);
  lines.push(`export async function run(): Promise<void> {`);
  lines.push(`  const browser: Browser = await chromium.launch({`);
  lines.push(`    headless: false,`);
  lines.push(`    slowMo: SLOW_MO,`);
  lines.push(`  });`);
  lines.push(``);
  lines.push(`  const context = await browser.newContext({`);
  lines.push(`    viewport: { width: ${viewport.width || 1920}, height: ${viewport.height || 1080} },`);
  lines.push(`    locale: 'de-DE',`);
  lines.push(`  });`);
  lines.push(``);
  lines.push(`  const page = await context.newPage();`);
  lines.push(``);

  // --- INTRO: Logo Zoom-Out + Fade ---
  lines.push(`  // === INTRO: anyPIM Logo ===`);
  lines.push(`  console.log('[INTRO] anyPIM Logo Zoom-Out (4s)');`);
  lines.push(`  await page.setContent(\``);
  lines.push(`<!DOCTYPE html>`);
  lines.push(`<html><head><style>`);
  lines.push(`  * { margin: 0; padding: 0; box-sizing: border-box; }`);
  lines.push(`  body { width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f0f23; overflow: hidden; }`);
  lines.push(`  .intro { display: flex; flex-direction: column; align-items: center; gap: 24px; animation: zoomFade 7s ease-in-out forwards; }`);
  lines.push(`  @keyframes zoomFade {`);
  lines.push(`    0% { transform: scale(2.5); opacity: 0; }`);
  lines.push(`    15% { transform: scale(1.05); opacity: 1; }`);
  lines.push(`    75% { transform: scale(1); opacity: 1; }`);
  lines.push(`    100% { transform: scale(0.95); opacity: 0; }`);
  lines.push(`  }`);
  lines.push(`  .logo-text { font-family: system-ui, -apple-system, sans-serif; font-size: 64px; font-weight: 800; letter-spacing: -1px; background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }`);
  lines.push(`  .logo-sub { font-family: system-ui, sans-serif; font-size: 18px; color: #94a3b8; letter-spacing: 4px; text-transform: uppercase; }`);
  lines.push(`</style></head><body>`);
  lines.push(`  <div class="intro">`);
  lines.push(`    <svg width="120" height="120" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">`);
  lines.push(`      <defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#6366f1"/><stop offset="50%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#06b6d4"/></linearGradient></defs>`);
  lines.push(`      <path d="M24 4L40 13v22L24 44L8 35V13L24 4z" fill="none" stroke="url(#g)" stroke-width="2.5" stroke-linejoin="round"/>`);
  lines.push(`      <circle cx="24" cy="24" r="4.5" fill="url(#g)"/>`);
  lines.push(`      <line x1="27" y1="20.5" x2="34" y2="15" stroke="url(#g)" stroke-width="2" stroke-linecap="round"/><circle cx="35" cy="14" r="2.5" fill="url(#g)"/>`);
  lines.push(`      <line x1="21" y1="20.5" x2="14" y2="15" stroke="url(#g)" stroke-width="2" stroke-linecap="round"/><circle cx="13" cy="14" r="2.5" fill="url(#g)"/>`);
  lines.push(`      <line x1="24" y1="28.5" x2="24" y2="36" stroke="url(#g)" stroke-width="2" stroke-linecap="round"/><circle cx="24" cy="37.5" r="2.5" fill="url(#g)"/>`);
  lines.push(`      <line x1="19.5" y1="24" x2="12" y2="27" stroke="url(#g)" stroke-width="2" stroke-linecap="round"/><circle cx="11" cy="27.5" r="2" fill="url(#g)"/>`);
  lines.push(`      <line x1="28.5" y1="24" x2="36" y2="27" stroke="url(#g)" stroke-width="2" stroke-linecap="round"/><circle cx="37" cy="27.5" r="2" fill="url(#g)"/>`);
  lines.push(`    </svg>`);
  lines.push(`    <div class="logo-text">anyPIM</div>`);
  lines.push(`    <div class="logo-sub">Product Information Management</div>`);
  lines.push(`  </div>`);
  lines.push(`</body></html>`);
  lines.push(`\`);`);
  lines.push(`  await sleep(7500);`);
  lines.push(``);

  const totalSteps = story.steps.length;

  for (let i = 0; i < story.steps.length; i++) {
    const step = story.steps[i];
    const stepNum = i + 1;

    lines.push(`  // --- Step ${stepNum}/${totalSteps}: ${step.id} (${step.action}) ---`);
    const logDetail = step.selector ? ` ${step.selector.replace(/'/g, "\\'")}` : '';
    lines.push(`  console.log('[STEP ${stepNum}/${totalSteps}] ${step.id} → ${step.action}${logDetail}');`);

    // Timestamp erfassen für SRT-Synchronisierung
    const sprecherText = (step.sprecher || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    lines.push(`  timestamps.push({ id: '${step.id}', sprecher: '${sprecherText}', startMs: elapsed() });`);

    lines.push(`  try {`);

    generateStepCode(lines, step, baseUrl);

    // wait_for
    if (step.wait_for) {
      const timeout = step.wait_timeout || 5000;
      lines.push(`    await page.waitForSelector(${JSON.stringify(step.wait_for)}, { state: 'visible', timeout: ${timeout} });`);
    }

    // pause_after: mindestens so lang wie das Audio-Segment (+ 500ms Puffer)
    const audioDur = audioDurations.get(step.id) || 0;
    const storyPause = step.pause_after || 0;
    const effectivePause = Math.max(storyPause, audioDur > 0 ? audioDur + 500 : 0);
    if (effectivePause > 0) {
      lines.push(`    await sleep(${effectivePause}); // pause: ${storyPause}ms story${audioDur > 0 ? `, ${audioDur}ms audio` : ''}`);
    }

    lines.push(`    timestamps[timestamps.length - 1].endMs = elapsed();`);
    lines.push(`  } catch (err) {`);
    lines.push(`    timestamps[timestamps.length - 1].endMs = elapsed();`);
    lines.push(`    console.warn('[STEP ${stepNum}/${totalSteps}] WARN: ${step.id} fehlgeschlagen:', (err as Error).message);`);
    lines.push(`    await page.screenshot({ path: 'step-${stepNum}-error.png' });`);
    lines.push(`  }`);
    lines.push(``);
  }

  lines.push(`  await sleep(1000);`);
  lines.push(``);

  // --- OUTRO: www.incoxx.com ---
  lines.push(`  // === OUTRO: www.incoxx.com ===`);
  lines.push(`  console.log('[OUTRO] www.incoxx.com (4s)');`);
  lines.push(`  await page.setContent(\``);
  lines.push(`<!DOCTYPE html>`);
  lines.push(`<html><head><style>`);
  lines.push(`  * { margin: 0; padding: 0; box-sizing: border-box; }`);
  lines.push(`  body { width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f0f23; overflow: hidden; }`);
  lines.push(`  .outro { display: flex; flex-direction: column; align-items: center; gap: 16px; animation: fadeInOut 4s ease-in-out forwards; }`);
  lines.push(`  @keyframes fadeInOut {`);
  lines.push(`    0% { opacity: 0; transform: translateY(20px); }`);
  lines.push(`    25% { opacity: 1; transform: translateY(0); }`);
  lines.push(`    75% { opacity: 1; transform: translateY(0); }`);
  lines.push(`    100% { opacity: 0; transform: translateY(-10px); }`);
  lines.push(`  }`);
  lines.push(`  .outro-url { font-family: system-ui, -apple-system, sans-serif; font-size: 42px; font-weight: 700; color: #e2e8f0; letter-spacing: 1px; }`);
  lines.push(`  .outro-line { width: 80px; height: 3px; background: linear-gradient(90deg, #6366f1, #06b6d4); border-radius: 2px; }`);
  lines.push(`  .outro-tagline { font-family: system-ui, sans-serif; font-size: 16px; color: #64748b; letter-spacing: 2px; text-transform: uppercase; }`);
  lines.push(`</style></head><body>`);
  lines.push(`  <div class="outro">`);
  lines.push(`    <div class="outro-url">www.incoxx.com</div>`);
  lines.push(`    <div class="outro-line"></div>`);
  lines.push(`    <div class="outro-tagline">Software &amp; Consulting</div>`);
  lines.push(`  </div>`);
  lines.push(`</body></html>`);
  lines.push(`\`);`);
  lines.push(`  await sleep(4500);`);
  lines.push(``);

  lines.push(`  // Timestamps als JSON speichern (fuer SRT-Synchronisierung)`);
  lines.push(`  writeFileSync(TIMESTAMPS_FILE, JSON.stringify(timestamps, null, 2));`);
  lines.push(`  console.log('Timestamps: ' + TIMESTAMPS_FILE + ' (' + timestamps.length + ' Eintraege)');`);
  lines.push(`  await browser.close();`);
  lines.push(`}`);
  lines.push(``);
  lines.push(`run().catch(err => {`);
  lines.push(`  // Timestamps auch bei Fehler speichern`);
  lines.push(`  try { writeFileSync(TIMESTAMPS_FILE, JSON.stringify(timestamps, null, 2)); } catch {}`);
  lines.push(`  console.error('Script fehlgeschlagen:', err);`);
  lines.push(`  process.exit(1);`);
  lines.push(`});`);

  return lines.join('\n');
}

function generateStepCode(lines: string[], step: StoryStep, baseUrl: string): void {
  switch (step.action) {
    case 'navigate': {
      if (!step.target) { lines.push(`    // WARN: navigate ohne target`); break; }
      if (step.target.startsWith('http')) {
        lines.push(`    await page.goto(${JSON.stringify(step.target)});`);
      } else {
        lines.push(`    await page.goto(BASE_URL + ${JSON.stringify(step.target)});`);
      }
      break;
    }

    case 'click':
      lines.push(`    await page.click(${JSON.stringify(step.selector)});`);
      break;

    case 'fill':
      if (step.type_speed && step.type_speed > 0) {
        lines.push(`    await typeSlowly(page, ${JSON.stringify(step.selector)}, ${JSON.stringify(step.value)}, ${step.type_speed});`);
      } else {
        lines.push(`    await page.fill(${JSON.stringify(step.selector)}, ${JSON.stringify(step.value)});`);
      }
      break;

    case 'select':
      // Versuche zuerst nach value, dann nach label zu matchen
      lines.push(`    await page.selectOption(${JSON.stringify(step.selector)}, { label: ${JSON.stringify(step.value)} }).catch(() =>`);
      lines.push(`      page.selectOption(${JSON.stringify(step.selector)}, ${JSON.stringify(step.value)})`);
      lines.push(`    );`);
      break;

    case 'select_tree': {
      if (!step.value) { lines.push(`    // WARN: select_tree ohne value`); break; }
      // Pfad aufsplittern und Node-für-Node expandieren
      const pathParts = step.value.split(' > ').map((p) => p.trim());
      lines.push(`    // Baumauswahl: ${step.value}`);
      for (const part of pathParts) {
        // Teil-String sicher in generierten Code einbetten
        const escapedPart = part.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        lines.push(`    await page.click(${JSON.stringify(step.selector)} + ' :text("${escapedPart}")');`);
        lines.push(`    await sleep(300);`);
      }
      break;
    }

    case 'upload':
      lines.push(`    const input = await page.$(${JSON.stringify(step.selector)} + ' input[type="file"]') || await page.$('input[type="file"]');`);
      lines.push(`    if (input) {`);
      lines.push(`      await input.setInputFiles(${JSON.stringify(step.file)});`);
      lines.push(`    }`);
      break;

    case 'hover':
      lines.push(`    await page.hover(${JSON.stringify(step.selector)});`);
      break;

    case 'scroll':
      if (step.selector) {
        lines.push(`    await page.evaluate((sel) => {`);
        lines.push(`      document.querySelector(sel)?.scrollIntoView({ behavior: 'smooth' });`);
        lines.push(`    }, ${JSON.stringify(step.selector)});`);
      } else {
        const delta = step.direction === 'up' ? -500 : 500;
        lines.push(`    await page.mouse.wheel(0, ${delta});`);
      }
      break;

    case 'wait':
      lines.push(`    await sleep(${step.duration || 1000});`);
      break;

    case 'highlight':
      lines.push(`    await highlight(page, ${JSON.stringify(step.selector)});`);
      break;

    case 'screenshot':
      lines.push(`    await page.screenshot({ path: 'screenshot-${step.id}.png', fullPage: false });`);
      break;

    case 'assert_visible':
      lines.push(`    await page.waitForSelector(${JSON.stringify(step.selector)}, { state: 'visible', timeout: 5000 });`);
      break;

    default:
      lines.push(`    // Unbekannte Action: ${step.action}`);
  }
}
