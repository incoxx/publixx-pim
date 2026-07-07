#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║              anyPIM — Setup & Installer                       ║
# ║              Ubuntu 24.04 LTS · Apache · PHP 8.4+ · MySQL 8       ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Dieses Script richtet eine vollstaendige anyPIM Instanz ein:
#   - Apache2 mit VirtualHost
#   - PHP 8.4 (oder die von Ubuntu nativ mitgelieferte neuere Version,
#     z.B. PHP 8.5 auf Ubuntu 26.04) + alle benoetigten Extensions
#   - MySQL 8.0 mit Datenbank und Benutzer
#   - Redis (Cache, Queue, Session)
#   - Node.js 20 LTS + Frontend-Build (Vue 3 / Vite)
#   - Composer + Laravel Backend
#   - Supervisor fuer Laravel Horizon
#   - Cron fuer Laravel Scheduler
#   - Demodaten (Seed)
#   - Optional: Let's Encrypt SSL
#
# Schnellstart:
#
#   1. Repository klonen (beliebiges Verzeichnis):
#      git clone https://github.com/incoxx/publixx-pim.git /tmp/publixx-pim
#
#   2. Setup-Script ausfuehren:
#      cd /tmp/publixx-pim
#      sudo bash setup.sh
#
#   3. Das Script fragt interaktiv nach:
#      - Domain / IP-Adresse
#      - Apache-Port (Standard: 80)
#      - SSL ja/nein
#      - MySQL-Zugangsdaten
#      - Installationspfad (Standard: /var/www/publixx-pim)
#
#   Das Script kopiert alle Dateien automatisch in den gewaehlten
#   Installationspfad. Das Clone-Verzeichnis kann danach geloescht werden.
#
#   Wird das Script direkt im Zielverzeichnis ausgefuehrt (z.B. nach
#   git clone ... /var/www/publixx-pim), entfaellt das Kopieren.
#
# Voraussetzung: Frisches Ubuntu 24.04 LTS mit Root-Zugang
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

# ─── Hilfsfunktionen ────────────────────────────────────────────────────────
info()    { echo -e "${GREEN}[✓]${NC} $1"; }
warn()    { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }
step()    { echo -e "\n${BLUE}━━━ $1 ━━━${NC}\n"; }
ask()     { echo -en "${CYAN}$1${NC}"; }

# ─── Banner ──────────────────────────────────────────────────────────────────
clear
echo -e "${BOLD}${BLUE}"
cat << 'BANNER'

  ╔══════════════════════════════════════════════════════════╗
  ║                                                          ║
  ║   ██████╗ ██╗   ██╗██████╗ ██╗     ██╗██╗  ██╗██╗  ██╗ ║
  ║   ██╔══██╗██║   ██║██╔══██╗██║     ██║╚██╗██╔╝╚██╗██╔╝ ║
  ║   ██████╔╝██║   ██║██████╔╝██║     ██║ ╚███╔╝  ╚███╔╝  ║
  ║   ██╔═══╝ ██║   ██║██╔══██╗██║     ██║ ██╔██╗  ██╔██╗  ║
  ║   ██║     ╚██████╔╝██████╔╝███████╗██║██╔╝ ██╗██╔╝ ██╗ ║
  ║   ╚═╝      ╚═════╝ ╚═════╝ ╚══════╝╚═╝╚═╝  ╚═╝╚═╝  ╚═╝ ║
  ║                                                          ║
  ║   P I M  —  Product Information Management               ║
  ║   Setup & Installer fuer Ubuntu 24.04 LTS                ║
  ║                                                          ║
  ╚══════════════════════════════════════════════════════════╝

BANNER
echo -e "${NC}"

# ─── Root-Check ──────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    error "Dieses Script muss als root ausgefuehrt werden: sudo bash setup.sh"
fi

# ─── Ubuntu-Check ────────────────────────────────────────────────────────────
if ! grep -q "Ubuntu" /etc/os-release 2>/dev/null; then
    warn "Dieses Script ist fuer Ubuntu 24.04 LTS optimiert."
    ask "Trotzdem fortfahren? [j/N]: "
    read -r CONTINUE
    if [[ ! "$CONTINUE" =~ ^[jJyY]$ ]]; then
        echo "Abgebrochen."
        exit 0
    fi
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

# ─── Gespeicherte Konfiguration aus vorherigem Lauf ────────────────────────────
# Falls ein spaeterer Schritt (z.B. apt/Netzwerk) fehlschlaegt, muessen beim
# erneuten Ausfuehren nicht alle Fragen erneut beantwortet werden. Datei liegt
# nur fuer root lesbar unter /root, da sie Passwoerter im Klartext enthaelt.
CONFIG_FILE="/root/.anypim-setup-answers.env"
RESUMED_FROM_CONFIG=false

if [ -f "$CONFIG_FILE" ]; then
    echo ""
    warn "Gespeicherte Konfiguration von einem vorherigen Lauf gefunden:"
    echo -e "  ${CYAN}${CONFIG_FILE}${NC} ($(date -r "$CONFIG_FILE" '+%d.%m.%Y %H:%M' 2>/dev/null))"
    ask "Diese Konfiguration wiederverwenden statt alles neu einzugeben? [J/n]: "
    read -r REUSE_CONFIG
    # "^[nN]" statt "^[nN]$": erkennt auch ausgeschriebene Antworten wie
    # "nein"/"no" als Ablehnung, nicht nur den exakten Einzelbuchstaben.
    if [[ ! "$REUSE_CONFIG" =~ ^[nN] ]]; then
        # shellcheck disable=SC1090
        if source "$CONFIG_FILE" 2>/dev/null; then
            RESUMED_FROM_CONFIG=true
            info "Konfiguration geladen — Installation wird ohne erneute Abfrage fortgesetzt."
        else
            warn "Gespeicherte Konfiguration ist beschaedigt (vermutlich ein waehrend des Speicherns abgebrochener Lauf) — wird verworfen, Fragen werden erneut gestellt."
            rm -f "$CONFIG_FILE"
        fi
    else
        rm -f "$CONFIG_FILE"
    fi
fi

# ═════════════════════════════════════════════════════════════════════════════
#  INTERAKTIVE KONFIGURATION
# ═════════════════════════════════════════════════════════════════════════════
if [ "$RESUMED_FROM_CONFIG" = false ]; then

step "Konfiguration"

echo -e "${BOLD}Bitte gib die folgenden Informationen ein:${NC}\n"

# --- Domain / IP ---
ask "Domain oder IP-Adresse (z.B. pim.example.com oder 192.168.1.100): "
read -r SERVER_DOMAIN
if [ -z "$SERVER_DOMAIN" ]; then
    error "Domain/IP darf nicht leer sein."
fi

# Pruefen ob es eine IP oder Domain ist
IS_IP=false
if [[ "$SERVER_DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    IS_IP=true
fi

# --- Port ---
ask "Apache-Port [80]: "
read -r APP_PORT
APP_PORT=${APP_PORT:-80}

# Validieren: nur Zahlen, 1-65535
if ! [[ "$APP_PORT" =~ ^[0-9]+$ ]] || [ "$APP_PORT" -lt 1 ] || [ "$APP_PORT" -gt 65535 ]; then
    error "Ungueltiger Port: ${APP_PORT}. Erlaubt: 1-65535."
fi

# --- Protokoll ---
USE_SSL=false
if [ "$IS_IP" = false ]; then
    ask "SSL mit Let's Encrypt einrichten? [j/N]: "
    read -r SSL_ANSWER
    if [[ "$SSL_ANSWER" =~ ^[jJyY]$ ]]; then
        USE_SSL=true
        ask "E-Mail fuer Let's Encrypt (fuer Zertifikats-Benachrichtigungen): "
        read -r SSL_EMAIL
        if [ -z "$SSL_EMAIL" ]; then
            error "E-Mail darf nicht leer sein wenn SSL aktiviert ist."
        fi
    fi
fi

if [ "$USE_SSL" = true ]; then
    APP_PROTOCOL="https"
    SSL_PORT=$((APP_PORT + 363))   # z.B. 80->443, 8080->8443
    if [ "$APP_PORT" -eq 80 ]; then
        SSL_PORT=443
    fi
else
    APP_PROTOCOL="http"
fi

# --- Web-Pfad (Unterverzeichnis) ---
echo ""
echo -e "${BOLD}Web-Pfad:${NC}"
echo -e "  Soll PIM im Root laufen (z.B. https://pim.example.com/)?"
echo -e "  Oder in einem Unterverzeichnis (z.B. https://example.com/pim)?"
echo ""
ask "Web-Pfad (z.B. /web oder /pim, leer fuer Root): "
read -r WEB_PATH

# Normalisieren: mit / beginnen, ohne / enden, leer = Root
if [ -n "$WEB_PATH" ]; then
    WEB_PATH="${WEB_PATH#/}"    # fuehrendes / entfernen
    WEB_PATH="${WEB_PATH%/}"    # abschliessendes / entfernen
    WEB_PATH="/${WEB_PATH}"    # mit / beginnen
    info "PIM wird unter '${WEB_PATH}' installiert."
else
    info "PIM wird im Root installiert."
fi

# URL zusammenbauen (Port nur anhaengen wenn nicht Standard)
if [ "$APP_PROTOCOL" = "https" ]; then
    DEFAULT_PORT=443
    EFFECTIVE_PORT=$SSL_PORT
else
    DEFAULT_PORT=80
    EFFECTIVE_PORT=$APP_PORT
fi

if [ "$EFFECTIVE_PORT" -eq "$DEFAULT_PORT" ]; then
    APP_URL="${APP_PROTOCOL}://${SERVER_DOMAIN}${WEB_PATH}"
    SANCTUM_DOMAIN="${SERVER_DOMAIN}"
else
    APP_URL="${APP_PROTOCOL}://${SERVER_DOMAIN}:${EFFECTIVE_PORT}${WEB_PATH}"
    SANCTUM_DOMAIN="${SERVER_DOMAIN}:${EFFECTIVE_PORT}"
fi

echo ""
echo -e "${BOLD}MySQL-Konfiguration:${NC}\n"

# --- MySQL ---
ask "MySQL Datenbankname [publixx_pim]: "
read -r DB_NAME
DB_NAME=${DB_NAME:-publixx_pim}

ask "MySQL Benutzername [pim]: "
read -r DB_USER
DB_USER=${DB_USER:-pim}

while true; do
    ask "MySQL Passwort: "
    read -rs DB_PASS
    echo ""
    if [ -z "$DB_PASS" ]; then
        warn "Passwort darf nicht leer sein."
        continue
    fi
    ask "MySQL Passwort bestaetigen: "
    read -rs DB_PASS_CONFIRM
    echo ""
    if [ "$DB_PASS" != "$DB_PASS_CONFIRM" ]; then
        warn "Passwoerter stimmen nicht ueberein. Bitte erneut eingeben."
        continue
    fi
    break
done

# --- Installationspfad ---
echo ""
ask "Installationspfad [/var/www/publixx-pim]: "
read -r INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-/var/www/publixx-pim}

# --- Zusaetzlicher Admin-Benutzer ---
echo ""
echo -e "${BOLD}Admin-Benutzer:${NC}"
echo -e "  Es werden automatisch Standard-Admins angelegt:"
echo -e "    admin@publixx.com / password"
echo -e "    admin@example.com / password"
echo ""
CUSTOM_ADMIN=false
ask "Zusaetzlichen Admin-Benutzer anlegen? [j/N]: "
read -r ADMIN_ANSWER
if [[ "$ADMIN_ANSWER" =~ ^[jJyY]$ ]]; then
    CUSTOM_ADMIN=true

    ask "Admin E-Mail-Adresse: "
    read -r ADMIN_EMAIL
    if [ -z "$ADMIN_EMAIL" ]; then
        warn "E-Mail darf nicht leer sein — ueberspringe zusaetzlichen Admin."
        CUSTOM_ADMIN=false
    fi

    if [ "$CUSTOM_ADMIN" = true ]; then
        ask "Admin Name [Admin]: "
        read -r ADMIN_NAME
        ADMIN_NAME=${ADMIN_NAME:-Admin}

        while true; do
            ask "Admin Passwort: "
            read -rs ADMIN_PASS
            echo ""
            if [ -z "$ADMIN_PASS" ]; then
                warn "Passwort darf nicht leer sein."
                continue
            fi
            ask "Admin Passwort bestaetigen: "
            read -rs ADMIN_PASS_CONFIRM
            echo ""
            if [ "$ADMIN_PASS" != "$ADMIN_PASS_CONFIRM" ]; then
                warn "Passwoerter stimmen nicht ueberein. Bitte erneut eingeben."
                continue
            fi
            break
        done

        info "Admin '${ADMIN_EMAIL}' wird nach der Installation angelegt."
    fi
fi

fi # Ende: RESUMED_FROM_CONFIG = false (interaktive Abfrage)

# --- Zusammenfassung ---
echo ""
echo -e "${BOLD}${BLUE}═══ Zusammenfassung ═══${NC}"
echo -e "  Server:       ${BOLD}${SERVER_DOMAIN}${NC}"
echo -e "  Port:         ${BOLD}${APP_PORT}$([ "$USE_SSL" = true ] && echo " (SSL: ${SSL_PORT})" || echo "")${NC}"
echo -e "  URL:          ${BOLD}${APP_URL}${NC}"
echo -e "  Web-Pfad:     ${BOLD}${WEB_PATH:-/ (Root)}${NC}"
echo -e "  SSL:          ${BOLD}$([ "$USE_SSL" = true ] && echo 'Ja (Let'\''s Encrypt)' || echo 'Nein')${NC}"
echo -e "  MySQL DB:     ${BOLD}${DB_NAME}${NC}"
echo -e "  MySQL User:   ${BOLD}${DB_USER}${NC}"
echo -e "  MySQL Pass:   ${BOLD}********${NC}"
echo -e "  Pfad:         ${BOLD}${INSTALL_DIR}${NC}"
if [ "$CUSTOM_ADMIN" = true ]; then
    echo -e "  Extra-Admin:  ${BOLD}${ADMIN_EMAIL}${NC}"
fi
echo ""
ask "Alles korrekt? Installation starten? [j/N]: "
read -r START_INSTALL
if [[ ! "$START_INSTALL" =~ ^[jJyY]$ ]]; then
    echo "Abgebrochen."
    exit 0
fi

# --- Konfiguration fuer Resume nach Abbruch speichern ---
umask 077
{
    declare -p SERVER_DOMAIN IS_IP APP_PORT USE_SSL APP_PROTOCOL WEB_PATH APP_URL SANCTUM_DOMAIN
    declare -p DB_NAME DB_USER DB_PASS INSTALL_DIR CUSTOM_ADMIN
    if [ "$USE_SSL" = true ]; then
        declare -p SSL_EMAIL SSL_PORT
    fi
    if [ "$CUSTOM_ADMIN" = true ]; then
        declare -p ADMIN_EMAIL ADMIN_NAME ADMIN_PASS
    fi
} > "$CONFIG_FILE"
chmod 600 "$CONFIG_FILE"
umask 022
info "Konfiguration gespeichert (${CONFIG_FILE}) — bei Abbruch muss diese Basis-Konfiguration nicht erneut eingegeben werden."
# Hinweis: einzelne spaetere Abfragen, die vom aktuellen System-Zustand
# abhaengen (z.B. Datenbank existiert bereits, Apache-Alias existiert
# bereits), werden davon bewusst nicht abgedeckt und laufen bei jedem
# (Wieder-)Start live, da sich dieser Zustand zwischen Laeufen aendern kann.

INSTALL_START=$(date +%s)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Muss vor jedem apt-get/add-apt-repository-Aufruf gesetzt sein (auch vor dem
# PHP-Repository-Preflight unten), sonst koennen debconf-Prompts vereinzelter
# Pakete den unbeaufsichtigten Lauf zum Haengen bringen.
export DEBIAN_FRONTEND=noninteractive

# ═════════════════════════════════════════════════════════════════════════════
#  PHP-VERSION ERMITTELN & REPOSITORY VORBEREITEN (muss vor "System
#  aktualisieren" laufen)
# ═════════════════════════════════════════════════════════════════════════════
# Die ondrej/php-PPA auf Launchpad (und teils auch packages.sury.org) bauen
# Pakete nicht immer sofort fuer brandneue Ubuntu-Versionen (z.B. Ubuntu
# 26.04 "Resolute"): entweder fehlt das Release fuer den Codename, oder das
# Release existiert bereits, aber die eigentlichen Pakete wurden fuer diese
# Suite noch nicht hochgeladen. Ein bloss erfolgreiches "apt-get update"
# reicht daher NICHT als Beweis, dass PHP 8.4 wirklich installierbar ist —
# deshalb wird hier zusaetzlich immer der tatsaechliche apt-Kandidat geprueft.
#
# Bevorzugt wird eine von Ubuntu selbst nativ mitgelieferte PHP-Version, wenn
# sie >= 8.4 ist (z.B. Ubuntu 26.04 "Resolute" bringt PHP 8.5 direkt aus dem
# eigenen Archiv mit). Das umgeht Drittanbieter-Repos komplett und damit
# strukturell auch das "Drittanbieter-Repo hinkt brandneuer Ubuntu-Version
# hinterher"-Problem, statt nur den einen konkret bekannten Fehlerfall zu
# patchen. Nur wenn nativ nichts >= 8.4 verfuegbar ist (z.B. Ubuntu 24.04,
# das nativ nur PHP 8.3 mitbringt), wird gezielt PHP 8.4 ueber ondrej/php
# bzw. packages.sury.org installiert.
step "PHP-Version ermitteln & Repository vorbereiten"

UBUNTU_CODENAME="$(. /etc/os-release && echo "${VERSION_CODENAME:-}")"
PHP_VERSION="8.4"

php_has_candidate() {
    apt-cache policy "$1" 2>/dev/null | grep -q "Candidate: [^(]"
}

apt-get update -qq --allow-releaseinfo-change 2>/tmp/anypim-apt-update.log || true

NATIVE_PHP_VERSION="$(apt-cache policy php 2>/dev/null | awk '/Candidate:/ {print $2}' | grep -oP '\d+\.\d+' | head -1)"
if [ -n "$NATIVE_PHP_VERSION" ] && [ "$(printf '%s\n' "8.4" "$NATIVE_PHP_VERSION" | sort -V | tail -1)" = "$NATIVE_PHP_VERSION" ]; then
    PHP_VERSION="$NATIVE_PHP_VERSION"
    info "Ubuntu liefert PHP ${PHP_VERSION} bereits nativ (>= 8.4) — ueberspringe ondrej/php PPA und packages.sury.org."
elif php_has_candidate "php8.4"; then
    info "PHP 8.4 ist bereits ueber ein konfiguriertes Repository installierbar."
else
    if ! grep -rq "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
        info "Fuege PHP-Repository ondrej/php (PPA) hinzu..."
        add-apt-repository ppa:ondrej/php -y 2>/tmp/anypim-apt-update.log || true
        apt-get update -qq --allow-releaseinfo-change 2>>/tmp/anypim-apt-update.log || true
    fi

    if ! php_has_candidate "php8.4"; then
        cat /tmp/anypim-apt-update.log >&2 2>/dev/null || true
        warn "PHP 8.4 ist ueber ondrej/php fuer '${UBUNTU_CODENAME}' (noch) nicht installierbar — vermutlich eine sehr neue Ubuntu-Version, fuer die das Repo noch keine Pakete hochgeladen hat."
        info "Wechsle auf packages.sury.org/php (von ondrej selbst als kanonisches Repo empfohlen)..."

        add-apt-repository --remove ppa:ondrej/php -y > /dev/null 2>&1 || true
        rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list /etc/apt/sources.list.d/ondrej-ubuntu-php-*.sources

        if ! grep -rq "packages\.sury\.org" /etc/apt/sources.list.d/ 2>/dev/null; then
            apt-get install -y -qq apt-transport-https ca-certificates gnupg

            # Key kann ASCII-armored oder bereits binaer sein — je nach Format
            # entdearmoren oder direkt uebernehmen. Explizit chmod 644, da gpgv
            # (von apt sandboxed als User "_apt" ausgefuehrt) den Keyring lesen
            # koennen muss — unabhaengig vom aktuell gesetzten umask.
            curl -fsSL https://packages.sury.org/php/apt.gpg -o /tmp/anypim-sury-php.key
            if grep -q "BEGIN PGP PUBLIC KEY BLOCK" /tmp/anypim-sury-php.key; then
                gpg --dearmor -o /usr/share/keyrings/deb-sury-php.gpg /tmp/anypim-sury-php.key
            else
                cp /tmp/anypim-sury-php.key /usr/share/keyrings/deb-sury-php.gpg
            fi
            rm -f /tmp/anypim-sury-php.key
            chmod 644 /usr/share/keyrings/deb-sury-php.gpg

            echo "deb [signed-by=/usr/share/keyrings/deb-sury-php.gpg] https://packages.sury.org/php/ ${UBUNTU_CODENAME} main" \
                > /etc/apt/sources.list.d/php-sury.list
        fi

        apt-get update -qq --allow-releaseinfo-change || true

        if ! php_has_candidate "php8.4"; then
            error "PHP 8.4 ist weder nativ von Ubuntu, noch ueber ondrej/php PPA, noch ueber packages.sury.org fuer '${UBUNTU_CODENAME}' installierbar. Bitte https://packages.sury.org/php/ manuell pruefen oder PHP von Hand installieren."
        fi
        info "PHP-Repository packages.sury.org/php fuer '${UBUNTU_CODENAME}' eingerichtet."
    fi
fi
rm -f /tmp/anypim-apt-update.log

# ═════════════════════════════════════════════════════════════════════════════
#  1. SYSTEM-PAKETE AKTUALISIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "1/10 — System aktualisieren"

# Kein erneutes apt-get update noetig: der Preflight oben hat den Paket-Cache
# bereits ueber einen erfolgreichen Aufruf verifiziert/aktualisiert (sonst
# waere das Script per set -e/error() vorher schon abgebrochen).
apt-get upgrade -y -qq
info "System aktualisiert."

# ═════════════════════════════════════════════════════════════════════════════
#  2. PHP INSTALLIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "2/10 — PHP ${PHP_VERSION} installieren"

# PHP-Version und -Repository wurden bereits vor Schritt 1 ermittelt/
# eingerichtet (siehe "PHP-Version ermitteln & Repository vorbereiten" weiter
# oben) — $PHP_VERSION ist entweder die nativ von Ubuntu mitgelieferte
# Version (>= 8.4) oder gezielt 8.4 ueber ondrej/php bzw. packages.sury.org.
PHP_PACKAGES=(
    "php${PHP_VERSION}"
    "php${PHP_VERSION}-cli"
    "php${PHP_VERSION}-common"
    "php${PHP_VERSION}-mysql"
    "php${PHP_VERSION}-redis"
    "php${PHP_VERSION}-mbstring"
    "php${PHP_VERSION}-xml"
    "php${PHP_VERSION}-zip"
    "php${PHP_VERSION}-gd"
    "php${PHP_VERSION}-bcmath"
    "php${PHP_VERSION}-curl"
    "php${PHP_VERSION}-intl"
    "php${PHP_VERSION}-readline"
    "libapache2-mod-php${PHP_VERSION}"
)

# php-opcache ist je nach Paketquelle kein eigenes Paket mehr (z.B. Ubuntus
# eigenes Archiv fuer PHP 8.5): OPcache ist seit PHP 5.5 fest im Core
# enthalten und wird dort direkt ueber php-common vorkonfiguriert. Nur
# hinzufuegen, wenn die aktive Quelle es tatsaechlich als separates Paket
# anbietet (z.B. ondrej/php-Pakete tun das weiterhin).
if php_has_candidate "php${PHP_VERSION}-opcache"; then
    PHP_PACKAGES+=("php${PHP_VERSION}-opcache")
fi

apt-get install -y -qq "${PHP_PACKAGES[@]}"

info "PHP ${PHP_VERSION} installiert: $(php -v | head -1)"

# PHP Konfiguration optimieren
PHP_INI="/etc/php/${PHP_VERSION}/apache2/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$PHP_INI"
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 256M/' "$PHP_INI"
    sed -i 's/^post_max_size = .*/post_max_size = 260M/' "$PHP_INI"
    sed -i 's/^max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
    sed -i 's/^;date.timezone =.*/date.timezone = Europe\/Berlin/' "$PHP_INI"
    info "PHP-Konfiguration optimiert."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  3. APACHE INSTALLIEREN & KONFIGURIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "3/10 — Apache installieren"

apt-get install -y -qq apache2

# Module aktivieren
a2enmod rewrite headers ssl "php${PHP_VERSION}" > /dev/null 2>&1

# Default-Site nur deaktivieren wenn PIM im Root laeuft
if [ -z "$WEB_PATH" ]; then
    a2dissite 000-default.conf > /dev/null 2>&1 || true
fi

info "Apache installiert und Module aktiviert."

# ═════════════════════════════════════════════════════════════════════════════
#  4. MYSQL INSTALLIEREN & EINRICHTEN
# ═════════════════════════════════════════════════════════════════════════════
step "4/10 — MySQL installieren & einrichten"

apt-get install -y -qq mysql-server

# MySQL starten falls noch nicht aktiv
systemctl enable mysql
systemctl start mysql

# Pruefen ob Datenbank bereits existiert
DB_EXISTS=false
DB_RESET=false
if mysql -u root -e "USE \`${DB_NAME}\`" 2>/dev/null; then
    DB_EXISTS=true
    TABLE_COUNT=$(mysql -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';")
    warn "Datenbank '${DB_NAME}' existiert bereits (${TABLE_COUNT} Tabellen)."
    echo ""
    ask "Was moechtest du tun?\n"
    echo -e "  ${BOLD}1${NC}) Zuruecksetzen — Datenbank loeschen und neu anlegen (alle Daten gehen verloren!)"
    echo -e "  ${BOLD}2${NC}) Beibehalten  — Nur fehlende Migrationen ausfuehren, keine Demodaten"
    echo ""
    ask "Auswahl [1/2]: "
    read -r DB_CHOICE
    case "$DB_CHOICE" in
        1)
            DB_RESET=true
            warn "Datenbank wird zurueckgesetzt..."
            mysql -u root -e "DROP DATABASE \`${DB_NAME}\`;"
            info "Datenbank '${DB_NAME}' geloescht."
            ;;
        2|"")
            # Leere Eingabe (z.B. kein Terminal bei einem non-interaktiven
            # Wiederholungslauf) faellt auf die sichere, nicht-destruktive
            # Option zurueck, statt abzubrechen.
            [ -z "$DB_CHOICE" ] && warn "Keine Eingabe erhalten — behalte bestehende Datenbank bei (sicherer Default)."
            info "Datenbank wird beibehalten."
            ;;
        *)
            error "Ungueltige Auswahl. Abgebrochen."
            ;;
    esac
fi

# Datenbank und Benutzer anlegen
# ALTER USER statt CREATE: falls der User schon existiert,
# wird das Passwort auf den aktuell eingegebenen Wert gesetzt.
mysql -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}_testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}_testing\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

# Verbindung testen bevor wir weitermachen
if ! mysql -u "${DB_USER}" -p"${DB_PASS}" -e "USE \`${DB_NAME}\`" 2>/dev/null; then
    error "MySQL-Verbindung fehlgeschlagen! Benutzer '${DB_USER}' kann nicht auf '${DB_NAME}' zugreifen."
fi
info "MySQL-Verbindung getestet — OK."

if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    info "MySQL-Benutzer '${DB_USER}' geprueft, Datenbank '${DB_NAME}' beibehalten."
else
    info "MySQL-Datenbank '${DB_NAME}' und Benutzer '${DB_USER}' angelegt."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  5. REDIS INSTALLIEREN & KONFIGURIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "5/10 — Redis installieren"

apt-get install -y -qq redis-server

# Redis-Konfiguration optimieren
REDIS_CONF="/etc/redis/redis.conf"
if [ -f "$REDIS_CONF" ]; then
    sed -i 's/^# maxmemory <bytes>.*/maxmemory 512mb/' "$REDIS_CONF"
    sed -i 's/^maxmemory .*/maxmemory 512mb/' "$REDIS_CONF"
    # maxmemory-policy setzen oder hinzufuegen
    if grep -q "^maxmemory-policy" "$REDIS_CONF"; then
        sed -i 's/^maxmemory-policy .*/maxmemory-policy allkeys-lru/' "$REDIS_CONF"
    elif grep -q "^# maxmemory-policy" "$REDIS_CONF"; then
        sed -i 's/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' "$REDIS_CONF"
    else
        echo "maxmemory-policy allkeys-lru" >> "$REDIS_CONF"
    fi
    # maxmemory einfuegen falls noch nicht vorhanden
    if ! grep -q "^maxmemory " "$REDIS_CONF"; then
        echo "maxmemory 512mb" >> "$REDIS_CONF"
    fi
fi

systemctl enable redis-server
systemctl restart redis-server

info "Redis installiert und konfiguriert."

# ═════════════════════════════════════════════════════════════════════════════
#  6. NODE.JS 22 LTS INSTALLIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "6/10 — Node.js 22 LTS installieren"

# Node 22+ erforderlich (vue-i18n v11 / @intlify). Aeltere Versionen (z. B. 20)
# werden ueber NodeSource auf 22 angehoben.
if ! command -v node &> /dev/null || [[ "$(node -v | cut -d. -f1 | tr -d 'v')" -lt 22 ]]; then
    # NodeSource-Repository ist wie die PHP-PPA an den Ubuntu-Codename gebunden
    # und kann bei brandneuen Releases (z.B. Ubuntu 26.04 "Resolute") noch
    # keine Pakete anbieten. Fallback: offizielles Binary-Tarball von nodejs.org
    # (codename-unabhaengig).
    info "Fuege NodeSource-Repository hinzu..."
    NODESOURCE_OK=true
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - > /tmp/anypim-nodesource.log 2>&1 || NODESOURCE_OK=false

    if [ "$NODESOURCE_OK" = true ]; then
        apt-get install -y -qq nodejs 2>>/tmp/anypim-nodesource.log || NODESOURCE_OK=false
    fi

    if [ "$NODESOURCE_OK" = false ]; then
        cat /tmp/anypim-nodesource.log >&2 2>/dev/null || true
        warn "NodeSource-Repository steht fuer diese Ubuntu-Version (noch) nicht zur Verfuegung."
        info "Installiere Node.js 22 LTS stattdessen als offizielles Binary von nodejs.org..."

        rm -f /etc/apt/sources.list.d/nodesource.list /etc/apt/sources.list.d/nodesource.sources

        case "$(dpkg --print-architecture)" in
            amd64) NODE_ARCH="x64" ;;
            arm64) NODE_ARCH="arm64" ;;
            *) error "Nicht unterstuetzte Architektur fuer Node.js-Binary-Fallback: $(dpkg --print-architecture)" ;;
        esac

        # In eine if-Bedingung eingebettet: schlaegt curl/grep fehl (Netzwerk,
        # SHASUMS-Format geaendert), wuerde eine blanke Zuweisung sonst per
        # set -e/pipefail sofort abbrechen, statt die Fehlermeldung unten zu erreichen.
        NODE_VERSION=""
        if NODE_VERSION=$(curl -fsSL https://nodejs.org/dist/latest-v22.x/SHASUMS256.txt \
            | grep -oP "node-v22\.\d+\.\d+(?=-linux-${NODE_ARCH}\.tar\.xz)" | sort -V | tail -1); then
            NODE_VERSION=${NODE_VERSION#node-v}
        fi

        if [ -z "$NODE_VERSION" ]; then
            error "Node.js-Version konnte nicht von nodejs.org ermittelt werden. Bitte manuell installieren."
        fi

        curl -fsSL "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" \
            -o /tmp/anypim-node.tar.xz
        tar -xJf /tmp/anypim-node.tar.xz -C /usr/local --strip-components=1
        rm -f /tmp/anypim-node.tar.xz
        info "Node.js ${NODE_VERSION} nach /usr/local installiert."
    fi
    rm -f /tmp/anypim-nodesource.log
fi

# npm fehlt, wenn Node ueber Ubuntus eigenes natives nodejs-Paket kommt
# (z.B. Ubuntu 26.04 "Resolute" liefert nodejs>=22 direkt aus dem eigenen
# Archiv -> obiger Versionscheck ist schon erfuellt, der Block oben laeuft
# gar nicht erst). Anders als bei NodeSource oder dem nodejs.org-Tarball
# (beide buendeln npm) ist npm bei Ubuntus eigener Paketierung ein
# separates Paket.
if ! command -v npm &> /dev/null; then
    info "npm fehlt (natives nodejs-Paket bringt es nicht mit) — installiere npm..."
    apt-get install -y -qq npm
fi

info "Node.js installiert: $(node -v)"
info "npm installiert: $(npm -v)"

# ═════════════════════════════════════════════════════════════════════════════
#  7. COMPOSER INSTALLIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "7/10 — Composer installieren"

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_NO_INTERACTION=1
export COMPOSER_DISABLE_XDEBUG_WARN=1

if ! command -v composer &> /dev/null; then
    info "Installiere Composer via apt..."
    apt-get install -y -qq composer 2>&1
fi

if command -v composer &> /dev/null; then
    info "Composer installiert: $(which composer)"
else
    error "Composer konnte nicht installiert werden."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  8. ANWENDUNG EINRICHTEN
# ═════════════════════════════════════════════════════════════════════════════
step "8/10 — anyPIM einrichten"

# Supervisor installieren (fuer Horizon)
apt-get install -y -qq supervisor

# Weitere Pakete
apt-get install -y -qq git unzip curl poppler-utils

# Typesense installieren (Volltextsuche fuer PDF-Seiten)
if ! command -v typesense-server &> /dev/null; then
    info "Installiere Typesense..."
    TYPESENSE_VERSION="27.1"
    curl -sO "https://dl.typesense.org/releases/${TYPESENSE_VERSION}/typesense-server-${TYPESENSE_VERSION}-amd64.deb"
    dpkg -i "typesense-server-${TYPESENSE_VERSION}-amd64.deb"
    rm -f "typesense-server-${TYPESENSE_VERSION}-amd64.deb"
    info "Typesense ${TYPESENSE_VERSION} installiert."
else
    info "Typesense bereits installiert: $(typesense-server --version 2>/dev/null || echo 'vorhanden')"
fi

# Meilisearch installieren (Semantische Schnellsuche, Hybrid Search)
# Benoetigt >= 1.10 fuer Embedder/Hybrid Search.
if ! command -v meilisearch &> /dev/null; then
    info "Installiere Meilisearch..."
    MEILI_VERSION="1.15.2"
    MEILI_ARCH="amd64"
    curl -sL "https://github.com/meilisearch/meilisearch/releases/download/v${MEILI_VERSION}/meilisearch-linux-${MEILI_ARCH}" \
        -o /usr/local/bin/meilisearch
    chmod +x /usr/local/bin/meilisearch
    info "Meilisearch ${MEILI_VERSION} installiert: $(meilisearch --version 2>/dev/null | head -1 || echo 'vorhanden')"
else
    info "Meilisearch bereits installiert: $(meilisearch --version 2>/dev/null | head -1 || echo 'vorhanden')"
fi

# Meilisearch als systemd-Dienst einrichten
MEILI_SERVICE="/etc/systemd/system/meilisearch.service"
MEILI_DATA_DIR="/var/lib/meilisearch"
MEILI_API_KEY=""
if [ ! -f "$MEILI_SERVICE" ]; then
    info "Konfiguriere Meilisearch-Dienst..."
    mkdir -p "$MEILI_DATA_DIR"
    chown -R www-data:www-data "$MEILI_DATA_DIR"
    # API-Key generieren
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
RestartSec=5

[Install]
WantedBy=multi-user.target
MEILISERVICE
    systemctl daemon-reload
    systemctl enable meilisearch
    systemctl start meilisearch
    info "Meilisearch-Dienst gestartet (Port 7700, API-Key generiert)."
else
    info "Meilisearch-Dienst bereits konfiguriert."
    # API-Key aus bestehendem Service auslesen
    MEILI_API_KEY=$(grep -oP '(?<=--master-key )\S+' "$MEILI_SERVICE" || true)
fi

# Ollama installieren (lokale Embeddings, Standard-Embedder)
# Kann nach der Installation uebersprungen werden wenn ein externer
# Embedding-Dienst (OpenAI o.ae.) verwendet wird.
INSTALL_OLLAMA=false
if ! command -v ollama &> /dev/null; then
    ask "Ollama (lokale Embeddings, nomic-embed-text) installieren? [j/N]: "
    read -r OLLAMA_ANSWER
    if [[ "$OLLAMA_ANSWER" =~ ^[jJyY]$ ]]; then
        INSTALL_OLLAMA=true
    else
        warn "Ollama nicht installiert. Embedder-Quelle in .env aendern: MEILISEARCH_EMBEDDER_SOURCE=openAi|rest"
    fi
else
    info "Ollama bereits installiert: $(ollama --version 2>/dev/null || echo 'vorhanden')"
    INSTALL_OLLAMA=false
fi

if [ "$INSTALL_OLLAMA" = true ]; then
    info "Installiere Ollama..."
    curl -fsSL https://ollama.ai/install.sh | sh 2>&1 | tail -3
    info "Ollama installiert."
    info "Lade Embedding-Modell nomic-embed-text (einmalig, ~270 MB)..."
    ollama pull nomic-embed-text 2>&1 | tail -3 && info "Modell geladen." \
        || warn "Modell-Download fehlgeschlagen — spaeter nachholen mit: ollama pull nomic-embed-text"
fi

# Typesense API-Key auslesen (wird vom DEB-Paket generiert)
TYPESENSE_API_KEY=""
if [ -f /etc/typesense/typesense-server.ini ]; then
    TYPESENSE_API_KEY=$(grep '^api-key' /etc/typesense/typesense-server.ini | cut -d'=' -f2- | tr -d ' ')
fi

# Installationsverzeichnis vorbereiten
if [ -d "$INSTALL_DIR" ] && [ "$INSTALL_DIR" != "$SCRIPT_DIR" ]; then
    warn "Verzeichnis ${INSTALL_DIR} existiert bereits."
    ask "Vorhandenes Verzeichnis loeschen und neu installieren? [j/N]: "
    read -r DELETE_EXISTING
    if [[ "$DELETE_EXISTING" =~ ^[jJyY]$ ]]; then
        rm -rf "$INSTALL_DIR"
    else
        error "Abgebrochen. Bitte waehle einen anderen Installationspfad."
    fi
fi

# Dateien kopieren oder am Ort belassen
if [ "$SCRIPT_DIR" = "$INSTALL_DIR" ]; then
    info "Script wird im Zielverzeichnis ausgefuehrt — ueberspringe Kopiervorgang."
else
    mkdir -p "$INSTALL_DIR"
    info "Kopiere Projektdateien nach ${INSTALL_DIR}..."
    # rsync bevorzugt, da es Symlinks und Rechte besser handhabt
    if command -v rsync &> /dev/null; then
        rsync -a --exclude='vendor' --exclude='node_modules' --exclude='.env' \
            "${SCRIPT_DIR}/" "${INSTALL_DIR}/"
    else
        apt-get install -y -qq rsync
        rsync -a --exclude='vendor' --exclude='node_modules' --exclude='.env' \
            "${SCRIPT_DIR}/" "${INSTALL_DIR}/"
    fi
    info "Projektdateien kopiert."
fi

# --- .env erstellen ---
info "Erstelle .env Konfiguration..."

if [ "$USE_SSL" = true ]; then
    SESSION_SECURE="true"
else
    SESSION_SECURE="false"
fi

cat > "${INSTALL_DIR}/.env" <<ENVFILE
APP_NAME="anyPIM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL}

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# ─── Database ─────────────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

# ─── Redis ────────────────────────────────────────────────────────────
# predis statt phpredis: umgeht laravel/framework#57908 (Cache::tags()->flush()
# stuerzt mit "Cannot use bool as array" ab, PHP >= 8.5 -- Fix nur in
# Laravel 12.x, unser composer.json ist auf ^11.0 gepinnt).
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
REDIS_SESSION_DB=3

# ─── Cache ────────────────────────────────────────────────────────────
CACHE_STORE=redis
CACHE_PREFIX=pim

CACHE_TTL_PRODUCT_FULL=3600
CACHE_TTL_PRODUCT_LANG=3600
CACHE_TTL_HIERARCHY_TREE=21600
CACHE_TTL_PQL_RESULT=900
CACHE_TTL_PRODUCT_LIST=300
CACHE_TTL_ATTRIBUTES_ALL=3600
CACHE_TTL_EXPORT_MAPPING=1800

# ─── Queue ────────────────────────────────────────────────────────────
QUEUE_CONNECTION=redis

# ─── Horizon ──────────────────────────────────────────────────────────
HORIZON_PREFIX=pim_horizon:

# ─── Session ──────────────────────────────────────────────────────────
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=${SESSION_SECURE}
SESSION_COOKIE_PATH=${WEB_PATH:-/}

# ─── Sanctum ──────────────────────────────────────────────────────────
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_DOMAIN}

# ─── Filesystem ──────────────────────────────────────────────────────
FILESYSTEM_DISK=local

# ─── Mail ─────────────────────────────────────────────────────────────
MAIL_MAILER=log

# ─── Typesense (PDF-Volltextsuche) ──────────────────────────────────
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=${TYPESENSE_API_KEY}

# ─── Meilisearch (Semantische Schnellsuche, Hybrid Search) ──────────
# Setup: php artisan pim:meili-setup
# Erstbefuellung: php artisan pim:meili-index --all
MEILISEARCH_ENABLED=$([ -n "${MEILI_API_KEY}" ] && echo true || echo false)
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_API_KEY=${MEILI_API_KEY}
MEILISEARCH_INDEX=products
MEILISEARCH_EMBEDDER_ENABLED=true
MEILISEARCH_EMBEDDER_SOURCE=ollama
MEILISEARCH_EMBEDDER_MODEL=nomic-embed-text
MEILISEARCH_EMBEDDER_URL=http://localhost:11434/api/embeddings
MEILISEARCH_SEMANTIC_RATIO=0.5
MEILISEARCH_NUMERIC_TOLERANCE=0.05

# ─── Frontend (CORS) ────────────────────────────────────────────────
FRONTEND_URL=${APP_URL}
ENVFILE

info ".env erstellt."

# --- .env.testing erstellen ---
info "Erstelle .env.testing fuer Test-Datenbank..."
cat > "${INSTALL_DIR}/.env.testing" <<ENVTESTFILE
APP_ENV=testing
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}_testing
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array
BCRYPT_ROUNDS=4
ENVTESTFILE

chown www-data:www-data "${INSTALL_DIR}/.env.testing"
chmod 640 "${INSTALL_DIR}/.env.testing"
info ".env.testing erstellt."

# --- Composer Install ---
info "Installiere PHP-Abhaengigkeiten (Composer)..."
info "(Dies kann bei der ersten Ausfuehrung einige Minuten dauern...)"
cd "$INSTALL_DIR"

# --no-scripts: verhindert artisan package:discover waehrend Install (haengt ggf.)
# --no-plugins: keine Plugin-Downloads waehrend Install
# --prefer-dist: Zip statt Git-Clone (schneller, weniger Netzwerk)
# memory_limit=-1: verhindert stilles Haengen bei wenig RAM
php -d memory_limit=-1 "$(which composer)" install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --no-plugins \
    --prefer-dist \
    -v \
    2>&1

info "PHP-Abhaengigkeiten installiert."

# --- Laravel-Verzeichnisse sicherstellen (bevor artisan laeuft) ---
info "Stelle sicher, dass Laravel-Verzeichnisse existieren..."
mkdir -p "${INSTALL_DIR}/bootstrap/cache"
mkdir -p "${INSTALL_DIR}/storage/framework/"{cache,sessions,views}
mkdir -p "${INSTALL_DIR}/storage/logs"
chown -R www-data:www-data "${INSTALL_DIR}/bootstrap/cache"
chown -R www-data:www-data "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
chmod -R 775 "${INSTALL_DIR}/storage"

# Post-Install Scripts separat ausfuehren (package:discover etc.)
info "Fuehre Composer post-install Scripts aus..."
php artisan package:discover --ansi 2>&1 || true

# --- App Key generieren ---
info "Generiere Application Key..."
php artisan key:generate --force

# APP_KEY in .env.testing uebernehmen
APP_KEY_VALUE=$(grep '^APP_KEY=' "${INSTALL_DIR}/.env" | head -1 | cut -d= -f2-)
sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" "${INSTALL_DIR}/.env.testing"
info "APP_KEY in .env.testing uebernommen."

# --- Datenbank migrieren ---
info "Fuehre Datenbank-Migrationen aus..."
if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    info "Fuehre nur fehlende Migrationen aus (Datenbank beibehalten)..."
fi
php artisan migrate --force

# --- Test-Datenbank migrieren ---
info "Migriere Test-Datenbank (${DB_NAME}_testing)..."
php artisan migrate --database=mysql --force --env=testing 2>&1 \
    && info "Test-Datenbank migriert." \
    || warn "Test-Datenbank konnte nicht migriert werden — kann spaeter mit 'php artisan migrate --env=testing' nachgeholt werden."

# --- Demodaten laden (Seed) ---
if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    info "Ueberspringe Demodaten (Datenbank wurde beibehalten)."
else
    info "Lade Demodaten (Rollen, Benutzer, Produkttypen, Attribute, Hierarchien, Produkte)..."
    php artisan db:seed --force
fi

# --- Zusaetzlichen Admin-Benutzer anlegen ---
if [ "$CUSTOM_ADMIN" = true ]; then
    info "Lege zusaetzlichen Admin-Benutzer an: ${ADMIN_EMAIL}..."

    # Passwort mit einfachen Anfuehrungszeichen escapen fuer PHP
    ADMIN_PASS_ESCAPED=$(printf '%s' "$ADMIN_PASS" | sed "s/'/\\\\'/g")

    php artisan tinker --execute="
        \$user = \App\Models\User::firstOrCreate(
            ['email' => '${ADMIN_EMAIL}'],
            [
                'name' => '${ADMIN_NAME}',
                'password' => \Illuminate\Support\Facades\Hash::make('${ADMIN_PASS_ESCAPED}'),
                'language' => 'de',
                'is_active' => true,
            ]
        );
        \$user->assignRole('Admin');
        echo 'Admin-Benutzer ' . \$user->email . ' angelegt (ID: ' . \$user->id . ')';
    " 2>&1

    info "Admin-Benutzer '${ADMIN_EMAIL}' angelegt."
fi

# --- Typesense Collection anlegen ---
if [ -n "$TYPESENSE_API_KEY" ]; then
    info "Erstelle Typesense Collection fuer PDF-Suche..."
    php artisan typesense:setup 2>&1 && info "Typesense Collection angelegt." \
        || warn "Typesense Collection konnte nicht angelegt werden — kann spaeter mit 'php artisan typesense:setup' nachgeholt werden."
else
    warn "Kein Typesense API-Key gefunden — PDF-Suche muss manuell eingerichtet werden."
    warn "Siehe: php artisan typesense:setup"
fi

# --- Meilisearch-Index konfigurieren und Erstbefuellung ---
if [ -n "$MEILI_API_KEY" ]; then
    info "Konfiguriere Meilisearch-Index (Filter, Synonyme, Embedder)..."
    php artisan pim:meili-setup 2>&1 \
        && info "Meilisearch-Index konfiguriert." \
        || warn "Meilisearch-Setup fehlgeschlagen — spaeter nachholen mit: php artisan pim:meili-setup"

    info "Befuelle Meilisearch-Index (Erstbefuellung, laeuft im Hintergrund)..."
    nohup php artisan pim:meili-index --all \
        >> "${INSTALL_DIR}/storage/logs/meilisearch-index.log" 2>&1 &
    info "Meilisearch-Erstbefuellung gestartet (PID: $!, Log: storage/logs/meilisearch-index.log)."
    info "Embeddings berechnet Meilisearch asynchron — Index wird in Kuerze suchbereit."
else
    warn "Meilisearch-API-Key nicht gesetzt — Index-Setup uebersprungen."
    warn "Nach Einrichtung nachholen mit: php artisan pim:meili-setup && php artisan pim:meili-index --all"
fi

# --- Storage Link ---
info "Erstelle Storage-Symlink..."
php artisan storage:link 2>/dev/null || true

# ═════════════════════════════════════════════════════════════════════════════
#  9. FRONTEND BAUEN (Vue 3 / Vite)
# ═════════════════════════════════════════════════════════════════════════════
step "9/10 — Frontend bauen"

FRONTEND_DIR="${INSTALL_DIR}/pim-frontend"

if [ -d "$FRONTEND_DIR" ]; then
    info "Installiere Frontend-Abhaengigkeiten..."
    cd "$FRONTEND_DIR"
    npm ci --production=false 2>&1

    info "Baue Frontend (Vue 3 + Vite)..."

    # Bei Subdirectory-Deployment: Vite Base-Path und API-URL setzen
    if [ -n "$WEB_PATH" ]; then
        export VITE_BASE_PATH="${WEB_PATH}/"
        export VITE_API_BASE_URL="${WEB_PATH}/api/v1"
        info "Frontend Base-Path: ${VITE_BASE_PATH}"
    fi

    npm run build 2>&1

    # Build-Output nach public/ kopieren
    if [ -d "${FRONTEND_DIR}/dist" ]; then
        info "Kopiere Frontend-Build nach public/..."
        cp "${FRONTEND_DIR}/dist/index.html" "${INSTALL_DIR}/public/spa.html"
        # Assets kopieren (CSS, JS, Bilder)
        if [ -d "${FRONTEND_DIR}/dist/pim-assets" ]; then
            cp -r "${FRONTEND_DIR}/dist/pim-assets" "${INSTALL_DIR}/public/"
        fi
        # Favicon und andere Root-Dateien kopieren
        find "${FRONTEND_DIR}/dist" -maxdepth 1 -type f ! -name "index.html" -exec cp {} "${INSTALL_DIR}/public/" \;
        info "Frontend-Build erfolgreich nach public/ kopiert."
    else
        warn "Frontend dist/ nicht gefunden — Build moeglicherweise fehlgeschlagen."
    fi

    cd "$INSTALL_DIR"
else
    warn "Frontend-Verzeichnis nicht gefunden — ueberspringe Frontend-Build."
fi

# ── Katalog-Embed Bundles bauen (Online + Offline) ──
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

# ── Dokumentation bauen (VitePress) ──
DOCS_DIR="${INSTALL_DIR}/static-content"
if [ -d "$DOCS_DIR" ] && [ -f "${DOCS_DIR}/package.json" ]; then
    info "Installiere Dokumentations-Abhaengigkeiten..."
    cd "$DOCS_DIR"
    npm ci 2>&1

    info "Baue Dokumentation (VitePress)..."
    npm run build 2>&1

    if [ -d "${DOCS_DIR}/.vitepress/dist" ]; then
        info "Dokumentation erfolgreich gebaut."
    else
        warn "Dokumentation dist/ nicht gefunden — Build moeglicherweise fehlgeschlagen."
    fi
    cd "$INSTALL_DIR"
else
    warn "Dokumentations-Verzeichnis nicht gefunden — ueberspringe Docs-Build."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  VIDEO ENGINE (optional)
# ═════════════════════════════════════════════════════════════════════════════
VIDEO_ENGINE_DIR="${INSTALL_DIR}/video-engine"

# In eine Funktion gekapselt, damit ein Fehlschlag (z.B. Playwright kennt eine
# brandneue Ubuntu-Version noch nicht) nur DIESEN optionalen Block per
# "if ! setup_video_engine" abfaengt, statt via set -e die GESAMTE
# Installation abzubrechen — "(optional)" im Step-Namen war bisher nicht
# tatsaechlich optional.
setup_video_engine() {
    step "Video-Engine einrichten (optional)"

    # System-Pakete fuer Video-Aufnahme
    info "Installiere Video-Engine Abhaengigkeiten (ffmpeg, Xvfb, x11-utils)..."
    apt-get install -y -qq \
        ffmpeg \
        xvfb \
        x11-utils \
        2>&1 | tail -1
    info "ffmpeg: $(ffmpeg -version 2>&1 | head -1 | cut -d' ' -f1-3)"
    info "Xvfb: $(which Xvfb)"
    info "xdpyinfo: $(which xdpyinfo)"

    # Node-Abhaengigkeiten installieren
    info "Installiere Video-Engine npm-Pakete..."
    cd "$VIDEO_ENGINE_DIR"
    npm ci --fund=false --loglevel=warn 2>&1

    # Playwright Browser installieren (Chromium)
    # WICHTIG: An einen gemeinsamen, fuer www-data lesbaren Ort installieren.
    # Ohne PLAYWRIGHT_BROWSERS_PATH landet der Browser in /root/.cache/ms-playwright
    # und der Webserver/Queue-Worker (www-data) findet ihn nicht ("Chromium nicht gefunden").
    # Ueberschreibbar via Umgebungsvariable PLAYWRIGHT_BROWSERS_PATH (Option).
    PW_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-/opt/pw-browsers}"
    info "Installiere Playwright Chromium Browser nach ${PW_BROWSERS_PATH}..."
    mkdir -p "$PW_BROWSERS_PATH"
    PLAYWRIGHT_BROWSERS_PATH="$PW_BROWSERS_PATH" npx playwright install --with-deps chromium 2>&1 | tail -5
    chmod -R a+rX "$PW_BROWSERS_PATH"
    info "Playwright Browser installiert (${PW_BROWSERS_PATH})."

    # Pfad in der App-.env hinterlegen, damit der Render-Job den Browser
    # unabhaengig vom HOME des ausfuehrenden Users findet (Webserver/Queue-Worker).
    if ! grep -q '^PLAYWRIGHT_BROWSERS_PATH=' "${INSTALL_DIR}/.env"; then
        echo "PLAYWRIGHT_BROWSERS_PATH=${PW_BROWSERS_PATH}" >> "${INSTALL_DIR}/.env"
        info "PLAYWRIGHT_BROWSERS_PATH=${PW_BROWSERS_PATH} in .env eingetragen."
    fi

    # Storage-Verzeichnisse sicherstellen
    mkdir -p "${INSTALL_DIR}/storage/video-engine/"{logs,tmp,lock}
    mkdir -p "${INSTALL_DIR}/public/videos"

    # Demo-Assets erzeugen
    if [ ! -f "${INSTALL_DIR}/video-stories/demo-assets/logo-anypim.svg" ]; then
        info "Erzeuge Demo-Assets..."
        npx tsx scripts/create-demo-assets.ts 2>&1
    fi

    # Sonic Logo erzeugen
    if [ ! -f "${INSTALL_DIR}/video-stories/demo-assets/anypim-sonic-logo.mp3" ]; then
        info "Erzeuge Sonic Logo..."
        npx tsx scripts/create-sonic-logo.ts 2>&1
    fi

    cd "$INSTALL_DIR"
    info "Video-Engine eingerichtet."
    info "  Nutzung: php artisan pim:video-generate --list"
    info "  Preflight: php artisan pim:video-generate --preflight"
}

if [ -d "$VIDEO_ENGINE_DIR" ] && [ -f "${VIDEO_ENGINE_DIR}/package.json" ]; then
    if ! setup_video_engine; then
        cd "$INSTALL_DIR"
        warn "Video-Engine-Setup fehlgeschlagen — optionales Feature wird uebersprungen, Rest der Installation laeuft weiter."
        warn "Haeufige Ursache auf brandneuen Ubuntu-Versionen: Playwright kennt die Distribution (noch) nicht."
        warn "Spaeter manuell nachholen: cd ${VIDEO_ENGINE_DIR} && npx playwright install --with-deps chromium"
    fi
else
    info "Video-Engine nicht vorhanden — uebersprungen."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  10. WEBSERVER, SERVICES & BERECHTIGUNGEN
# ═════════════════════════════════════════════════════════════════════════════
step "10/10 — Apache VHost, Supervisor, Cron & Berechtigungen"

# --- Dateiberechtigungen (final, nach Frontend-Build) ---
info "Setze finale Dateiberechtigungen..."
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R u=rwX,g=rX,o=rX "$INSTALL_DIR"
chmod -R 775 "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"

# --- Apache-Konfiguration ---
if [ -n "$WEB_PATH" ]; then
    # ╔═══════════════════════════════════════════════════════════════════╗
    # ║  SUBDIRECTORY-MODUS: Apache Alias (kein eigener VHost)          ║
    # ╠═══════════════════════════════════════════════════════════════════╣
    # ║  Verwendet Apache Alias, um PIM unter einem Unterverzeichnis    ║
    # ║  eines bestehenden VHosts bereitzustellen.                      ║
    # ║  z.B. https://example.com/web → /var/www/publixx-pim/public    ║
    # ╚═══════════════════════════════════════════════════════════════════╝
    info "Konfiguriere Apache-Alias fuer ${WEB_PATH}..."

    # Alias-Modul aktivieren
    a2enmod alias > /dev/null 2>&1

    # Globale Alias-Konfiguration (wird in alle VHosts eingebunden)
    ALIAS_CONF="/etc/apache2/conf-available/publixx-pim.conf"

    WRITE_ALIAS_CONF=true
    if [ -f "$ALIAS_CONF" ]; then
        warn "Apache-Konfiguration existiert bereits:"
        echo ""
        echo -e "${CYAN}--- ${ALIAS_CONF} ---${NC}"
        cat "$ALIAS_CONF"
        echo -e "${CYAN}--- Ende ---${NC}"
        echo ""
        ask "Was moechtest du tun?\n"
        echo -e "  ${BOLD}1${NC}) Ueberschreiben — Neue Konfiguration fuer '${WEB_PATH}' schreiben"
        echo -e "  ${BOLD}2${NC}) Beibehalten    — Bestehende Konfiguration nicht aendern"
        echo ""
        ask "Auswahl [1/2]: "
        read -r ALIAS_CHOICE
        case "$ALIAS_CHOICE" in
            1)
                info "Konfiguration wird ueberschrieben..."
                ;;
            2)
                WRITE_ALIAS_CONF=false
                info "Bestehende Apache-Konfiguration beibehalten."
                ;;
            *)
                WRITE_ALIAS_CONF=false
                info "Ungueltige Auswahl — bestehende Konfiguration beibehalten."
                ;;
        esac
    fi

    if [ "$WRITE_ALIAS_CONF" = true ]; then
        cat > "$ALIAS_CONF" <<ALIASCONF
# anyPIM — Apache Alias fuer Unterverzeichnis ${WEB_PATH}
# Generiert von setup.sh
#
# Diese Konfiguration bindet PIM unter ${WEB_PATH} ein.
# Sie wird automatisch in alle VHosts geladen.

Alias ${WEB_PATH} ${INSTALL_DIR}/public

<Directory ${INSTALL_DIR}/public>
    AllowOverride All
    Require all granted
    Options -Indexes +FollowSymLinks

    # Sicherheits-Header
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</Directory>

# anyPIM — Dokumentation (VitePress)
Alias ${WEB_PATH}/help ${INSTALL_DIR}/static-content/.vitepress/dist

<Directory ${INSTALL_DIR}/static-content/.vitepress/dist>
    Options -Indexes
    AllowOverride None
    Require all granted
    FallbackResource ${WEB_PATH}/help/index.html
</Directory>
ALIASCONF

        a2enconf publixx-pim > /dev/null 2>&1

        if apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
            systemctl reload apache2
            info "Apache-Alias '${WEB_PATH}' konfiguriert und Apache neu geladen."
        else
            warn "Apache-Konfiguration fehlerhaft — bitte manuell pruefen."
            apache2ctl configtest
        fi
    fi

    info "Der Alias ist global aktiv und funktioniert mit allen bestehenden VHosts."
    info "PIM ist erreichbar unter: ${APP_URL}"

else
    # ╔═══════════════════════════════════════════════════════════════════╗
    # ║  ROOT-MODUS: Eigener VHost (PIM ist die einzige App)            ║
    # ╚═══════════════════════════════════════════════════════════════════╝
    info "Konfiguriere Apache-Port ${APP_PORT}..."

    PORTS_CONF="/etc/apache2/ports.conf"

    # ports.conf komplett neu schreiben: nur die Ports, die wir brauchen.
    # Das verhindert Konflikte mit Default-Listen-Direktiven (z.B. Port 80),
    # wenn ein anderer Dienst dort bereits laeuft.
    {
        echo "# anyPIM — generiert von setup.sh"
        echo "Listen ${APP_PORT}"
        if [ "$USE_SSL" = true ]; then
            echo "Listen ${SSL_PORT}"
        fi
    } > "$PORTS_CONF"
    info "ports.conf: Listen ${APP_PORT}$([ "$USE_SSL" = true ] && echo " + ${SSL_PORT} (SSL)" || echo "")"

    # --- Apache VHost ---
    info "Erstelle Apache VirtualHost (Port ${APP_PORT})..."

    VHOST_FILE="/etc/apache2/sites-available/publixx-pim.conf"

    cat > "$VHOST_FILE" <<VHOST
<VirtualHost *:${APP_PORT}>
    ServerName ${SERVER_DOMAIN}
    DocumentRoot ${INSTALL_DIR}/public

    <Directory ${INSTALL_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # anyPIM — Dokumentation (VitePress)
    Alias /web/help ${INSTALL_DIR}/static-content/.vitepress/dist

    <Directory ${INSTALL_DIR}/static-content/.vitepress/dist>
        Options -Indexes
        AllowOverride None
        Require all granted
        FallbackResource /web/help/index.html
    </Directory>

    # Sicherheits-Header
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Log-Dateien
    ErrorLog \${APACHE_LOG_DIR}/publixx-pim-error.log
    CustomLog \${APACHE_LOG_DIR}/publixx-pim-access.log combined

    # Upload-Limit (64 MB)
    LimitRequestBody 268435456
</VirtualHost>
VHOST

    a2ensite publixx-pim.conf > /dev/null 2>&1

    # Apache-Konfiguration testen
    if apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
        systemctl restart apache2
        info "Apache VHost aktiviert (Port ${APP_PORT}) und Apache neu gestartet."
    else
        warn "Apache-Konfiguration fehlerhaft — bitte manuell pruefen."
        apache2ctl configtest
    fi

    # --- SSL mit Let's Encrypt (optional) ---
    if [ "$USE_SSL" = true ]; then
        # Pruefen ob bereits ein Zertifikat fuer diese Domain existiert
        if [ -d "/etc/letsencrypt/live/${SERVER_DOMAIN}" ]; then
            info "Let's Encrypt Zertifikat fuer '${SERVER_DOMAIN}' existiert bereits."
            ask "SSL-Zertifikat neu einrichten / erneuern? [j/N]: "
            read -r SSL_RENEW
            if [[ ! "$SSL_RENEW" =~ ^[jJyY]$ ]]; then
                info "Bestehendes SSL-Zertifikat wird beibehalten."
                # SSL-VHost trotzdem erstellen falls noch nicht vorhanden
                if ! grep -q "SSLEngine" "$VHOST_FILE" 2>/dev/null; then
                    info "Erstelle SSL-VHost mit bestehendem Zertifikat auf Port ${SSL_PORT}..."

                    cat >> "$VHOST_FILE" <<SSLEXISTING

<VirtualHost *:${SSL_PORT}>
    ServerName ${SERVER_DOMAIN}
    DocumentRoot ${INSTALL_DIR}/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${SERVER_DOMAIN}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${SERVER_DOMAIN}/privkey.pem

    <Directory ${INSTALL_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # anyPIM — Dokumentation (VitePress)
    Alias /web/help ${INSTALL_DIR}/static-content/.vitepress/dist

    <Directory ${INSTALL_DIR}/static-content/.vitepress/dist>
        Options -Indexes
        AllowOverride None
        Require all granted
        FallbackResource /web/help/index.html
    </Directory>

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog \${APACHE_LOG_DIR}/publixx-pim-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/publixx-pim-ssl-access.log combined

    LimitRequestBody 268435456
</VirtualHost>
SSLEXISTING
                    systemctl restart apache2
                    info "SSL-VHost auf Port ${SSL_PORT} mit bestehendem Zertifikat aktiviert."
                fi

                USE_SSL_SETUP=false
            else
                USE_SSL_SETUP=true
            fi
        else
            USE_SSL_SETUP=true
        fi

        if [ "${USE_SSL_SETUP:-true}" = true ]; then
            info "Richte Let's Encrypt SSL ein..."
            apt-get install -y -qq certbot python3-certbot-apache

        if [ "$APP_PORT" -eq 80 ] && [ "$SSL_PORT" -eq 443 ]; then
            # Standard-Ports: Certbot kann automatisch konfigurieren
            certbot --apache \
                -d "$SERVER_DOMAIN" \
                --non-interactive \
                --agree-tos \
                --email "$SSL_EMAIL" \
                --redirect \
            && info "SSL-Zertifikat erfolgreich eingerichtet." \
            || warn "SSL-Einrichtung fehlgeschlagen — kann spaeter mit 'sudo certbot --apache' nachgeholt werden."
        else
            # Nicht-Standard-Ports: Nur Zertifikat holen, VHost manuell anpassen
            certbot certonly --standalone \
                -d "$SERVER_DOMAIN" \
                --non-interactive \
                --agree-tos \
                --email "$SSL_EMAIL" \
                --http-01-port "$APP_PORT" \
            && {
                info "SSL-Zertifikat erstellt. Erstelle SSL-VHost auf Port ${SSL_PORT}..."

                # SSL-VHost hinzufuegen
                cat >> "$VHOST_FILE" <<SSLVHOST

<VirtualHost *:${SSL_PORT}>
    ServerName ${SERVER_DOMAIN}
    DocumentRoot ${INSTALL_DIR}/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${SERVER_DOMAIN}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${SERVER_DOMAIN}/privkey.pem

    <Directory ${INSTALL_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # anyPIM — Dokumentation (VitePress)
    Alias /web/help ${INSTALL_DIR}/static-content/.vitepress/dist

    <Directory ${INSTALL_DIR}/static-content/.vitepress/dist>
        Options -Indexes
        AllowOverride None
        Require all granted
        FallbackResource /web/help/index.html
    </Directory>

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog \${APACHE_LOG_DIR}/publixx-pim-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/publixx-pim-ssl-access.log combined

    LimitRequestBody 268435456
</VirtualHost>
SSLVHOST
                systemctl restart apache2
                info "SSL-VHost auf Port ${SSL_PORT} aktiviert."
            } \
            || warn "SSL-Einrichtung fehlgeschlagen — kann spaeter nachgeholt werden."
        fi
        fi  # USE_SSL_SETUP
    fi
fi

# --- Supervisor fuer Horizon ---
info "Konfiguriere Supervisor fuer Laravel Horizon..."

cat > /etc/supervisor/conf.d/horizon.conf <<SUPERVISOR
[program:horizon]
process_name=%(program_name)s
command=php ${INSTALL_DIR}/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/horizon.log
stopwaitsecs=3600
SUPERVISOR

supervisorctl reread > /dev/null 2>&1
supervisorctl update > /dev/null 2>&1
supervisorctl start horizon > /dev/null 2>&1 || true

info "Horizon via Supervisor konfiguriert."

# --- Cron fuer Laravel Scheduler ---
info "Richte Laravel Scheduler Cron-Job ein..."

CRON_LINE="* * * * * cd ${INSTALL_DIR} && php artisan schedule:run >> /dev/null 2>&1"

# Bestehenden Cron fuer www-data pruefen und hinzufuegen
(crontab -u www-data -l 2>/dev/null | grep -v "artisan schedule:run"; echo "$CRON_LINE") | crontab -u www-data -

info "Cron-Job fuer Laravel Scheduler eingerichtet."

# --- Laravel Caches optimieren ---
info "Optimiere Laravel-Caches..."
cd "$INSTALL_DIR"
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "Caches erstellt."

# --- Firewall (optional, nur wenn ufw installiert) ---
if command -v ufw &> /dev/null; then
    info "Konfiguriere Firewall (UFW)..."
    if [ "$APP_PORT" -eq 80 ]; then
        ufw allow 'Apache Full' > /dev/null 2>&1 || true
        info "Firewall-Regel: Apache Full (80/443)"
    else
        ufw allow "$APP_PORT/tcp" > /dev/null 2>&1 || true
        info "Firewall-Regel: Port ${APP_PORT}/tcp"
        if [ "$USE_SSL" = true ] && [ "$SSL_PORT" -ne 443 ]; then
            ufw allow "$SSL_PORT/tcp" > /dev/null 2>&1 || true
            info "Firewall-Regel: Port ${SSL_PORT}/tcp (SSL)"
        fi
    fi
    ufw allow OpenSSH > /dev/null 2>&1 || true
    # ufw nicht automatisch aktivieren — das soll der Admin entscheiden
    info "Firewall-Regeln hinzugefuegt. Aktiviere mit: sudo ufw enable"
fi

# ═════════════════════════════════════════════════════════════════════════════
#  FERTIG
# ═════════════════════════════════════════════════════════════════════════════

# Installation erfolgreich -> gespeicherte Klartext-Passwoerter nicht mehr noetig.
rm -f "$CONFIG_FILE"

INSTALL_END=$(date +%s)
INSTALL_DURATION=$(( INSTALL_END - INSTALL_START ))
INSTALL_MINUTES=$(( INSTALL_DURATION / 60 ))
INSTALL_SECONDS=$(( INSTALL_DURATION % 60 ))

echo ""
echo -e "${BOLD}${GREEN}"
cat << 'DONE'
  ╔══════════════════════════════════════════════════════════╗
  ║                                                          ║
  ║   Installation erfolgreich abgeschlossen!                ║
  ║                                                          ║
  ╚══════════════════════════════════════════════════════════╝
DONE
echo -e "${NC}"

echo -e "${BOLD}Zusammenfassung:${NC}"
echo -e "  Dauer:         ${INSTALL_MINUTES}m ${INSTALL_SECONDS}s"
echo -e "  URL:           ${BOLD}${APP_URL}${NC}"
echo -e "  Web-Pfad:      ${BOLD}${WEB_PATH:-/ (Root)}${NC}"
echo -e "  Pfad:          ${INSTALL_DIR}"
echo -e "  Datenbank:     ${DB_NAME}"
echo ""
echo -e "${BOLD}Anmeldedaten:${NC}"
echo -e "  E-Mail:        ${BOLD}admin@publixx.com${NC}"
echo -e "  Passwort:      ${BOLD}password${NC}"
echo ""
echo -e "  E-Mail:        ${BOLD}admin@example.com${NC}"
echo -e "  Passwort:      ${BOLD}password${NC}"
if [ "$CUSTOM_ADMIN" = true ]; then
    echo ""
    echo -e "  E-Mail:        ${BOLD}${ADMIN_EMAIL}${NC}"
    echo -e "  Passwort:      ${BOLD}********${NC}"
fi
echo ""
echo -e "${YELLOW}Wichtig: Aendere die Standard-Passwoerter nach dem ersten Login!${NC}"
echo ""
echo -e "${BOLD}Dienste:${NC}"
echo -e "  Apache:        $(systemctl is-active apache2 2>/dev/null || echo 'unbekannt') (Port ${APP_PORT}$([ "$USE_SSL" = true ] && echo ", SSL ${SSL_PORT}" || echo ""))"
echo -e "  MySQL:         $(systemctl is-active mysql 2>/dev/null || echo 'unbekannt')"
echo -e "  Redis:         $(systemctl is-active redis-server 2>/dev/null || echo 'unbekannt')"
echo -e "  Supervisor:    $(systemctl is-active supervisor 2>/dev/null || echo 'unbekannt')"
echo -e "  Typesense:     $(systemctl is-active typesense-server 2>/dev/null || echo 'unbekannt') (Port 8108)"
echo -e "  Meilisearch:   $(systemctl is-active meilisearch 2>/dev/null || echo 'nicht installiert') (Port 7700)"
echo ""
echo -e "${BOLD}Nuetzliche Befehle:${NC}"
echo -e "  Horizon:       sudo supervisorctl status horizon"
echo -e "  Logs:          tail -f ${INSTALL_DIR}/storage/logs/laravel.log"
echo -e "  Update:        sudo bash ${INSTALL_DIR}/update.sh"
echo -e "  Artisan:       cd ${INSTALL_DIR} && php artisan"
echo ""
if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    echo -e "${BOLD}Datenbank:${NC}"
    echo -e "  Bestehende Datenbank '${DB_NAME}' wurde beibehalten."
    echo -e "  Fehlende Migrationen wurden ausgefuehrt."
    echo -e "  Demodaten wurden nicht erneut geladen."
else
    echo -e "${BOLD}Demodaten geladen:${NC}"
    echo -e "  7 Rollen (Admin, Data Steward, Product Manager, Viewer, Export Manager, API Designer, Project Management)"
    echo -e "  2 Admin-Benutzer"
    echo -e "  6 Produkttypen"
    echo -e "  12 Attribute mit Einheiten und Wertelisten"
    echo -e "  Produkt-Hierarchien (Elektrowerkzeuge)"
    echo -e "  5 Demo-Produkte mit Preisen und Attributwerten"
fi
echo ""
if [ -n "$WEB_PATH" ]; then
    echo -e "${BOLD}Subdirectory-Modus:${NC}"
    echo -e "  PIM laeuft unter:  ${BOLD}${APP_URL}${NC}"
    echo -e "  Apache-Alias:      ${WEB_PATH} -> ${INSTALL_DIR}/public"
    echo -e "  Konfiguration:     /etc/apache2/conf-available/publixx-pim.conf"
    echo -e ""
    echo -e "  ${YELLOW}Hinweis: PIM nutzt den bestehenden VHost fuer ${SERVER_DOMAIN}.${NC}"
    echo -e "  ${YELLOW}Stelle sicher, dass ein VHost fuer diese Domain existiert.${NC}"
    echo ""
fi
