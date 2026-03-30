import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import documentPortalApi from '@/api/documentPortal'

export const useDocumentPortalStore = defineStore('documentPortal', () => {
  // Zustand
  const selectedCountry = ref(null) // { code, name, region }
  const searchQuery = ref('')
  const searchResults = ref([])
  const searchMeta = ref({ current_page: 1, last_page: 1, total: 0 })
  const currentProduct = ref(null)
  const productDocuments = ref(null) // Antwort von productDocuments endpoint
  const countries = ref([])
  const settings = ref({})
  const loading = ref(false)
  const documentsLoading = ref(false)

  // Berechnete Werte
  const countriesByRegion = computed(() => {
    const grouped = {}
    for (const c of countries.value) {
      if (!grouped[c.region]) grouped[c.region] = []
      grouped[c.region].push(c)
    }
    return grouped
  })

  const selectedLanguage = computed(() => {
    if (!selectedCountry.value) return 'de'
    // Land-zu-Sprache Mapping (vereinfacht)
    const map = {
      DE: 'de', AT: 'de', CH: 'de',
      US: 'en', GB: 'en', AU: 'en', CA: 'en', IN: 'en', SG: 'en',
      FR: 'fr', BE: 'fr',
      ES: 'es', MX: 'es',
      IT: 'it',
      NL: 'nl',
      PL: 'pl',
      JP: 'ja',
      CN: 'zh', TW: 'zh',
      KR: 'ko',
      BR: 'pt', PT: 'pt',
      RU: 'ru',
      SE: 'sv', NO: 'no', DK: 'da', FI: 'fi',
      CZ: 'cs', HU: 'hu', HR: 'hr', RO: 'ro',
      GR: 'el', TR: 'tr',
    }
    return map[selectedCountry.value.code] || 'en'
  })

  // Aktionen
  async function fetchSettings() {
    try {
      const { data } = await documentPortalApi.getSettings()
      settings.value = data.data || data
    } catch (e) {
      console.warn('[DocumentPortal] Einstellungen konnten nicht geladen werden:', e.message)
    }
  }

  async function fetchCountries() {
    try {
      const { data } = await documentPortalApi.getCountries()
      countries.value = data.data || data || []
    } catch (e) {
      console.warn('[DocumentPortal] Länder konnten nicht geladen werden:', e.message)
    }
  }

  function selectCountry(country) {
    selectedCountry.value = country
  }

  async function search(query) {
    searchQuery.value = query
    if (!query || query.length < 2) {
      searchResults.value = []
      return
    }

    loading.value = true
    try {
      const params = { q: query }
      if (selectedCountry.value) {
        params.country = selectedCountry.value.code
      }
      const { data } = await documentPortalApi.search(params)
      searchResults.value = data.data || []
      searchMeta.value = data.meta || { current_page: 1, last_page: 1, total: 0 }
    } catch (e) {
      console.error('[DocumentPortal] Suche fehlgeschlagen:', e.message)
      searchResults.value = []
    } finally {
      loading.value = false
    }
  }

  async function fetchProductDocuments(productId) {
    documentsLoading.value = true
    try {
      const params = {}
      if (selectedCountry.value) {
        params.country = selectedCountry.value.code
        params.lang = selectedLanguage.value
      }
      const { data } = await documentPortalApi.getProductDocuments(productId, params)
      productDocuments.value = data.data || data
      currentProduct.value = productDocuments.value.product
    } catch (e) {
      console.error('[DocumentPortal] Dokumente konnten nicht geladen werden:', e.message)
      productDocuments.value = null
    } finally {
      documentsLoading.value = false
    }
  }

  function reset() {
    searchQuery.value = ''
    searchResults.value = []
    currentProduct.value = null
    productDocuments.value = null
  }

  return {
    // Zustand
    selectedCountry,
    searchQuery,
    searchResults,
    searchMeta,
    currentProduct,
    productDocuments,
    countries,
    settings,
    loading,
    documentsLoading,
    // Berechnete
    countriesByRegion,
    selectedLanguage,
    // Aktionen
    fetchSettings,
    fetchCountries,
    selectCountry,
    search,
    fetchProductDocuments,
    reset,
  }
})
