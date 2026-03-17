---
title: Catalog Embed — Architecture & HowTo
---

# Catalog Embed — Architecture & HowTo

This page describes the internal architecture of the Catalog Embed system, explains architectural decisions, and provides a step-by-step guide for creating your own template.

## Directory Structure

```
catalog-embed/
├── src/
│   ├── index.js              ← Entry point, auto-discovery, public API
│   ├── api.js                ← API client (fetch + in-memory cache)
│   ├── store.js              ← Singleton reactive store (Vue 3)
│   ├── icons.js              ← Inline SVG icons (~20 icons)
│   ├── styles.css            ← Default CSS (~930 lines, CSS Custom Properties)
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
│   ├── minimal.html          ← Minimal example
│   ├── basic.html            ← Complete layout
│   ├── custom-design.html    ← Extensive custom CSS
│   └── austrian-sunrise.html ← Professional 3-row header template
├── dist/                     ← Build output (generated)
│   ├── catalog-embed.umd.js
│   ├── catalog-embed.es.js
│   └── catalog-embed.css
├── package.json
├── vite.config.js
└── index.html                ← Dev server entry
```

## Architecture Overview

```
┌──────────────────────────────────────────────────┐
│                    HTML Page                      │
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
    │   → createApp() → mount() per widget    │
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
    │     + 60s In-Memory Cache (Map)          │
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

## Core Concepts

### 1. Auto-Discovery via `data-catalog`

On initialization, the system finds all HTML elements with the `data-catalog` attribute and mounts an independent Vue 3 mini-app for each:

```js
// index.js — simplified
const elements = document.querySelectorAll('[data-catalog]')
elements.forEach(el => {
  const widgetName = el.getAttribute('data-catalog')
  const Component = WIDGET_MAP[widgetName]
  const app = createApp({ render: () => h(Component, props) })
  app.mount(el)
})
```

Each widget is mounted as an independent Vue app:

- Widgets can be placed anywhere in the DOM
- Order doesn't matter
- Widgets can be mounted dynamically later (`PublixxCatalog.mount()`)

### 2. Singleton Store (No Pinia)

All widgets share a single reactive store created with Vue 3 `reactive()`. There is no Pinia or Vuex — the store is a plain module:

```js
// store.js — simplified
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

// Singleton — all widgets get the same instance
let _store = null
export function useStore() {
  if (!_store) _store = createStore()
  return _store
}
```

**Why no Pinia?** The embed widget runs on customer websites that may already use Vue or other frameworks. A singleton store without a plugin system avoids conflicts.

### 3. API Client with Caching

The API client uses native `fetch()` with no external dependencies. All GET requests are cached for 60 seconds in an in-memory `Map`:

```js
// api.js — cache logic
const _cache = new Map()
const _cacheTTL = 60000  // 60 seconds

function getCached(key) {
  const entry = _cache.get(key)
  if (!entry || Date.now() - entry.ts > _cacheTTL) return null
  return entry.data
}
```

POST requests (PDF, Excel, compare) are **not** cached.

The cache is automatically cleared on:
- Locale change (`setLocale()`)
- Manual call to `clearCache()`

### 4. CSS Architecture

The CSS follows three principles:

**a) CSS Custom Properties for theming**
```css
:root {
  --pxc-primary: #1B3A5C;
  --pxc-accent: #ef4444;
  --pxc-radius: 8px;
  /* ... 12 variables total */
}
```

**b) BEM naming convention with `pxc-` prefix**
```css
.pxc-product-card { }             /* Block */
.pxc-product-card__image { }      /* Element */
.pxc-product-card--featured { }   /* Modifier */
```

**c) Low specificity**

All rules use intentionally low specificity (single class) so customers can easily override them with their own CSS — no `!important` needed.

### 5. Build System (Vite)

```js
// vite.config.js
export default defineConfig({
  build: {
    lib: {
      entry: 'src/index.js',
      name: 'PublixxCatalog',
      formats: ['umd', 'es'],      // Two formats
    },
    rollupOptions: { external: [] },  // Everything bundled
    cssCodeSplit: false,              // Single CSS file
    minify: 'esbuild',               // Fast minification
  },
})
```

- **UMD format** (`catalog-embed.umd.js`): For `<script>` tag usage, exposes `window.PublixxCatalog`
- **ES format** (`catalog-embed.es.js`): For modern build systems with `import`
- Vue 3 is **fully bundled** (no external peer dependency)
- Result: ~120 KB JS + ~22 KB CSS (minified)

## HowTo: Create Your Own Template

### Step 1 — Create Template File

Create a new HTML file in `catalog-embed/examples/`:

```bash
cp catalog-embed/examples/basic.html catalog-embed/examples/my-design.html
```

### Step 2 — Understand the Structure

Every template follows this structure:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Catalog</title>
  <style>
    /* Your CSS Custom Properties and overrides */
    :root {
      --pxc-primary: #ff6600;
      --pxc-accent: #333333;
      --pxc-radius: 4px;
    }
  </style>
</head>
<body>
  <!-- Your HTML structure with widgets -->
  <div data-catalog="search"></div>
  <div data-catalog="product-grid"></div>

  <!-- Modals (hidden, teleport to body) -->
  <div data-catalog="product-detail"></div>

  <!-- Init -->
  <script src="../dist/catalog-embed.umd.js"></script>
  <script>
    PublixxCatalog.init({
      api: 'http://localhost:8000/api/v1',
      locale: 'en',
      perPage: 24,
    })
  </script>
</body>
</html>
```

::: tip Note
The `<script src>` and API URL are automatically replaced by the server when accessed via `/catalog-embed/my-design`. You don't need to adjust paths manually.
:::

### Step 3 — Apply Corporate Design

Override the CSS Custom Properties:

```css
:root {
  --pxc-primary: #1a7f37;       /* Your green */
  --pxc-primary-text: #ffffff;
  --pxc-accent: #cf222e;        /* Your red */
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

### Step 4 — Override Widget Classes

For deeper customization, override the `pxc-` classes:

```css
/* Fully customize product cards */
.pxc-product-card {
  border: 2px solid var(--pxc-border);
  border-radius: 0;
  box-shadow: none;
}

.pxc-product-card__image {
  aspect-ratio: 4/3;        /* Wider image format */
}

/* Customize buttons */
.pxc-btn {
  border-radius: 999px;     /* Pill buttons */
  text-transform: uppercase;
}
```

### Step 5 — Layout with Wrapper Classes

Use your own wrapper classes to embed widgets in your layout:

```html
<div class="my-layout">
  <aside class="my-sidebar">
    <div data-catalog="categories"></div>
    <div data-catalog="facets"></div>
  </aside>
  <main class="my-main">
    <div data-catalog="toolbar"></div>
    <div data-catalog="product-grid"></div>
    <div data-catalog="pagination"></div>
  </main>
</div>

<style>
  .my-layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 24px;
    max-width: 1200px;
    margin: 0 auto;
  }
  @media (max-width: 768px) {
    .my-layout { grid-template-columns: 1fr; }
    .my-sidebar { display: none; }
  }
</style>
```

### Step 6 — Test

During development you can open the template directly in the browser:

```bash
# In the catalog-embed directory:
npm run dev
# → opens http://localhost:5173

# Or via anyPIM (if deployed):
# https://your-domain.com/catalog-embed/my-design
```

### Step 7 — Done

After the next deployment, the template is automatically available at `/catalog-embed/my-design`.

## Data Flow

```
User clicks "Page 2"
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
        ▼ (cache miss → fetch)
GET /api/v1/catalog/products?page=2&per_page=24
        │
        ▼
store.state.products = [...new products]
store.state.meta = { current_page: 2, ... }
        │
        ▼ (Vue reactivity)
ProductGridWidget renders new cards
PaginationWidget updates active page
ToolbarWidget updates product count
```

## Security

| Aspect | Implementation |
|---|---|
| **Template names** | Server-side sanitized: only `[a-zA-Z0-9_-]` allowed |
| **API access** | Protected via `catalog.access` middleware |
| **Token** | Only set via `init({ token })`, never implicitly read from localStorage |
| **XSS** | No `v-html` with user input; only predefined SVG icons |
| **CORS** | API and widget run on same domain → no CORS needed |

## Performance Tips

1. **Use caching**: The built-in 60s cache prevents redundant API calls during navigation
2. **Choose `perPage` wisely**: 12–24 products per page is optimal
3. **Lazy-load images**: The product grid automatically sets `loading="lazy"` on `<img>` tags
4. **Minimize custom CSS**: Keep your styles lean since the framework CSS is already ~22 KB
5. **Cache bundle via CDN/Apache**: Static assets in `/catalog-embed-assets/` should be served with long cache headers

```apache
# Apache .htaccess for catalog-embed-assets
<IfModule mod_expires.c>
  <LocationMatch "/catalog-embed-assets/">
    ExpiresActive On
    ExpiresDefault "access plus 1 year"
    Header set Cache-Control "public, max-age=31536000, immutable"
  </LocationMatch>
</IfModule>
```

## Next Steps

- [Catalog Embed Overview](/en/advanced/catalog-embed) — Quick start and widget reference
- [Catalog API Endpoints](/en/api/products) — API reference for catalog endpoints
- [Access Links](/en/administration/access-links) — Set up catalog access for external users
