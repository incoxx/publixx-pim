/**
 * Portal-Embed Reactive Store — Vue 3 reactivity ohne Pinia.
 * Alle Portal-Widgets teilen diesen Store.
 */
import { reactive, computed } from 'vue'
import { portalApi } from './api.js'

function createStore() {
  const state = reactive({
    config: null,
    filterValues: [],
    selections: {},
    catalogTemplateSlug: '',
    catalogUrl: '/catalog-embed',
    locale: 'de',
    loading: false,
    error: null,
  })

  const getters = {
    branding: computed(() => state.config?.branding || {}),
    filterSteps: computed(() => state.config?.filter_steps || []),
    isComplete: computed(() => {
      const steps = state.config?.filter_steps || []
      return steps.length > 0 && steps.every(s => !!state.selections[s.key])
    }),
    redirectUrl: computed(() => {
      if (!state.catalogTemplateSlug) return null
      const params = new URLSearchParams()
      const steps = state.config?.filter_steps || []
      for (const step of steps) {
        const value = state.selections[step.key]
        if (value) {
          params.set(`filters[${step.attribute_id}]`, value)
        }
        if (step.derive_locale && value) {
          const locale = deriveLocale(value)
          if (locale) params.set('lang', locale)
        }
      }
      if (state.locale) params.set('lang', params.get('lang') || state.locale)
      return `${state.catalogUrl}/${state.catalogTemplateSlug}?${params.toString()}`
    }),
  }

  const actions = {
    async fetchConfig(slug) {
      state.loading = true
      state.error = null
      try {
        const result = await portalApi.getConfig(slug)
        state.config = result.data
        state.catalogTemplateSlug = result.data.catalog_template_slug || ''
        state.locale = result.data.default_locale || 'de'
      } catch (e) {
        state.error = e.message
        console.error('[PortalEmbed] Config laden fehlgeschlagen:', e.message)
      } finally {
        state.loading = false
      }
    },

    async fetchFilterValues(slug) {
      try {
        const result = await portalApi.getFilterValues(slug)
        state.filterValues = result.data || []
      } catch (e) {
        console.error('[PortalEmbed] Filter-Werte laden fehlgeschlagen:', e.message)
      }
    },

    setSelection(key, value) {
      state.selections[key] = value
    },

    clearSelection(key) {
      delete state.selections[key]
    },

    submit() {
      const url = getters.redirectUrl.value
      if (url) {
        window.location.href = url
      }
    },
  }

  return { state, getters, actions }
}

// Singleton
let _store = null
export function useStore() {
  if (!_store) _store = createStore()
  return _store
}

/** Laendercode → Sprache ableiten. */
function deriveLocale(countryCode) {
  const map = {
    DE: 'de', AT: 'de', CH: 'de',
    US: 'en', GB: 'en', AU: 'en', CA: 'en', IN: 'en', SG: 'en',
    FR: 'fr', BE: 'fr',
    ES: 'es', MX: 'es',
    IT: 'it', NL: 'nl', PL: 'pl',
    JP: 'ja', CN: 'zh', TW: 'zh', KR: 'ko',
    BR: 'pt', PT: 'pt', RU: 'ru',
    SE: 'sv', NO: 'no', DK: 'da', FI: 'fi',
    CZ: 'cs', HU: 'hu', HR: 'hr', RO: 'ro',
    GR: 'el', TR: 'tr',
  }
  return map[countryCode?.toUpperCase()] || null
}
