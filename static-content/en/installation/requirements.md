---
title: Requirements
---

# Requirements

Before starting the installation of anyPIM, ensure your environment meets the following requirements. The specifications apply to production use — for local development, the same software requirements apply, but hardware requirements are lower.

## Software Requirements

### PHP 8.3+

anyPIM requires PHP version 8.3 or higher. The following PHP extensions must be enabled:

| Extension | Purpose |
|---|---|
| `pdo_mysql` | Database connection to MySQL |
| `redis` | Connection to Redis cache and queue broker |
| `mbstring` | Correct processing of UTF-8 strings |
| `xml` | Processing XML data and configurations |
| `zip` | Processing ZIP archives (media upload, export) |
| `gd` | Image processing (thumbnails, media preview) |
| `bcmath` | Precise mathematical calculations (prices, units) |
| `curl` | HTTP communication with external services |
| `intl` | Internationalization, sorting, and formatting |

Check installed extensions with:

```bash
php -m | grep -iE "(mysql|redis|mbstring|xml|zip|gd|bcmath|curl|intl)"
```

Missing extensions can be installed on Ubuntu as follows:

```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-gd php8.3-bcmath \
    php8.3-curl php8.3-intl
```

### MySQL 8.0+

MySQL 8.0 or higher is required as the database. The system uses the following MySQL-specific features:

- **InnoDB** — as storage engine for transaction safety and foreign keys
- **JSON columns** — for flexible metadata and configurations
- **Common Table Expressions (CTEs)** — for recursive hierarchy queries
- **FULLTEXT indexes** — for full-text search in product data

::: warning Note
MariaDB is not officially supported, as JSON functions and CTE implementations differ in some areas.
:::

### Redis 6+

Redis serves as cache backend and queue broker for Laravel Horizon. Version 6 or higher is required.

### Nginx or Apache

**Nginx** is recommended as the web server. Apache with `mod_rewrite` is also supported but is not the primary target platform.

### Node.js 20+ and npm

Node.js version 20 (LTS) or higher is required for building the Vue.js frontend:

```bash
node --version   # >= v20.0.0
npm --version    # >= 10.0.0
```

### Composer 2.x

The PHP package manager Composer version 2.x is required.

### Supervisor

Supervisor is needed to run Laravel Horizon (queue worker) in production.

## Hardware Requirements

### Minimum (Production)

| Resource | Requirement |
|---|---|
| **vCPU** | 4 cores |
| **RAM** | 8 GB |
| **Storage** | 160 GB SSD |
| **Network** | 400 Mbit/s |

### Recommended (Production)

For larger product catalogs (100,000+ products), extensive media libraries, or high concurrent access:

| Resource | Requirement |
|---|---|
| **vCPU** | 8 cores |
| **RAM** | 16 GB |
| **Storage** | 500 GB SSD (NVMe) |
| **Network** | 1 Gbit/s |

### Local Development

| Resource | Requirement |
|---|---|
| **CPU** | 2 cores |
| **RAM** | 4 GB |
| **Storage** | 20 GB free space |

## Operating System

anyPIM is optimized and tested for **Ubuntu 24.04 LTS**. Other Linux distributions (Debian 12, Rocky Linux 9) can also be used but are not officially tested.

## Next Step

Once all requirements are met, proceed with the [Quick Start](./quickstart).
