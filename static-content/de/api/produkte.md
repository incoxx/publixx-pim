---
title: Produkte API
---

# Produkte API

Die Produkte-API bietet vollständige CRUD-Operationen für Produkte sowie Endpunkte für Attributwerte, Varianten, Medien, Preise und Relationen.

## Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `GET` | `/api/v1/products` | Produkte auflisten |
| `POST` | `/api/v1/products` | Produkt erstellen |
| `GET` | `/api/v1/products/{id}` | Produkt abrufen |
| `PUT` | `/api/v1/products/{id}` | Produkt aktualisieren |
| `DELETE` | `/api/v1/products/{id}` | Produkt löschen |
| `GET` | `/api/v1/products/{id}/attribute-values` | Attributwerte abrufen |
| `PUT` | `/api/v1/products/{id}/attribute-values` | Attributwerte aktualisieren |
| `GET` | `/api/v1/products/{id}/variants` | Varianten auflisten |
| `POST` | `/api/v1/products/{id}/variants` | Variante erstellen |
| `GET` | `/api/v1/products/{id}/variant-rules` | Variantenregeln abrufen |
| `PUT` | `/api/v1/products/{id}/variant-rules` | Variantenregeln aktualisieren |
| `GET` | `/api/v1/products/{id}/media` | Medien abrufen |
| `POST` | `/api/v1/products/{id}/media` | Medium zuordnen |
| `DELETE` | `/api/v1/products/{id}/media/{mediaId}` | Medienzuordnung entfernen |
| `GET` | `/api/v1/products/{id}/prices` | Preise abrufen |
| `POST` | `/api/v1/products/{id}/prices` | Preis hinzufügen |
| `PUT` | `/api/v1/products/{id}/prices/{priceId}` | Preis aktualisieren |
| `DELETE` | `/api/v1/products/{id}/prices/{priceId}` | Preis entfernen |
| `GET` | `/api/v1/products/{id}/relations` | Relationen abrufen |
| `POST` | `/api/v1/products/{id}/relations` | Relation erstellen |
| `DELETE` | `/api/v1/products/{id}/relations/{relationId}` | Relation entfernen |

## Produkte auflisten

```
GET /api/v1/products
```

### Query-Parameter

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `filter[status]` | String | -- | Nach Status filtern (`draft`, `active`, `inactive`) |
| `filter[product_type]` | String | -- | Nach Produkttyp filtern (`simple`, `configurable`) |
| `filter[hierarchy_node]` | UUID | -- | Produkte eines Hierarchieknotens |
| `sort` | String | `created_at` | Sortierfeld (Präfix `-` für absteigend) |
| `search` | String | -- | Freitextsuche (SKU, Name) |
| `include` | String | -- | Verwandte Ressourcen (`variants`, `media`, `prices`, `relations`) |
| `fields` | String | -- | Zurückzugebende Felder |
| `page` | Integer | `1` | Seitennummer |
| `per_page` | Integer | `25` | Einträge pro Seite (max. 100) |
| `lang` | String | -- | Sprache für übersetzbare Felder |

### Beispiel-Anfrage

```bash
curl -X GET "https://pim.example.com/api/v1/products?filter[status]=active&sort=-updated_at&include=media&lang=de&per_page=10" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Beispiel-Antwort (200 OK)

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

## Produkt erstellen

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

### Antwort (201 Created)

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

## Produkt abrufen

```
GET /api/v1/products/{id}
```

### Query-Parameter

| Parameter | Typ | Beschreibung |
|---|---|---|
| `include` | String | Verwandte Ressourcen mitladen |
| `lang` | String | Sprache für übersetzbare Felder |

### Beispiel-Anfrage

```bash
curl -X GET "https://pim.example.com/api/v1/products/550e8400-e29b-41d4-a716-446655440000?include=variants,media,prices&lang=de" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

## Produkt aktualisieren

```
PUT /api/v1/products/{id}
```

### Request Body

Es müssen nur die zu ändernden Felder übergeben werden:

```json
{
  "name_de": "Akkubohrschrauber Pro 18V (2025)",
  "status": "active"
}
```

### Antwort (200 OK)

Gibt das aktualisierte Produkt zurück.

## Produkt löschen

```
DELETE /api/v1/products/{id}
```

### Antwort (204 No Content)

Kein Inhalt. Das Produkt sowie alle zugehörigen Attributwerte, Medienzuordnungen, Preise und Relationen werden entfernt.

::: warning Hinweis
Das Löschen eines Produkts vom Typ `configurable` entfernt auch alle zugehörigen Varianten.
:::

## Attributwerte

### Attributwerte abrufen

```
GET /api/v1/products/{id}/attribute-values
```

| Parameter | Typ | Beschreibung |
|---|---|---|
| `view` | String | Attributansicht (z. B. `basis`, `detail`, `export`) |
| `lang` | String | Sprache für übersetzbare Attributwerte |

#### Beispiel-Antwort

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

### Attributwerte aktualisieren

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

## Varianten

### Varianten auflisten

```
GET /api/v1/products/{id}/variants
```

#### Beispiel-Antwort

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

### Variante erstellen

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

### Variantenregeln abrufen

```
GET /api/v1/products/{id}/variant-rules
```

Gibt die Regeln zurück, die definieren, welche Attribute die Varianten unterscheiden:

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

### Variantenregeln aktualisieren

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

## Medien

### Medien abrufen

```
GET /api/v1/products/{id}/media
```

### Medium zuordnen

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

### Medienzuordnung entfernen

```
DELETE /api/v1/products/{id}/media/{mediaId}
```

## Preise

### Preise abrufen

```
GET /api/v1/products/{id}/prices
```

#### Beispiel-Antwort

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

### Preis hinzufügen

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

## Relationen

### Relationen abrufen

```
GET /api/v1/products/{id}/relations
```

#### Beispiel-Antwort

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

### Relation erstellen

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

### Relation entfernen

```
DELETE /api/v1/products/{id}/relations/{relationId}
```
