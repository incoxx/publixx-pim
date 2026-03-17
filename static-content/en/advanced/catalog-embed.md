---
title: Catalog Embed (Template System)
---

# Catalog Embed (Template System)

The Catalog Embed is a drop-in widget system that lets you embed a full product catalog into any website. Customers can customize the design to match their corporate identity without any programming skills.

## How It Works

The system consists of a single JavaScript file (`catalog-embed.umd.js`) and a CSS file (`catalog-embed.css`) that are loaded into an HTML page. Widgets are placed anywhere in the HTML using the `data-catalog="..."` attribute.

```html
<!-- Place widgets -->
<div data-catalog="product-grid"></div>
<div data-catalog="search"></div>
<div data-catalog="pagination"></div>

<!-- Load bundle and initialize -->
<script src="/catalog-embed-assets/catalog-embed.umd.js"></script>
<script>
  PublixxCatalog.init({
    api: 'https://your-domain.com/api/v1',
    locale: 'en',
    perPage: 24,
  })
</script>
```

## Available Widgets

Each widget is activated via the `data-catalog` attribute:

| Widget | Attribute | Description |
|---|---|---|
| **Product Grid** | `product-grid` | Displays products as a card grid or list |
| **Search** | `search` | Search field with real-time search |
| **Pagination** | `pagination` | Page navigation for the product list |
| **Categories** | `categories` | Category navigation as tree structure |
| **Facets** | `facets` | Filter panel with attribute facets |
| **Toolbar** | `toolbar` | Sorting, view mode, and product count |
| **Active Filters** | `active-filters` | Shows active filter badges with remove function |
| **Wishlist** | `wishlist` | Wishlist drawer with export functions |
| **Wishlist Button** | `wishlist-button` | Standalone button to open the wishlist |
| **Product Detail** | `product-detail` | Product detail view as modal |
| **Compare** | `compare` | Product comparison as table |
| **Locale** | `locale` | Language switcher (DE/EN) |

::: tip
The `product-detail` and `compare` widgets are displayed as modals and can be placed anywhere in the HTML (e.g., at the end of `<body>`).
:::

## Templates

Templates are ready-made HTML files that serve as starting points for custom designs. They are stored in the `catalog-embed/examples/` directory.

### Included Templates

| Template | Description |
|---|---|
| **minimal** | Minimal example with only a few widgets |
| **basic** | Complete layout with all widgets |
| **custom-design** | Example with extensive custom CSS |
| **austrian-sunrise** | Professional template with 3-row header, sidebar, and blue/yellow color scheme |

### Accessing Templates

Templates can be accessed directly via URL:

```
https://your-domain.com/catalog-embed/              → Template overview
https://your-domain.com/catalog-embed/basic          → Basic template
https://your-domain.com/catalog-embed/austrian-sunrise → Austrian Sunrise
```

This also works for subdirectory installations:

```
https://your-domain.com/pim/catalog-embed/basic
```

## Customizing the Design (CSS Custom Properties)

The entire appearance is controlled via CSS Custom Properties. Override them in a `<style>` block:

```css
:root {
  /* Colors */
  --pxc-primary: #004588;        /* Primary color */
  --pxc-primary-text: #ffffff;   /* Text on primary color */
  --pxc-accent: #fd0;            /* Accent color */
  --pxc-bg: #ffffff;             /* Background */
  --pxc-surface: #f7f7f7;        /* Surface/cards */
  --pxc-border: #e5e7eb;         /* Border color */
  --pxc-text: #111827;           /* Text color */
  --pxc-text-muted: #6b7280;     /* Muted text */

  /* Typography & Shape */
  --pxc-font: 'Open Sans', sans-serif;
  --pxc-radius: 8px;             /* Border radius */
  --pxc-shadow: 0 1px 3px rgba(0,0,0,0.1);
  --pxc-shadow-lg: 0 4px 12px rgba(0,0,0,0.15);
}
```

All widget classes use the `pxc-` prefix and follow BEM naming convention:

```css
.pxc-product-card { }           /* Block */
.pxc-product-card__image { }    /* Element */
.pxc-product-card--featured { } /* Modifier */
```

## Configuration (`PublixxCatalog.init`)

| Option | Type | Default | Description |
|---|---|---|---|
| `api` | `string` | `/api/v1` | Base URL of the catalog API |
| `locale` | `string` | `'de'` | Language (`de` or `en`) |
| `perPage` | `number` | `24` | Products per page |
| `token` | `string` | — | Optional API token for protected catalogs |
| `cache` | `boolean` | `true` | Enables in-memory caching (60s TTL) |

## Creating Your Own Template

1. Copy an existing template (e.g., `basic.html`) as a starting point
2. Customize the CSS Custom Properties in `:root` to match your corporate design
3. Arrange widget containers (`data-catalog="..."`) according to your layout
4. Add your own HTML elements (header, footer, banners)
5. Save the file as `catalog-embed/examples/your-name.html`
6. Access the template at `/catalog-embed/your-name`

::: warning Note
Template filenames may only contain letters, numbers, hyphens, and underscores. No spaces or special characters.
:::

## Deployment

The Catalog Embed is automatically built during deployment. The deploy process:

1. Runs `npm ci` in the `catalog-embed/` directory
2. Builds the bundle with `npm run build`
3. Copies the files to `public/catalog-embed-assets/`

Generated files:
- `catalog-embed.umd.js` — JavaScript bundle (~120 KB)
- `catalog-embed.css` — Stylesheet (~22 KB)

## Technical Details

| Aspect | Detail |
|---|---|
| **Framework** | Vue 3 (Composition API) |
| **Bundler** | Vite (UMD format) |
| **Dependencies** | No external runtime dependencies |
| **API Client** | Native `fetch()` with 60s in-memory cache |
| **State Management** | Vue 3 Reactivity (Singleton Store) |
| **CSS** | Custom Properties + BEM with `pxc-` prefix |
| **Bundle Size** | ~120 KB JS + ~22 KB CSS (minified) |

## Next Steps

- [Catalog API Endpoints](/en/api/products) — API reference for catalog endpoints
- [Access Links](/en/administration/access-links) — Set up catalog access for external users
