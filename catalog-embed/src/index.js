/**
 * PublixxCatalog — Drop-in catalog widget framework.
 *
 * Usage:
 *   <script src="catalog-embed.umd.js"></script>
 *   <script>
 *     PublixxCatalog.init({ api: 'https://pim.example.com/api/v1' })
 *   </script>
 *
 * Then place widgets anywhere in your HTML:
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
import { configureApi } from './api.js'
import { useStore } from './store.js'

// Widget imports
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
import LoginWidget from './widgets/LoginWidget.vue'

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
  'login': LoginWidget,
}

const mountedApps = []

/**
 * Mountet das globale Login-Overlay (sichtbar nur bei state.requiresLogin),
 * unabhängig davon, welche Widgets der Host platziert hat.
 */
function mountLoginOverlay() {
  if (typeof document === 'undefined' || document.__pxc_login_mounted) return
  const el = document.createElement('div')
  el.setAttribute('data-pxc-login-overlay', '')
  document.body.appendChild(el)
  const app = createApp({ render() { return h(LoginWidget) } })
  app.mount(el)
  document.__pxc_login_mounted = true
  mountedApps.push({ el, app })
}

function mountWidgets() {
  const elements = document.querySelectorAll('[data-catalog]')

  elements.forEach((el) => {
    // Skip already-mounted elements
    if (el.__pxc_mounted) return

    const widgetName = el.getAttribute('data-catalog')
    const Component = WIDGET_MAP[widgetName]

    if (!Component) {
      console.warn(`[PublixxCatalog] Unknown widget: "${widgetName}". Available: ${Object.keys(WIDGET_MAP).join(', ')}`)
      return
    }

    // Collect data-* attributes as props (except data-catalog itself)
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
  if (typeof document !== 'undefined') {
    document.__pxc_login_mounted = false
  }
}

/**
 * Initialize the catalog framework.
 * @param {Object} options
 * @param {string} options.api - Base URL of the PIM API (e.g. "https://pim.example.com/api/v1")
 * @param {string} [options.token] - Bearer token for authenticated catalog access
 * @param {string} [options.locale] - Default locale ("de" or "en")
 * @param {number} [options.perPage] - Products per page (default: 24)
 * @param {number} [options.timeout] - API timeout in ms (default: 15000)
 * @param {boolean} [options.autoMount] - Auto-discover and mount widgets (default: true)
 */
async function init(options = {}) {
  // Configure API
  configureApi({
    baseUrl: options.api || options.baseUrl || '/api/v1',
    token: options.token,
    timeout: options.timeout,
  })

  // Configure store defaults
  const { state, actions } = useStore()
  if (options.locale) {
    state.locale = options.locale
  }
  if (options.perPage) {
    state.meta.per_page = options.perPage
  }
  // Vom Host übergebenes Token gilt als angemeldet (kein Login-Gate nötig).
  if (options.token) {
    state.authenticated = true
  }

  // Globales Login-Overlay bereitstellen (zeigt sich nur bei requiresLogin).
  mountLoginOverlay()

  // Load settings from PIM
  await actions.fetchSettings()

  // Import wishlist from URL if shared
  actions.importWishlistFromUrl()

  // Apply deeplinks (?sku=, ?cat=)
  await actions.applyDeeplinks()

  // Auto-mount widgets
  if (options.autoMount !== false) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mountWidgets)
    } else {
      mountWidgets()
    }
  }
}

// Public API
const PublixxCatalog = {
  init,
  mount: mountWidgets,
  destroy,
  store: useStore,
  widgets: WIDGET_MAP,
  version: '1.0.0',
}

// Expose globally for <script> tag usage
if (typeof window !== 'undefined') {
  window.PublixxCatalog = PublixxCatalog
}

export default PublixxCatalog
export { init, mountWidgets as mount, destroy, useStore }
