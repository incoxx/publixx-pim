# anyPIM — Feature Overview

> Complete Product Information Management for teams that need to centralize, enrich, and distribute product data.

---

## Daily Work

| Feature | Description | Benefit |
|---------|------------|---------|
| **Dashboard** | Personal dashboard with widgets: open workflow tasks, recently edited products, data quality KPIs. | Instant overview of your workspace on login — no searching for the starting point. |
| **Hierarchies** | Master and output hierarchies with materialized path algorithm, drag-and-drop sorting, and per-node attribute assignments. | Products can be organized into arbitrarily deep category trees. Output hierarchies enable channel-specific structures (shop, print, marketplace) without duplicating data. |
| **Media** | Upload, automatic thumbnail generation, preview, metadata management. Supports images, PDFs, videos, and other files. | All product assets in one place — no file chaos on network drives. Thumbnails and previews directly in the PIM. |
| **Media Usage Types** | Define media usage categories (teaser, gallery, technical drawing, document). | Media is not just uploaded but classified per channel — shop, print, and catalog automatically receive the correct assets. |
| **PDF Templates** | Visual template designer for product data sheets with placeholders for attributes, media, and prices. | Product data sheets are generated automatically from PIM data — no more manual layout in InDesign or Word. |
| **Planning Calendar** | Calendar view for planned product launches, seasonal changes, and milestones. | Editorial planning directly in the PIM — all stakeholders see when products go live. |
| **Products** | Product list with inline filters, column customization, and bulk selection. Detail view with tabs for attributes, media, prices, relations, and variants. | Central data management with all information on one page. Variants inherit attributes automatically — only deviations need manual maintenance. |
| **Reports** | Configurable report designer with drag-and-drop fields, filters, and groupings. | Spot data quality and completeness at a glance — without exporting to Excel. |
| **Search** | Google-like full-text search across product names, SKU, EAN, and attribute values. Supports PQL (Product Query Language) for complex queries, plus a Meilisearch-based semantic/hybrid search (keyword + vector + hard filters). | Any product found in seconds — even with typos, thanks to phonetic search, fuzzy matching, and semantic similarity. |
| **Watchlist** | Add products to a personal watchlist for quick access. Compare products side by side. | Frequently needed or in-progress products are one click away — saves search time in daily work. |
| **Workflow** | Task management with status tracking, user assignment, and history. | Data maintenance becomes manageable: who needs to do what by when? Tasks no longer get lost in emails. |

---

## Configuration

| Feature | Description | Benefit |
|---------|------------|---------|
| **Attributes** | Manage all product attributes with 21 data types: String, Number, Float, Date, Flag, Selection, MultiSelection, Dictionary, Composite, RichText, Hyperlink, ImageLink, PdfLink, VideoLink, DelimitedValue, JsonArtefact, Textarea, HierarchyNodeReference, ProductReference, SimpleSelect, SimpleMultiSelect. | Any product property can be modeled without database changes — from simple text to structured links to repeatable data groups (collections). |
| **Attribute Groups** | Logical grouping of attributes (e.g., "Technical Data", "Marketing", "Logistics"). | Clear structure even with hundreds of attributes — users see only the group relevant to them. |
| **Attribute Views** | Definable subsets of attributes for different use cases and departments. | Marketing sees marketing attributes, logistics sees logistics attributes — without getting in each other's way. |
| **Dictionary** | Lookup table for technical terms, abbreviations, and translations. | Consistent terminology across the entire product range — important for SEO and brand consistency. |
| **Manufacturers** | Manufacturer master data with logo, contact details, and product assignments. | Manufacturer information is maintained centrally and available consistently across all products. |
| **Prices** | Price types (MSRP, purchase, retail, promotional) with tiered pricing and currency support. | Flexible pricing structures for different sales channels and customer groups — all managed at the product level. |
| **Price Regions** | Regional price assignments for different markets and countries. | International pricing directly in the PIM — without maintaining separate tables per country. |
| **Product Types** | Predefined types (physical product, training, service, software, bundle, digital asset) plus custom types. | Different product categories get different required fields and validation rules — a bundle has different requirements than software. |
| **Relation Types** | Definable relationship types between products (accessories, spare parts, cross-selling, up-selling) with relation-specific attributes. | Product recommendations and accessory lists are maintained in the PIM and automatically distributed to all channels. |
| **Translations** | Multi-language management of all texts with XLIFF export for translation agencies. | Translation workflow directly in the PIM — export to translators, re-import results. No more back-and-forth with Excel files. |
| **Units** | Unit groups and units with conversion factors (e.g., mm → cm → m). | Values are stored uniformly and converted automatically when needed — no more manual conversions. |
| **Value Lists** | Hierarchical pick lists with multi-language display values and nesting. | Uniform dropdown values across all products — "Red" is always "Red", not sometimes "red", "RED", or "Rot". |

---

## Administration

| Feature | Description | Benefit |
|---------|------------|---------|
| **Access Links** | Temporary, token-based links for external catalog access without a user account. | External partners, agencies, or customers can view product data — without user creation and with automatic expiration. |
| **API Tester** | Built-in API tester for directly calling all REST endpoints with authentication. | Interfaces can be tested directly in the PIM — without Postman or external tools. |
| **BMEcat Import/Export** | Industry-standard format for B2B product data exchange — bidirectional. | Seamless data exchange with suppliers and customers using BMEcat — without manual conversion. |
| **Database** | Direct view of the database structure and table contents for administrators. | Quick error analysis and data verification without command-line access to the server. |
| **Export** | Filtered product exports in configurable formats with mapping templates. | Each channel gets data in the desired format — shop, marketplace, and print from a single source. |
| **Export Jobs** | Named, reusable export configurations with scheduling (cron) and delivery via filesystem, SFTP, or webhook. | Regular exports run automatically — e.g., updated product data to the shop every morning. |
| **Import** | Excel import with 14-tab structure, three-phase validation (parse → validate → execute), and fuzzy matching for typos. | Large data volumes can be loaded via Excel — with automatic error detection and correction suggestions instead of silent failures. |
| **Journal** | System-wide change log of all data modifications. | Complete data history — every change is traceable and reversible if needed. |
| **JSON Export/Import** | Full data export/import in 18 dependency-ordered sections. Supports filtering by status, product type, and hierarchy. | Complete PIM instances can be backed up, migrated, or transferred between environments — including all relationships and dependencies. |
| **Roles** | Fine-grained permission system with predefined roles (Admin, Data Steward, Product Manager, Viewer, Export Manager) and custom configuration. | Each user sees and can do exactly what they need — restrictable down to attribute and hierarchy node level. |
| **Settings** | System-wide configuration: languages, modules, licensing, search index rebuild. | Central system control — all settings in one place, not scattered across config files. |
| **User Audit Trail** | Complete logging of all user actions with timestamp, user, and changed fields. | Traceability: who changed what and when? Important for quality assurance and compliance. |
| **Users** | User management with Sanctum authentication, password policies, and profile management. SSO support via Azure AD. | Full user control without external identity management — ready to use immediately. |

---

## Public Areas (no login required)

| Feature | Description | Benefit |
|---------|------------|---------|
| **Asset Catalog** | Public media catalog with folder structure and download functionality. | Agencies and partners can download product images and documents themselves — without email requests. |
| **Product Catalog** | Public product catalog with category navigation, faceted filters, product detail pages, and contact form. | Ready-to-use online catalog directly from the PIM — as an interim solution until the shop launches or as a permanent B2B catalog. |

---

## Additional Product Features

| Feature | Description | Benefit |
|---------|------------|---------|
| **Bulk Editor** | Spreadsheet-style mass editing of attribute values across multiple products simultaneously. | Update hundreds of products in minutes instead of hours — like Excel, but directly in the PIM. |
| **Bulk Update** | Apply the same value to a selection of products in a single step. | "Set status to active for all 500 summer products" — one click instead of 500. |
| **Collections** | Configurable, typed product groupings (e.g. offers/quotes) with item-level attribute values, PDF/XLSX rendering, share links, and import from JSON/CSV/OpenTRANS RFQ with matching. | Offers and quotes are assembled from PIM data and exported or shared directly, and incoming RFQs can be matched against existing products. |
| **Product Versioning** | Version history with scheduling (publish date) and one-click rollback. | Product changes can be prepared and scheduled — with a safety net through instant rollback. |
| **Variant Inheritance** | Per-attribute control: inherit or override. Changes to the master product propagate automatically. | Variants practically maintain themselves — only actual deviations (color, size) need to be set manually. |
| **Cluster Inheritance (dynamic clusters)** | Product types can be flagged to generate virtual products from a cluster definition, with an option to allow free (unmapped) attributes per axis. | Large structured product families are maintained via a single cluster definition instead of one row per combination. |

---

## Technical Differentiators

### PQL — Product Query Language
Custom query language with SQL-like syntax for complex product searches:
- Comparison operators: `=`, `!=`, `>`, `<`, `>=`, `<=`
- Pattern matching: `LIKE`, `NOT LIKE` (full-text indexed)
- Lists: `IN`, `NOT IN`
- Ranges: `BETWEEN`, `NOT BETWEEN`
- Existence: `EXISTS`, `NOT EXISTS`
- **Fuzzy search:** `FUZZY 'text' [threshold]` — Levenshtein + trigram (60%+40% weighted)
- **Phonetic search:** `SOUNDS_LIKE 'text'` — Kölner Phonetik (German) + Soundex (English)
- **Weighted field search:** `SEARCH_FIELDS(field^weight, ...)`
- **Relevance ranking:** `ORDER BY SCORE DESC`

**Benefit:** Product managers find the right products even with imprecise queries — typos, dialect spellings, and synonyms are recognized.

### Two-Level Inheritance System
1. **Hierarchy inheritance:** Products inherit attributes from their assigned node and all ancestors
2. **Variant inheritance:** Per-attribute control (inherit vs. override)

**Benefit:** Dramatic reduction in maintenance effort — shared data is maintained only once and automatically propagated.

### EAV Architecture with Materialized Search Index
- Entity-Attribute-Value model for unlimited attribute flexibility
- Denormalized search index table for full-text search with phonetic field
- Automatic invalidation on data changes

**Benefit:** New attributes are created through the UI — without database migrations, deployments, or developers.

### REST API with ~870 Endpoints
Full API coverage of all features with Sanctum authentication (Bearer Token + SPA Cookie).

**Benefit:** Any third-party system (shop, ERP, POS, marketplace) can read and write product data — the PIM becomes the central data hub.

---

## Technical Specifications

| Property | Value |
|----------|-------|
| **Backend** | PHP 8.4 / Laravel 11 |
| **Frontend** | Vue 3 / Vite / Tailwind CSS 4 / DaisyUI 5 |
| **Database** | MySQL 8+ |
| **Cache & Queue** | Redis / Laravel Horizon |
| **Authentication** | Laravel Sanctum + SSO (Azure AD) |
| **API Endpoints** | ~870 RESTful |
| **Data Types** | 21 |
| **Eloquent Models** | 122 |
| **Vue Components** | 315+ (.vue files) |
| **Migrations** | 214 |
| **UI Languages** | German, English |
| **License** | GPL-3.0 (Open Source) |
| **Installation** | Single command (`setup.sh`) on Ubuntu 24.04 |
