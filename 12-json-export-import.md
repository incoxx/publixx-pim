# 12 — JSON Export/Import & Export Jobs

## Overview

The JSON Export/Import enables full exchange of all PIM data in a structured, human-readable JSON format. The Export Job management allows creating named, reusable export configurations.

---

## JSON Format

### Metadata

Each JSON export file begins with a `_meta` block:

```json
{
  "_meta": {
    "format": "anypim-json",
    "version": "1.0",
    "exported_at": "2026-03-06T14:30:00+01:00",
    "sections": [
      "unit_groups", "units", "attribute_views", "attribute_groups",
      "value_lists", "attributes", "product_types", "price_types",
      "relation_types", "hierarchies", "hierarchy_attribute_assignments",
      "products", "product_attribute_values", "variants",
      "product_hierarchy_assignments", "product_relations",
      "prices", "media_assignments"
    ],
    "filter": {
      "status": "active"
    },
    "counts": {
      "unit_groups": 5,
      "products": 1234
    }
  }
}
```

### Sections in Dependency Order

The order ensures that dependencies already exist during import:

| #  | Section                          | Depends on                         | Import |
|----|----------------------------------|------------------------------------|--------|
| 1  | `unit_groups`                    | —                                  | ✓      |
| 2  | `units`                          | unit_groups                        | ✓      |
| 3  | `attribute_views`                | —                                  | ✓      |
| 4  | `attribute_groups`               | —                                  | ✓      |
| 5  | `value_lists`                    | —                                  | ✓      |
| 6  | `attributes`                     | attribute_groups, value_lists, units, attribute_views | ✓ |
| 7  | `product_types`                  | —                                  | ✓      |
| 8  | `price_types`                    | —                                  | ✓      |
| 9  | `relation_types`                 | —                                  | ✓      |
| 10 | `hierarchies`                    | —                                  | ✓      |
| 11 | `hierarchy_attribute_assignments`| hierarchies, attributes            | ✓      |
| 12 | `products`                       | product_types                      | ✓      |
| 13 | `product_attribute_values`       | products, attributes               | ✓      |
| 14 | `variants`                       | products                           | ✓      |
| 15 | `product_hierarchy_assignments`  | products, hierarchies              | ✓      |
| 16 | `product_relations`              | products, relation_types           | ✓      |
| 17 | `prices`                         | products, price_types              | ✓      |
| 18 | `media_assignments`              | products                           | ✓      |

---

## JSON Schema per Section

### `unit_groups`

```json
{
  "unit_groups": [
    {
      "technical_name": "length",
      "name_de": "Länge",
      "name_en": "Length"
    }
  ]
}
```

| Field           | Type   | Required | Description                          |
|-----------------|--------|----------|--------------------------------------|
| technical_name  | string | ✓        | Unique technical name                |
| name_de         | string | ✓        | German display name                  |
| name_en         | string |          | English display name                 |

### `units`

```json
{
  "units": [
    {
      "technical_name": "mm",
      "abbreviation": "mm",
      "unit_group": "length",
      "conversion_factor": 1.0,
      "is_base_unit": true
    }
  ]
}
```

| Field             | Type    | Required | Description                        |
|-------------------|---------|----------|------------------------------------|
| technical_name    | string  | ✓        | Technical name of the unit         |
| abbreviation      | string  | ✓        | Abbreviation (e.g. "mm", "kg")     |
| unit_group        | string  | ✓        | Reference to unit_groups.technical_name |
| conversion_factor | number  |          | Conversion factor (default: 1)     |
| is_base_unit      | boolean |          | Base unit of the group?            |

### `attribute_views`

```json
{
  "attribute_views": [
    {
      "technical_name": "marketing",
      "name_de": "Marketing-Sicht",
      "name_en": "Marketing View",
      "description": "Attributes for marketing purposes"
    }
  ]
}
```

| Field          | Type   | Required | Description             |
|----------------|--------|----------|-------------------------|
| technical_name | string | ✓        | Technical name          |
| name_de        | string | ✓        | German name             |
| name_en        | string |          | English name            |
| description    | string |          | Description             |

### `attribute_groups`

```json
{
  "attribute_groups": [
    {
      "technical_name": "dimensions",
      "name_de": "Abmessungen",
      "name_en": "Dimensions",
      "description": "Measurements and weight",
      "sort_order": 10
    }
  ]
}
```

| Field          | Type   | Required | Description             |
|----------------|--------|----------|-------------------------|
| technical_name | string | ✓        | Technical name          |
| name_de        | string | ✓        | German name             |
| name_en        | string |          | English name            |
| description    | string |          | Description             |
| sort_order     | int    |          | Sort order              |

### `value_lists`

Value lists with nested entries:

```json
{
  "value_lists": [
    {
      "technical_name": "colors",
      "name_de": "Farben",
      "entries": [
        {
          "technical_name": "red",
          "display_value_de": "Rot",
          "display_value_en": "Red",
          "sort_order": 1
        },
        {
          "technical_name": "blue",
          "display_value_de": "Blau",
          "display_value_en": "Blue",
          "sort_order": 2
        }
      ]
    }
  ]
}
```

**Value list:**

| Field          | Type   | Required | Description             |
|----------------|--------|----------|-------------------------|
| technical_name | string | ✓        | Technical name          |
| name_de        | string | ✓        | German name             |
| entries        | array  |          | List entries            |

**Entry:**

| Field            | Type   | Required | Description             |
|------------------|--------|----------|-------------------------|
| technical_name   | string | ✓        | Technical name          |
| display_value_de | string | ✓        | German display value    |
| display_value_en | string |          | English display value   |
| sort_order       | int    |          | Sort order              |

### `attributes`

```json
{
  "attributes": [
    {
      "technical_name": "weight",
      "name_de": "Gewicht",
      "name_en": "Weight",
      "description": "Product weight including packaging",
      "data_type": "number",
      "attribute_group": "dimensions",
      "value_list": null,
      "unit_group": "weight",
      "default_unit": "kg",
      "is_multipliable": false,
      "max_multiplied": null,
      "is_translatable": false,
      "is_mandatory": true,
      "is_unique": false,
      "is_searchable": true,
      "is_inheritable": true,
      "parent_attribute": null,
      "source_system": null,
      "views": ["marketing", "logistics"]
    }
  ]
}
```

| Field            | Type     | Required | Description                               |
|------------------|----------|----------|-------------------------------------------|
| technical_name   | string   | ✓        | Unique technical name                     |
| name_de          | string   | ✓        | German name                               |
| name_en          | string   |          | English name                              |
| data_type        | string   | ✓        | text, number, date, boolean, select, multiselect, composite, html |
| attribute_group  | string   |          | Reference to attribute_groups             |
| value_list       | string   |          | Reference to value_lists (for select)     |
| unit_group       | string   |          | Reference to unit_groups                  |
| default_unit     | string   |          | Default unit                              |
| is_multipliable  | boolean  |          | Multiple values allowed?                  |
| max_multiplied   | int/null |          | Maximum number of multiple values         |
| is_translatable  | boolean  |          | Multilingual?                             |
| is_mandatory     | boolean  |          | Required field?                           |
| is_unique        | boolean  |          | Value must be unique?                     |
| is_searchable    | boolean  |          | Searchable?                               |
| is_inheritable   | boolean  |          | Inheritable to variants?                  |
| parent_attribute | string   |          | Parent attribute (composite)              |
| source_system    | string   |          | Source system                             |
| views            | array    |          | Assigned attribute views                  |

### `product_types`

```json
{
  "product_types": [
    {
      "technical_name": "power_tool",
      "name_de": "Elektrowerkzeug",
      "name_en": "Power Tool",
      "description": "Electrically powered tools",
      "has_variants": true,
      "has_ean": true,
      "has_prices": true,
      "has_media": true
    }
  ]
}
```

| Field          | Type    | Required | Description             |
|----------------|---------|----------|-------------------------|
| technical_name | string  | ✓        | Technical name          |
| name_de        | string  | ✓        | German name             |
| name_en        | string  |          | English name            |
| description    | string  |          | Description             |
| has_variants   | boolean |          | Variants allowed?       |
| has_ean        | boolean |          | EAN field active?       |
| has_prices     | boolean |          | Prices assignable?      |
| has_media      | boolean |          | Media assignable?       |

### `price_types`

```json
{
  "price_types": [
    {
      "technical_name": "retail",
      "name_de": "UVP",
      "name_en": "Retail Price"
    }
  ]
}
```

### `relation_types`

```json
{
  "relation_types": [
    {
      "technical_name": "accessory",
      "name_de": "Zubehör",
      "name_en": "Accessory",
      "is_bidirectional": false
    }
  ]
}
```

### `hierarchies`

Hierarchies with nested nodes (Materialized Path):

```json
{
  "hierarchies": [
    {
      "technical_name": "main_category",
      "name_de": "Hauptkategorie",
      "hierarchy_type": "master",
      "nodes": [
        {
          "path": "Werkzeuge",
          "name_de": "Werkzeuge",
          "name_en": "Tools"
        },
        {
          "path": "Werkzeuge/Elektrowerkzeuge",
          "name_de": "Elektrowerkzeuge",
          "name_en": "Power Tools"
        },
        {
          "path": "Werkzeuge/Elektrowerkzeuge/Bohrmaschinen",
          "name_de": "Bohrmaschinen",
          "name_en": "Drills"
        }
      ]
    }
  ]
}
```

**Hierarchy:**

| Field          | Type   | Required | Description             |
|----------------|--------|----------|-------------------------|
| technical_name | string | ✓        | Technical name          |
| name_de        | string | ✓        | German name             |
| hierarchy_type | string |          | master, sales, web, ... |
| nodes          | array  |          | Hierarchy nodes         |

**Node:**

| Field   | Type   | Required | Description                       |
|---------|--------|----------|-----------------------------------|
| path    | string | ✓        | Materialized Path (e.g. "A/B/C") |
| name_de | string |          | German name                       |
| name_en | string |          | English name                      |

### `hierarchy_attribute_assignments`

```json
{
  "hierarchy_attribute_assignments": [
    {
      "hierarchy": "main_category",
      "node_path": "Werkzeuge/Elektrowerkzeuge",
      "attribute": "voltage",
      "collection_name": "Technische Daten",
      "collection_sort": 1,
      "attribute_sort": 10,
      "dont_inherit": false
    }
  ]
}
```

| Field           | Type    | Required | Description                           |
|-----------------|---------|----------|---------------------------------------|
| hierarchy       | string  | ✓        | Reference to hierarchies              |
| node_path       | string  | ✓        | Path of the hierarchy node            |
| attribute       | string  | ✓        | Reference to attributes               |
| collection_name | string  |          | Name of the attribute group at the node |
| collection_sort | int     |          | Group sort order                      |
| attribute_sort  | int     |          | Attribute sort order within the group |
| dont_inherit    | boolean |          | Prevent inheritance to child nodes?   |

### `products`

```json
{
  "products": [
    {
      "sku": "BM-2024-001",
      "name": "Bohrmaschine ProMax 800",
      "name_en": "Drill ProMax 800",
      "product_type": "power_tool",
      "ean": "4006209001234",
      "status": "active"
    }
  ]
}
```

| Field        | Type   | Required | Description                              |
|--------------|--------|----------|------------------------------------------|
| sku          | string | ✓        | Unique article number                    |
| name         | string | ✓        | Product name (de)                        |
| name_en      | string |          | English product name                     |
| product_type | string | ✓        | Reference to product_types               |
| ean          | string |          | EAN/GTIN                                 |
| status       | string |          | draft, active, inactive (default: draft) |

### `product_attribute_values`

```json
{
  "product_attribute_values": [
    {
      "sku": "BM-2024-001",
      "attribute": "weight",
      "value": "2.5",
      "unit": "kg",
      "language": null,
      "index": 0
    },
    {
      "sku": "BM-2024-001",
      "attribute": "description",
      "value": "Leistungsstarke Bohrmaschine für den Profi",
      "unit": null,
      "language": "de",
      "index": 0
    }
  ]
}
```

| Field     | Type        | Required | Description                               |
|-----------|-------------|----------|-------------------------------------------|
| sku       | string      | ✓        | Reference to products.sku                 |
| attribute | string      | ✓        | Reference to attributes.technical_name    |
| value     | string/null | ✓        | Attribute value as string                 |
| unit      | string      |          | Unit (for numeric attributes)             |
| language  | string      |          | Language code (de, en) for translatable attributes |
| index     | int         |          | Index for multiple values (default: 0)    |

### `variants`

```json
{
  "variants": [
    {
      "parent_sku": "BM-2024-001",
      "sku": "BM-2024-001-BL",
      "name": "Bohrmaschine ProMax 800 Blau",
      "name_en": "Drill ProMax 800 Blue",
      "ean": "4006209001235",
      "status": "active"
    }
  ]
}
```

| Field      | Type   | Required | Description                        |
|------------|--------|----------|------------------------------------|
| parent_sku | string | ✓        | SKU of the main product            |
| sku        | string | ✓        | Unique variant SKU                 |
| name       | string | ✓        | Variant name (de)                  |
| name_en    | string |          | English variant name               |
| ean        | string |          | EAN/GTIN of the variant            |
| status     | string |          | draft, active, inactive            |

### `product_hierarchy_assignments`

```json
{
  "product_hierarchy_assignments": [
    {
      "sku": "BM-2024-001",
      "hierarchy": "main_category",
      "node_path": "Werkzeuge/Elektrowerkzeuge/Bohrmaschinen"
    }
  ]
}
```

| Field     | Type   | Required | Description                        |
|-----------|--------|----------|------------------------------------|
| sku       | string | ✓        | Reference to products.sku          |
| hierarchy | string | ✓        | Reference to hierarchies           |
| node_path | string | ✓        | Path of the hierarchy node         |

### `product_relations`

```json
{
  "product_relations": [
    {
      "source_sku": "BM-2024-001",
      "target_sku": "BZ-2024-010",
      "relation_type": "accessory",
      "sort_order": 1
    }
  ]
}
```

| Field         | Type   | Required | Description                        |
|---------------|--------|----------|------------------------------------|
| source_sku    | string | ✓        | Source product SKU                  |
| target_sku    | string | ✓        | Target product SKU                 |
| relation_type | string | ✓        | Reference to relation_types        |
| sort_order    | int    |          | Sort order                         |

### `prices`

```json
{
  "prices": [
    {
      "sku": "BM-2024-001",
      "price_type": "retail",
      "amount": 299.99,
      "currency": "EUR",
      "valid_from": "2026-01-01",
      "valid_to": null,
      "country": "DE",
      "scale_from": null,
      "scale_to": null
    }
  ]
}
```

| Field      | Type        | Required | Description                        |
|------------|-------------|----------|------------------------------------|
| sku        | string      | ✓        | Reference to products.sku          |
| price_type | string      | ✓        | Reference to price_types           |
| amount     | number      | ✓        | Price (decimal number)             |
| currency   | string      |          | Currency (default: EUR)            |
| valid_from | date/null   |          | Valid from (Y-m-d)                 |
| valid_to   | date/null   |          | Valid until (Y-m-d)                |
| country    | string/null |          | Country code (DE, AT, CH, ...)     |
| scale_from | number/null |          | Scale pricing from quantity        |
| scale_to   | number/null |          | Scale pricing to quantity          |

### `media_assignments`

```json
{
  "media_assignments": [
    {
      "sku": "BM-2024-001",
      "file_name": "bohrmaschine-promax-800-front.jpg",
      "media_type": "image",
      "usage_type": "gallery",
      "title_de": "Produktfoto Vorderansicht",
      "title_en": "Product Photo Front View",
      "alt_text_de": "Bohrmaschine ProMax 800",
      "sort_order": 1,
      "is_primary": true
    }
  ]
}
```

| Field       | Type    | Required | Description                        |
|-------------|---------|----------|------------------------------------|
| sku         | string  | ✓        | Reference to products.sku          |
| file_name   | string  | ✓        | Filename of the media              |
| media_type  | string  |          | image, document, video, ...        |
| usage_type  | string  |          | gallery, datasheet, thumbnail, ... |
| title_de    | string  |          | German title                       |
| title_en    | string  |          | English title                      |
| alt_text_de | string  |          | Alt text (de)                      |
| sort_order  | int     |          | Sort order                         |
| is_primary  | boolean |          | Primary image?                     |

---

## REST API

### JSON Export

```
GET  /api/v1/json-export                Full export as JSON download
POST /api/v1/json-export                Filtered export
GET  /api/v1/json-export/sections       List available sections
```

**POST /api/v1/json-export** — Body:

```json
{
  "sections": ["products", "product_attribute_values", "prices"],
  "filter": {
    "status": "active",
    "product_type": "power_tool",
    "search_text": "Bohrmaschine",
    "updated_after": "2026-01-01",
    "skus": ["BM-2024-001"],
    "category_ids": ["uuid-..."]
  },
  "inline": false
}
```

| Parameter           | Type    | Description                                     |
|---------------------|---------|-------------------------------------------------|
| sections            | array   | Sections to export (empty = all)                |
| filter.status       | string  | draft, active, inactive                         |
| filter.product_type | string  | Product type technical_name                     |
| filter.search_text  | string  | Free text (SKU, name, EAN)                      |
| filter.updated_after | date   | Only products updated after this date           |
| filter.skus         | array   | Only specific SKUs                              |
| filter.category_ids | array   | Hierarchy node IDs                              |
| inline              | boolean | true = JSON response instead of download        |

### JSON Import

```
POST /api/v1/json-import                Import JSON file or body
POST /api/v1/json-import/validate       Validate without importing
```

**Import modes** (query parameter `mode`):
- `update` (default): Upsert — update existing records, create new ones
- `delete_insert`: Delete all affected data and re-insert

**File upload:**
```
POST /api/v1/json-import?mode=update
Content-Type: multipart/form-data

file: [JSON file]
```

**Raw JSON body:**
```
POST /api/v1/json-import?mode=update
Content-Type: application/json

{ "_meta": {...}, "products": [...] }
```

### Export Jobs

```
GET    /api/v1/export-jobs              List all jobs
POST   /api/v1/export-jobs              Create new job
GET    /api/v1/export-jobs/{id}         Job details
PUT    /api/v1/export-jobs/{id}         Update job
DELETE /api/v1/export-jobs/{id}         Delete job
POST   /api/v1/export-jobs/{id}/execute Execute job
GET    /api/v1/export-jobs/{id}/download Download latest file
```

**POST /api/v1/export-jobs** — Body:

```json
{
  "name": "Power Tools Export active",
  "description": "Active power tools as JSON",
  "format": "json",
  "sections": ["products", "product_attribute_values", "prices"],
  "filters": {
    "status": "active",
    "product_type": "power_tool"
  },
  "search_profile_id": null,
  "export_profile_id": null,
  "cron_expression": null,
  "is_active": true,
  "is_shared": true
}
```

**POST /api/v1/export-jobs/{id}/execute:**

```json
{ "async": true }
```

- `async: false` (default): Synchronous execution, result in response
- `async: true`: Job is queued (status 202)

---

## CLI Commands

### JSON Export

```bash
# Full export
php artisan pim:json-export

# Specific sections
php artisan pim:json-export --sections=products,prices,variants

# With filters
php artisan pim:json-export --status=active --product-type=power_tool

# Free text search
php artisan pim:json-export --search="Bohrmaschine"

# Only products updated after a date
php artisan pim:json-export --updated-after=2026-01-01

# Specify output file
php artisan pim:json-export --output=/tmp/export.json

# Compact JSON (without pretty-print)
php artisan pim:json-export --compact

# Show available sections
php artisan pim:json-export --sections-list
```

### JSON Import

```bash
# Import (upsert)
php artisan pim:json-import /path/to/file.json

# Import with delete-insert
php artisan pim:json-import /path/to/file.json --mode=delete_insert

# Validate only (no import)
php artisan pim:json-import /path/to/file.json --validate
```

### Export Jobs

```bash
# List all jobs
php artisan pim:export-job --list

# Execute job
php artisan pim:export-job {job-id}

# Create new job
php artisan pim:export-job --create --name="My Export" --format=json --filter-status=active

# Execute all scheduled jobs
php artisan pim:export-job --run-scheduled

# With output directory
php artisan pim:export-job {job-id} --output-dir=/tmp/exports
```

---

## Import Notes

### Validation

Before import, the JSON structure is automatically validated:
- `_meta` section must be present
- All sections must be arrays
- Products require: `sku`, `name`, `product_type`
- Attributes require: `technical_name`, `data_type`

### Transaction Safety

The import runs within a database transaction. If an error occurs, all changes are rolled back.

---

## Logging

- **Export**: `storage/logs/export-YYYY-MM-DD.log` (Channel: `export`, 30-day rotation)
- **Import**: `storage/logs/import-YYYY-MM-DD.log` (Channel: `import`)

Logged: start/end, duration, sections, file sizes, errors.
