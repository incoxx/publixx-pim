---
title: E-Commerce Integrations — anyPIM
---

# E-Commerce & Integrations

<div class="sales-folder">

## anyPIM connects with your stack

anyPIM is not an isolated system. Native connectors synchronize product data directly with your shop systems, DAM platforms, and design tools — no middleware, no CSV exports, no manual effort.

All connectors share a common architecture: OAuth authentication, delta sync, checksums, sync logs, and bulk operations. Configuration via the web interface, execution by click or automated.

---

## Shop Systems

### Shopware 6

Complete, bidirectional integration with Shopware 6. anyPIM synchronizes:

- **Products** — Master data, descriptions, variants, and custom fields
- **Categories** — Hierarchies automatically mapped as Shopware category trees
- **Media** — Product images and documents including thumbnail generation
- **Properties** — Shopware property groups and options generated from PIM attributes
- **Delta Sync** — Only transfer changed products, based on checksums
- **Profile-based Sync** — Export profiles define which products and attributes are synchronized

Additional features: shop reset, purge (delete categories/media individually), sync logs with export, checksum management.

### Shopify

Native integration with Shopify via the Admin API:

- **Products** — Synchronize master data, variants, and prices
- **Categories** — Automatically create and assign collections
- **Media** — Upload and manage product images
- **Metafields** — Map custom fields as Shopify metafields
- **Delta Sync** — Checksums for incremental updates

Supports both legacy access tokens and OAuth (Client ID / Secret).

### Salesforce Commerce Cloud

Enterprise e-commerce integration for Salesforce Commerce Cloud (SFCC):

- **Products** — Synchronize product data to the SFCC catalog
- **Categories** — Automatically map catalog structure
- **Media** — Upload assets to the SFCC media library
- **Checksums** — Incremental updates for large catalogs

Configuration via Account Manager Client Credentials. Supports any site and catalog IDs.

---

## PIM-to-PIM

### anyPIM Connector

Bidirectional synchronization between anyPIM instances:

- **Push** — Send products and media to another anyPIM instance
- **Pull** — Retrieve products and translations from another instance
- **Bidirectional** — Full reconciliation in both directions
- **Connection Test** — Verify connectivity before syncing

Ideal for distributed teams, multi-tenant setups, or cross-company collaboration.

---

## DAM & Design

### Canva

OAuth-based integration with Canva for creative workflows:

- **Asset Sync** — Synchronize product images and media with Canva
- **Export Profiles** — Configurable profiles for automated Canva exports
- **Brand Templates** — Automatically populate existing Canva Brand Templates with product data (Autofill)

Perfect for marketing teams that process product images directly in Canva.

### Cloudinary

Push product media to the Cloudinary cloud:

- **Asset Upload** — Push media from anyPIM to Cloudinary, organized in folder structures
- **Transformation URLs** — Generate Cloudinary URLs for automatic image optimization and scaling

---

## Shared Connector Features

All connectors in anyPIM use the same robust infrastructure:

| Feature | Description |
|---------|-------------|
| **OAuth Authentication** | Secure authorization via OAuth 2.0 flow |
| **Delta Sync** | Only transfer changed data — saves time and bandwidth |
| **Checksums** | Change detection at field level |
| **Bulk Operations** | Synchronize hundreds of products in a single run |
| **Preview / Dry Run** | Review changes before they are transferred |
| **Sync Logs** | Complete audit trail of every synchronization |
| **Export Profiles** | Define which products and attributes are synchronized |
| **Connection Management** | Manage multiple connections per connector |

---

## Ready to integrate?

<div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem; margin-bottom: 2rem;">
<a href="/web/help/en/installation/quickstart" class="marketing-cta-button marketing-cta-primary">Quick Start Guide</a>
<a href="/web/help/en/marketing/ai-translation" class="marketing-cta-button marketing-cta-secondary">AI & API Designer</a>
<a href="/web/help/en/marketing/" class="marketing-cta-button marketing-cta-secondary">Back to Overview</a>
</div>

</div>
