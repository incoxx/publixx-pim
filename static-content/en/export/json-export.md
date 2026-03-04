---
title: JSON Export
---

# JSON Export

The JSON export provides product data via REST API endpoints in a structured JSON format. It is suitable for integration with webshops, ERP systems, marketplaces, and other third-party systems.

## Endpoints

### Export Products

```
GET /api/v1/export/products
```

Exports products with filtering and pagination. Suitable for regular synchronizations and browsing-style access.

### Bulk Export

```
POST /api/v1/export/products/bulk
```

Exports a large volume of products in a single call. The export is processed as a background job and the result can be retrieved afterwards. Suitable for full exports and initial data migrations.

**Request Body:**

```json
{
  "filters": {
    "status": "active",
    "hierarchy_path": "Werkzeuge/Elektrowerkzeuge"
  },
  "include": ["media", "prices", "relations"],
  "format": "nested",
  "lang": "de"
}
```

### PQL-Based Export

```
POST /api/v1/export/query
```

Exports products based on a PQL query. Offers the greatest flexibility in filtering.

**Request Body:**

```json
{
  "query": "SELECT sku, name, gewicht FROM products WHERE status = 'active' AND kategorie = 'Elektrowerkzeuge'",
  "include": ["media"],
  "format": "flat",
  "lang": "de"
}
```

## Filter Parameters

The following parameters are available for the `GET /api/v1/export/products` endpoint:

| Parameter | Type | Description | Example |
|---|---|---|---|
| `status` | String | Filter by product status | `status=active` |
| `hierarchy_node` | UUID | Products of a hierarchy node | `hierarchy_node=uuid-hier` |
| `hierarchy_path` | String | Products of a hierarchy path | `hierarchy_path=Werkzeuge/Bohrer` |
| `attribute[name]` | String | Attribute value filter | `attribute[farbe]=rot` |
| `view` | String | Restrict to attribute view | `view=export` |
| `output_hierarchy` | UUID | Output hierarchy for structuring | `output_hierarchy=uuid-hier` |
| `updated_after` | ISO 8601 | Delta export from timestamp | `updated_after=2025-01-15T08:00:00Z` |

### Filter Examples

**Active products of a category:**

```
GET /api/v1/export/products?status=active&hierarchy_path=Werkzeuge/Elektrowerkzeuge
```

**Delta export since last synchronization:**

```
GET /api/v1/export/products?updated_after=2025-06-15T10:00:00Z&status=active
```

**Products with a specific attribute value:**

```
GET /api/v1/export/products?attribute[marke]=Bosch&attribute[leistung_min]=500
```

## Include Options

Additional data per product can be loaded via the `include` parameter:

| Value | Description |
|---|---|
| `media` | Assigned media (images, documents, videos) with URLs |
| `prices` | Prices with price type, currency, and validity |
| `relations` | Product relations (accessories, spare parts, cross-references) |
| `variants` | Variants with all attribute values |

**Multiple include values:**

```
GET /api/v1/export/products?include=media,prices,relations
```

## Language Selection

The `lang` parameter determines in which language translatable attribute values are exported:

```
GET /api/v1/export/products?lang=de
GET /api/v1/export/products?lang=en
```

If no `lang` parameter is specified, all available languages are exported as a nested object:

```json
{
  "name": {
    "de": "Akkubohrschrauber Pro",
    "en": "Cordless Drill Pro"
  }
}
```

## Format Options

The `format` parameter determines the structure of the exported data:

### `flat` -- Flat Structure

All attribute values as simple key-value pairs at the top level:

```json
{
  "sku": "ABS-100-PRO",
  "name": "Akkubohrschrauber Pro",
  "status": "active",
  "gewicht_netto": { "value": 1.8, "unit": "kg" },
  "farbe": "Blau",
  "leistung": { "value": 18, "unit": "V" },
  "beschreibung": "Leistungsstarker Akkubohrschrauber..."
}
```

### `nested` -- Nested Structure

Attribute values grouped by attribute groups:

```json
{
  "sku": "ABS-100-PRO",
  "name": "Akkubohrschrauber Pro",
  "status": "active",
  "attribute_groups": {
    "technische_daten": {
      "gewicht_netto": { "value": 1.8, "unit": "kg" },
      "leistung": { "value": 18, "unit": "V" }
    },
    "allgemein": {
      "farbe": "Blau",
      "beschreibung": "Leistungsstarker Akkubohrschrauber..."
    }
  }
}
```

### `publixx` -- Publixx Format

Transformation according to Publixx mapping rules. For details, see [Publixx Export](/en/export/publixx-export).

## Pagination

The `GET /api/v1/export/products` endpoint uses cursor-based pagination:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | Integer | `1` | Current page |
| `per_page` | Integer | `50` | Number of products per page (max. 500) |

### Response Structure with Pagination

```json
{
  "data": [
    { "sku": "ABS-100-PRO", "name": "..." },
    { "sku": "BM-2000", "name": "..." }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1250,
    "total_pages": 25
  },
  "links": {
    "first": "/api/v1/export/products?page=1&per_page=50",
    "last": "/api/v1/export/products?page=25&per_page=50",
    "next": "/api/v1/export/products?page=2&per_page=50",
    "prev": null
  }
}
```

## Complete Example

**Request:**

```bash
curl -X GET "https://pim.example.com/api/v1/export/products?status=active&hierarchy_path=Werkzeuge/Elektrowerkzeuge&include=media,prices&format=nested&lang=de&per_page=2" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Response:**

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "sku": "ABS-100-PRO",
      "name": "Akkubohrschrauber Pro",
      "product_type": "simple",
      "status": "active",
      "attribute_groups": {
        "technische_daten": {
          "gewicht_netto": { "value": 1.8, "unit": "kg" },
          "spannung": { "value": 18, "unit": "V" },
          "drehmoment_max": { "value": 60, "unit": "Nm" }
        },
        "allgemein": {
          "beschreibung": "Leistungsstarker Akkubohrschrauber für den professionellen Einsatz.",
          "marke": "PowerTool"
        }
      },
      "media": [
        {
          "type": "image",
          "url": "https://pim.example.com/storage/media/abs-100-pro-front.jpg",
          "sort_order": 1
        }
      ],
      "prices": [
        {
          "price_type": "UVP",
          "amount": 189.99,
          "currency": "EUR",
          "valid_from": "2025-01-01",
          "valid_to": null
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 2,
    "total": 42,
    "total_pages": 21
  }
}
```

## Further Documentation

- [Export Overview](/en/export/) -- Process overview and concept
- [Publixx Export](/en/export/publixx-export) -- Mapping configuration and PXF integration
- [API Reference](/en/api/) -- Complete API documentation
