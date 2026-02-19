# Publixx PIM — Frontend (Vue.js)

> **Zweck:** Vue.js 3 SPA-Architektur und UI. Verwende diesen Skill beim Bauen von Komponenten, Views, Stores, Composables und beim Styling.

---

## Stack

| Paket | Version | Zweck |
|-------|---------|-------|
| Vue.js | 3.5+ | Composition API |
| Vite | 6+ | Build, HMR |
| Pinia | 2+ | State Management |
| Vue Router | 4+ | Routing |
| TailwindCSS | 4+ | Utility CSS |
| Headless UI | 1+ | Accessible Dropdowns, Modals, Tabs |
| @vueuse/core | 11+ | Composables |
| axios | 1+ | HTTP-Client |
| vue-draggable-plus | 0.5+ | Drag & Drop |
| vue-i18n | 9+ | i18n |
| Monaco Editor | 0.50+ | JSON/Code-Editor |

---

## Design-System

### Designsprache

Industrieller Minimalismus. Clean, hell, typographisch klar. Inspiriert von Linear.app, Notion, Figma. **Keine bunten Kugeln, keine Illustrationen, keine unnötigen Animationen.**

### Farben

```css
--color-primary: #1B3A5C;         /* Tiefblau — Hauptfarbe */
--color-accent: #2E75B6;          /* Mittelblau — Aktiv, Links, Fokus */
--color-bg: #FAFBFC;              /* Fast-Weiß Hintergrund */
--color-surface: #FFFFFF;         /* Karten, Panels */
--color-border: #E5E7EB;         /* Subtle Borders */
--color-text-primary: #111827;    /* Fast-Schwarz */
--color-text-secondary: #6B7280;  /* Grau — Labels, Hints */
--color-success: #059669;         /* Grün */
--color-warning: #D97706;         /* Amber */
--color-error: #DC2626;           /* Rot */
```

### Typografie

```css
--font-ui: 'Inter Variable', sans-serif;        /* 400, 500, 600 */
--font-mono: 'JetBrains Mono', monospace;       /* Technische Namen, JSON */
```

### Spacing

4px-Raster: 4, 8, 12, 16, 24, 32, 48, 64

### Border Radius

6px (Buttons), 8px (Karten), 12px (Modals)

---

## Layout: Drei-Spalten

```
┌─────────┬───────────────────────────┬──────────┐
│ Sidebar  │       Hauptbereich        │  Panel   │
│ 240px    │       (flexibel)          │  360px   │
│ collaps. │                           │ on-demand│
└─────────┴───────────────────────────┴──────────┘
```

---

## Navigation (Sidebar)

| Icon (Lucide) | Label | Route |
|---------------|-------|-------|
| Search | Suche | /search |
| Package | Produkte | /products |
| GitBranch | Hierarchien | /hierarchies |
| Sliders | Attribute | /attributes |
| Database | Wertelisten | /value-lists |
| Upload | Import | /imports |
| Download | Export | /exports |
| Image | Medien | /media |
| DollarSign | Preise | /prices |
| Users | Benutzer | /users |
| Settings | Einstellungen | /settings |

---

## Kernkomponenten

| Komponente | Beschreibung |
|-----------|--------------|
| PimTable | Generische Datentabelle: Sortierung, Filter, Spalten-Konfig, Inline-Edit, Keyboard-Nav, Virtuelles Scrolling |
| PimTree | Rekursiver Baum: Lazy-Loading, Drag & Drop, Kontextmenü, Suche |
| PimForm | Dynamisches Formular: Generiert aus Attribut-Schema, Validierung |
| PimAttributeInput | Dynamisches Eingabefeld nach Datentyp: Text, Number, Select, Date, Toggle, Rich-Text |
| PimCollectionGroup | Attributgruppe als Accordion: Drag & Drop Sortierung, Fortschrittsanzeige |
| PimBreadcrumb | Klickbarer Pfad mit Trunkierung |
| PimStatusBadge | Farbiger Punkt + Label (grün=aktiv, grau=draft, rot=inaktiv) |
| PimInheritanceBadge | "Vererbt von: X" mit Tooltip und Link |
| PimDropZone | Datei-Upload: Drag & Drop, Progress, Multi-Upload |
| PimJsonPreview | JSON mit Syntax-Highlighting, Copy, Collapse |
| PimFilterBar | Chips, Schnellsuche, Preset-Filter, Clear-All |
| PimCommandPalette | Cmd+K: Globale Suche, Aktionen, Navigation |
| PxfRenderer | PXF-Layout-Preview (rendert alle 15 Elementtypen) |

---

## Keyboard-Shortcuts

| Shortcut | Aktion | Kontext |
|----------|--------|---------|
| Cmd+K | Command Palette | Global |
| Cmd+S | Speichern | Formulare |
| Cmd+N | Neues Element | Listen |
| / | Suche fokussieren | Listen, Baum |
| Escape | Schließen | Modals, Panels |
| Enter | Öffnen / Bestätigen | Listen |
| Space | Auswählen (Toggle) | Listen |
| ↑ / ↓ | Navigation | Listen, Baum |
| → / ← | Expand / Collapse | Baum |
| Tab | Nächstes Feld | Formulare |
| Cmd+Z | Undo | Formulare |

---

## Projektstruktur

```
pim-frontend/
├── src/
│   ├── api/                  # Axios-Wrapper pro Entität
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

## Performance-Patterns

| Pattern | Beschreibung |
|---------|--------------|
| Virtuelles Scrolling | vue-virtual-scroller für Listen > 100 Zeilen |
| Lazy Loading | Hierarchie-Kinder laden bei Expand |
| Debounce | 250ms auf Suche/Filter |
| Optimistic Updates | UI zeigt Änderung sofort, API im Hintergrund |
| Skeleton Loading | Statt Spinner |
| SWR | Cached Daten zeigen, im Hintergrund aktualisieren |
| Code-Splitting | Jede Route = eigener Chunk (< 200KB gzip initial) |
| Web Worker | Client-seitige PQL-Filterung |
