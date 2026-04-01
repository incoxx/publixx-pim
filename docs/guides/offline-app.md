# Offline-Katalog — Aufbau der App

Die Offline-App ist ein Vue 3 Widget-Framework, das als UMD-Bundle ausgeliefert wird.
Sie liest vorab exportierte JSON-Dateien und bietet einen vollständigen Produktkatalog
ohne Server-Anbindung — inklusive Suche, Filterung, Sortierung und PDF-Export.

---

## Architektur-Übersicht

```
catalog-embed/
├── src/
│   ├── offline-init.js       # Entry Point (Offline-Modus)
│   ├── offline-api.js         # In-Memory API-Layer (liest JSON-Dateien)
│   ├── index.js               # Entry Point (Online-Modus, zum Vergleich)
│   ├── api.js                 # HTTP-basierte API (Online-Modus)
│   ├── store.js               # Reaktiver Vue 3 Store (gemeinsam)
│   ├── pdf-generator.js       # Client-seitiger PDF-Export (jsPDF)
│   ├── icons.js               # SVG-Icon-Definitionen
│   ├── styles.css             # Stylesheet (CSS Custom Properties)
│   └── widgets/               # 13 Vue 3 Widget-Komponenten
│       ├── SearchWidget.vue
│       ├── CategoriesWidget.vue
│       ├── FacetsWidget.vue
│       ├── ProductGridWidget.vue
│       ├── ProductDetailWidget.vue
│       ├── PaginationWidget.vue
│       ├── ToolbarWidget.vue
│       ├── WishlistWidget.vue
│       ├── WishlistButtonWidget.vue
│       ├── CompareWidget.vue
│       ├── LocaleWidget.vue
│       ├── ActiveFiltersWidget.vue
│       ├── SidebarToggleWidget.vue
│       └── CategorySubtree.vue    # Hilfskomponente fuer rekursiven Baum
├── dist/
│   ├── catalog-offline.umd.js    # Offline-Bundle (gebuildet)
│   ├── catalog-embed.umd.js      # Online-Bundle (gebuildet)
│   ├── catalog-embed.es.js       # Online ES-Module (gebuildet)
│   └── catalog-embed.css          # Gemeinsames Stylesheet
├── examples/
│   └── basic.html                 # Fallback-Template
├── vite.config.js                 # Build-Konfiguration
└── package.json
```

### Online vs. Offline

Die App teilt sich den gleichen Store und die gleichen Widgets. Der Unterschied liegt nur
in der API-Schicht:

| | Online | Offline |
|--|--------|---------|
| Entry Point | `index.js` | `offline-init.js` |
| API | `api.js` (HTTP → PIM-Server) | `offline-api.js` (fetch → lokale JSON) |
| Global | `window.PublixxCatalog` | `window.PublixxCatalogOffline` |
| Bundle | `catalog-embed.umd.js` | `catalog-offline.umd.js` |
| Init | `PublixxCatalog.init({ api: '...' })` | `PublixxCatalogOffline.init({ dataPath: './data/' })` |

---

## Build-System

Vite mit `@vitejs/plugin-vue`. Zwei Build-Targets über die Umgebungsvariable `VITE_BUILD_TARGET`:

```bash
# Online-Bundle (Standard)
cd catalog-embed && npx vite build

# Offline-Bundle
cd catalog-embed && VITE_BUILD_TARGET=offline npx vite build
```

### Vite-Konfiguration (`vite.config.js`)

```js
const onlineConfig = {
  entry: 'src/index.js',
  name: 'PublixxCatalog',
  formats: ['umd', 'es'],
  fileName: (format) => `catalog-embed.${format}.js`,
}

const offlineConfig = {
  entry: 'src/offline-init.js',
  name: 'PublixxCatalogOffline',
  formats: ['umd'],
  fileName: () => 'catalog-offline.umd.js',
}
```

- Alle Abhängigkeiten werden gebundelt (kein `externals`)
- CSS wird nicht gesplittet (`cssCodeSplit: false`) → eine einzige `catalog-embed.css`
- Minifizierung via esbuild

---

## Entry Point — `offline-init.js`

Initialisiert die Offline-App und mountet Vue 3 Widgets.

### Globales Objekt

```js
window.PublixxCatalogOffline = {
  init,        // App initialisieren
  mount,       // Widgets manuell mounten
  destroy,     // Alle Widgets unmounten
  store,       // Zugriff auf den reaktiven Store
  widgets,     // Widget-Map (Name → Komponente)
  version,     // '1.0.0'
  mode,        // 'offline'
}
```

### `init(options)`

| Option | Typ | Default | Beschreibung |
|--------|-----|---------|--------------|
| `dataPath` | `string` | `'./data/'` | Pfad zum Datenverzeichnis |
| `locale` | `string` | `'de'` | Sprache (`'de'` oder `'en'`) |
| `perPage` | `number` | `24` | Produkte pro Seite |
| `autoMount` | `boolean` | `true` | Widgets automatisch per `data-catalog` finden und mounten |
| `showProgress` | `boolean` | `true` | Lade-Overlay mit Fortschritt anzeigen |

### Initialisierungsablauf

```
1. Lade-Overlay anzeigen ("Offline-Katalog wird geladen...")
2. Offline-API erstellen (createOfflineApi)
3. API-Provider im Store tauschen (setApiProvider)
4. Store-Defaults setzen (locale, perPage)
5. Settings laden (fetchSettings)
6. Merkzettel aus URL importieren (importWishlistFromUrl)
7. Widgets mounten (alle [data-catalog] Elemente)
8. Parallel laden: Kategorien, Facetten, Attributgruppen
9. Produkte laden (Chunks mit Fortschritts-Callback)
10. Deeplinks anwenden (?sku=, ?cat=)
11. Lade-Overlay ausblenden
```

### Lade-Overlay

Zeigt während des Chunk-Ladens einen Fortschrittsbalken:

```
┌────────────────────────────────┐
│  Offline-Katalog wird geladen  │
│  8.500 / 12.500 Produkte (68%) │
└────────────────────────────────┘
```

---

## Offline API — `offline-api.js`

Client-seitige virtuelle API-Schicht. Implementiert das gleiche Interface wie die Online-API
(`api.js`), aber alle Operationen laufen in-memory auf den vorgeladenen JSON-Daten.

### `createOfflineApi(dataPath, options)`

Erzeugt ein API-Objekt mit folgenden Methoden:

| Methode | Beschreibung |
|---------|--------------|
| `getProducts(opts)` | Produkte abrufen (gefiltert, sortiert, paginiert) |
| `getProduct(id)` | Einzelnes Produktdetail laden |
| `getCategories()` | Kategorie-Baum |
| `getSettings()` | Theme-Einstellungen |
| `getFacets()` | Facetten-Definitionen |
| `getAttributeGroups()` | Attributgruppen |
| `downloadProductPdf(id)` | PDF fuer ein Produkt generieren (jsPDF) |
| `downloadWishlistPdf(ids, lang)` | Merkzettel-PDF generieren |
| `compareProducts(ids, lang)` | Produktvergleich erstellen |

### Produktladung — Zwei-Index-Strategie

```
┌──────────────────────────────────────────────┐
│ Primary Products (Hierarchie-Produkte)       │
│ → Eager Loading beim Start                   │
│ → Quelle: products/chunk-{n}.json           │
│ → Verwendet fuer: Browse, Filtern, Sortieren │
└──────────────────────────────────────────────┘

┌──────────────────────────────────────────────┐
│ Search Products (Nicht-Hierarchie-Produkte)  │
│ → Lazy Loading bei erster Textsuche          │
│ → Quelle: search-index/chunk-{n}.json       │
│ → Verwendet fuer: Volltextsuche              │
└──────────────────────────────────────────────┘
```

- **Primary Products** werden beim Start in 4er-Batches geladen (4 parallele `fetch()`-Aufrufe)
- **Search Products** werden erst beim ersten Suchvorgang geladen und dann im Speicher behalten

### Suche und Filterung

Alle Filter- und Sortieroperationen laufen clientseitig im Speicher:

| Operation | Implementierung |
|-----------|----------------|
| Textsuche | Substring-Match auf `name`, `sku`, `ean`, `search` |
| Kategorie-Filter | Prüft ob `categoryId` in `cats[]` enthalten ist |
| Facetten-Filter | Abgleich `facets[attrId]` gegen Filter-Werte |
| Sortierung | Nach `name`, `sku` oder `price` (asc/desc) |
| Paginierung | Array-Slice im Speicher |

### Facetten-Filter-Formate

```js
filters = {
  "attr-uuid-1": "value-id-1,value-id-2",   // ValueList: Komma-getrennte IDs
  "attr-uuid-2": "10:50",                    // Decimal: min:max Bereich
  "attr-uuid-3": "1",                        // Boolean: "1" oder "0"
}
```

### Produktdetail-Auflösung

```
getProduct(id):
  1. Suche in Primary Products → _dd Bucket lesen
  2. Falls nicht gefunden: Suche in Search Products → _dd Bucket lesen
  3. Falls nicht gefunden: relationDetailMap[id] → Bucket lesen
  4. Lade: products-detail/{bucket}/{id}.json
```

---

## Store — `store.js`

Reaktiver Vue 3 Store ohne Pinia. Alle Widgets teilen den gleichen State über `useStore()`.

### API-Provider-Swapping

```js
// Default: Online-API
import { catalogApi } from './api.js'
let _api = catalogApi

// Offline-Modus: API tauschen
export function setApiProvider(api, resolveMedia) {
  _api = api
  if (resolveMedia) _resolveMedia = resolveMedia
}
```

### State-Struktur

```js
const state = reactive({
  // Produkte
  products: [],
  currentProduct: null,
  loading: false,
  productLoading: false,
  error: null,

  // Paginierung
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 24,
    total: 0,
  },

  // Filter & Navigation
  search: '',
  selectedCategoryId: null,
  selectedCategoryName: null,
  sort: { field: 'name', order: 'asc' },
  viewMode: 'grid',  // 'grid' oder 'list'
  locale: 'de',

  // Kategorien
  categories: [],
  hierarchyInfo: null,
  categoriesLoading: false,

  // ...weitere: facets, wishlist, compare, settings
})
```

### Actions

- `fetchProducts()` — Produkte laden/aktualisieren
- `fetchProduct(id)` — Einzelprodukt laden
- `fetchCategories()` — Kategorie-Baum laden
- `fetchFacets()` — Facetten laden
- `fetchSettings()` — Einstellungen laden
- `fetchAttributeGroups()` — Attributgruppen laden
- `applyDeeplinks()` — URL-Parameter auswerten (`?sku=`, `?cat=`)
- `importWishlistFromUrl()` — Geteilten Merkzettel aus URL importieren

---

## Widgets

13 Vue 3 Komponenten, die über `data-catalog="widget-name"` Attribute in HTML platziert werden:

```html
<div data-catalog="search"></div>
<div data-catalog="categories"></div>
<div data-catalog="facets"></div>
<div data-catalog="toolbar"></div>
<div data-catalog="product-grid"></div>
<div data-catalog="pagination"></div>
<div data-catalog="wishlist"></div>
<div data-catalog="wishlist-button"></div>
<div data-catalog="product-detail"></div>
<div data-catalog="compare"></div>
<div data-catalog="locale"></div>
<div data-catalog="active-filters"></div>
<div data-catalog="sidebar-toggle"></div>
```

### Widget-Beschreibungen

| Widget | Beschreibung |
|--------|--------------|
| `search` | Suchfeld mit Echtzeit-Eingabe |
| `categories` | Kategorie-Baum (Sidebar) mit Produkt-Counts |
| `facets` | Filter-Sidebar (ValueList-Checkboxen, Bereiche, Flags) |
| `toolbar` | Sortierung, Ansichtsmodus (Grid/List) |
| `product-grid` | Produktkarten-Raster (Grid- oder Listenansicht) |
| `pagination` | Seitennavigation |
| `wishlist` | Merkzettel-Drawer (Slide-in Panel) |
| `wishlist-button` | Merkzettel-Icon mit Badge-Counter |
| `product-detail` | Produktdetail-Modal (Overlay) |
| `compare` | Produktvergleich-Modal |
| `locale` | Sprachumschalter (DE/EN) |
| `active-filters` | Aktive Filter als Pills mit Entfernen-Button |
| `sidebar-toggle` | Hamburger-Menü fuer mobile Sidebar |

### Widget-Mounting

Jedes `[data-catalog]`-Element wird als eigenständige Vue 3 App gemountet:

```js
const app = createApp({
  render() { return h(Component, props) },
})
app.mount(el)
```

Zusätzliche `data-*` Attribute werden als Props übergeben:

```html
<!-- data-per-page wird als perPage-Prop übergeben -->
<div data-catalog="product-grid" data-per-page="12"></div>
```

### Automatisch injizierte Widgets

Falls im HTML-Template fehlend, werden diese Widgets automatisch eingefügt:

- `sidebar-toggle` — nach `<body>` (fixed, oben links)
- `wishlist` — vor `</body>` (Drawer)
- `product-detail` — vor `</body>` (Modal)
- `compare` — vor `</body>` (Modal)

---

## Styling — `styles.css`

Alle CSS-Klassen verwenden den Prefix `pxc-` um Konflikte mit der einbettenden Seite zu vermeiden.

### CSS Custom Properties

```css
:root {
  --pxc-primary: #1B3A5C;       /* Primärfarbe (wird von settings.json überschrieben) */
  --pxc-primary-text: #ffffff;   /* Text auf Primärfarbe */
  --pxc-accent: #ef4444;         /* Akzentfarbe (Merkzettel-Herzen) */
  --pxc-bg: #ffffff;             /* Seitenhintergrund */
  --pxc-surface: #f8fafc;        /* Karten/Panel-Hintergrund */
  --pxc-border: #e2e8f0;         /* Rahmenfarbe */
  --pxc-text: #111827;           /* Haupttextfarbe */
  --pxc-text-muted: #6b7280;     /* Sekundärtextfarbe */
  --pxc-radius: 8px;             /* Eckenradius */
  --pxc-font: system-ui, -apple-system, sans-serif;
  --pxc-shadow: 0 1px 3px rgba(0,0,0,0.1);
  --pxc-shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
  --pxc-transition: 200ms ease;
}
```

### Theming

Die `primary_color` aus `settings.json` überschreibt `--pxc-primary` zur Laufzeit.
Eigene CSS-Regeln können alle Custom Properties überschreiben:

```css
:root {
  --pxc-primary: #006633;
  --pxc-radius: 0;
}
```

---

## PDF-Generator — `pdf-generator.js`

Client-seitige PDF-Erzeugung mit jsPDF. Zwei Funktionen:

| Funktion | Beschreibung |
|----------|--------------|
| `generateProductPdf(product, options)` | Einzelprodukt-Datenblatt |
| `generateWishlistPdf(products, options)` | Merkzettel als Tabelle |

**Optionen:**

| Option | Typ | Beschreibung |
|--------|-----|--------------|
| `locale` | `string` | Sprache (`'de'` / `'en'`) |
| `primaryColor` | `string` | Primärfarbe aus Settings |

Hinweis: Excel-Export (`downloadWishlistExcel`) ist im Offline-Modus nicht verfügbar und wirft einen Fehler.

---

## Deeplinks

Die App unterstützt URL-Parameter zur Direktnavigation:

| Parameter | Beschreibung | Beispiel |
|-----------|--------------|---------|
| `?sku=AKB-PRO-18V` | Produkt direkt öffnen (Detail-Modal) | `index.html?sku=AKB-PRO-18V` |
| `?cat=uuid-bohrmaschinen` | Kategorie vorauswählen | `index.html?cat=uuid-bohrmaschinen` |
| `?wishlist=id1,id2,id3` | Merkzettel aus geteiltem Link importieren | `index.html?wishlist=uuid1,uuid2` |

---

## Build-Info

Jeder Offline-Export enthält ein Build-Info-Widget (ℹ-Icon, unten rechts), das folgende Metadaten anzeigt:

```
PublixxCatalog Offline
Build:    2026-03-24 14:30:52
JS:       a1b2c3d4
CSS:      e5f6g7h8
Template: Standardkatalog
Locale:   de
```

Die gleichen Informationen stehen als HTML-Kommentar im Quellcode der `index.html`.
