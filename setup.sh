#!/bin/bash
#
# ╔══════════════════════════════════════════════════════════════════════╗
# ║              Publixx PIM — Setup & Installer                       ║
# ║              Ubuntu 24.04 LTS · Apache · PHP 8.4 · MySQL 8        ║
# ╚══════════════════════════════════════════════════════════════════════╝
#
# Dieses Script richtet eine vollstaendige Publixx PIM Instanz ein:
#   - Apache2 mit VirtualHost
#   - PHP 8.4 + alle benoetigten Extensions
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

# ═════════════════════════════════════════════════════════════════════════════
#  INTERAKTIVE KONFIGURATION
# ═════════════════════════════════════════════════════════════════════════════
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

# URL zusammenbauen (Port nur anhaengen wenn nicht Standard)
if [ "$APP_PROTOCOL" = "https" ]; then
    DEFAULT_PORT=443
    EFFECTIVE_PORT=$SSL_PORT
else
    DEFAULT_PORT=80
    EFFECTIVE_PORT=$APP_PORT
fi

if [ "$EFFECTIVE_PORT" -eq "$DEFAULT_PORT" ]; then
    APP_URL="${APP_PROTOCOL}://${SERVER_DOMAIN}"
    SANCTUM_DOMAIN="${SERVER_DOMAIN}"
else
    APP_URL="${APP_PROTOCOL}://${SERVER_DOMAIN}:${EFFECTIVE_PORT}"
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

# --- Zusammenfassung ---
echo ""
echo -e "${BOLD}${BLUE}═══ Zusammenfassung ═══${NC}"
echo -e "  Server:       ${BOLD}${SERVER_DOMAIN}${NC}"
echo -e "  Port:         ${BOLD}${APP_PORT}$([ "$USE_SSL" = true ] && echo " (SSL: ${SSL_PORT})" || echo "")${NC}"
echo -e "  URL:          ${BOLD}${APP_URL}${NC}"
echo -e "  SSL:          ${BOLD}$([ "$USE_SSL" = true ] && echo 'Ja (Let'\''s Encrypt)' || echo 'Nein')${NC}"
echo -e "  MySQL DB:     ${BOLD}${DB_NAME}${NC}"
echo -e "  MySQL User:   ${BOLD}${DB_USER}${NC}"
echo -e "  MySQL Pass:   ${BOLD}********${NC}"
echo -e "  Pfad:         ${BOLD}${INSTALL_DIR}${NC}"
echo ""
ask "Alles korrekt? Installation starten? [j/N]: "
read -r START_INSTALL
if [[ ! "$START_INSTALL" =~ ^[jJyY]$ ]]; then
    echo "Abgebrochen."
    exit 0
fi

INSTALL_START=$(date +%s)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ═════════════════════════════════════════════════════════════════════════════
#  1. SYSTEM-PAKETE AKTUALISIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "1/10 — System aktualisieren"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
info "System aktualisiert."

# ═════════════════════════════════════════════════════════════════════════════
#  2. PHP 8.4 INSTALLIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "2/10 — PHP 8.4 installieren"

# PPA hinzufuegen falls noch nicht vorhanden
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
    add-apt-repository ppa:ondrej/php -y
    apt-get update -qq
fi

apt-get install -y -qq \
    php8.4 \
    php8.4-cli \
    php8.4-common \
    php8.4-mysql \
    php8.4-redis \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    php8.4-gd \
    php8.4-bcmath \
    php8.4-curl \
    php8.4-intl \
    php8.4-readline \
    php8.4-opcache \
    libapache2-mod-php8.4

info "PHP 8.4 installiert: $(php -v | head -1)"

# PHP Konfiguration optimieren
PHP_INI="/etc/php/8.4/apache2/php.ini"
if [ -f "$PHP_INI" ]; then
    sed -i 's/^memory_limit = .*/memory_limit = 256M/' "$PHP_INI"
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 64M/' "$PHP_INI"
    sed -i 's/^post_max_size = .*/post_max_size = 64M/' "$PHP_INI"
    sed -i 's/^max_execution_time = .*/max_execution_time = 120/' "$PHP_INI"
    sed -i 's/^;date.timezone =.*/date.timezone = Europe\/Berlin/' "$PHP_INI"
    info "PHP-Konfiguration optimiert."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  3. APACHE INSTALLIEREN & KONFIGURIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "3/10 — Apache installieren"

apt-get install -y -qq apache2

# Module aktivieren
a2enmod rewrite headers ssl php8.4 > /dev/null 2>&1

# Default-Site deaktivieren
a2dissite 000-default.conf > /dev/null 2>&1 || true

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
        2)
            info "Datenbank wird beibehalten."
            ;;
        *)
            error "Ungueltige Auswahl. Abgebrochen."
            ;;
    esac
fi

# Datenbank und Benutzer anlegen (IF NOT EXISTS fuer Wiederholbarkeit)
mysql -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

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
#  6. NODE.JS 20 LTS INSTALLIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "6/10 — Node.js 20 LTS installieren"

if ! command -v node &> /dev/null || [[ "$(node -v | cut -d. -f1 | tr -d 'v')" -lt 18 ]]; then
    # NodeSource Repository hinzufuegen
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y -qq nodejs
fi

info "Node.js installiert: $(node -v)"
info "npm installiert: $(npm -v)"

# ═════════════════════════════════════════════════════════════════════════════
#  7. COMPOSER INSTALLIEREN
# ═════════════════════════════════════════════════════════════════════════════
step "7/10 — Composer installieren"

export COMPOSER_ALLOW_SUPERUSER=1

if ! command -v composer &> /dev/null; then
    info "Installiere Composer via apt..."
    apt-get install -y -qq composer 2>&1

    # Falls apt-Version zu alt ist (< 2.x), direkt als PHAR herunterladen
    if ! composer --version 2>/dev/null | grep -q "^Composer version 2"; then
        warn "apt-Version zu alt, lade aktuelle Version herunter..."
        curl -sS --connect-timeout 15 --max-time 120 \
            https://getcomposer.org/download/latest-stable/composer.phar \
            -o /usr/local/bin/composer \
            || error "Composer konnte nicht heruntergeladen werden. Netzwerkproblem?"
        chmod +x /usr/local/bin/composer
    fi
fi

info "Composer installiert: $(composer --version 2>/dev/null | head -1)"

# ═════════════════════════════════════════════════════════════════════════════
#  8. ANWENDUNG EINRICHTEN
# ═════════════════════════════════════════════════════════════════════════════
step "8/10 — Publixx PIM einrichten"

# Supervisor installieren (fuer Horizon)
apt-get install -y -qq supervisor

# Weitere Pakete
apt-get install -y -qq git unzip curl

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
APP_NAME="Publixx PIM"
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
REDIS_CLIENT=phpredis
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

# ─── Sanctum ──────────────────────────────────────────────────────────
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_DOMAIN}

# ─── Filesystem ──────────────────────────────────────────────────────
FILESYSTEM_DISK=local

# ─── Mail ─────────────────────────────────────────────────────────────
MAIL_MAILER=log

# ─── Frontend (CORS) ────────────────────────────────────────────────
FRONTEND_URL=${APP_URL}
ENVFILE

info ".env erstellt."

# --- Composer Install ---
info "Installiere PHP-Abhaengigkeiten (Composer)..."
cd "$INSTALL_DIR"

# --no-scripts verhindert, dass post-autoload-dump (php artisan package:discover)
# waehrend des Installs laeuft — das wuerde Laravel bootstrappen und koennte haengen,
# falls DB/Redis noch nicht erreichbar sind.
# memory_limit=-1 verhindert stilles Haengen bei wenig RAM.
php -d memory_limit=-1 "$(which composer)" install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    2>&1

# Post-Install Scripts separat ausfuehren (package:discover etc.)
info "Fuehre Composer post-install Scripts aus..."
php artisan package:discover --ansi 2>&1 || true

# --- App Key generieren ---
info "Generiere Application Key..."
php artisan key:generate --force

# --- Datenbank migrieren ---
info "Fuehre Datenbank-Migrationen aus..."
if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    info "Fuehre nur fehlende Migrationen aus (Datenbank beibehalten)..."
fi
php artisan migrate --force

# --- Demodaten laden (Seed) ---
if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    info "Ueberspringe Demodaten (Datenbank wurde beibehalten)."
else
    info "Lade Demodaten (Rollen, Benutzer, Produkttypen, Attribute, Hierarchien, Produkte)..."
    php artisan db:seed --force
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
    npm run build 2>&1

    # Build-Output nach public/ kopieren
    if [ -d "${FRONTEND_DIR}/dist" ]; then
        info "Kopiere Frontend-Build nach public/..."
        cp "${FRONTEND_DIR}/dist/index.html" "${INSTALL_DIR}/public/spa.html"
        # Assets kopieren (CSS, JS, Bilder)
        if [ -d "${FRONTEND_DIR}/dist/assets" ]; then
            cp -r "${FRONTEND_DIR}/dist/assets" "${INSTALL_DIR}/public/"
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

# ═════════════════════════════════════════════════════════════════════════════
#  10. WEBSERVER, SERVICES & BERECHTIGUNGEN
# ═════════════════════════════════════════════════════════════════════════════
step "10/10 — Apache VHost, Supervisor, Cron & Berechtigungen"

# --- Dateiberechtigungen (final, nach Frontend-Build) ---
info "Setze finale Dateiberechtigungen..."
chown -R www-data:www-data "$INSTALL_DIR"
find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"

# --- Apache Port konfigurieren ---
info "Konfiguriere Apache-Port ${APP_PORT}..."

PORTS_CONF="/etc/apache2/ports.conf"

# Listen-Direktive fuer den gewaehlten Port sicherstellen
if ! grep -q "^Listen ${APP_PORT}$" "$PORTS_CONF" 2>/dev/null; then
    # Nicht-Standard-Port hinzufuegen
    if [ "$APP_PORT" -ne 80 ] && [ "$APP_PORT" -ne 443 ]; then
        echo "Listen ${APP_PORT}" >> "$PORTS_CONF"
        info "Listen ${APP_PORT} zu ports.conf hinzugefuegt."
    fi
fi

# SSL-Port registrieren falls noetig
if [ "$USE_SSL" = true ] && [ "$SSL_PORT" -ne 443 ]; then
    if ! grep -q "^Listen ${SSL_PORT}$" "$PORTS_CONF" 2>/dev/null; then
        echo "Listen ${SSL_PORT}" >> "$PORTS_CONF"
        info "Listen ${SSL_PORT} (SSL) zu ports.conf hinzugefuegt."
    fi
fi

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

    # Sicherheits-Header
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Log-Dateien
    ErrorLog \${APACHE_LOG_DIR}/publixx-pim-error.log
    CustomLog \${APACHE_LOG_DIR}/publixx-pim-access.log combined

    # Upload-Limit (64 MB)
    LimitRequestBody 67108864
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

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog \${APACHE_LOG_DIR}/publixx-pim-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/publixx-pim-ssl-access.log combined

    LimitRequestBody 67108864
</VirtualHost>
SSLVHOST
            systemctl restart apache2
            info "SSL-VHost auf Port ${SSL_PORT} aktiviert."
        } \
        || warn "SSL-Einrichtung fehlgeschlagen — kann spaeter nachgeholt werden."
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
echo -e "  Pfad:          ${INSTALL_DIR}"
echo -e "  Datenbank:     ${DB_NAME}"
echo ""
echo -e "${BOLD}Anmeldedaten:${NC}"
echo -e "  E-Mail:        ${BOLD}admin@publixx.com${NC}"
echo -e "  Passwort:      ${BOLD}password${NC}"
echo ""
echo -e "  E-Mail:        ${BOLD}admin@example.com${NC}"
echo -e "  Passwort:      ${BOLD}password${NC}"
echo ""
echo -e "${YELLOW}Wichtig: Aendere die Standard-Passwoerter nach dem ersten Login!${NC}"
echo ""
echo -e "${BOLD}Dienste:${NC}"
echo -e "  Apache:        $(systemctl is-active apache2 2>/dev/null || echo 'unbekannt') (Port ${APP_PORT}$([ "$USE_SSL" = true ] && echo ", SSL ${SSL_PORT}" || echo ""))"
echo -e "  MySQL:         $(systemctl is-active mysql 2>/dev/null || echo 'unbekannt')"
echo -e "  Redis:         $(systemctl is-active redis-server 2>/dev/null || echo 'unbekannt')"
echo -e "  Supervisor:    $(systemctl is-active supervisor 2>/dev/null || echo 'unbekannt')"
echo ""
echo -e "${BOLD}Nuetzliche Befehle:${NC}"
echo -e "  Horizon:       sudo supervisorctl status horizon"
echo -e "  Logs:          tail -f ${INSTALL_DIR}/storage/logs/laravel.log"
echo -e "  Deploy:        sudo bash ${INSTALL_DIR}/deploy.sh"
echo -e "  Artisan:       cd ${INSTALL_DIR} && php artisan"
echo ""
if [ "$DB_EXISTS" = true ] && [ "$DB_RESET" = false ]; then
    echo -e "${BOLD}Datenbank:${NC}"
    echo -e "  Bestehende Datenbank '${DB_NAME}' wurde beibehalten."
    echo -e "  Fehlende Migrationen wurden ausgefuehrt."
    echo -e "  Demodaten wurden nicht erneut geladen."
else
    echo -e "${BOLD}Demodaten geladen:${NC}"
    echo -e "  5 Rollen (Admin, Data Steward, Product Manager, Viewer, Export Manager)"
    echo -e "  2 Admin-Benutzer"
    echo -e "  6 Produkttypen"
    echo -e "  12 Attribute mit Einheiten und Wertelisten"
    echo -e "  Produkt-Hierarchien (Elektrowerkzeuge)"
    echo -e "  5 Demo-Produkte mit Preisen und Attributwerten"
fi
echo ""
