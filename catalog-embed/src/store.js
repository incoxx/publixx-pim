/**
 * Reaktiver Katalog-Store — nutzt Vue 3 Reactivity direkt (ohne Pinia/Vuex).
 *
 * Singleton: Alle Widgets teilen denselben reaktiven State. Der Store wird
 * einmalig via `useStore()` erstellt und danach gecacht.
 *
 * ## Architektur
 *
 * ```
 * API-Provider (online oder offline)
 *       ↕ setApiProvider()
 * Store (state + actions + getters)
 *       ↕ useStore()
 * Widgets (Vue-Komponenten lesen/schreiben state, rufen actions auf)
 * ```
 *
 * Der Store ist API-agnostisch — im Online-Modus wird `catalogApi` (api.js)
 * verwendet, im Offline-Modus `createOfflineApi` (offline-api.js).
 * Der Wechsel geschieht via `setApiProvider()` vor dem ersten `fetchProducts()`.
 *
 * @module store
 */
import { reactive, computed, watch } from 'vue'
import { catalogApi as defaultApi, resolveMediaUrl as defaultResolveMedia, clearCache, setToken, getToken } from './api.js'

/** @type {Object} Aktiver API-Provider (austauschbar via setApiProvider) */
let _api = defaultApi
/** @type {function(string): string} Aktiver Media-URL-Resolver */
let _resolveMedia = defaultResolveMedia

/**
 * Tauscht den API-Provider aus. Muss vor dem ersten Datenzugriff aufgerufen werden.
 *
 * Im Offline-Modus wird hier `createOfflineApi()` + `offlineResolveMediaUrl` injiziert.
 *
 * @param {Object} api - API-Objekt (muss getProducts, getProduct, getCategories, etc. implementieren)
 * @param {function} [resolveMedia] - Media-URL-Resolver (Standard: Online-Resolver)
 */
export function setApiProvider(api, resolveMedia) {
  _api = api
  if (resolveMedia) _resolveMedia = resolveMedia
}

/**
 * Erstellt den reaktiven Store mit State, Getters und Actions.
 *
 * @returns {{state: Object, getters: Object, actions: Object}}
 */
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
    categoryCounts: {},  // Dynamische Counts bei aktiven Filtern: { nodeId: count }

    // Facets
    facets: [],
    activeFilters: {},

    // Attribute groups (for detail view grouping)
    attributeGroups: [],

    // Wishlist
    wishlistIds: JSON.parse((typeof localStorage !== 'undefined' && localStorage.getItem('pxc_wishlist')) || '[]'),

    // Settings (from PIM)
    settings: {},
    _settingsLoaded: false,

    // Auth-Gate (catalog_access_mode = 'login')
    authenticated: !!getToken(),
    requiresLogin: false,
    authLoading: false,
    authError: null,

    // Compare
    compareData: null,
    compareLoading: false,
    compareOpen: false,
    compareProductIds: [],

    // Product detail modal/view
    detailOpen: false,
    detailProductId: null,

    // Mobile sidebar
    sidebarOpen: false,

    // Category assets
    categoryAssets: [],
    categoryAssetsLoading: false,
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
    /** Lädt Katalog-Einstellungen (Theme, Feature-Flags, Default-Locale). */
    async fetchSettings() {
      try {
        const data = await _api.getSettings()
        state.settings = data || {}
        if (!(typeof localStorage !== 'undefined' && localStorage.getItem('pxc_locale')) && data.default_locale) {
          state.locale = data.default_locale
        }
        state._settingsLoaded = true
        // Login-Gate: Bei 'login'-Modus und fehlender Anmeldung den Katalog
        // sperren, bevor Datenabrufe unnötige 401er erzeugen.
        if (state.settings.catalog_access_mode === 'login' && !state.authenticated) {
          state.requiresLogin = true
        }
      } catch (e) {
        console.warn('[PublixxCatalog] Failed to load settings:', e.message)
      }
    },

    /**
     * Erkennt 401-Fehler, verwirft ein ggf. abgelaufenes Token und aktiviert
     * das Login-Gate. Gibt true zurück, wenn es sich um einen Auth-Fehler handelt.
     * @param {Error} e
     * @returns {boolean}
     */
    handleAuthError(e) {
      if (e?.status !== 401) return false
      setToken(null)
      state.authenticated = false
      state.requiresLogin = true
      return true
    },

    /**
     * Meldet einen PIM-Benutzer an, speichert das Token und lädt den Katalog neu.
     * @param {string} email
     * @param {string} password
     * @returns {Promise<boolean>} true bei Erfolg
     */
    async login(email, password) {
      state.authLoading = true
      state.authError = null
      try {
        const result = await defaultApi.login(email, password)
        if (!result?.token) throw new Error('Kein Token erhalten')
        setToken(result.token)
        state.authenticated = true
        state.requiresLogin = false
        clearCache()
        await actions.reloadAll()
        return true
      } catch (e) {
        // Echten Fehler sichtbar machen (hilft bei der Diagnose):
        // - 401 → Zugangsdaten; 422 → Validierung (z. B. keine echte E-Mail);
        // - 419 → CSRF/Stateful-Domain; 429 → zu viele Versuche;
        // - kein Status → Server nicht erreichbar (Netzwerk/CORS/falsche API-URL).
        if (e.data?.detail || e.data?.title) {
          state.authError = e.data.detail || e.data.title
        } else if (e.data?.message) {
          state.authError = e.data.message
        } else if (e.status) {
          state.authError = `Anmeldung fehlgeschlagen (HTTP ${e.status}).`
        } else {
          state.authError = 'Server nicht erreichbar (Netzwerk-/CORS-Fehler oder falsche API-URL).'
        }
        return false
      } finally {
        state.authLoading = false
      }
    },

    /** Meldet ab, verwirft das Token und sperrt den Katalog erneut. */
    logout() {
      setToken(null)
      state.authenticated = false
      clearCache()
      if (state.settings.catalog_access_mode === 'login') {
        state.requiresLogin = true
      }
    },

    /** Lädt die Kerndaten (Kategorien, Facetten, Produkte) neu — z. B. nach Login. */
    async reloadAll() {
      await Promise.all([
        actions.fetchCategories(),
        actions.fetchFacets(),
        actions.fetchProducts(),
      ])
    },

    /** Lädt Produkte basierend auf aktuellem State (Suche, Kategorie, Filter, Sortierung, Seite). */
    async fetchProducts() {
      if (state.requiresLogin) return
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
        state.categoryCounts = result.category_counts || {}
      } catch (e) {
        if (actions.handleAuthError(e)) {
          state.products = []
          return
        }
        state.error = e.data?.title || 'Fehler beim Laden'
        state.products = []
      } finally {
        state.loading = false
      }
    },

    /**
     * Lädt ein vollständiges Produktdetail und setzt es als currentProduct.
     * @param {string} id - Produkt-UUID
     */
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
        if (actions.handleAuthError(e)) {
          state.currentProduct = null
          return
        }
        state.error = e.data?.title || 'Produkt nicht gefunden'
        state.currentProduct = null
      } finally {
        state.productLoading = false
      }
    },

    /** Lädt den Kategorie-Baum und speichert hierarchy-Info. */
    async fetchCategories() {
      if (state.requiresLogin) return
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
        if (actions.handleAuthError(e)) {
          state.categories = []
          return
        }
        console.error('[PublixxCatalog] Categories load failed:', e)
        state.categories = []
      } finally {
        state.categoriesLoading = false
      }
    },

    /** Lädt Facetten-Filter (berücksichtigt aktive Filter und Kategorie für Counts). */
    async fetchFacets() {
      if (state.requiresLogin) return
      try {
        const opts = { lang: state.locale }
        // Pass active filters so facet counts reflect current selection
        if (Object.keys(state.activeFilters).length > 0) {
          opts.filters = { ...state.activeFilters }
        }
        // Pass category so facet counts respect category selection
        if (state.selectedCategoryId) {
          opts.category = state.selectedCategoryId
        }
        const data = await _api.getFacets(opts)
        state.facets = data.facets || []
      } catch (e) {
        if (actions.handleAuthError(e)) {
          state.facets = []
          return
        }
        console.warn('[PublixxCatalog] Facets load failed:', e.message)
        state.facets = []
      }
    },

    /** Lädt Attributgruppen für die Detail-Ansicht. */
    async fetchAttributeGroups() {
      try {
        const data = await _api.getAttributeGroups({ lang: state.locale })
        state.attributeGroups = data.data || []
      } catch (e) {
        console.warn('[PublixxCatalog] Attribute groups load failed:', e.message)
        state.attributeGroups = []
      }
    },

    /**
     * Setzt den Suchbegriff und springt auf Seite 1.
     * @param {string} term - Suchbegriff
     */
    setSearch(term) {
      state.search = term
      state.meta.current_page = 1
      for (const key of Object.keys(state.activeFilters)) {
        delete state.activeFilters[key]
      }
    },

    /**
     * Wählt eine Kategorie und lädt deren Assets.
     * @param {string|null} nodeId - Kategorie-UUID
     * @param {string|null} [nodeName] - Anzeigename
     */
    setCategory(nodeId, nodeName = null) {
      state.selectedCategoryId = nodeId
      state.selectedCategoryName = nodeName
      state.meta.current_page = 1
      for (const key of Object.keys(state.activeFilters)) {
        delete state.activeFilters[key]
      }
      actions.fetchCategoryAssets(nodeId)
    },

    async fetchCategoryAssets(nodeId) {
      if (!nodeId) {
        state.categoryAssets = []
        return
      }
      state.categoryAssetsLoading = true
      try {
        const data = await _api.getCategoryAssets(nodeId, { lang: state.locale, per_page: 12 })
        state.categoryAssets = data.data || data || []
      } catch {
        state.categoryAssets = []
      } finally {
        state.categoryAssetsLoading = false
      }
    },

    clearCategory() {
      state.selectedCategoryId = null
      state.selectedCategoryName = null
      state.categoryAssets = []
      state.meta.current_page = 1
      for (const key of Object.keys(state.activeFilters)) {
        delete state.activeFilters[key]
      }
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

    /**
     * Wertet URL-Deeplinks aus und wendet sie auf den State an.
     *
     * Unterstützte Parameter:
     * - `?lang=de` — Sprache setzen
     * - `?cat=<uuid>` — Kategorie öffnen
     * - `?filters[<attr-id>]=<value>` — Facetten-Filter setzen
     * - `?sku=<sku>` — Produktdetail per SKU-Suche öffnen
     * - `?wishlist=<id1>,<id2>` — Merkliste importieren (via importWishlistFromUrl)
     */
    async applyDeeplinks() {
      const params = new URLSearchParams(window.location.search)

      // Language deeplink: ?lang=de
      const lang = params.get('lang')
      if (lang) {
        state.locale = lang
      }

      // Category deeplink: ?cat=<category-id>
      const cat = params.get('cat')
      if (cat) {
        actions.setCategory(cat)
        await actions.fetchProducts()
      }

      // Filter deeplinks: ?filters[attr-id]=value
      let hasFilters = false
      for (const [key, value] of params) {
        const match = key.match(/^filters\[(.+)]$/)
        if (match) {
          actions.setFilter(match[1], decodeURIComponent(value))
          hasFilters = true
        }
      }
      if (hasFilters && !cat) {
        await actions.fetchProducts()
        await actions.fetchFacets()
      }

      // Product SKU deeplink: ?sku=<sku>
      const sku = params.get('sku')
      if (sku) {
        try {
          const result = await _api.getProducts({ search: sku, perPage: 1, lang: state.locale })
          const product = result.products && result.products[0]
          if (product) {
            actions.openDetail(product.id)
          }
        } catch (e) {
          console.warn('[PublixxCatalog] Deeplink SKU lookup failed:', e.message)
        }
      }
    },

    /** Importiert eine geteilte Merkliste aus der URL (?wishlist=id1,id2,...) und bereinigt die URL. */
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

    /**
     * Öffnet die Produktdetail-Ansicht und lädt das Produkt.
     * @param {string} productId - Produkt-UUID
     */
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

    /**
     * Öffnet den Produktvergleich und lädt die Vergleichsdaten.
     * @param {string[]} [productIds] - Produkt-UUIDs (Standard: Merkliste)
     */
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

    // Mobile sidebar
    openSidebar() { state.sidebarOpen = true },
    closeSidebar() { state.sidebarOpen = false },
    toggleSidebar() { state.sidebarOpen = !state.sidebarOpen },

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

/**
 * Erzeugt einen temporären Download-Link für einen Blob und löst den Download aus.
 *
 * @param {Blob} blob - Dateiinhalt
 * @param {string} filename - Dateiname für den Download
 */
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

/** @type {{state: Object, getters: Object, actions: Object}|null} Singleton-Instanz */
let _store = null

/**
 * Gibt die Singleton-Store-Instanz zurück (erstellt sie beim ersten Aufruf).
 *
 * @returns {{state: Object, getters: Object, actions: Object}}
 */
export function useStore() {
  if (!_store) _store = createStore()
  return _store
}
