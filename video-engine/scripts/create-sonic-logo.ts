/**
 * anyPIM Sonic Logo Generator
 *
 * Erzeugt den anyPIM Signature Sound programmatisch mit ffmpeg.
 * Konzept: "Daten die sich verbinden" – passend zum Hexagon-Hub-Logo.
 *
 * Aufbau (2.5 Sekunden):
 *   0.0s - Sanfter Bass-Ton (Fundament, der Hub)
 *   0.3s - Ping 1 (erster Knoten verbindet sich)
 *   0.6s - Ping 2 (zweiter Knoten, höher)
 *   0.9s - Ping 3 (dritter Knoten, noch höher)
 *   1.1s - Warmer Akkord (alles verbunden, Harmonie)
 *   2.5s - Fade Out
 */

import { execSync } from 'child_process';
import path from 'path';
import fs from 'fs';

const OUTPUT_DIR = path.resolve(__dirname, '../../video-stories/demo-assets');
const OUTPUT_FILE = path.join(OUTPUT_DIR, 'anypim-sonic-logo.mp3');
const TMP_DIR = path.resolve(__dirname, '../../storage/video-engine/tmp');

if (!fs.existsSync(TMP_DIR)) {
  fs.mkdirSync(TMP_DIR, { recursive: true });
}

console.log('anyPIM Sonic Logo Generator\n');

// Frequenzen (harmonische Reihe in D-Dur – warm und modern)
const BASS = 147;       // D3 – Fundament
const PING1 = 587;      // D5 – erster Knoten
const PING2 = 740;      // F#5 – zweiter Knoten
const PING3 = 880;      // A5 – dritter Knoten
const CHORD_ROOT = 294;  // D4 – Schlussakkord
const CHORD_THIRD = 370; // F#4
const CHORD_FIFTH = 440; // A4
const SHIMMER = 1175;    // D6 – Oberton/Glanz

// Einzelne Schichten als WAV erzeugen
const layers: { file: string; cmd: string }[] = [
  // Layer 1: Bass-Fundament (sanfter Einschwung)
  {
    file: path.join(TMP_DIR, 'sonic-bass.wav'),
    cmd: `ffmpeg -y -f lavfi -i "sine=frequency=${BASS}:duration=2.5" -af "afade=t=in:d=0.3,afade=t=out:st=1.5:d=1.0,volume=0.25" -ar 44100 "${path.join(TMP_DIR, 'sonic-bass.wav')}"`,
  },
  // Layer 2: Ping 1 (kurz, perkussiv)
  {
    file: path.join(TMP_DIR, 'sonic-ping1.wav'),
    cmd: `ffmpeg -y -f lavfi -i "sine=frequency=${PING1}:duration=0.6" -af "afade=t=in:d=0.01,afade=t=out:st=0.1:d=0.5,volume=0.3,adelay=300|300" -ar 44100 "${path.join(TMP_DIR, 'sonic-ping1.wav')}"`,
  },
  // Layer 3: Ping 2 (etwas höher)
  {
    file: path.join(TMP_DIR, 'sonic-ping2.wav'),
    cmd: `ffmpeg -y -f lavfi -i "sine=frequency=${PING2}:duration=0.6" -af "afade=t=in:d=0.01,afade=t=out:st=0.1:d=0.5,volume=0.28,adelay=600|600" -ar 44100 "${path.join(TMP_DIR, 'sonic-ping2.wav')}"`,
  },
  // Layer 4: Ping 3 (höchster Punkt)
  {
    file: path.join(TMP_DIR, 'sonic-ping3.wav'),
    cmd: `ffmpeg -y -f lavfi -i "sine=frequency=${PING3}:duration=0.8" -af "afade=t=in:d=0.01,afade=t=out:st=0.15:d=0.65,volume=0.32,adelay=900|900" -ar 44100 "${path.join(TMP_DIR, 'sonic-ping3.wav')}"`,
  },
  // Layer 5: Schlussakkord (warm, breit)
  {
    file: path.join(TMP_DIR, 'sonic-chord.wav'),
    cmd: `ffmpeg -y -f lavfi -i "sine=frequency=${CHORD_ROOT}:duration=1.8" -f lavfi -i "sine=frequency=${CHORD_THIRD}:duration=1.8" -f lavfi -i "sine=frequency=${CHORD_FIFTH}:duration=1.8" -filter_complex "[0]volume=0.2[a];[1]volume=0.15[b];[2]volume=0.15[c];[a][b][c]amix=inputs=3:duration=longest,afade=t=in:d=0.1,afade=t=out:st=0.8:d=1.0,adelay=1100|1100" -ar 44100 "${path.join(TMP_DIR, 'sonic-chord.wav')}"`,
  },
  // Layer 6: Shimmer/Oberton (subtiler Glanz)
  {
    file: path.join(TMP_DIR, 'sonic-shimmer.wav'),
    cmd: `ffmpeg -y -f lavfi -i "sine=frequency=${SHIMMER}:duration=1.5" -af "afade=t=in:d=0.05,afade=t=out:st=0.3:d=1.2,volume=0.08,adelay=1000|1000" -ar 44100 "${path.join(TMP_DIR, 'sonic-shimmer.wav')}"`,
  },
];

// Schichten erzeugen
for (const layer of layers) {
  console.log(`  Erzeuge: ${path.basename(layer.file)}`);
  execSync(layer.cmd, { stdio: 'pipe' });
}

// Alle Schichten mischen
console.log('\n  Mische alle Schichten...');

const inputArgs = layers.map(l => `-i "${l.file}"`).join(' ');
const filterInputs = layers.map((_, i) => `[${i}]`).join('');
const mixCmd = `ffmpeg -y ${inputArgs} -filter_complex "${filterInputs}amix=inputs=${layers.length}:duration=longest:dropout_transition=0,alimiter=limit=0.9,afade=t=out:st=2.0:d=0.5" -b:a 192k "${OUTPUT_FILE}"`;

execSync(mixCmd, { stdio: 'pipe' });

// Aufräumen
for (const layer of layers) {
  try { fs.unlinkSync(layer.file); } catch {}
}

// Ergebnis prüfen
const stats = fs.statSync(OUTPUT_FILE);
const duration = execSync(`ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${OUTPUT_FILE}"`, { encoding: 'utf-8' }).trim();

console.log(`\n✓ Sonic Logo erzeugt: ${OUTPUT_FILE}`);
console.log(`  Dauer: ${parseFloat(duration).toFixed(1)}s`);
console.log(`  Größe: ${(stats.size / 1024).toFixed(0)} KB`);
