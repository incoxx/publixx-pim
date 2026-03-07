<p align="center">
  <img src="pim-frontend/public/logo-dark.svg" alt="anyPIM Logo" width="200" />
</p>

<h1 align="center">anyPIM</h1>

<p align="center">
  <strong>Open-Source Product Information Management</strong><br/>
  Centralize, enrich, and distribute your product data — all in one place.
</p>

<p align="center">
  <a href="#features">Features</a> &nbsp;&middot;&nbsp;
  <a href="#quick-start">Quick Start</a> &nbsp;&middot;&nbsp;
  <a href="#tech-stack">Tech Stack</a> &nbsp;&middot;&nbsp;
  <a href="#documentation">Documentation</a> &nbsp;&middot;&nbsp;
  <a href="#api">API</a> &nbsp;&middot;&nbsp;
  <a href="#license">License</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-8892BF?style=flat-square&logo=php" alt="PHP 8.4" />
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/Vue.js-3-4FC08D?style=flat-square&logo=vuedotjs" alt="Vue 3" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-3-06B6D4?style=flat-square&logo=tailwindcss" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/License-AGPL--3.0-blue?style=flat-square" alt="AGPL-3.0" />
</p>

---

## Why anyPIM?

Managing product data across spreadsheets, ERPs, and shop systems is painful. **anyPIM** gives you a single source of truth for all your product information — from technical attributes and media assets to pricing and multi-language content.

Built for teams that need to manage **hundreds to tens of thousands of products** with complex attribute structures, hierarchies, and multi-channel export requirements.

---

## Features

### Core

- **Products & Variants** — Master data management with configurable attributes, variant generation, and attribute inheritance across hierarchies
- **Flexible Attribute System** — 9 data types (String, Number, Float, Date, Flag, Selection, Dictionary, Composite, RichText), units of measurement, value lists, and attribute grouping
- **Hierarchies** — Master and output hierarchies with materialized path, drag-and-drop sorting, and attribute inheritance across all levels
- **Media Management** — Upload, thumbnails, asset catalog with media-specific attributes and automatic image processing
- **Product Versioning** — Version history with scheduling (publish dates) and one-click rollback

### Search & Query

- **PQL (Product Query Language)** — Powerful, flexible query language for complex product searches across all attributes
- **Full-Text Search** — Instant search across product names, SKUs, EANs, and attribute values
- **Quick Lookup** — Column-level inline filtering for rapid data exploration
- **Advanced Filters** — Filter by any attribute, product type, status, or custom criteria

### Data Management

- **Import / Export** — Excel import with 14-tab structure, JSON export, and Publixx PXF template integration
- **Bulk Operations** — Select multiple products or attributes and edit properties in bulk
- **Prices & Relations** — Price types, product relationships with relation-specific attributes
- **Attribute Views** — Define custom attribute views for different use cases and departments

### Administration

- **Roles & Permissions** — Fine-grained access control with 5 default roles (Admin, Data Steward, Product Manager, Viewer, Export Manager)
- **User Management** — Full user administration with Sanctum-based authentication
- **Audit Trail** — Track changes across the system

### Frontend

- **Modern SPA** — Vue 3 single-page application with responsive design
- **Dark Mode** — Full dark mode support with automatic system preference detection
- **Internationalization** — Multi-language UI (German, English) with extensible i18n
- **Keyboard Shortcuts** — Power-user friendly with keyboard navigation

---

## Quick Start

Get a fully working anyPIM instance in under 10 minutes on a fresh Ubuntu 24.04 server.

### 1. Clone

```bash
git clone https://github.com/incoxx/publixx-pim.git /var/www/publixx-pim
cd /var/www/publixx-pim
```

### 2. Install

```bash
sudo bash setup.sh
```

The interactive setup script handles everything:
- PHP 8.4, Apache, MySQL 8, Redis, Node.js 20
- Database creation, migrations, and demo data seeding
- Frontend build (Vue 3 + Vite)
- Supervisor for Laravel Horizon (queue processing)
- Cron for Laravel Scheduler
- Optional: Let's Encrypt SSL
- Optional: Subdirectory deployment (e.g. `https://example.com/pim`)

### 3. Verify

```bash
bash healthcheck.sh
```

### 4. Log In

Open your configured URL and sign in:

| Email | Password |
|---|---|
| `admin@example.com` | `password` |

> **Important:** Change default passwords after first login.

---

## Updating

Pull the latest changes and rebuild with a single command:

```bash
sudo bash update.sh
```

The update script handles the full lifecycle automatically:

1. Activate maintenance mode
2. Pull latest changes from GitHub
3. Install PHP dependencies (Composer)
4. Run database migrations
5. Rebuild the frontend (Vue 3 + Vite)
6. Recreate Laravel caches
7. Restart services and fix permissions
8. Run healthcheck and deactivate maintenance mode

**Options:**

```bash
sudo bash update.sh --branch=develop     # Use a different branch
sudo bash update.sh --skip-frontend      # Skip frontend rebuild
sudo bash update.sh --skip-composer      # Skip Composer install
sudo bash update.sh --seed               # Re-run database seeders
sudo bash update.sh --force              # Skip confirmation prompt
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.4 / Laravel 11 |
| **Frontend** | Vue 3, Vite, Tailwind CSS |
| **Database** | MySQL 8+ |
| **Cache & Queue** | Redis |
| **Queue Worker** | Laravel Horizon + Supervisor |
| **Web Server** | Apache 2.4 |
| **Auth** | Laravel Sanctum (token + SPA cookie) |

---

## Project Structure

```
anyPIM/
├── app/
│   ├── Http/Controllers/     API controllers (V1)
│   ├── Models/               48 Eloquent models
│   └── Services/             Business logic (Inheritance, PQL, Export, Versioning)
├── pim-frontend/             Vue 3 SPA (standalone npm project)
│   ├── src/components/       116 Vue components
│   ├── src/stores/           Pinia state management
│   └── src/views/            Page-level views
├── database/
│   ├── migrations/           63 migration files
│   └── seeders/              Demo data (products, attributes, hierarchies)
├── routes/api.php            188 API routes (/api/v1/*)
├── static-content/           VitePress documentation site
├── setup.sh                  One-command server setup
├── update.sh                 One-command update & rebuild
└── healthcheck.sh            Service health verification
```

---

## Deployment Modes

### Root Mode

anyPIM is the only application on the domain. Apache VHost serves on port 80/443.

```
https://pim.example.com → /var/www/publixx-pim/public
```

### Subdirectory Mode

anyPIM runs under a path within an existing site. Apache Alias is injected into the existing VHost. SSL is handled by the parent VHost.

```
https://example.com/pim → /var/www/publixx-pim/public
```

Both modes are configured automatically by `setup.sh`.

---

## API

All endpoints are available under `/api/v1/` with Laravel Sanctum authentication (Bearer Token).

**188 RESTful endpoints** covering products, attributes, hierarchies, media, prices, relations, imports, exports, and more.

```bash
# Authenticate
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Fetch products with includes
curl https://your-domain.com/api/v1/products?include=attributeValues,media \
  -H "Authorization: Bearer <token>"

# Search with PQL
curl -X POST https://your-domain.com/api/v1/products/search \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"pql":"price > 100 AND category = \"Electronics\""}'

# Health check (no auth required)
curl https://your-domain.com/api/v1/health
```

See [API.md](API.md) for the full endpoint reference.

---

## Documentation

Full documentation is available as a VitePress site in both German and English:

| | German | English |
|---|---|---|
| **Online** | [smartentities.de/web/help/de](https://smartentities.de/web/help/de/) | [smartentities.de/web/help/en](https://smartentities.de/web/help/en/) |

### Guides

| Topic | Description |
|---|---|
| [Quick Start](https://smartentities.de/web/help/en/installation/quickstart) | Get anyPIM running in 10 minutes |
| [User Guide](https://smartentities.de/web/help/en/usage/) | Full user manual for all modules |
| [Architecture](https://smartentities.de/web/help/en/architecture/) | EAV data model, services, inheritance engine |
| [API Reference](https://smartentities.de/web/help/en/api/) | 188 REST endpoints with examples |
| [Import](https://smartentities.de/web/help/en/import/) | Excel import with 14-tab structure |
| [Export](https://smartentities.de/web/help/en/export/) | JSON export and Publixx PXF integration |
| [FAQ](https://smartentities.de/web/help/en/faq/) | Frequently asked questions |

### Local Documentation

| File | Description |
|---|---|
| [INSTALL.md](INSTALL.md) | Detailed installation guide (`setup.sh`) |
| [UPDATE.md](UPDATE.md) | Update procedures (`update.sh`) |
| [API.md](API.md) | Complete REST API reference (188 endpoints) |
| [LICENSE](LICENSE) | AGPL-3.0 license text |

---

## Scripts

| Script | Purpose | Usage |
|---|---|---|
| `setup.sh` | Full server setup (interactive) | `sudo bash setup.sh` |
| `update.sh` | Pull, migrate, build, restart | `sudo bash update.sh` |
| `healthcheck.sh` | Verify all services are running | `bash healthcheck.sh` |

---

## Requirements

| Component | Minimum |
|---|---|
| OS | Ubuntu 24.04 LTS (recommended) |
| PHP | 8.4+ |
| MySQL | 8.0+ |
| Redis | 6+ |
| Node.js | 20 LTS |
| RAM | 2 GB (4 GB recommended) |
| Disk | 2 GB free |

> `setup.sh` installs all dependencies automatically on a fresh Ubuntu 24.04 server.

---

## License

**AGPL-3.0-only** — see [LICENSE](LICENSE) for the full text.

This means you can freely use, modify, and distribute anyPIM, but any modifications to the source code must also be made available under the AGPL-3.0 license when the software is deployed as a network service.

---

<p align="center">
  Built with care for product data teams everywhere.
</p>
