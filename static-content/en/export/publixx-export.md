---
title: Publixx Export
---

# Publixx Export

The Publixx export transforms PIM product data into the Publixx-specific record format (PXF -- Publixx Exchange Format). Configurable mappings define which PIM fields are mapped to which Publixx record fields. The exported records can be used directly in Publixx catalogs and templates.

## Export Mapping Configuration

### Mapping Table

The assignment between PIM fields and Publixx record fields is configured in the database table `publixx_export_mappings`. Each entry describes a mapping rule.

| Field | Type | Description |
|---|---|---|
| `id` | UUID | Unique identifier |
| `name` | String | Name of the mapping (e.g., "Main Catalog 2025") |
| `source` | String | Source field in the PIM (attribute technical name or system field) |
| `target` | String | Target field in the Publixx record |
| `type` | Enum | Mapping type (determines the transformation) |
| `config` | JSON | Additional configuration depending on the mapping type |
| `sort_order` | Integer | Order of fields in the record |

### Mapping Types

| Type | Description | Source Field (Example) | Target Field (Example) |
|---|---|---|---|
| `text` | Simple text value, transferred 1:1 | `beschreibung` | `description` |
| `unit_value` | Value with unit, exported as a formatted string | `gewicht_netto` | `weight` |
| `media_url` | URL of the first assigned medium | `hauptbild` | `image_url` |
| `media_array` | Array of all assigned media URLs | `produktbilder` | `images` |
| `price` | Price value of a specific price type | `UVP` | `price` |
| `variant_array` | Array of all variants with defined fields | `varianten` | `variants` |
| `relation_array` | Array of all relations of a specific type | `zubehoer` | `accessories` |
| `group` | Groups multiple source fields under a target object | `technische_daten.*` | `specs` |

### Mapping Example

```json
[
  {
    "source": "name",
    "target": "title",
    "type": "text"
  },
  {
    "source": "beschreibung",
    "target": "description",
    "type": "text"
  },
  {
    "source": "gewicht_netto",
    "target": "weight",
    "type": "unit_value",
    "config": {
      "format": "{value} {unit}",
      "target_unit": "kg"
    }
  },
  {
    "source": "hauptbild",
    "target": "image",
    "type": "media_url",
    "config": {
      "media_type": "image",
      "position": 1
    }
  },
  {
    "source": "UVP",
    "target": "price",
    "type": "price",
    "config": {
      "currency": "EUR",
      "format": "decimal"
    }
  },
  {
    "source": "zubehoer",
    "target": "accessories",
    "type": "relation_array",
    "config": {
      "relation_type": "accessory",
      "fields": ["sku", "name"]
    }
  }
]
```

## Publixx Live API Endpoints

The following endpoints provide the interface between PIM and the Publixx platform:

### Retrieve Records

```
GET /api/v1/publixx/datasets
```

Returns all exported records in PXF format, filtered by mapping configuration.

**Query Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `mapping` | String | Name or ID of the mapping configuration |
| `status` | String | Product status (`active`) |
| `hierarchy_path` | String | Hierarchy path for restriction |
| `updated_after` | ISO 8601 | Delta export from timestamp |
| `lang` | String | Content language (ISO 639-1) |
| `page` | Integer | Page number |
| `per_page` | Integer | Records per page (max. 200) |

### Retrieve Single Record

```
GET /api/v1/publixx/datasets/{sku}
```

Returns the record of a single product.

### Records by PQL Query

```
POST /api/v1/publixx/datasets/query
```

Returns records based on a PQL query.

## PXF Template Management

PXF templates define the layout and structure of a Publixx catalog. They are managed in the PIM and linked to the exported records.

### Template Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/publixx/templates` | List all templates |
| `GET` | `/api/v1/publixx/templates/{id}` | Retrieve template details |
| `POST` | `/api/v1/publixx/templates` | Create new template |
| `PUT` | `/api/v1/publixx/templates/{id}` | Update template |
| `DELETE` | `/api/v1/publixx/templates/{id}` | Delete template |

### Template Structure

```json
{
  "id": "tpl-550e8400-e29b-41d4",
  "name": "Produktdatenblatt A4",
  "format": "A4",
  "orientation": "portrait",
  "elements": [
    {
      "id": "elem-title",
      "type": "text",
      "binding": "title",
      "position": { "x": 20, "y": 30 },
      "style": { "font_size": 24, "font_weight": "bold" }
    },
    {
      "id": "elem-image",
      "type": "image",
      "binding": "image",
      "position": { "x": 20, "y": 80 },
      "size": { "width": 200, "height": 200 }
    },
    {
      "id": "elem-specs",
      "type": "table",
      "binding": "specs",
      "position": { "x": 240, "y": 80 }
    }
  ]
}
```

## Publixx Element Binding

Each element in a PXF template has a `binding` property that defines which field of the exported record is bound to this element.

| Element Type | Binding Type | Description |
|---|---|---|
| `text` | Text value | Displays the value of the bound field as text |
| `image` | Media URL | Displays the image of the bound media URL |
| `table` | Object/Array | Renders the data as a table |
| `list` | Array | Renders the data as a list |
| `price` | Price value | Formats and displays the price value |
| `barcode` | String | Generates a barcode (EAN/GTIN) |

The resolution is done via the field name: the `binding` attribute references the `target` field of the mapping configuration.

## Example: Exported Record

The following example shows a fully exported Publixx record for a product "Cordless Drill":

```json
{
  "dataset_id": "ds-550e8400-e29b-41d4",
  "sku": "ABS-100-PRO",
  "title": "Akkubohrschrauber Pro 18V",
  "description": "Leistungsstarker Akkubohrschrauber mit bürstenlosem Motor für den professionellen Einsatz im Handwerk und der Industrie. Kompaktes Design mit ergonomischem Softgrip für ermüdungsfreies Arbeiten.",
  "image": "https://pim.example.com/storage/media/abs-100-pro-front.jpg",
  "images": [
    "https://pim.example.com/storage/media/abs-100-pro-front.jpg",
    "https://pim.example.com/storage/media/abs-100-pro-side.jpg",
    "https://pim.example.com/storage/media/abs-100-pro-detail.jpg"
  ],
  "price": 189.99,
  "price_formatted": "189,99 EUR",
  "ean": "4012345678901",
  "specs": {
    "Spannung": "18 V",
    "Drehmoment max.": "60 Nm",
    "Leerlaufdrehzahl": "0-1800 U/min",
    "Bohrfutter": "13 mm Schnellspann",
    "Gewicht": "1,8 kg",
    "Akkukapazität": "5,0 Ah"
  },
  "features": [
    "Bürstenloser Motor für längere Lebensdauer",
    "LED-Arbeitsleuchte",
    "Rechts-/Linkslauf",
    "21+1 Drehmomentstufen",
    "Elektronische Motorbremse"
  ],
  "accessories": [
    { "sku": "AKU-18V-5AH", "name": "Akku 18V 5.0Ah" },
    { "sku": "LG-18V-RAPID", "name": "Schnellladegerät 18V" },
    { "sku": "BIT-SET-32", "name": "Bit-Set 32-teilig" }
  ],
  "variants": [
    {
      "sku": "ABS-100-PRO-BL",
      "name": "Akkubohrschrauber Pro 18V - Blau",
      "color": "Blau",
      "ean": "4012345678918"
    },
    {
      "sku": "ABS-100-PRO-RD",
      "name": "Akkubohrschrauber Pro 18V - Rot",
      "color": "Rot",
      "ean": "4012345678925"
    }
  ],
  "meta": {
    "exported_at": "2025-06-15T14:30:00Z",
    "mapping": "Hauptkatalog 2025",
    "lang": "de"
  }
}
```

## PQL Queries on Records

Exported records can be filtered after the fact with PQL queries. This allows extracting targeted subsets from a large record pool for specific Publixx catalogs:

```sql
SELECT * FROM datasets
WHERE specs.Spannung = '18 V'
  AND price BETWEEN 100 AND 250
  AND EXISTS(accessories)
ORDER BY price
```

```bash
curl -X POST "https://pim.example.com/api/v1/publixx/datasets/query" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "SELECT * FROM datasets WHERE specs.Spannung = '\''18 V'\'' AND price BETWEEN 100 AND 250",
    "mapping": "Hauptkatalog 2025",
    "lang": "de"
  }'
```

## Further Documentation

- [Export Overview](/en/export/) -- Process overview and concept
- [JSON Export](/en/export/json-export) -- Generic JSON export
- [PQL Query Language](/en/api/pql) -- Complete PQL documentation
