#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║              anyPIM — Update Fix                                    ║
# ║              Repariert haeufige Probleme vor dem Update             ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Verwendung:
#   cd /var/www/anypim   (oder dein Installationspfad)
#   sudo bash update_fix.sh
#
# Was passiert:
#   1. Merge-Konflikte bereinigen (falls vorhanden)
#   2. composer.lock mit composer.json synchronisieren
#   3. update.sh ausfuehren
#

set -euo pipefail

# ─── Farben ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

info()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }
step()  { echo -e "\n${BLUE}━━━ $1 ━━━${NC}\n"; }

# ─── Pruefungen ──────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    error "Dieses Skript muss als root ausgefuehrt werden (sudo bash update_fix.sh)"
fi

if [ ! -f artisan ]; then
    error "Kein Laravel-Projekt gefunden. Bitte ins Installationsverzeichnis wechseln."
fi

echo ""
echo -e "  ${BOLD}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "  ${BOLD}║   anyPIM — Update Fix                                  ║${NC}"
echo -e "  ${BOLD}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""

# ═════════════════════════════════════════════════════════════════════════════
#  1. MERGE-KONFLIKTE BEREINIGEN
# ═════════════════════════════════════════════════════════════════════════════
step "1/3 — Merge-Konflikte bereinigen"

FIXED_MERGE=false

# Unvollstaendiger Merge?
if [ -f .git/MERGE_HEAD ]; then
    warn "Unvollstaendiger Merge erkannt — wird abgebrochen..."
    git merge --abort 2>/dev/null || git reset --merge 2>/dev/null || true
    info "Merge-Zustand bereinigt."
    FIXED_MERGE=true
fi

# Dateien im "needs merge" Zustand?
UNMERGED=$(git ls-files --unmerged 2>/dev/null | awk '{print $4}' | sort -u)
if [ -n "$UNMERGED" ]; then
    warn "Dateien mit Merge-Konflikten gefunden:"
    echo "$UNMERGED" | while read -r f; do
        echo -e "    ${YELLOW}${f}${NC}"
    done
    warn "Setze auf Remote-Version zurueck..."
    echo "$UNMERGED" | while read -r f; do
        git reset -q HEAD -- "$f" 2>/dev/null || true
        git checkout -q -- "$f" 2>/dev/null || true
    done
    info "Merge-Konflikte bereinigt."
    FIXED_MERGE=true
fi

if [ "$FIXED_MERGE" = false ]; then
    info "Keine Merge-Konflikte vorhanden."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  2. COMPOSER.LOCK SYNCHRONISIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "2/3 — composer.lock synchronisieren"

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_NO_INTERACTION=1

# Pruefen ob composer.lock out-of-sync ist
if php -d error_reporting=E_ALL\&~E_DEPRECATED "$(which composer)" validate --no-check-all 2>&1 | grep -q "lock file is not up to date"; then
    warn "composer.lock ist nicht aktuell — synchronisiere..."
    php -d memory_limit=-1 -d error_reporting=E_ALL\&~E_DEPRECATED "$(which composer)" update \
        --no-interaction \
        --prefer-dist \
        2>&1 | grep -v "^Deprecation Notice:"
    info "composer.lock synchronisiert."
else
    info "composer.lock ist bereits aktuell."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  3. UPDATE.SH AUSFUEHREN
# ═════════════════════════════════════════════════════════════════════════════
step "3/3 — update.sh ausfuehren"

if [ ! -f update.sh ]; then
    error "update.sh nicht gefunden. Zuerst: git checkout origin/main -- update.sh"
fi

info "Starte update.sh..."
echo ""
exec bash update.sh "$@"
