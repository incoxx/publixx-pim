#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║              anyPIM — Healthcheck Script                       ║
# ║              Prueft alle kritischen Services und Abhaengigkeiten    ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Verwendung:
#   bash healthcheck.sh              Alle Checks ausfuehren
#   bash healthcheck.sh --url-only   Nur HTTP-Check gegen API-Endpoint
#   bash healthcheck.sh --json       Ausgabe als JSON (fuer Monitoring)
#   bash healthcheck.sh --quiet      Nur Exit-Code (0=ok, 1=fehler)
#

set -uo pipefail

# ─── Farben ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

# ─── Argumente ───────────────────────────────────────────────────────────────
MODE="full"
for arg in "$@"; do
    case "$arg" in
        --url-only) MODE="url" ;;
        --json)     MODE="json" ;;
        --quiet)    MODE="quiet" ;;
        --help|-h)
            echo "Verwendung: bash healthcheck.sh [--url-only] [--json] [--quiet]"
            exit 0
            ;;
    esac
done

# ─── Installationsverzeichnis bestimmen ──────────────────────────────────────
INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ERRORS=0
WARNINGS=0
RESULTS=""

# ─── Hilfsfunktionen ────────────────────────────────────────────────────────
check_ok()   { RESULTS+="ok|$1|$2\n"; }
check_warn() { RESULTS+="warn|$1|$2\n"; WARNINGS=$((WARNINGS + 1)); }
check_fail() { RESULTS+="fail|$1|$2\n"; ERRORS=$((ERRORS + 1)); }

# ═════════════════════════════════════════════════════════════════════════════
#  URL-Only-Modus: nur HTTP-Check gegen /api/v1/health
# ═════════════════════════════════════════════════════════════════════════════
if [ "$MODE" = "url" ]; then
    if [ ! -f "${INSTALL_DIR}/.env" ]; then
        echo "ERROR: .env not found"
        exit 1
    fi

    APP_URL=$(grep '^APP_URL=' "${INSTALL_DIR}/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    HC_URL="${APP_URL}/api/v1/health"

    RESPONSE=$(curl -s --max-time 10 "$HC_URL" 2>/dev/null)
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$HC_URL" 2>/dev/null || echo "000")

    if [ "$HTTP_CODE" = "200" ]; then
        echo "OK: ${HC_URL} → HTTP ${HTTP_CODE}"
        echo "$RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE"
        exit 0
    else
        echo "FAIL: ${HC_URL} → HTTP ${HTTP_CODE}"
        [ -n "$RESPONSE" ] && echo "$RESPONSE"
        exit 1
    fi
fi

# ═════════════════════════════════════════════════════════════════════════════
#  Vollstaendiger Healthcheck (lokal)
# ═════════════════════════════════════════════════════════════════════════════

# 1. Laravel-Projekt vorhanden?
if [ -f "${INSTALL_DIR}/artisan" ]; then
    check_ok "Laravel" "artisan gefunden in ${INSTALL_DIR}"
else
    check_fail "Laravel" "artisan nicht gefunden in ${INSTALL_DIR}"
fi

# 2. .env vorhanden?
if [ -f "${INSTALL_DIR}/.env" ]; then
    check_ok ".env" "Konfiguration vorhanden"
    APP_URL=$(grep '^APP_URL=' "${INSTALL_DIR}/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
else
    check_fail ".env" "Keine .env-Datei gefunden"
    APP_URL=""
fi

# 3. PHP
if command -v php > /dev/null 2>&1; then
    PHP_VER=$(php -r 'echo PHP_VERSION;')
    check_ok "PHP" "${PHP_VER}"
else
    check_fail "PHP" "nicht installiert"
fi

# 4. Datenbank-Verbindung
if [ -f "${INSTALL_DIR}/artisan" ]; then
    DB_CHECK=$(cd "$INSTALL_DIR" && php artisan tinker --execute="try { DB::select('SELECT 1'); echo 'OK'; } catch(\Throwable \$e) { echo 'FAIL: '.\$e->getMessage(); }" 2>/dev/null)
    if echo "$DB_CHECK" | grep -q "OK"; then
        check_ok "Datenbank" "Verbindung OK"
    else
        check_fail "Datenbank" "Verbindung fehlgeschlagen"
    fi
fi

# 5. Redis / Cache
if command -v redis-cli > /dev/null 2>&1; then
    REDIS_PING=$(redis-cli ping 2>/dev/null)
    if [ "$REDIS_PING" = "PONG" ]; then
        check_ok "Redis" "PONG"
    else
        check_fail "Redis" "Keine Antwort"
    fi
else
    check_warn "Redis" "redis-cli nicht gefunden"
fi

# 6. Apache
if systemctl is-active apache2 > /dev/null 2>&1; then
    check_ok "Apache" "laeuft"
else
    check_fail "Apache" "nicht aktiv"
fi

# 7. Supervisor / Horizon
if command -v supervisorctl > /dev/null 2>&1; then
    HORIZON_STATE=$(supervisorctl status horizon 2>/dev/null | awk '{print $2}')
    if [ "$HORIZON_STATE" = "RUNNING" ]; then
        check_ok "Horizon" "RUNNING"
    elif [ -n "$HORIZON_STATE" ]; then
        check_warn "Horizon" "${HORIZON_STATE}"
    else
        check_warn "Horizon" "nicht konfiguriert"
    fi
else
    check_warn "Supervisor" "nicht installiert"
fi

# 8. Speicherplatz
FREE_KB=$(df -k "$INSTALL_DIR" | tail -1 | awk '{print $4}')
FREE_GB=$(echo "scale=1; ${FREE_KB} / 1048576" | bc 2>/dev/null || echo "?")
USED_PCT=$(df -k "$INSTALL_DIR" | tail -1 | awk '{print $5}')

if [ "${FREE_KB}" -lt 200000 ] 2>/dev/null; then
    check_fail "Speicher" "${FREE_GB} GB frei (${USED_PCT} belegt) — KRITISCH"
elif [ "${FREE_KB}" -lt 1048576 ] 2>/dev/null; then
    check_warn "Speicher" "${FREE_GB} GB frei (${USED_PCT} belegt)"
else
    check_ok "Speicher" "${FREE_GB} GB frei (${USED_PCT} belegt)"
fi

# 9. Storage beschreibbar
if [ -w "${INSTALL_DIR}/storage" ]; then
    check_ok "Storage" "beschreibbar"
else
    check_fail "Storage" "nicht beschreibbar"
fi

# 10. HTTP-Endpoint (wenn APP_URL gesetzt)
if [ -n "$APP_URL" ] && command -v curl > /dev/null 2>&1; then
    HC_URL="${APP_URL}/api/v1/health"
    HC_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$HC_URL" 2>/dev/null || echo "000")

    if [ "$HC_STATUS" = "200" ]; then
        check_ok "HTTP" "${HC_URL} → ${HC_STATUS}"
    elif [ "$HC_STATUS" = "000" ]; then
        check_warn "HTTP" "${HC_URL} → nicht erreichbar"
    else
        check_fail "HTTP" "${HC_URL} → ${HC_STATUS}"
    fi
fi

# ═════════════════════════════════════════════════════════════════════════════
#  Ausgabe
# ═════════════════════════════════════════════════════════════════════════════

# JSON-Modus
if [ "$MODE" = "json" ]; then
    echo "{"
    echo "  \"status\": \"$([ $ERRORS -eq 0 ] && echo 'healthy' || echo 'degraded')\","
    echo "  \"errors\": ${ERRORS},"
    echo "  \"warnings\": ${WARNINGS},"
    echo "  \"checks\": ["
    FIRST=true
    echo -e "$RESULTS" | while IFS='|' read -r status name detail; do
        [ -z "$status" ] && continue
        [ "$FIRST" = true ] && FIRST=false || echo ","
        echo -n "    {\"status\":\"${status}\",\"name\":\"${name}\",\"detail\":\"${detail}\"}"
    done
    echo ""
    echo "  ]"
    echo "}"
    exit $( [ $ERRORS -eq 0 ] && echo 0 || echo 1 )
fi

# Quiet-Modus
if [ "$MODE" = "quiet" ]; then
    exit $( [ $ERRORS -eq 0 ] && echo 0 || echo 1 )
fi

# Normaler Modus
echo ""
echo -e "${BOLD}${BLUE}  anyPIM — Healthcheck${NC}"
echo -e "  ─────────────────────────────"
echo ""

echo -e "$RESULTS" | while IFS='|' read -r status name detail; do
    [ -z "$status" ] && continue
    case "$status" in
        ok)   echo -e "  ${GREEN}[✓]${NC} ${BOLD}${name}${NC}: ${detail}" ;;
        warn) echo -e "  ${YELLOW}[!]${NC} ${BOLD}${name}${NC}: ${detail}" ;;
        fail) echo -e "  ${RED}[✗]${NC} ${BOLD}${name}${NC}: ${detail}" ;;
    esac
done

echo ""
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "  ${BOLD}${GREEN}Alle Checks bestanden.${NC}"
elif [ $ERRORS -eq 0 ]; then
    echo -e "  ${BOLD}${YELLOW}${WARNINGS} Warnung(en), keine Fehler.${NC}"
else
    echo -e "  ${BOLD}${RED}${ERRORS} Fehler, ${WARNINGS} Warnung(en).${NC}"
fi
echo ""

exit $( [ $ERRORS -eq 0 ] && echo 0 || echo 1 )
