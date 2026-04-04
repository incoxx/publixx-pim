#!/usr/bin/env bash
# anyPIM Video Engine – Einzelne Story generieren
# Usage: ./generate-one.sh <story-id> [--force] [--preflight-only]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENGINE_DIR="$(dirname "$SCRIPT_DIR")"
PROJECT_ROOT="$(dirname "$ENGINE_DIR")"
STORIES_DIR="$PROJECT_ROOT/video-stories"
OUTPUT_DIR="${VIDEO_OUTPUT_DIR:-$PROJECT_ROOT/public/videos}"
STORAGE_DIR="${VIDEO_STORAGE_DIR:-$PROJECT_ROOT/storage/video-engine}"

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
SEEDER=$(cd "$ENGINE_DIR" && npx tsx -e "
  import { parse } from 'yaml';
  import { readFileSync } from 'fs';
  const story = parse(readFileSync('$STORY_PATH', 'utf-8'));
  console.log(story.meta.seeder || '');
")

if [ -n "$SEEDER" ]; then
  echo "→ Seeder: $SEEDER"
  cd "$PROJECT_ROOT" && php artisan db:seed --class="$SEEDER" --force 2>&1 || {
    echo "✗ Seeder fehlgeschlagen"
    exit 1
  }
fi

# Script generieren
TMP_DIR="$STORAGE_DIR/tmp/$STORY_ID-$(date +%s)"
mkdir -p "$TMP_DIR"
SCRIPT_FILE="$TMP_DIR/playwright-script.ts"

echo "→ Playwright-Script generieren..."
cd "$ENGINE_DIR" && npx tsx -e "
  import { validateStory, interpolateEnv } from './src/story-validator.js';
  import { generatePlaywrightScript } from './src/script-generator.js';
  import { writeFileSync } from 'fs';
  const { story } = validateStory('$STORY_PATH');
  if (!story) { console.error('Story ungültig'); process.exit(1); }
  const interpolated = interpolateEnv(story);
  const baseUrl = process.env.ANYPIM_BASE_URL || process.env.VIDEO_ENGINE_BASE_URL || 'http://localhost:8000';
  const script = generatePlaywrightScript(interpolated, baseUrl);
  writeFileSync('$SCRIPT_FILE', script);
  console.log('Script: $SCRIPT_FILE');
"

# Aufnahme starten (Xvfb + ffmpeg + Playwright)
echo "→ Aufnahme starten..."
DISPLAY="${VIDEO_DISPLAY:-:99}"
FPS="${VIDEO_FPS:-30}"
WIDTH=1920
HEIGHT=1080
RECORDING="$TMP_DIR/recording.mp4"

# Xvfb starten
Xvfb "$DISPLAY" -screen 0 "${WIDTH}x${HEIGHT}x24" -ac &
XVFB_PID=$!
sleep 1

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

# Playwright-Script ausführen
DISPLAY="$DISPLAY" npx tsx "$SCRIPT_FILE" 2>&1 || {
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

# Untertitel generieren
echo "→ SRT generieren..."
SRT_FILE="$TMP_DIR/$STORY_ID.srt"
cd "$ENGINE_DIR" && npx tsx -e "
  import { validateStory } from './src/story-validator.js';
  import { extractSubtitles, generateSrt } from './src/subtitle-extractor.js';
  import { writeFileSync } from 'fs';
  const { story } = validateStory('$STORY_PATH');
  if (!story) process.exit(1);
  const entries = extractSubtitles(story);
  writeFileSync('$SRT_FILE', generateSrt(entries));
  console.log('SRT: $SRT_FILE (' + entries.length + ' Einträge)');
"

# Audio erzeugen (optional)
echo "→ Voiceover erzeugen..."
AUDIO_FILE=""
if [ -n "${ELEVENLABS_API_KEY:-}" ] || command -v gtts-cli &>/dev/null; then
  cd "$ENGINE_DIR" && npx tsx -e "
    import { validateStory } from './src/story-validator.js';
    import { VoiceSynthesizer } from './src/voice-synthesizer.js';
    import { createStoryLogger } from './src/logger.js';
    const logger = createStoryLogger('$STORY_ID');
    const { story } = validateStory('$STORY_PATH');
    if (!story) process.exit(1);
    const synth = new VoiceSynthesizer(logger);
    const audioPath = await synth.synthesize(story, '$TMP_DIR');
    if (audioPath) console.log('AUDIO:' + audioPath);
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

# Video rendern (merge)
echo "→ Finales Video rendern..."
QUALITY="${VIDEO_QUALITY:-high}"
FINAL_VIDEO="$TMP_DIR/$STORY_ID-final.mp4"

cd "$ENGINE_DIR" && npx tsx -e "
  import { renderVideo } from './src/video-renderer.js';
  import { createStoryLogger } from './src/logger.js';
  const logger = createStoryLogger('$STORY_ID');
  await renderVideo({
    videoPath: '$RECORDING',
    audioPath: '$AUDIO_FILE' || null,
    srtPath: '$SRT_FILE',
    outputPath: '$FINAL_VIDEO',
    quality: '$QUALITY' as any,
    logger,
  });
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
