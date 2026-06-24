#!/usr/bin/env bash
# anyPIM Video Engine – Einzelne Story generieren
# Usage: ./generate-one.sh <story-id> [--force] [--preflight-only]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENGINE_DIR="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$ENGINE_DIR")"
STORIES_DIR="$PROJECT_ROOT/video-stories"
# OUTPUT_DIR: Absolut machen falls relativ
_OUTPUT_DIR="${VIDEO_ENGINE_OUTPUT_DIR:-${VIDEO_OUTPUT_DIR:-public/videos}}"
if [[ "$_OUTPUT_DIR" != /* ]]; then
  OUTPUT_DIR="$PROJECT_ROOT/$_OUTPUT_DIR"
else
  OUTPUT_DIR="$_OUTPUT_DIR"
fi
_STORAGE_DIR="${VIDEO_ENGINE_STORAGE_DIR:-${VIDEO_STORAGE_DIR:-storage/video-engine}}"
if [[ "$_STORAGE_DIR" != /* ]]; then
  STORAGE_DIR="$PROJECT_ROOT/$_STORAGE_DIR"
else
  STORAGE_DIR="$_STORAGE_DIR"
fi

# .env laden damit ELEVENLABS_API_KEY & Co. in der Shell verfügbar sind
# (analog zu preflight.ts die dotenv lädt – Bash lädt .env nicht automatisch)
if [ -f "$PROJECT_ROOT/.env" ]; then
  set -a
  # shellcheck disable=SC1091
  source "$PROJECT_ROOT/.env"
  set +a
fi

STORY_ID="${1:-}"
FORCE=false
PREFLIGHT_ONLY=false

# Argumente parsen
for arg in "$@"; do
  case "$arg" in
    --force) FORCE=true ;;
    --preflight-only|--preflight) PREFLIGHT_ONLY=true ;;
  esac
done

if [ -z "$STORY_ID" ]; then
  echo "Usage: $0 <story-id> [--force] [--preflight-only]"
  echo ""
  echo "Verfügbare Stories:"
  cd "$ENGINE_DIR" && npx tsx src/story-validator.ts
  exit 1
fi

STORY_PATH="$STORIES_DIR/$STORY_ID/story.yaml"

if [ ! -f "$STORY_PATH" ]; then
  echo "✗ Story nicht gefunden: $STORY_PATH"
  exit 1
fi

# Preflight-Check
echo "=== Preflight Check ==="
cd "$ENGINE_DIR" && npx tsx src/preflight.ts "$STORY_ID"
PREFLIGHT_EXIT=$?

if [ $PREFLIGHT_EXIT -ne 0 ]; then
  echo "✗ Preflight fehlgeschlagen"
  exit 1
fi

if [ "$PREFLIGHT_ONLY" = true ]; then
  echo "✓ Preflight bestanden (--preflight-only)"
  exit 0
fi

# Idempotenz-Check
OUTPUT_FILE="$OUTPUT_DIR/$STORY_ID.mp4"
if [ -f "$OUTPUT_FILE" ] && [ "$FORCE" != true ]; then
  echo "ℹ Video existiert bereits: $OUTPUT_FILE"
  echo "  Nutze --force um zu überschreiben"
  exit 0
fi

# Produktionsschutz
if [ "${APP_ENV:-local}" = "production" ]; then
  echo "✗ Video-Generierung ist in der Produktionsumgebung nicht erlaubt"
  exit 1
fi

echo ""
echo "=== Story: $STORY_ID ==="

# Seeder ausführen (falls konfiguriert)
SEEDER=$(cd "$ENGINE_DIR" && STORY_FILE="$STORY_PATH" npx tsx -e "
  import { parse } from 'yaml';
  import { readFileSync } from 'fs';
  const story = parse(readFileSync(process.env.STORY_FILE!, 'utf-8'));
  console.log(story.meta.seeder || '');
")

if [ -n "$SEEDER" ]; then
  echo "→ Seeder: $SEEDER"
  cd "$PROJECT_ROOT" && php artisan db:seed --class="$SEEDER" --force 2>&1 || {
    echo "✗ Seeder fehlgeschlagen"
    exit 1
  }
fi

# Temporäres Verzeichnis
TMP_DIR="$STORAGE_DIR/tmp/$STORY_ID-$(date +%s)"
mkdir -p "$TMP_DIR"
AUDIO_SEGMENTS_FILE="$TMP_DIR/audio-segments.json"
TIMESTAMPS_FILE="$TMP_DIR/timestamps.json"

# ──────────────────────────────────────────────────────────────────
# SCHRITT 1: Audio VOR der Aufnahme generieren (für Pausen-Berechnung)
# ──────────────────────────────────────────────────────────────────
echo "→ Voiceover erzeugen (vor Aufnahme – für Pausen-Berechnung)..."
AUDIO_FILE=""
if [ -n "${ELEVENLABS_API_KEY:-}" ] || command -v gtts-cli &>/dev/null; then
  cd "$ENGINE_DIR" && STORY_FILE="$STORY_PATH" AUDIO_TMP_DIR="$TMP_DIR" VIDEO_STORY_ID="$STORY_ID" npx tsx -e "
    import { validateStory } from './src/story-validator';
    import { VoiceSynthesizer } from './src/voice-synthesizer';
    import { createStoryLogger } from './src/logger';
    (async () => {
      const logger = createStoryLogger(process.env.VIDEO_STORY_ID!);
      const { story } = validateStory(process.env.STORY_FILE!);
      if (!story) process.exit(1);
      const synth = new VoiceSynthesizer(logger);
      const audioPath = await synth.synthesize(story, process.env.AUDIO_TMP_DIR!);
      if (audioPath) console.log('AUDIO:' + audioPath);
    })();
  " | while read -r line; do
    if [[ "$line" == AUDIO:* ]]; then
      echo "${line#AUDIO:}" > "$TMP_DIR/.audio-path"
    else
      echo "$line"
    fi
  done
  [ -f "$TMP_DIR/.audio-path" ] && AUDIO_FILE=$(cat "$TMP_DIR/.audio-path")
else
  echo "  Kein TTS verfügbar – Video ohne Voiceover"
fi

# ──────────────────────────────────────────────────────────────────
# SCHRITT 2: Playwright-Script generieren MIT Audio-Dauern
# ──────────────────────────────────────────────────────────────────
SCRIPT_FILE="$TMP_DIR/playwright-script.ts"

echo "→ Playwright-Script generieren (mit Audio-Pausen)..."
cd "$ENGINE_DIR" && STORY_FILE="$STORY_PATH" SCRIPT_OUTPUT="$SCRIPT_FILE" AUDIO_SEG_FILE="$AUDIO_SEGMENTS_FILE" npx tsx -e "
  import { validateStory, interpolateEnv } from './src/story-validator';
  import { generatePlaywrightScript } from './src/script-generator';
  import { writeFileSync, readFileSync, existsSync } from 'fs';
  const { story } = validateStory(process.env.STORY_FILE!);
  if (!story) { console.error('Story ungültig'); process.exit(1); }
  const interpolated = interpolateEnv(story);
  const baseUrl = process.env.ANYPIM_BASE_URL || process.env.VIDEO_ENGINE_BASE_URL || 'http://localhost:8000';
  // Audio-Segment-Dauern laden (falls vorhanden)
  let audioSegments = undefined;
  const segFile = process.env.AUDIO_SEG_FILE!;
  if (existsSync(segFile)) {
    audioSegments = JSON.parse(readFileSync(segFile, 'utf-8'));
    console.log('Script: Pausen angepasst an ' + audioSegments.length + ' Audio-Segmente');
  }
  const script = generatePlaywrightScript(interpolated, baseUrl, audioSegments);
  writeFileSync(process.env.SCRIPT_OUTPUT!, script);
  console.log('Script: ' + process.env.SCRIPT_OUTPUT);
"

# ──────────────────────────────────────────────────────────────────
# SCHRITT 3: Aufnahme (Xvfb + ffmpeg + Playwright)
# ──────────────────────────────────────────────────────────────────
echo "→ Aufnahme starten..."
DISPLAY="${VIDEO_ENGINE_DISPLAY:-${VIDEO_DISPLAY:-:99}}"
FPS="${VIDEO_ENGINE_FPS:-${VIDEO_FPS:-30}}"
WIDTH=1920
HEIGHT=1080
RECORDING="$TMP_DIR/recording.mp4"

# Xvfb starten (altes Display aufräumen falls vorhanden)
if [ -f "/tmp/.X${DISPLAY#:}-lock" ]; then
  OLD_PID=$(cat "/tmp/.X${DISPLAY#:}-lock" 2>/dev/null | tr -d ' ')
  if [ -n "$OLD_PID" ] && kill -0 "$OLD_PID" 2>/dev/null; then
    echo "  Beende altes Xvfb auf Display $DISPLAY (PID $OLD_PID)"
    kill "$OLD_PID" 2>/dev/null || true
    sleep 0.5
  fi
  rm -f "/tmp/.X${DISPLAY#:}-lock" 2>/dev/null || true
fi

Xvfb "$DISPLAY" -screen 0 "${WIDTH}x${HEIGHT}x24" -ac &
XVFB_PID=$!
XVFB_READY=false
for i in $(seq 1 10); do
  sleep 0.5
  if ! kill -0 "$XVFB_PID" 2>/dev/null; then
    break
  fi
  if command -v xdpyinfo >/dev/null 2>&1; then
    if xdpyinfo -display "$DISPLAY" >/dev/null 2>&1; then
      XVFB_READY=true
      break
    fi
  else
    if [ "$i" -ge 3 ]; then
      XVFB_READY=true
      break
    fi
  fi
done
if [ "$XVFB_READY" = false ]; then
  echo "✗ Xvfb konnte nicht gestartet werden"
  exit 1
fi

# ffmpeg starten
ffmpeg -y -f x11grab -video_size "${WIDTH}x${HEIGHT}" -framerate "$FPS" -i "$DISPLAY" \
  -c:v libx264 -preset ultrafast -pix_fmt yuv420p "$RECORDING" &
FFMPEG_PID=$!
sleep 1

# Cleanup bei Abbruch
cleanup() {
  kill "$FFMPEG_PID" 2>/dev/null || true
  sleep 1
  kill "$XVFB_PID" 2>/dev/null || true
}
trap cleanup EXIT

# Chromium-Pfad auflösen – nutzt runtime-deps.ts Fallback-Logik um Versions-Versatz
# zwischen installierten Browsern und PLAYWRIGHT_BROWSERS_PATH abzufangen.
CHROMIUM_PATH=$(cd "$ENGINE_DIR" && npx tsx -e "
  import { resolveChromium } from './src/runtime-deps';
  const p = resolveChromium();
  if (p) process.stdout.write(p);
" 2>/dev/null || true)
if [ -n "$CHROMIUM_PATH" ]; then
  echo "  Chromium: $CHROMIUM_PATH"
else
  echo "  Chromium: Playwright-Standard"
fi

# Playwright-Script ausführen (wartet jetzt lange genug für Audio)
cd "$ENGINE_DIR" && DISPLAY="$DISPLAY" NODE_PATH="$ENGINE_DIR/node_modules" \
  TIMESTAMPS_FILE="$TIMESTAMPS_FILE" PLAYWRIGHT_CHROMIUM_PATH="$CHROMIUM_PATH" \
  npx tsx "$SCRIPT_FILE" 2>&1 || {
  echo "⚠ Playwright-Script mit Fehlern beendet"
}

# ffmpeg sauber stoppen
kill -INT "$FFMPEG_PID" 2>/dev/null
wait "$FFMPEG_PID" 2>/dev/null || true

# Xvfb stoppen
kill "$XVFB_PID" 2>/dev/null
wait "$XVFB_PID" 2>/dev/null || true
trap - EXIT

echo "→ Aufnahme beendet: $RECORDING"

# ──────────────────────────────────────────────────────────────────
# SCHRITT 4: Audio mit echten Timestamps neu positionieren
# ──────────────────────────────────────────────────────────────────
if [ -n "$AUDIO_FILE" ] && [ -f "$TIMESTAMPS_FILE" ]; then
  echo "→ Audio an Aufnahme-Timestamps ausrichten..."
  cd "$ENGINE_DIR" && STORY_FILE="$STORY_PATH" AUDIO_TMP_DIR="$TMP_DIR" VIDEO_STORY_ID="$STORY_ID" TS_FILE="$TIMESTAMPS_FILE" npx tsx -e "
    import { validateStory } from './src/story-validator';
    import { VoiceSynthesizer } from './src/voice-synthesizer';
    import { createStoryLogger } from './src/logger';
    (async () => {
      const logger = createStoryLogger(process.env.VIDEO_STORY_ID!);
      const { story } = validateStory(process.env.STORY_FILE!);
      if (!story) process.exit(1);
      const synth = new VoiceSynthesizer(logger);
      const audioPath = await synth.synthesize(story, process.env.AUDIO_TMP_DIR!, process.env.TS_FILE);
      if (audioPath) console.log('AUDIO:' + audioPath);
    })();
  " | while read -r line; do
    if [[ "$line" == AUDIO:* ]]; then
      echo "${line#AUDIO:}" > "$TMP_DIR/.audio-path"
    else
      echo "$line"
    fi
  done
  [ -f "$TMP_DIR/.audio-path" ] && AUDIO_FILE=$(cat "$TMP_DIR/.audio-path")
fi

# ──────────────────────────────────────────────────────────────────
# SCHRITT 5: SRT generieren (aus Timestamps + Audio-Dauern)
# ──────────────────────────────────────────────────────────────────
echo "→ SRT generieren..."
SRT_FILE="$TMP_DIR/$STORY_ID.srt"
cd "$ENGINE_DIR" && STORY_FILE="$STORY_PATH" SRT_OUTPUT="$SRT_FILE" TS_FILE="$TIMESTAMPS_FILE" AUDIO_SEG_FILE="$AUDIO_SEGMENTS_FILE" npx tsx -e "
  import { validateStory } from './src/story-validator';
  import { extractSubtitles, extractSubtitlesFromTimestamps, generateSrt } from './src/subtitle-extractor';
  import { writeFileSync, existsSync } from 'fs';
  const tsFile = process.env.TS_FILE!;
  const audioSegFile = process.env.AUDIO_SEG_FILE!;
  let entries;
  if (existsSync(tsFile)) {
    entries = extractSubtitlesFromTimestamps(tsFile, existsSync(audioSegFile) ? audioSegFile : undefined);
    console.log('SRT: Synchronisiert mit Timestamps' + (existsSync(audioSegFile) ? ' + Audio-Dauern' : ''));
  } else {
    const { story } = validateStory(process.env.STORY_FILE!);
    if (!story) process.exit(1);
    entries = extractSubtitles(story);
    console.log('SRT: Fallback auf geschaetzte Dauern');
  }
  writeFileSync(process.env.SRT_OUTPUT!, generateSrt(entries));
  console.log('SRT: ' + process.env.SRT_OUTPUT + ' (' + entries.length + ' Eintraege)');
"

# ──────────────────────────────────────────────────────────────────
# SCHRITT 6: Video rendern (merge Video + Audio + SRT + Sonic Logo)
# ──────────────────────────────────────────────────────────────────
echo "→ Finales Video rendern..."
QUALITY="${VIDEO_ENGINE_QUALITY:-${VIDEO_QUALITY:-high}}"
FINAL_VIDEO="$TMP_DIR/$STORY_ID-final.mp4"
SONIC_LOGO="$PROJECT_ROOT/video-stories/demo-assets/anypim-sonic-logo.mp3"

# audioPath: leerer String → null, sonst Pfad
AUDIO_ARG="${AUDIO_FILE:-}"
cd "$ENGINE_DIR" && VIDEO_PATH="$RECORDING" AUDIO_PATH="$AUDIO_ARG" SRT_PATH="$SRT_FILE" \
  RENDER_OUTPUT="$FINAL_VIDEO" RENDER_QUALITY="$QUALITY" VIDEO_STORY_ID="$STORY_ID" \
  SONIC_LOGO_PATH="$SONIC_LOGO" \
  npx tsx -e "
  import { renderVideo } from './src/video-renderer';
  import { createStoryLogger } from './src/logger';
  import { existsSync } from 'fs';
  (async () => {
    const logger = createStoryLogger(process.env.VIDEO_STORY_ID!);
    const sonicPath = process.env.SONIC_LOGO_PATH;
    await renderVideo({
      videoPath: process.env.VIDEO_PATH!,
      audioPath: process.env.AUDIO_PATH || null,
      srtPath: process.env.SRT_PATH!,
      sonicLogoPath: sonicPath && existsSync(sonicPath) ? sonicPath : null,
      outputPath: process.env.RENDER_OUTPUT!,
      quality: (process.env.RENDER_QUALITY || 'high') as 'high' | 'medium' | 'low',
      logger,
    });
  })();
"

# Output kopieren
mkdir -p "$OUTPUT_DIR"
cp "$FINAL_VIDEO" "$OUTPUT_FILE"
[ -f "$SRT_FILE" ] && cp "$SRT_FILE" "$OUTPUT_DIR/$STORY_ID.srt"

echo ""
echo "=== Fertig ==="
echo "✓ Video: $OUTPUT_FILE"
echo "✓ SRT:   $OUTPUT_DIR/$STORY_ID.srt"
ls -lh "$OUTPUT_FILE"

# Temporäre Dateien aufräumen
rm -rf "$TMP_DIR"
