#!/bin/bash
#
# Publixx PIM — Docs VHost Setup
#
# Adds the Apache Alias for the VitePress documentation to the active VHost.
#
# Usage:
#   sudo bash setup-docs-vhost.sh                          # Auto-detect VHost
#   sudo bash setup-docs-vhost.sh /etc/apache2/sites-available/mysite.conf
#

set -euo pipefail

APP_DIR="/var/www/publixx-pim"
DOCS_DIST="${APP_DIR}/docs/.vitepress/dist"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ─── Root check ──────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    error "This script must be run as root (use sudo)."
fi

# ─── Find VHost config ──────────────────────────────────────────────────────
if [ -n "${1:-}" ]; then
    VHOST_CONF="$1"
else
    # Auto-detect: find enabled VHost that references the app directory
    VHOST_CONF=$(grep -rl "$APP_DIR" /etc/apache2/sites-enabled/ 2>/dev/null | head -n 1 || true)
    if [ -z "$VHOST_CONF" ]; then
        # Fallback: check sites-available
        VHOST_CONF=$(grep -rl "$APP_DIR" /etc/apache2/sites-available/ 2>/dev/null | head -n 1 || true)
    fi
    if [ -z "$VHOST_CONF" ]; then
        error "Could not auto-detect VHost config. Pass the path as argument:\n  sudo bash setup-docs-vhost.sh /etc/apache2/sites-available/your-site.conf"
    fi
fi

if [ ! -f "$VHOST_CONF" ]; then
    error "VHost config not found: $VHOST_CONF"
fi

info "Using VHost config: $VHOST_CONF"

# ─── Check if already configured ────────────────────────────────────────────
if grep -q "Alias /docs" "$VHOST_CONF"; then
    warn "Docs alias already exists in $VHOST_CONF — nothing to do."
    exit 0
fi

# ─── Build docs if dist doesn't exist ───────────────────────────────────────
if [ ! -d "$DOCS_DIST" ]; then
    info "Docs not built yet. Building now..."
    cd "${APP_DIR}/docs"
    if [ ! -d "node_modules" ]; then
        sudo -u www-data npm ci --production=false
    fi
    sudo -u www-data npm run build
    cd "$APP_DIR"
    info "Docs built successfully."
fi

# ─── Create backup ──────────────────────────────────────────────────────────
BACKUP="${VHOST_CONF}.bak.$(date '+%Y%m%d%H%M%S')"
cp "$VHOST_CONF" "$BACKUP"
info "Backup created: $BACKUP"

# ─── Inject docs config before closing </VirtualHost> ───────────────────────
DOCS_BLOCK=$(cat <<'APACHE'

    # ─── Publixx PIM Documentation (VitePress) ──────────────────────
    Alias /docs /var/www/publixx-pim/docs/.vitepress/dist

    <Directory /var/www/publixx-pim/docs/.vitepress/dist>
        Options -Indexes
        AllowOverride None
        Require all granted
        FallbackResource /docs/index.html
    </Directory>
APACHE
)

# Insert before the last </VirtualHost> tag
sed -i "/<\/VirtualHost>/i\\${DOCS_BLOCK//$'\n'/\\n}" "$VHOST_CONF" 2>/dev/null || {
    # Fallback: use awk if sed fails with special characters
    awk -v block="$DOCS_BLOCK" '
        /<\/VirtualHost>/ && !done { print block; done=1 }
        { print }
    ' "$BACKUP" > "$VHOST_CONF"
}

info "Docs alias added to VHost config."

# ─── Test & reload Apache ───────────────────────────────────────────────────
info "Testing Apache configuration..."
if apachectl configtest 2>&1; then
    info "Config OK. Reloading Apache..."
    systemctl reload apache2
    info "Apache reloaded successfully."
    echo ""
    info "Documentation is now available at: https://your-domain/docs/"
    info "  German:  /docs/de/"
    info "  English: /docs/en/"
else
    error "Apache config test failed! Restoring backup..."
    cp "$BACKUP" "$VHOST_CONF"
    error "Backup restored. Please check your VHost config manually."
fi
