---
title: Products API
---

# Products API

The Products API provides full CRUD operations for products as well as endpoints for attribute values, variants, media, prices, and relations.

## Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/products` | List products |
| `POST` | `/api/v1/products` | Create product |
| `GET` | `/api/v1/products/{id}` | Retrieve product |
| `PUT` | `/api/v1/products/{id}` | Update product |
| `DELETE` | `/api/v1/products/{id}` | Delete product |
| `GET` | `/api/v1/products/{id}/attribute-values` | Retrieve attribute values |
| `PUT` | `/api/v1/products/{id}/attribute-values` | Update attribute values |
| `GET` | `/api/v1/products/{id}/variants` | List variants |
| `POST` | `/api/v1/products/{id}/variants` | Create variant |
| `GET` | `/api/v1/products/{id}/variant-rules` | Retrieve variant rules |
| `PUT` | `/api/v1/products/{id}/variant-rules` | Update variant rules |
| `GET` | `/api/v1/products/{id}/media` | Retrieve media |
| `POST` | `/api/v1/products/{id}/media` | Assign media |
| `DELETE` | `/api/v1/products/{id}/media/{mediaId}` | Remove media assignment |
| `GET` | `/api/v1/products/{id}/prices` | Retrieve prices |
| `POST` | `/api/v1/products/{id}/prices` | Add price |
| `PUT` | `/api/v1/products/{id}/prices/{priceId}` | Update price |
| `DELETE` | `/api/v1/products/{id}/prices/{priceId}` | Remove price |
| `GET` | `/api/v1/products/{id}/relations` | Retrieve relations |
| `POST` | `/api/v1/products/{id}/relations` | Create relation |
| `DELETE` | `/api/v1/products/{id}/relations/{relationId}` | Remove relation |

## List Products

```
GET /api/v1/products
```

### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `filter[status]` | String | -- | Filter by status (`draft`, `active`, `inactive`) |
| `filter[product_type]` | String | -- | Filter by product type (`simple`, `configurable`) |
| `filter[hierarchy_node]` | UUID | -- | Products of a hierarchy node |
| `sort` | String | `created_at` | Sort field (prefix `-` for descending) |
| `search` | String | -- | Full-text search (SKU, name) |
| `include` | String | -- | Related resources (`variants`, `media`, `prices`, `relations`) |
| `fields` | String | -- | Fields to return |
| `page` | Integer | `1` | Page number |
| `per_page` | Integer | `25` | Entries per page (max. 100) |
| `lang` | String | -- | Language for translatable fields |

### Example Request

```bash
curl -X GET "https://pim.example.com/api/v1/products?filter[status]=active&sort=-updated_at&include=media&lang=de&per_page=10" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Example Response (200 OK)

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "sku": "ABS-100-PRO",
      "name": "Akkubohrschrauber Pro 18V",
      "product_type": "configurable",
      "ean": "4012345678901",
      "status": "active",
      "created_at": "2025-03-10T08:15:00Z",
      "updated_at": "2025-06-12T14:30:00Z",
      "media": [
        {
          "id": "media-uuid-001",
          "type": "image",
          "url": "https://pim.example.com/storage/media/abs-100-pro.jpg",
          "filename": "abs-100-pro.jpg",
          "sort_order": 1
        }
      ]
    },
    {
      "id": "660f9500-f30c-52e5-b827-557766550000",
      "sku": "BM-2000-PRO",
      "name": "Bohrmaschine Pro 2000",
      "product_type": "simple",
      "ean": "4012345678902",
      "status": "active",
      "created_at": "2025-02-20T10:45:00Z",
      "updated_at": "2025-05-18T09:20:00Z",
      "media": []
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 1250,
    "total_pages": 125
  }
}
```

## Create Product

```
POST /api/v1/products
```

### Request Body

```json
{
  "sku": "SLM-500",
  "name_de": "Schleifmaschine 500W",
  "name_en": "Sander 500W",
  "product_type": "simple",
  "ean": "4012345678903",
  "status": "draft"
}
```

### Response (201 Created)

```json
{
  "data": {
    "id": "770g0600-g41d-63f6-c938-668877660000",
    "sku": "SLM-500",
    "name": "Schleifmaschine 500W",
    "product_type": "simple",
    "ean": "4012345678903",
    "status": "draft",
    "created_at": "2025-06-15T14:00:00Z",
    "updated_at": "2025-06-15T14:00:00Z"
  }
}
```

## Retrieve Product

```
GET /api/v1/products/{id}
```

### Query Parameters

| Parameter | Type | Description |
|---|---|---|
| `include` | String | Include related resources |
| `lang` | String | Language for translatable fields |

### Example Request

```bash
curl -X GET "https://pim.example.com/api/v1/products/550e8400-e29b-41d4-a716-446655440000?include=variants,media,prices&lang=de" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

## Update Product

```
PUT /api/v1/products/{id}
```

### Request Body

Only the fields to be changed need to be provided:

```json
{
  "name_de": "Akkubohrschrauber Pro 18V (2025)",
  "status": "active"
}
```

### Response (200 OK)

Returns the updated product.

## Delete Product

```
DELETE /api/v1/products/{id}
```

### Response (204 No Content)

No content. The product and all associated attribute values, media assignments, prices, and relations are removed.

::: warning Note
Deleting a product of type `configurable` also removes all associated variants.
:::

## Attribute Values

### Retrieve Attribute Values

```
GET /api/v1/products/{id}/attribute-values
```

| Parameter | Type | Description |
|---|---|---|
| `view` | String | Attribute view (e.g., `basis`, `detail`, `export`) |
| `lang` | String | Language for translatable attribute values |

#### Example Response

```json
{
  "data": [
    {
      "attribute": {
        "id": "attr-uuid-gewicht",
        "technical_name": "gewicht_netto",
        "name": "Nettogewicht",
        "data_type": "number"
      },
      "value": 1.8,
      "unit": {
        "technical_name": "kg",
        "name": "Kilogramm"
      },
      "language": null,
      "index": 1
    },
    {
      "attribute": {
        "id": "attr-uuid-beschreibung",
        "technical_name": "beschreibung",
        "name": "Beschreibung",
        "data_type": "richtext"
      },
      "value": "Leistungsstarker Akkubohrschrauber mit bürstenlosem Motor.",
      "unit": null,
      "language": "de",
      "index": 1
    }
  ]
}
```

### Update Attribute Values

```
PUT /api/v1/products/{id}/attribute-values
```

#### Request Body

```json
{
  "values": [
    {
      "attribute": "gewicht_netto",
      "value": 1.9,
      "unit": "kg"
    },
    {
      "attribute": "beschreibung",
      "value": "Überarbeitete Beschreibung des Akkubohrschraubers.",
      "language": "de"
    },
    {
      "attribute": "beschreibung",
      "value": "Revised description of the cordless drill.",
      "language": "en"
    },
    {
      "attribute": "zertifikat",
      "value": "TÜV",
      "index": 3
    }
  ]
}
```

## Variants

### List Variants

```
GET /api/v1/products/{id}/variants
```

#### Example Response

```json
{
  "data": [
    {
      "id": "var-uuid-001",
      "sku": "ABS-100-PRO-BL",
      "name": "Akkubohrschrauber Pro 18V - Blau",
      "parent_product_id": "550e8400-e29b-41d4-a716-446655440000",
      "status": "active",
      "overridden_attributes": ["farbe", "ean"],
      "created_at": "2025-03-15T09:00:00Z"
    },
    {
      "id": "var-uuid-002",
      "sku": "ABS-100-PRO-RD",
      "name": "Akkubohrschrauber Pro 18V - Rot",
      "parent_product_id": "550e8400-e29b-41d4-a716-446655440000",
      "status": "active",
      "overridden_attributes": ["farbe", "ean"],
      "created_at": "2025-03-15T09:05:00Z"
    }
  ]
}
```

### Create Variant

```
POST /api/v1/products/{id}/variants
```

#### Request Body

```json
{
  "sku": "ABS-100-PRO-GN",
  "name_de": "Akkubohrschrauber Pro 18V - Grün",
  "name_en": "Cordless Drill Pro 18V - Green",
  "status": "draft",
  "attribute_values": [
    {
      "attribute": "farbe",
      "value": "Grün"
    },
    {
      "attribute": "ean",
      "value": "4012345678932"
    }
  ]
}
```

### Retrieve Variant Rules

```
GET /api/v1/products/{id}/variant-rules
```

Returns the rules that define which attributes differentiate the variants:

```json
{
  "data": {
    "defining_attributes": ["farbe"],
    "auto_inherit": true,
    "rules": [
      {
        "attribute": "farbe",
        "values": ["Blau", "Rot", "Grün"]
      }
    ]
  }
}
```

### Update Variant Rules

```
PUT /api/v1/products/{id}/variant-rules
```

#### Request Body

```json
{
  "defining_attributes": ["farbe", "groesse"],
  "auto_inherit": true
}
```

## Media

### Retrieve Media

```
GET /api/v1/products/{id}/media
```

### Assign Media

```
POST /api/v1/products/{id}/media
```

#### Request Body

```json
{
  "media_id": "media-uuid-003",
  "sort_order": 2
}
```

### Remove Media Assignment

```
DELETE /api/v1/products/{id}/media/{mediaId}
```

## Prices

### Retrieve Prices

```
GET /api/v1/products/{id}/prices
```

#### Example Response

```json
{
  "data": [
    {
      "id": "price-uuid-001",
      "price_type": "UVP",
      "amount": 189.99,
      "currency": "EUR",
      "valid_from": "2025-01-01",
      "valid_to": null
    },
    {
      "id": "price-uuid-002",
      "price_type": "Händler-EK",
      "amount": 125.00,
      "currency": "EUR",
      "valid_from": "2025-01-01",
      "valid_to": "2025-12-31"
    }
  ]
}
```

### Add Price

```
POST /api/v1/products/{id}/prices
```

#### Request Body

```json
{
  "price_type": "Aktionspreis",
  "amount": 159.99,
  "currency": "EUR",
  "valid_from": "2025-06-01",
  "valid_to": "2025-06-30"
}
```

## Relations

### Retrieve Relations

```
GET /api/v1/products/{id}/relations
```

#### Example Response

```json
{
  "data": [
    {
      "id": "rel-uuid-001",
      "type": "accessory",
      "target_product": {
        "id": "prod-uuid-akku",
        "sku": "AKU-18V-5AH",
        "name": "Akku 18V 5.0Ah"
      },
      "sort_order": 1
    },
    {
      "id": "rel-uuid-002",
      "type": "spare_part",
      "target_product": {
        "id": "prod-uuid-kohle",
        "sku": "KOH-BM2000",
        "name": "Kohlebürsten BM-2000"
      },
      "sort_order": 1
    }
  ]
}
```

### Create Relation

```
POST /api/v1/products/{id}/relations
```

#### Request Body

```json
{
  "type": "cross_sell",
  "target_product_id": "prod-uuid-target",
  "sort_order": 1
}
```

### Remove Relation

```
DELETE /api/v1/products/{id}/relations/{relationId}
```
