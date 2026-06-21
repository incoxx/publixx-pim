# anyPIM — Deployment Guide

Manual deployment guide for production servers. For automated installation, use `setup.sh` instead (see [install.md](install.md)).

Recommended server: **4 vCPU, 8 GB RAM, 160 GB SSD, Ubuntu 24.04 LTS**

---

## 1. Install System Packages

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.4
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.4 php8.4-cli php8.4-mysql php8.4-redis \
  php8.4-mbstring php8.4-xml php8.4-zip php8.4-gd php8.4-bcmath \
  php8.4-curl php8.4-intl php8.4-readline libapache2-mod-php8.4

# MySQL, Redis, Apache, Supervisor
sudo apt install -y mysql-server-8.0 redis-server apache2 supervisor \
  certbot python3-certbot-apache git unzip curl

# Node.js 22 LTS
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2. Set Up MySQL

```sql
sudo mysql
CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pim'@'localhost' IDENTIFIED BY '<SECURE_PASSWORD>';
GRANT ALL PRIVILEGES ON publixx_pim.* TO 'pim'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. Configure Redis

```bash
sudo nano /etc/redis/redis.conf
```

Set:
```
maxmemory 512mb
maxmemory-policy allkeys-lru
```

```bash
sudo systemctl restart redis-server
```

---

## 4. Deploy Application

```bash
# Create directory
sudo mkdir -p /var/www/publixx-pim
sudo chown www-data:www-data /var/www/publixx-pim

# Clone as www-data
sudo -u www-data git clone <REPO_URL> /var/www/publixx-pim
cd /var/www/publixx-pim

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Environment
sudo -u www-data cp .env.production.example .env
sudo -u www-data php artisan key:generate
```

Edit `.env` with your actual values:
```bash
sudo -u www-data nano .env
```
- `DB_USERNAME=pim`
- `DB_PASSWORD=<YOUR_PASSWORD>`
- `REDIS_PASSWORD=` (if set)
- Mail credentials (if needed)

```bash
# Migrate and seed database
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed

# Storage link
sudo -u www-data php artisan storage:link

# Optimize caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

---

## 5. Build Frontend

```bash
cd /var/www/publixx-pim/pim-frontend
sudo -u www-data npm ci --production=false
sudo -u www-data npm run build
cd ..

# Copy built assets to public directory
sudo -u www-data cp pim-frontend/dist/index.html public/spa.html
sudo -u www-data cp -r pim-frontend/dist/pim-assets public/
```

---

## 6. Configure Apache

```bash
sudo a2enmod rewrite headers ssl alias
sudo nano /etc/apache2/sites-available/publixx-pim.conf
```

```apache
<VirtualHost *:80>
    ServerName pim.example.com
    DocumentRoot /var/www/publixx-pim/public

    <Directory /var/www/publixx-pim/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"

    ErrorLog ${APACHE_LOG_DIR}/publixx-pim-error.log
    CustomLog ${APACHE_LOG_DIR}/publixx-pim-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite publixx-pim.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

---

## 7. SSL with Let's Encrypt

```bash
sudo certbot --apache -d pim.example.com
```

Verify auto-renewal:
```bash
sudo certbot renew --dry-run
```

---

## 8. Tune PHP

```bash
sudo nano /etc/php/8.4/apache2/php.ini
```

```ini
memory_limit = 512M
upload_max_filesize = 256M
post_max_size = 260M
max_execution_time = 300
```

```bash
sudo systemctl restart apache2
```

---

## 9. Supervisor for Horizon

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

## 10. Cron for Laravel Scheduler

```bash
sudo crontab -u www-data -e
```

Add:
```
* * * * * cd /var/www/publixx-pim && php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Firewall

```bash
sudo ufw allow 'Apache Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

---

## Deploying Updates

### Via Script (recommended)

```bash
cd /var/www/publixx-pim
sudo bash update.sh
```

See [update.md](update.md) for all options.

### Manual

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

- **Horizon Dashboard:** `https://pim.example.com/horizon`
- **Logs:** `storage/logs/laravel.log`, `storage/logs/horizon.log`
- **Redis:** `redis-cli INFO memory`
- **Queue Status:** `php artisan horizon:status`
- **Healthcheck:** `curl https://pim.example.com/api/v1/health`
