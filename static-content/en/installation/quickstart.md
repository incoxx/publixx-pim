---
title: Quick Start
---

# Quick Start

anyPIM offers two installation paths: the **automated setup script** (recommended) for server installations and **manual installation** for development environments.

## Automated Installation with setup.sh (Recommended)

The included `setup.sh` installs **everything automatically** on a fresh Ubuntu 24.04 LTS server: PHP 8.4, MySQL 8, Redis, Apache, Node.js, Composer, frontend build, Supervisor, and SSL.

### Prerequisites

- Fresh **Ubuntu 24.04 LTS** server with root access
- A domain name or IP address

### Installation

```bash
git clone https://github.com/incoxx/publixx-pim.git /var/www/publixx-pim
cd /var/www/publixx-pim
sudo bash setup.sh
```

The script guides you interactively through the configuration:

| Prompt | Description | Default |
|---|---|---|
| **Domain/IP** | Server hostname or IP address | — (required) |
| **Apache Port** | Web server port | `80` |
| **SSL with Let's Encrypt** | Automatic HTTPS certificate | No |
| **Web Path** | Subdirectory deployment (e.g., `/web`, `/pim`) | empty (root) |
| **MySQL Database** | Database name | `publixx_pim` |
| **MySQL User** | Database user | `pim` |
| **MySQL Password** | Database password | — (required) |
| **Installation Path** | Application directory | `/var/www/publixx-pim` |
| **Admin User** | Optional additional admin account | Optional |

### What setup.sh Installs

The script runs through **10 steps** in approximately 5–10 minutes:

1. **System Update** — `apt-get upgrade`
2. **PHP 8.4** — with all extensions (mysql, redis, mbstring, xml, zip, gd, bcmath, curl, intl, opcache)
3. **Apache 2.4** — with mod_rewrite, mod_headers, mod_ssl, mod_php
4. **MySQL 8.0** — database and user are created automatically
5. **Redis** — with 512 MB memory limit and allkeys-lru policy
6. **Node.js 20 LTS** — for the frontend build
7. **Composer** — PHP package manager
8. **Application** — .env, Composer install, migrations, demo data, storage link
9. **Frontend Build** — npm ci, Vite build, copy to public/
10. **Web Server & Services** — Apache VHost, Supervisor for Horizon, cron, firewall

### After Installation

```bash
# Check status of all services
bash healthcheck.sh

# Only test HTTP endpoint
bash healthcheck.sh --url-only

# JSON output (for monitoring)
bash healthcheck.sh --json
```

::: info Default Credentials
After installation, the following demo accounts are available:
- `admin@publixx.com` / `password`
- `admin@example.com` / `password`

If you created a custom admin account during setup, use those credentials. Change the passwords after your first login.
:::

### Deployment Modes

The setup script supports two modes:

**Root Mode** (default): The PIM is the only application on the domain. Apache VHost on port 80/443.

**Subdirectory Mode**: The PIM runs under a path (e.g., `https://example.com/web`). Apache alias is added to the existing VHost. Ideal when other applications already run on the domain.

---

## Updates with update.sh

For subsequent updates, use the `update.sh` script. It pulls the latest code from GitHub, runs migrations, rebuilds the frontend, and restarts services.

```bash
sudo bash update.sh
```

### Options

| Option | Description |
|---|---|
| `--branch=NAME` | Use a different branch (default: main) |
| `--skip-frontend` | Skip frontend build (faster update) |
| `--skip-composer` | Skip Composer install |
| `--seed` | Run database seeders after migrations |
| `--force` | Skip confirmation prompt |

### Update Process

The script runs through these steps:

1. **Enable Maintenance Mode** — Laravel `down` with 60-second retry
2. **Git Pull** — Fetch latest changes from branch
3. **Composer Install** — Update PHP dependencies
4. **Database Migrations** — Run pending schema changes
5. **Frontend Build** — npm ci + Vite build + copy to public/
6. **Refresh Caches** — Config, route, view, and event cache
7. **Restart Services** — Permissions, Horizon restart, Apache reload
8. **Healthcheck** — Test API endpoint + disable maintenance mode

::: tip Quick Update
For backend-only changes (no frontend changes), use:
```bash
sudo bash update.sh --skip-frontend
```
This significantly reduces update time.
:::

### Error Handling

If an error occurs during the update, maintenance mode is automatically disabled so the application remains accessible.

---

## Healthcheck with healthcheck.sh

The healthcheck script verifies all critical services and dependencies:

```bash
bash healthcheck.sh
```

### Checks

| Check | Description |
|---|---|
| **Laravel** | Artisan file present |
| **.env** | Configuration file present |
| **PHP** | Version and status |
| **Database** | MySQL connection (SELECT 1) |
| **Redis** | Cache connection (PING) |
| **Apache** | Service status |
| **Supervisor/Horizon** | Queue worker running |
| **Disk Space** | Warning at < 1 GB, critical at < 200 MB |
| **Storage** | Write permissions on storage/ |
| **HTTP Endpoint** | API healthcheck (`/api/v1/health`) |

### Options

| Option | Description |
|---|---|
| `--url-only` | Only check HTTP endpoint |
| `--json` | JSON output (for monitoring systems) |
| `--quiet` | Exit code only (0 = healthy, 1 = error) |

---

## Manual Installation (Development)

For local development environments, you can install manually. Make sure all dependencies described in the [Requirements](./requirements) are installed.

### 1. Clone the Repository

```bash
git clone git@github.com:incoxx/publixx-pim.git
cd publixx-pim
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Frontend Dependencies

```bash
cd pim-frontend
npm install
cd ..
```

### 4. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Open the `.env` file and configure at least the following values:

```dotenv
# Application
APP_NAME="anyPIM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=publixx_pim
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

Create the database:

```bash
mysql -u root -p -e "CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Set Up the Database

```bash
php artisan migrate
php artisan db:seed
```

### 6. Create Storage Link

```bash
php artisan storage:link
```

### 7. Start Development Servers

Start backend and frontend in two separate terminals:

**Terminal 1 — Laravel Backend:**

```bash
php artisan serve
```

**Terminal 2 — Vite Frontend (Development Mode):**

```bash
cd pim-frontend
npm run dev
```

**Terminal 3 — Queue Worker (Optional):**

```bash
php artisan horizon
```

The PIM is now available at `http://localhost:5173`.

### 8. Verify Installation

| Check | Expected Result |
|---|---|
| Login page is displayed | Frontend and API connection work |
| Login with admin credentials | Authentication and database work |
| Dashboard loads | SPA routing and API endpoints work |
| Horizon dashboard accessible | Redis and queue system work |

## Common Issues

### CORS Errors in Browser

Make sure `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` in the `.env` file are correctly set to the frontend URL.

### Database Connection Fails

```bash
php artisan db:monitor
```

### Redis Connection Fails

```bash
redis-cli ping
# Expected response: PONG
```

### Storage Permission Issues

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

## Next Step

For production deployment, read the [Deployment](./deployment) guide.
