/**
 * PublixxCatalogOffline — Offline catalog entry point.
 *
 * Usage:
 *   <script src="catalog-offline.umd.js"></script>
 *   <script>
 *     PublixxCatalogOffline.init({ dataPath: './data/' })
 *   </script>
 *
 * Then place widgets anywhere in your HTML (same as online):
 *   <div data-catalog="search"></div>
 *   <div data-catalog="categories"></div>
 *   <div data-catalog="facets"></div>
 *   <div data-catalog="toolbar"></div>
 *   <div data-catalog="product-grid"></div>
 *   <div data-catalog="pagination"></div>
 *   <div data-catalog="wishlist"></div>
 *   <div data-catalog="wishlist-button"></div>
 *   <div data-catalog="locale"></div>
 *   <div data-catalog="active-filters"></div>
 *   <div data-catalog="product-detail"></div>
 *   <div data-catalog="compare"></div>
 */

import { createApp, h } from 'vue'
import { setApiProvider, useStore } from './store.js'
import { createOfflineApi, offlineResolveMediaUrl } from './offline-api.js'

// Widget imports (same as index.js)
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

const mountedApps = []

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

function destroy() {
  mountedApps.forEach(({ app }) => app.unmount())
  mountedApps.length = 0
}

/**
 * Show/hide a loading overlay with progress info.
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

function updateLoadingProgress(loaded, total) {
  const el = document.getElementById('pxc-offline-progress')
  if (el) {
    const pct = total > 0 ? Math.round((loaded / total) * 100) : 0
    el.textContent = `${loaded.toLocaleString()} / ${total.toLocaleString()} Produkte geladen (${pct}%)`
  }
}

function hideLoadingOverlay() {
  const overlay = document.getElementById('pxc-offline-loading')
  if (overlay) overlay.style.display = 'none'
}

/**
 * Initialize the offline catalog.
 *
 * @param {Object} options
 * @param {string} options.dataPath - Path to the data directory (default: './data/')
 * @param {string} [options.locale] - Default locale ("de" or "en")
 * @param {number} [options.perPage] - Products per page (default: 24)
 * @param {boolean} [options.autoMount] - Auto-discover and mount widgets (default: true)
 * @param {boolean} [options.showProgress] - Show loading overlay during data load (default: true)
 * @param {object} [options.azure] - Azure configuration for hosted mode (Phase 3)
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
