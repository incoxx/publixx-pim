/**
 * Reactive catalog store — powered by Vue 3 reactivity without Pinia.
 * All widgets share this single reactive state.
 */
import { reactive, computed, watch } from 'vue'
import { catalogApi as defaultApi, resolveMediaUrl as defaultResolveMedia, clearCache } from './api.js'

// Swappable API provider — allows offline mode to inject a different backend
let _api = defaultApi
let _resolveMedia = defaultResolveMedia

/**
 * Replace the API backend used by the store.
 * @param {object} api - Object implementing the same interface as catalogApi
 * @param {function} [resolveMedia] - Optional media URL resolver override
 */
export function setApiProvider(api, resolveMedia) {
  _api = api
  if (resolveMedia) _resolveMedia = resolveMedia
}

function createStore() {
  const state = reactive({
    // Products
    products: [],
    currentProduct: null,
    loading: false,
    productLoading: false,
    error: null,

    // Pagination
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 24,
      total: 0,
    },

    // Filters & navigation
    search: '',
    selectedCategoryId: null,
    selectedCategoryName: null,
    sort: { field: 'name', order: 'asc' },
    viewMode: (typeof localStorage !== 'undefined' && localStorage.getItem('pxc_view_mode')) || 'grid',
    locale: (typeof localStorage !== 'undefined' && localStorage.getItem('pxc_locale')) || 'de',

    // Categories
    categories: [],
    hierarchyInfo: null,
    categoriesLoading: false,

    // Facets
    facets: [],
    activeFilters: {},

    // Wishlist
    wishlistIds: JSON.parse((typeof localStorage !== 'undefined' && localStorage.getItem('pxc_wishlist')) || '[]'),

    // Settings (from PIM)
    settings: {},
    _settingsLoaded: false,

    // Compare
    compareData: null,
    compareLoading: false,
    compareOpen: false,
    compareProductIds: [],

    // Product detail modal/view
    detailOpen: false,
    detailProductId: null,
  })

  // Persist wishlist
  if (typeof localStorage !== 'undefined') {
    watch(() => state.wishlistIds, (ids) => {
      localStorage.setItem('pxc_wishlist', JSON.stringify(ids))
    }, { deep: true })

    // Persist view mode
    watch(() => state.viewMode, (v) => localStorage.setItem('pxc_view_mode', v))

    // Persist locale
    watch(() => state.locale, (v) => localStorage.setItem('pxc_locale', v))
  }

  // --- Computed-like getters ---
  const getters = {
    isEmpty: computed(() => state.products.length === 0 && !state.loading),
    wishlistCount: computed(() => state.wishlistIds.length),
    searchActive: computed(() => state.search && state.search.trim().length > 0),
    activeFilterCount: computed(() => Object.keys(state.activeFilters).length),
    isInWishlist(id) { return state.wishlistIds.includes(id) },
  }

  // --- Actions ---
  const actions = {
    async fetchSettings() {
      try {
        const data = await _api.getSettings()
        state.settings = data || {}
        if (!(typeof localStorage !== 'undefined' && localStorage.getItem('pxc_locale')) && data.default_locale) {
          state.locale = data.default_locale
        }
        state._settingsLoaded = true
      } catch (e) {
        console.warn('[PublixxCatalog] Failed to load settings:', e.message)
      }
    },

    async fetchProducts() {
      state.loading = true
      state.error = null
      try {
        const isSearching = state.search && state.search.trim().length > 0
        const result = await _api.getProducts({
          page: state.meta.current_page,
          perPage: state.meta.per_page,
          sort: state.sort.field,
          order: state.sort.order,
          search: state.search || undefined,
          category: isSearching ? undefined : (state.selectedCategoryId || undefined),
          lang: state.locale,
          filters: isSearching ? undefined : (Object.keys(state.activeFilters).length > 0 ? { ...state.activeFilters } : undefined),
          hierarchyId: state.settings.hierarchy_id || undefined,
        })
        state.products = result.products.map(p => ({
          ...p,
          image_url: _resolveMedia(p.image_url),
        }))
        state.meta = result.meta
      } catch (e) {
        state.error = e.data?.title || 'Fehler beim Laden'
        state.products = []
      } finally {
        state.loading = false
      }
    },

    async fetchProduct(id) {
      state.productLoading = true
      state.error = null
      try {
        const prod = await _api.getProduct(id, { lang: state.locale })
        if (prod?.media) {
          prod.media = prod.media.map(m => ({ ...m, url: _resolveMedia(m.url) }))
        }
        state.currentProduct = prod
      } catch (e) {
        state.error = e.data?.title || 'Produkt nicht gefunden'
        state.currentProduct = null
      } finally {
        state.productLoading = false
      }
    },

    async fetchCategories() {
      state.categoriesLoading = true
      try {
        const data = await _api.getCategories({
          lang: state.locale,
          hierarchyId: state.settings.hierarchy_id || undefined,
        })
        state.categories = data.nodes || []
        state.hierarchyInfo = {
          hierarchy_id: data.hierarchy_id,
          hierarchy_name: data.hierarchy_name,
          type: data.type,
        }
      } catch (e) {
        console.error('[PublixxCatalog] Categories load failed:', e)
        state.categories = []
      } finally {
        state.categoriesLoading = false
      }
    },

    async fetchFacets() {
      try {
        const data = await _api.getFacets({ lang: state.locale })
        state.facets = data.facets || []
      } catch (e) {
        console.warn('[PublixxCatalog] Facets load failed:', e.message)
        state.facets = []
      }
    },

    // Navigation
    setSearch(term) {
      state.search = term
      state.meta.current_page = 1
    },

    setCategory(nodeId, nodeName = null) {
      state.selectedCategoryId = nodeId
      state.selectedCategoryName = nodeName
      state.meta.current_page = 1
    },

    clearCategory() {
      state.selectedCategoryId = null
      state.selectedCategoryName = null
      state.meta.current_page = 1
    },

    setPage(page) {
      state.meta.current_page = page
    },

    setSort(field, order) {
      state.sort = { field, order }
      state.meta.current_page = 1
    },

    setViewMode(mode) {
      state.viewMode = mode
    },

    setLocale(loc) {
      state.locale = loc
      clearCache()
    },

    // Filters
    setFilter(attributeId, value) {
      state.activeFilters[attributeId] = value
      state.meta.current_page = 1
    },

    clearFilter(attributeId) {
      delete state.activeFilters[attributeId]
      state.meta.current_page = 1
    },

    clearAllFilters() {
      for (const key of Object.keys(state.activeFilters)) {
        delete state.activeFilters[key]
      }
      state.meta.current_page = 1
    },

    // Wishlist
    toggleWishlist(productId) {
      const idx = state.wishlistIds.indexOf(productId)
      if (idx === -1) {
        state.wishlistIds.push(productId)
      } else {
        state.wishlistIds.splice(idx, 1)
      }
    },

    clearWishlist() {
      state.wishlistIds.splice(0, state.wishlistIds.length)
    },

    importWishlistFromUrl() {
      const params = new URLSearchParams(window.location.search)
      const wl = params.get('wishlist')
      if (!wl) return
      const ids = wl.split(',').filter(Boolean)
      const existing = new Set(state.wishlistIds)
      for (const id of ids) {
        if (!existing.has(id)) state.wishlistIds.push(id)
      }
      params.delete('wishlist')
      const newUrl = params.toString()
        ? `${window.location.pathname}?${params.toString()}`
        : window.location.pathname
      window.history.replaceState({}, '', newUrl)
    },

    // Detail view
    openDetail(productId) {
      state.detailProductId = productId
      state.detailOpen = true
      actions.fetchProduct(productId)
    },

    closeDetail() {
      state.detailOpen = false
      state.currentProduct = null
      state.detailProductId = null
    },

    // Compare
    async openCompare(productIds) {
      state.compareProductIds = productIds || [...state.wishlistIds]
      state.compareOpen = true
      state.compareLoading = true
      try {
        state.compareData = await _api.compareProducts(state.compareProductIds, state.locale)
      } catch (e) {
        console.error('[PublixxCatalog] Compare failed:', e)
        state.compareData = null
      } finally {
        state.compareLoading = false
      }
    },

    closeCompare() {
      state.compareOpen = false
      state.compareData = null
      state.compareProductIds = []
    },

    // Exports
    async downloadProductPdf(id) {
      const blob = await _api.downloadProductPdf(id, { lang: state.locale })
      triggerBlobDownload(blob, `product-${id}.pdf`)
    },

    async downloadWishlistPdf() {
      const blob = await _api.downloadWishlistPdf(state.wishlistIds, state.locale)
      triggerBlobDownload(blob, `wishlist-${new Date().toISOString().slice(0, 10)}.pdf`)
    },

    async downloadWishlistExcel() {
      const blob = await _api.downloadWishlistExcel(state.wishlistIds)
      triggerBlobDownload(blob, `wishlist-${new Date().toISOString().slice(0, 10)}.xlsx`)
    },
  }

  return { state, getters, actions }
}

function triggerBlobDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

// Singleton store instance
let _store = null
export function useStore() {
  if (!_store) _store = createStore()
  return _store
}
