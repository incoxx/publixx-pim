# Publixx PIM — Deployment-Anleitung

Server: **IONOS Cloud VPS M** (4 vCPU, 8 GB RAM, 160 GB SSD, Ubuntu 24.04 LTS)
Domain: `publixx-pim.incoxx.com`

---

## 1. Server-Pakete installieren

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.4
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis \
  php8.4-mbstring php8.4-xml php8.4-zip php8.4-gd php8.4-bcmath \
  php8.4-curl php8.4-intl php8.4-readline

# MySQL, Redis, Nginx, Supervisor
sudo apt install -y mysql-server-8.0 redis-server nginx supervisor \
  certbot python3-certbot-nginx git unzip curl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2. MySQL einrichten

```sql
sudo mysql
CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pim'@'localhost' IDENTIFIED BY '<SICHERES_PASSWORT>';
GRANT ALL PRIVILEGES ON publixx_pim.* TO 'pim'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. Redis konfigurieren

```bash
sudo nano /etc/redis/redis.conf
```

Setzen:
```
maxmemory 512mb
maxmemory-policy allkeys-lru
```

```bash
sudo systemctl restart redis-server
```

---

## 4. Anwendung deployen

```bash
# Verzeichnis anlegen
sudo mkdir -p /var/www/publixx-pim
sudo chown www-data:www-data /var/www/publixx-pim

# Als www-data klonen
sudo -u www-data git clone <REPO_URL> /var/www/publixx-pim
cd /var/www/publixx-pim

# Dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Environment
sudo -u www-data cp .env.production.example .env
sudo -u www-data php artisan key:generate
```

Jetzt `.env` editieren und die echten Werte eintragen:
```bash
sudo -u www-data nano .env
```
- `DB_USERNAME=pim`
- `DB_PASSWORD=<DEIN_PASSWORT>`
- `REDIS_PASSWORD=` (falls gesetzt)
- Mail-Credentials (falls noetig)

```bash
# Datenbank migrieren + seeden
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed

# Storage-Link
sudo -u www-data php artisan storage:link

# Caches optimieren
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

---

## 5. Nginx konfigurieren

```bash
sudo nano /etc/nginx/sites-available/publixx-pim
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name publixx-pim.incoxx.com;
    root /var/www/publixx-pim/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/publixx-pim /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6. SSL mit Let's Encrypt

```bash
sudo certbot --nginx -d publixx-pim.incoxx.com
```

---

## 7. PHP-FPM tunen

```bash
sudo nano /etc/php/8.4/fpm/pool.d/www.conf
```

```ini
pm = dynamic
pm.max_children = 16
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 120
```

```bash
sudo systemctl restart php8.4-fpm
```

---

## 8. Supervisor fuer Horizon

```bash
sudo nano /etc/supervisor/conf.d/horizon.conf
```

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

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

---

## 9. Cron fuer Laravel Scheduler

```bash
sudo crontab -u www-data -e
```

Hinzufuegen:
```
* * * * * cd /var/www/publixx-pim && php artisan schedule:run >> /dev/null 2>&1
```

---

## 10. Firewall

```bash
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

---

## Updates deployen

```bash
cd /var/www/publixx-pim
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan horizon:terminate
sudo supervisorctl restart horizon
```

---

## Monitoring

- **Horizon Dashboard:** `https://publixx-pim.incoxx.com/horizon`
- **Logs:** `storage/logs/laravel.log`, `storage/logs/horizon.log`
- **Redis:** `redis-cli INFO memory`
- **Queue-Status:** `php artisan horizon:status`
