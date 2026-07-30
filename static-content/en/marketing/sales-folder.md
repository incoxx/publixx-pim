---
title: Sales Folder — anyPIM
---

# anyPIM Sales Folder

<div class="sales-folder">

## At a Glance

**anyPIM** is an open-source Product Information Management system (PIM) that delivers enterprise functionality without enterprise costs. Developed by incoxx GmbH in Ingolstadt, Germany — with over 20 years of experience in product data management.

| | |
|---|---|
| **License** | GPL-3.0 — Open Source, free of charge |
| **Technology** | Laravel 11, Vue.js 3, Tailwind CSS, MySQL 8, Redis |
| **Setup Time** | 10 minutes (one script, one server) |
| **API** | 370+ REST endpoints + GraphQL, API-first architecture |
| **Languages** | Unlimited languages per attribute, UI in German & English |
| **Hosting** | Self-hosted on your own server (Ubuntu 24.04 LTS) |

---

## The Problem

Companies struggle with product data every day:

- **Spreadsheet Chaos** — Product information scattered across dozens of files, emailed back and forth, merged manually
- **Vendor Lock-in** — Expensive licenses (50,000–500,000+ EUR/year), opaque pricing, dependency on a single vendor
- **Rigid Legacy Systems** — New attribute? Six weeks lead time. API integration? Consulting project
- **Exploding Costs** — License fees, implementation costs, maintenance contracts

---

## The Solution: anyPIM

### Core Features

#### EAV Architecture — Unlimited Flexible Attributes

New product properties are created in seconds — no migration, no deployment, no waiting. The Entity-Attribute-Value model eliminates schema changes entirely.

**Supported Attribute Types:**
Text, Number, Decimal, Boolean, Date, Select/Multiselect, Textarea, Rich Text (HTML), Media, Reference, JSON, and more.

#### Smart Inheritance

Product variants automatically inherit attributes from the parent product. Changes propagate in real-time throughout the entire hierarchy. No more redundancy and inconsistency.

- Hierarchy inheritance (Category → Product)
- Variant inheritance (Parent product → Variant)
- Override possible at every level

#### PQL Query Language

Custom query language for filtering and searching products across any attribute combination:

```
name CONTAINS "Bolt" AND material = "Stainless Steel" AND price > 10
name FUZZY "Bolt"
name SOUNDS_LIKE "Bolt"
```

Supports fuzzy search and phonetic matching — finds products even with typos.

#### Excel Import & Export

- **Import:** 14-tab Excel format with intelligent validation, error reporting, and preview
- **Export:** JSON export, Publixx export, or custom formats via export mappings
- **BMEcat:** Support for the BMEcat industry standard

#### Fine-grained Permissions (RBAC)

7 predefined roles: Admin, Data Steward, Product Manager, Viewer, Export Manager, API Designer, Project Management.

Permissions controllable down to:
- Attribute level (who may view/edit which fields)
- Node level (who may edit which categories)
- Action level (import, export, workflow transitions)

#### True Multilingual

Unlimited languages per attribute — natively built into the architecture. No plugins, no workarounds. Optional: automatic translation via the integrated Translation Memory Service (TMS) with DeepL, Claude AI, Google Translate, or OpenAI.

---

### E-Commerce Integrations

anyPIM synchronizes product data natively with leading shop systems — no middleware, no manual export.

| Connector | Features |
|-----------|----------|
| **Shopware 6** | Products, categories, media, properties, delta sync, profile-based sync, shop reset, thumbnail generation |
| **Shopify** | Products, categories, media, metafields, delta sync (OAuth + legacy token) |
| **anyPIM-to-anyPIM** | Bidirectional sync: push, pull, translations, connection test |

All connectors offer: OAuth authentication, delta sync with checksums, sync logs with export, bulk operations, preview/dry run, and connection management.

More details: [E-Commerce Integrations](/en/marketing/integrations)

---

### AI & Automation

#### Translation Memory Service (TMS)

Dedicated microservice for automatic translation with four providers:

| Provider | Strength |
|----------|----------|
| **DeepL** | Highest quality for European languages |
| **Claude AI** | Context-sensitive translation with domain terminology |
| **Google Translate** | Broadest language coverage |
| **OpenAI** | Flexible alternative with GPT-4o |

Translation jobs with workflow (Create → Submit → Approve) and XLIFF import/export for translation agencies.

#### Claude AI Connector

AI-powered text processing directly in the PIM:
- Generate product descriptions and SEO copy
- Optimize and rephrase existing texts
- Context-aware based on product attributes

#### GraphQL & API Designer

- **Visual API Designer** — Create API endpoints via GUI, no code required
- **GraphQL Schemas** — Dynamically generated from the PIM data model
- **API Streams** — Public endpoints with slug-based access
- **API Templates** — With preview, custom keys, and dependency overview

More details: [AI, Translation & API Designer](/en/marketing/ai-translation)

---

### Advanced Features

| Feature | Description |
|---------|-------------|
| **Workflow System** | Multi-stage approval processes for product data |
| **Versioning** | Time-controlled versions with scheduled activation dates |
| **PDF Templates** | Custom product data sheets with individual layouts |
| **Catalog Embed** | Embeddable product catalogs for websites (online & offline) |
| **Planning Calendar** | Visual overview of planned changes and publications |
| **Price Regions** | Region-specific price lists with currency support |
| **Reports** | Data quality reports and completeness analysis |
| **Media Management** | Upload, manage, and assign images, PDFs, and documents |
| **Journal** | Complete audit trail of all changes |
| **Full-text Search** | PDF full-text search via Typesense |
| **Export Jobs** | Automated, scheduled exports |
| **Manufacturers** | Manufacturer management with product assignment |
| **Relation Types** | Flexible product relations (accessories, spare parts, etc.) |
| **Units** | Unit of measurement management with conversion |
| **Dictionary** | Central terminology database for consistent naming |
| **SSO** | Single Sign-On via Azure AD / Entra ID |
| **Excel Template Designer** | Visually configure custom Excel export templates |
| **Attribute Mapping** | Cross-classification mapping with Excel import/export |
| **Projects** | Organize product groups in projects with bulk assignment |
| **PimSync API** | Dedicated API for PIM-to-PIM synchronization |
| **User API Keys** | Self-service: users manage their own API keys |
| **Catalog Templates** | Templates for product catalogs with presets and preview |

---

## Technical Architecture

### System Requirements

| Component | Version/Specification |
|-----------|----------------------|
| Operating System | Ubuntu 24.04 LTS |
| RAM | Minimum 2 GB (4 GB recommended) |
| PHP | 8.4 with OPcache, Redis, GD, Intl |
| Database | MySQL 8.0 (InnoDB, utf8mb4) |
| Cache/Queue | Redis (separate databases for Cache, Queue, Session) |
| Search Engine | Typesense 27.1 (PDF full-text search) |
| Web Server | Apache 2 with mod_rewrite, SSL |
| Queue Worker | Laravel Horizon via Supervisor |
| Frontend | Vue.js 3, Tailwind CSS, Vite |
| Node.js | 20 LTS |

### Queue Architecture (Horizon)

Dedicated queues for different tasks with automatic scaling:

| Queue | Purpose | Max Processes |
|-------|---------|---------------|
| indexing | Search index updates | 4 |
| cache | Cache invalidation | 2 |
| default | General jobs, import, export | 4 |
| pdf | PDF processing | 2 |
| warmup | Cache warming | 2 |

### API-first Design

Complete REST API with token authentication (Laravel Sanctum) plus GraphQL support. Every function available in the frontend is also available via API. 370+ endpoints for:

- Products (CRUD, search, PQL queries)
- Attributes (management, groups, types)
- Hierarchies (tree structure, nodes)
- Import/Export (trigger, status, download)
- Connectors (Shopware, Shopify)
- Translation (TMS, translation jobs, XLIFF)
- API Designer (templates, streams, GraphQL)
- System (health check, status, queue management)

### Monitoring & Operations

- **Health Endpoint:** `/api/v1/health` — Checks database, Redis, storage, queue, disk
- **Horizon Dashboard:** Real-time monitoring of all queue workers
- **Healthcheck Script:** CLI tool with JSON output for monitoring systems
- **Logging:** Separate logs for application, import, export, Horizon

---

## Comparison: anyPIM vs. Enterprise PIM

| Criteria | anyPIM | Typical Enterprise PIM |
|----------|--------|------------------------|
| **License Cost** | 0 EUR — Open Source | 50,000–500,000+ EUR/year |
| **Setup Time** | 10 minutes | 3–12 months |
| **New Attributes** | Instant, no migration | Schema change + deployment |
| **API** | 370+ REST endpoints + GraphQL | Often limited or paid add-on |
| **E-Commerce** | Shopware 6, Shopify native | Custom connectors or middleware |
| **AI & Translation** | DeepL, Claude AI, Google, OpenAI | Manual or third-party plugin |
| **Query Language** | PQL with fuzzy + phonetic | Basic filters or SQL |
| **Source Code** | 100% visible, customizable | Closed source, black box |
| **Vendor Lock-in** | None | High |
| **Tech Stack** | Laravel 11, Vue 3, MySQL | Java/Proprietary, complex |
| **Hosting** | Self-hosted, full control | Cloud dependency or expensive infrastructure |
| **Support** | Community + optional enterprise support | Paid maintenance contracts |

---

## Target Audiences

### Mid-size Companies
Companies with 500–50,000 products that want to manage product data professionally without six-figure license costs.

### E-Commerce Retailers
Online retailers who need to centrally manage product data for multiple channels (webshop, marketplaces, print) and export automatically.

### Industrial Companies
Manufacturers with complex product hierarchies, technical attributes, and multilingual needs (BMEcat, eCl@ss compatible).

### Agencies & System Integrators
Service providers implementing PIM solutions for their clients who need full control over the source code.

---

## Implementation

### Quick Start

```bash
# 1. Clone repository
git clone https://github.com/incoxx/publixx-pim.git
cd publixx-pim

# 2. Run setup (installs everything automatically)
sudo bash setup.sh

# 3. Done — PIM is accessible
```

The setup script automatically installs and configures: PHP 8.4, MySQL 8, Redis, Apache, Node.js, Typesense, Horizon/Supervisor, and all dependencies.

### Update Process

```bash
sudo bash update.sh
```

Automated 10-step process: maintenance mode, git pull, Composer, migration, frontend build, documentation, cache, service restart, health check.

### Deployment Modes

| Mode | Command | Scope |
|------|---------|-------|
| Full | `sudo bash deploy.sh` | Everything (default) |
| Quick | `sudo bash deploy.sh --quick` | Cache & restart only |
| Backend | `sudo bash deploy.sh --backend` | Without frontend |
| Frontend | `sudo bash deploy.sh --frontend` | Frontend only |

---

## Security

- **Authentication:** Laravel Sanctum (token + SPA session)
- **SSO:** Azure AD / Entra ID integration
- **RBAC:** Fine-grained role and permission management
- **HTTPS:** Automatic Let's Encrypt SSL certificates
- **Session:** Redis-based, configurable lifetime (default: 120 minutes)
- **Audit Log:** Complete audit trail of all user activities
- **CORS:** Configurable stateful domains

---

## Support & Contact

### Open-Source Community
- GitHub: [github.com/incoxx/publixx-pim](https://github.com/incoxx/publixx-pim)
- Issues & feature requests via GitHub

### Enterprise Support
Professional support, custom development, and training from incoxx GmbH.

### Contact

**incoxx GmbH**
Aloisiweg 11
85049 Ingolstadt
Germany

Phone: +49 800 7542116
Email: [info@incoxx.com](mailto:info@incoxx.com)
Web: [www.incoxx.com](https://www.incoxx.com)

Managing Directors: Gabriele Karst, Markus Gerber
Commercial Register: AG Ingolstadt, HRB 5970
VAT ID: DE277889591

</div>
