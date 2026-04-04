#!/usr/bin/env bash
# anyPIM Video Engine – Alle Stories generieren
# Usage: ./generate-all.sh [--force]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
STORIES_DIR="$PROJECT_ROOT/video-stories"

FORCE=""
for arg in "$@"; do
  case "$arg" in
    --force) FORCE="--force" ;;
  esac
done

echo "=== anyPIM Video Engine – Alle Stories ==="
echo ""

TOTAL=0
SUCCESS=0
FAILED=0
SKIPPED=0

for story_dir in "$STORIES_DIR"/*/; do
  # Überspringe _schema, _template, demo-assets
  dir_name=$(basename "$story_dir")
  if [[ "$dir_name" == _* ]] || [[ "$dir_name" == "demo-assets" ]]; then
    continue
  fi

  if [ ! -f "$story_dir/story.yaml" ]; then
    continue
  fi

  TOTAL=$((TOTAL + 1))
  echo "─── Story: $dir_name ───"

  if bash "$SCRIPT_DIR/generate-one.sh" "$dir_name" $FORCE; then
    SUCCESS=$((SUCCESS + 1))
  else
    EXIT_CODE=$?
    if [ $EXIT_CODE -eq 0 ]; then
      SKIPPED=$((SKIPPED + 1))
    else
      FAILED=$((FAILED + 1))
      echo "✗ $dir_name fehlgeschlagen"
    fi
  fi
  echo ""
done

echo "=== Zusammenfassung ==="
echo "  Gesamt:      $TOTAL"
echo "  Erfolgreich: $SUCCESS"
echo "  Übersprungen: $SKIPPED"
echo "  Fehlgeschlagen: $FAILED"

if [ $FAILED -gt 0 ]; then
  exit 1
fi
