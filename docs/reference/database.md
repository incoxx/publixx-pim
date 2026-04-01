# anyPIM — Database Schema

> 58 tables across 88 migrations, organized by domain.

All business tables use UUID (CHAR 36) primary keys. The schema follows an EAV (Entity-Attribute-Value) pattern for flexible product attributes and multi-language support via JSON columns.

---

## Overview

| Domain | Tables |
|--------|--------|
| [Authentication & Authorization](#authentication--authorization) | 9 |
| [Attributes](#attributes) | 6 |
| [Units & Values](#units--values) | 5 |
| [Hierarchies](#hierarchies) | 5 |
| [Products](#products) | 5 |
| [Product Relations](#product-relations) | 4 |
| [Media](#media) | 4 |
| [Pricing](#pricing) | 3 |
| [Manufacturers](#manufacturers) | 1 |
| [Import](#import) | 4 |
| [Export](#export) | 4 |
| [Search](#search) | 2 |
| [Reports & PDF](#reports--pdf) | 3 |
| [Workflow & Calendar](#workflow--calendar) | 2 |
| [Watchlist](#watchlist) | 1 |
| [Audit](#audit) | 1 |
| [Settings](#settings) | 1 |
| [Queue & Cache](#queue--cache) | 4 |

---

## Authentication & Authorization

### `users`
User accounts with authentication and profile data.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `name` | varchar(255) | |
| `email` | varchar(255) | UNIQUE |
| `password` | varchar(255) | nullable |
| `language` | varchar(5) | default `de` |
| `is_active` | boolean | default `true` |
| `last_login_at` | timestamp | nullable |
| `sso_provider` | varchar(255) | nullable |
| `sso_id` | varchar(255) | nullable |
| `email_verified_at` | timestamp | nullable |
| `remember_token` | varchar(100) | nullable |
| `created_at` / `updated_at` | timestamp | |

### `roles`
Role definitions for RBAC.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `name` | varchar(255) | UNIQUE with guard_name |
| `guard_name` | varchar(255) | default `sanctum` |

### `permissions`
Individual permission definitions.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `name` | varchar(255) | UNIQUE with guard_name |
| `guard_name` | varchar(255) | default `sanctum` |

### `model_has_roles`
Assigns roles to users. PK: (role_id, model_id, model_type).

### `model_has_permissions`
Assigns permissions directly to users. PK: (permission_id, model_id, model_type).

### `role_has_permissions`
Assigns permissions to roles. PK: (permission_id, role_id).

### `personal_access_tokens`
API token storage (Laravel Sanctum).

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK, auto-increment |
| `tokenable_type` / `tokenable_id` | varchar / char(36) | polymorphic |
| `name` | varchar(255) | |
| `token` | varchar(64) | UNIQUE |
| `abilities` | text | nullable |
| `last_used_at` / `expires_at` | timestamp | nullable |

### `access_links`
Temporary, token-based access links for external users.

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | PK |
| `token` | varchar(64) | UNIQUE |
| `name` | varchar(255) | |
| `role_id` | uuid | FK → roles |
| `created_by` | uuid | FK → users |
| `expires_at` | timestamp | |
| `used_at` | timestamp | nullable |
| `user_id` | uuid | nullable, FK → users |
| `ip_address` / `user_agent` | varchar | nullable |

### `sessions`
User session storage.

---

## Attributes

### `attribute_types`
Logical grouping of attributes (e.g., "Technical Data", "Marketing").

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `name_json` | json | nullable, multi-language |
| `description` | text | nullable |
| `sort_order` | int | default `0` |

### `attributes`
Core attribute definitions — the heart of the EAV model.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `name_json` | json | nullable |
| `description_de` / `description_en` | text | nullable |
| `data_type` | enum | String, Number, Float, Date, Flag, Selection, Dictionary, Composite, RichText, Hyperlink, ImageLink, PdfLink, VideoLink |
| `attribute_type_id` | char(36) | nullable, FK → attribute_types |
| `value_list_id` | char(36) | nullable, FK → value_lists |
| `unit_group_id` | char(36) | nullable, FK → unit_groups |
| `default_unit_id` | char(36) | nullable, FK → units |
| `comparison_operator_group_id` | char(36) | nullable, FK → comparison_operator_groups |
| `is_translatable` | boolean | default `false` |
| `is_multipliable` | boolean | default `false` |
| `is_searchable` | boolean | default `true` |
| `is_mandatory` | boolean | default `false` |
| `is_unique` | boolean | default `false` |
| `is_country_specific` | boolean | default `false` |
| `is_inheritable` | boolean | default `true` |
| `is_variant_attribute` | boolean | default `false` |
| `is_internal` | boolean | default `false` |
| `parent_attribute_id` | char(36) | nullable, FK → attributes (composite children) |
| `composite_format` / `composite_expression` | varchar | nullable |
| `max_multiplied` / `max_pre_decimal` / `max_post_decimal` / `max_characters` | int | nullable |
| `position` | int | nullable |
| `source_system` / `source_attribute_name` / `source_attribute_key` | varchar | nullable |
| `status` | enum | active, inactive |

### `attribute_views`
Definable subsets of attributes for different departments.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `name_json` | json | nullable |
| `is_write_protected` | boolean | default `false` |
| `sort_order` | int | default `0` |

### `attribute_view_assignments`
Many-to-many: attributes ↔ attribute views. UNIQUE (attribute_id, attribute_view_id).

### `dictionary_entries`
Reusable dictionary entries for Dictionary-type attributes.

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | PK |
| `category` | varchar(100) | nullable, indexed |
| `short_text_de` / `short_text_en` | varchar(255) | |
| `long_text_de` / `long_text_en` | text | |
| `status` | enum | active, inactive |

### `attribute_dictionary_entry`
Many-to-many: attributes ↔ dictionary entries. UNIQUE (attribute_id, dictionary_entry_id).

---

## Units & Values

### `unit_groups`
Groups of related measurement units (e.g., "Length", "Weight").

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `name_json` | json | nullable |

### `units`
Individual measurement units with conversion factors.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `unit_group_id` | char(36) | FK → unit_groups |
| `technical_name` | varchar(100) | UNIQUE within group |
| `abbreviation` | varchar(20) | |
| `abbreviation_json` | json | nullable |
| `conversion_factor` | decimal(20,10) | default `1` |
| `is_base_unit` | boolean | default `false` |

### `value_lists`
Enumeration definitions for Selection-type attributes.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `value_data_type` | enum | String, Number |
| `max_depth` | int | default `1` |

### `value_list_entries`
Individual values within a value list. Supports nesting via `parent_entry_id`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `value_list_id` | char(36) | FK → value_lists |
| `parent_entry_id` | char(36) | nullable, FK → self |
| `technical_name` | varchar(100) | UNIQUE within list |
| `display_value_de` / `display_value_en` | varchar(255) | |
| `display_value_json` | json | nullable |
| `sort_order` | int | default `0` |
| `is_active` | boolean | default `true` |

### `comparison_operator_groups` / `comparison_operators`
Comparison operator definitions for attribute filtering. Operators belong to groups and have a `symbol` (e.g., `≤`, `≥`).

---

## Hierarchies

### `hierarchies`
Hierarchy definitions (master, output, or asset type).

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `hierarchy_type` | enum | master, output, asset |

### `hierarchy_nodes`
Tree nodes with materialized path for efficient querying.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `hierarchy_id` | char(36) | FK → hierarchies |
| `parent_node_id` | char(36) | nullable, FK → self |
| `name_de` / `name_en` | varchar(255) | |
| `name_json` | json | nullable |
| `path` | varchar(1000) | materialized path |
| `depth` | int | default `0` |
| `sort_order` | int | default `0` |
| `is_active` | boolean | default `true` |

### `hierarchy_attribute_assignments`
Attributes assigned at hierarchy level. UNIQUE (hierarchy_id, attribute_id).

### `hierarchy_node_attribute_assignments`
Attributes assigned at node level with inheritance control.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `hierarchy_node_id` | char(36) | FK → hierarchy_nodes |
| `attribute_id` | char(36) | FK → attributes |
| `collection_name` | varchar(255) | nullable |
| `collection_sort` / `attribute_sort` | int | sorting |
| `dont_inherit` | boolean | default `false` |
| `is_required` | boolean | default `false` |
| `parent_assignment_id` | char(36) | nullable, FK → self |
| `access_hierarchy` / `access_product` / `access_variant` | enum | hidden, visible, editable |

### `hierarchy_node_attribute_values`
EAV values stored at hierarchy node level (for inheritance).

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `hierarchy_node_id` | char(36) | FK → hierarchy_nodes |
| `attribute_id` | char(36) | FK → attributes |
| `value_string` | text | nullable |
| `value_number` | decimal(20,6) | nullable |
| `value_date` | date | nullable |
| `value_flag` | boolean | nullable |
| `value_selection_id` | char(36) | nullable, FK → value_list_entries |
| `unit_id` | char(36) | nullable, FK → units |
| `language` | varchar(5) | nullable |
| `multiplied_index` | int | default `0` |

---

## Products

### `product_types`
Product classification with feature flags.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `has_variants` / `has_ean` / `has_prices` / `has_media` / `has_stock` / `has_physical_dimensions` | boolean | feature flags |
| `workflow_enabled` | boolean | default `false` |
| `default_attribute_groups` / `allowed_relation_types` / `validation_rules` | json | nullable |

### `products`
Core product records.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `product_type_id` | char(36) | FK → product_types |
| `sku` | varchar(100) | UNIQUE |
| `ean` | varchar(20) | nullable |
| `name` | varchar(500) | FULLTEXT indexed |
| `status` | enum | draft, active, inactive, discontinued |
| `product_type_ref` | enum | product, variant |
| `parent_product_id` | char(36) | nullable, FK → self (variants) |
| `master_hierarchy_node_id` | char(36) | nullable, FK → hierarchy_nodes |
| `manufacturer_id` | uuid | nullable, FK → manufacturers |
| `workflow_status` | enum | nullable: editing, review, approved |
| `workflow_assignee_id` | char(36) | nullable, FK → users |
| `created_by` / `updated_by` | char(36) | nullable, FK → users |

### `product_attribute_values`
EAV attribute values for products — the main data storage table.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `product_id` | char(36) | FK → products |
| `attribute_id` | char(36) | FK → attributes |
| `value_string` | text | nullable |
| `value_number` | decimal(20,6) | nullable |
| `value_date` | date | nullable |
| `value_flag` | boolean | nullable |
| `value_selection_id` | char(36) | nullable, FK → value_list_entries |
| `unit_id` | char(36) | nullable, FK → units |
| `comparison_operator_id` | char(36) | nullable, FK → comparison_operators |
| `language` | varchar(5) | nullable |
| `multiplied_index` | int | default `0` |
| `is_inherited` | boolean | default `false` |
| `inherited_from_node_id` | char(36) | nullable, FK → hierarchy_nodes |
| `inherited_from_product_id` | char(36) | nullable, FK → products |
| `output_hierarchy_id` | char(36) | nullable, FK → hierarchies |

UNIQUE: (product_id, attribute_id, language, multiplied_index, output_hierarchy_id)

### `variant_inheritance_rules`
Per-attribute inheritance control for variants. UNIQUE (product_id, attribute_id).

| Column | Type | Notes |
|--------|------|-------|
| `product_id` | char(36) | FK → products |
| `attribute_id` | char(36) | FK → attributes |
| `inheritance_mode` | enum | inherit, override |

### `product_versions`
Product version history with scheduling support.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `product_id` | char(36) | FK → products |
| `version_number` | unsigned int | UNIQUE within product |
| `status` | enum | draft, scheduled, active, archived |
| `snapshot` | json | full product state |
| `change_reason` | text | nullable |
| `publish_at` / `published_at` | timestamp | nullable |
| `created_by` | char(36) | nullable, FK → users |

---

## Product Relations

### `product_relation_types`
Types of relationships between products (e.g., accessories, cross-selling).

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `is_bidirectional` | boolean | default `false` |

### `product_relations`
Actual relationships between products. UNIQUE (source, target, type).

### `product_relation_attribute_values`
EAV values on product relations (same structure as product_attribute_values).

### `relation_type_default_attributes`
Default attributes assigned to a relation type. UNIQUE (relation_type_id, attribute_id).

---

## Media

### `media`
Media file metadata.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `file_name` | varchar(255) | |
| `file_path` | varchar(500) | |
| `mime_type` | varchar(100) | |
| `file_size` | bigint unsigned | |
| `media_type` | enum | image, document, video, other |
| `title_de` / `title_en` | varchar(255) | nullable |
| `description_de` / `description_en` | text | nullable |
| `alt_text_de` / `alt_text_en` | varchar(255) | nullable |
| `width` / `height` | int | nullable |
| `asset_folder_id` | char(36) | nullable, FK → hierarchy_nodes |
| `usage_purpose` | varchar(10) | default `both` |

### `media_usage_types`
Classification of media usage (e.g., teaser, gallery, technical drawing).

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `technical_name` | varchar(100) | UNIQUE |
| `name_de` / `name_en` | varchar(255) | |
| `allowed_extensions` | json | nullable |
| `sort_order` | int | default `0` |

### `product_media_assignments`
Assigns media to products with usage type and sort order.

| Column | Type | Notes |
|--------|------|-------|
| `product_id` | char(36) | FK → products |
| `media_id` | char(36) | FK → media |
| `usage_type_id` | char(36) | nullable, FK → media_usage_types |
| `sort_order` | int | default `0` |
| `is_primary` | boolean | default `false` |

### `media_attribute_values`
EAV values for media assets (same structure as product_attribute_values).

---

## Pricing

### `price_types`
Price classifications (e.g., MSRP, purchase price, retail).

### `price_regions`
Geographic or organizational pricing regions.

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | PK |
| `code` | varchar(10) | UNIQUE |
| `name` | varchar(255) | |
| `type` | enum | country, vkorg |
| `metadata` | json | nullable |

### `product_prices`
Product pricing data with tiered pricing support.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `product_id` | char(36) | FK → products |
| `price_type_id` | char(36) | FK → price_types |
| `amount` | decimal(12,2) | |
| `currency` | varchar(3) | default `EUR` |
| `valid_from` / `valid_to` | date | nullable |
| `price_region_id` | char(36) | nullable, FK → price_regions |
| `scale_from` / `scale_to` | int | nullable, tiered pricing |

---

## Manufacturers

### `manufacturers`
Product manufacturer information.

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | PK |
| `name` | varchar(255) | |
| `street` / `zip` / `city` | varchar | nullable |
| `country` | varchar(2) | nullable |
| `email` / `website` | varchar | nullable |
| `logo_media_id` | uuid | nullable, FK → media |

---

## Import

### `import_jobs`
Import task execution records with status tracking.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `user_id` | char(36) | FK → users |
| `file_name` / `file_path` | varchar | |
| `status` | enum | uploaded, validating, validated, executing, completed, failed |
| `sheets_found` / `summary` / `result` | json | nullable |

### `import_job_errors`
Detailed error records for failed import rows.

### `import_logs`
Phase-based import logging (upload, validation, execution) with level (info, warning, error).

### `import_profiles`
Saved import configurations with column mappings, price mappings, and relation mappings.

---

## Export

### `publixx_export_mappings`
Export mapping templates for channel-specific data formats.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `name` | varchar(255) | |
| `attribute_view_id` | char(36) | nullable, FK → attribute_views |
| `output_hierarchy_id` | char(36) | nullable, FK → hierarchies |
| `mapping_rules` | json | nullable |
| `include_media` / `include_prices` / `include_variants` / `include_relations` | boolean | |
| `languages` | json | nullable |
| `flatten_mode` | enum | flat, nested, publixx |

### `export_profiles`
Saved export configurations with format and filter settings.

### `export_jobs`
Scheduled export jobs with cron expressions and delivery configuration.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `name` | varchar(255) | |
| `format` | enum | json, excel, csv, xml |
| `cron_expression` | varchar(100) | nullable |
| `delivery_type` | varchar(20) | nullable (filesystem, sftp, http) |
| `delivery_config` | json | nullable |
| `is_active` | boolean | default `true` |
| `last_status` | enum | pending, running, completed, failed |
| `next_run_at` | timestamp | nullable |

### `export_job_logs`
Execution history for export jobs with duration, file size, and delivery status.

---

## Search

### `search_profiles`
Saved search configurations with text, mode, filters, and sorting.

### `products_search_index`
Denormalized search index for fast full-text search.

| Column | Type | Notes |
|--------|------|-------|
| `product_id` | char(36) | PK |
| `sku` / `ean` | varchar | indexed |
| `product_type` / `status` | varchar / enum | indexed |
| `name_de` / `name_en` | varchar(500) | FULLTEXT |
| `description_de` | text | FULLTEXT |
| `hierarchy_path` | varchar(1000) | |
| `primary_image` | varchar(500) | |
| `list_price` | decimal(12,2) | indexed |
| `attribute_completeness` | tinyint | |
| `phonetic_name_de` | varchar(100) | |
| `searchable_text` / `media_text` / `phonetic_text` | text | FULLTEXT |

---

## Reports & PDF

### `report_templates`
Report template definitions with layout settings.

### `report_jobs`
Report generation job tracking with status and output path.

### `pdf_templates`
PDF template designs stored as JSON with page settings.

---

## Workflow & Calendar

### `workflow_tasks`
Task management for product data maintenance.

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | PK |
| `product_id` | char(36) | FK → products |
| `title` | varchar(255) | |
| `status` | enum | open, in_progress, closed |
| `assigned_to` | char(36) | nullable, FK → users |
| `created_by` | char(36) | nullable, FK → users |
| `closed_at` | timestamp | nullable |
| `note` | text | nullable |

### `scheduled_actions`
Scheduled product actions (status changes, price updates, etc.).

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid | PK |
| `title` | varchar(255) | |
| `action_type` | varchar(50) | |
| `scheduled_at` | datetime | indexed |
| `status` | varchar(20) | default `pending` |
| `product_id` | uuid | nullable, FK → products |
| `product_ids` | json | nullable (bulk) |
| `payload` | json | action parameters |
| `color` | varchar(20) | nullable, calendar display |

---

## Watchlist

### `watchlist_items`
Personal product watchlists per user. UNIQUE (user_id, product_id).

---

## Audit

### `audit_logs`
Change tracking for all entities.

| Column | Type | Notes |
|--------|------|-------|
| `id` | char(36) | PK |
| `user_id` | char(36) | nullable, FK → users |
| `auditable_type` / `auditable_id` | varchar / char(36) | polymorphic |
| `action` | varchar(50) | |
| `old_values` / `new_values` | json | nullable |
| `ip_address` | varchar(45) | nullable |

---

## Settings

### `settings`
Key-value configuration storage. Grouped by `group` (UNIQUE).

---

## Queue & Cache

### `jobs` / `job_batches` / `failed_jobs`
Laravel queue infrastructure tables.

### `cache` / `cache_locks`
Laravel cache storage tables.

---

## Output Hierarchy Assignments

### `output_hierarchy_product_assignments`
Products assigned to output hierarchy nodes. UNIQUE (hierarchy_node_id, product_id).

---

## Key Design Patterns

1. **UUID primary keys** — All business tables use CHAR(36) UUIDs for distributed scalability
2. **EAV pattern** — Used for products, hierarchy nodes, media, and product relations
3. **Multi-language** — `name_de`, `name_en`, `name_json` columns + `language` in EAV tables
4. **Materialized path** — Hierarchy nodes store their full path for efficient tree queries
5. **Denormalized search index** — `products_search_index` with FULLTEXT and phonetic fields
6. **Soft cascades** — Nullable FKs with SET NULL for flexible data retention
7. **Polymorphic relations** — Permission system uses model_type/model_id
8. **Inheritance tracking** — `is_inherited`, `inherited_from_node_id`, `inherited_from_product_id` in attribute values
