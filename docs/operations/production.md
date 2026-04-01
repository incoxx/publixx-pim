# anyPIM — Production Operations

Complete guide for installation, updates, and operations on a production server.

---

## Overview

anyPIM provides two scripts for the complete server lifecycle:

| Script | Purpose | Usage |
|---|---|---|
| `setup.sh` | Initial installation (one-time) | `sudo bash setup.sh` |
| `update.sh` | Apply updates (recurring) | `sudo bash update.sh` |

Both scripts run interactively but can also be automated (`--force`).

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Ubuntu 24.04 LTS Server                                │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐              │
│  │  Apache   │  │  MySQL   │  │  Redis   │              │
│  │  :80/:443 │  │  :3306   │  │  :6379   │              │
│  └─────┬─────┘  └────┬─────┘  └────┬─────┘              │
│        │              │             │                    │
│  ┌─────┴──────────────┴─────────────┴──────┐             │
│  │         Laravel (PHP 8.4)               │             │
│  │         /var/www/publixx-pim            │             │
│  └─────────────────┬───────────────────────┘             │
│                    │                                     │
│  ┌─────────────────┴───────────────────────┐             │
│  │  Supervisor                             │             │
│  │  └── Horizon (Queue Worker)             │             │
│  │      ├── indexing   (Search index)      │             │
│  │      ├── cache      (Cache invalidation)│             │
│  │      ├── warmup     (Cache warmup)      │             │
│  │      └── default    (Import/Export/etc.) │             │
│  └─────────────────────────────────────────┘             │
│                                                         │
│  ┌─────────────────────────────────────────┐             │
│  │  Cron (www-data)                        │             │
│  │  * * * * * php artisan schedule:run     │             │
│  └─────────────────────────────────────────┘             │
└─────────────────────────────────────────────────────────┘
```

---

## 1. Initial Installation with setup.sh

### Prerequisites

- Fresh **Ubuntu 24.04 LTS** (or 22.04)
- **Root access** (sudo)
- Minimum 2 GB RAM, 10 GB storage
- Recommended: 4 vCPU, 8 GB RAM, 160 GB SSD

### Execution

```bash
# Clone repository
git clone <repository-url> /tmp/publixx-pim
cd /tmp/publixx-pim

# Start setup
sudo bash setup.sh
```

### Interactive Prompts

| Prompt | Example | Default |
|---|---|---|
| Domain / IP | `pim.example.com` | — (required) |
| Apache port | `80` | 80 |
| SSL (Let's Encrypt) | `y/N` | No |
| Web path | `/web` or empty | `/` (root) |
| MySQL database name | `publixx_pim` | `publixx_pim` |
| MySQL username | `pim` | `pim` |
| MySQL password | `*****` | — (required) |
| Installation path | `/var/www/publixx-pim` | `/var/www/publixx-pim` |
| Create extra admin | `y/N` | No |

### What setup.sh Installs and Configures

```
Step  1/10  System update (apt update/upgrade)
Step  2/10  PHP 8.4 + extensions (mysql, redis, mbstring, xml, zip, gd, bcmath, curl, intl)
Step  3/10  Apache + modules (rewrite, headers, ssl, alias)
Step  4/10  MySQL 8 (create database + user)
Step  5/10  Redis (maxmemory 512mb, allkeys-lru)
Step  6/10  Node.js 20 LTS
Step  7/10  Composer 2
Step  8/10  Laravel setup (.env, Composer install, migrations, seeder, storage link)
Step  9/10  Build frontend (npm ci + npm run build → public/)
Step 10/10  Apache VHost, Supervisor/Horizon, Cron, permissions, firewall
```

### Services Running After Installation

| Service | Management | Autostart |
|---|---|---|
| **Apache** | `systemctl` | Yes (systemd) |
| **MySQL** | `systemctl` | Yes (systemd) |
| **Redis** | `systemctl` | Yes (systemd) |
| **Supervisor** | `systemctl` | Yes (systemd) |
| **Horizon** (queue worker) | `supervisorctl` | Yes (`autostart=true`) |
| **Laravel Scheduler** | Cron (`www-data`) | Yes (`* * * * *`) |

---

## 2. Updates with update.sh

### Execution

```bash
cd /var/www/publixx-pim
sudo bash update.sh
```

### Options

```bash
sudo bash update.sh [options]
```

| Option | Description |
|---|---|
| `--branch=NAME` | Use a different branch instead of `main` |
| `--skip-frontend` | Skip frontend build (backend-only changes) |
| `--skip-docs` | Skip documentation site rebuild |
| `--skip-tms` | Skip TMS setup |
| `--skip-composer` | Skip Composer install |
| `--seed` | Run seeders after migrations |
| `--force` | No confirmation, execute immediately |

### Examples

```bash
# Standard update
sudo bash update.sh

# Quick backend update
sudo bash update.sh --skip-frontend

# Test a feature branch
sudo bash update.sh --branch=feature/new-import

# Fully automated (CI/CD)
sudo bash update.sh --force

# With new test data
sudo bash update.sh --seed
```

### Process (10 Steps)

```
Step  1/10  Activate maintenance mode (artisan down)
Step  2/10  Git pull from branch (shows changes)
Step  3/10  Composer install (--no-dev, optimized autoloader)
Step  4/10  Database migrations (artisan migrate --force)
Step  5/10  Frontend build (npm ci + build, subdirectory-aware)
Step  6/10  Documentation build (VitePress)
Step  7/10  TMS setup (if applicable)
Step  8/10  Recreate Laravel caches (config, route, view, event)
Step  9/10  Permissions + Horizon/queue worker + Apache restart
Step 10/10  Deactivate maintenance mode + healthcheck
```

### Error Handling

- On any error, **maintenance mode is automatically deactivated** (trap)
- A **healthcheck** runs at the end against `/api/v1/health`
- If Supervisor/Horizon is unavailable, the script starts a **fallback queue worker** directly

---

## 3. Queue System (Horizon)

### Queues and Their Tasks

| Queue | Purpose | Priority | Max Workers |
|---|---|---|---|
| `indexing` | Update search index (product-critical) | High | 4 |
| `cache` | Cache invalidation after changes | Medium | 2 |
| `default` | Import, export, general jobs | Normal | 4 |
| `warmup` | Cache warmup after imports | Low | 2 |

### Supervisor Configuration

Automatically created by `setup.sh` at `/etc/supervisor/conf.d/horizon.conf`:

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

### Horizon Configuration

Queue worker settings for production are in `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-indexing' => [
            'maxProcesses' => 4,    // Up to 4 workers for indexing
            'minProcesses' => 1,    // Minimum 1 worker
        ],
        'supervisor-cache' => [
            'maxProcesses' => 2,
        ],
        'supervisor-warmup' => [
            'maxProcesses' => 2,
        ],
        'supervisor-default' => [
            'maxProcesses' => 4,
        ],
    ],
],
```

### Managing Horizon

```bash
# Check status
sudo supervisorctl status horizon

# Restart (after code changes)
sudo supervisorctl restart horizon

# Stop
sudo supervisorctl stop horizon

# Horizon dashboard in browser
# https://example.com/horizon
# (Requires login as authenticated user)

# Queue status via CLI
php artisan horizon:status

# Show all running jobs
php artisan queue:monitor indexing,default,cache,warmup
```

### Fallback Without Supervisor

If Supervisor is not installed (e.g., development environment), `update.sh` automatically starts a simple queue worker:

```bash
# Started automatically — or manually:
nohup php artisan queue:work --queue=indexing,default --sleep=3 --tries=3 \
    >> storage/logs/queue-worker.log 2>&1 &
```

> **Note:** The nohup worker does not survive a server restart. Always use Supervisor for production.

---

## 4. Laravel Scheduler (Cron)

The scheduler runs recurring tasks (cache cleanup, scheduled exports, etc.).

Automatically set up by `setup.sh`:

```
* * * * * cd /var/www/publixx-pim && php artisan schedule:run >> /dev/null 2>&1
```

### Verify

```bash
# Show www-data cron jobs
sudo crontab -u www-data -l

# Run scheduler manually (for testing)
cd /var/www/publixx-pim
php artisan schedule:run
```

---

## 5. Monitoring and Logs

### Log Files

| File | Content |
|---|---|
| `storage/logs/laravel.log` | Application logs (errors, warnings) |
| `storage/logs/horizon.log` | Horizon/queue worker logs |
| `storage/logs/queue-worker.log` | Fallback worker logs (only without Supervisor) |
| `/var/log/apache2/publixx-pim-error.log` | Apache errors |
| `/var/log/apache2/publixx-pim-access.log` | Apache access logs |

### Healthcheck

```bash
# Via script (checks all services locally)
bash healthcheck.sh

# Via URL
curl -s https://example.com/api/v1/health

# Check individual services
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status redis-server
sudo systemctl status supervisor
sudo supervisorctl status horizon
```

### Horizon Dashboard

Accessible in the browser at `https://example.com/horizon` (login required).

Shows:
- Active/pending/failed jobs
- Queue throughput and wait times
- Worker utilization
- Job details and error messages

---

## 6. Subdirectory Mode

PIM can run under a subdirectory (e.g., `https://example.com/web`):

```bash
# setup.sh asks: Web path (e.g., /web or empty for root)
# In .env:
APP_URL=https://example.com/web
```

### Automatic Adjustments

- **Apache:** Alias configuration instead of dedicated VHost (`/etc/apache2/conf-available/publixx-pim.conf`)
- **Frontend build:** `VITE_BASE_PATH=/web/` and `VITE_API_BASE_URL=/web/api/v1` are set automatically
- **Session cookie:** `SESSION_COOKIE_PATH=/web`
- **update.sh:** Detects the mode automatically from `.env`

---

## 7. SSL / HTTPS

### Automatic (setup.sh)

```bash
# setup.sh asks:
# Set up SSL with Let's Encrypt? [y/N]
# Email for Let's Encrypt: admin@example.com
```

### Manual Setup

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Set up certificate
sudo certbot --apache -d pim.example.com

# Verify auto-renewal
sudo certbot renew --dry-run
```

---

## 8. Troubleshooting

### Permission Errors

```bash
sudo chown -R www-data:www-data /var/www/publixx-pim
sudo chmod -R 775 /var/www/publixx-pim/storage
sudo chmod -R 775 /var/www/publixx-pim/bootstrap/cache
```

### Horizon Not Running

```bash
# Check status
sudo supervisorctl status horizon

# If FATAL:
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

# Check logs
tail -50 /var/www/publixx-pim/storage/logs/horizon.log
```

### Search Index Empty (product names missing)

The search index is populated asynchronously via the `indexing` queue. If product names are missing in the catalog:

```bash
# 1. Check if Horizon/queue worker is running
sudo supervisorctl status horizon

# 2. Check queue length
php artisan queue:monitor indexing

# 3. Manually rebuild search index for all products
php artisan tinker --execute="
    \App\Models\Product::where('status', 'active')
        ->pluck('id')
        ->each(fn(\$id) => \App\Jobs\UpdateSearchIndex::dispatch(\$id));
    echo 'Jobs dispatched.';
"
```

### Apache Configuration Error

```bash
sudo apache2ctl configtest
sudo systemctl status apache2
tail -20 /var/log/apache2/publixx-pim-error.log
```

### Maintenance Mode Stuck

```bash
cd /var/www/publixx-pim
php artisan up

# If that doesn't work:
rm storage/framework/down
```

### Redis Connection Failed

```bash
# Check status
sudo systemctl status redis-server

# Test connection
redis-cli ping   # Expected: PONG

# Check memory
redis-cli INFO memory
```

---

## 9. Backup

### Database

```bash
# Create backup
mysqldump -u pim -p publixx_pim > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore backup
mysql -u pim -p publixx_pim < backup_20250305_120000.sql
```

### Media Files

```bash
# Back up uploaded files
tar czf media_backup_$(date +%Y%m%d).tar.gz storage/app/public/media/
```

### Full Backup

```bash
# Database + media + .env
mysqldump -u pim -p publixx_pim > /tmp/db_backup.sql
tar czf /tmp/pim_backup_$(date +%Y%m%d).tar.gz \
    /tmp/db_backup.sql \
    /var/www/publixx-pim/.env \
    /var/www/publixx-pim/storage/app/public/media/
rm /tmp/db_backup.sql
```

---

## 10. Summary: Typical Operations

### Initial Installation (one-time)

```bash
git clone <repo> /tmp/publixx-pim
cd /tmp/publixx-pim
sudo bash setup.sh
# → Answer interactive prompts
# → Done: PIM is running with all services
```

### Regular Updates

```bash
cd /var/www/publixx-pim
sudo bash update.sh
# → Shows changes, asks for confirmation
# → Maintenance mode → Update → Restart → Healthcheck
```

### Quick Backend Update

```bash
sudo bash update.sh --skip-frontend
```

### Automated Update (CI/CD)

```bash
sudo bash update.sh --force
```

### Check Status

```bash
sudo supervisorctl status horizon    # Queue worker
curl -s https://example.com/api/v1/health  # Healthcheck
tail -20 storage/logs/laravel.log    # Application logs
```
