import type { Story, StoryStep } from './story-validator';

/**
 * Generiert ein ausführbares Playwright-Script aus einer Story.
 * Das Script wird als String zurückgegeben und kann als .ts-Datei gespeichert werden.
 */
export function generatePlaywrightScript(story: Story, baseUrl: string): string {
  const viewport = story.meta.viewport || { width: 1920, height: 1080 };
  const slowMo = story.meta.slow_mo || 600;

  const lines: string[] = [];

  lines.push(`// Auto-generiert von anyPIM Video Engine`);
  lines.push(`// Story: ${story.meta.title} (${story.meta.id})`);
  lines.push(`// Version: ${story.meta.version}`);
  lines.push(``);
  lines.push(`import { chromium, type Page, type Browser } from 'playwright';`);
  lines.push(``);
  lines.push(`const BASE_URL = ${JSON.stringify(baseUrl)};`);
  lines.push(`const SLOW_MO = ${slowMo};`);
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

  const totalSteps = story.steps.length;

  for (let i = 0; i < story.steps.length; i++) {
    const step = story.steps[i];
    const stepNum = i + 1;

    lines.push(`  // --- Step ${stepNum}/${totalSteps}: ${step.id} (${step.action}) ---`);
    const logDetail = step.selector ? ` ${step.selector.replace(/'/g, "\\'")}` : '';
    lines.push(`  console.log('[STEP ${stepNum}/${totalSteps}] ${step.id} → ${step.action}${logDetail}');`);
    lines.push(`  try {`);

    generateStepCode(lines, step, baseUrl);

    // wait_for
    if (step.wait_for) {
      const timeout = step.wait_timeout || 5000;
      lines.push(`    await page.waitForSelector(${JSON.stringify(step.wait_for)}, { state: 'visible', timeout: ${timeout} });`);
    }

    // pause_after
    if (step.pause_after) {
      lines.push(`    await sleep(${step.pause_after});`);
    }

    lines.push(`  } catch (err) {`);
    lines.push(`    console.warn('[STEP ${stepNum}/${totalSteps}] WARN: ${step.id} fehlgeschlagen:', (err as Error).message);`);
    lines.push(`    await page.screenshot({ path: 'step-${stepNum}-error.png' });`);
    lines.push(`  }`);
    lines.push(``);
  }

  lines.push(`  await sleep(1000);`);
  lines.push(`  await browser.close();`);
  lines.push(`}`);
  lines.push(``);
  lines.push(`run().catch(err => {`);
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
