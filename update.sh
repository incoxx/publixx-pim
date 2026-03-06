#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║              anyPIM — Update Script                            ║
# ║              Holt den neuesten Stand von GitHub und baut neu        ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Verwendung:
#   cd /var/www/publixx-pim   (oder dein Installationspfad)
#   sudo bash update.sh [optionen]
#
# Optionen:
#   --branch=NAME     Anderen Branch als 'main' verwenden
#   --skip-frontend   Frontend-Build ueberspringen
#   --skip-composer   Composer Install ueberspringen
#   --seed            Nach Migrationen auch Seeders ausfuehren
#   --force           Keine Bestaetigung vor dem Update
#
# Was passiert:
#   1. Wartungsmodus aktivieren
#   2. Git Pull (main oder angegebener Branch)
#   3. Composer Install (PHP-Abhaengigkeiten)
#   4. Datenbank-Migrationen
#   5. Frontend-Build (npm ci + npm run build, subdirectory-aware)
#   6. Laravel-Caches neu erstellen
#   7. Services neu starten + Dateiberechtigungen
#   8. Healthcheck + Wartungsmodus deaktivieren
#

set -euo pipefail

# ─── Farben ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

info()    { echo -e "${GREEN}[✓]${NC} $1"; }
warn()    { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }
step()    { echo -e "\n${BLUE}━━━ $1 ━━━${NC}\n"; }

# ─── Argumente parsen ──────────────────────────────────────────────────────
BRANCH="main"
SKIP_FRONTEND=false
SKIP_COMPOSER=false
RUN_SEED=false
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --branch=*)      BRANCH="${arg#*=}" ;;
        --skip-frontend) SKIP_FRONTEND=true ;;
        --skip-composer) SKIP_COMPOSER=true ;;
        --seed)          RUN_SEED=true ;;
        --force)         FORCE=true ;;
        --help|-h)
            echo "Verwendung: sudo bash update.sh [--branch=NAME] [--skip-frontend] [--skip-composer] [--seed] [--force]"
            exit 0
            ;;
        *)
            warn "Unbekannte Option: ${arg}"
            ;;
    esac
done

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

# Pruefen ob .env existiert
if [ ! -f "${INSTALL_DIR}/.env" ]; then
    error "Keine .env gefunden. Bitte zuerst setup.sh ausfuehren."
fi

echo -e "${BOLD}${BLUE}"
cat << 'BANNER'
  ╔══════════════════════════════════════════════════════════╗
  ║   anyPIM — Update                                  ║
  ╚══════════════════════════════════════════════════════════╝
BANNER
echo -e "${NC}"

# ─── Subdirectory-Modus aus .env erkennen ────────────────────────────────────
APP_URL=$(grep '^APP_URL=' "${INSTALL_DIR}/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
# Pfad-Anteil aus APP_URL extrahieren (z.B. /web aus https://example.com/web)
WEB_PATH=$(echo "$APP_URL" | sed -E 's|^https?://[^/]+||' | sed 's|/$||')

echo -e "  Verzeichnis:  ${BOLD}${INSTALL_DIR}${NC}"
echo -e "  APP_URL:      ${BOLD}${APP_URL}${NC}"
echo -e "  Web-Pfad:     ${BOLD}${WEB_PATH:-/ (Root)}${NC}"
echo -e "  Branch:       ${BOLD}${BRANCH}${NC}"
echo -e "  Frontend:     ${BOLD}$([ "$SKIP_FRONTEND" = true ] && echo "ueberspringen" || echo "bauen")${NC}"
echo ""
echo -e "  Aktueller Stand: ${CYAN}$(git log --oneline -1 2>/dev/null || echo 'unbekannt')${NC}"
echo ""

# Bestaetigung
if [ "$FORCE" = false ]; then
    echo -e -n "  Update starten? [J/n]: "
    read -r CONFIRM
    if [[ "$CONFIRM" =~ ^[nN]$ ]]; then
        echo "  Abgebrochen."
        exit 0
    fi
fi

UPDATE_START=$(date +%s)
cd "$INSTALL_DIR"

# ═════════════════════════════════════════════════════════════════════════════
#  1. WARTUNGSMODUS
# ═════════════════════════════════════════════════════════════════════════════
step "1/8 — Wartungsmodus aktivieren"

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
step "2/8 — Neuesten Stand von GitHub holen"

# Aktuellen Branch merken
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")

# Lokale Aenderungen sichern (z.B. durch Bild-Uploads, Cache-Dateien etc.)
STASHED=false
if ! git diff --quiet HEAD 2>/dev/null || [ -n "$(git ls-files --others --exclude-standard)" ]; then
    warn "Lokale Aenderungen erkannt — werden temporaer gesichert (git stash)..."
    git stash push -m "update.sh auto-stash $(date +%Y%m%d-%H%M%S)"
    STASHED=true
    info "Lokale Aenderungen gesichert."
fi

# Falls nicht auf dem Ziel-Branch: wechseln
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    warn "Aktueller Branch: ${CURRENT_BRANCH} — wechsle zu ${BRANCH}..."
    git fetch origin "$BRANCH"
    git checkout "$BRANCH"
fi

# Aenderungen holen und mergen
OLD_HEAD=$(git rev-parse HEAD)
git pull origin "$BRANCH"

# Lokale Aenderungen wiederherstellen
if [ "$STASHED" = true ]; then
    info "Stelle lokale Aenderungen wieder her..."
    if git stash pop 2>/dev/null; then
        info "Lokale Aenderungen wiederhergestellt."
    else
        warn "Lokale Aenderungen konnten nicht automatisch gemergt werden."
        warn "Gesichert als: $(git stash list | head -1)"
        warn "Manuell wiederherstellen mit: git stash pop"
    fi
fi

NEW_HEAD=$(git rev-parse HEAD)

if [ "$OLD_HEAD" = "$NEW_HEAD" ]; then
    info "Bereits auf dem neuesten Stand (${NEW_HEAD:0:8})."
else
    COMMIT_COUNT=$(git rev-list --count "${OLD_HEAD}..${NEW_HEAD}")
    info "${COMMIT_COUNT} neue Commits geholt (${OLD_HEAD:0:8} -> ${NEW_HEAD:0:8})."

    # Aenderungen zusammenfassen
    echo ""
    echo -e "  ${BOLD}Aenderungen:${NC}"
    git log --oneline -10 "${OLD_HEAD}..${NEW_HEAD}" | while read -r line; do
        echo -e "    ${CYAN}${line}${NC}"
    done
    TOTAL=$(git rev-list --count "${OLD_HEAD}..${NEW_HEAD}")
    if [ "$TOTAL" -gt 10 ]; then
        echo -e "    ${CYAN}... und $(( TOTAL - 10 )) weitere${NC}"
    fi
    echo ""
fi

# ═════════════════════════════════════════════════════════════════════════════
#  3. COMPOSER INSTALL
# ═════════════════════════════════════════════════════════════════════════════
step "3/8 — PHP-Abhaengigkeiten aktualisieren"

if [ "$SKIP_COMPOSER" = true ]; then
    info "Composer-Install uebersprungen (--skip-composer)."
else
    export COMPOSER_ALLOW_SUPERUSER=1
    export COMPOSER_NO_INTERACTION=1

    php -d memory_limit=-1 "$(which composer)" install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist \
        2>&1

    info "Composer-Abhaengigkeiten aktualisiert."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  4. DATENBANK-MIGRATIONEN
# ═════════════════════════════════════════════════════════════════════════════
step "4/8 — Datenbank-Migrationen"

php artisan migrate --force
info "Migrationen ausgefuehrt."

if [ "$RUN_SEED" = true ]; then
    info "Fuehre Seeders aus (--seed)..."
    php artisan db:seed --force
    info "Seeders ausgefuehrt."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  5. FRONTEND BUILD
# ═════════════════════════════════════════════════════════════════════════════
step "5/8 — Frontend bauen"

FRONTEND_DIR="${INSTALL_DIR}/pim-frontend"

if [ "$SKIP_FRONTEND" = true ]; then
    info "Frontend-Build uebersprungen (--skip-frontend)."
elif [ -d "$FRONTEND_DIR" ]; then
    cd "$FRONTEND_DIR"

    info "Installiere Frontend-Abhaengigkeiten..."
    npm ci --production=false 2>&1

    # Bekannte Sicherheitsluecken automatisch patchen
    npm audit fix 2>/dev/null || true

    info "Baue Frontend (Vue 3 + Vite)..."

    # Bei Subdirectory-Deployment: Vite Base-Path und API-URL setzen
    if [ -n "$WEB_PATH" ]; then
        export VITE_BASE_PATH="${WEB_PATH}/"
        export VITE_API_BASE_URL="${WEB_PATH}/api/v1"
        info "Subdirectory-Modus: Base-Path=${VITE_BASE_PATH}"
    fi

    npm run build 2>&1

    # Build-Output nach public/ kopieren
    if [ -d "${FRONTEND_DIR}/dist" ]; then
        info "Kopiere Frontend-Build nach public/..."

        # Alte Assets entfernen, dann neue kopieren
        rm -rf "${INSTALL_DIR}/public/pim-assets"

        cp "${FRONTEND_DIR}/dist/index.html" "${INSTALL_DIR}/public/spa.html"
        if [ -d "${FRONTEND_DIR}/dist/pim-assets" ]; then
            cp -r "${FRONTEND_DIR}/dist/pim-assets" "${INSTALL_DIR}/public/"
        fi
        # Favicon und andere Root-Dateien kopieren
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
step "6/8 — Caches neu erstellen"

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

info "Caches erstellt."

# ═════════════════════════════════════════════════════════════════════════════
#  7. SERVICES NEU STARTEN & BERECHTIGUNGEN
# ═════════════════════════════════════════════════════════════════════════════
step "7/8 — Services neu starten"

# Dateiberechtigungen korrigieren
chown -R www-data:www-data "$INSTALL_DIR"
find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
info "Dateiberechtigungen gesetzt."

# Horizon / Queue Worker neu starten
if command -v supervisorctl > /dev/null 2>&1; then
    if supervisorctl status horizon > /dev/null 2>&1; then
        supervisorctl restart horizon > /dev/null 2>&1 && info "Horizon neu gestartet." \
            || warn "Horizon konnte nicht neu gestartet werden."
    else
        warn "Horizon nicht in Supervisor konfiguriert — starte Queue Worker..."
        # Alten Worker beenden falls vorhanden
        pkill -f "artisan queue:work" > /dev/null 2>&1 || true
        nohup php artisan queue:work --queue=indexing,default --sleep=3 --tries=3 \
            >> "${INSTALL_DIR}/storage/logs/queue-worker.log" 2>&1 &
        info "Queue Worker gestartet (PID: $!)."
    fi
else
    warn "supervisorctl nicht gefunden — starte Queue Worker direkt..."
    pkill -f "artisan queue:work" > /dev/null 2>&1 || true
    nohup php artisan queue:work --queue=indexing,default --sleep=3 --tries=3 \
        >> "${INSTALL_DIR}/storage/logs/queue-worker.log" 2>&1 &
    info "Queue Worker gestartet (PID: $!)."
fi

# Apache neu laden (graceful)
systemctl reload apache2 > /dev/null 2>&1 && info "Apache neu geladen." \
    || warn "Apache konnte nicht neu geladen werden."

# ═════════════════════════════════════════════════════════════════════════════
#  8. HEALTHCHECK & WARTUNGSMODUS BEENDEN
# ═════════════════════════════════════════════════════════════════════════════
step "8/8 — Healthcheck & Wartungsmodus beenden"

# Wartungsmodus deaktivieren
trap - ERR
php artisan up
info "Wartungsmodus deaktiviert."

# Healthcheck
sleep 1
if command -v curl > /dev/null 2>&1; then
    HC_URL="${APP_URL}/api/v1/health"
    HC_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$HC_URL" 2>/dev/null || echo "000")

    if [ "$HC_STATUS" = "200" ]; then
        info "Healthcheck OK (${HC_URL} → ${HC_STATUS})"
    elif [ "$HC_STATUS" = "000" ]; then
        warn "Healthcheck nicht erreichbar (${HC_URL}) — ggf. Firewall oder DNS pruefen."
    else
        warn "Healthcheck HTTP ${HC_STATUS} (${HC_URL}) — bitte manuell pruefen."
    fi
else
    warn "curl nicht installiert — Healthcheck uebersprungen."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  FERTIG
# ═════════════════════════════════════════════════════════════════════════════
UPDATE_END=$(date +%s)
UPDATE_DURATION=$(( UPDATE_END - UPDATE_START ))

echo ""
echo -e "${BOLD}${GREEN}  ╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}  ║   Update abgeschlossen in ${UPDATE_DURATION} Sekunden                      ║${NC}"
echo -e "${BOLD}${GREEN}  ╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Stand:     ${CYAN}$(git log --oneline -1)${NC}"
echo -e "  URL:       ${BOLD}${APP_URL}${NC}"
echo ""
