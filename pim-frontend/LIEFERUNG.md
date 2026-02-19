# Publixx PIM Frontend — Lieferung Agent 8

> **Datum:** 19.02.2026 | **Agent:** 8 (Frontend) | **Phase:** 3

---

## Dateiliste

```
pim-frontend/
├── package.json                          # Abhängigkeiten + Scripts
├── vite.config.js                        # Vite 6 + Vue Plugin + Tailwind + Proxy
├── tailwind.config.js                    # Referenz (Config in CSS via TailwindCSS 4)
├── index.html                            # Entry HTML mit Font-Preloads
├── .env                                  # API Base URL Konfiguration
├── public/
│   └── favicon.svg
└── src/
    ├── main.js                           # App Bootstrap: Vue + Pinia + Router + i18n
    ├── App.vue                           # Root: Layout + CommandPalette + Keyboard Shortcuts
    ├── assets/
    │   └── main.css                      # TailwindCSS 4 Theme + Design System + Utilities
    ├── router/
    │   └── index.js                      # Vue Router: 14 Routes, Code-Splitting, Auth Guard
    ├── stores/
    │   ├── auth.js                       # Auth State, Token, Panel, Sidebar, CommandPalette
    │   ├── products.js                   # CRUD, Pagination, Sort, Filter, Attribute Values
    │   ├── hierarchies.js                # Tree State, Node Selection, Expand/Collapse
    │   ├── attributes.js                 # Attribute CRUD, Types, ValueLists, ProductTypes
    │   └── locale.js                     # UI Locale, Data Locales, Fallback Chain
    ├── api/
    │   ├── client.js                     # Axios Instance, Interceptors, Token, Error Handling
    │   ├── auth.js                       # POST login/logout, GET me, POST refresh
    │   ├── products.js                   # Products CRUD + Values + Variants + Media + Prices
    │   ├── attributes.js                 # Attributes + Types + UnitGroups + ValueLists + Views
    │   ├── hierarchies.js                # Hierarchies + Nodes + Move + Attribute Assignments
    │   ├── media.js                      # Media Upload + CRUD + fileUrl Helper
    │   ├── imports.js                    # Upload + Status + Preview + Execute + Templates
    │   ├── exports.js                    # Export Products + Bulk + PQL + Publixx Datasets
    │   ├── pql.js                        # PQL Query + Count + Validate + Explain
    │   ├── users.js                      # Users CRUD + Roles CRUD + Permissions
    │   ├── pxfTemplates.js               # PXF Templates CRUD + Preview + Import
    │   └── prices.js                     # PriceTypes + RelationTypes
    ├── composables/
    │   ├── useFilters.js                 # Debounced Search + Filter Chips + Presets
    │   ├── usePql.js                     # PQL Query/Validate/Explain Composable
    │   ├── useInheritance.js             # Inheritance Map + isInherited/isOverridden
    │   ├── useLocale.js                  # Localized Value Helper
    │   └── useDragDrop.js                # Drag & Drop State
    ├── components/
    │   ├── layout/
    │   │   ├── AppLayout.vue             # 3-Column Layout (Sidebar 240px, Main, Panel 360px)
    │   │   ├── AppSidebar.vue            # Navigation mit Lucide Icons, Collapsible
    │   │   └── AppHeader.vue             # Title, Cmd+K Trigger, Locale Switcher, User Menu
    │   ├── shared/
    │   │   ├── PimTable.vue              # Generische Tabelle: Sort, Select, Skeleton, Pagination
    │   │   ├── PimTree.vue               # Rekursiver Baum: Expand, Select, D&D, Context Menu
    │   │   ├── PimForm.vue               # Dynamic Form: Fields aus Schema, Cmd+S Save
    │   │   ├── PimAttributeInput.vue     # Input by Type: text/number/select/boolean/date/json
    │   │   ├── PimCollectionGroup.vue    # Accordion mit Fortschrittsbalken
    │   │   ├── PimCommandPalette.vue     # Cmd+K: Navigation + Actions, Fuzzy Filter
    │   │   └── PimFilterBar.vue          # Search + Filter Chips + Presets
    │   └── pxf/
    │       ├── PxfDataResolver.js        # Binding, NumberFormat, DateFormat, TextTransform,
    │       │                               Visibility, FormattingRules, AssetURL Resolution
    │       ├── PxfRenderer.vue           # Container: Zoom Controls + Page Navigation
    │       ├── PxfPage.vue               # Single Page: Element Dispatch, Visibility Check
    │       └── elements/
    │           ├── PxfTextElement.vue     # text + fixedText (Style, Prefix/Suffix, Formatting)
    │           ├── PxfImageElement.vue    # image (AssetBase, Filters, ObjectFit)
    │           ├── PxfRectElement.vue     # rect (Fill, Stroke, Radius)
    │           ├── PxfLineElement.vue     # line (Horizontal/Vertical)
    │           ├── PxfListElement.vue     # list (Bullet Types, Spacing)
    │           ├── PxfTableElement.vue    # table + smartTable (Auto-Columns, Header, Alternating)
    │           ├── PxfChartElement.vue    # chart (Chart.js: bar/line/pie/doughnut/radar)
    │           ├── PxfBarcodeElement.vue  # barcode (JsBarcode) + qrcode (qrcode lib)
    │           ├── PxfMapElement.vue      # map (Leaflet + OpenStreetMap)
    │           ├── PxfGroupElement.vue    # group/repeater (H/V/Grid, Children)
    │           ├── PxfVideoElement.vue    # video (Controls, Autoplay, ObjectFit)
    │           ├── PxfAudioElement.vue    # audio (Controls, Loop, Volume)
    │           └── PxfSmartObject.vue     # smartObject (Scoped Data, Recursive)
    └── views/
        ├── LoginView.vue                 # Login Form
        ├── NotFoundView.vue              # 404
        ├── dashboard/DashboardView.vue   # KPI Cards
        ├── search/SearchView.vue         # Global PQL Search
        ├── products/
        │   ├── ProductListView.vue       # Table + Filter + Pagination + Sort
        │   └── ProductDetailView.vue     # Tabs: Attributes, Variants, Media, Prices, PXF Preview
        ├── hierarchies/HierarchyView.vue # Split: Tree + Node Detail
        ├── attributes/AttributeAdminView.vue  # Table + CRUD
        ├── valueLists/ValueListView.vue  # Table + CRUD
        ├── imports/ImportView.vue        # Upload Zone + Status + Templates
        ├── exports/ExportView.vue        # PQL Export + JSON Preview
        ├── media/MediaView.vue           # Grid/List + Upload
        ├── prices/PriceView.vue          # Platzhalter (Preise pro Produkt)
        ├── users/UserView.vue            # User Table + Roles
        └── settings/SettingsView.vue     # Locale + Darstellung
```

---

## Installation

```bash
cd pim-frontend

# Node.js 20+ erforderlich
npm install

# Development Server starten
npm run dev
# => http://localhost:3000

# Production Build
npm run build
# => dist/ Ordner
```

---

## API-Base-URL Konfiguration

In `.env` (oder `.env.local` für lokale Overrides):

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME=Publixx PIM
VITE_DEFAULT_LOCALE=de
```

Der Vite Dev-Server proxied `/api` automatisch auf `http://localhost:8000`.

---

## Design-System

| Token | Wert |
|-------|------|
| Primary | `#1B3A5C` (Tiefblau) |
| Accent | `#2E75B6` (Mittelblau) |
| Background | `#FAFBFC` |
| Surface | `#FFFFFF` |
| Border | `#E5E7EB` |
| Text Primary | `#111827` |
| Text Secondary | `#6B7280` |
| Success | `#059669` |
| Warning | `#D97706` |
| Error | `#DC2626` |
| Font UI | Inter Variable |
| Font Mono | JetBrains Mono |
| Spacing | 4px Raster |
| Radii | 6px (Buttons), 8px (Cards), 12px (Modals) |

**Designsprache:** Industrieller Minimalismus (Linear.app, Notion, Figma)

---

## Keyboard Shortcuts

| Shortcut | Aktion |
|----------|--------|
| `Cmd+K` | Command Palette oeffnen/schliessen |
| `Cmd+S` | Speichern (in Formularen) |
| `Cmd+N` | Neues Element |
| `/` | Suche fokussieren |
| `Escape` | Modal/Panel schliessen |
| `Arrow Up/Down` | Navigation in Listen |

---

## PXF-Renderer

Alle 15 PXF-Elementtypen sind implementiert:

1. **text** — Datengebundener Text mit NumberFormat, DateFormat, TextTransform
2. **fixedText** — Statischer Text
3. **image** — Bild mit AssetBase-Aufloesung, CSS-Filtern
4. **rect** — Farbflaeche
5. **line** — Trennlinie (horizontal/vertical)
6. **list** — Bullet-Liste
7. **table** — Einfache Tabelle
8. **smartTable** — Tabelle mit PTL-Spalten
9. **chart** — Chart.js (bar, line, pie, doughnut, radar, polarArea, horizontalBar)
10. **barcode** — JsBarcode (CODE128)
11. **qrcode** — QR-Code
12. **map** — Leaflet/OpenStreetMap
13. **video** — HTML5 Video
14. **audio** — HTML5 Audio
15. **smartObject** — Sub-Template mit eigenem Daten-Scope
16. **group** — Repeater (horizontal/vertical/grid)

Features:
- Dot-Notation Binding (`technischeDaten.drehmoment.value`)
- Visibility Rules (AND/OR mit 8 Operatoren)
- Formatting Rules (conditional styles)
- Text Transform (15 Transformationen)
- Template Routing (pageType -> Template)
- Zoom Controls + Page Navigation

---

## Fehlende Backend-Abhaengigkeiten

Folgende API-Endpunkte werden vom Frontend benoetigt (Agent 3):

### Auth (Agent 2)
- `POST /api/v1/auth/login` — Login, liefert `{ token, user }`
- `POST /api/v1/auth/logout` — Token invalidieren
- `GET /api/v1/auth/me` — User + Rechte + Rolle

### Products (Agent 3)
- `GET /api/v1/products` — Paginiert, filterbar, sortierbar, suchbar
- `POST /api/v1/products` — Anlegen
- `GET /api/v1/products/{id}` — Mit includes: attributeValues, variants, media, prices
- `PUT /api/v1/products/{id}` — Aktualisieren
- `DELETE /api/v1/products/{id}` — Loeschen
- `GET /api/v1/products/{id}/attribute-values` — Attributwerte
- `PUT /api/v1/products/{id}/attribute-values` — Bulk Save

### Hierarchies (Agent 3)
- `GET /api/v1/hierarchies` — Liste
- `GET /api/v1/hierarchies/{id}/tree` — Baum als JSON
- `POST /api/v1/hierarchies/{id}/nodes` — Knoten anlegen
- `PUT /api/v1/hierarchy-nodes/{id}/move` — Knoten verschieben

### Attributes (Agent 3)
- `GET /api/v1/attributes` — Paginiert, mit includes
- `GET /api/v1/attribute-types` — Gruppen
- `GET /api/v1/value-lists` — Mit entries
- `GET /api/v1/product-types` — Produkttypen

### Media (Agent 3)
- `POST /api/v1/media` — Upload (multipart)
- `GET /api/v1/media` — Liste
- `GET /api/v1/media/file/{filename}` — Datei ausliefern

### Import (Agent 6)
- `POST /api/v1/imports` — Excel Upload
- `GET /api/v1/imports/{id}` — Status
- `POST /api/v1/imports/{id}/execute` — Ausfuehren
- `GET /api/v1/imports/templates/{type}` — Excel-Vorlage

### Export (Agent 7)
- `POST /api/v1/export/query` — PQL-basierter Export

### PQL (Agent 5)
- `POST /api/v1/pql/query` — Query ausfuehren
- `POST /api/v1/pql/query/validate` — Validieren

### PXF Templates (Agent 3)
- `GET /api/v1/pxf-templates` — Liste
- `GET /api/v1/pxf-templates/{id}/preview/{product_id}` — Live Preview

### Users (Agent 2)
- `GET /api/v1/users` — Liste mit Rollen
- `CRUD /api/v1/users/{id}` — Benutzerverwaltung
- `GET /api/v1/roles` — Rollen mit Permissions

---

## Response-Format Erwartung

Alle API-Antworten werden im Laravel-Standard-Format erwartet:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 50,
    "total": 487
  }
}
```

Fehler im RFC 7807 Format:
```json
{
  "type": "https://pim.example.com/errors/validation",
  "title": "Validation Error",
  "status": 422,
  "detail": "The given data was invalid.",
  "errors": { "sku": ["SKU ist bereits vergeben"] }
}
```
