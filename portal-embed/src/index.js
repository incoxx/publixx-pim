/**
 * PortalEmbed — Drop-in Portal-Widget-Framework.
 *
 * Platziere Widgets per data-portal Attribut:
 *   <div data-portal="country-select"></div>
 *   <div data-portal="language-select"></div>
 *   <div data-portal="filter-dropdown" data-attribute="uuid"></div>
 *   <div data-portal="filter-cards" data-attribute="uuid"></div>
 *   <div data-portal="submit-button"></div>
 *   <div data-portal="branding"></div>
 */

import { createApp, h } from 'vue'
import { configureApi } from './api.js'
import { useStore } from './store.js'

import CountrySelectWidget from './widgets/CountrySelectWidget.vue'
import LanguageSelectWidget from './widgets/LanguageSelectWidget.vue'
import FilterDropdownWidget from './widgets/FilterDropdownWidget.vue'
import FilterCardsWidget from './widgets/FilterCardsWidget.vue'
import SubmitButtonWidget from './widgets/SubmitButtonWidget.vue'
import BrandingWidget from './widgets/BrandingWidget.vue'

import './styles.css'

const WIDGET_MAP = {
  'country-select': CountrySelectWidget,
  'language-select': LanguageSelectWidget,
  'filter-dropdown': FilterDropdownWidget,
  'filter-cards': FilterCardsWidget,
  'submit-button': SubmitButtonWidget,
  'branding': BrandingWidget,
}

const mountedApps = []

function mountWidgets() {
  const elements = document.querySelectorAll('[data-portal]')

  elements.forEach((el) => {
    if (el.__pe_mounted) return

    const widgetName = el.getAttribute('data-portal')
    const Component = WIDGET_MAP[widgetName]

    if (!Component) {
      console.warn(`[PortalEmbed] Unbekanntes Widget: "${widgetName}". Verfuegbar: ${Object.keys(WIDGET_MAP).join(', ')}`)
      return
    }

    // data-* Attribute als Props sammeln
    const props = {}
    for (const attr of el.attributes) {
      if (attr.name.startsWith('data-') && attr.name !== 'data-portal') {
        const key = attr.name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())
        props[key] = attr.value
      }
    }

    const app = createApp({
      render() { return h(Component, props) },
    })

    app.mount(el)
    el.__pe_mounted = true
    mountedApps.push({ el, app })
  })
}

function destroy() {
  mountedApps.forEach(({ app }) => app.unmount())
  mountedApps.length = 0
}

/**
 * Portal initialisieren.
 * @param {Object} options
 * @param {string} options.api - PIM API Base-URL
 * @param {string} options.slug - Portal-Slug
 * @param {string} [options.catalogUrl] - Basis-URL fuer Catalog-Embed Redirect (default: /catalog-embed)
 * @param {string} [options.token] - Bearer Token
 * @param {number} [options.timeout] - API Timeout in ms
 */
async function init(options = {}) {
  configureApi({
    baseUrl: options.api || '/api/v1',
    token: options.token,
    timeout: options.timeout,
  })

  const { state, actions } = useStore()

  if (options.catalogUrl) {
    state.catalogUrl = options.catalogUrl
  }

  const slug = options.slug
  if (!slug) {
    console.error('[PortalEmbed] Kein slug angegeben in init()')
    return
  }

  await actions.fetchConfig(slug)
  await actions.fetchFilterValues(slug)

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountWidgets)
  } else {
    mountWidgets()
  }
}

const PortalEmbed = {
  init,
  mount: mountWidgets,
  destroy,
  store: useStore,
  widgets: WIDGET_MAP,
  version: '1.0.0',
}

if (typeof window !== 'undefined') {
  window.PortalEmbed = PortalEmbed
}

export default PortalEmbed
export { init, mountWidgets as mount, destroy, useStore }
