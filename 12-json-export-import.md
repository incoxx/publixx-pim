# 12 — JSON Export/Import & Export-Jobs

## Übersicht / Overview

Der JSON Export/Import ermöglicht den vollständigen Austausch aller PIM-Daten in einem strukturierten, gut lesbaren JSON-Format. Die Export-Job-Steuerung erlaubt das Anlegen benannter, wiederverwendbarer Export-Konfigurationen.

The JSON Export/Import enables full exchange of all PIM data in a structured, human-readable JSON format. The Export Job management allows creating named, reusable export configurations.

---

## JSON-Format / JSON Format

### Metadaten / Metadata

Jede JSON-Exportdatei beginnt mit einem `_meta`-Block:

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

### Sektionen in Abhängigkeitsreihenfolge / Sections in Dependency Order

Die Reihenfolge stellt sicher, dass beim Import Abhängigkeiten bereits vorhanden sind:

| #  | Sektion / Section                | Abhängigkeit / Depends on          | Import |
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

## JSON Schema je Sektion / JSON Schema per Section

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

| Feld            | Typ    | Pflicht | Beschreibung                         |
|-----------------|--------|---------|--------------------------------------|
| technical_name  | string | ✓       | Eindeutiger technischer Name         |
| name_de         | string | ✓       | Deutscher Anzeigename                |
| name_en         | string |         | Englischer Anzeigename               |

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

| Feld              | Typ     | Pflicht | Beschreibung                       |
|-------------------|---------|---------|------------------------------------|
| technical_name    | string  | ✓       | Technischer Name der Einheit       |
| abbreviation      | string  | ✓       | Abkürzung (z.B. "mm", "kg")       |
| unit_group        | string  | ✓       | Referenz auf unit_groups.technical_name |
| conversion_factor | number  |         | Umrechnungsfaktor (Standard: 1)    |
| is_base_unit      | boolean |         | Basiseinheit der Gruppe?           |

### `attribute_views`

```json
{
  "attribute_views": [
    {
      "technical_name": "marketing",
      "name_de": "Marketing-Sicht",
      "name_en": "Marketing View",
      "description": "Attribute für Marketingzwecke"
    }
  ]
}
```

| Feld           | Typ    | Pflicht | Beschreibung              |
|----------------|--------|---------|---------------------------|
| technical_name | string | ✓       | Technischer Name          |
| name_de        | string | ✓       | Deutscher Name            |
| name_en        | string |         | Englischer Name           |
| description    | string |         | Beschreibung              |

### `attribute_groups`

```json
{
  "attribute_groups": [
    {
      "technical_name": "dimensions",
      "name_de": "Abmessungen",
      "name_en": "Dimensions",
      "description": "Maße und Gewicht",
      "sort_order": 10
    }
  ]
}
```

| Feld           | Typ    | Pflicht | Beschreibung              |
|----------------|--------|---------|---------------------------|
| technical_name | string | ✓       | Technischer Name          |
| name_de        | string | ✓       | Deutscher Name            |
| name_en        | string |         | Englischer Name           |
| description    | string |         | Beschreibung              |
| sort_order     | int    |         | Sortierreihenfolge        |

### `value_lists`

Wertelisten mit verschachtelten Einträgen:

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

**Werteliste:**

| Feld           | Typ    | Pflicht | Beschreibung              |
|----------------|--------|---------|---------------------------|
| technical_name | string | ✓       | Technischer Name          |
| name_de        | string | ✓       | Deutscher Name            |
| entries        | array  |         | Listeneinträge            |

**Eintrag (entry):**

| Feld             | Typ    | Pflicht | Beschreibung              |
|------------------|--------|---------|---------------------------|
| technical_name   | string | ✓       | Technischer Name          |
| display_value_de | string | ✓       | Deutscher Anzeigewert     |
| display_value_en | string |         | Englischer Anzeigewert    |
| sort_order       | int    |         | Sortierreihenfolge        |

### `attributes`

```json
{
  "attributes": [
    {
      "technical_name": "weight",
      "name_de": "Gewicht",
      "name_en": "Weight",
      "description": "Produktgewicht in der Verpackung",
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

| Feld             | Typ      | Pflicht | Beschreibung                              |
|------------------|----------|---------|-------------------------------------------|
| technical_name   | string   | ✓       | Eindeutiger technischer Name              |
| name_de          | string   | ✓       | Deutscher Name                            |
| name_en          | string   |         | Englischer Name                           |
| data_type        | string   | ✓       | text, number, date, boolean, select, multiselect, composite, html |
| attribute_group  | string   |         | Referenz auf attribute_groups             |
| value_list       | string   |         | Referenz auf value_lists (bei select)     |
| unit_group       | string   |         | Referenz auf unit_groups                  |
| default_unit     | string   |         | Standard-Einheit                          |
| is_multipliable  | boolean  |         | Mehrfacherfassung möglich?                |
| max_multiplied   | int/null |         | Maximale Anzahl Mehrfachwerte             |
| is_translatable  | boolean  |         | Mehrsprachig?                             |
| is_mandatory     | boolean  |         | Pflichtfeld?                              |
| is_unique        | boolean  |         | Wert muss eindeutig sein?                 |
| is_searchable    | boolean  |         | Durchsuchbar?                             |
| is_inheritable   | boolean  |         | Vererbbar an Varianten?                   |
| parent_attribute | string   |         | Übergeordnetes Attribut (Composite)       |
| source_system    | string   |         | Quellsystem                               |
| views            | array    |         | Zugeordnete Attribut-Sichten              |

### `product_types`

```json
{
  "product_types": [
    {
      "technical_name": "power_tool",
      "name_de": "Elektrowerkzeug",
      "name_en": "Power Tool",
      "description": "Elektrisch betriebene Werkzeuge",
      "has_variants": true,
      "has_ean": true,
      "has_prices": true,
      "has_media": true
    }
  ]
}
```

| Feld           | Typ     | Pflicht | Beschreibung              |
|----------------|---------|---------|---------------------------|
| technical_name | string  | ✓       | Technischer Name          |
| name_de        | string  | ✓       | Deutscher Name            |
| name_en        | string  |         | Englischer Name           |
| description    | string  |         | Beschreibung              |
| has_variants   | boolean |         | Varianten erlaubt?        |
| has_ean        | boolean |         | EAN-Feld aktiv?           |
| has_prices     | boolean |         | Preise zuordbar?          |
| has_media      | boolean |         | Medien zuordbar?          |

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

Hierarchien mit verschachtelten Knoten (Materialized Path):

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

**Hierarchie:**

| Feld           | Typ    | Pflicht | Beschreibung              |
|----------------|--------|---------|---------------------------|
| technical_name | string | ✓       | Technischer Name          |
| name_de        | string | ✓       | Deutscher Name            |
| hierarchy_type | string |         | master, sales, web, ...   |
| nodes          | array  |         | Hierarchie-Knoten         |

**Knoten (node):**

| Feld    | Typ    | Pflicht | Beschreibung                      |
|---------|--------|---------|-----------------------------------|
| path    | string | ✓       | Materialized Path (z.B. "A/B/C") |
| name_de | string |         | Deutscher Name                    |
| name_en | string |         | Englischer Name                   |

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

| Feld            | Typ     | Pflicht | Beschreibung                          |
|-----------------|---------|---------|---------------------------------------|
| hierarchy       | string  | ✓       | Referenz auf hierarchies              |
| node_path       | string  | ✓       | Pfad des Hierarchie-Knotens           |
| attribute       | string  | ✓       | Referenz auf attributes               |
| collection_name | string  |         | Name der Attributgruppe am Knoten     |
| collection_sort | int     |         | Sortierung der Gruppe                 |
| attribute_sort  | int     |         | Sortierung des Attributs in der Gruppe|
| dont_inherit    | boolean |         | Vererbung an Unterknoten unterbinden? |

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

| Feld         | Typ    | Pflicht | Beschreibung                             |
|--------------|--------|---------|------------------------------------------|
| sku          | string | ✓       | Eindeutige Artikelnummer                 |
| name         | string | ✓       | Produktname (de)                         |
| name_en      | string |         | Englischer Produktname                   |
| product_type | string | ✓       | Referenz auf product_types               |
| ean          | string |         | EAN/GTIN                                 |
| status       | string |         | draft, active, inactive (Standard: draft)|

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

| Feld      | Typ         | Pflicht | Beschreibung                              |
|-----------|-------------|---------|-------------------------------------------|
| sku       | string      | ✓       | Referenz auf products.sku                 |
| attribute | string      | ✓       | Referenz auf attributes.technical_name    |
| value     | string/null | ✓       | Attributwert als String                   |
| unit      | string      |         | Einheit (bei numerischen Attributen)      |
| language  | string      |         | Sprachcode (de, en) bei übersetzbaren Attributen |
| index     | int         |         | Index bei Mehrfachwerten (Standard: 0)    |

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

| Feld       | Typ    | Pflicht | Beschreibung                       |
|------------|--------|---------|------------------------------------|
| parent_sku | string | ✓       | SKU des Hauptprodukts              |
| sku        | string | ✓       | Eindeutige Varianten-SKU           |
| name       | string | ✓       | Variantenname (de)                 |
| name_en    | string |         | Englischer Variantenname           |
| ean        | string |         | EAN/GTIN der Variante              |
| status     | string |         | draft, active, inactive            |

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

| Feld      | Typ    | Pflicht | Beschreibung                       |
|-----------|--------|---------|------------------------------------|
| sku       | string | ✓       | Referenz auf products.sku          |
| hierarchy | string | ✓       | Referenz auf hierarchies           |
| node_path | string | ✓       | Pfad des Hierarchie-Knotens        |

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

| Feld          | Typ    | Pflicht | Beschreibung                       |
|---------------|--------|---------|------------------------------------|
| source_sku    | string | ✓       | Quellprodukt-SKU                   |
| target_sku    | string | ✓       | Zielprodukt-SKU                    |
| relation_type | string | ✓       | Referenz auf relation_types        |
| sort_order    | int    |         | Sortierreihenfolge                 |

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

| Feld       | Typ         | Pflicht | Beschreibung                       |
|------------|-------------|---------|------------------------------------|
| sku        | string      | ✓       | Referenz auf products.sku          |
| price_type | string      | ✓       | Referenz auf price_types           |
| amount     | number      | ✓       | Preis (Dezimalzahl)                |
| currency   | string      |         | Währung (Standard: EUR)            |
| valid_from | date/null   |         | Gültig ab (Y-m-d)                  |
| valid_to   | date/null   |         | Gültig bis (Y-m-d)                 |
| country    | string/null |         | Länderkürzel (DE, AT, CH, ...)     |
| scale_from | number/null |         | Mengenstaffel von                  |
| scale_to   | number/null |         | Mengenstaffel bis                  |

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

| Feld        | Typ     | Pflicht | Beschreibung                       |
|-------------|---------|---------|------------------------------------|
| sku         | string  | ✓       | Referenz auf products.sku          |
| file_name   | string  | ✓       | Dateiname des Mediums              |
| media_type  | string  |         | image, document, video, ...        |
| usage_type  | string  |         | gallery, datasheet, thumbnail, ... |
| title_de    | string  |         | Deutscher Titel                    |
| title_en    | string  |         | Englischer Titel                   |
| alt_text_de | string  |         | Alt-Text (de)                      |
| sort_order  | int     |         | Sortierreihenfolge                 |
| is_primary  | boolean |         | Primärbild?                        |

---

## REST API

### JSON Export

```
GET  /api/v1/json-export                Vollexport als JSON-Download
POST /api/v1/json-export                Gefilterter Export
GET  /api/v1/json-export/sections       Verfügbare Sektionen auflisten
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

| Parameter       | Typ     | Beschreibung                                    |
|-----------------|---------|-------------------------------------------------|
| sections        | array   | Zu exportierende Sektionen (leer = alle)        |
| filter.status   | string  | draft, active, inactive                         |
| filter.product_type | string | Produkttyp-technical_name                   |
| filter.search_text  | string | Freitext (SKU, Name, EAN)                   |
| filter.updated_after | date  | Nur nach Datum aktualisierte Produkte       |
| filter.skus     | array   | Nur bestimmte SKUs                              |
| filter.category_ids | array | Hierarchie-Knoten-IDs                       |
| inline          | boolean | true = JSON-Response statt Download             |

### JSON Import

```
POST /api/v1/json-import                JSON-Datei oder Body importieren
POST /api/v1/json-import/validate       Validierung ohne Import
```

**Import-Modi** (Query-Parameter `mode`):
- `update` (Standard): Upsert — vorhandene Datensätze aktualisieren, neue anlegen
- `delete_insert`: Alle betroffenen Daten löschen und neu einfügen

**Datei-Upload:**
```
POST /api/v1/json-import?mode=update
Content-Type: multipart/form-data

file: [JSON-Datei]
```

**Raw JSON Body:**
```
POST /api/v1/json-import?mode=update
Content-Type: application/json

{ "_meta": {...}, "products": [...] }
```

### Export-Jobs

```
GET    /api/v1/export-jobs              Alle Jobs auflisten
POST   /api/v1/export-jobs              Neuen Job anlegen
GET    /api/v1/export-jobs/{id}         Job-Details
PUT    /api/v1/export-jobs/{id}         Job aktualisieren
DELETE /api/v1/export-jobs/{id}         Job löschen
POST   /api/v1/export-jobs/{id}/execute Job ausführen
GET    /api/v1/export-jobs/{id}/download Letzte Datei herunterladen
```

**POST /api/v1/export-jobs** — Body:

```json
{
  "name": "Elektrowerkzeuge Export aktiv",
  "description": "Aktive Elektrowerkzeuge als JSON",
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

- `async: false` (Standard): Synchrone Ausführung, Ergebnis in Response
- `async: true`: Job wird in die Queue eingereiht (Status 202)

---

## CLI-Befehle / CLI Commands

### JSON Export

```bash
# Vollexport
php artisan pim:json-export

# Bestimmte Sektionen
php artisan pim:json-export --sections=products,prices,variants

# Mit Filtern
php artisan pim:json-export --status=active --product-type=power_tool

# Freitextsuche
php artisan pim:json-export --search="Bohrmaschine"

# Nur nach Datum aktualisierte Produkte
php artisan pim:json-export --updated-after=2026-01-01

# Ausgabedatei festlegen
php artisan pim:json-export --output=/tmp/export.json

# Kompaktes JSON (ohne Pretty-Print)
php artisan pim:json-export --compact

# Verfügbare Sektionen anzeigen
php artisan pim:json-export --sections-list
```

### JSON Import

```bash
# Import (Upsert)
php artisan pim:json-import /pfad/zur/datei.json

# Import mit Delete-Insert
php artisan pim:json-import /pfad/zur/datei.json --mode=delete_insert

# Nur validieren (kein Import)
php artisan pim:json-import /pfad/zur/datei.json --validate
```

### Export-Jobs

```bash
# Alle Jobs anzeigen
php artisan pim:export-job --list

# Job ausführen
php artisan pim:export-job {job-id}

# Neuen Job anlegen
php artisan pim:export-job --create --name="Mein Export" --format=json --filter-status=active

# Alle fälligen Jobs ausführen
php artisan pim:export-job --run-scheduled

# Mit Ausgabeverzeichnis
php artisan pim:export-job {job-id} --output-dir=/tmp/exports
```

---

## Import-Hinweise / Import Notes

### Validierung / Validation

Vor dem Import wird die JSON-Struktur automatisch validiert:
- `_meta`-Sektion muss vorhanden sein
- Alle Sektionen müssen Arrays sein
- Produkte benötigen: `sku`, `name`, `product_type`
- Attribute benötigen: `technical_name`, `data_type`

### Transaktionssicherheit / Transaction Safety

Der Import läuft in einer Datenbank-Transaktion. Bei einem Fehler werden alle Änderungen zurückgerollt.

---

## Logging

- **Export**: `storage/logs/export-YYYY-MM-DD.log` (Channel: `export`, 30 Tage Rotation)
- **Import**: `storage/logs/import-YYYY-MM-DD.log` (Channel: `import`)

Geloggt werden: Start/Ende, Dauer, Sektionen, Dateigrößen, Fehler.
