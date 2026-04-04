/**
 * Erzeugt Platzhalter-Bilder für Video-Stories.
 * Nutzt nur Node.js-Bordmittel (keine externen Bild-Libraries nötig).
 *
 * Erzeugt einfache SVG-Dateien die als Platzhalter dienen.
 * Für echte Produktbilder können diese später durch reale Fotos ersetzt werden.
 */

import fs from 'fs';
import path from 'path';

const ASSETS_DIR = path.resolve(__dirname, '../../video-stories/demo-assets');

function ensureDir(dir: string): void {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

/**
 * Erzeugt ein SVG-Platzhalter-Produktbild.
 */
function createProductPlaceholder(
  filename: string,
  width: number,
  height: number,
  title: string,
  bgColor: string,
  accentColor: string,
): void {
  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:${bgColor};stop-opacity:1" />
      <stop offset="100%" style="stop-color:${accentColor};stop-opacity:0.8" />
    </linearGradient>
  </defs>
  <rect width="${width}" height="${height}" fill="url(#bg)" />
  <rect x="${width * 0.1}" y="${height * 0.15}" width="${width * 0.8}" height="${height * 0.55}"
        rx="12" fill="white" fill-opacity="0.15" />
  <text x="${width / 2}" y="${height * 0.45}" text-anchor="middle"
        font-family="Arial, sans-serif" font-size="${Math.floor(width * 0.04)}" font-weight="bold" fill="white">
    ${title}
  </text>
  <text x="${width / 2}" y="${height * 0.55}" text-anchor="middle"
        font-family="Arial, sans-serif" font-size="${Math.floor(width * 0.02)}" fill="white" fill-opacity="0.7">
    anyPIM Demo-Produktbild
  </text>
  <text x="${width / 2}" y="${height * 0.88}" text-anchor="middle"
        font-family="Arial, sans-serif" font-size="${Math.floor(width * 0.015)}" fill="white" fill-opacity="0.4">
    ${width} × ${height} px – Platzhalter
  </text>
</svg>`;

  const filePath = path.join(ASSETS_DIR, filename);
  fs.writeFileSync(filePath, svg, 'utf-8');
  console.log(`  ✓ ${filename} (${width}×${height})`);
}

/**
 * Erzeugt ein SVG-Logo.
 */
function createLogo(filename: string): void {
  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100" viewBox="0 0 400 100">
  <rect width="400" height="100" fill="transparent" />
  <text x="200" y="60" text-anchor="middle"
        font-family="Arial, sans-serif" font-size="36" font-weight="bold" fill="#1e293b">
    any<tspan fill="#3b82f6">PIM</tspan>
  </text>
  <text x="200" y="82" text-anchor="middle"
        font-family="Arial, sans-serif" font-size="12" fill="#94a3b8">
    Product Information Management
  </text>
</svg>`;

  const filePath = path.join(ASSETS_DIR, filename);
  fs.writeFileSync(filePath, svg, 'utf-8');
  console.log(`  ✓ ${filename}`);
}

// Hauptprogramm
console.log('anyPIM Video Engine – Demo-Assets erstellen\n');

ensureDir(ASSETS_DIR);

createProductPlaceholder(
  'produkt-samsung-oled.svg',
  1200, 800,
  'Samsung OLED 55"',
  '#1a1a2e', '#16213e',
);

createProductPlaceholder(
  'produkt-akkubohrer.svg',
  1200, 800,
  'Akkubohrschrauber ProDrill',
  '#1b4332', '#2d6a4f',
);

createProductPlaceholder(
  'produkt-schlagbohrer.svg',
  1200, 800,
  'Schlagbohrmaschine PowerHammer',
  '#7f1d1d', '#991b1b',
);

createLogo('logo-anypim.svg');

console.log('\n✓ Demo-Assets erstellt in: ' + ASSETS_DIR);
