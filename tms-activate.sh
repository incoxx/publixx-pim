#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║       anyPIM — Translation Memory Service aktivieren                ║
# ║       Schluessel abgleichen, Dienst starten, Verbindung pruefen     ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Fuer den Fall, dass die GUI meldet:
#   "Das Translation Memory ist nicht erreichbar. Pruefen: laeuft der Dienst,
#    stimmt TMS_API_KEY in .env und tms/.env?"
#
# Das Script macht genau das, was diese Meldung verlangt, und zwar in einem
# Durchgang: Schluessel auf beiden Seiten angleichen, fehlende Variablen
# ergaenzen, Dienst und Queue Worker starten, Config-Caches neu bauen und die
# Verbindung anschliessend echt nachmessen (Health, Authentifizierung, Status).
#
# Verwendung:
#   cd /var/www/publixx-pim   (oder dein Installationspfad)
#   sudo bash tms-activate.sh [optionen]
#
# Optionen:
#   --status               Nur pruefen und berichten, nichts veraendern
#   --api-key=KEY          Gemeinsames Shared Secret vorgeben
#                          (ohne Angabe: vorhandener Key wird uebernommen,
#                           fehlt er, wird einer erzeugt)
#   --deepl-key=KEY        DeepL API-Key
#   --deepl-url=URL        DeepL Endpunkt (Standard: https://api-free.deepl.com/v2,
#                          fuer DeepL Pro: https://api.deepl.com/v2)
#   --google-key=KEY       Google-Translate API-Key
#   --anthropic-key=KEY    Anthropic API-Key
#   --anthropic-model=NAME Anthropic Modell (Standard: claude-sonnet-4-6)
#   --openai-key=KEY       OpenAI API-Key
#   --openai-model=NAME    OpenAI Modell (Standard: gpt-4o)
#   --provider-chain=A,B   Reihenfolge der Uebersetzungs-Provider
#   --languages=de,en,...  Ruecktfall-Zielsprachen fuer tms/.env
#   --port=8001            Port des TMS (Standard: aus TMS_BASE_URL)
#   --no-restart           Apache und Supervisor nicht anfassen
#   --help | -h            Diese Hilfe
#
# Hinweis zu den Schluesseln:
#   Auf der Kommandozeile uebergebene Keys landen in der Shell-History. Wer das
#   vermeiden will, traegt sie direkt in tms/.env ein und ruft das Script danach
#   ohne die Key-Optionen auf — bestehende Werte werden nie geloescht.
#   In der Ausgabe und im Protokoll erscheinen Keys ausschliesslich maskiert.
#
# Protokoll:
#   storage/logs/tms-activate-JJJJMMTT-HHMMSS.log   (die letzten 10 bleiben)
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

# ─── Protokollierung ────────────────────────────────────────────────────────
LOG_FILE=""
WARN_MESSAGES=()
ERROR_MESSAGES=()

info()         { echo -e "${GREEN}[✓]${NC} $1"; }
warn()         { WARN_MESSAGES+=("$1"); echo -e "${YELLOW}[!]${NC} $1"; }
error()        { ERROR_MESSAGES+=("$1"); echo -e "${RED}[✗]${NC} $1"; log_hint; exit 1; }
error_noexit() { ERROR_MESSAGES+=("$1"); echo -e "${RED}[✗]${NC} $1"; }
step()         { echo -e "\n${BLUE}━━━ $1 ━━━${NC}\n"; }

log_hint() {
    [ -n "$LOG_FILE" ] || return 0
    echo -e "  Vollstaendiges Protokoll: ${CYAN}${LOG_FILE}${NC}"
}

print_summary() {
    local n_warn=${#WARN_MESSAGES[@]}
    local n_err=${#ERROR_MESSAGES[@]}
    local m

    echo ""
    if [ "$n_err" -eq 0 ] && [ "$n_warn" -eq 0 ]; then
        info "Meldungen: keine Warnungen, keine Fehler."
    else
        echo -e "${BOLD}Meldungen: ${n_err} Fehler, ${n_warn} Warnung(en)${NC}"
        for m in ${ERROR_MESSAGES[@]+"${ERROR_MESSAGES[@]}"}; do
            echo -e "  ${RED}[✗]${NC} ${m}"
        done
        for m in ${WARN_MESSAGES[@]+"${WARN_MESSAGES[@]}"}; do
            echo -e "  ${YELLOW}[!]${NC} ${m}"
        done
    fi
    log_hint
}

# Der tee-Subprozess schreibt asynchron — kurz warten, damit die letzten
# Zeilen noch in der Logdatei landen.
flush_log() {
    [ -n "$LOG_FILE" ] || return 0
    sleep 0.3
}

# ─── Argumente ──────────────────────────────────────────────────────────────
ONLY_STATUS=false
NO_RESTART=false
OPT_API_KEY=""
OPT_DEEPL_KEY=""
OPT_DEEPL_URL=""
OPT_GOOGLE_KEY=""
OPT_ANTHROPIC_KEY=""
OPT_ANTHROPIC_MODEL=""
OPT_OPENAI_KEY=""
OPT_OPENAI_MODEL=""
OPT_PROVIDER_CHAIN=""
OPT_LANGUAGES=""
OPT_PORT=""

for arg in "$@"; do
    case "$arg" in
        --status)             ONLY_STATUS=true ;;
        --no-restart)         NO_RESTART=true ;;
        --api-key=*)          OPT_API_KEY="${arg#*=}" ;;
        --deepl-key=*)        OPT_DEEPL_KEY="${arg#*=}" ;;
        --deepl-url=*)        OPT_DEEPL_URL="${arg#*=}" ;;
        --google-key=*)       OPT_GOOGLE_KEY="${arg#*=}" ;;
        --anthropic-key=*)    OPT_ANTHROPIC_KEY="${arg#*=}" ;;
        --anthropic-model=*)  OPT_ANTHROPIC_MODEL="${arg#*=}" ;;
        --openai-key=*)       OPT_OPENAI_KEY="${arg#*=}" ;;
        --openai-model=*)     OPT_OPENAI_MODEL="${arg#*=}" ;;
        --provider-chain=*)   OPT_PROVIDER_CHAIN="${arg#*=}" ;;
        --languages=*)        OPT_LANGUAGES="${arg#*=}" ;;
        --port=*)             OPT_PORT="${arg#*=}" ;;
        --help|-h)
            sed -n '2,48p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            warn "Unbekannte Option: ${arg}"
            ;;
    esac
done

# ─── Root-Check ─────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    error "Dieses Script muss als root ausgefuehrt werden: sudo bash tms-activate.sh"
fi

# ─── Verzeichnisse pruefen ──────────────────────────────────────────────────
INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PIM_ENV="${INSTALL_DIR}/.env"
TMS_DIR="${INSTALL_DIR}/tms"

if [ ! -f "${INSTALL_DIR}/artisan" ]; then
    error "Kein Laravel-Projekt in ${INSTALL_DIR} gefunden. Bitte im Installationsverzeichnis ausfuehren."
fi
if [ ! -f "$PIM_ENV" ]; then
    error "Keine .env in ${INSTALL_DIR} gefunden. Bitte zuerst setup.sh ausfuehren."
fi
if [ ! -f "${TMS_DIR}/artisan" ]; then
    error "TMS-Verzeichnis ${TMS_DIR} fehlt oder ist unvollstaendig. Bitte zuerst update.sh ausfuehren."
fi

# ─── Protokoll-Datei ────────────────────────────────────────────────────────
LOG_DIR="${INSTALL_DIR}/storage/logs"
mkdir -p "$LOG_DIR" 2>/dev/null || true
LOG_FILE_CANDIDATE="${LOG_DIR}/tms-activate-$(date +%Y%m%d-%H%M%S).log"

if : > "$LOG_FILE_CANDIDATE" 2>/dev/null; then
    LOG_FILE="$LOG_FILE_CANDIDATE"
    exec > >(tee >(sed -u -E 's/\x1B\[[0-9;]*[a-zA-Z]//g' >> "$LOG_FILE")) 2>&1
    ls -1t "${LOG_DIR}"/tms-activate-*.log 2>/dev/null | tail -n +11 | xargs -r rm -f 2>/dev/null || true
else
    warn "Protokoll-Datei ${LOG_FILE_CANDIDATE} nicht beschreibbar — Lauf ohne Log."
fi

# ─── Fehlerbehandlung ───────────────────────────────────────────────────────
tms_failed() {
    local exit_code=$?
    local failed_line=${BASH_LINENO[0]:-unbekannt}
    echo ""
    error_noexit "Abbruch in Zeile ${failed_line} (Exit-Code: ${exit_code})"
    echo -e "${RED}[✗]${NC} Der letzte Befehl ist fehlgeschlagen. Details siehe Ausgabe oberhalb."
    print_summary
    flush_log
}
trap tms_failed ERR

# ─── Hilfsfunktionen fuer .env ──────────────────────────────────────────────
# Liest einen Wert, ohne bei fehlendem Treffer zu scheitern (wichtig wegen
# 'set -euo pipefail' — ein leeres grep liefert Exit 1).
read_env() {
    local file="$1" key="$2"
    [ -f "$file" ] || { echo ""; return 0; }
    grep -m1 "^${key}=" "$file" 2>/dev/null | cut -d'=' -f2- | tr -d '"'"'" || echo ""
}

# Setzt einen Wert (ersetzt die erste Fundstelle oder haengt an). Der Wert geht
# ueber die Umgebung an awk statt ueber -v: awk wertet in -v-Zuweisungen
# Escape-Sequenzen aus und wuerde einen Key mit Backslash veraendern.
set_env() {
    local file="$1" key="$2" value="$3" tmp
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        tmp="$(mktemp)"
        SET_ENV_KEY="$key" SET_ENV_VALUE="$value" awk '
            BEGIN { k = ENVIRON["SET_ENV_KEY"]; v = ENVIRON["SET_ENV_VALUE"]; done = 0 }
            !done && index($0, k "=") == 1 { print k "=" v; done = 1; next }
            { print }
        ' "$file" > "$tmp"
        # Inhalt zurueckschreiben statt mv: erhaelt Eigentuemer und Rechte der .env
        cat "$tmp" > "$file"
        rm -f "$tmp"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

# Traegt einen Wert nur ein, wenn der Schluessel noch fehlt
default_env() {
    local file="$1" key="$2" value="$3"
    grep -q "^${key}=" "$file" 2>/dev/null || printf '%s=%s\n' "$key" "$value" >> "$file"
}

# Schluessel niemals im Klartext ausgeben — die Ausgabe landet auch im Protokoll
mask() {
    local v="${1:-}"
    if [ -z "$v" ]; then
        echo "(nicht gesetzt)"
    elif [ "${#v}" -le 10 ]; then
        echo "**** (${#v} Zeichen)"
    else
        echo "${v:0:6}…${v: -2} (${#v} Zeichen)"
    fi
}

echo -e "${BOLD}${BLUE}"
cat << 'BANNER'
  ╔══════════════════════════════════════════════════════════╗
  ║   anyPIM — Translation Memory aktivieren                 ║
  ╚══════════════════════════════════════════════════════════╝
BANNER
echo -e "${NC}"

# ═════════════════════════════════════════════════════════════════════════════
#  1. IST-ZUSTAND
# ═════════════════════════════════════════════════════════════════════════════
step "1/7 — Ist-Zustand ermitteln"

PIM_KEY="$(read_env "$PIM_ENV" "TMS_API_KEY")"
TMS_KEY="$(read_env "${TMS_DIR}/.env" "TMS_API_KEY")"
PIM_ENABLED="$(read_env "$PIM_ENV" "TMS_ENABLED")"
TMS_BASE_URL="$(read_env "$PIM_ENV" "TMS_BASE_URL")"
TMS_BASE_URL="${TMS_BASE_URL:-http://127.0.0.1:8001/api}"

# Port: Option schlaegt URL, URL schlaegt Standard
TMS_PORT="$(echo "$TMS_BASE_URL" | grep -oP ':\K[0-9]+' | head -1 || true)"
TMS_PORT="${OPT_PORT:-${TMS_PORT:-8001}}"

echo -e "  Installation:   ${INSTALL_DIR}"
echo -e "  TMS_ENABLED:    ${PIM_ENABLED:-(nicht gesetzt)}"
echo -e "  TMS_BASE_URL:   ${TMS_BASE_URL}"
echo -e "  Port:           ${TMS_PORT}"
echo -e "  Key (.env):     $(mask "$PIM_KEY")"
echo -e "  Key (tms/.env): $(mask "$TMS_KEY")"

if [ -n "$PIM_KEY" ] && [ -n "$TMS_KEY" ]; then
    if [ "$PIM_KEY" = "$TMS_KEY" ]; then
        info "Schluessel auf beiden Seiten identisch."
    else
        warn "Schluessel unterschiedlich — genau das fuehrt zu HTTP 401 und der Meldung in der GUI."
    fi
fi

if [ "$ONLY_STATUS" = true ]; then
    info "Nur-Pruefen-Modus (--status): es wird nichts veraendert."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  2. PIM-.env
# ═════════════════════════════════════════════════════════════════════════════
if [ "$ONLY_STATUS" = false ]; then
    step "2/7 — Schluessel und Variablen in der PIM-.env setzen"

    # Key festlegen: Option > vorhandener PIM-Key > vorhandener TMS-Key > neu
    NEW_KEY=false
    if [ -n "$OPT_API_KEY" ]; then
        SHARED_KEY="$OPT_API_KEY"
    elif [ -n "$PIM_KEY" ] && [ "$PIM_KEY" != "change-me-to-a-secure-key" ]; then
        SHARED_KEY="$PIM_KEY"
    elif [ -n "$TMS_KEY" ] && [ "$TMS_KEY" != "change-me-to-a-secure-key" ]; then
        # Nur das TMS hat einen brauchbaren Key — den uebernehmen, statt einen
        # neuen zu erzeugen: sonst muesste das TMS unnoetig neu gecacht werden.
        SHARED_KEY="$TMS_KEY"
    else
        SHARED_KEY="$(openssl rand -hex 32 2>/dev/null || tr -d '-' < /proc/sys/kernel/random/uuid)"
        NEW_KEY=true
    fi

    set_env "$PIM_ENV" "TMS_API_KEY" "$SHARED_KEY"
    if [ "$NEW_KEY" = true ]; then
        info "Neuer Shared Secret erzeugt: $(mask "$SHARED_KEY")"
    else
        info "Shared Secret uebernommen: $(mask "$SHARED_KEY")"
    fi

    # Das ist der Zweck dieses Scripts — anders als update.sh, das eine
    # ausdrueckliche Festlegung TMS_ENABLED=false bewusst stehen laesst.
    if [ "$PIM_ENABLED" != "true" ]; then
        set_env "$PIM_ENV" "TMS_ENABLED" "true"
        info "TMS_ENABLED=true gesetzt (vorher: ${PIM_ENABLED:-nicht gesetzt})."
    fi

    set_env "$PIM_ENV" "TMS_BASE_URL" "http://127.0.0.1:${TMS_PORT}/api"
    default_env "$PIM_ENV" "TMS_TIMEOUT" "5"
    [ -n "$OPT_LANGUAGES" ] && set_env "$PIM_ENV" "TMS_TARGET_LANGUAGES" "$OPT_LANGUAGES"
    info "TMS_BASE_URL und TMS_TIMEOUT gesetzt."
else
    SHARED_KEY="$PIM_KEY"
fi

# ═════════════════════════════════════════════════════════════════════════════
#  3. tms/.env
# ═════════════════════════════════════════════════════════════════════════════
if [ "$ONLY_STATUS" = false ]; then
    step "3/7 — tms/.env abgleichen (inkl. Provider-Schluessel)"

    if [ ! -f "${TMS_DIR}/.env" ]; then
        # Frisch anlegen: DB-Zugang aus der PIM-Konfiguration uebernehmen,
        # eigene Datenbank per Konvention <pim-datenbank>_tms.
        PIM_DB_NAME="$(read_env "$PIM_ENV" "DB_DATABASE")"
        PIM_DB_USER="$(read_env "$PIM_ENV" "DB_USERNAME")"
        PIM_DB_PASS="$(read_env "$PIM_ENV" "DB_PASSWORD")"
        PIM_LANGS="$(read_env "$PIM_ENV" "TMS_TARGET_LANGUAGES")"
        PIM_LANGS="${PIM_LANGS:-en,fr,es,it,nl}"

        cat > "${TMS_DIR}/.env" <<TMSENV
APP_NAME=TMS
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://127.0.0.1:${TMS_PORT}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${PIM_DB_NAME}_tms
DB_USERNAME=${PIM_DB_USER}
DB_PASSWORD=${PIM_DB_PASS}

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=4

QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Muss zur Supervisor-Konfiguration passen (queue:work --queue=tms,default)
REDIS_QUEUE=tms
QUEUE_FAILED_DRIVER=database-uuids

TMS_API_KEY=${SHARED_KEY}

TMS_TARGET_LANGUAGES=${PIM_LANGS}
TMS_PROVIDER_CHAIN=deepl,google
TMS_CACHE_TTL=86400

DEEPL_API_KEY=
DEEPL_API_URL=https://api-free.deepl.com/v2
GOOGLE_TRANSLATE_API_KEY=
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o
TMSENV
        info "tms/.env aus der PIM-Konfiguration erzeugt."
    else
        # Bestehende Datei: nur das Noetige nachziehen, alles andere bleibt.
        set_env "${TMS_DIR}/.env" "TMS_API_KEY" "$SHARED_KEY"
        set_env "${TMS_DIR}/.env" "APP_URL" "http://127.0.0.1:${TMS_PORT}"
        info "Shared Secret und APP_URL in tms/.env nachgezogen."
    fi

    # Alle Provider-Schluessel als Zeilen sicherstellen — auch leer. Sonst muss
    # man beim Nachtragen erst wissen, wie die Variable heisst.
    default_env "${TMS_DIR}/.env" "DEEPL_API_KEY" ""
    default_env "${TMS_DIR}/.env" "DEEPL_API_URL" "https://api-free.deepl.com/v2"
    default_env "${TMS_DIR}/.env" "GOOGLE_TRANSLATE_API_KEY" ""
    default_env "${TMS_DIR}/.env" "ANTHROPIC_API_KEY" ""
    default_env "${TMS_DIR}/.env" "ANTHROPIC_MODEL" "claude-sonnet-4-6"
    default_env "${TMS_DIR}/.env" "OPENAI_API_KEY" ""
    default_env "${TMS_DIR}/.env" "OPENAI_MODEL" "gpt-4o"
    default_env "${TMS_DIR}/.env" "TMS_PROVIDER_CHAIN" "deepl,google"
    default_env "${TMS_DIR}/.env" "TMS_CACHE_TTL" "86400"
    # Werden von tms/config/queue.php gelesen, fehlten bisher in der Vorlage.
    # Die Warteschlange muss 'tms' heissen, sonst arbeitet der Supervisor-Worker
    # (--queue=tms,default) an einer anderen Queue als der Dienst befuellt.
    default_env "${TMS_DIR}/.env" "REDIS_QUEUE" "tms"
    default_env "${TMS_DIR}/.env" "QUEUE_FAILED_DRIVER" "database-uuids"

    # Uebergebene Provider-Schluessel eintragen (leere Optionen loeschen nichts)
    [ -n "$OPT_DEEPL_KEY" ]        && { set_env "${TMS_DIR}/.env" "DEEPL_API_KEY" "$OPT_DEEPL_KEY";                info "DeepL-Key gesetzt: $(mask "$OPT_DEEPL_KEY")"; }
    [ -n "$OPT_DEEPL_URL" ]        && { set_env "${TMS_DIR}/.env" "DEEPL_API_URL" "$OPT_DEEPL_URL";                info "DeepL-URL gesetzt: ${OPT_DEEPL_URL}"; }
    [ -n "$OPT_GOOGLE_KEY" ]       && { set_env "${TMS_DIR}/.env" "GOOGLE_TRANSLATE_API_KEY" "$OPT_GOOGLE_KEY";    info "Google-Key gesetzt: $(mask "$OPT_GOOGLE_KEY")"; }
    [ -n "$OPT_ANTHROPIC_KEY" ]    && { set_env "${TMS_DIR}/.env" "ANTHROPIC_API_KEY" "$OPT_ANTHROPIC_KEY";        info "Anthropic-Key gesetzt: $(mask "$OPT_ANTHROPIC_KEY")"; }
    [ -n "$OPT_ANTHROPIC_MODEL" ]  && { set_env "${TMS_DIR}/.env" "ANTHROPIC_MODEL" "$OPT_ANTHROPIC_MODEL";        info "Anthropic-Modell: ${OPT_ANTHROPIC_MODEL}"; }
    [ -n "$OPT_OPENAI_KEY" ]       && { set_env "${TMS_DIR}/.env" "OPENAI_API_KEY" "$OPT_OPENAI_KEY";              info "OpenAI-Key gesetzt: $(mask "$OPT_OPENAI_KEY")"; }
    [ -n "$OPT_OPENAI_MODEL" ]     && { set_env "${TMS_DIR}/.env" "OPENAI_MODEL" "$OPT_OPENAI_MODEL";              info "OpenAI-Modell: ${OPT_OPENAI_MODEL}"; }
    [ -n "$OPT_PROVIDER_CHAIN" ]   && { set_env "${TMS_DIR}/.env" "TMS_PROVIDER_CHAIN" "$OPT_PROVIDER_CHAIN";      info "Provider-Reihenfolge: ${OPT_PROVIDER_CHAIN}"; }
    [ -n "$OPT_LANGUAGES" ]        && { set_env "${TMS_DIR}/.env" "TMS_TARGET_LANGUAGES" "$OPT_LANGUAGES";         info "Zielsprachen (Rueckfall): ${OPT_LANGUAGES}"; }

    if [ "$(read_env "${TMS_DIR}/.env" "APP_DEBUG")" = "true" ]; then
        warn "APP_DEBUG=true in tms/.env — fuer den Produktivbetrieb auf false setzen."
    fi

    # Uebersicht: was steht jetzt in tms/.env? Keys maskiert, damit die Ausgabe
    # (und das Protokoll) keine Geheimnisse enthaelt.
    echo ""
    echo -e "  ${BOLD}Schluessel in tms/.env${NC}"
    # APP_KEY fehlt hier bewusst: der entsteht erst in Schritt 4 und wuerde an
    # dieser Stelle bei einer Neuinstallation faelschlich als leer erscheinen.
    echo -e "    TMS_API_KEY              $(mask "$(read_env "${TMS_DIR}/.env" "TMS_API_KEY")")"
    echo -e "    DEEPL_API_KEY            $(mask "$(read_env "${TMS_DIR}/.env" "DEEPL_API_KEY")")"
    echo -e "    GOOGLE_TRANSLATE_API_KEY $(mask "$(read_env "${TMS_DIR}/.env" "GOOGLE_TRANSLATE_API_KEY")")"
    echo -e "    ANTHROPIC_API_KEY        $(mask "$(read_env "${TMS_DIR}/.env" "ANTHROPIC_API_KEY")")"
    echo -e "    OPENAI_API_KEY           $(mask "$(read_env "${TMS_DIR}/.env" "OPENAI_API_KEY")")"
    echo -e "    TMS_PROVIDER_CHAIN       $(read_env "${TMS_DIR}/.env" "TMS_PROVIDER_CHAIN")"
    echo -e "    TMS_TARGET_LANGUAGES     $(read_env "${TMS_DIR}/.env" "TMS_TARGET_LANGUAGES")"
    echo ""

    chown www-data:www-data "${TMS_DIR}/.env" 2>/dev/null || true
    chmod 640 "${TMS_DIR}/.env" 2>/dev/null || true

    # Ohne mindestens einen Provider bleibt das TM rein manuell — kein Fehler,
    # aber es erklaert, warum spaeter nichts automatisch uebersetzt wird.
    if [ -z "$(read_env "${TMS_DIR}/.env" "DEEPL_API_KEY")" ] \
       && [ -z "$(read_env "${TMS_DIR}/.env" "GOOGLE_TRANSLATE_API_KEY")" ] \
       && [ -z "$(read_env "${TMS_DIR}/.env" "ANTHROPIC_API_KEY")" ] \
       && [ -z "$(read_env "${TMS_DIR}/.env" "OPENAI_API_KEY")" ]; then
        warn "Kein Provider-Schluessel hinterlegt — das TM sammelt Begriffe, uebersetzt aber nichts maschinell."
        warn "Nachtragen z.B. mit: sudo bash tms-activate.sh --deepl-key=DEIN_KEY"
    fi
fi

# ═════════════════════════════════════════════════════════════════════════════
#  4. ABHAENGIGKEITEN, SCHLUESSEL, DATENBANK
# ═════════════════════════════════════════════════════════════════════════════
if [ "$ONLY_STATUS" = false ]; then
    step "4/7 — Abhaengigkeiten, APP_KEY, Datenbank, Migrationen"

    mkdir -p "${TMS_DIR}/storage/"{app,framework/{cache,sessions,views},logs} \
             "${TMS_DIR}/bootstrap/cache"

    # Composer nur, wenn vendor/ fehlt — sonst ist das Sache von update.sh.
    if [ ! -f "${TMS_DIR}/vendor/autoload.php" ]; then
        info "Installiere PHP-Abhaengigkeiten des TMS..."
        TMS_COMPOSER_LOG="${TMS_DIR}/storage/logs/composer.log"
        if (cd "$TMS_DIR" && php -d memory_limit=-1 "$(command -v composer)" install \
                --no-dev --optimize-autoloader --no-interaction --prefer-dist \
                > "$TMS_COMPOSER_LOG" 2>&1); then
            info "Abhaengigkeiten installiert."
        else
            tail -5 "$TMS_COMPOSER_LOG" 2>/dev/null || true
            error "composer install fehlgeschlagen — Details: ${TMS_COMPOSER_LOG}"
        fi
    fi

    [ -f "${TMS_DIR}/vendor/autoload.php" ] \
        || error "tms/vendor/autoload.php fehlt — artisan im TMS nicht lauffaehig."

    if [ -z "$(read_env "${TMS_DIR}/.env" "APP_KEY")" ]; then
        (cd "$TMS_DIR" && php artisan key:generate --force > /dev/null 2>&1) \
            && info "APP_KEY erzeugt: $(mask "$(read_env "${TMS_DIR}/.env" "APP_KEY")")" \
            || error "key:generate im TMS fehlgeschlagen."
    else
        info "APP_KEY vorhanden: $(mask "$(read_env "${TMS_DIR}/.env" "APP_KEY")")"
    fi

    # Datenbank anlegen, falls sie fehlt
    TMS_DB="$(read_env "${TMS_DIR}/.env" "DB_DATABASE")"
    TMS_DB_USER="$(read_env "${TMS_DIR}/.env" "DB_USERNAME")"
    if [ -n "$TMS_DB" ] && command -v mysql > /dev/null 2>&1; then
        if ! mysql -u root -e "USE \`${TMS_DB}\`" 2>/dev/null; then
            if mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`${TMS_DB}\` \
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
                GRANT ALL PRIVILEGES ON \`${TMS_DB}\`.* TO '${TMS_DB_USER}'@'localhost'; \
                FLUSH PRIVILEGES;" 2>/dev/null; then
                info "Datenbank '${TMS_DB}' angelegt."
            else
                warn "Datenbank '${TMS_DB}' konnte nicht angelegt werden (kein root-Zugang?)."
            fi
        fi
    fi

    if (cd "$TMS_DIR" && php artisan migrate --force 2>&1); then
        info "Migrationen ausgefuehrt."
    else
        error "Migrationen fehlgeschlagen — DB-Zugang in tms/.env pruefen."
    fi

    chown -R www-data:www-data "${TMS_DIR}/storage" "${TMS_DIR}/bootstrap" 2>/dev/null || true
    chmod -R 775 "${TMS_DIR}/storage" "${TMS_DIR}/bootstrap/cache" 2>/dev/null || true
fi

# ═════════════════════════════════════════════════════════════════════════════
#  5. DIENSTE
# ═════════════════════════════════════════════════════════════════════════════
if [ "$ONLY_STATUS" = false ] && [ "$NO_RESTART" = false ]; then
    step "5/7 — Dienst und Queue Worker starten"

    # Altlasten aus frueheren Versionen der Setup-Skripte beenden
    pkill -f "tms/artisan queue:work" > /dev/null 2>&1 || true
    pkill -f "php.*-S.*127.0.0.1:${TMS_PORT}" > /dev/null 2>&1 || true

    # Der Schreibvorgang ist bewusst abgefangen: schlaegt er fehl, wuerde
    # 'set -e' hier den ganzen Lauf beenden und die Schritte 6 und 7 (Caches,
    # Verbindungspruefung) faenden nie statt.
    mkdir -p /etc/supervisor/conf.d 2>/dev/null || true
    if command -v supervisorctl > /dev/null 2>&1 \
       && cat > /etc/supervisor/conf.d/anypim-tms-worker.conf <<TMSWORKER
[program:anypim-tms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${TMS_DIR}/artisan queue:work --queue=tms,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=${TMS_DIR}/storage/logs/queue-worker.log
stopwaitsecs=3600
TMSWORKER
    then
        supervisorctl reread > /dev/null 2>&1 || true
        supervisorctl update > /dev/null 2>&1 || true
        supervisorctl restart "anypim-tms-worker:*" > /dev/null 2>&1 \
            || supervisorctl start "anypim-tms-worker:*" > /dev/null 2>&1 || true

        # Nicht behaupten, sondern nachsehen. Der Worker ist keine Nebensache:
        # der TMS speichert beim Ingest nichts direkt, sondern stellt einen
        # ProcessIngestBatchJob in seine Queue. Laeuft der Worker nicht, quittiert
        # der Ingest mit 202 — und es entsteht trotzdem kein einziger Begriff.
        sleep 1
        WORKER_STATUS="$(supervisorctl status "anypim-tms-worker:*" 2>/dev/null || true)"
        if echo "$WORKER_STATUS" | grep -q "RUNNING"; then
            info "Queue Worker laeuft ($(echo "$WORKER_STATUS" | grep -c RUNNING) Prozess(e))."
        else
            error_noexit "Queue Worker laeuft NICHT — ohne ihn uebernimmt das TMS keine Begriffe."
            echo "$WORKER_STATUS" | sed 's/^/     /'

            # Bei "Exited too quickly" steht der Grund im Worker-Log — den hier
            # gleich mitliefern, statt nur den Pfad zu nennen. Ohne ihn ist die
            # Meldung von Supervisor nicht zu gebrauchen.
            WORKER_LOG="${TMS_DIR}/storage/logs/queue-worker.log"
            if [ -s "$WORKER_LOG" ]; then
                echo ""
                echo -e "     ${BOLD}Letzte Zeilen aus queue-worker.log${NC}"
                tail -15 "$WORKER_LOG" | sed 's/^/       /'
                echo ""
            else
                echo -e "     Worker-Log ist leer (${WORKER_LOG}) — der Prozess startet gar nicht erst."
                echo -e "     Dann steht der Grund in Supervisors eigenem Log: /var/log/supervisor/supervisord.log"
            fi

            echo -e "     Im Vordergrund testen — zeigt den Fehler direkt:"
            echo -e "       cd ${TMS_DIR} && sudo -u www-data php artisan queue:work --queue=tms,default --once"
        fi
    else
        warn "Supervisor nicht verfuegbar oder /etc/supervisor/conf.d nicht beschreibbar —"
        warn "Queue Worker nicht eingerichtet. Ohne ihn uebernimmt das TMS keine Begriffe."
    fi

    # Ohne public/.htaccess reicht Apache nur vorhandene Dateien aus: "/" laeuft
    # ueber den DirectoryIndex noch, jede echte Route (/up, /api/stats) endet
    # aber in einem 404 von Apache, ohne dass Laravel je gefragt wird. Die Datei
    # gehoert ins Repository — hier nur als Selbstheilung fuer Installationen,
    # bei denen sie fehlt.
    if [ ! -f "${TMS_DIR}/public/.htaccess" ] \
       && cat > "${TMS_DIR}/public/.htaccess" <<'TMSHTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
TMSHTACCESS
    then
        chown www-data:www-data "${TMS_DIR}/public/.htaccess" 2>/dev/null || true
        warn "tms/public/.htaccess fehlte und wurde angelegt — ohne sie liefert jede TMS-Route 404."
    elif [ ! -f "${TMS_DIR}/public/.htaccess" ]; then
        warn "tms/public/.htaccess fehlt und konnte nicht angelegt werden — TMS-Routen liefern 404."
    fi

    # Die .htaccess ist wirkungslos, wenn mod_rewrite nicht geladen ist.
    if command -v a2enmod > /dev/null 2>&1; then
        if ! apache2ctl -M 2>/dev/null | grep -q rewrite_module; then
            a2enmod rewrite > /dev/null 2>&1 && info "mod_rewrite aktiviert." \
                || warn "mod_rewrite konnte nicht aktiviert werden — TMS-Routen bleiben auf 404."
        fi
    fi

    if command -v a2ensite > /dev/null 2>&1; then
        cat > /etc/apache2/sites-available/anypim-tms.conf <<TMSVHOST
# anyPIM — Translation Memory Service (nur ueber Loopback erreichbar)
Listen 127.0.0.1:${TMS_PORT}

<VirtualHost 127.0.0.1:${TMS_PORT}>
    ServerName tms.localhost
    DocumentRoot ${TMS_DIR}/public

    <Directory ${TMS_DIR}/public>
        AllowOverride All
        Require ip 127.0.0.1
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/anypim-tms-error.log
    CustomLog \${APACHE_LOG_DIR}/anypim-tms-access.log combined
</VirtualHost>
TMSVHOST
        a2ensite anypim-tms.conf > /dev/null 2>&1 || true

        # Voller Neustart, kein reload: eine neue Listen-Direktive wird nur beim
        # Neustart gebunden. configtest prueft die Syntax, nicht ob der Port frei
        # ist — bei Konflikt zurueckrollen, sonst nimmt der fehlgeschlagene
        # Apache-Start das PIM mit.
        if apache2ctl configtest 2>&1 | grep -q "Syntax OK" && systemctl restart apache2 2>/dev/null; then
            info "TMS erreichbar via Apache auf 127.0.0.1:${TMS_PORT}."
        else
            a2dissite anypim-tms.conf > /dev/null 2>&1 || true
            systemctl restart apache2 2>/dev/null || true
            warn "Apache-VHost konnte nicht aktiviert werden (Port ${TMS_PORT} belegt?) — VHost wieder deaktiviert."
        fi
    else
        warn "Apache nicht gefunden — VHost nicht eingerichtet."
    fi
elif [ "$NO_RESTART" = true ]; then
    step "5/7 — Dienste"
    info "Uebersprungen (--no-restart)."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  6. CACHES
# ═════════════════════════════════════════════════════════════════════════════
if [ "$ONLY_STATUS" = false ]; then
    step "6/7 — Config-Caches neu bauen"

    # Ohne diesen Schritt bleibt die Aenderung wirkungslos: beide Anwendungen
    # laufen mit gecachter Konfiguration und lesen die .env gar nicht mehr.
    (cd "$TMS_DIR" && php artisan config:cache > /dev/null 2>&1) || warn "config:cache im TMS fehlgeschlagen."
    (cd "$TMS_DIR" && php artisan route:cache > /dev/null 2>&1) || true
    (cd "$INSTALL_DIR" && php artisan config:cache > /dev/null 2>&1) || warn "config:cache im PIM fehlgeschlagen."
    info "Caches in PIM und TMS neu gebaut."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  7. VERIFIKATION
# ═════════════════════════════════════════════════════════════════════════════
step "7/7 — Verbindung nachmessen"

VERIFY_KEY="$(read_env "$PIM_ENV" "TMS_API_KEY")"
VERIFY_BASE="$(read_env "$PIM_ENV" "TMS_BASE_URL")"
VERIFY_BASE="${VERIFY_BASE:-http://127.0.0.1:${TMS_PORT}/api}"
VERIFY_BASE="${VERIFY_BASE%/}"
VERIFY_ORIGIN="${VERIFY_BASE%/api}"
TMS_REACHABLE=false

if ! command -v curl > /dev/null 2>&1; then
    warn "curl nicht installiert — Verbindung kann nicht gemessen werden."
else
    # a) Laeuft der Dienst ueberhaupt? (Laravel-Health-Route, ohne Auth)
    # curl gibt bei Verbindungsfehlern selbst "000" aus und endet mit Exit != 0
    # — deshalb kein '|| echo 000', das haenge sonst ein zweites "000" an.
    HEALTH_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${VERIFY_ORIGIN}/up" 2>/dev/null || true)"
    HEALTH_CODE="${HEALTH_CODE:-000}"
    if [ "$HEALTH_CODE" = "200" ]; then
        info "Dienst erreichbar (${VERIFY_ORIGIN}/up → 200)."
    elif [ "$HEALTH_CODE" = "404" ]; then
        # 404 heisst: da antwortet ein Webserver, aber er reicht die Anfrage
        # nicht an Laravel weiter. Das ist ein anderer Fehler als "laeuft nicht".
        error_noexit "HTTP 404 auf ${VERIFY_ORIGIN}/up — der Webserver antwortet, aber Laravel wird nicht erreicht."
        echo -e "     Fast immer eine fehlende Rewrite-Regel. Pruefen:"
        echo -e "       ls -l ${TMS_DIR}/public/.htaccess       (muss existieren)"
        echo -e "       apache2ctl -M | grep rewrite            (rewrite_module muss geladen sein)"
        echo -e "       apache2ctl -S | grep ${TMS_PORT}                 (antwortet wirklich der TMS-VHost?)"
        echo -e "     Dieses Script legt die .htaccess bei Bedarf an — danach erneut ausfuehren."
    else
        error_noexit "Dienst NICHT erreichbar (${VERIFY_ORIGIN}/up → ${HEALTH_CODE})."
        echo -e "     Pruefen: systemctl status apache2 · ss -ltnp | grep ${TMS_PORT} · tail /var/log/apache2/anypim-tms-error.log"
    fi

    # b) Stimmt der Schluessel? Das ist die eigentliche Ursache der GUI-Meldung.
    AUTH_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 \
        -H "Authorization: Bearer ${VERIFY_KEY}" "${VERIFY_BASE}/stats" 2>/dev/null || true)"
    AUTH_CODE="${AUTH_CODE:-000}"
    case "$AUTH_CODE" in
        200)
            info "Authentifizierung erfolgreich (${VERIFY_BASE}/stats → 200)."
            TMS_REACHABLE=true
            ;;
        401)
            error_noexit "HTTP 401 — der Schluessel wird abgelehnt."
            echo -e "     TMS_API_KEY in .env und tms/.env stimmen nicht ueberein oder der Config-Cache des TMS ist alt."
            echo -e "     Beheben: sudo bash tms-activate.sh   (gleicht beides ab und cached neu)"
            ;;
        500)
            error_noexit "HTTP 500 — das TMS meldet 'API key not configured'."
            echo -e "     In tms/.env fehlt TMS_API_KEY, oder der Config-Cache des TMS ist alt:"
            echo -e "     cd ${TMS_DIR} && php artisan config:cache"
            ;;
        404)
            error_noexit "HTTP 404 auf ${VERIFY_BASE}/stats — die API-Route wird nicht ausgeliefert."
            echo -e "     Gleiche Ursache wie oben: ohne tms/public/.htaccess bzw. mod_rewrite sieht"
            echo -e "     Apache nur Dateien und findet /api/stats nicht."
            ;;
        000)
            error_noexit "Keine Antwort von ${VERIFY_BASE}/stats."
            ;;
        *)
            error_noexit "Unerwartete Antwort von ${VERIFY_BASE}/stats: HTTP ${AUTH_CODE}."
            ;;
    esac
fi

# c) Verarbeitet die PIM-Seite ihre Queue? Der Ingest wird als Job auf die
#    'default'-Queue gelegt (IngestToTmsJob). Ohne Horizon bleibt er dort
#    liegen, die GUI meldet trotzdem "laeuft im Hintergrund".
if command -v supervisorctl > /dev/null 2>&1; then
    if supervisorctl status horizon 2>/dev/null | grep -q "RUNNING"; then
        info "Horizon laeuft — der Ingest-Job der 'default'-Queue wird abgearbeitet."
    elif pgrep -f "artisan queue:work" > /dev/null 2>&1; then
        info "Queue Worker im PIM aktiv (kein Horizon)."
    else
        warn "Im PIM arbeitet niemand die 'default'-Queue ab — der Ingest bleibt liegen."
        warn "Starten mit: sudo supervisorctl start horizon   (oder: php artisan tms:ingest --sync)"
    fi
fi

# d) Sicht des PIM — zeigt zusaetzlich Abdeckung und Sprach-Drift
echo ""
echo -e "${BOLD}php artisan tms:status${NC}"
(cd "$INSTALL_DIR" && php artisan tms:status 2>&1) || warn "tms:status meldet ein Problem (siehe Ausgabe oberhalb)."

echo ""
if [ "$TMS_REACHABLE" = true ]; then
    echo -e "${BOLD}${GREEN}  ╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${GREEN}  ║   Translation Memory ist aktiv und erreichbar            ║${NC}"
    echo -e "${BOLD}${GREEN}  ╚══════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  Begriffe einspeisen:    ${CYAN}php artisan tms:ingest${NC}"
    echo -e "  Fehlendes uebersetzen:  ${CYAN}php artisan tms:translate-missing${NC}"
    echo -e "  Uebersetzungen holen:   ${CYAN}php artisan tms:sync${NC}"
else
    echo -e "${BOLD}${YELLOW}  Translation Memory ist noch nicht erreichbar — siehe Meldungen unten.${NC}"
    echo ""
    if [ "$ONLY_STATUS" = true ]; then
        echo -e "  Es wurde nichts veraendert (--status). Beheben mit:"
        echo -e "    ${CYAN}sudo bash tms-activate.sh${NC}"
        echo ""
    fi
    echo -e "  TMS-Log:     ${CYAN}${TMS_DIR}/storage/logs/${NC}"
    echo -e "  Apache-Log:  ${CYAN}/var/log/apache2/anypim-tms-error.log${NC}"
fi

trap - ERR
print_summary
echo ""
flush_log
