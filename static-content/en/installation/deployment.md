---
title: Deployment
---

# Deployment

This guide describes the production deployment of Publixx PIM on an Ubuntu server. It covers the complete server setup, configuration of all services, and automated deployment with the included deploy script.

::: tip Prerequisite
Ensure your server meets the hardware and software requirements described in the [Requirements](./requirements).
:::

## Server Setup

### Operating System

Publixx PIM is optimized for **Ubuntu 24.04 LTS**. First, update the system:

```bash
sudo apt update && sudo apt upgrade -y
```

### Create Application User

```bash
sudo adduser pim
sudo usermod -aG www-data pim
```

### Install Base Packages

```bash
sudo apt install -y git curl unzip software-properties-common \
    apt-transport-https ca-certificates
```

## PHP-FPM Installation and Configuration

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-curl php8.3-intl
```

## MySQL Setup

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

```sql
CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pim_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON publixx_pim.* TO 'pim_user'@'localhost';
FLUSH PRIVILEGES;
```

## Redis Setup

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl restart redis-server
```

## Web Server Configuration

Configure Nginx or Apache as your reverse proxy. See the full [German deployment guide](/de/installation/deployment) for detailed Nginx and Apache configurations.

## Supervisor for Horizon

Create the configuration at `/etc/supervisor/conf.d/horizon.conf`:

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

## Deploy Script

For subsequent deployments, use the `deploy.sh` script in the project directory:

| Command | Description |
|---|---|
| `./deploy.sh` | Full deployment (backend + frontend) |
| `./deploy.sh --quick` | Backend only (fast, no frontend build) |
| `./deploy.sh --backend` | Backend with Composer and migrations |
| `./deploy.sh --frontend` | Frontend only |

## Next Step

After successful deployment, explore the system through the [Usage Guide](/en/usage/) or start with [Importing](/en/import/) product data.
