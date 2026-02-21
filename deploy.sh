#!/bin/bash
#
# Publixx PIM — Deploy & Restart Script
#
# Usage:
#   sudo bash deploy.sh              # Full deploy (pull + backend + frontend + restart)
#   sudo bash deploy.sh --quick      # Quick deploy (pull + cache + restart, no composer/migrate)
#   sudo bash deploy.sh --backend    # Backend only (skip frontend build)
#   sudo bash deploy.sh --frontend   # Frontend only (skip backend steps)
#

set -euo pipefail

# ─── Configuration ────────────────────────────────────────────────────────────
APP_DIR="/var/www/publixx-pim"
APP_USER="www-data"
BRANCH="main"
FRONTEND_DIR="${APP_DIR}/pim-frontend"
DOCS_DIR="${APP_DIR}/docs"
PHP_FPM_SERVICE="php8.4-fpm"

# ─── Colors ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# ─── Helper functions ─────────────────────────────────────────────────────────
info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ─── Parse arguments ─────────────────────────────────────────────────────────
MODE="full"
for arg in "$@"; do
    case $arg in
        --quick)    MODE="quick" ;;
        --backend)  MODE="backend" ;;
        --frontend) MODE="frontend" ;;
        --help|-h)
            echo "Usage: sudo bash deploy.sh [--quick|--backend|--frontend]"
            echo ""
            echo "  (default)    Full deploy: git pull, composer, migrate, frontend + docs build, cache, restart"
            echo "  --quick      Quick: git pull, cache clear/rebuild, restart services"
            echo "  --backend    Backend only: git pull, composer, migrate, cache, restart (no frontend/docs)"
            echo "  --frontend   Frontend only: git pull, npm install, build frontend + docs"
            echo "  --help       Show this help"
            exit 0
            ;;
        *) error "Unknown argument: $arg. Use --help for usage." ;;
    esac
done

# ─── Checks ───────────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    error "This script must be run as root (use sudo)."
fi

if [ ! -d "$APP_DIR" ]; then
    error "Application directory $APP_DIR does not exist."
fi

info "Starting deployment (mode: ${MODE})..."
info "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# ─── Maintenance Mode ────────────────────────────────────────────────────────
if [ "$MODE" != "frontend" ]; then
    info "Enabling maintenance mode..."
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" down --retry=30 || true
fi

# ─── Git Pull ─────────────────────────────────────────────────────────────────
info "Pulling latest changes from ${BRANCH}..."
cd "$APP_DIR"
sudo -u "$APP_USER" git pull origin "$BRANCH"
echo ""

# ─── Backend Steps ────────────────────────────────────────────────────────────
if [ "$MODE" = "full" ] || [ "$MODE" = "backend" ]; then
    info "Installing Composer dependencies..."
    sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --working-dir="$APP_DIR"
    echo ""

    info "Running database migrations..."
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" migrate --force
    echo ""

    info "Ensuring storage symlink exists..."
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" storage:link 2>/dev/null || true
    echo ""
fi

# ─── Frontend Build ──────────────────────────────────────────────────────────
if [ "$MODE" = "full" ] || [ "$MODE" = "frontend" ]; then
    if [ -d "$FRONTEND_DIR" ] && command -v npm &> /dev/null; then
        info "Installing frontend dependencies..."
        cd "$FRONTEND_DIR"
        sudo -u "$APP_USER" npm ci --production=false
        echo ""

        info "Building frontend..."
        sudo -u "$APP_USER" npm run build
        echo ""
        cd "$APP_DIR"

        # Build documentation
        if [ -d "$DOCS_DIR" ] && [ -f "${DOCS_DIR}/package.json" ]; then
            info "Building documentation..."
            cd "$DOCS_DIR"
            sudo -u "$APP_USER" npm ci --production=false
            sudo -u "$APP_USER" npm run build
            echo ""
            cd "$APP_DIR"
        fi
    else
        if [ ! -d "$FRONTEND_DIR" ]; then
            warn "Frontend directory not found, skipping frontend build."
        elif ! command -v npm &> /dev/null; then
            warn "npm not found, skipping frontend build. Install Node.js to enable frontend builds."
        fi
    fi
fi

# ─── Cache ────────────────────────────────────────────────────────────────────
if [ "$MODE" != "frontend" ]; then
    info "Clearing and rebuilding caches..."
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" config:cache
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" route:cache
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" view:cache
    echo ""
fi

# ─── Restart Services ────────────────────────────────────────────────────────
if [ "$MODE" != "frontend" ]; then
    info "Restarting Horizon (queue worker)..."
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" horizon:terminate 2>/dev/null || true
    supervisorctl restart horizon
    echo ""

    info "Restarting PHP-FPM..."
    systemctl restart "$PHP_FPM_SERVICE"
    echo ""

    # ─── Disable Maintenance Mode ────────────────────────────────────────────
    info "Disabling maintenance mode..."
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" up
    echo ""
fi

# ─── Done ─────────────────────────────────────────────────────────────────────
info "Deployment complete! ($(date '+%Y-%m-%d %H:%M:%S'))"
echo ""

# ─── Status Check ────────────────────────────────────────────────────────────
if [ "$MODE" != "frontend" ]; then
    info "Service status:"
    echo -n "  PHP-FPM:  "
    systemctl is-active "$PHP_FPM_SERVICE" 2>/dev/null || echo "unknown"
    echo -n "  Nginx:    "
    systemctl is-active nginx 2>/dev/null || echo "unknown"
    echo -n "  Redis:    "
    systemctl is-active redis-server 2>/dev/null || echo "unknown"
    echo -n "  Horizon:  "
    sudo -u "$APP_USER" php "${APP_DIR}/artisan" horizon:status 2>/dev/null || echo "unknown"
    echo ""
fi
