#!/usr/bin/env bash
# =============================================================================
#  docker_clone.sh — Laufende anyPIM-Instanz 1:1 in Docker klonen
# =============================================================================
#  Klont eine bereits installierte & laufende anyPIM-Instanz (Apache + mod_php,
#  MySQL, Redis, Typesense, Horizon) inklusive aller Daten in einen
#  Docker-Compose-Stack.
#
#  Abgeglichen mit dem echten Installer (setup.sh / docs/operations/install.md):
#    - Apache 2.4 + mod_php 8.4   (nutzt die committed public/.htaccess)
#    - MySQL 8 / Redis (phpredis) / Typesense 27.1
#    - Laravel Horizon (Queue) + Scheduler  via Supervisor
#    - Frontend ist in der laufenden Instanz BEREITS gebaut  →  public/ wird
#      nur kopiert, KEIN npm-Build im Container nötig
#
#  Verwendung:
#    sudo bash /docker_clone.sh \
#        [--app-dir /var/www/anypim] \
#        [--output /opt/anypim-docker] \
#        [--port 8080] \
#        [--pack]
#
#  Idempotent: beliebig oft ausführbar — ein vorhandener Klon (Container +
#  Volumes + Verzeichnis) wird automatisch abgeräumt und neu erstellt.
#  Eignet sich als Cron-Job oder CI-Step, um immer die neueste Version
#  des laufenden Systems als Docker-Klon bereitzustellen.
#
#  --pack:  erstellt zusätzlich <output>.tar.gz zum Transfer (z.B. nach Windows)
#
#  Ergebnis:  <output>/  →  cd <output> && bash start.sh
#             (Windows: start.ps1 liegt ebenfalls im Ordner)
# =============================================================================

set -euo pipefail

# ─── Ausgabe-Helfer ──────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

# ─── Standardwerte (Default-Pfad = /var/www/anypim) ──────────────────────────
APP_DIR="/var/www/anypim"
OUTPUT_DIR="/opt/anypim-docker"
HTTP_PORT="8080"
PACK=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --app-dir)  APP_DIR="$2";    shift 2 ;;
        --output)   OUTPUT_DIR="$2"; shift 2 ;;
        --port)     HTTP_PORT="$2";  shift 2 ;;
        --pack)     PACK=true;       shift ;;
        -h|--help)
            grep -E '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) error "Unbekanntes Argument: $1  (--help für Hilfe)" ;;
    esac
done

# ─── Voraussetzungen ─────────────────────────────────────────────────────────
for cmd in docker mysqldump rsync; do
    command -v "$cmd" &>/dev/null || error "Benötigtes Programm fehlt: $cmd"
done
docker compose version &>/dev/null || error "'docker compose' (v2) nicht verfügbar"

[[ -d "$APP_DIR" ]]      || error "App-Verzeichnis nicht gefunden: $APP_DIR  (--app-dir setzen)"
[[ -f "$APP_DIR/.env" ]] || error "Keine .env in $APP_DIR — ist anyPIM dort installiert?"
[[ -d "$APP_DIR/public" ]] || error "Kein public/ in $APP_DIR — sieht nicht nach Laravel-Root aus"

# ─── Konfiguration aus laufender .env lesen ──────────────────────────────────
info "Lese Konfiguration aus $APP_DIR/.env ..."

parse_env() {
    grep -E "^${1}=" "$APP_DIR/.env" 2>/dev/null | head -1 | cut -d'=' -f2- \
        | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//' -e 's/[[:space:]]*#.*$//' || true
}

DB_HOST=$(parse_env DB_HOST);         DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT=$(parse_env DB_PORT);         DB_PORT="${DB_PORT:-3306}"
DB_DATABASE=$(parse_env DB_DATABASE); DB_DATABASE="${DB_DATABASE:-publixx_pim}"
DB_USERNAME=$(parse_env DB_USERNAME); DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD=$(parse_env DB_PASSWORD)
REDIS_PASSWORD=$(parse_env REDIS_PASSWORD)
TYPESENSE_API_KEY=$(parse_env TYPESENSE_API_KEY)

# Typesense-Key als Fallback aus der laufenden Server-Konfig holen
if [[ -z "$TYPESENSE_API_KEY" && -f /etc/typesense/typesense-server.ini ]]; then
    TYPESENSE_API_KEY=$(grep '^api-key' /etc/typesense/typesense-server.ini 2>/dev/null | cut -d'=' -f2- | tr -d ' ' || true)
fi
[[ -z "$TYPESENSE_API_KEY" ]] && TYPESENSE_API_KEY="anypim_local_key"

info "  Datenbank : ${DB_DATABASE} @ ${DB_HOST}:${DB_PORT} (User: ${DB_USERNAME})"
info "  Typesense : API-Key ${TYPESENSE_API_KEY:0:6}…"
info "  HTTP-Port : ${HTTP_PORT}"

# ─── Vorhandenen Klon abräumen (idempotent: beliebig oft ausführbar) ─────────
COMPOSE_NAME="$(basename "$OUTPUT_DIR")"   # z.B. "anypim-docker"

if [[ -f "$OUTPUT_DIR/docker-compose.yml" ]]; then
    info "Vorhandener Klon gefunden — räume Container + Volumes ab ..."
    (cd "$OUTPUT_DIR" && docker compose down -v --remove-orphans) \
        || warn "docker compose down meldete Fehler — prüfe ob noch Container laufen"
    # Sicherstellen dass kein Container des alten Stacks mehr läuft
    if docker ps --format '{{.Names}}' | grep -q "^${COMPOSE_NAME}-"; then
        error "Container des alten Klons laufen noch — bitte manuell stoppen:\n  docker ps | grep ${COMPOSE_NAME}"
    fi
    success "Alter Stack gestoppt und Volumes gelöscht"
elif docker volume ls --format '{{.Name}}' | grep -q "^${COMPOSE_NAME}_"; then
    # Volumes existieren noch ohne docker-compose.yml (z.B. nach manuellem rm -rf)
    warn "Verwaiste Volumes gefunden — werden gelöscht ..."
    docker volume ls --format '{{.Name}}' \
        | grep "^${COMPOSE_NAME}_" \
        | xargs -r docker volume rm
    success "Verwaiste Volumes gelöscht"
fi

# ─── Zielstruktur anlegen ────────────────────────────────────────────────────
info "Erstelle Ausgabe-Verzeichnis: $OUTPUT_DIR"
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"/{app,docker/init,docker/apache,docker/php,docker/supervisor,storage_backup}

# ─── 1. Datenbank-Dump (komplette laufende DB inkl. Testdaten) ───────────────
info "Erstelle Datenbank-Dump (--single-transaction, kein Lock) ..."

MYSQL_AUTH=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME")
[[ -n "$DB_PASSWORD" ]] && MYSQL_AUTH+=("-p$DB_PASSWORD")

# Ohne CREATE DATABASE/USE: das mysql-init-Verzeichnis importiert automatisch
# in die per MYSQL_DATABASE angelegte DB.
# --no-tablespaces: vermeidet 'PROCESS privilege'-Fehler, wenn der DB-User
#   das globale PROCESS-Recht nicht hat (Standard bei dedizierten App-Usern).
mysqldump "${MYSQL_AUTH[@]}" \
    --single-transaction --quick --routines --triggers --events \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" > "$OUTPUT_DIR/docker/init/01_dump.sql" \
    || error "mysqldump fehlgeschlagen"

success "DB-Dump: $(du -sh "$OUTPUT_DIR/docker/init/01_dump.sql" | cut -f1)"

# ─── 2. Storage / Medien sichern (kommt nach dem Start ins Volume) ───────────
info "Sichere Storage-Dateien (Medien, generierte Dateien) ..."
if [[ -d "$APP_DIR/storage/app" ]]; then
    rsync -a "$APP_DIR/storage/app/" "$OUTPUT_DIR/storage_backup/"
    success "Storage gesichert: $(du -sh "$OUTPUT_DIR/storage_backup" | cut -f1)"
else
    warn "Kein storage/app gefunden — übersprungen"
fi

# ─── 3. Anwendungscode kopieren (Frontend ist bereits in public/ gebaut) ─────
info "Kopiere Anwendungscode (inkl. vendor/ und gebautem public/) ..."
rsync -a \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='storage/app' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='.env' \
    --exclude='*/node_modules' \
    "$APP_DIR/" "$OUTPUT_DIR/app/"
success "App-Code kopiert: $(du -sh "$OUTPUT_DIR/app" | cut -f1)"

# ─── 4. .env für den Container erzeugen (Service-Namen statt localhost) ──────
info "Erzeuge container-taugliche .env (DB→db, Redis→redis, Typesense→typesense) ..."
sed \
    -e 's#^APP_ENV=.*#APP_ENV=production#' \
    -e 's#^APP_DEBUG=.*#APP_DEBUG=false#' \
    -e "s#^APP_URL=.*#APP_URL=http://localhost:${HTTP_PORT}#" \
    -e 's#^DB_HOST=.*#DB_HOST=db#' \
    -e 's#^DB_PORT=.*#DB_PORT=3306#' \
    -e 's#^REDIS_HOST=.*#REDIS_HOST=redis#' \
    -e 's#^REDIS_PORT=.*#REDIS_PORT=6379#' \
    -e 's#^TYPESENSE_HOST=.*#TYPESENSE_HOST=typesense#' \
    -e 's#^TYPESENSE_PORT=.*#TYPESENSE_PORT=8108#' \
    -e 's#^TYPESENSE_PROTOCOL=.*#TYPESENSE_PROTOCOL=http#' \
    "$APP_DIR/.env" > "$OUTPUT_DIR/app/.env"

# SANCTUM-Domain für lokalen Zugriff ergänzen (idempotent)
if grep -q '^SANCTUM_STATEFUL_DOMAINS=' "$OUTPUT_DIR/app/.env"; then
    sed -i "s#^SANCTUM_STATEFUL_DOMAINS=.*#SANCTUM_STATEFUL_DOMAINS=localhost:${HTTP_PORT},localhost,127.0.0.1#" "$OUTPUT_DIR/app/.env"
fi

# Typesense-Einträge sicherstellen — sed ersetzt nur bestehende Zeilen,
# fehlende Einträge werden hier ergänzt.
ensure_env() {
    local key="$1" val="$2" file="$OUTPUT_DIR/app/.env"
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        sed -i "s#^${key}=.*#${key}=${val}#" "$file"
    else
        echo "${key}=${val}" >> "$file"
    fi
}
ensure_env TYPESENSE_HOST     typesense
ensure_env TYPESENSE_PORT     8108
ensure_env TYPESENSE_PROTOCOL http
ensure_env TYPESENSE_API_KEY  "${TYPESENSE_API_KEY}"

# Redis-Passwort synchronisieren: Original-Wert "null" durch echten Wert ersetzen
if [[ -n "$REDIS_PASSWORD" && "$REDIS_PASSWORD" != "null" ]]; then
    ensure_env REDIS_PASSWORD "${REDIS_PASSWORD}"
fi

success ".env für Container geschrieben (APP_KEY bleibt erhalten)"

# ─── 4b. Entrypoint-Skript (Config-Cache vor Start leeren) ──────────────────
cat > "$OUTPUT_DIR/docker/entrypoint.sh" << 'ENTRYPOINT'
#!/bin/sh
# Config-Cache leeren damit .env-Werte zur Laufzeit gelten (nicht Build-Zeit)
cd /var/www/html
php artisan config:clear --quiet 2>/dev/null || true
php artisan cache:clear  --quiet 2>/dev/null || true
exec "$@"
ENTRYPOINT

# ─── 5. Apache-VHost (DocumentRoot=public, AllowOverride All wie im Original) ─
cat > "$OUTPUT_DIR/docker/apache/000-default.conf" << 'APACHE'
<VirtualHost *:80>
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    LimitRequestBody 268435456

    ErrorLog  ${APACHE_LOG_DIR}/anypim-error.log
    CustomLog ${APACHE_LOG_DIR}/anypim-access.log combined
</VirtualHost>
APACHE

# ─── 6. PHP-Konfiguration ────────────────────────────────────────────────────
cat > "$OUTPUT_DIR/docker/php/anypim.ini" << 'PHPINI'
upload_max_filesize = 256M
post_max_size = 256M
max_execution_time = 300
memory_limit = 512M
date.timezone = Europe/Berlin
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
PHPINI

# ─── 7. Supervisor: Apache + Horizon + Scheduler (wie setup.sh, Schritt 10) ──
cat > "$OUTPUT_DIR/docker/supervisor/anypim.conf" << 'SUPERVISOR'
[supervisord]
nodaemon=true
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
user=root

[program:apache]
command=apache2-foreground
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:horizon]
command=php /var/www/html/artisan horizon
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/horizon.log
stopwaitsecs=3600

[program:scheduler]
command=php /var/www/html/artisan schedule:work
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/scheduler.log
SUPERVISOR

# ─── 8. Dockerfile (php:8.4 + Apache/mod_php, exakt der Original-Stack) ──────
info "Schreibe Dockerfile (php:8.4-apache + Extensions + Redis) ..."
cat > "$OUTPUT_DIR/Dockerfile" << 'DOCKERFILE'
FROM php:8.4-apache-bookworm

# System-Abhängigkeiten
RUN apt-get update && apt-get install -y --no-install-recommends \
        supervisor curl zip unzip git \
        libpng-dev libjpeg-dev libwebp-dev libzip-dev libonig-dev \
        libicu-dev libxml2-dev default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# PHP-Extensions (entspricht php8.4-{mysql,mbstring,xml,zip,gd,bcmath,intl,opcache})
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring zip gd exif pcntl bcmath intl xml opcache

# Redis-Extension (phpredis — REDIS_CLIENT=phpredis)
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: mod_rewrite + mod_headers, DocumentRoot=public
RUN a2enmod rewrite headers
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/anypim.ini          /usr/local/etc/php/conf.d/anypim.ini
COPY docker/supervisor/anypim.conf  /etc/supervisor/conf.d/anypim.conf

WORKDIR /var/www/html

# Bereits gebaute Anwendung (inkl. vendor/ und gebautem public/) übernehmen
COPY app/ /var/www/html/

# Autoloader für den Container optimieren (offline, ohne Netzwerk)
RUN composer dump-autoload --optimize --no-dev --no-scripts || true

# Storage-/Cache-Struktur sicherstellen + Rechte
RUN mkdir -p \
        storage/app/public storage/app/private \
        storage/framework/{cache,sessions,views,testing} \
        storage/logs bootstrap/cache /var/log/supervisor \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Config-Cache vor dem Start leeren — verhindert dass gecachte Werte
# aus der Build-Zeit (z.B. DB_HOST=127.0.0.1) zur Laufzeit gelten.
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/anypim.conf"]
DOCKERFILE
success "Dockerfile geschrieben"

# ─── 9. docker-compose.yml ───────────────────────────────────────────────────
info "Schreibe docker-compose.yml ..."

# Passwort über environment (REDIS_PASSWORD) übergeben, nicht als CLI-Argument —
# vermeidet Sonderzeichen/Leerzeichen im YAML-command-Wert.
REDIS_HAS_PASSWORD=false
if [[ -n "$REDIS_PASSWORD" && "$REDIS_PASSWORD" != "null" ]]; then
    REDIS_HAS_PASSWORD=true
fi

cat > "$OUTPUT_DIR/docker-compose.yml" << COMPOSE
services:

  app:
    build: .
    restart: unless-stopped
    ports:
      - "${HTTP_PORT}:80"
    volumes:
      - storage_data:/var/www/html/storage/app
      - ./app/.env:/var/www/html/.env:ro   # .env als Volume → Änderungen wirken ohne Rebuild
    depends_on:
      db:        { condition: service_healthy }
      redis:     { condition: service_healthy }
      typesense: { condition: service_started }
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://localhost/api/v1/health"]
      interval: 30s
      timeout: 5s
      retries: 5

  db:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: \${DB_DATABASE}
      MYSQL_USER: \${DB_USERNAME}
      MYSQL_PASSWORD: \${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: \${MYSQL_ROOT_PASSWORD}
    command: --default-authentication-plugin=caching_sha2_password
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/init:/docker-entrypoint-initdb.d:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-uroot", "-p\${MYSQL_ROOT_PASSWORD}"]
      interval: 10s
      timeout: 5s
      retries: 12
    ports:
      - "127.0.0.1:3307:3306"

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    # Passwort als Env-Variable, nie im command-String — sicher bei Sonderzeichen
    command: >-
      redis-server --appendonly yes
      ${REDIS_HAS_PASSWORD:+--requirepass \${REDIS_PASSWORD}}
    environment:
      REDIS_PASSWORD: \${REDIS_PASSWORD:-}
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      retries: 5

  typesense:
    image: typesense/typesense:27.1
    restart: unless-stopped
    command: '--data-dir /data --api-key=\${TYPESENSE_API_KEY} --enable-cors'
    volumes:
      - typesense_data:/data
    # Kein Host-Port-Mapping: die App erreicht Typesense intern über
    # 'typesense:8108'. Verhindert Konflikt mit einer nativen Typesense-
    # Installation auf demselben Host (Port 8108 bereits belegt).

volumes:
  db_data:
  redis_data:
  typesense_data:
  storage_data:
COMPOSE
success "docker-compose.yml geschrieben"

# ─── 10. .env für docker compose (Secrets/Variablen) ─────────────────────────
cat > "$OUTPUT_DIR/.env" << ENVFILE
# Variablen für docker-compose (NICHT die Laravel-.env — die liegt in app/.env)
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16 2>/dev/null || echo "rootsecret_change_me")
TYPESENSE_API_KEY=${TYPESENSE_API_KEY}
ENVFILE
chmod 600 "$OUTPUT_DIR/.env"

# ─── 11. start.sh — Ein-Klick-Start inkl. Daten-/Index-Wiederherstellung ────
cat > "$OUTPUT_DIR/start.sh" << 'STARTSH'
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "▶ 1/5  Image bauen ..."
docker compose build

echo "▶ 2/5  Container starten (DB-Import beim ersten Start kann 1-2 Min dauern) ..."
docker compose up -d

echo "▶ 3/5  Warte auf gesunde App (max. 5 Min) ..."
HEALTHY=false
for i in $(seq 1 60); do
    state=$(docker compose ps app --format '{{.Health}}' 2>/dev/null || echo "")
    if [[ "$state" == "healthy" ]]; then HEALTHY=true; break; fi
    printf '.'; sleep 5
done
echo ""
if [[ "$HEALTHY" != "true" ]]; then
    echo "FEHLER: App-Container nicht healthy nach 5 Min."
    echo "  Logs: docker compose logs app"
    docker compose logs --tail=30 app
    exit 1
fi

CID=$(docker compose ps -q app)
[[ -z "$CID" ]] && { echo "FEHLER: Container-ID leer — Container läuft nicht"; exit 1; }

echo "▶ 4/5  Storage (Medien) ins Volume kopieren ..."
if [ -d storage_backup ] && [ -n "$(ls -A storage_backup 2>/dev/null)" ]; then
    docker cp storage_backup/. "${CID}:/var/www/html/storage/app/"
    docker compose exec -T app chown -R www-data:www-data storage/app
fi
docker compose exec -T app php artisan storage:link || true

echo "▶ 5/5  Laravel optimieren + Typesense-Index aufbauen ..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan typesense:setup    || echo "  (typesense:setup übersprungen)"
docker compose exec -T app php artisan typesense:reindex  || \
docker compose exec -T app php artisan search:reindex --sync || echo "  (Reindex übersprungen)"

PORT=$(grep -E '^\s*-\s*"[0-9]+:80"' docker-compose.yml | head -1 | grep -oE '[0-9]+:80' | cut -d: -f1)
echo ""
echo "✔ anyPIM-Klon läuft:  http://localhost:${PORT:-8080}"
echo "   Logs   : docker compose logs -f app"
echo "   Shell  : docker compose exec app bash"
echo "   Stoppen: docker compose down        (Daten bleiben in Volumes)"
echo "   Reset  : docker compose down -v      (löscht ALLE Daten/Volumes)"
STARTSH
chmod +x "$OUTPUT_DIR/start.sh"

# ─── 11b. start.ps1 — Windows-Pendant (Docker Desktop / PowerShell) ──────────
# Nur ASCII im Heredoc: PowerShell liest die Datei ggf. als Windows-1252,
# UTF-8-Sonderzeichen (Umlaute, Em-Dash) fuehren zu Parse-Fehlern.
cat > "$OUTPUT_DIR/start.ps1" << 'STARTPS'
# anyPIM Docker-Klon - Start unter Windows (Docker Desktop + PowerShell)
$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

Write-Host "==> 1/5  Image bauen ..."
docker compose build

Write-Host "==> 2/5  Container starten (DB-Import beim ersten Start kann 1-2 Min dauern) ..."
docker compose up -d

Write-Host "==> 3/5  Warte auf gesunde App (max. 5 Min) ..."
$healthy = $false
for ($i = 0; $i -lt 60; $i++) {
    $state = (docker compose ps app --format '{{.Health}}') 2>$null
    if ($state -eq "healthy") { $healthy = $true; break }
    Start-Sleep -Seconds 5
    Write-Host "." -NoNewline
}
Write-Host ""
if (-not $healthy) {
    Write-Error "App-Container nicht healthy nach 5 Min. Logs: docker compose logs app"
    docker compose logs --tail 30 app
    exit 1
}

$CID = (docker compose ps -q app)
if (-not $CID) { Write-Error "Container-ID leer - Container laeuft nicht"; exit 1 }

Write-Host "==> 4/5  Storage (Medien) ins Volume kopieren ..."
if (Test-Path "storage_backup") {
    docker cp "storage_backup/." "${CID}:/var/www/html/storage/app/"
    docker compose exec -T app chown -R www-data:www-data storage/app
}
docker compose exec -T app php artisan storage:link

Write-Host "==> 5/5  Laravel optimieren + Typesense-Index aufbauen ..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan optimize
try { docker compose exec -T app php artisan typesense:setup }   catch { Write-Host "  (typesense:setup uebersprungen)" }
try { docker compose exec -T app php artisan typesense:reindex } catch { Write-Host "  (Reindex uebersprungen)" }

Write-Host ""
Write-Host "OK  anyPIM-Klon laeuft:  http://localhost:8080"
Write-Host "    Logs   : docker compose logs -f app"
Write-Host "    Stoppen: docker compose down"
STARTPS

# ─── 12. Kurz-README ─────────────────────────────────────────────────────────
cat > "$OUTPUT_DIR/README.md" << README
# anyPIM — Docker-Klon

Erstellt aus der laufenden Instanz: \`${APP_DIR}\`

## Starten
\`\`\`bash
cd ${OUTPUT_DIR}
bash start.sh        # Linux / macOS / Git Bash / WSL
\`\`\`
Unter **Windows** (Docker Desktop, PowerShell): \`.\\start.ps1\`

Danach erreichbar unter http://localhost:${HTTP_PORT}

## Auf einen anderen Rechner übertragen
Dieser Ordner ist eigenständig (Code + DB-Dump + Medien). Übertragen via:
\`\`\`bash
# Auf dem Quell-Server packen (oder direkt: docker_clone.sh --pack)
tar czf anypim-docker.tar.gz -C $(dirname ${OUTPUT_DIR}) $(basename ${OUTPUT_DIR})
# Auf dem Ziel entpacken und start.sh / start.ps1 ausführen
\`\`\`

## Enthaltene Services
| Service   | Image                  | Port (Host)        |
|-----------|------------------------|--------------------|
| app       | Apache + PHP 8.4 (Build)| ${HTTP_PORT} → 80  |
| db        | mysql:8.0              | 127.0.0.1:3307     |
| redis     | redis:7-alpine         | intern             |
| typesense | typesense:27.1         | 127.0.0.1:8108     |

## Daten
- **DB**        : \`docker/init/01_dump.sql\` (Auto-Import beim ersten Start)
- **Medien**    : \`storage_backup/\` (von start.sh ins Volume kopiert)
- **Suche**     : Typesense-Index wird via \`typesense:setup\`/\`reindex\` neu aufgebaut

## Image exportieren (Weitergabe ohne Registry)
\`\`\`bash
docker save \$(docker compose images -q app) | gzip > anypim-app.tar.gz
# Ziel:  docker load < anypim-app.tar.gz
\`\`\`
README

# ─── 13. Optional: Transfer-Archiv packen (--pack) ───────────────────────────
ARCHIVE=""
if [[ "$PACK" == true ]]; then
    ARCHIVE="${OUTPUT_DIR}.tar.gz"
    info "Packe Transfer-Archiv (für Übertragung z.B. nach Windows) ..."
    tar -czf "$ARCHIVE" -C "$(dirname "$OUTPUT_DIR")" "$(basename "$OUTPUT_DIR")" \
        || error "Archivierung fehlgeschlagen"
    success "Archiv: $(du -sh "$ARCHIVE" | cut -f1) → ${ARCHIVE}"
fi

# ─── Zusammenfassung ─────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  anyPIM Docker-Klon erstellt${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo -e "  Quelle  : ${CYAN}${APP_DIR}${NC}"
echo -e "  Ziel    : ${CYAN}${OUTPUT_DIR}${NC}"
echo -e "  DB-Dump : ${CYAN}$(du -sh "$OUTPUT_DIR/docker/init/01_dump.sql" | cut -f1)${NC}"
echo -e "  Storage : ${CYAN}$(du -sh "$OUTPUT_DIR/storage_backup" 2>/dev/null | cut -f1 || echo 0)${NC}"
echo -e "  App     : ${CYAN}$(du -sh "$OUTPUT_DIR/app" | cut -f1)${NC}"
[[ -n "$ARCHIVE" ]] && echo -e "  Archiv  : ${CYAN}$(du -sh "$ARCHIVE" | cut -f1) → ${ARCHIVE}${NC}"
echo ""
echo -e "  ${YELLOW}Nächster Schritt:${NC}"
echo -e "    cd ${OUTPUT_DIR} && bash start.sh"
echo -e "    → http://localhost:${HTTP_PORT}"
if [[ -n "$ARCHIVE" ]]; then
    echo ""
    echo -e "  ${YELLOW}Transfer (z.B. nach Windows):${NC}"
    echo -e "    ${ARCHIVE}  herunterladen, entpacken,"
    echo -e "    dann:  bash start.sh   (Git Bash/WSL)  oder  .\\start.ps1  (PowerShell)"
fi
echo ""
