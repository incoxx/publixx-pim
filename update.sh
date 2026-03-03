#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║              Publixx PIM — Update Script                            ║
# ║              Holt den neuesten Stand von GitHub und baut neu        ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Verwendung:
#   cd /var/www/publixx-pim   (oder dein Installationspfad)
#   sudo bash update.sh
#
# Was passiert:
#   1. Wartungsmodus aktivieren
#   2. Git Pull (main Branch)
#   3. Composer Install (PHP-Abhaengigkeiten)
#   4. Datenbank-Migrationen
#   5. Frontend-Build (npm ci + npm run build)
#   6. Laravel-Caches neu erstellen
#   7. Horizon (Queue-Worker) neu starten
#   8. Wartungsmodus deaktivieren
#

set -euo pipefail

# ─── Farben ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

info()    { echo -e "${GREEN}[✓]${NC} $1"; }
warn()    { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }
step()    { echo -e "\n${BLUE}━━━ $1 ━━━${NC}\n"; }

# ─── Root-Check ──────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    error "Dieses Script muss als root ausgefuehrt werden: sudo bash update.sh"
fi

# ─── Installationsverzeichnis bestimmen ──────────────────────────────────────
INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Pruefen ob es ein Laravel-Projekt ist
if [ ! -f "${INSTALL_DIR}/artisan" ]; then
    error "Kein Laravel-Projekt in ${INSTALL_DIR} gefunden. Bitte im Installationsverzeichnis ausfuehren."
fi

echo -e "${BOLD}${BLUE}"
cat << 'BANNER'
  ╔══════════════════════════════════════════════════════════╗
  ║   Publixx PIM — Update                                  ║
  ╚══════════════════════════════════════════════════════════╝
BANNER
echo -e "${NC}"

echo -e "  Verzeichnis: ${BOLD}${INSTALL_DIR}${NC}"
echo ""

UPDATE_START=$(date +%s)
cd "$INSTALL_DIR"

# ═════════════════════════════════════════════════════════════════════════════
#  1. WARTUNGSMODUS
# ═════════════════════════════════════════════════════════════════════════════
step "1/7 — Wartungsmodus aktivieren"

php artisan down --retry=60 2>/dev/null || true
info "Wartungsmodus aktiviert."

# Ab hier: bei Fehler Wartungsmodus wieder deaktivieren
cleanup() {
    warn "Fehler aufgetreten — deaktiviere Wartungsmodus..."
    cd "$INSTALL_DIR"
    php artisan up 2>/dev/null || true
}
trap cleanup ERR

# ═════════════════════════════════════════════════════════════════════════════
#  2. GIT PULL
# ═════════════════════════════════════════════════════════════════════════════
step "2/7 — Neuesten Stand von GitHub holen"

# Aktuellen Branch merken
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")

# Falls nicht auf main: auf main wechseln
if [ "$CURRENT_BRANCH" != "main" ]; then
    warn "Aktueller Branch: ${CURRENT_BRANCH} — wechsle zu main..."
    git checkout main
fi

# Aenderungen holen und mergen
OLD_HEAD=$(git rev-parse HEAD)
git pull origin main

NEW_HEAD=$(git rev-parse HEAD)

if [ "$OLD_HEAD" = "$NEW_HEAD" ]; then
    info "Bereits auf dem neuesten Stand (${NEW_HEAD:0:8})."
else
    COMMIT_COUNT=$(git rev-list --count "${OLD_HEAD}..${NEW_HEAD}")
    info "${COMMIT_COUNT} neue Commits geholt (${OLD_HEAD:0:8} -> ${NEW_HEAD:0:8})."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  3. COMPOSER INSTALL
# ═════════════════════════════════════════════════════════════════════════════
step "3/7 — PHP-Abhaengigkeiten aktualisieren"

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_NO_INTERACTION=1

php -d memory_limit=-1 "$(which composer)" install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    2>&1

info "Composer-Abhaengigkeiten aktualisiert."

# ═════════════════════════════════════════════════════════════════════════════
#  4. DATENBANK-MIGRATIONEN
# ═════════════════════════════════════════════════════════════════════════════
step "4/7 — Datenbank-Migrationen"

php artisan migrate --force
info "Migrationen ausgefuehrt."

# ═════════════════════════════════════════════════════════════════════════════
#  5. FRONTEND BUILD
# ═════════════════════════════════════════════════════════════════════════════
step "5/7 — Frontend bauen"

FRONTEND_DIR="${INSTALL_DIR}/pim-frontend"

if [ -d "$FRONTEND_DIR" ]; then
    cd "$FRONTEND_DIR"

    info "Installiere Frontend-Abhaengigkeiten..."
    npm ci --production=false 2>&1

    info "Baue Frontend (Vue 3 + Vite)..."
    npm run build 2>&1

    # Build-Output nach public/ kopieren
    if [ -d "${FRONTEND_DIR}/dist" ]; then
        cp "${FRONTEND_DIR}/dist/index.html" "${INSTALL_DIR}/public/spa.html"
        if [ -d "${FRONTEND_DIR}/dist/assets" ]; then
            cp -r "${FRONTEND_DIR}/dist/assets" "${INSTALL_DIR}/public/"
        fi
        find "${FRONTEND_DIR}/dist" -maxdepth 1 -type f ! -name "index.html" -exec cp {} "${INSTALL_DIR}/public/" \;
        info "Frontend-Build nach public/ kopiert."
    else
        warn "Frontend dist/ nicht gefunden — Build moeglicherweise fehlgeschlagen."
    fi

    cd "$INSTALL_DIR"
else
    warn "Frontend-Verzeichnis nicht gefunden — ueberspringe Frontend-Build."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  6. CACHES & OPTIMIERUNGEN
# ═════════════════════════════════════════════════════════════════════════════
step "6/7 — Caches neu erstellen"

php artisan config:cache
php artisan route:cache
php artisan view:cache

info "Caches erstellt."

# ═════════════════════════════════════════════════════════════════════════════
#  7. SERVICES NEU STARTEN & WARTUNGSMODUS BEENDEN
# ═════════════════════════════════════════════════════════════════════════════
step "7/7 — Services neu starten"

# Dateiberechtigungen korrigieren
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 775 "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
info "Dateiberechtigungen gesetzt."

# Horizon neu starten
supervisorctl restart horizon > /dev/null 2>&1 || warn "Horizon konnte nicht neu gestartet werden."
info "Horizon neu gestartet."

# Apache neu laden (nicht restart — graceful)
systemctl reload apache2 > /dev/null 2>&1 || warn "Apache konnte nicht neu geladen werden."
info "Apache neu geladen."

# Wartungsmodus deaktivieren
trap - ERR
php artisan up
info "Wartungsmodus deaktiviert."

# ═════════════════════════════════════════════════════════════════════════════
#  FERTIG
# ═════════════════════════════════════════════════════════════════════════════
UPDATE_END=$(date +%s)
UPDATE_DURATION=$(( UPDATE_END - UPDATE_START ))

echo ""
echo -e "${BOLD}${GREEN}[✓] Update abgeschlossen in ${UPDATE_DURATION} Sekunden.${NC}"
echo -e "    Stand: $(git log --oneline -1)"
echo ""
