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
#   --skip-docs       Dokumentations-Build ueberspringen
#   --skip-tms        TMS-Setup ueberspringen
#   --skip-composer   Composer Install ueberspringen
#   --skip-meilisearch  Meilisearch-Installation/-Sync ueberspringen
#   --seed            Nach Migrationen auch Seeders ausfuehren
#   --force           Keine Bestaetigung vor dem Update
#
# Was passiert:
#   1.  Wartungsmodus aktivieren
#   2.  Git Pull (main oder angegebener Branch)
#   3.  Composer Install (PHP-Abhaengigkeiten)
#   4.  Datenbank-Migrationen
#   5.  Frontend-Build (npm ci + npm run build, subdirectory-aware)
#   6.  Dokumentation bauen (VitePress)
#   7.  TMS (Translation Memory Service) — Composer, Migrationen, Queue Worker
#   8.  Laravel-Caches neu erstellen
#   9.  Services neu starten + Dateiberechtigungen
#   10. Healthcheck + Wartungsmodus deaktivieren
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

info()         { echo -e "${GREEN}[✓]${NC} $1"; }
warn()         { echo -e "${YELLOW}[!]${NC} $1"; }
error()        { echo -e "${RED}[✗]${NC} $1"; exit 1; }
error_noexit() { echo -e "${RED}[✗]${NC} $1"; }
step()         { echo -e "\n${BLUE}━━━ $1 ━━━${NC}\n"; }

# ─── Argumente parsen ──────────────────────────────────────────────────────
BRANCH="main"
SKIP_FRONTEND=false
SKIP_DOCS=false
SKIP_TMS=false
SKIP_COMPOSER=false
SKIP_MEILISEARCH=false
RUN_SEED=false
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --branch=*)      BRANCH="${arg#*=}" ;;
        --skip-frontend) SKIP_FRONTEND=true ;;
        --skip-docs)     SKIP_DOCS=true ;;
        --skip-tms)      SKIP_TMS=true ;;
        --skip-composer) SKIP_COMPOSER=true ;;
        --skip-meilisearch) SKIP_MEILISEARCH=true ;;
        --seed)          RUN_SEED=true ;;
        --force)         FORCE=true ;;
        --help|-h)
            echo "Verwendung: sudo bash update.sh [--branch=NAME] [--skip-frontend] [--skip-docs] [--skip-tms] [--skip-composer] [--skip-meilisearch] [--seed] [--force]"
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

# Git safe.directory setzen (noetig wenn Repository einem anderen User gehoert, z.B. www-data)
git config --global --add safe.directory "${INSTALL_DIR}" 2>/dev/null || true

# Pruefen ob .env existiert
if [ ! -f "${INSTALL_DIR}/.env" ]; then
    error "Keine .env gefunden. Bitte zuerst setup.sh ausfuehren."
fi

# ─── Swap-Check ─────────────────────────────────────────────────────────────
# Bei wenig RAM (< 4 GB) wird Swap benoetigt, damit der Vite-Build
# (Frontend) nicht vom OOM-Killer abgebrochen wird.
TOTAL_RAM_MB=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
SWAP_TOTAL_MB=$(awk '/SwapTotal/ {printf "%d", $2/1024}' /proc/meminfo)

if [ "$TOTAL_RAM_MB" -lt 4096 ] && [ "$SWAP_TOTAL_MB" -lt 512 ]; then
    warn "Nur ${TOTAL_RAM_MB} MB RAM und ${SWAP_TOTAL_MB} MB Swap erkannt."
    info "Erstelle 2 GB Swap-Datei (noetig fuer Frontend-Build)..."

    if [ ! -f /swapfile ]; then
        fallocate -l 2G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile > /dev/null
        swapon /swapfile
        # Permanent machen
        if ! grep -q '/swapfile' /etc/fstab; then
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi
        info "Swap-Datei erstellt und aktiviert (2 GB)."
    else
        # Swapfile existiert aber ist nicht aktiv
        if ! swapon --show | grep -q '/swapfile'; then
            swapon /swapfile 2>/dev/null || true
        fi
        info "Vorhandene Swap-Datei aktiviert."
    fi
elif [ "$TOTAL_RAM_MB" -lt 4096 ]; then
    info "RAM: ${TOTAL_RAM_MB} MB, Swap: ${SWAP_TOTAL_MB} MB — ausreichend."
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
step "1/10 — Wartungsmodus aktivieren"

php artisan down --retry=60 2>/dev/null || true
info "Wartungsmodus aktiviert."

# Ab hier: bei Fehler Wartungsmodus wieder deaktivieren
cleanup() {
    local exit_code=$?
    local failed_line=${BASH_LINENO[0]:-unbekannt}
    echo ""
    error_noexit "Fehler in Zeile ${failed_line} (Exit-Code: ${exit_code})"
    error_noexit "Letzter Befehl ist fehlgeschlagen. Pruefe die Ausgabe oberhalb dieser Meldung."
    warn "Deaktiviere Wartungsmodus..."
    cd "$INSTALL_DIR"
    php artisan up 2>/dev/null || true
}
trap cleanup ERR

# ═════════════════════════════════════════════════════════════════════════════
#  2. GIT PULL
# ═════════════════════════════════════════════════════════════════════════════
step "2/10 — Neuesten Stand von GitHub holen"

# Aktuellen Branch merken
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")

# Abgebrochene Merges bereinigen (z.B. nach fehlgeschlagenem Update)
if [ -f .git/MERGE_HEAD ]; then
    warn "Unvollstaendiger Merge erkannt — wird abgebrochen..."
    git merge --abort 2>/dev/null || git reset --merge 2>/dev/null || true
    info "Merge-Zustand bereinigt."
fi

# Build-Artefakte zuruecksetzen (werden in Schritt 5 ohnehin neu gebaut)
for build_dir in catalog-embed/dist pim-frontend/dist; do
    if [ -d "$build_dir" ]; then
        # Reset index first (handles "needs merge" state), then restore files
        git reset -q HEAD -- "$build_dir" 2>/dev/null || true
        git checkout -q -- "$build_dir" 2>/dev/null || true
        info "Build-Artefakte in ${build_dir}/ zurueckgesetzt (werden spaeter neu gebaut)."
    fi
done

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
        warn "Bereinige Merge-Konflikte (behalte Remote-Version)..."
        git reset HEAD . 2>/dev/null || true
        git checkout -- . 2>/dev/null || true
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
step "3/10 — PHP-Abhaengigkeiten aktualisieren"

if [ "$SKIP_COMPOSER" = true ]; then
    info "Composer-Install uebersprungen (--skip-composer)."
else
    export COMPOSER_ALLOW_SUPERUSER=1
    export COMPOSER_NO_INTERACTION=1

    # PHP 8.4 Deprecation Notices vom System-Composer unterdruecken
    php -d memory_limit=-1 -d error_reporting=E_ALL\&~E_DEPRECATED "$(which composer)" install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist \
        2>&1 | grep -v "^Deprecation Notice:"

    info "Composer-Abhaengigkeiten aktualisiert."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  4. DATENBANK-MIGRATIONEN
# ═════════════════════════════════════════════════════════════════════════════
step "4/10 — Datenbank-Migrationen"

php artisan migrate --force
info "Migrationen ausgefuehrt."

if [ "$RUN_SEED" = true ]; then
    info "Fuehre Seeders aus (--seed)..."
    php artisan db:seed --force
    info "Seeders ausgefuehrt."
fi

# Typesense Collection sicherstellen (falls Typesense konfiguriert)
TYPESENSE_KEY=$(grep '^TYPESENSE_API_KEY=' "${INSTALL_DIR}/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || true)
if [ -n "$TYPESENSE_KEY" ]; then
    php artisan typesense:setup && info "Typesense Collection geprueft." \
        || warn "Typesense Collection konnte nicht geprueft werden."
fi

# ─── Meilisearch: bei Bedarf automatisch installieren ────────────────────────
# Binary, systemd-Dienst und .env-Eintraege werden per Default eingerichtet,
# falls sie fehlen (--skip-meilisearch deaktiviert das). Eine vorhandene
# MEILISEARCH_ENABLED-Zeile in der .env wird respektiert und nie ueberschrieben.
if [ "$SKIP_MEILISEARCH" = false ]; then

    # 1. Binary installieren falls nicht vorhanden
    if ! command -v meilisearch &> /dev/null; then
        info "Meilisearch nicht gefunden — installiere..."
        MEILI_VERSION="1.15.2"
        MEILI_ARCH="amd64"
        curl -sL "https://github.com/meilisearch/meilisearch/releases/download/v${MEILI_VERSION}/meilisearch-linux-${MEILI_ARCH}" \
            -o /usr/local/bin/meilisearch \
            && chmod +x /usr/local/bin/meilisearch \
            && info "Meilisearch ${MEILI_VERSION} installiert." \
            || warn "Meilisearch-Download fehlgeschlagen — semantische Suche bleibt deaktiviert."
    fi

    # 2. systemd-Dienst einrichten falls nicht vorhanden
    MEILI_SERVICE="/etc/systemd/system/meilisearch.service"
    MEILI_DATA_DIR="/var/lib/meilisearch"
    MEILI_API_KEY=""
    if command -v meilisearch &> /dev/null; then
        if [ ! -f "$MEILI_SERVICE" ]; then
            info "Konfiguriere Meilisearch-Dienst..."
            mkdir -p "$MEILI_DATA_DIR"
            chown -R www-data:www-data "$MEILI_DATA_DIR"
            MEILI_API_KEY=$(openssl rand -hex 32 2>/dev/null || cat /proc/sys/kernel/random/uuid | tr -d '-')
            cat > "$MEILI_SERVICE" <<MEILISERVICE
[Unit]
Description=Meilisearch (anyPIM Hybrid Search)
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=${MEILI_DATA_DIR}
ExecStart=/usr/local/bin/meilisearch --http-addr 127.0.0.1:7700 --master-key ${MEILI_API_KEY} --db-path ${MEILI_DATA_DIR}/data
Restart=on-failure

[Install]
WantedBy=multi-user.target
MEILISERVICE
            systemctl daemon-reload
            systemctl enable meilisearch
            systemctl start meilisearch
            info "Meilisearch-Dienst gestartet (Port 7700, API-Key generiert)."
        else
            # Key aus bestehendem Dienst auslesen (fuer .env-Befuellung)
            MEILI_API_KEY=$(grep -oP '(?<=--master-key )\S+' "$MEILI_SERVICE" || true)
            systemctl is-active meilisearch > /dev/null 2>&1 || systemctl start meilisearch || true
        fi
    fi

    # 3. Ollama (lokale Embeddings) installieren falls nicht vorhanden
    if [ -n "$MEILI_API_KEY" ] && ! command -v ollama &> /dev/null; then
        info "Ollama nicht gefunden — installiere (lokale Embeddings)..."
        curl -fsSL https://ollama.ai/install.sh | sh 2>&1 | tail -3 \
            && info "Ollama installiert." \
            || warn "Ollama-Installation fehlgeschlagen — Embedder spaeter einrichten."
    fi
    if [ -n "$MEILI_API_KEY" ] && command -v ollama &> /dev/null; then
        if ! ollama list 2>/dev/null | grep -q nomic-embed-text; then
            info "Lade Embedding-Modell nomic-embed-text..."
            ollama pull nomic-embed-text 2>&1 | tail -2 && info "Modell geladen." \
                || warn "Modell-Download fehlgeschlagen — nachholen mit: ollama pull nomic-embed-text"
        fi
    fi

    # 4. .env-Eintraege ergaenzen falls noch keine vorhanden sind
    if [ -n "$MEILI_API_KEY" ] && ! grep -q '^MEILISEARCH_ENABLED=' "${INSTALL_DIR}/.env" 2>/dev/null; then
        info "Trage Meilisearch-Konfiguration in .env ein..."
        cat >> "${INSTALL_DIR}/.env" <<MEILIENV

# ─── Meilisearch (Semantische Schnellsuche, Hybrid Search) ──────────
MEILISEARCH_ENABLED=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_API_KEY=${MEILI_API_KEY}
MEILISEARCH_INDEX=products
MEILISEARCH_EMBEDDER_ENABLED=true
MEILISEARCH_EMBEDDER_SOURCE=ollama
MEILISEARCH_EMBEDDER_MODEL=nomic-embed-text
MEILISEARCH_EMBEDDER_URL=http://localhost:11434/api/embeddings
MEILISEARCH_SEMANTIC_RATIO=0.5
MEILISEARCH_NUMERIC_TOLERANCE=0.05
MEILIENV
        info "Meilisearch in .env aktiviert (MEILISEARCH_ENABLED=true)."
        # Config-Cache leeren, damit pim:meili-setup die neuen Werte sieht
        php artisan config:clear > /dev/null 2>&1 || true
    fi
fi

# Meilisearch: Index-Settings aktualisieren (Filter, Synonyme, Embedder)
# Falls das PIM-Schema (Attribute/Einheiten) geaendert wurde, werden neue
# filterbare Felder registriert. Dokumente bleiben unveraendert.
MEILI_ENABLED=$(grep '^MEILISEARCH_ENABLED=' "${INSTALL_DIR}/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || true)
if [ "$MEILI_ENABLED" = "true" ]; then
    info "Meilisearch-Index-Settings aktualisieren..."
    php artisan pim:meili-setup --fresh-schema 2>&1 \
        && info "Meilisearch-Settings aktualisiert." \
        || warn "Meilisearch-Setup fehlgeschlagen — spaeter nachholen mit: php artisan pim:meili-setup"

    # Inkrementelle Synchronisierung: nur seit dem letzten Lauf geaenderte
    # Produkte — schnell, kein Full-Reindex. Embeddings berechnet Meilisearch
    # nur fuer geaenderte Dokumente (documentTemplate).
    info "Meilisearch inkrementell synchronisieren..."
    nohup php artisan pim:meili-index \
        >> "${INSTALL_DIR}/storage/logs/meilisearch-index.log" 2>&1 &
    info "Meilisearch-Sync gestartet im Hintergrund (PID: $!)."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  5. FRONTEND BUILD
# ═════════════════════════════════════════════════════════════════════════════
step "5/10 — Frontend bauen"

FRONTEND_DIR="${INSTALL_DIR}/pim-frontend"

if [ "$SKIP_FRONTEND" = true ]; then
    info "Frontend-Build uebersprungen (--skip-frontend)."
elif [ -d "$FRONTEND_DIR" ]; then
    cd "$FRONTEND_DIR"

    info "Installiere Frontend-Abhaengigkeiten..."
    npm ci --fund=false --loglevel=warn 2>&1

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

    # Katalog-Embed Bundles bauen (Online + Offline)
    CATALOG_EMBED_DIR="${INSTALL_DIR}/catalog-embed"
    if [ -d "$CATALOG_EMBED_DIR" ] && [ -f "${CATALOG_EMBED_DIR}/package.json" ]; then
        cd "$CATALOG_EMBED_DIR"
        info "Installiere catalog-embed Abhaengigkeiten..."
        npm ci --fund=false --loglevel=warn 2>&1

        info "Baue Online-Katalog Bundle..."
        npx vite build 2>&1
        # Kopiere Online-Bundle nach public/catalog-embed-assets/
        if [ -d "${CATALOG_EMBED_DIR}/dist" ]; then
            mkdir -p "${INSTALL_DIR}/public/catalog-embed-assets"
            cp "${CATALOG_EMBED_DIR}/dist/catalog-embed.umd.js" "${INSTALL_DIR}/public/catalog-embed-assets/" 2>/dev/null || true
            cp "${CATALOG_EMBED_DIR}/dist/catalog-embed.css" "${INSTALL_DIR}/public/catalog-embed-assets/" 2>/dev/null || true
            cp "${CATALOG_EMBED_DIR}/dist/catalog-embed.es.js" "${INSTALL_DIR}/public/catalog-embed-assets/" 2>/dev/null || true
            info "Online-Katalog Bundle gebaut und nach public/ kopiert."
        fi

        info "Baue Offline-Katalog Bundle..."
        VITE_BUILD_TARGET=offline npx vite build 2>&1
        info "Offline-Katalog Bundle gebaut."

        cd "$INSTALL_DIR"
    else
        warn "catalog-embed/ nicht gefunden — Katalog Bundles werden nicht gebaut."
    fi

    cd "$INSTALL_DIR"
else
    warn "Frontend-Verzeichnis nicht gefunden — ueberspringe Frontend-Build."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  6. DOKUMENTATION BAUEN (VitePress)
# ═════════════════════════════════════════════════════════════════════════════
step "6/10 — Dokumentation bauen"

DOCS_DIR="${INSTALL_DIR}/static-content"

if [ "$SKIP_DOCS" = true ]; then
    info "Dokumentations-Build uebersprungen (--skip-docs)."
elif [ -d "$DOCS_DIR" ] && [ -f "${DOCS_DIR}/package.json" ]; then
    cd "$DOCS_DIR"

    info "Installiere Dokumentations-Abhaengigkeiten..."
    npm ci --fund=false --loglevel=warn 2>&1

    info "Baue Dokumentation (VitePress)..."
    npm run build 2>&1

    if [ -d "${DOCS_DIR}/.vitepress/dist" ]; then
        info "Dokumentation erfolgreich gebaut (${DOCS_DIR}/.vitepress/dist)."

        # Apache-Alias fuer Dokumentation sicherstellen
        # Pruefen ob bereits ein Alias /web/help existiert
        DOCS_ALIAS_EXISTS=false
        if [ -n "$WEB_PATH" ]; then
            # Subdirectory-Modus: Alias in conf-available
            ALIAS_CONF="/etc/apache2/conf-available/publixx-pim.conf"
            if [ -f "$ALIAS_CONF" ] && grep -q "Alias.*help" "$ALIAS_CONF" 2>/dev/null; then
                DOCS_ALIAS_EXISTS=true
            fi
        else
            # Root-Modus: Alias im VHost
            for vhost in /etc/apache2/sites-enabled/*.conf /etc/apache2/sites-available/publixx-pim.conf; do
                if [ -f "$vhost" ] && grep -q "Alias.*/web/help" "$vhost" 2>/dev/null; then
                    DOCS_ALIAS_EXISTS=true
                    break
                fi
            done
        fi

        if [ "$DOCS_ALIAS_EXISTS" = false ]; then
            warn "Apache-Alias fuer Dokumentation (/web/help) fehlt."

            if [ -n "$WEB_PATH" ] && [ -f "$ALIAS_CONF" ]; then
                # Subdirectory-Modus: Alias an bestehende Konfiguration anhaengen
                cat >> "$ALIAS_CONF" <<DOCSALIAS

# anyPIM — Dokumentation (VitePress)
Alias ${WEB_PATH}/help ${INSTALL_DIR}/static-content/.vitepress/dist

<Directory ${INSTALL_DIR}/static-content/.vitepress/dist>
    Options -Indexes
    AllowOverride None
    Require all granted
    FallbackResource ${WEB_PATH}/help/index.html
</Directory>
DOCSALIAS
                info "Docs-Alias zu ${ALIAS_CONF} hinzugefuegt."
            else
                # Root-Modus: Alias direkt in VHost-Datei einfuegen
                VHOST_FILE=""
                # VHost-Datei finden die auf unser Verzeichnis zeigt
                for vf in /etc/apache2/sites-enabled/*.conf /etc/apache2/sites-available/publixx-pim.conf /etc/apache2/sites-available/anypim.conf; do
                    if [ -f "$vf" ] && grep -q "${INSTALL_DIR}" "$vf" 2>/dev/null; then
                        # Symlinks aufloesen (sites-enabled verlinkt auf sites-available)
                        VHOST_FILE="$(readlink -f "$vf")"
                        break
                    fi
                done

                if [ -n "$VHOST_FILE" ]; then
                    info "VHost gefunden: ${VHOST_FILE}"
                    # Docs-Block vor dem letzten </VirtualHost> einfuegen
                    DOCS_BLOCK="\\n    # anyPIM — Dokumentation (VitePress)\\n    Alias /web/help ${INSTALL_DIR}/static-content/.vitepress/dist\\n\\n    <Directory ${INSTALL_DIR}/static-content/.vitepress/dist>\\n        Options -Indexes\\n        AllowOverride None\\n        Require all granted\\n        FallbackResource /web/help/index.html\\n    </Directory>"
                    # Vor dem ersten </VirtualHost> einfuegen
                    sed -i "0,/<\/VirtualHost>/s|</VirtualHost>|${DOCS_BLOCK}\n</VirtualHost>|" "$VHOST_FILE" 2>/dev/null \
                        && info "Docs-Alias in ${VHOST_FILE} eingefuegt." \
                        || warn "Konnte Docs-Alias nicht in VHost einfuegen."

                    # Apache neu laden
                    if apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
                        systemctl reload apache2 2>/dev/null || true
                        info "Apache neu geladen."
                    else
                        warn "Apache-Konfiguration fehlerhaft — bitte manuell pruefen."
                    fi
                else
                    warn "Kein VHost fuer ${INSTALL_DIR} gefunden — bitte Docs-Alias manuell einrichten."
                fi
            fi
        else
            info "Apache-Alias fuer Dokumentation bereits konfiguriert."
        fi
    else
        warn "Dokumentation dist/ nicht gefunden — Build moeglicherweise fehlgeschlagen."
    fi

    cd "$INSTALL_DIR"
else
    warn "Dokumentations-Verzeichnis nicht gefunden — ueberspringe Dokumentations-Build."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  VIDEO ENGINE (optional — npm ci bei Aenderungen)
# ═════════════════════════════════════════════════════════════════════════════
VIDEO_ENGINE_DIR="${INSTALL_DIR}/video-engine"

if [ -d "$VIDEO_ENGINE_DIR" ] && [ -f "${VIDEO_ENGINE_DIR}/package.json" ]; then
    # Nur npm ci wenn package-lock.json geaendert wurde
    if git diff --name-only "${OLD_HEAD}..${NEW_HEAD}" 2>/dev/null | grep -q "video-engine/package"; then
        info "Video-Engine: Abhaengigkeiten aktualisieren..."
        cd "$VIDEO_ENGINE_DIR"
        npm ci --fund=false --loglevel=warn 2>&1
        cd "$INSTALL_DIR"
        info "Video-Engine: npm-Pakete aktualisiert."
    else
        info "Video-Engine: Keine Paket-Aenderungen — uebersprungen."
    fi
fi

# ═════════════════════════════════════════════════════════════════════════════
#  7. TMS (Translation Memory Service)
# ═════════════════════════════════════════════════════════════════════════════
step "7/10 — TMS (Translation Memory Service)"

TMS_DIR="${INSTALL_DIR}/tms"

# Pruefen ob TMS in der PIM .env aktiviert ist
TMS_ENABLED=$(grep '^TMS_ENABLED=' "${INSTALL_DIR}/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "false")

if [ "$SKIP_TMS" = true ]; then
    info "TMS-Setup uebersprungen (--skip-tms)."
elif [ "$TMS_ENABLED" != "true" ]; then
    info "TMS nicht aktiviert (TMS_ENABLED=${TMS_ENABLED:-false}). Ueberspringe."
    info "Zum Aktivieren: TMS_ENABLED=true in .env setzen."
elif [ -d "$TMS_DIR" ] && [ -f "${TMS_DIR}/artisan" ]; then
    cd "$TMS_DIR"

    # .env erstellen falls nicht vorhanden
    if [ ! -f "${TMS_DIR}/.env" ]; then
        if [ -f "${TMS_DIR}/.env.example" ]; then
            cp "${TMS_DIR}/.env.example" "${TMS_DIR}/.env"
            warn "TMS .env aus .env.example erstellt — bitte API-Keys konfigurieren!"
        else
            error "TMS .env.example nicht gefunden."
        fi
    fi

    # APP_KEY generieren falls leer
    TMS_APP_KEY=$(grep '^APP_KEY=' "${TMS_DIR}/.env" | cut -d'=' -f2-)
    if [ -z "$TMS_APP_KEY" ]; then
        info "Generiere TMS APP_KEY..."
        php artisan key:generate --force 2>&1
    fi

    # Composer Install
    if [ "$SKIP_COMPOSER" = false ]; then
        info "TMS: Installiere PHP-Abhaengigkeiten..."
        php -d memory_limit=-1 -d error_reporting=E_ALL\&~E_DEPRECATED "$(which composer)" install \
            --no-dev \
            --optimize-autoloader \
            --no-interaction \
            --prefer-dist \
            2>&1 | grep -v "^Deprecation Notice:"
        info "TMS: Composer-Abhaengigkeiten aktualisiert."
    fi

    # Migrationen
    info "TMS: Fuehre Migrationen aus..."
    php artisan migrate --force 2>&1
    info "TMS: Migrationen ausgefuehrt."

    # Caches
    php artisan config:cache 2>&1 || true
    php artisan route:cache 2>&1 || true
    info "TMS: Caches erstellt."

    # Storage-Verzeichnisse sicherstellen
    mkdir -p "${TMS_DIR}/storage/"{app,framework/{cache,sessions,views},logs}
    chown -R www-data:www-data "${TMS_DIR}/storage" "${TMS_DIR}/bootstrap"
    chmod -R 775 "${TMS_DIR}/storage"

    # TMS Queue Worker neu starten
    pkill -f "tms/artisan queue:work" > /dev/null 2>&1 || true
    nohup php "${TMS_DIR}/artisan" queue:work --queue=tms,default --sleep=3 --tries=3 \
        >> "${TMS_DIR}/storage/logs/queue-worker.log" 2>&1 &
    info "TMS: Queue Worker gestartet (PID: $!)."

    # TMS als PHP Built-in Server starten (Produktion: besser per Apache/Nginx)
    TMS_PORT=$(grep '^APP_URL=' "${TMS_DIR}/.env" 2>/dev/null | grep -oP ':\K[0-9]+$' || echo "8001")
    pkill -f "php.*-S.*:${TMS_PORT}.*tms/public" > /dev/null 2>&1 || true
    nohup php -S "127.0.0.1:${TMS_PORT}" -t "${TMS_DIR}/public" \
        >> "${TMS_DIR}/storage/logs/server.log" 2>&1 &
    info "TMS: Server gestartet auf Port ${TMS_PORT} (PID: $!)."

    cd "$INSTALL_DIR"
    info "TMS: Setup abgeschlossen."
else
    warn "TMS-Verzeichnis nicht gefunden oder unvollstaendig — ueberspringe TMS."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  8. CACHES & OPTIMIERUNGEN
# ═════════════════════════════════════════════════════════════════════════════
step "8/10 — Caches neu erstellen"

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

info "Caches erstellt."

# ═════════════════════════════════════════════════════════════════════════════
#  8. SERVICES NEU STARTEN & BERECHTIGUNGEN
# ═════════════════════════════════════════════════════════════════════════════
step "9/10 — Services neu starten"

# Dateiberechtigungen korrigieren (ohne langsames find -exec)
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R u=rwX,g=rX,o=rX "$INSTALL_DIR"
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
        pkill -f "${INSTALL_DIR}/artisan queue:work" > /dev/null 2>&1 || true
        nohup php artisan queue:work --queue=pdf,indexing,default --sleep=3 --tries=3 --timeout=300 \
            >> "${INSTALL_DIR}/storage/logs/queue-worker.log" 2>&1 &
        info "Queue Worker gestartet (PID: $!)."
    fi
else
    warn "supervisorctl nicht gefunden — starte Queue Worker direkt..."
    pkill -f "${INSTALL_DIR}/artisan queue:work" > /dev/null 2>&1 || true
    nohup php artisan queue:work --queue=pdf,indexing,default --sleep=3 --tries=3 --timeout=300 \
        >> "${INSTALL_DIR}/storage/logs/queue-worker.log" 2>&1 &
    info "Queue Worker gestartet (PID: $!)."
fi

# Apache neu starten (restart statt reload, damit neue PHP-Configs greifen)
systemctl restart apache2 > /dev/null 2>&1 && info "Apache neu gestartet." \
    || warn "Apache konnte nicht neu gestartet werden."

# ═════════════════════════════════════════════════════════════════════════════
#  9. HEALTHCHECK & WARTUNGSMODUS BEENDEN
# ═════════════════════════════════════════════════════════════════════════════
step "10/10 — Healthcheck & Wartungsmodus beenden"

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
DURATION_TEXT="Update abgeschlossen in ${UPDATE_DURATION} Sekunden"
PAD_LEN=$(( 56 - ${#DURATION_TEXT} ))
PADDING=$(printf '%*s' "$PAD_LEN" '')
echo -e "${BOLD}${GREEN}  ╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}  ║   ${DURATION_TEXT}${PADDING}║${NC}"
echo -e "${BOLD}${GREEN}  ╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Stand:     ${CYAN}$(git log --oneline -1)${NC}"
echo -e "  URL:       ${BOLD}${APP_URL}${NC}"
echo ""
