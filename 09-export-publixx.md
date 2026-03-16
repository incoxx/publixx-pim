# anyPIM — Export & Publixx Integration

> **Purpose:** JSON export and Publixx integration. Use this skill when implementing the export engine, mapping configuration, and live API endpoints for Publixx.

---

## Concept

The PIM exports product data as JSON datasets that are directly usable as Publixx datasets. A configurable mapping translates PIM attributes into Publixx JSON fields.

---

## Export Endpoints

```
GET  /api/v1/export/products                           Filter → JSON array
GET  /api/v1/export/products/{id}                      Single product
POST /api/v1/export/products/bulk                      Bulk by filter
GET  /api/v1/export/products/{id}/publixx              Publixx dataset format
POST /api/v1/export/query                              PQL filter
```

### Publixx Live API

```
GET  /api/v1/publixx/datasets/{mapping_id}             All products of the mapping
GET  /api/v1/publixx/datasets/{mapping_id}/{product_id} Single dataset
POST /api/v1/publixx/datasets/{mapping_id}/pql          PQL-filtered
POST /api/v1/publixx/webhook                            Webhook from Publixx
```

---

## Filter Parameters

| Parameter | Example | Description |
|-----------|---------|-------------|
| filter[status] | active | Product status |
| filter[hierarchy_node] | uuid | All products under node |
| filter[hierarchy_path] | Elektro/Akkubohr | By path |
| filter[attribute.gewicht][gte] | 5 | Attribute value numeric |
| filter[attribute.farbe] | rot | Attribute value exact |
| filter[attribute.name][contains] | Bohr | Substring |
| filter[view] | eshop_view | Only attributes of a view |
| filter[output_hierarchy] | uuid | Output hierarchy |
| filter[updated_after] | 2025-01-01T00:00:00Z | Delta export |
| include_media | true | Media URLs |
| include_prices | true | Prices |
| include_relations | true | Relations |
| lang | de,en | Languages |
| format | publixx | flat / nested / publixx |

---

## Export Mapping (publixx_export_mappings)

### Entity

```
id, name, attribute_view_id (FK nullable), output_hierarchy_id (FK nullable),
mapping_rules (JSON), include_media, include_prices, include_variants,
include_relations, languages (JSON), flatten_mode ENUM('flat','nested','publixx')
```

### Mapping Rules

```json
{
  "rules": [
    { "source": "attribute:product-name-dict", "target": "productName", "type": "text" },
    { "source": "attribute:product-weight-num", "target": "specs.weight", "type": "unit_value" },
    { "source": "media:teaser", "target": "productImage", "type": "media_url" },
    { "source": "media:gallery", "target": "gallery", "type": "media_array" },
    { "source": "prices:list_price", "target": "preis.listenpreis", "type": "price" },
    { "source": "variants", "target": "varianten", "type": "variant_array" },
    { "source": "relations:accessory", "target": "zubehoer", "type": "relation_array" },
    { "source": "collection:technische_daten", "target": "technischeDaten", "type": "group" }
  ]
}
```

### Mapping Types

| type | source | Result |
|------|--------|--------|
| text | attribute:tech_name | `"productName": "Wert"` |
| unit_value | attribute:tech_name | `"specs.weight": { "value": 1.8, "unit": "kg" }` |
| media_url | media:usage_type | `"productImage": "https://..."` |
| media_array | media:usage_type | `"gallery": ["url1", "url2"]` |
| price | prices:price_type | `"preis.listenpreis": 189.99` |
| variant_array | variants | `"varianten": [{ "sku": "...", ... }]` |
| relation_array | relations:rel_type | `"zubehoer": [{ "sku": "...", ... }]` |
| group | collection:name | Groups attributes as object |

---

## Example: Exported Dataset (format=publixx)

```json
{
  "id": "abc-123",
  "sku": "EW-ABS-001",
  "ean": "4012345678901",
  "productName": "Akkubohrschrauber ProDrill 18V",
  "productName_en": "Cordless Drill ProDrill 18V",
  "status": "active",
  "hierarchy": "Elektrowerkzeuge > Akkubohrschrauber > mit Akku",
  "stammdaten": {
    "produktname": "Akkubohrschrauber ProDrill 18V",
    "sku": "EW-ABS-001",
    "ean": "4012345678901"
  },
  "technischeDaten": {
    "drehmoment": { "value": 60, "unit": "Nm" },
    "drehzahl": { "value": 1800, "unit": "min-1" },
    "akkuspannung": { "value": 18, "unit": "V" }
  },
  "verpackung": {
    "gewicht": { "value": 1.8, "unit": "kg" },
    "masse": {
      "laenge": { "value": 320, "unit": "mm" },
      "breite": { "value": 85, "unit": "mm" }
    }
  },
  "productImage": "https://pim.example.com/media/prodrill-18v.jpg",
  "gallery": ["https://pim.example.com/media/prodrill-front.jpg"],
  "preis": { "listenpreis": 189.99, "currency": "EUR" },
  "varianten": [
    { "sku": "EW-ABS-001-2AH", "name": "2.0 Ah Akku", "preis": 189.99 },
    { "sku": "EW-ABS-001-5AH", "name": "5.0 Ah Akku", "preis": 249.99 }
  ],
  "zubehoer": [
    { "sku": "ZB-BIT-SET", "name": "Bit-Set 32-teilig", "image": "..." }
  ]
}
```

---

## Publixx Element Binding

| Publixx Element | bind | PIM Source |
|-----------------|------|-----------|
| text (Name) | `"productName"` | Attribute |
| image (Main image) | `"productImage"` | Media teaser |
| text (Weight value) | `"specs.weight.value"` | Attribute (value) |
| text (Weight unit) | `"specs.weight.unit"` | Attribute (unit) |
| smartTable | `"varianten"` | Variants |
| group/repeater | `"zubehoer"` | Relations |
| barcode | `"ean"` | Base data |

---

## PQL on Exported Datasets (in Publixx)

```sql
SELECT * WHERE status = 'active' AND productImage EXISTS
SELECT * WHERE preis.listenpreis > 100
SELECT * WHERE productName LIKE '%Bohr%'
SELECT * WHERE hierarchy LIKE 'Elektrowerkzeuge%'
```

---

## Laravel Classes

```php
App\Services\Export\ExportService           // Orchestration
App\Services\Export\MappingResolver         // Mapping rules → JSON
App\Services\Export\DatasetBuilder          // Product → dataset
App\Services\Export\PublixxDatasetService   // Publixx-specific endpoints
App\Http\Controllers\Api\V1\ExportController
App\Http\Controllers\Api\V1\PublixxDatasetController
```
