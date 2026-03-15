# anyPIM — Installation Guide

## Prerequisites

| Software | Version |
|---|---|
| Ubuntu / Debian | 22.04+ / 24.04 recommended |
| PHP | 8.3+ (extensions: mysql, redis, mbstring, xml, zip, gd, bcmath, curl, intl) |
| MySQL | 8.0+ |
| Redis | 6+ |
| Node.js | 20 LTS (with npm) |
| Composer | 2.x |
| Apache | 2.4 (with mod_rewrite, mod_headers, mod_alias) |
| Supervisor | For Laravel Horizon (queue worker) |
| Git | For deployment and updates |

## Installation with setup.sh

The interactive setup script installs everything automatically:

```bash
# Clone repository
git clone <repository-url> /var/www/publixx-pim
cd /var/www/publixx-pim

# Start setup (as root)
sudo bash setup.sh
```

### Interactive Prompts

The script asks for the following information:

1. **Server domain** — e.g., `pim.example.com`
2. **Web path** — empty for root (`/`) or e.g., `/web` for subdirectory
3. **HTTPS** — yes/no (with optional Let's Encrypt)
4. **Port** — default 80/443 or custom
5. **MySQL credentials** — host, port, database name, user, password
6. **Redis host** — default 127.0.0.1
7. **Admin account** — email and password for the first user

### What setup.sh Does (10 Steps)

| Step | Description |
|---|---|
| 1/10 | Install system packages (PHP, MySQL client, Redis, Node.js, Composer) |
| 2/10 | Check and install PHP extensions |
| 3/10 | Composer install (PHP dependencies) |
| 4/10 | Generate .env file + APP_KEY |
| 5/10 | Create database and run migrations |
| 6/10 | Create admin user |
| 7/10 | Load demo data (optional) |
| 8/10 | Configure Supervisor/Horizon |
| 9/10 | Build frontend (npm ci + npm run build) |
| 10/10 | Configure Apache + set permissions |

## Deployment Modes

### Root Mode (PIM = only app)

- Dedicated Apache VHost on port 80 (+ 443 with SSL)
- Optional Let's Encrypt setup
- `APP_URL=https://example.com`

### Subdirectory Mode (PIM under /path)

- Apache Alias is injected into the existing VHost
- SSL is handled by the existing VHost
- `APP_URL=https://example.com/web`
- Frontend is automatically built with the correct base path

```bash
# Example: PIM under /web
#   Domain: example.com
#   Web path: /web
#   → APP_URL=https://example.com/web
#   → Frontend base path: /web/
#   → API: https://example.com/web/api/v1/...
```

## SSL / Let's Encrypt

### New Certificate (Root Mode)

The setup automatically offers Let's Encrypt when HTTPS is selected.

### Existing Certificate

If a Let's Encrypt certificate already exists for the domain, the script detects this and asks whether to keep or renew it.

### Subdirectory Mode

No SSL setup needed — the existing VHost handles SSL.

## Post-Installation

### Healthcheck

```bash
# Local (checks all services)
bash healthcheck.sh

# URL only
bash healthcheck.sh --url-only

# Or directly in the browser
curl https://example.com/api/v1/health
```

### Load Demo Data

```bash
cd /var/www/publixx-pim
php artisan db:seed --class=DemoAttributeSeeder
php artisan db:seed --class=DemoHierarchySeeder
php artisan db:seed --class=DemoProductSeeder
```

### Log In

Open the APP_URL in your browser. Log in with the admin credentials created during installation.

## Troubleshooting

### "Permission denied" Errors

```bash
sudo chown -R www-data:www-data /var/www/publixx-pim
sudo chmod -R 775 /var/www/publixx-pim/storage
sudo chmod -R 775 /var/www/publixx-pim/bootstrap/cache
```

### Check Apache Configuration

```bash
sudo apache2ctl configtest
sudo systemctl status apache2
```

### Laravel Logs

```bash
# Via API
curl https://example.com/api/v1/debug/logs

# Or directly
tail -100 /var/www/publixx-pim/storage/logs/laravel.log
```

### Horizon / Queue

```bash
sudo supervisorctl status horizon
sudo supervisorctl restart horizon
```
