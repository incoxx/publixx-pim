---
title: Katalog-Embed — Architektur & HowTo
---

# Katalog-Embed — Architektur & HowTo

Diese Seite beschreibt den internen Aufbau des Katalog-Embed-Systems, erklärt die Architekturentscheidungen und zeigt Schritt für Schritt, wie Sie ein eigenes Template erstellen.

## Verzeichnisstruktur

```
catalog-embed/
├── src/
│   ├── index.js              ← Einstiegspunkt, Auto-Discovery, Public API
│   ├── api.js                ← API-Client (fetch + In-Memory-Cache)
│   ├── store.js              ← Singleton Reactive Store (Vue 3)
│   ├── icons.js              ← Inline SVG-Icons (~20 Stück)
│   ├── styles.css            ← Standard-CSS (~930 Zeilen, CSS Custom Properties)
│   └── widgets/
│       ├── SearchWidget.vue
│       ├── CategoriesWidget.vue
│       ├── FacetsWidget.vue
│       ├── ProductGridWidget.vue
│       ├── PaginationWidget.vue
│       ├── ToolbarWidget.vue
│       ├── WishlistWidget.vue
│       ├── WishlistButtonWidget.vue
│       ├── ProductDetailWidget.vue
│       ├── CompareWidget.vue
│       ├── LocaleWidget.vue
│       └── ActiveFiltersWidget.vue
├── examples/
│   ├── minimal.html          ← Minimales Beispiel
│   ├── basic.html            ← Vollständiges Layout
│   ├── custom-design.html    ← Umfangreiches Custom-CSS
│   └── austrian-sunrise.html ← Professionelles 3-Row-Header Template
├── dist/                     ← Build-Ausgabe (wird generiert)
│   ├── catalog-embed.umd.js
│   ├── catalog-embed.es.js
│   └── catalog-embed.css
├── package.json
├── vite.config.js
└── index.html                ← Dev-Server Einstieg
```

## Architekturübersicht

```
┌──────────────────────────────────────────────────┐
│                   HTML-Seite                      │
│                                                   │
│   ┌─────────────┐  ┌──────────┐  ┌───────────┐  │
│   │ data-catalog │  │ data-    │  │ data-     │  │
│   │ ="search"   │  │ catalog= │  │ catalog=  │  │
│   │             │  │ "grid"   │  │ "wishlist"│  │
│   └──────┬──────┘  └────┬─────┘  └─────┬─────┘  │
│          │              │              │          │
└──────────┼──────────────┼──────────────┼──────────┘
           │              │              │
           ▼              ▼              ▼
    ┌─────────────────────────────────────────┐
    │        index.js — Auto-Discovery        │
    │   querySelectorAll('[data-catalog]')    │
    │   → createApp() → mount() per Widget    │
    └──────────────────┬──────────────────────┘
                       │
          ┌────────────┼────────────┐
          ▼            ▼            ▼
    ┌──────────┐ ┌──────────┐ ┌──────────┐
    │ Vue 3    │ │ Vue 3    │ │ Vue 3    │
    │ Mini-App │ │ Mini-App │ │ Mini-App │
    │ (Search) │ │ (Grid)   │ │ (Wish.)  │
    └────┬─────┘ └────┬─────┘ └────┬─────┘
         │            │            │
         ▼            ▼            ▼
    ┌─────────────────────────────────────────┐
    │     store.js — Singleton Reactive Store  │
    │     (Vue 3 reactive() + computed())      │
    │                                          │
    │  state: products, categories, wishlist,  │
    │         filters, locale, pagination...   │
    │  actions: fetchProducts, toggleWishlist,  │
    │           setSearch, setPage, openDetail..│
    └──────────────────┬──────────────────────┘
                       │
                       ▼
    ┌─────────────────────────────────────────┐
    │     api.js — Native fetch() Client       │
    │     + 60s In-Memory-Cache (Map)          │
    │                                          │
    │  GET /catalog/products                   │
    │  GET /catalog/products/:id               │
    │  GET /catalog/categories                 │
    │  GET /catalog/facets                     │
    │  GET /catalog/settings                   │
    │  POST /catalog/products/compare          │
    │  POST /catalog/wishlist/pdf              │
    │  POST /catalog/wishlist/excel            │
    └──────────────────┬──────────────────────┘
                       │
                       ▼
              anyPIM REST API (/api/v1)
```

## Kernkonzepte

### 1. Auto-Discovery via `data-catalog`

Das System sucht beim Initialisieren alle HTML-Elemente mit dem Attribut `data-catalog` und mountet für jedes eine eigenständige Vue 3 Mini-App:

```js
// index.js — vereinfacht
const elements = document.querySelectorAll('[data-catalog]')
elements.forEach(el => {
  const widgetName = el.getAttribute('data-catalog')
  const Component = WIDGET_MAP[widgetName]
  const app = createApp({ render: () => h(Component, props) })
  app.mount(el)
})
```

Jedes Widget wird als unabhängige Vue-App gemountet. Das bedeutet:

- Widgets können beliebig im DOM platziert werden
- Die Reihenfolge ist egal
- Widgets können auch dynamisch nachgeladen werden (`PublixxCatalog.mount()`)

### 2. Singleton Store (Kein Pinia)

Alle Widgets teilen sich einen einzigen reaktiven Store, der mit Vue 3 `reactive()` erstellt wird. Es gibt kein Pinia oder Vuex — der Store ist ein einfaches Modul:

```js
// store.js — vereinfacht
import { reactive, computed, watch } from 'vue'

function createStore() {
  const state = reactive({
    products: [],
    search: '',
    locale: 'de',
    wishlistIds: [],
    // ...
  })

  const actions = {
    async fetchProducts() { /* ... */ },
    toggleWishlist(id) { /* ... */ },
    // ...
  }

  return { state, getters, actions }
}

// Singleton — alle Widgets erhalten dieselbe Instanz
let _store = null
export function useStore() {
  if (!_store) _store = createStore()
  return _store
}
```

**Warum kein Pinia?** Das Embed-Widget soll auf Kunden-Websites laufen, die möglicherweise bereits Vue oder andere Frameworks nutzen. Ein Singleton Store ohne Plugin-System vermeidet Konflikte.

### 3. API-Client mit Caching

Der API-Client nutzt natives `fetch()` ohne externe Abhängigkeiten. Alle GET-Requests werden für 60 Sekunden in einer In-Memory `Map` gecacht:

```js
// api.js — Cache-Logik
const _cache = new Map()
const _cacheTTL = 60000  // 60 Sekunden

function getCached(key) {
  const entry = _cache.get(key)
  if (!entry || Date.now() - entry.ts > _cacheTTL) return null
  return entry.data
}

// Verwendung in API-Methoden
async getProducts(options) {
  const path = `/catalog/products${buildQuery(options)}`
  const cached = getCached(path)
  if (cached) return cached        // ← Cache-Hit: kein Netzwerk-Request
  const resp = await request(path)
  // ... parse + cache ...
}
```

POST-Requests (PDF, Excel, Vergleich) werden **nicht** gecacht.

Der Cache wird automatisch geleert bei:
- Sprachwechsel (`setLocale()`)
- Manuellem Aufruf von `clearCache()`

### 4. CSS-Architektur

Das CSS folgt drei Prinzipien:

**a) CSS Custom Properties für Theming**
```css
:root {
  --pxc-primary: #1B3A5C;
  --pxc-accent: #ef4444;
  --pxc-radius: 8px;
  /* ... 12 Variablen insgesamt */
}
```

**b) BEM-Namenskonvention mit `pxc-`-Prefix**
```css
.pxc-product-card { }             /* Block */
.pxc-product-card__image { }      /* Element */
.pxc-product-card__name { }       /* Element */
.pxc-product-card--featured { }   /* Modifier */
```

**c) Niedrige Spezifizität**

Alle Regeln verwenden bewusst niedrige Spezifizität (einzelne Klasse), damit Kunden sie mit eigenem CSS leicht überschreiben können — ohne `!important`.

### 5. Build-System (Vite)

```js
// vite.config.js
export default defineConfig({
  build: {
    lib: {
      entry: 'src/index.js',
      name: 'PublixxCatalog',
      formats: ['umd', 'es'],      // Zwei Formate
    },
    rollupOptions: { external: [] },  // Alles gebundelt
    cssCodeSplit: false,              // Ein CSS-File
    minify: 'esbuild',               // Schnelles Minifying
  },
})
```

- **UMD-Format** (`catalog-embed.umd.js`): Für `<script>`-Tag Einbindung, exponiert `window.PublixxCatalog`
- **ES-Format** (`catalog-embed.es.js`): Für moderne Build-Systeme mit `import`
- Vue 3 wird **komplett gebundelt** (kein externes Peer-Dependency)
- Ergebnis: ~120 KB JS + ~22 KB CSS (minified)

## HowTo: Eigenes Template erstellen

### Schritt 1 — Template-Datei anlegen

Erstellen Sie eine neue HTML-Datei in `catalog-embed/examples/`:

```bash
cp catalog-embed/examples/basic.html catalog-embed/examples/mein-design.html
```

### Schritt 2 — Grundstruktur verstehen

Jedes Template hat diese Struktur:

```html
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mein Katalog</title>
  <style>
    /* Ihre CSS Custom Properties und Overrides */
    :root {
      --pxc-primary: #ff6600;
      --pxc-accent: #333333;
      --pxc-radius: 4px;
    }
  </style>
</head>
<body>
  <!-- Ihre HTML-Struktur mit Widgets -->
  <div data-catalog="search"></div>
  <div data-catalog="product-grid"></div>

  <!-- Modals (versteckt, Teleport to body) -->
  <div data-catalog="product-detail"></div>

  <!-- Init -->
  <script src="../dist/catalog-embed.umd.js"></script>
  <script>
    PublixxCatalog.init({
      api: 'http://localhost:8000/api/v1',
      locale: 'de',
      perPage: 24,
    })
  </script>
</body>
</html>
```

::: tip Hinweis
Die `<script src>` und die API-URL werden beim Aufruf über `/catalog-embed/mein-design` automatisch vom Server ersetzt. Sie müssen die Pfade nicht manuell anpassen.
:::

### Schritt 3 — Corporate Design anwenden

Überschreiben Sie die CSS Custom Properties:

```css
:root {
  --pxc-primary: #1a7f37;       /* Ihr Grün */
  --pxc-primary-text: #ffffff;
  --pxc-accent: #cf222e;        /* Ihr Rot */
  --pxc-bg: #f6f8fa;
  --pxc-surface: #ffffff;
  --pxc-border: #d0d7de;
  --pxc-text: #1f2328;
  --pxc-text-muted: #636c76;
  --pxc-radius: 6px;
  --pxc-font: 'Inter', sans-serif;
  --pxc-shadow: 0 1px 0 rgba(27,31,36,0.04);
}
```

### Schritt 4 — Widget-Klassen überschreiben

Für tiefergehende Anpassungen überschreiben Sie die `pxc-`-Klassen:

```css
/* Produktkarten komplett anpassen */
.pxc-product-card {
  border: 2px solid var(--pxc-border);
  border-radius: 0;
  box-shadow: none;
}

.pxc-product-card__image {
  aspect-ratio: 4/3;        /* Breiteres Bildformat */
}

.pxc-product-card__name {
  font-size: 1.1rem;
  font-weight: 700;
  text-transform: uppercase;
}

/* Buttons anpassen */
.pxc-btn {
  border-radius: 999px;     /* Pill-Buttons */
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
```

### Schritt 5 — Layout mit Wrapper-Klassen

Verwenden Sie eigene Wrapper-Klassen (wie im "Austrian Sunrise" Template), um Widgets in Ihr Layout einzubetten:

```html
<div class="mein-layout">
  <aside class="mein-sidebar">
    <div data-catalog="categories"></div>
    <div data-catalog="facets"></div>
  </aside>
  <main class="mein-main">
    <div data-catalog="toolbar"></div>
    <div data-catalog="product-grid"></div>
    <div data-catalog="pagination"></div>
  </main>
</div>

<style>
  .mein-layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 24px;
    max-width: 1200px;
    margin: 0 auto;
  }
  @media (max-width: 768px) {
    .mein-layout { grid-template-columns: 1fr; }
    .mein-sidebar { display: none; }
  }
</style>
```

### Schritt 6 — Testen

Während der Entwicklung können Sie das Template direkt im Browser öffnen:

```bash
# Im catalog-embed-Verzeichnis:
npm run dev
# → öffnet http://localhost:5173

# Oder über anyPIM (falls deployed):
# https://ihre-domain.de/catalog-embed/mein-design
```

### Schritt 7 — Fertig

Nach dem nächsten Deployment ist das Template automatisch unter `/catalog-embed/mein-design` erreichbar.

## Widget-Übersicht im Detail

### Produktraster (`product-grid`)

Zeigt Produkte als Karten (Grid) oder als Liste. Reagiert auf:
- Suchbegriff → filtert serverseitig
- Kategorie → filtert nach Hierarchie
- Facetten → filtert nach Attributen
- Pagination → lädt nächste Seite
- Sortierung → re-sortiert serverseitig

### Merkliste (`wishlist`)

Drawer-Komponente mit:
- Merkliste anzeigen (nur aktuell geladene Produkte + Zähler für nicht sichtbare)
- PDF-Export der Merkliste
- Excel-Export der Merkliste
- Produktvergleich starten
- Merkliste per Link teilen (URL mit `?wishlist=id1,id2,id3`)
- Merkliste leeren

Die Merklisten-IDs werden in `localStorage` unter dem Key `pxc_wishlist` persistiert.

### Produktdetail (`product-detail`)

Modal mit:
- Bildergalerie (Thumbnails + Navigation)
- Produktattribute in Sektionen
- Preise (mehrere Preistypen)
- PDF-Download des Produkts
- Merkliste-Button
- Verwandte Produkte (Relations)

### Vergleich (`compare`)

Modal-Tabelle mit:
- Alle Attribute nebeneinander
- "Nur Unterschiede"-Filter
- Farbliche Hervorhebung von Abweichungen

## Datenfluss

```
Benutzer klickt "Seite 2"
        │
        ▼
PaginationWidget → actions.setPage(2)
        │
        ▼
store.state.meta.current_page = 2
        │
        ▼ (watch in ProductGridWidget)
actions.fetchProducts()
        │
        ▼
api.getProducts({ page: 2, ... })
        │
        ▼ (Cache-Miss → fetch)
GET /api/v1/catalog/products?page=2&per_page=24
        │
        ▼
store.state.products = [...neue Produkte]
store.state.meta = { current_page: 2, ... }
        │
        ▼ (Vue Reactivity)
ProductGridWidget rendert neue Karten
PaginationWidget aktualisiert aktive Seite
ToolbarWidget aktualisiert Produktanzahl
```

## Sicherheit

| Aspekt | Umsetzung |
|---|---|
| **Template-Namen** | Server-seitig sanitisiert: nur `[a-zA-Z0-9_-]` erlaubt |
| **API-Zugang** | Über `catalog.access` Middleware geschützt |
| **Token** | Wird nur per `init({ token })` gesetzt, nie implizit aus localStorage gelesen |
| **XSS** | Kein `v-html` mit Benutzereingaben; nur vordefinierte SVG-Icons |
| **CORS** | API und Widget laufen auf derselben Domain → kein CORS nötig |

## Performance-Tipps

1. **Caching nutzen**: Der eingebaute 60s-Cache verhindert redundante API-Calls bei Navigation
2. **`perPage` sinnvoll wählen**: 12–24 Produkte pro Seite ist optimal
3. **Bilder lazy-loaden**: Das Produktraster setzt automatisch `loading="lazy"` auf `<img>`-Tags
4. **CSS minimieren**: Eigene Styles schlank halten, da das Framework-CSS bereits ~22 KB mitbringt
5. **Bundle über CDN/Apache cachen**: Die statischen Assets in `/catalog-embed-assets/` sollten mit langen Cache-Headern ausgeliefert werden

```apache
# Apache .htaccess für catalog-embed-assets
<IfModule mod_expires.c>
  <LocationMatch "/catalog-embed-assets/">
    ExpiresActive On
    ExpiresDefault "access plus 1 year"
    Header set Cache-Control "public, max-age=31536000, immutable"
  </LocationMatch>
</IfModule>
```

## Nächste Schritte

- [Katalog-Embed Übersicht](/de/erweitert/catalog-embed) — Schnelleinstieg und Widget-Referenz
- [Katalog-API Endpunkte](/de/api/produkte) — API-Referenz für die Katalog-Endpunkte
- [Zugangslinks](/de/administration/zugangslinks) — Katalog-Zugang für externe Nutzer einrichten
