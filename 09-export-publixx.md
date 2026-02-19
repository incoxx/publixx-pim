# Publixx PIM — Export & Publixx-Integration

> **Zweck:** JSON-Export und Publixx-Anbindung. Verwende diesen Skill beim Implementieren der Export-Engine, Mapping-Konfiguration und Live-API-Endpunkte für Publixx.

---

## Konzept

Das PIM exportiert Produktdaten als JSON-Datasets, die direkt als Publixx-Datasets verwendbar sind. Ein konfigurierbares Mapping übersetzt PIM-Attribute in Publixx-JSON-Felder.

---

## Export-Endpunkte

```
GET  /api/v1/export/products                           Filter → JSON-Array
GET  /api/v1/export/products/{id}                      Einzelprodukt
POST /api/v1/export/products/bulk                      Bulk nach Filter
GET  /api/v1/export/products/{id}/publixx              PXF-Dataset-Format
POST /api/v1/export/query                              PQL-Filter
```

### Publixx Live-API

```
GET  /api/v1/publixx/datasets/{mapping_id}             Alle Produkte des Mappings
GET  /api/v1/publixx/datasets/{mapping_id}/{product_id} Einzelnes Dataset
POST /api/v1/publixx/datasets/{mapping_id}/pql          PQL-gefiltert
POST /api/v1/publixx/webhook                            Webhook von Publixx
```

---

## Filter-Parameter

| Parameter | Beispiel | Beschreibung |
|-----------|---------|--------------|
| filter[status] | active | Produktstatus |
| filter[hierarchy_node] | uuid | Alle Produkte unter Knoten |
| filter[hierarchy_path] | Elektro/Akkubohr | Per Pfad |
| filter[attribute.gewicht][gte] | 5 | Attributwert numerisch |
| filter[attribute.farbe] | rot | Attributwert exakt |
| filter[attribute.name][contains] | Bohr | Teilstring |
| filter[view] | eshop_view | Nur Attribute einer Sicht |
| filter[output_hierarchy] | uuid | Ausgabehierarchie |
| filter[updated_after] | 2025-01-01T00:00:00Z | Delta-Export |
| include_media | true | Medien-URLs |
| include_prices | true | Preise |
| include_relations | true | Beziehungen |
| lang | de,en | Sprachen |
| format | publixx | flat / nested / publixx |

---

## Export-Mapping (publixx_export_mappings)

### Entität

```
id, name, attribute_view_id (FK nullable), output_hierarchy_id (FK nullable),
mapping_rules (JSON), include_media, include_prices, include_variants,
include_relations, languages (JSON), flatten_mode ENUM('flat','nested','publixx')
```

### Mapping-Regeln

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

### Mapping-Typen

| type | source | Ergebnis |
|------|--------|---------|
| text | attribute:tech_name | `"productName": "Wert"` |
| unit_value | attribute:tech_name | `"specs.weight": { "value": 1.8, "unit": "kg" }` |
| media_url | media:usage_type | `"productImage": "https://..."` |
| media_array | media:usage_type | `"gallery": ["url1", "url2"]` |
| price | prices:price_type | `"preis.listenpreis": 189.99` |
| variant_array | variants | `"varianten": [{ "sku": "...", ... }]` |
| relation_array | relations:rel_type | `"zubehoer": [{ "sku": "...", ... }]` |
| group | collection:name | Gruppiert Attribute als Objekt |

---

## Beispiel: Exportiertes Dataset (format=publixx)

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

## Publixx Element-Binding

| Publixx Element | bind | PIM-Quelle |
|-----------------|------|-----------|
| text (Name) | `"productName"` | Attribut |
| image (Hauptbild) | `"productImage"` | Media teaser |
| text (Gewicht Wert) | `"specs.weight.value"` | Attribut (Wert) |
| text (Gewicht Einheit) | `"specs.weight.unit"` | Attribut (Einheit) |
| smartTable | `"varianten"` | Varianten |
| group/repeater | `"zubehoer"` | Relations |
| barcode | `"ean"` | Grunddatum |

---

## PQL auf exportierten Datasets (in Publixx)

```sql
SELECT * WHERE status = 'active' AND productImage EXISTS
SELECT * WHERE preis.listenpreis > 100
SELECT * WHERE productName LIKE '%Bohr%'
SELECT * WHERE hierarchy LIKE 'Elektrowerkzeuge%'
```

---

## Laravel-Klassen

```php
App\Services\Export\ExportService           // Orchestrierung
App\Services\Export\MappingResolver         // Mapping-Regeln → JSON
App\Services\Export\DatasetBuilder          // Produkt → Dataset
App\Services\Export\PublixxDatasetService   // Publixx-spezifische Endpunkte
App\Http\Controllers\Api\V1\ExportController
App\Http\Controllers\Api\V1\PublixxDatasetController
```
