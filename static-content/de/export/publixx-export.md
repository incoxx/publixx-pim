---
title: Publixx-Export
---

# Publixx-Export

Der Publixx-Export transformiert PIM-Produktdaten in das Publixx-spezifische Datensatzformat (PXF -- Publixx Exchange Format). Über konfigurierbare Mappings wird definiert, welche PIM-Felder auf welche Publixx-Datensatzfelder abgebildet werden. Die exportierten Datensätze können direkt in Publixx-Katalogen und -Templates verwendet werden.

## Export-Mapping-Konfiguration

### Mapping-Tabelle

Die Zuordnung zwischen PIM-Feldern und Publixx-Datensatzfeldern wird in der Datenbanktabelle `publixx_export_mappings` konfiguriert. Jeder Eintrag beschreibt eine Mapping-Regel.

| Feld | Typ | Beschreibung |
|---|---|---|
| `id` | UUID | Eindeutige Kennung |
| `name` | String | Name des Mappings (z. B. "Hauptkatalog 2025") |
| `source` | String | Quellfeld im PIM (Attribut-Technischer-Name oder Systemfeld) |
| `target` | String | Zielfeld im Publixx-Datensatz |
| `type` | Enum | Mapping-Typ (bestimmt die Transformation) |
| `config` | JSON | Zusätzliche Konfiguration je nach Mapping-Typ |
| `sort_order` | Integer | Reihenfolge der Felder im Datensatz |

### Mapping-Typen

| Typ | Beschreibung | Quellfeld (Beispiel) | Zielfeld (Beispiel) |
|---|---|---|---|
| `text` | Einfacher Textwert, wird 1:1 übernommen | `beschreibung` | `description` |
| `unit_value` | Wert mit Einheit, wird als formatierter String exportiert | `gewicht_netto` | `weight` |
| `media_url` | URL des ersten zugeordneten Mediums | `hauptbild` | `image_url` |
| `media_array` | Array aller zugeordneten Medien-URLs | `produktbilder` | `images` |
| `price` | Preiswert einer bestimmten Preisart | `UVP` | `price` |
| `variant_array` | Array aller Varianten mit definierten Feldern | `varianten` | `variants` |
| `relation_array` | Array aller Relationen eines bestimmten Typs | `zubehoer` | `accessories` |
| `group` | Gruppiert mehrere Quellfelder unter einem Ziel-Objekt | `technische_daten.*` | `specs` |

### Mapping-Beispiel

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

## Publixx Live-API-Endpunkte

Die folgenden Endpunkte stellen die Schnittstelle zwischen PIM und Publixx-Plattform bereit:

### Datensätze abrufen

```
GET /api/v1/publixx/datasets
```

Liefert alle exportierten Datensätze im PXF-Format, gefiltert nach Mapping-Konfiguration.

**Query-Parameter:**

| Parameter | Typ | Beschreibung |
|---|---|---|
| `mapping` | String | Name oder ID der Mapping-Konfiguration |
| `status` | String | Produktstatus (`active`) |
| `hierarchy_path` | String | Hierarchiepfad zur Einschränkung |
| `updated_after` | ISO 8601 | Delta-Export ab Zeitstempel |
| `lang` | String | Inhaltssprache (ISO 639-1) |
| `page` | Integer | Seitennummer |
| `per_page` | Integer | Datensätze pro Seite (max. 200) |

### Einzelnen Datensatz abrufen

```
GET /api/v1/publixx/datasets/{sku}
```

Liefert den Datensatz eines einzelnen Produkts.

### Datensatz nach PQL-Abfrage

```
POST /api/v1/publixx/datasets/query
```

Liefert Datensätze basierend auf einer PQL-Abfrage.

## PXF-Template-Verwaltung

PXF-Templates definieren das Layout und die Struktur eines Publixx-Katalogs. Sie werden im PIM verwaltet und mit den exportierten Datensätzen verknüpft.

### Template-Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `GET` | `/api/v1/publixx/templates` | Alle Templates auflisten |
| `GET` | `/api/v1/publixx/templates/{id}` | Template-Details abrufen |
| `POST` | `/api/v1/publixx/templates` | Neues Template anlegen |
| `PUT` | `/api/v1/publixx/templates/{id}` | Template aktualisieren |
| `DELETE` | `/api/v1/publixx/templates/{id}` | Template löschen |

### Template-Struktur

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

## Publixx-Element-Binding

Jedes Element in einem PXF-Template hat eine `binding`-Eigenschaft, die definiert, welches Feld des exportierten Datensatzes an dieses Element gebunden wird.

| Element-Typ | Binding-Typ | Beschreibung |
|---|---|---|
| `text` | Textwert | Zeigt den Wert des gebundenen Felds als Text an |
| `image` | Media-URL | Zeigt das Bild der gebundenen Media-URL an |
| `table` | Objekt/Array | Rendert die Daten als Tabelle |
| `list` | Array | Rendert die Daten als Liste |
| `price` | Preiswert | Formatiert und zeigt den Preiswert an |
| `barcode` | String | Generiert einen Barcode (EAN/GTIN) |

Die Auflösung erfolgt über den Feldnamen: Das `binding`-Attribut referenziert das `target`-Feld der Mapping-Konfiguration.

## Beispiel: Exportierter Datensatz

Das folgende Beispiel zeigt einen vollständig exportierten Publixx-Datensatz für ein Produkt "Akkubohrschrauber":

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

## PQL-Abfragen auf Datensätzen

Exportierte Datensätze können nachträglich mit PQL-Abfragen gefiltert werden. Dies ermöglicht es, aus einem grossen Datensatz-Pool gezielt Teilmengen für bestimmte Publixx-Kataloge zu extrahieren:

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

## Weiterführende Dokumentation

- [Export-Übersicht](/de/export/) -- Prozessübersicht und Konzept
- [JSON-Export](/de/export/json-export) -- Generischer JSON-Export
- [PQL-Abfragesprache](/de/api/pql) -- Vollständige PQL-Dokumentation
