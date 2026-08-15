# anyPIM — Update Guide

## Quick Update

```bash
cd /var/www/publixx-pim
sudo bash update.sh
```

The script handles everything automatically: maintenance mode, git pull, Composer, migrations, frontend build, documentation build, caches, service restart, healthcheck.

## Options

```bash
sudo bash update.sh [options]
```

| Option | Description |
|---|---|
| `--branch=NAME` | Use a different branch instead of `main` |
| `--skip-frontend` | Skip frontend build (saves time for backend-only changes) |
| `--skip-docs` | Skip documentation site rebuild (VitePress) |
| `--skip-tms` | Skip TMS (Translation Memory) setup. Not recommended — the TMS is set up on every run by default. |
| `--skip-composer` | Skip Composer install |
| `--seed` | Run seeders after migrations |
| `--force` | No confirmation before update |

### Examples

```bash
# Standard update from main
sudo bash update.sh

# Quick backend update (no frontend build)
sudo bash update.sh --skip-frontend

# Test a feature branch
sudo bash update.sh --branch=feature/new-import

# Fully automated (e.g., in cron/CI)
sudo bash update.sh --force

# After schema changes with new test data
sudo bash update.sh --seed
```

## What update.sh Does (10 Steps)

| Step | Description |
|---|---|
| 1/10 | Activate maintenance mode (`artisan down`) |
| 2/10 | Git pull from the specified branch |
| 3/10 | Composer install (--no-dev, optimized autoloader) |
| 4/10 | Database migrations (`artisan migrate --force`) |
| 5/10 | Frontend build (npm ci + build, subdirectory-aware) |
| 6/10 | Documentation build (VitePress) |
| 7/10 | TMS setup (Translation Memory: composer, migrations, Apache vhost, supervisor worker — always runs unless --skip-tms) |
| 8/10 | Recreate Laravel caches (config, route, view, event) |
| 9/10 | File permissions + Horizon/queue worker + Apache restart |
| 10/10 | Deactivate maintenance mode + healthcheck |

## Subdirectory Mode

The update script automatically detects subdirectory mode from the `.env`:

```
APP_URL=https://example.com/web
```

During frontend build, `VITE_BASE_PATH` and `VITE_API_BASE_URL` are set automatically.

## Error Handling

- On any error, maintenance mode is **automatically deactivated** (trap)
- A **healthcheck** runs at the end against `/api/v1/health`
- Changes since the last state are displayed

## Manual Update (without script)

If you prefer not to use the script:

```bash
cd /var/www/publixx-pim

# 1. Maintenance mode
php artisan down --retry=60

# 2. Pull code
git pull origin main

# 3. PHP dependencies
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# 4. Migrations
php artisan migrate --force

# 5. Build frontend
cd pim-frontend
# For subdirectory: export VITE_BASE_PATH="/web/"
npm ci --production=false
npm run build
cd ..
rm -rf public/pim-assets
cp pim-frontend/dist/index.html public/spa.html
cp -r pim-frontend/dist/pim-assets public/

# 6. Build documentation
cd static-content
npm ci
npm run build
cd ..

# 7. Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Permissions
chown -R www-data:www-data .
chmod -R 775 storage bootstrap/cache

# 9. Services
supervisorctl restart horizon
systemctl reload apache2
php artisan up
```

## Healthcheck After Update

```bash
# Automatic (integrated in script)
# Or manually:
bash healthcheck.sh

# URL check only
curl -s https://example.com/api/v1/health | python3 -m json.tool
```
