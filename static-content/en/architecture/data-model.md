---
title: Data Model
---

# Data Model

The anyPIM data model comprises **35 tables**, organized into nine functional domains. All tables use **UUID primary keys** for globally unique identifiers and make extensive use of **JSON columns** for flexible, schema-independent data structures.

## Domain Overview

### Attribute Model (10 Tables)

The attribute model forms the backbone of the EAV architecture. It defines which properties products can have and how they are structured.

| Table | Description |
|---|---|
| `attributes` | Attribute definitions with type, validation rules, sort order, and multilingual settings |
| `attribute_groups` | Logical grouping of attributes (e.g., "Technical Data", "Marketing Texts") |
| `attribute_group_attribute` | Assignment of attributes to groups (n:m) |
| `attribute_options` | Predefined choices for select and multiselect attributes |
| `attribute_views` | Visibility definitions: Which subset of attributes a user role is allowed to see |
| `attribute_view_attribute` | Assignment of attributes to views (n:m) |
| `attribute_collections` | Definition of collection attribute groups (repeatable attribute groups) |
| `attribute_collection_entries` | Individual entries within a collection instance |
| `attribute_validations` | Extended validation rules per attribute (regex, min/max, required field) |
| `attribute_translations` | Translations for attribute names and descriptions |

### Product Model (6 Tables)

| Table | Description |
|---|---|
| `products` | Core entity with reference to parent product (variants), status, and metadata |
| `product_attribute_values` | Central EAV value table: Links product, attribute, and value with locale reference |
| `product_variants` | Assignment table for the parent-variant relationship with inheritance rules |
| `product_variant_rules` | Per-attribute rules for variant inheritance (inherit/override) |
| `product_versions` | Versioning information for products (change history) |
| `product_collection_values` | Values within collection instances per product |

### Hierarchy Model (4 Tables)

| Table | Description |
|---|---|
| `hierarchies` | Hierarchy definitions (master hierarchy, output hierarchies) with type and metadata |
| `hierarchy_nodes` | Individual nodes within a hierarchy, with `parent_id` for the tree structure |
| `hierarchy_node_product` | Assignment of products to hierarchy nodes (n:m) |
| `hierarchy_node_attribute` | Assignment of attributes to nodes for hierarchy inheritance, incl. `dont_inherit` flag |

### Media Model (2 Tables)

| Table | Description |
|---|---|
| `media` | Media files with path, MIME type, file size, and metadata (JSON) |
| `mediables` | Polymorphic assignment table: Links media with products, attributes, or nodes |

### Price Model (2 Tables)

| Table | Description |
|---|---|
| `price_lists` | Price list definitions with currency, validity period, and priority |
| `product_prices` | Price assignments: Product-price list combination with net/gross price |

### Export and PXF (2 Tables)

| Table | Description |
|---|---|
| `export_templates` | PXF export templates with channel assignment and mapping configuration (JSON) |
| `export_mappings` | Individual field mappings within a template: Source attribute to target field name |

### Import (2 Tables)

| Table | Description |
|---|---|
| `import_jobs` | Import jobs with status, file reference, progress, and error log (JSON) |
| `import_mappings` | Column mappings between Excel columns and system attributes incl. fuzzy match score |

### User Management (5 Tables)

| Table | Description |
|---|---|
| `users` | User accounts with profile information and settings |
| `roles` | Role definitions (Spatie Permission) |
| `permissions` | Individual permissions (Spatie Permission) |
| `model_has_roles` | Assignment of roles to users |
| `model_has_permissions` | Direct assignment of permissions to users or roles |

### Performance (1 Table)

| Table | Description |
|---|---|
| `products_search_index` | Materialized search index with denormalized product data, FULLTEXT indexes, and pre-aggregated attribute values |

### System (1 Table)

| Table | Description |
|---|---|
| `system_settings` | Key-value store for global system configurations (JSON values) |

---

## The EAV Pattern in Detail

The central design decision of anyPIM is the **Entity-Attribute-Value pattern**. The following diagram shows the three core entities and their relationships.

<svg viewBox="0 0 880 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="erShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2" flood-opacity="0.1"/>
    </filter>
    <marker id="erArrow" markerWidth="12" markerHeight="8" refX="12" refY="4" orient="auto">
      <polygon points="0 0, 12 4, 0 8" fill="#64748b"/>
    </marker>
    <marker id="erDiamond" markerWidth="14" markerHeight="10" refX="0" refY="5" orient="auto">
      <polygon points="7 0, 14 5, 7 10, 0 5" fill="#64748b"/>
    </marker>
  </defs>

  <!-- Entity: products -->
  <rect x="30" y="40" width="240" height="220" rx="8" fill="white" stroke="#3b82f6" stroke-width="2" filter="url(#erShadow)"/>
  <rect x="30" y="40" width="240" height="40" rx="8" fill="#3b82f6"/>
  <rect x="30" y="72" width="240" height="8" fill="#3b82f6"/>
  <text x="150" y="66" text-anchor="middle" fill="white" font-size="14" font-weight="bold">products</text>
  <text x="45" y="104" fill="#1e293b" font-size="12" font-family="monospace">id         UUID PK</text>
  <text x="45" y="124" fill="#1e293b" font-size="12" font-family="monospace">parent_id  UUID FK null</text>
  <text x="45" y="144" fill="#1e293b" font-size="12" font-family="monospace">sku        VARCHAR unique</text>
  <text x="45" y="164" fill="#1e293b" font-size="12" font-family="monospace">status     ENUM</text>
  <text x="45" y="184" fill="#1e293b" font-size="12" font-family="monospace">metadata   JSON</text>
  <text x="45" y="204" fill="#1e293b" font-size="12" font-family="monospace">created_at TIMESTAMP</text>
  <text x="45" y="224" fill="#1e293b" font-size="12" font-family="monospace">updated_at TIMESTAMP</text>

  <!-- Entity: attributes -->
  <rect x="590" y="40" width="260" height="260" rx="8" fill="white" stroke="#059669" stroke-width="2" filter="url(#erShadow)"/>
  <rect x="590" y="40" width="260" height="40" rx="8" fill="#059669"/>
  <rect x="590" y="72" width="260" height="8" fill="#059669"/>
  <text x="720" y="66" text-anchor="middle" fill="white" font-size="14" font-weight="bold">attributes</text>
  <text x="605" y="104" fill="#1e293b" font-size="12" font-family="monospace">id              UUID PK</text>
  <text x="605" y="124" fill="#1e293b" font-size="12" font-family="monospace">code            VARCHAR unique</text>
  <text x="605" y="144" fill="#1e293b" font-size="12" font-family="monospace">type            ENUM</text>
  <text x="605" y="164" fill="#1e293b" font-size="12" font-family="monospace">is_translatable BOOLEAN</text>
  <text x="605" y="184" fill="#1e293b" font-size="12" font-family="monospace">is_required     BOOLEAN</text>
  <text x="605" y="204" fill="#1e293b" font-size="12" font-family="monospace">validation      JSON</text>
  <text x="605" y="224" fill="#1e293b" font-size="12" font-family="monospace">sort_order      INTEGER</text>
  <text x="605" y="244" fill="#1e293b" font-size="12" font-family="monospace">created_at      TIMESTAMP</text>
  <text x="605" y="264" fill="#1e293b" font-size="12" font-family="monospace">updated_at      TIMESTAMP</text>

  <!-- Entity: product_attribute_values -->
  <rect x="220" y="340" width="420" height="170" rx="8" fill="white" stroke="#d97706" stroke-width="2" filter="url(#erShadow)"/>
  <rect x="220" y="340" width="420" height="40" rx="8" fill="#d97706"/>
  <rect x="220" y="372" width="420" height="8" fill="#d97706"/>
  <text x="430" y="366" text-anchor="middle" fill="white" font-size="14" font-weight="bold">product_attribute_values</text>
  <text x="240" y="404" fill="#1e293b" font-size="12" font-family="monospace">id            UUID PK</text>
  <text x="240" y="424" fill="#1e293b" font-size="12" font-family="monospace">product_id    UUID FK  ──&gt; products.id</text>
  <text x="240" y="444" fill="#1e293b" font-size="12" font-family="monospace">attribute_id  UUID FK  ──&gt; attributes.id</text>
  <text x="240" y="464" fill="#1e293b" font-size="12" font-family="monospace">locale        VARCHAR  (e.g. "de", "en")</text>
  <text x="240" y="484" fill="#1e293b" font-size="12" font-family="monospace">value         JSON     (flexible value type)</text>

  <!-- Relationship Lines -->
  <!-- products -> product_attribute_values -->
  <line x1="150" y1="260" x2="150" y2="380" stroke="#64748b" stroke-width="2"/>
  <line x1="150" y1="380" x2="218" y2="380" stroke="#64748b" stroke-width="2" marker-end="url(#erArrow)"/>
  <text x="100" y="320" fill="#64748b" font-size="12" font-weight="bold">1</text>
  <text x="195" y="373" fill="#64748b" font-size="12" font-weight="bold">n</text>

  <!-- attributes -> product_attribute_values -->
  <line x1="720" y1="300" x2="720" y2="410" stroke="#64748b" stroke-width="2"/>
  <line x1="720" y1="410" x2="642" y2="410" stroke="#64748b" stroke-width="2" marker-end="url(#erArrow)"/>
  <text x="730" y="340" fill="#64748b" font-size="12" font-weight="bold">1</text>
  <text x="650" y="403" fill="#64748b" font-size="12" font-weight="bold">n</text>

  <!-- Self-referencing: products.parent_id -->
  <path d="M 30 120 C -20 120, -20 180, 30 180" stroke="#64748b" stroke-width="2" fill="none" stroke-dasharray="5,3" marker-end="url(#erArrow)"/>
  <text x="-10" y="155" fill="#64748b" font-size="10" text-anchor="middle">parent</text>

  <!-- Labels -->
  <rect x="310" y="290" width="250" height="30" rx="5" fill="#fef3c7" stroke="#f59e0b" stroke-width="1"/>
  <text x="435" y="310" text-anchor="middle" fill="#92400e" font-size="11" font-weight="bold">Entity-Attribute-Value Assignment</text>
</svg>

### How It Works

The three tables work together as follows:

1. **`products`** stores the core identity of a product (SKU, status, parent reference for variants). The table intentionally contains no product-specific columns like "color" or "weight".

2. **`attributes`** defines all possible product properties: their code (machine name), type (text, number, select, ...), whether they are translatable, and which validation rules apply.

3. **`product_attribute_values`** connects both: Each row represents the assignment of a specific value for a particular attribute to a particular product. The `locale` column enables multilingual values, and the `value` column stores the actual content as a JSON type, providing flexibility regarding data types.

### Query Example

```sql
-- Load all German-language values of a product
SELECT a.code, a.type, pav.value
FROM product_attribute_values pav
JOIN attributes a ON a.id = pav.attribute_id
WHERE pav.product_id = '550e8400-e29b-41d4-a716-446655440000'
  AND pav.locale = 'de';
```

Since this type of query requires numerous JOINs when dealing with many attributes, the materialized search index provides a performant alternative for read and search operations.

---

## UUID Primary Keys

All tables use UUIDs (`CHAR(36)`) as primary keys. The decision in favor of UUIDs is based on the following considerations:

- **Conflict-free**: IDs can be generated client-side without coordinating with the database
- **Security**: UUIDs are not predictable, unlike auto-incrementing integers
- **Scalability**: No ID conflicts arise with future horizontal scaling
- **Reference stability**: IDs remain stable across export/import cycles

MySQL 8 supports UUIDs efficiently via `BINARY(16)` storage with `UUID_TO_BIN()` functions, which optimizes storage consumption and index performance.

---

## JSON Columns

Several tables use MySQL JSON columns for semi-structured data:

| Table | Column | Usage |
|---|---|---|
| `product_attribute_values` | `value` | Flexible value type: String, number, array, object |
| `products` | `metadata` | Product-related metadata (source system, import information) |
| `attributes` | `validation` | Validation rules as JSON schema |
| `export_templates` | `config` | Template configuration with field mappings |
| `import_jobs` | `error_log` | Structured error log |
| `system_settings` | `value` | Arbitrary configuration values |

MySQL 8 allows indexing of individual JSON paths via generated columns, making targeted queries on JSON content performant.

---

## Materialized Search Index

The `products_search_index` table is a denormalized representation of product data, optimized for fast read and search operations.

### Structure

```sql
CREATE TABLE products_search_index (
    product_id    CHAR(36) PRIMARY KEY,
    sku           VARCHAR(255),
    status        VARCHAR(50),
    hierarchy_path TEXT,
    searchable_text TEXT,          -- Aggregated full text of all attribute values
    filter_data   JSON,            -- Facetable attribute values
    sort_data     JSON,            -- Pre-computed sort values
    created_at    TIMESTAMP,
    updated_at    TIMESTAMP,
    FULLTEXT INDEX ft_search (searchable_text)
) ENGINE=InnoDB;
```

### Update Strategy

The index is updated in an **event-driven** manner:

1. Each change to `product_attribute_values` triggers a `ProductValueChanged` event
2. A listener checks whether the affected value is included in the search index
3. If so, a queue job is created to recalculate the index entry
4. The job aggregates all relevant values and updates the corresponding row

For bulk operations (import, batch updates), the update is batched to avoid overloading the database with individual updates.
