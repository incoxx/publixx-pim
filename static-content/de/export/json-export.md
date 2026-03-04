---
title: JSON-Export
---

# JSON-Export

Der JSON-Export stellt Produktdaten über REST-API-Endpunkte in einem strukturierten JSON-Format bereit. Er eignet sich für die Integration mit Webshops, ERP-Systemen, Marktplätzen und anderen Drittsystemen.

## Endpunkte

### Produkte exportieren

```
GET /api/v1/export/products
```

Exportiert Produkte mit Filterung und Paginierung. Geeignet für regelmässige Synchronisationen und browsing-artige Zugriffe.

### Bulk-Export

```
POST /api/v1/export/products/bulk
```

Exportiert eine grosse Menge an Produkten in einem einzelnen Aufruf. Der Export wird als Hintergrundjob verarbeitet und das Ergebnis kann anschliessend abgerufen werden. Geeignet für Vollexporte und initiale Datenübernahmen.

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

### PQL-basierter Export

```
POST /api/v1/export/query
```

Exportiert Produkte basierend auf einer PQL-Abfrage. Bietet die grösste Flexibilität bei der Filterung.

**Request Body:**

```json
{
  "query": "SELECT sku, name, gewicht FROM products WHERE status = 'active' AND kategorie = 'Elektrowerkzeuge'",
  "include": ["media"],
  "format": "flat",
  "lang": "de"
}
```

## Filterparameter

Die folgenden Parameter stehen für den `GET /api/v1/export/products`-Endpunkt zur Verfügung:

| Parameter | Typ | Beschreibung | Beispiel |
|---|---|---|---|
| `status` | String | Produktstatus filtern | `status=active` |
| `hierarchy_node` | UUID | Produkte eines Hierarchieknotens | `hierarchy_node=uuid-hier` |
| `hierarchy_path` | String | Produkte eines Hierarchiepfads | `hierarchy_path=Werkzeuge/Bohrer` |
| `attribute[name]` | String | Attributwert-Filter | `attribute[farbe]=rot` |
| `view` | String | Attributansicht einschränken | `view=export` |
| `output_hierarchy` | UUID | Ausgabehierarchie für Strukturierung | `output_hierarchy=uuid-hier` |
| `updated_after` | ISO 8601 | Delta-Export ab Zeitstempel | `updated_after=2025-01-15T08:00:00Z` |

### Filterbeispiele

**Aktive Produkte einer Kategorie:**

```
GET /api/v1/export/products?status=active&hierarchy_path=Werkzeuge/Elektrowerkzeuge
```

**Delta-Export seit letzter Synchronisation:**

```
GET /api/v1/export/products?updated_after=2025-06-15T10:00:00Z&status=active
```

**Produkte mit bestimmtem Attributwert:**

```
GET /api/v1/export/products?attribute[marke]=Bosch&attribute[leistung_min]=500
```

## Include-Optionen

Über den Parameter `include` können zusätzliche Daten pro Produkt geladen werden:

| Wert | Beschreibung |
|---|---|
| `media` | Zugeordnete Medien (Bilder, Dokumente, Videos) mit URLs |
| `prices` | Preise mit Preisart, Währung und Gültigkeit |
| `relations` | Produktrelationen (Zubehör, Ersatzteile, Querverweise) |
| `variants` | Varianten mit allen Attributwerten |

**Mehrere Include-Werte:**

```
GET /api/v1/export/products?include=media,prices,relations
```

## Sprachauswahl

Der Parameter `lang` bestimmt, in welcher Sprache übersetzbare Attributwerte exportiert werden:

```
GET /api/v1/export/products?lang=de
GET /api/v1/export/products?lang=en
```

Wird kein `lang`-Parameter angegeben, werden alle verfügbaren Sprachen als verschachteltes Objekt exportiert:

```json
{
  "name": {
    "de": "Akkubohrschrauber Pro",
    "en": "Cordless Drill Pro"
  }
}
```

## Formatoptionen

Der Parameter `format` bestimmt die Struktur der exportierten Daten:

### `flat` -- Flache Struktur

Alle Attributwerte als einfache Schlüssel-Wert-Paare auf der obersten Ebene:

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

### `nested` -- Verschachtelte Struktur

Attributwerte gruppiert nach Attributgruppen:

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

### `publixx` -- Publixx-Format

Transformation nach Publixx-Mapping-Regeln. Details siehe [Publixx-Export](/de/export/publixx-export).

## Paginierung

Der `GET /api/v1/export/products`-Endpunkt verwendet Cursor-basierte Paginierung:

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `page` | Integer | `1` | Aktuelle Seite |
| `per_page` | Integer | `50` | Anzahl Produkte pro Seite (max. 500) |

### Antwortstruktur mit Paginierung

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

## Vollständiges Beispiel

**Anfrage:**

```bash
curl -X GET "https://pim.example.com/api/v1/export/products?status=active&hierarchy_path=Werkzeuge/Elektrowerkzeuge&include=media,prices&format=nested&lang=de&per_page=2" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Antwort:**

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

## Weiterführende Dokumentation

- [Export-Übersicht](/de/export/) -- Prozessübersicht und Konzept
- [Publixx-Export](/de/export/publixx-export) -- Mapping-Konfiguration und PXF-Integration
- [API-Referenz](/de/api/) -- Vollständige API-Dokumentation
