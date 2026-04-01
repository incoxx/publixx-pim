# Offline-Katalog — Aufbau des Datenpakets

Der Offline-Katalog-Generator (`OfflineCatalogExportService`) erzeugt ein ZIP-Archiv,
das einen vollständig funktionsfähigen Produktkatalog ohne Server-Anbindung enthält.

---

## ZIP-Verzeichnisstruktur

```
offline-catalog_2026-03-24_143052.zip
├── index.html                          # HTML-Einstiegsseite
├── catalog-offline.umd.js             # Vue 3 Widget-Bundle (UMD)
├── catalog-embed.css                  # Stylesheet
│
├── data/
│   ├── products/
│   │   ├── index.json                 # Chunk-Index (Metadaten)
│   │   ├── chunk-0.json              # Produkte 1–500
│   │   ├── chunk-1.json              # Produkte 501–1000
│   │   └── chunk-{n}.json            # ...weitere Chunks
│   │
│   ├── products-detail/
│   │   ├── 0/
│   │   │   ├── {product-uuid}.json   # Detail-JSON pro Produkt
│   │   │   └── ...                    # max. 1000 Dateien pro Bucket
│   │   ├── 1/
│   │   │   └── ...
│   │   └── {bucket}/
│   │       └── ...
│   │
│   ├── search-index/
│   │   ├── index.json                 # Such-Index Metadaten
│   │   ├── chunk-0.json              # Nicht-Hierarchie-Produkte
│   │   └── chunk-{n}.json
│   │
│   ├── categories.json                # Kategorie-Baum
│   ├── facets.json                    # Filter-Definitionen
│   ├── attribute-groups.json          # Attributgruppen
│   └── settings.json                  # Theme & UI-Konfiguration
```

### Konstanten

| Konstante | Wert | Beschreibung |
|-----------|------|--------------|
| `CHUNK_SIZE` | 500 | Produkte pro Chunk-Datei |
| `DETAIL_DIR_SIZE` | 1000 | Max. Detail-JSONs pro Bucket-Unterordner |

---

## products/index.json

Metadaten über die Produkt-Chunks. Wird als erstes vom Client geladen.

```json
{
  "totalProducts": 12500,
  "chunkSize": 500,
  "chunks": [
    "chunk-0.json",
    "chunk-1.json",
    "chunk-2.json"
  ],
  "detailDirSize": 1000,
  "detailBuckets": 13,
  "relationDetailMap": {
    "uuid-produkt-ohne-hierarchie": 12,
    "uuid-weiteres-produkt": 12
  }
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `totalProducts` | `int` | Anzahl Hierarchie-Produkte (Browse-Index) |
| `chunkSize` | `int` | Produkte pro Chunk (immer 500) |
| `chunks` | `string[]` | Dateinamen der Chunk-Dateien |
| `detailDirSize` | `int` | Max. Dateien pro Detail-Bucket (immer 1000) |
| `detailBuckets` | `int` | Anzahl Detail-Unterordner |
| `relationDetailMap` | `object` | Optional. Mapping `productId → bucket` fuer Produkte ohne Hierarchie-Zuordnung (Relations-Ziele, Ersatzteile etc.) |

---

## products/chunk-{n}.json

Array von Produkten im **kompakten Listenformat**. Wird fuer Produktkarten, Filterung und Sortierung verwendet. Felder sind bewusst kurz benannt, um bei grossen Katalogen (100k+) Speicher zu sparen.

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "sku": "AKB-PRO-18V",
    "name": "Akkubohrer Professional 18V",
    "cat": "uuid-kategorie-bohrmaschinen",
    "cats": [
      "uuid-root",
      "uuid-elektrowerkzeuge",
      "uuid-kategorie-bohrmaschinen"
    ],
    "img": "/api/v1/catalog/media/akkubohrer-pro-18v.jpg",
    "ean": "4006209876543",
    "cat_name": "Bohrmaschinen",
    "price": 189.99,
    "cur": "EUR",
    "primary": "1.8 kg",
    "attrs": [
      {
        "attribute_id": "uuid-attr-spannung",
        "label": "Spannung",
        "value": "18 V"
      },
      {
        "attribute_id": "uuid-attr-gewicht",
        "label": "Gewicht",
        "value": "1.8 kg"
      }
    ],
    "search": "Akkubohrer Professional 18V Li-Ion Bohrschrauber kabellos...",
    "facets": {
      "uuid-attr-spannung": "uuid-valuelist-18v",
      "uuid-attr-schutzart": "uuid-valuelist-ip55",
      "uuid-attr-gewicht": 1.8,
      "uuid-attr-led": true
    },
    "_dd": 0
  }
]
```

### Felder

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `id` | `string` | Ja | Produkt-UUID |
| `sku` | `string` | Ja | Artikelnummer |
| `name` | `string` | Ja | Produktname (sprachabhängig) |
| `cat` | `string\|null` | Ja | Direkter Kategorie-Knoten-ID |
| `cats` | `string[]` | Ja | Alle Vorfahr-Kategorie-IDs + eigene Kategorie (fuer hierarchische Filterung) |
| `img` | `string\|null` | Ja | URL zum Produktbild |
| `ean` | `string` | Nein | EAN/GTIN (nur wenn vorhanden) |
| `cat_name` | `string` | Nein | Kategorie-Anzeigename |
| `price` | `float` | Nein | Preis (aus konfiguriertem Preistyp oder Listenpreis) |
| `cur` | `string` | Nein | Währung (nur wenn nicht `EUR`) |
| `primary` | `string` | Nein | Primäres Karten-Attribut (z.B. "1.8 kg") |
| `attrs` | `object[]` | Nein | Karten-Attribute (vom WebsiteProfile konfiguriert) |
| `attrs[].attribute_id` | `string` | Ja | Attribut-UUID |
| `attrs[].label` | `string` | Ja | Attribut-Anzeigename |
| `attrs[].value` | `string` | Ja | Formatierter Wert inkl. Einheit |
| `search` | `string` | Nein | Durchsuchbarer Text (max. 500 Zeichen, aus SearchIndex) |
| `facets` | `object` | Nein | Facetten-Werte fuer clientseitige Filterung |
| `_dd` | `int` | Ja | Detail-Bucket-Nummer (Unterordner in `products-detail/`) |

### Facetten-Werttypen in `facets`

| Attribut-Datentyp | Wert in `facets` | Beispiel |
|-------------------|-----------------|---------|
| ValueList / Selection / Dictionary | `value_selection_id` (String) | `"uuid-wert-ip55"` |
| Flag | `boolean` | `true` |
| Number / Float / Decimal / Integer | `float` | `1.8` |
| Text / String | `value_string` (String) | `"Edelstahl"` |

---

## products-detail/{bucket}/{id}.json

Vollständiges Produktdetail. Wird on-demand geladen, wenn ein Nutzer ein Produkt anklickt.

Die Dateien sind in Bucket-Unterordner aufgeteilt (max. 1000 pro Ordner), um Dateisystem-Limits zu vermeiden.

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "sku": "AKB-PRO-18V",
  "ean": "4006209876543",
  "name": "Akkubohrer Professional 18V",
  "description": "Der leistungsstarke Akkubohrer fuer den professionellen Einsatz...",
  "breadcrumb": [
    { "id": "uuid-root", "name": "Sortiment" },
    { "id": "uuid-elektrowerkzeuge", "name": "Elektrowerkzeuge" },
    { "id": "uuid-bohrmaschinen", "name": "Bohrmaschinen" }
  ],
  "attributes": [
    {
      "attribute_id": "uuid-attr-spannung",
      "technical_name": "spannung-v",
      "label": "Spannung",
      "data_type": "Number",
      "value": "18 V",
      "raw_value": "18",
      "unit": "V",
      "group_id": "uuid-attrgroup-technisch"
    },
    {
      "attribute_id": "uuid-attr-abmessung",
      "technical_name": "abmessung-lbh",
      "label": "Abmessung (L x B x H)",
      "data_type": "Composite",
      "value": "280 x 85 x 240 mm",
      "raw_value": "280 x 85 x 240 mm",
      "unit": null,
      "group_id": "uuid-attrgroup-technisch"
    }
  ],
  "description_attributes": [
    {
      "attribute_id": "uuid-attr-kurzbeschreibung",
      "label": "Kurzbeschreibung",
      "value": "Kompakter 18V Akkubohrer mit bürstenlosem Motor",
      "typography": "heading"
    },
    {
      "attribute_id": "uuid-attr-langtext",
      "label": "Beschreibung",
      "value": "Der Professional 18V bietet maximale Leistung...",
      "typography": "base"
    }
  ],
  "media": [
    {
      "id": "uuid-media-1",
      "file_name": "akkubohrer-pro-18v.jpg",
      "mime_type": "image/jpeg",
      "url": "/api/v1/catalog/media/akkubohrer-pro-18v.jpg"
    },
    {
      "id": "uuid-media-2",
      "file_name": "akkubohrer-pro-18v-detail.pdf",
      "mime_type": "application/pdf",
      "url": "/api/v1/catalog/media/akkubohrer-pro-18v-detail.pdf"
    }
  ],
  "prices": [
    {
      "amount": 189.99,
      "currency": "EUR",
      "price_type": "Listenpreis"
    },
    {
      "amount": 159.99,
      "currency": "EUR",
      "price_type": "Händlerpreis"
    }
  ],
  "relations": [
    {
      "type": "Zubehör",
      "target_id": "uuid-produkt-akku",
      "target_sku": "AKK-18V-5AH",
      "target_name": "Akku 18V 5.0 Ah"
    }
  ],
  "variants": [
    {
      "id": "uuid-variante-1",
      "sku": "AKB-PRO-18V-BL",
      "name": "Blau"
    }
  ]
}
```

### Felder

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | `string` | Produkt-UUID |
| `sku` | `string` | Artikelnummer |
| `ean` | `string\|null` | EAN/GTIN |
| `name` | `string` | Produktname (sprachabhängig) |
| `description` | `string\|null` | Produkt-Kurzbeschreibung (aus SearchIndex) |
| `breadcrumb` | `object[]` | Kategorie-Pfad vom Root bis zur aktuellen Kategorie |
| `breadcrumb[].id` | `string` | Knoten-UUID |
| `breadcrumb[].name` | `string` | Knoten-Anzeigename |
| `attributes` | `object[]` | Alle Produkt-Attribute |
| `attributes[].attribute_id` | `string` | Attribut-UUID |
| `attributes[].technical_name` | `string` | Technischer Name |
| `attributes[].label` | `string` | Anzeigename (sprachabhängig) |
| `attributes[].data_type` | `string` | Datentyp: `String`, `Number`, `Flag`, `ValueList`, `Selection`, `Dictionary`, `Composite`, `Date` |
| `attributes[].value` | `string` | Formatierter Wert inkl. Einheit |
| `attributes[].raw_value` | `string` | Rohwert ohne Einheit |
| `attributes[].unit` | `string\|null` | Einheiten-Kürzel (z.B. `kg`, `mm`, `V`) |
| `attributes[].group_id` | `string\|null` | Attributgruppen-UUID (fuer Gruppierung in der Detailansicht) |
| `description_attributes` | `object[]` | Beschreibungs-Attribute (konfiguriert im WebsiteProfile) |
| `description_attributes[].attribute_id` | `string` | Attribut-UUID |
| `description_attributes[].label` | `string` | Anzeigename |
| `description_attributes[].value` | `string` | Formatierter Wert |
| `description_attributes[].typography` | `string` | Typografie-Stil: `heading`, `base` |
| `media` | `object[]` | Alle Medien (Bilder, PDFs etc.) |
| `media[].id` | `string` | Medien-UUID |
| `media[].file_name` | `string` | Dateiname |
| `media[].mime_type` | `string` | MIME-Typ |
| `media[].url` | `string` | URL (absolut oder relativ) |
| `prices` | `object[]` | Alle gültigen Preise |
| `prices[].amount` | `float` | Betrag |
| `prices[].currency` | `string` | Währung |
| `prices[].price_type` | `string\|null` | Preistyp-Name |
| `relations` | `object[]` | Produktrelationen (Zubehör, Ersatzteile etc.) |
| `relations[].type` | `string\|null` | Relationstyp-Name |
| `relations[].target_id` | `string` | Zielprodukt-UUID |
| `relations[].target_sku` | `string\|null` | Zielprodukt-SKU |
| `relations[].target_name` | `string\|null` | Zielprodukt-Name |
| `variants` | `object[]` | Produktvarianten |
| `variants[].id` | `string` | Varianten-UUID |
| `variants[].sku` | `string` | Varianten-SKU |
| `variants[].name` | `string` | Varianten-Name |

---

## search-index/index.json

Metadaten fuer den Such-Index. Enthält nur Produkte, die **nicht** in den Haupt-Chunks (`products/`) enthalten sind (d.h. Produkte ohne Hierarchie-Zuordnung). Wird lazy geladen beim ersten Suchvorgang.

```json
{
  "totalProducts": 3200,
  "chunks": [
    "chunk-0.json",
    "chunk-1.json"
  ]
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `totalProducts` | `int` | Anzahl Nicht-Hierarchie-Produkte |
| `chunks` | `string[]` | Chunk-Dateinamen (je max. 2000 Einträge) |

## search-index/chunk-{n}.json

Leichtgewichtige Einträge — nur Such-relevante Felder, keine Facetten oder Karten-Attribute.

```json
[
  {
    "id": "uuid-produkt-123",
    "name": "Ersatzteil Kohlebürste Set",
    "sku": "ET-KB-SET-01",
    "ean": "4006201234567",
    "search": "Kohlebürste Motor Ersatzteil Winkelschleifer...",
    "img": "/api/v1/catalog/media/kohlebuerste-set.jpg",
    "_dd": 12
  }
]
```

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `id` | `string` | Ja | Produkt-UUID |
| `name` | `string` | Ja | Produktname |
| `sku` | `string` | Ja | Artikelnummer |
| `ean` | `string` | Nein | EAN/GTIN |
| `search` | `string` | Nein | Durchsuchbarer Text (max. 300 Zeichen) |
| `img` | `string` | Nein | Bild-URL |
| `_dd` | `int` | Nein | Detail-Bucket (nur wenn Detail-JSON existiert, z.B. via `relationDetailMap`) |

---

## categories.json

Hierarchischer Kategorie-Baum mit Produkt-Anzahlen. Produkt-Counts werden von unten nach oben aggregiert (Kindknoten-Counts fliessen in Elternknoten ein).

```json
{
  "data": {
    "hierarchy_id": "uuid-hierarchie-master",
    "hierarchy_name": "Produktkatalog",
    "type": "master",
    "nodes": [
      {
        "id": "uuid-elektrowerkzeuge",
        "name": "Elektrowerkzeuge",
        "product_count": 1250,
        "children": [
          {
            "id": "uuid-bohrmaschinen",
            "name": "Bohrmaschinen",
            "product_count": 340,
            "children": []
          },
          {
            "id": "uuid-schleifer",
            "name": "Schleifmaschinen",
            "product_count": 210,
            "children": []
          }
        ]
      }
    ]
  }
}
```

### Felder (Root)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `hierarchy_id` | `string\|null` | Hierarchie-UUID |
| `hierarchy_name` | `string\|null` | Hierarchie-Anzeigename |
| `type` | `string` | Hierarchie-Typ (`master`) |
| `nodes` | `object[]` | Wurzel-Knoten des Baums |

### Felder (Node, rekursiv)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | `string` | Knoten-UUID |
| `name` | `string` | Anzeigename (sprachabhängig) |
| `product_count` | `int` | Produkte in diesem Knoten + allen Kindern |
| `children` | `object[]` | Kind-Knoten (gleiche Struktur, rekursiv) |

---

## facets.json

Filter-Definitionen fuer die Facettensuche. Pro konfiguriertem Facetten-Attribut ein Eintrag. Wird einmalig geladen und fuer die Sidebar-Filter verwendet.

```json
{
  "facets": [
    {
      "attribute_id": "uuid-attr-schutzart",
      "label": "Schutzart",
      "data_type": "ValueList",
      "values": [
        { "value": "IP55", "value_id": "uuid-vle-ip55", "count": 342 },
        { "value": "IP44", "value_id": "uuid-vle-ip44", "count": 128 }
      ]
    },
    {
      "attribute_id": "uuid-attr-led",
      "label": "LED-Beleuchtung",
      "data_type": "Boolean",
      "values": [
        { "value": "Ja", "filter_value": "1", "count": 890 },
        { "value": "Nein", "filter_value": "0", "count": 360 }
      ]
    },
    {
      "attribute_id": "uuid-attr-gewicht",
      "label": "Gewicht",
      "data_type": "Decimal",
      "min": 0.3,
      "max": 12.5,
      "count": 1180,
      "unit": "kg"
    },
    {
      "attribute_id": "uuid-attr-material",
      "label": "Material",
      "data_type": "Text",
      "values": [
        { "value": "Edelstahl", "value_id": "Edelstahl", "count": 95 },
        { "value": "Aluminium", "value_id": "Aluminium", "count": 63 }
      ]
    }
  ]
}
```

### Facetten-Typen

#### ValueList (inkl. Selection, Dictionary)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `data_type` | `string` | `"ValueList"` |
| `values` | `object[]` | Verfügbare Werte |
| `values[].value` | `string` | Anzeigename |
| `values[].value_id` | `string` | Wert-ID (zum Abgleich mit `facets` im Produkt) |
| `values[].count` | `int` | Produkte mit diesem Wert |

#### Boolean (Flag)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `data_type` | `string` | `"Boolean"` |
| `values` | `object[]` | Zwei Einträge: Ja/Nein |
| `values[].value` | `string` | `"Ja"` oder `"Nein"` |
| `values[].filter_value` | `string` | `"1"` oder `"0"` |
| `values[].count` | `int` | Produkte mit diesem Flag-Wert |

#### Decimal (Zahlenbereiche)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `data_type` | `string` | `"Decimal"` |
| `min` | `float\|null` | Kleinster Wert im Katalog |
| `max` | `float\|null` | Grösster Wert im Katalog |
| `count` | `int` | Produkte mit gesetztem Zahlenwert |
| `unit` | `string\|null` | Einheit (z.B. `"kg"`, `"mm"`) |

#### Text (String-Werte)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `data_type` | `string` | `"Text"` |
| `values` | `object[]` | Top 20 häufigste Werte |
| `values[].value` | `string` | Textwert |
| `values[].value_id` | `string` | Gleich wie `value` |
| `values[].count` | `int` | Produkte mit diesem Wert |

---

## attribute-groups.json

Attributgruppen-Metadaten fuer die Gruppierung von Attributen in der Produktdetailansicht.

```json
{
  "data": [
    {
      "id": "uuid-attrgroup-technisch",
      "name": "Technische Daten",
      "sort_order": 1
    },
    {
      "id": "uuid-attrgroup-allgemein",
      "name": "Allgemein",
      "sort_order": 2
    }
  ]
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | `string` | Attributgruppen-UUID |
| `name` | `string` | Anzeigename (sprachabhängig) |
| `sort_order` | `int` | Sortierreihenfolge |

---

## settings.json

Theme- und UI-Konfiguration aus dem WebsiteProfile. Steuert das Erscheinungsbild und die verfügbaren Funktionen im Offline-Katalog.

```json
{
  "data": {
    "catalog_name": "Produktkatalog 2026",
    "primary_color": "#1B3A5C",
    "card_show_sku": true,
    "card_show_category": true,
    "card_show_price": true,
    "catalog_compare_enabled": true,
    "catalog_compare_max_products": 3,
    "catalog_share_wishlist_enabled": true,
    "catalog_pdf_enabled": true,
    "catalog_excel_export_enabled": false,
    "catalog_access_mode": "public",
    "mode": "offline"
  }
}
```

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `catalog_name` | `string` | Katalog-Titel (wird im Header angezeigt) |
| `primary_color` | `string` | Primärfarbe (CSS Hex) |
| `card_show_sku` | `bool` | SKU auf Produktkarten anzeigen |
| `card_show_category` | `bool` | Kategorie auf Produktkarten anzeigen |
| `card_show_price` | `bool` | Preis auf Produktkarten anzeigen |
| `catalog_compare_enabled` | `bool` | Produktvergleich aktiviert |
| `catalog_compare_max_products` | `int` | Max. Produkte im Vergleich |
| `catalog_share_wishlist_enabled` | `bool` | Merkzettel-Teilen aktiviert |
| `catalog_pdf_enabled` | `bool` | PDF-Download aktiviert (client-seitig via jsPDF) |
| `catalog_excel_export_enabled` | `bool` | Immer `false` im Offline-Modus |
| `catalog_access_mode` | `string` | Immer `"public"` im Offline-Modus |
| `mode` | `string` | Immer `"offline"` |

---

## Statische Dateien

### index.html

HTML-Einstiegsseite mit Widget-Platzhaltern (`data-catalog="..."`) und dem eingebetteten Init-Script:

```html
<link rel="stylesheet" href="./catalog-embed.css">
<script src="./catalog-offline.umd.js"></script>
<script>
  PublixxCatalogOffline.init({
    dataPath: './data/',
    locale: 'de',
    perPage: 24,
  })
</script>
```

Die HTML-Vorlage stammt aus einem `CatalogTemplate` (Datenbank) oder dem Fallback-Template.

### catalog-offline.umd.js

Vite-gebautes UMD-Bundle. Enthält die komplette Vue 3 Offline-App inkl. aller Widgets, Store, Offline-API und jsPDF.

### catalog-embed.css

Stylesheet mit CSS Custom Properties (Prefix `pxc-`). Wird durch `primary_color` aus `settings.json` zur Laufzeit überschrieben.

---

## Datenfluss beim Laden

```
1. Browser öffnet index.html
2. catalog-offline.umd.js wird geladen
3. PublixxCatalogOffline.init() wird aufgerufen
4. Parallel geladen:
   ├── data/settings.json          → Theme anwenden
   ├── data/categories.json        → Kategorie-Baum rendern
   ├── data/facets.json            → Filter-Sidebar aufbauen
   └── data/attribute-groups.json  → Attributgruppen cachen
5. data/products/index.json laden  → Chunk-Liste ermitteln
6. Chunks laden (4 parallel):
   ├── data/products/chunk-0.json
   ├── data/products/chunk-1.json
   ├── data/products/chunk-2.json
   └── data/products/chunk-3.json
   ... (weiter in 4er-Batches)
7. Alle Produkte im Speicher → Produktraster anzeigen
8. Bei Klick auf Produkt:
   └── data/products-detail/{bucket}/{id}.json laden
9. Bei erster Textsuche:
   └── data/search-index/index.json + Chunks lazy laden
```
