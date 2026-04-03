/**
 * PublixxCatalogOffline — Einstiegspunkt für den Offline-Katalog.
 *
 * Wird als UMD-Bundle (`catalog-offline.umd.js`) gebaut und per `<script>`-Tag eingebunden.
 * Alle Daten werden aus vorexportierten JSON-Dateien gelesen (kein Server nötig).
 *
 * ## Verwendung
 *
 * ```html
 * <link rel="stylesheet" href="./catalog-embed.css">
 * <script src="./catalog-offline.umd.js"></script>
 * <script>
 *   PublixxCatalogOffline.init({
 *     dataPath: './data/',
 *     locale: 'de',
 *     perPage: 24,
 *   })
 * </script>
 * ```
 *
 * ## Widgets (data-catalog Attribut)
 *
 * Widgets werden automatisch erkannt und in DOM-Elemente gemountet:
 *
 * | Widget              | Funktion                               |
 * |---------------------|----------------------------------------|
 * | `search`            | Suchfeld                               |
 * | `categories`        | Kategorie-Baum (Sidebar)               |
 * | `facets`            | Facetten-Filter (Sidebar)              |
 * | `toolbar`           | Sortierung, Ansicht, Ergebnisanzahl    |
 * | `product-grid`      | Produktliste (Grid/Liste)              |
 * | `pagination`        | Seitenwechsel                          |
 * | `wishlist`          | Merkliste-Ansicht                      |
 * | `wishlist-button`   | Merkliste-Toggle-Button                |
 * | `locale`            | Sprachauswahl (de/en)                  |
 * | `active-filters`    | Aktive Filter-Tags                     |
 * | `product-detail`    | Produktdetail-Modal                    |
 * | `compare`           | Produktvergleich-Modal                 |
 * | `sidebar-toggle`    | Hamburger-Menü für Mobile              |
 *
 * @module offline-init
 */

import { createApp, h } from 'vue'
import { setApiProvider, useStore } from './store.js'
import { createOfflineApi, offlineResolveMediaUrl } from './offline-api.js'

// Widget-Imports (identisch zu index.js für Online-Modus)
import SearchWidget from './widgets/SearchWidget.vue'
import CategoriesWidget from './widgets/CategoriesWidget.vue'
import FacetsWidget from './widgets/FacetsWidget.vue'
import ProductGridWidget from './widgets/ProductGridWidget.vue'
import PaginationWidget from './widgets/PaginationWidget.vue'
import ToolbarWidget from './widgets/ToolbarWidget.vue'
import WishlistWidget from './widgets/WishlistWidget.vue'
import WishlistButtonWidget from './widgets/WishlistButtonWidget.vue'
import ProductDetailWidget from './widgets/ProductDetailWidget.vue'
import CompareWidget from './widgets/CompareWidget.vue'
import LocaleWidget from './widgets/LocaleWidget.vue'
import ActiveFiltersWidget from './widgets/ActiveFiltersWidget.vue'
import SidebarToggleWidget from './widgets/SidebarToggleWidget.vue'

// CSS
import './styles.css'

const WIDGET_MAP = {
  'search': SearchWidget,
  'categories': CategoriesWidget,
  'facets': FacetsWidget,
  'product-grid': ProductGridWidget,
  'pagination': PaginationWidget,
  'toolbar': ToolbarWidget,
  'wishlist': WishlistWidget,
  'wishlist-button': WishlistButtonWidget,
  'product-detail': ProductDetailWidget,
  'compare': CompareWidget,
  'locale': LocaleWidget,
  'active-filters': ActiveFiltersWidget,
  'sidebar-toggle': SidebarToggleWidget,
}

/** @type {{el: HTMLElement, app: import('vue').App}[]} Gemountete Vue-App-Instanzen */
const mountedApps = []

/**
 * Findet alle `[data-catalog]` Elemente im DOM und mountet die passenden Vue-Widgets.
 *
 * Bereits gemountete Elemente (markiert via `__pxc_mounted`) werden übersprungen.
 * Zusätzliche `data-*` Attribute werden als Props an das Widget übergeben.
 */
function mountWidgets() {
  const elements = document.querySelectorAll('[data-catalog]')

  elements.forEach((el) => {
    if (el.__pxc_mounted) return

    const widgetName = el.getAttribute('data-catalog')
    const Component = WIDGET_MAP[widgetName]

    if (!Component) {
      console.warn(`[PublixxCatalogOffline] Unknown widget: "${widgetName}". Available: ${Object.keys(WIDGET_MAP).join(', ')}`)
      return
    }

    const props = {}
    for (const attr of el.attributes) {
      if (attr.name.startsWith('data-') && attr.name !== 'data-catalog') {
        const key = attr.name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())
        props[key] = attr.value
      }
    }

    const app = createApp({
      render() { return h(Component, props) },
    })

    app.mount(el)
    el.__pxc_mounted = true
    mountedApps.push({ el, app })
  })
}

/**
 * Unmountet alle aktiven Widget-Instanzen und räumt die Liste auf.
 */
function destroy() {
  mountedApps.forEach(({ app }) => app.unmount())
  mountedApps.length = 0
}

/**
 * Zeigt ein Lade-Overlay mit Fortschrittsanzeige.
 *
 * Wird beim initialen Chunk-Loading angezeigt und enthält einen
 * Fortschrittstext (#pxc-offline-progress), der via updateLoadingProgress() aktualisiert wird.
 *
 * @param {string} message - Anzuzeigende Nachricht (z.B. 'Offline-Katalog wird geladen...')
 */
function showLoadingOverlay(message) {
  let overlay = document.getElementById('pxc-offline-loading')
  if (!overlay) {
    overlay = document.createElement('div')
    overlay.id = 'pxc-offline-loading'
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.95);display:flex;align-items:center;justify-content:center;z-index:99999;font-family:system-ui,sans-serif;'
    document.body.appendChild(overlay)
  }
  overlay.innerHTML = `<div style="text-align:center">
    <div style="font-size:1.2em;color:#333;margin-bottom:12px">${message}</div>
    <div id="pxc-offline-progress" style="font-size:0.9em;color:#666"></div>
  </div>`
  overlay.style.display = 'flex'
}

/**
 * Aktualisiert den Fortschrittstext im Lade-Overlay.
 *
 * @param {number} loaded - Anzahl geladener Produkte
 * @param {number} total - Gesamtanzahl der Produkte
 */
function updateLoadingProgress(loaded, total) {
  const el = document.getElementById('pxc-offline-progress')
  if (el) {
    const pct = total > 0 ? Math.round((loaded / total) * 100) : 0
    el.textContent = `${loaded.toLocaleString()} / ${total.toLocaleString()} Produkte geladen (${pct}%)`
  }
}

/** Versteckt das Lade-Overlay (nach Abschluss des Chunk-Loadings). */
function hideLoadingOverlay() {
  const overlay = document.getElementById('pxc-offline-loading')
  if (overlay) overlay.style.display = 'none'
}

/**
 * Initialisiert den Offline-Katalog.
 *
 * Ablauf:
 * 1. Offline-API erstellen und in den Store injizieren
 * 2. Store-Defaults konfigurieren (Locale, perPage)
 * 3. Settings laden (Theme, Feature-Flags)
 * 4. Widgets auto-mounten (falls autoMount !== false)
 * 5. Kategorien, Facetten und Produkte parallel laden
 * 6. Deeplinks auswerten (?sku=, ?cat=, ?lang=, ?filters[]=)
 *
 * @param {Object} [options]
 * @param {string} [options.dataPath='./data/'] - Pfad zum Datenverzeichnis
 * @param {string} [options.locale] - Startsprache ('de' oder 'en')
 * @param {number} [options.perPage=24] - Produkte pro Seite
 * @param {boolean} [options.autoMount=true] - Widgets automatisch erkennen und mounten
 * @param {boolean} [options.showProgress=true] - Lade-Overlay mit Fortschritt anzeigen
 * @returns {Promise<void>} Resolves wenn alle Daten geladen und Widgets gemountet sind
 */
async function init(options = {}) {
  const dataPath = options.dataPath || './data/'
  const showProgress = options.showProgress !== false

  if (showProgress) {
    showLoadingOverlay('Offline-Katalog wird geladen...')
  }

  // Create offline API and swap it into the store
  const offlineApi = createOfflineApi(dataPath, {
    onProgress: showProgress ? updateLoadingProgress : undefined,
  })
  setApiProvider(offlineApi, offlineResolveMediaUrl)

  // Configure store defaults
  const { state, actions } = useStore()
  if (options.locale) {
    state.locale = options.locale
  }
  if (options.perPage) {
    state.meta.per_page = options.perPage
  }

  // Load settings
  await actions.fetchSettings()

  // Import wishlist from URL if shared
  actions.importWishlistFromUrl()

  // Auto-mount widgets
  if (options.autoMount !== false) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mountWidgets)
    } else {
      mountWidgets()
    }
  }

  // Trigger initial data loads (categories, facets, products)
  await Promise.all([
    actions.fetchCategories(),
    actions.fetchFacets(),
    actions.fetchAttributeGroups(),
  ])

  // Load products (this triggers the chunk loading with progress)
  await actions.fetchProducts()

  // Apply deeplinks (?sku=, ?cat=)
  await actions.applyDeeplinks()

  if (showProgress) {
    hideLoadingOverlay()
  }
}

// Public API
const PublixxCatalogOffline = {
  init,
  mount: mountWidgets,
  destroy,
  store: useStore,
  widgets: WIDGET_MAP,
  version: '1.0.0',
  mode: 'offline',
}

// Expose globally for <script> tag usage
if (typeof window !== 'undefined') {
  window.PublixxCatalogOffline = PublixxCatalogOffline
}

export default PublixxCatalogOffline
export { init, mountWidgets as mount, destroy, useStore }
