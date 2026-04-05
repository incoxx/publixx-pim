# data-testid Selektoren – anyPIM Video Engine

Alle `data-testid`-Attribute die von Video-Stories verwendet werden.

## Login

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `login-form` | Login-Formular | `pim-frontend/src/views/LoginView.vue` |
| `input-email` | E-Mail Eingabefeld | `pim-frontend/src/views/LoginView.vue` |
| `input-password` | Passwort Eingabefeld | `pim-frontend/src/views/LoginView.vue` |
| `btn-login` | Login-Button | `pim-frontend/src/views/LoginView.vue` |

## Navigation (Sidebar)

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `nav-dashboard` | Dashboard-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-quick-search` | Schnellsuche-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-search` | Suche-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-products` | Produkte-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-hierarchies` | Hierarchien-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-watchlist` | Merkliste-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-workflow` | Workflow-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-calendar` | Kalender-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |
| `nav-media` | Medien-Link | `pim-frontend/src/components/layout/AppSidebar.vue` |

## Views (Seitencontainer)

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `dashboard` | Dashboard | `pim-frontend/src/views/dashboard/DashboardView.vue` |
| `quick-search-view` | Schnellsuche | `pim-frontend/src/views/search/QuickSearchView.vue` |
| `search-view` | Erweiterte Suche | `pim-frontend/src/views/search/SearchWizardView.vue` |
| `product-list` | Produktliste | `pim-frontend/src/views/products/ProductListView.vue` |
| `product-detail` | Produktdetail | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `hierarchy-view` | Hierarchie-Ansicht | `pim-frontend/src/views/hierarchies/HierarchyView.vue` |
| `watchlist-view` | Merkliste | `pim-frontend/src/views/watchlist/WatchlistView.vue` |
| `calendar-view` | Planungskalender | `pim-frontend/src/views/calendar/CalendarView.vue` |
| `media-view` | Medienverwaltung | `pim-frontend/src/views/media/MediaView.vue` |
| `workflow-view` | Workflow-Aufgaben | `pim-frontend/src/views/workflow/WorkflowTaskView.vue` |

## Produkte – Buttons & Panels

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `btn-new-product` | "Neues Produkt" Button | `pim-frontend/src/views/products/ProductListView.vue` |
| `product-create-panel` | Produkt-Erstellungs-Panel | `pim-frontend/src/components/panels/ProductCreatePanel.vue` |

## Produkte – Detail-Tabs

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `tab-base-data` | Grunddaten | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-attributes` | Attribute | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-variant-attributes` | Varianten-Attribute | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-variants` | Varianten | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-media` | Medien | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-prices` | Preise | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-relations` | Relationen | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-notes` | Notizen | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-output-hierarchies` | Ausgabehierarchien | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-preview` | Vorschau | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-versions` | Versionen | `pim-frontend/src/views/products/ProductDetailView.vue` |
| `tab-scheduled-actions` | Planung | `pim-frontend/src/views/products/ProductDetailView.vue` |

## Formulare

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `field-{key}` | Feld-Wrapper (dynamisch) | `pim-frontend/src/components/shared/PimForm.vue` |
| `btn-save` | Speichern-Button | `pim-frontend/src/components/shared/PimForm.vue` |

Häufige `field-{key}` Werte:
- `field-sku` – SKU/Artikelnummer
- `field-name` – Produktname
- `field-product_type_id` – Produkttyp-Auswahl
- `field-ean` – EAN
- `field-status` – Status
- `field-master_hierarchy_node_id` – Hierarchie-Knoten
- `field-manufacturer_id` – Hersteller
- `field-name_de` – Name (Deutsch)
- `field-name_en` – Name (Englisch)

## Hierarchien

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `btn-new-node` | "Knoten erstellen" Button | `pim-frontend/src/views/hierarchies/HierarchyView.vue` |
| `node-form-panel` | Knoten-Formular-Panel | `pim-frontend/src/components/panels/HierarchyNodeFormPanel.vue` |

## Suche

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `search-input` | Sucheingabefeld | `pim-frontend/src/views/search/SearchWizardView.vue` |
| `btn-search` | Suchen-Button | `pim-frontend/src/views/search/SearchWizardView.vue` |
| `search-results` | Suchergebnis-Anzeige | `pim-frontend/src/views/search/SearchWizardView.vue` |

## Toast / Feedback

| Selektor | Komponente | Datei |
|----------|-----------|-------|
| `toast-success` | Erfolgs-Toast | `pim-frontend/src/components/shared/PimToast.vue` |
| `toast-error` | Fehler-Toast | `pim-frontend/src/components/shared/PimToast.vue` |
| `toast-info` | Info-Toast | `pim-frontend/src/components/shared/PimToast.vue` |

---

Gesamt: **~50 Selektoren** in **15 Komponenten**

## Konventionen

- **Navigation:** `nav-{section}` (z.B. `nav-products`)
- **Views:** `{name}-view` (z.B. `search-view`)
- **Buttons:** `btn-{action}` (z.B. `btn-save`, `btn-new-product`)
- **Formulare:** `field-{key}` (dynamisch aus PimForm field.key)
- **Tabs:** `tab-{key}` (dynamisch aus tabs computed)
- **Panels:** `{name}-panel` (z.B. `product-create-panel`)
- **Toast:** `toast-{type}` (success, error, info)
