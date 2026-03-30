---
title: PimSync API
---

# PimSync API

Die PimSync API ermöglicht die **bidirektionale Synchronisation** von Produktdaten zwischen anyPIM-Instanzen. Sie wird automatisch bereitgestellt und kann von externen anyPIM-Instanzen oder anderen Systemen genutzt werden.

## Authentifizierung

Die PimSync-Endpoints verwenden die gleiche Bearer-Token-Authentifizierung wie alle anderen API-Endpoints. Verwenden Sie einen [API-Key](./api-keys) für den Zugriff.

**Benötigte Berechtigungen:**

| Endpoint | Berechtigung |
|---|---|
| Produkte lesen | `products.view` |
| Produkte empfangen | `products.edit` |
| Media lesen | `media.view` |
| Media empfangen | `media.create` |
| Kategorien lesen | `products.view` |
| Attribute lesen | `products.view` |

## Endpunkte

### Produkte

#### Produkte abrufen (paginiert)

```
GET /api/v1/pim-sync/products
Authorization: Bearer {api-key}
```

**Query-Parameter:**

| Parameter | Typ | Beschreibung |
|---|---|---|
| `hierarchy_id` | UUID | Filtert nach Hierarchie-Knoten |
| `product_type_ids[]` | UUID[] | Filtert nach Produkttypen |
| `skus[]` | String[] | Filtert nach SKUs |
| `updated_since` | ISO 8601 | Nur Produkte geändert nach diesem Zeitpunkt |
| `per_page` | Integer | Einträge pro Seite (max. 500, Standard: 100) |
| `page` | Integer | Seitennummer |

**Antwort:**

```json
{
  "products": [
    {
      "sku": "ABC-123",
      "ean": "4006381333931",
      "name": "Akkubohrer Professional",
      "status": "active",
      "product_type": "simple",
      "manufacturer": "Bosch",
      "attributes": {
        "product-name-dict": {
          "de": "Akkubohrer Professional",
          "en": "Cordless Drill Professional"
        },
        "weight": {
          "value": 1.8,
          "unit": "kg"
        }
      },
      "prices": [
        {
          "type": "list_price",
          "amount": 189.99,
          "currency": "EUR"
        }
      ],
      "media": [
        {
          "file_name": "akkubohrer.jpg",
          "usage_type": "teaser",
          "is_primary": true
        }
      ],
      "_checksum": "a1b2c3d4",
      "_updated_at": "2026-03-28T14:30:00Z"
    }
  ],
  "meta": {
    "total": 1250,
    "per_page": 100,
    "current_page": 1,
    "last_page": 13
  }
}
```

#### Einzelnes Produkt abrufen

```
GET /api/v1/pim-sync/products/{sku}
Authorization: Bearer {api-key}
```

#### Produkte empfangen (Batch-Import)

```
POST /api/v1/pim-sync/products
Authorization: Bearer {api-key}
```

**Request Body:**

```json
{
  "products": [
    {
      "sku": "ABC-123",
      "name": "Akkubohrer Professional",
      "status": "active",
      "attributes": {
        "product-name-dict": {
          "de": "Akkubohrer Professional",
          "fr": "Perceuse sans fil Professional"
        }
      },
      "prices": [
        {
          "type": "list_price",
          "amount": 189.99,
          "currency": "EUR"
        }
      ]
    }
  ],
  "conflict_resolution": "newer_wins",
  "sync_attributes": true,
  "sync_prices": true,
  "sync_media": true
}
```

| Feld | Typ | Standard | Beschreibung |
|---|---|---|---|
| `products` | Array | Pflicht | Array von Produkten (max. 500) |
| `conflict_resolution` | String | `remote_wins` | Konfliktstrategie: `remote_wins`, `local_wins`, `newer_wins` |
| `sync_attributes` | Boolean | `true` | Attributwerte synchronisieren |
| `sync_prices` | Boolean | `true` | Preise synchronisieren |
| `sync_media` | Boolean | `true` | Media-Zuordnungen synchronisieren |

**Antwort:**

```json
{
  "message": "Import abgeschlossen.",
  "stats": {
    "created": 5,
    "updated": 12,
    "skipped": 3,
    "errors": 0,
    "details": []
  }
}
```

### Checksums (Delta-Sync)

```
GET /api/v1/pim-sync/checksums
Authorization: Bearer {api-key}
```

Gibt Checksums für alle Produkte zurück. Wird für Delta-Sync verwendet — nur Produkte mit geänderten Checksums müssen synchronisiert werden.

**Antwort:**

```json
{
  "checksums": {
    "ABC-123": {
      "checksum": "a1b2c3d4",
      "updated_at": "2026-03-28T14:30:00Z"
    }
  },
  "total": 1250
}
```

### Kategorien

```
GET /api/v1/pim-sync/categories?hierarchy_id={uuid}
Authorization: Bearer {api-key}
```

### Attribute

```
GET /api/v1/pim-sync/attributes
Authorization: Bearer {api-key}
```

Gibt alle Attribut-Definitionen zurück (technischer Name, Datentyp, Übersetzbarkeit, Einheit).

### Schema

```
GET /api/v1/pim-sync/schema
Authorization: Bearer {api-key}
```

Gibt Schema-Informationen der Instanz zurück (Produkttypen, Hierarchien, Attribut-Anzahl).

## Konfliktstrategie

Bei Produkten die sowohl lokal als auch remote geändert wurden:

| Strategie | Verhalten |
|---|---|
| `remote_wins` | Remote-Daten überschreiben lokale |
| `local_wins` | Lokale Daten werden beibehalten, Import übersprungen |
| `newer_wins` | Die aktuellere Version gewinnt (basierend auf `_updated_at`) |

## Automatische Attribut-Anlage

Wenn ein importiertes Produkt Attribute referenziert, die lokal nicht existieren, werden diese **automatisch angelegt** mit:
- `source_system = 'anypim-sync'`
- Name abgeleitet vom `technical_name`
- Datentyp automatisch ermittelt

## CLI-Befehl

Die Synchronisation kann auch über die Kommandozeile ausgeführt werden:

```bash
# Einzelne Verbindung synchronisieren
php artisan pim:sync --connection={uuid}

# Alle aktiven anyPIM-Verbindungen
php artisan pim:sync --all

# Nur Delta (geänderte Produkte)
php artisan pim:sync --all --delta

# Nur Push / Pull
php artisan pim:sync --connection={uuid} --direction=push
php artisan pim:sync --connection={uuid} --direction=pull

# Dry-Run (ohne Änderungen)
php artisan pim:sync --connection={uuid} --dry-run
```
