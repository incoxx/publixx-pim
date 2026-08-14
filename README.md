**⚠️ anyPIM is under active development and is not yet stable. Use at your own risk. Code, API, interfaces, and other components may change at any time without prior notice.**

---

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
  <img src="https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss" alt="Tailwind CSS 4" />
  <img src="https://img.shields.io/badge/License-GPL--3.0-blue?style=flat-square" alt="GPL-3.0" />
</p>

---

## Why anyPIM?

Managing product data across spreadsheets, ERPs, and shop systems is painful. **anyPIM** gives you a single source of truth for all your product information — from technical attributes and media assets to pricing and multi-language content.

Built for teams that need to manage **hundreds to tens of thousands of products** with complex attribute structures, hierarchies, and multi-channel export requirements.

---

## Features

### Core

- **Products & Variants** — Master data management with configurable attributes, variant generation, and attribute inheritance across hierarchies
- **Flexible Attribute System** — 21 data types (String, Number, Float, Date, Flag, Selection, MultiSelection, Dictionary, Composite, RichText, Hyperlink, ImageLink, PdfLink, VideoLink, DelimitedValue, JsonArtefact, Textarea, HierarchyNodeReference, ProductReference, SimpleSelect, SimpleMultiSelect), units of measurement, value lists, and attribute grouping
- **Hierarchies** — Master and output hierarchies with materialized path, drag-and-drop sorting, and attribute inheritance across all levels
- **Media Management** — Upload, thumbnails, asset catalog with media-specific attributes and automatic image processing
- **Product Versioning** — Version history with scheduling (publish dates) and one-click rollback
- **Reference Profiles & Conformance** — Define required attribute/media rules per product type and run conformance checks with a data-quality report

### Search & Query

- **PQL (Product Query Language)** — Powerful, flexible query language for complex product searches across all attributes
- **Full-Text Search** — Instant search across product names, SKUs, EANs, and attribute values
- **Quick Lookup** — Column-level inline filtering for rapid data exploration
- **Advanced Filters** — Filter by any attribute, product type, status, or custom criteria

### Data Management

- **Import / Export** — Excel import with 17-tab structure, JSON export/import, BMEcat, and Publixx integration
- **Bulk Operations** — Spreadsheet-style bulk editor and bulk update for mass data maintenance
- **Prices & Relations** — Price types, price regions, product relationships with relation-specific attributes
- **Attribute Views** — Define custom attribute views for different use cases and departments
- **Workflow & Calendar** — Task management with assignments and a planning calendar for scheduled product actions
- **Classification Mapping** — Global attribute mapping (source → target) with conditional rules for external classification standards (e.g. ETIM), so products can be maintained once and exported into multiple classifications
- **Export Jobs** — Scheduled, reusable export configurations with SFTP and webhook delivery

### Content (CMS)

- **Content Pages & Sections** — Configurable page types and section types for building product-related content pages
- **Product Widgets** — Reusable, product-data-driven widgets embeddable in content sections
- **Sitemap / Navigation** — Manage the public site's navigation tree
- **Website Preview** — Live preview of published content
- **CMS Integration** — TYPO3 connector for pushing content into an existing CMS

### E-Commerce

- **Cart, Address & Payment Types** — Configure checkout building blocks (cart types, address types, payment types)
- **Orders** — Order management view for orders placed through connected shop systems

### Publish

- **Reports** — Reporting module across products and data quality
- **PDF & Catalog Templates** — Design PDF and catalog output templates
- **Catalog Demo / Embed** — Embeddable, faceted product catalog for previewing and sharing
- **Social Video** — Generate short social-media product videos from product data and media

### Integrations & Connectors

- **anyPIM Sync** — Connect and synchronize with other anyPIM instances
- **Shopify & Shopware** — Native e-commerce connectors for products, categories, media, and metafields/properties
- **DeepL** — Machine translation for multi-language attribute content
- **Claude AI** — AI-assisted text generation for product content
- **Translation Management (TMS)** — Translation jobs and translation memory across configured providers
- **API Designer & MCP Playground** — Design custom API endpoints and test them against a built-in MCP (Model Context Protocol) playground

### Administration

- **Roles & Permissions** — Fine-grained access control with 5 default roles (Admin, Data Steward, Product Manager, Viewer, Export Manager)
- **User Management** — Full user administration with Sanctum-based authentication and SSO support
- **Cockpit Layouts** — Configurable dashboard layouts per role/user
- **Audit Trail** — Track changes across the system with user audit logs and journal
- **Access Links** — Temporary, token-based links for external catalog access without user accounts
- **System Tools** — Built-in test runner, API tester, database browser, data-consistency checker, security guard, log viewer, and Artisan cockpit for operations without shell access

### Project Management

- **Project Dashboard** — Overview of projects, teams, and workflow status
- **Workflows** — Configurable workflow statuses for task and product processes
- **Teams & Projects** — Organize users into teams and projects

### Public Catalog

- **Product Catalog** — Public-facing catalog with category navigation, faceted filters, and product detail pages
- **Asset Catalog** — Public media catalog with folder structure and download functionality

### Frontend

- **Modern SPA** — Vue 3 single-page application with responsive design
- **Dark Mode** — Full dark mode support with automatic system preference detection
- **Internationalization** — Multi-language UI (German, English) with extensible i18n
- **Keyboard Shortcuts** — Power-user friendly with keyboard navigation

> See [FEATURES.md](docs/features/features.md) for a detailed feature list with business benefits for each module.
> Content, E-Commerce, and some other modules are optional/licensed modules and only appear in the menu once enabled.

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
6. Rebuild documentation site (VitePress)
7. Recreate Laravel caches
8. Restart services and fix permissions
9. Run healthcheck and deactivate maintenance mode

**Options:**

```bash
sudo bash update.sh --branch=develop     # Use a different branch
sudo bash update.sh --skip-frontend      # Skip frontend rebuild
sudo bash update.sh --skip-docs          # Skip documentation rebuild
sudo bash update.sh --skip-tms           # Skip TMS setup
sudo bash update.sh --skip-composer      # Skip Composer install
sudo bash update.sh --skip-meilisearch   # Skip Meilisearch setup
sudo bash update.sh --seed               # Re-run database seeders
sudo bash update.sh --force              # Skip confirmation prompt
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.4 / Laravel 11 |
| **Frontend** | Vue 3, Vite, Tailwind CSS 4, DaisyUI 5 |
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
│   ├── Http/Controllers/Api/V1/  150+ API controllers (V1)
│   ├── Models/                   125+ Eloquent models
│   └── Services/                 175+ service classes (Inheritance, PQL, Export, Import, Versioning, Connectors)
├── pim-frontend/                 Vue 3 SPA (standalone npm project)
│   ├── src/components/            190+ Vue components
│   ├── src/stores/                Pinia state management
│   └── src/views/                 120+ page-level views
├── database/
│   ├── migrations/               225+ migration files
│   └── seeders/                  Demo data (products, attributes, hierarchies)
├── routes/api.php                600+ API routes (/api/v1/*)
├── static-content/               VitePress documentation site
├── setup.sh                      One-command server setup
├── update.sh                     One-command update & rebuild
└── healthcheck.sh                Service health verification
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

**600+ RESTful endpoints** covering products, attributes, hierarchies, media, prices, relations, imports, exports, workflow, reports, PDF templates, connectors, and more.

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

See [API.md](docs/reference/api.md) for the full endpoint reference.

---

## Documentation

Full documentation is available as a VitePress site in both German and English. `update.sh` builds it automatically and Apache serves it alongside your own installation (see [Updating](#updating)) — no external hosting or fixed domain involved. It's served at `<your-app-url>/help/{de,en}/`, where `<your-app-url>` is wherever you installed anyPIM — the domain root in Root Mode, or the subdirectory you chose in Subdirectory Mode (see [Deployment Modes](#deployment-modes)):

| | German | English |
|---|---|---|
| **On your install** | `https://your-domain.com/help/de/` | `https://your-domain.com/help/en/` |
| *(subdirectory mode)* | `https://your-domain.com/pim/help/de/` | `https://your-domain.com/pim/help/en/` |

### Guides

The same topics are available under your own installation's docs route (`.../help/en/...` below):

| Topic | Path |
|---|---|
| Quick Start | `installation/quickstart` |
| User Guide | `usage/` |
| Architecture | `architecture/` |
| API Reference | `api/` |
| Import | `import/` |
| Export | `export/` |
| FAQ | `faq/` |

### Local Documentation

| File | Description |
|---|---|
| [Features](docs/features/features.md) | Complete feature overview with business benefits |
| [API Reference](docs/reference/api.md) | REST API reference (600+ endpoints) |
| [Database Schema](docs/reference/database.md) | Database schema documentation |
| [Installation](docs/operations/install.md) | Detailed installation guide (`setup.sh`) |
| [Updates](docs/operations/update.md) | Update procedures (`update.sh`) |
| [Deployment](docs/operations/deployment.md) | Manual server deployment guide |
| [Production](docs/operations/production.md) | Production operations guide |
| [LICENSE](LICENSE) | GPL-3.0 license text |

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
| PHP | 8.3+ (8.4 recommended) |
| MySQL | 8.0+ |
| Redis | 6+ |
| Node.js | 20 LTS |
| RAM | 2 GB (4 GB recommended) |
| Disk | 2 GB free |

> `setup.sh` installs all dependencies automatically on a fresh Ubuntu 24.04 server.

---

## License

**GPL-3.0-only** — see [LICENSE](LICENSE) for the full text.

This means you can freely use, modify, and distribute anyPIM, but any modifications must also be made available under the GPL-3.0 license when distributed.

---

<p align="center">
  Built with care for product data teams everywhere.
</p>
