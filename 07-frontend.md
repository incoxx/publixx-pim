# anyPIM — Frontend (Vue.js)

> **Purpose:** Vue.js 3 SPA architecture and UI. Use this skill when building components, views, stores, composables, and when styling.

---

## Stack

| Package | Version | Purpose |
|---------|---------|---------|
| Vue.js | 3.5+ | Composition API |
| Vite | 6+ | Build, HMR |
| Pinia | 2+ | State Management |
| Vue Router | 4+ | Routing |
| TailwindCSS | 4+ | Utility CSS |
| Headless UI | 1+ | Accessible Dropdowns, Modals, Tabs |
| @vueuse/core | 11+ | Composables |
| axios | 1+ | HTTP Client |
| vue-draggable-plus | 0.5+ | Drag & Drop |
| vue-i18n | 9+ | i18n |
| Monaco Editor | 0.50+ | JSON/Code Editor |

---

## Design System

### Design Language

Industrial minimalism. Clean, bright, typographically clear. Inspired by Linear.app, Notion, Figma. **No colorful orbs, no illustrations, no unnecessary animations.**

### Colors

```css
--color-primary: #1B3A5C;         /* Deep blue — primary color */
--color-accent: #2E75B6;          /* Medium blue — active, links, focus */
--color-bg: #FAFBFC;              /* Near-white background */
--color-surface: #FFFFFF;         /* Cards, panels */
--color-border: #E5E7EB;         /* Subtle borders */
--color-text-primary: #111827;    /* Near-black */
--color-text-secondary: #6B7280;  /* Gray — labels, hints */
--color-success: #059669;         /* Green */
--color-warning: #D97706;         /* Amber */
--color-error: #DC2626;           /* Red */
```

### Typography

```css
--font-ui: 'Inter Variable', sans-serif;        /* 400, 500, 600 */
--font-mono: 'JetBrains Mono', monospace;       /* Technical names, JSON */
```

### Spacing

4px grid: 4, 8, 12, 16, 24, 32, 48, 64

### Border Radius

6px (buttons), 8px (cards), 12px (modals)

---

## Layout: Three Columns

```
┌─────────┬───────────────────────────┬──────────┐
│ Sidebar  │       Main area           │  Panel   │
│ 240px    │       (flexible)          │  360px   │
│ collaps. │                           │ on-demand│
└─────────┴───────────────────────────┴──────────┘
```

---

## Navigation (Sidebar)

| Icon (Lucide) | Label | Route |
|---------------|-------|-------|
| Search | Search | /search |
| Package | Products | /products |
| GitBranch | Hierarchies | /hierarchies |
| Sliders | Attributes | /attributes |
| Database | Value Lists | /value-lists |
| Upload | Import | /imports |
| Download | Export | /exports |
| Image | Media | /media |
| DollarSign | Prices | /prices |
| Users | Users | /users |
| Settings | Settings | /settings |

---

## Core Components

| Component | Description |
|-----------|-------------|
| PimTable | Generic data table: sorting, filters, column config, inline edit, keyboard nav, virtual scrolling |
| PimTree | Recursive tree: lazy loading, drag & drop, context menu, search |
| PimForm | Dynamic form: generated from attribute schema, validation |
| PimAttributeInput | Dynamic input field by data type: text, number, select, date, toggle, rich text |
| PimCollectionGroup | Attribute group as accordion: drag & drop sorting, progress indicator |
| PimBreadcrumb | Clickable path with truncation |
| PimStatusBadge | Colored dot + label (green=active, gray=draft, red=inactive) |
| PimInheritanceBadge | "Inherited from: X" with tooltip and link |
| PimDropZone | File upload: drag & drop, progress, multi-upload |
| PimJsonPreview | JSON with syntax highlighting, copy, collapse |
| PimFilterBar | Chips, quick search, preset filters, clear all |
| PimCommandPalette | Cmd+K: global search, actions, navigation |
| PxfRenderer | PXF layout preview (renders all 15 element types) |

---

## Keyboard Shortcuts

| Shortcut | Action | Context |
|----------|--------|---------|
| Cmd+K | Command Palette | Global |
| Cmd+S | Save | Forms |
| Cmd+N | New element | Lists |
| / | Focus search | Lists, tree |
| Escape | Close | Modals, panels |
| Enter | Open / confirm | Lists |
| Space | Select (toggle) | Lists |
| ↑ / ↓ | Navigation | Lists, tree |
| → / ← | Expand / collapse | Tree |
| Tab | Next field | Forms |
| Cmd+Z | Undo | Forms |

---

## Project Structure

```
pim-frontend/
├── src/
│   ├── api/                  # Axios wrapper per entity
│   │   ├── attributes.js
│   │   ├── products.js
│   │   ├── hierarchies.js
│   │   ├── pql.js
│   │   └── ...
│   ├── stores/               # Pinia Stores
│   │   ├── useAuthStore.js
│   │   ├── useProductStore.js
│   │   ├── useHierarchyStore.js
│   │   ├── useAttributeStore.js
│   │   └── useLocaleStore.js
│   ├── views/
│   │   ├── ProductListView.vue
│   │   ├── ProductDetailView.vue
│   │   ├── HierarchyView.vue
│   │   ├── AttributeAdminView.vue
│   │   ├── ImportView.vue
│   │   ├── ExportView.vue
│   │   └── ...
│   ├── components/
│   │   ├── hierarchy/
│   │   │   ├── TreeNode.vue
│   │   │   └── HierarchyTree.vue
│   │   ├── product/
│   │   │   ├── AttributeForm.vue
│   │   │   ├── CollectionGroup.vue
│   │   │   └── VariantManager.vue
│   │   ├── export/
│   │   │   ├── MappingEditor.vue
│   │   │   └── JsonPreview.vue
│   │   ├── pxf/
│   │   │   ├── PxfRenderer.vue
│   │   │   ├── PxfPage.vue
│   │   │   └── elements/
│   │   └── shared/
│   │       ├── PimTable.vue
│   │       ├── PimTree.vue
│   │       ├── PimCommandPalette.vue
│   │       └── ...
│   ├── composables/
│   │   ├── useInheritance.js
│   │   ├── useDragDrop.js
│   │   ├── useFilters.js
│   │   ├── usePql.js
│   │   └── useLocale.js
│   ├── router/
│   └── App.vue
├── tailwind.config.js
└── vite.config.js
```

---

## Performance Patterns

| Pattern | Description |
|---------|-------------|
| Virtual Scrolling | vue-virtual-scroller for lists > 100 rows |
| Lazy Loading | Load hierarchy children on expand |
| Debounce | 250ms on search/filter |
| Optimistic Updates | UI shows change immediately, API in background |
| Skeleton Loading | Instead of spinner |
| SWR | Show cached data, refresh in background |
| Code Splitting | Each route = separate chunk (< 200KB gzip initial) |
| Web Worker | Client-side PQL filtering |
