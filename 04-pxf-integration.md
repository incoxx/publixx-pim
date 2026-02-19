# Publixx PIM — PXF-Integration & Visualisierung

> **Zweck:** PXF-Templates aus Publixx im PIM rendern. Verwende diesen Skill beim Bauen des PXF-Renderers, der Preview-API, des Template-Managements und der Publixx-Anbindung.

---

## Konzept

PXF (Publixx Exchange Format) v2.5 ist das Layout-Format von Publixx. Das PIM nutzt PXF-Templates, um Produktdaten visuell darzustellen — als Datenblätter, Katalogseiten, Highlight-Karten. Statt plumper Listen sehen User ihre Daten im Layout des Kunden.

---

## Workflow

```
1. Designer erstellt Template in Publixx → Export als .pxf
2. PXF wird im PIM hinterlegt (Upload / API)
3. User öffnet Produkt im PIM
4. Backend liefert Produktdaten als JSON-Dataset (via Export-Mapping)
5. Frontend PXF-Renderer injiziert Daten → rendert Layout
6. User sieht gestaltetes Datenblatt
```

---

## PXF 2.5 Struktur (Referenz)

```json
{
  "format": "publixx-pxf",
  "version": "2.5.0",
  "encrypted": false,
  "template": {
    "orientation": "a4hoch",      // a4hoch (794x1122), a4quer, custom
    "margin": 0,
    "grid": 8,
    "elements": [...]
  },
  "data": [                        // Ein Record = eine Seite
    { "pageType": "highlight", "label": "...", "title": "...", ... }
  ],
  "config": {
    "assetBase": "https://pim.example.com/api/v1/media/file/",
    "defaultFontFamily": "Arial",
    "defaultFontSize": 12
  },
  "templateRouting": {
    "enabled": true,
    "templates": [
      { "name": "Highlight", "elements": [...] },
      { "name": "Grid", "elements": [...] }
    ],
    "rules": [
      { "field": "pageType", "operator": "equals", "value": "highlight", "templateIndex": 0 },
      { "field": "pageType", "operator": "equals", "value": "grid", "templateIndex": 1 }
    ]
  }
}
```

---

## Element-Typen (alle 15)

| Typ | Binding | Beschreibung |
|-----|---------|--------------|
| `text` | `bind: "field"` (Dot-Notation) | Datengebundener Text |
| `fixedText` | `text: "..."` (statisch) | Labels, Überschriften |
| `image` | `bind: "imageField"` + `urlConfig.useAssetBase` | Bild aus Medienbibliothek |
| `rect` | Kein Binding | Farbfläche, Header-Balken |
| `line` | Kein Binding | Trennlinie |
| `list` | `bind: "stringArray"` | Bullet-Liste |
| `table` | `bind: "arrayOfObjects"` | Einfache Tabelle |
| `smartTable` | `bind: "array"` + `ptl` (Pivot) | Datentabelle mit Sort/Filter/Merge |
| `smartObject` | `smartObjectConfig.dataPath` | Sub-Template, eigener Daten-Scope |
| `chart` | `bind: "chartData"` | Chart.js (bar, line, pie, ...) |
| `barcode` | `bind: "eanField"` | Barcode |
| `qrcode` | `bind: "urlField"` | QR-Code |
| `map` | `mapConfig.latBind/lonBind` | OpenStreetMap |
| `audio` | `audioConfig.srcBind` | Audio-Player |
| `video` | `videoConfig.srcBind` | Video-Player |
| `group` | `iterator.source` | Repeater (horizontal/vertical) |

### Element-Positionierung

Jedes Element hat: `x`, `y`, `w`, `h` (Pixel). A4 Hochformat = 794 x 1122 px.

---

## Template-Routing: Produkttyp → pageType

| PIM Produkttyp | PXF pageType | Template |
|----------------|-------------|----------|
| physical_product | highlight | Großes Bild, Tech-Daten, Preis |
| training | training_detail | Kursinfo, Termine, QR |
| service | service_card | Beschreibung, Preis |
| bundle | grid | smartObject-Raster |
| (Vergleich) | comparison | smartTable Pivot |

---

## PIM-Backend: Preview-API

### GET /api/v1/pxf-templates/{id}/preview/{product_id}

Liefert ein komplett befülltes PXF:

1. Template laden (`pxf_templates.pxf_data`)
2. Export-Mapping anwenden (`export_mapping_id`)
3. Produktdaten als JSON-Dataset aufbereiten
4. `config.assetBase` auf PIM-Media-URL setzen
5. Dataset in `data[]` Array einfügen
6. Fertiges PXF als JSON zurückgeben

```json
// Response
{
  "format": "publixx-pxf",
  "version": "2.5.0",
  "template": { "orientation": "a4hoch", "elements": [...] },
  "data": [{
    "pageType": "highlight",
    "productName": "Akkubohrschrauber ProDrill 18V",
    "productImage": "prodrill-18v.jpg",
    "technischeDaten": { "drehmoment": { "value": 60, "unit": "Nm" } },
    "preis": { "listenpreis": 189.99, "currency": "EUR" },
    "varianten": [...]
  }],
  "config": { "assetBase": "https://pim.example.com/api/v1/media/file/" },
  "templateRouting": { ... }
}
```

---

## Vue.js PXF-Renderer Komponente

```
<PxfRenderer :pxf="pxfData" :zoom="0.5" />
```

### Architektur

```
PxfRenderer.vue              → Container, Zoom, Seitennavigation
├── PxfPage.vue              → Einzelne Seite (794x1122 scaled)
│   ├── PxfTextElement.vue   → text + fixedText
│   ├── PxfImageElement.vue  → image (mit assetBase)
│   ├── PxfRectElement.vue   → rect
│   ├── PxfLineElement.vue   → line
│   ├── PxfListElement.vue   → list
│   ├── PxfTableElement.vue  → table + smartTable
│   ├── PxfSmartObject.vue   → smartObject (rekursiv!)
│   ├── PxfChartElement.vue  → chart (Chart.js)
│   ├── PxfBarcodeElement.vue → barcode + qrcode
│   ├── PxfMapElement.vue    → map (Leaflet)
│   └── PxfGroupElement.vue  → group/repeater
└── PxfDataResolver.js       → Dot-Notation Binding auflösen
```

### Data-Binding Auflösung

```javascript
// bind: "technischeDaten.drehmoment.value" auf Dataset anwenden:
function resolveBinding(data, bindPath) {
  return bindPath.split('.').reduce((obj, key) => obj?.[key], data);
}
// resolveBinding(dataset, "technischeDaten.drehmoment.value") → 60
```

---

## Einsatzorte im PIM-UI

| Ort | Darstellung | Trigger |
|-----|-------------|---------|
| Produktdetail: Preview-Tab | A4-Seite mit Zoom-Controls | Tab-Klick |
| Produktliste: Hover-Preview | Thumbnail (300px) im rechten Panel | Zeile hovern |
| Export-Ansicht | Galerie aller Seiten | Nach Export |
| Hierarchie: Knoten-Preview | Beispielprodukt im Layout | Knoten selektieren |
| Dashboard | Carousel der letzten Produkte | Auto |

---

## DB: pxf_templates

```sql
CREATE TABLE pxf_templates (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  pxf_data JSON NOT NULL,                    -- Vollständiges PXF-JSON
  version VARCHAR(10) DEFAULT '2.5.0',
  orientation ENUM('a4hoch','a4quer','custom'),
  product_type_id CHAR(36),                  -- FK optional
  export_mapping_id CHAR(36),                -- FK optional
  thumbnail VARCHAR(500),
  is_default BOOLEAN DEFAULT false,
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP, updated_at TIMESTAMP
);
```

---

## Wichtige Regeln (aus PXF-Spezifikation)

1. **Keine Emojis** in Daten oder Templates (brechen PDF-Export)
2. **Relative Bildpfade** — assetBase löst auf
3. **IDs müssen eindeutig sein** pro Template
4. **pageType** ist Pflicht in jedem Data-Record
5. **smartObject bindet relativ** zum dataPath
6. **Max 12 Data-Records, 4 Templates, 8-12 Elemente** pro Template
