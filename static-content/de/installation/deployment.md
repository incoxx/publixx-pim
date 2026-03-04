---
title: Deployment
---

# Deployment

Diese Anleitung beschreibt das produktive Deployment des Publixx PIM auf einem Ubuntu-Server. Sie umfasst die vollständige Server-Einrichtung, die Konfiguration aller Dienste sowie das automatisierte Deployment mit dem mitgelieferten Deploy-Skript.

::: tip Voraussetzung
Stellen Sie sicher, dass Ihr Server die in den [Voraussetzungen](./voraussetzungen) beschriebenen Hardware- und Software-Anforderungen erfüllt.
:::

## Server-Grundeinrichtung

### Betriebssystem

Das Publixx PIM ist für **Ubuntu 24.04 LTS** optimiert. Aktualisieren Sie zunächst das System:

```bash
sudo apt update && sudo apt upgrade -y
```

### Benutzer einrichten

Erstellen Sie einen dedizierten Benutzer für die Anwendung:

```bash
sudo adduser pim
sudo usermod -aG www-data pim
```

### Grundlegende Pakete installieren

```bash
sudo apt install -y git curl unzip software-properties-common \
    apt-transport-https ca-certificates
```

## PHP-FPM installieren und konfigurieren

### Installation

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-curl php8.3-intl
```

### PHP-FPM Tuning

Passen Sie die Pool-Konfiguration an die verfügbaren Ressourcen an. Bearbeiten Sie `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data

; Process Manager - für 8 GB RAM optimiert
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000

; Timeouts
request_terminate_timeout = 300
```

Passen Sie die `php.ini` für den Produktivbetrieb an (`/etc/php/8.3/fpm/php.ini`):

```ini
; Produktions-Einstellungen
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 300
max_input_vars = 5000

; OPcache für Produktionsumgebung
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
```

::: warning Hinweis
`opcache.validate_timestamps = 0` bedeutet, dass Dateiänderungen nicht automatisch erkannt werden. Nach jedem Deployment muss der PHP-FPM-Dienst neu gestartet oder der OPcache manuell geleert werden. Das Deploy-Skript übernimmt dies automatisch.
:::

Starten Sie PHP-FPM neu:

```bash
sudo systemctl restart php8.3-fpm
```

## MySQL einrichten

### Installation und Absicherung

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

### Datenbank und Benutzer anlegen

```sql
CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pim_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT ALL PRIVILEGES ON publixx_pim.* TO 'pim_user'@'localhost';
FLUSH PRIVILEGES;
```

### MySQL-Konfiguration optimieren

Bearbeiten Sie `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size    = 512M
innodb_flush_log_at_trx_commit = 2
max_connections         = 200
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
sql_mode                = STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

```bash
sudo systemctl restart mysql
```

## Redis einrichten

```bash
sudo apt install -y redis-server
```

Bearbeiten Sie `/etc/redis/redis.conf`:

```conf
maxmemory 512mb
maxmemory-policy allkeys-lru
supervised systemd
```

```bash
sudo systemctl enable redis-server
sudo systemctl restart redis-server
```

## Nginx-Konfiguration

### Installation

```bash
sudo apt install -y nginx
```

### SSL-Zertifikat mit Let's Encrypt

Installieren Sie Certbot und erstellen Sie ein SSL-Zertifikat:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pim.ihre-domain.de
```

Certbot richtet automatisch die SSL-Konfiguration ein und einen Cronjob für die Zertifikatserneuerung.

### Nginx Virtual Host

Erstellen Sie die Konfiguration unter `/etc/nginx/sites-available/publixx-pim`:

```nginx
server {
    listen 80;
    server_name pim.ihre-domain.de;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name pim.ihre-domain.de;

    # SSL-Konfiguration (von Certbot verwaltet)
    ssl_certificate     /etc/letsencrypt/live/pim.ihre-domain.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pim.ihre-domain.de/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Dokumentenroot
    root /var/www/publixx-pim/public;
    index index.php index.html;

    # Logging
    access_log /var/log/nginx/pim-access.log;
    error_log  /var/log/nginx/pim-error.log;

    # Maximale Upload-Grösse
    client_max_body_size 100M;

    # Gzip-Komprimierung
    gzip on;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1000;

    # API-Routen und PHP-Verarbeitung
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Statische Dateien cachen
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Sicherheitsheader
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Zugriff auf sensible Dateien blockieren
    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Aktivieren Sie die Konfiguration:

```bash
sudo ln -s /etc/nginx/sites-available/publixx-pim /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Supervisor für Horizon

Laravel Horizon verarbeitet die Queue-Jobs (Import, Export, Benachrichtigungen). Supervisor stellt sicher, dass Horizon automatisch startet und bei Abstürzen neu gestartet wird.

Erstellen Sie die Konfiguration unter `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/publixx-pim/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/publixx-pim/storage/logs/horizon.log
stopwaitsecs=3600
```

Aktualisieren Sie Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

Überprüfen Sie den Status:

```bash
sudo supervisorctl status horizon
```

## Cron für Laravel Scheduler

Der Laravel Scheduler führt wiederkehrende Aufgaben aus (Cache-Bereinigung, geplante Exporte). Fügen Sie folgenden Eintrag in die Crontab des `www-data`-Benutzers ein:

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/publixx-pim && php artisan schedule:run >> /dev/null 2>&1
```

## Firewall (UFW)

Konfigurieren Sie die Firewall, um nur die benötigten Ports zu öffnen:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Überprüfen Sie den Status:

```bash
sudo ufw status verbose
```

Die erwartete Ausgabe zeigt offene Ports für SSH (22), HTTP (80) und HTTPS (443).

## Anwendung deployen

### Erstmaliges Deployment

Klonen Sie das Repository und richten Sie die Anwendung ein:

```bash
cd /var/www
sudo git clone git@github.com:publixx/publixx-pim.git
sudo chown -R www-data:www-data publixx-pim
cd publixx-pim

# Backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# .env anpassen (DB, Redis, APP_URL, etc.)

# Datenbank
php artisan migrate --force
php artisan db:seed --force

# Frontend
cd pim-frontend
npm install
npm run build
cp -r dist/* ../public/
cd ..

# Optimierungen
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link

# Berechtigungen
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Deploy-Skript

Für nachfolgende Deployments steht das Skript `deploy.sh` im Projektverzeichnis zur Verfügung. Es automatisiert den gesamten Deployment-Prozess.

**Vollständiges Deployment (Backend + Frontend):**

```bash
./deploy.sh
```

**Nur Backend deployen (schnell, ohne Frontend-Build):**

```bash
./deploy.sh --quick
```

**Nur Backend deployen (mit Composer und Migrationen):**

```bash
./deploy.sh --backend
```

**Nur Frontend deployen:**

```bash
./deploy.sh --frontend
```

Das Deploy-Skript führt je nach Modus folgende Schritte aus:

| Schritt | `--quick` | `--backend` | `--frontend` | Vollständig |
|---|:---:|:---:|:---:|:---:|
| `git pull` | x | x | x | x |
| `composer install` | -- | x | -- | x |
| `php artisan migrate` | -- | x | -- | x |
| `npm install` | -- | -- | x | x |
| `npm run build` | -- | -- | x | x |
| Frontend kopieren | -- | -- | x | x |
| Config/Route/View Cache | x | x | -- | x |
| Horizon Neustart | x | x | -- | x |
| PHP-FPM Reload | x | x | -- | x |

## Monitoring

### Horizon-Dashboard

Das Horizon-Dashboard bietet eine Übersicht über den Zustand des Queue-Systems:

- **URL**: `https://pim.ihre-domain.de/horizon`
- **Zugang**: Nur für authentifizierte Admin-Benutzer
- **Inhalte**: Aktive Jobs, fehlgeschlagene Jobs, Durchsatz, Wartezeiten

### Log-Dateien

Die wichtigsten Log-Dateien befinden sich unter:

| Datei | Inhalt |
|---|---|
| `storage/logs/laravel.log` | Anwendungs-Logs (Fehler, Warnungen, Info) |
| `storage/logs/horizon.log` | Horizon Queue-Worker-Logs |
| `/var/log/nginx/pim-access.log` | Nginx Zugriffs-Logs |
| `/var/log/nginx/pim-error.log` | Nginx Fehler-Logs |
| `/var/log/mysql/error.log` | MySQL Fehler-Logs |

### Redis-Monitoring

Überwachen Sie den Redis-Speicherverbrauch regelmässig:

```bash
redis-cli info memory
```

Wichtige Kennzahlen:

- `used_memory_human` -- Aktueller Speicherverbrauch
- `maxmemory_human` -- Konfiguriertes Limit
- `evicted_keys` -- Anzahl entfernter Schlüssel (sollte niedrig bleiben)

### Systemressourcen

Überwachen Sie CPU, RAM und Festplattenspeicher:

```bash
# CPU und RAM
htop

# Festplattenspeicher
df -h /var/www/publixx-pim

# MySQL-Datenbankgrösse
mysql -u pim_user -p -e "SELECT table_schema AS 'Datenbank',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Grösse (MB)'
    FROM information_schema.tables
    WHERE table_schema = 'publixx_pim'
    GROUP BY table_schema;"
```

## Backup-Strategie

### Datenbank-Backup

Erstellen Sie ein tägliches Backup der MySQL-Datenbank:

```bash
mysqldump -u pim_user -p publixx_pim | gzip > /backup/pim_$(date +%Y%m%d_%H%M%S).sql.gz
```

Automatisieren Sie dies per Cron:

```cron
0 2 * * * mysqldump -u pim_user -p'passwort' publixx_pim | gzip > /backup/pim_$(date +\%Y\%m\%d).sql.gz
```

### Medien-Backup

Die hochgeladenen Medien befinden sich unter `storage/app/public/`. Sichern Sie dieses Verzeichnis zusätzlich:

```bash
rsync -avz /var/www/publixx-pim/storage/app/public/ /backup/media/
```

### Umgebungskonfiguration

Sichern Sie die `.env`-Datei separat, da sie sensible Zugangsdaten enthält:

```bash
cp /var/www/publixx-pim/.env /backup/env_$(date +%Y%m%d).env
```

## Sicherheitsempfehlungen

- **SSH-Zugang**: Verwenden Sie ausschliesslich Key-basierte Authentifizierung und deaktivieren Sie Passwort-Login.
- **Firewall**: Halten Sie die UFW-Regeln minimal und öffnen Sie nur die tatsächlich benötigten Ports.
- **Updates**: Installieren Sie Sicherheitsupdates zeitnah mit `sudo apt update && sudo apt upgrade`.
- **`.env`-Datei**: Stellen Sie sicher, dass die `.env`-Datei nicht öffentlich zugänglich ist (Nginx-Konfiguration blockiert Dotfiles bereits).
- **Horizon-Dashboard**: Beschränken Sie den Zugriff auf Admin-Benutzer.

## Nächster Schritt

Nach erfolgreichem Deployment können Sie das System über die [Bedienung](/de/bedienung/) kennenlernen oder direkt mit dem [Import](/de/import/) von Produktdaten beginnen.
