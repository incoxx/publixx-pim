---
title: Excel Format
---

# Excel Format

This page documents the structure of the Excel import file in detail. It describes the structure of all 14 worksheets (tabs), their dependencies on each other, and the column definitions of the most important tabs.

## Overview: 14 Tabs with Dependencies

The tabs are processed in a defined order. Later tabs can reference data from earlier tabs or data already present in the system.

| No. | Tab Name | Identifier | Depends On |
|---|---|---|---|
| 01 | `Languages` | ISO 639-1 code | -- |
| 02 | `Attribute Groups` | Technical name | `01_Languages` |
| 03 | `Unit Groups` | Technical name | `01_Languages` |
| 04 | `Units` | Technical name | `03_Unit Groups` |
| 05 | `Attributes` | Technical name | `02_Attribute Groups`, `03_Unit Groups`, `07_Value Lists` |
| 06 | `Hierarchies` | Hierarchy name + level | -- |
| 07 | `Value Lists` | Technical name | `01_Languages` |
| 08 | `Products` | SKU | `06_Hierarchies` |
| 09 | `Product Values` | SKU + attribute + language + index | `08_Products`, `05_Attributes`, `01_Languages` |
| 10 | `Variants` | Variant SKU | `08_Products` |
| 11 | `Variant Values` | Variant SKU + attribute + language + index | `10_Variants`, `05_Attributes` |
| 12 | `Media` | SKU + media URL | `08_Products` |
| 13 | `Prices` | SKU + price type + currency | `08_Products` |
| 14 | `Relations` | Source SKU + target SKU + type | `08_Products` |

::: info Processing Order
Although the tabs are numbered, the system automatically resolves circular dependencies (e.g., `05_Attributes` references `07_Value Lists`) by performing validation in multiple passes.
:::

## Tab 05: Attributes

The Attributes tab is one of the most complex tabs and defines the entire attribute schema of the PIM. Each row describes an attribute with 19 columns.

| Column | Field Name | Required | Data Type | Description |
|---|---|---|---|---|
| A | `technical_name` | Yes | String | Unique technical identifier (e.g., `gewicht`, `farbe_ral`) |
| B | `name_de` | Yes | String | Display name in German |
| C | `name_en` | No | String | Display name in English |
| D | `description` | No | String | Description of the attribute |
| E | `data_type` | Yes | Enum | Data type: `text`, `number`, `boolean`, `date`, `datetime`, `json`, `richtext` |
| F | `attribute_group` | Yes | Reference | Technical name of the attribute group |
| G | `value_list` | No | Reference | Technical name of the value list (only for selection attributes) |
| H | `unit_group` | No | Reference | Technical name of the unit group |
| I | `default_unit` | No | Reference | Technical name of the default unit |
| J | `repeatable` | No | Boolean | Attribute can have multiple values (`true`/`false`) |
| K | `max_repetitions` | No | Integer | Maximum number of repetitions (only when `repeatable = true`) |
| L | `translatable` | No | Boolean | Attribute values are language-dependent (`true`/`false`) |
| M | `required` | No | Boolean | Required field when creating products (`true`/`false`) |
| N | `unique` | No | Boolean | Value must be unique system-wide (`true`/`false`) |
| O | `searchable` | No | Boolean | Attribute is included in full-text search (`true`/`false`) |
| P | `inheritable` | No | Boolean | Value is inherited by variants (`true`/`false`) |
| Q | `parent_attribute` | No | Reference | Technical name of the parent attribute (for nested attributes) |
| R | `source_system` | No | String | Source system identifier (e.g., `ERP`, `PIM`, `Webshop`) |
| S | `views` | No | String | Comma-separated list of attribute views (e.g., `basis,detail,export`) |

### Example Row

| technical_name | name_de | name_en | data_type | attribute_group | translatable | required | searchable | inheritable |
|---|---|---|---|---|---|---|---|---|
| `gewicht_netto` | Nettogewicht | Net Weight | `number` | `technische_daten` | `false` | `true` | `true` | `true` |

### Notes

- **Boolean values**: Accepted values are `true`/`false`, `1`/`0`, `ja`/`nein`, and `yes`/`no`.
- **References**: All references use the technical name of the target entity. Resolution to UUIDs is performed automatically.
- **Default values**: Unfilled optional Boolean fields are interpreted as `false`.

## Tab 06: Hierarchies

Hierarchies are imported as a flat table with up to six levels. Each row represents a path from the root node to the deepest specified node.

| Column | Field Name | Required | Data Type | Description |
|---|---|---|---|---|
| A | `hierarchy_name` | Yes | String | Name of the hierarchy |
| B | `type` | Yes | Enum | Hierarchy type: `master` or `output` |
| C | `level_1` | Yes | String | Node at level 1 (root) |
| D | `level_2` | No | String | Node at level 2 |
| E | `level_3` | No | String | Node at level 3 |
| F | `level_4` | No | String | Node at level 4 |
| G | `level_5` | No | String | Node at level 5 |
| H | `level_6` | No | String | Node at level 6 (leaf) |

### Example Rows

| hierarchy_name | type | level_1 | level_2 | level_3 |
|---|---|---|---|---|
| Product Catalog | master | Tools | Power Tools | Drills |
| Product Catalog | master | Tools | Power Tools | Grinders |
| Product Catalog | master | Tools | Hand Tools | Screwdrivers |
| Webshop | output | DIY | Drilling & Screwing | -- |

### Notes

- **Merging**: Rows with identical prefixes (same upper levels) are automatically merged into a tree.
- **Master vs. Output**: `master` hierarchies are used for internal product classification, `output` hierarchies for structuring exports.
- **Empty levels**: Empty cells at the end of a row indicate that the path ends at the last filled level.

## Tab 08: Products

The Products tab contains the master data for each product.

| Column | Field Name | Required | Data Type | Description |
|---|---|---|---|---|
| A | `sku` | Yes | String | Unique article number (Stock Keeping Unit) |
| B | `name_de` | Yes | String | Product name in German |
| C | `name_en` | No | String | Product name in English |
| D | `product_type` | Yes | Enum | Product type: `simple`, `configurable` |
| E | `ean` | No | String | European Article Number (EAN/GTIN) |
| F | `status` | No | Enum | Status: `draft`, `active`, `inactive` (default: `draft`) |

### Example Rows

| sku | name_de | name_en | product_type | ean | status |
|---|---|---|---|---|---|
| `BM-2000-PRO` | Bohrmaschine Pro 2000 | Drill Machine Pro 2000 | `simple` | `4012345678901` | `active` |
| `ABS-SERIE-X` | Akkubohrschrauber Serie X | Cordless Drill Series X | `configurable` | -- | `draft` |

### Notes

- **Upsert**: If a product with the same SKU already exists, it will be updated.
- **Product type `configurable`**: Products of this type can have variants, which are defined in the `10_Variants` tab.
- **Status**: The `draft` status is the default value. Only products with `active` status are included in exports.

## Tab 09: Product Values

The Product Values tab assigns attribute values to products. The combination of SKU, attribute, language, and index forms the unique key.

| Column | Field Name | Required | Data Type | Description |
|---|---|---|---|---|
| A | `sku` | Yes | Reference | SKU of the product (from tab `08_Products`) |
| B | `attribute` | Yes | Reference | Technical name of the attribute (from tab `05_Attributes`) |
| C | `value` | Yes | Mixed | The attribute value (type depends on the attribute) |
| D | `unit` | No | Reference | Technical name of the unit (for attributes with a unit group) |
| E | `language` | No | String | ISO 639-1 language code (only for translatable attributes, e.g., `de`, `en`) |
| F | `index` | No | Integer | Repetition index (only for repeatable attributes, default: `1`) |

### Example Rows

| sku | attribute | value | unit | language | index |
|---|---|---|---|---|---|
| `BM-2000-PRO` | `gewicht_netto` | `2.5` | `kg` | -- | -- |
| `BM-2000-PRO` | `beschreibung` | Leistungsstarke Bohrmaschine | -- | `de` | -- |
| `BM-2000-PRO` | `beschreibung` | Powerful drill machine | -- | `en` | -- |
| `BM-2000-PRO` | `zertifikat` | CE | -- | -- | `1` |
| `BM-2000-PRO` | `zertifikat` | GS | -- | -- | `2` |

### Notes

- **Language**: Only required for attributes configured as `translatable`. For non-translatable attributes, the column remains empty.
- **Index**: Only required for attributes configured as `repeatable`. The index starts at `1`.
- **Unit**: Only required for attributes assigned to a unit group.
- **Upsert**: If a value with the same combination of SKU, attribute, language, and index already exists, it will be updated.

## General Rules

### Upsert Logic in Detail

The following upsert logic applies to each tab:

| Situation | Behavior |
|---|---|
| Identifier does **not** exist in the system | Record is **created** |
| Identifier **already** exists in the system | Record is **updated** (only filled fields) |
| Identifier exists **twice** in the **same file** | Reported as an error |

### Encoding and Formatting

- **Character set**: UTF-8 (standard for .xlsx files)
- **Date format**: ISO 8601 (`YYYY-MM-DD`) or German format (`DD.MM.YYYY`)
- **Decimal separator**: Period (`.`) as decimal separator (e.g., `12.50`)
- **Boolean values**: `true`/`false`, `1`/`0`, `ja`/`nein`, `yes`/`no`
- **Empty cells**: Are interpreted as "not specified" and do **not** overwrite existing values

::: warning Note
If you want to explicitly delete an existing value, use the special placeholder `__NULL__` as the cell value.
:::

## Further Documentation

- [Import Overview](/en/import/) -- Process overview and concept
- [Validation](/en/import/validation) -- Validation rules and error messages
