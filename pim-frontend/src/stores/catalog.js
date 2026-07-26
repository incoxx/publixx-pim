import { defineStore } from 'pinia'
import { ref, reactive, computed, watch } from 'vue'
import catalogApi, { resolveMediaUrl } from '@/api/catalog'
import { CATALOG_SHARE_KEY } from '@/api/catalogClient'

export const useCatalogStore = defineStore('catalog', () => {
  // --- State ---
  const products = ref([])
  const currentProduct = ref(null)
  const categories = ref([])
  const hierarchyInfo = ref(null)
  const loading = ref(false)
  const productLoading = ref(false)
  const categoriesLoading = ref(false)
  const error = ref(null)

  // --- Facets ---
  const facets = ref([])
  const activeFilters = reactive({}) // { attributeId: 'value1,value2' or 'min:max' or '1'/'0' }

  // --- Quick Lookup — eigenständige Präfix-Filter (Name/SKU/Attribut-Spalten),
  // unabhängig von der Fassetten-Auswahl. Filtert serverseitig über die komplette
  // Treffermenge, nicht nur die aktuell angezeigte Seite.
  const quickLookupFilters = reactive({ name: '', sku: '', attributes: {} })

  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 24,
    total: 0,
  })

  const search = ref('')
  const selectedCategoryId = ref(null)
  const selectedCategoryName = ref(null)
  const hierarchyType = ref('master')
  const sort = ref({ field: 'name', order: 'asc' })
  const viewMode = ref(localStorage.getItem('catalog_view_mode') || 'grid')
  const locale = ref(localStorage.getItem('catalog_locale') || 'de')

  // --- Theme Settings ---
  const themeSettings = ref({
    font_family: 'Inter',
    font_heading_size: '1.75rem',
    font_body_size: '0.875rem',
    color_primary: '#1B3A5C',
    color_accent: '#0D9488',
    color_table_bg: '#f8fafc',
    color_body_text: '#111827',
    logo_url: null,
    catalog_title: 'Produktkatalog',
    impressum_url: null,
    kontakt_url: null,
    impressum_text: null,
    kontakt_text: null,
    footer_text: null,
    catalog_access_mode: 'public',
    catalog_linked_products_only: false,
    catalog_pdf_enabled: false,
    catalog_pdf_template_id: null,
    catalog_compare_enabled: false,
    catalog_compare_max_products: 3,
    catalog_excel_export_enabled: false,
    catalog_share_wishlist_enabled: false,
  })

  const _themeLoaded = ref(false)

  async function fetchThemeSettings() {
    try {
      const { data } = await catalogApi.getSettings()
      if (data.data) {
        themeSettings.value = { ...themeSettings.value, ...data.data }

        // Apply default_locale from settings if user hasn't explicitly chosen one
        if (!localStorage.getItem('catalog_locale') && data.data.default_locale) {
          locale.value = data.data.default_locale
        }
      }
      _themeLoaded.value = true
    } catch (e) {
      console.warn('Failed to load catalog theme settings:', e.message)
    }
  }

  // --- Wishlist (localStorage-backed) ---
  const WISHLIST_KEY = 'pim_catalog_wishlist'
  const wishlistIds = ref(JSON.parse(localStorage.getItem(WISHLIST_KEY) || '[]'))
  // Serverseitig nachgeladene Merklisten-Produkte, die NICHT auf der aktuellen
  // Grid-Seite liegen (id -> Produkt-Zusammenfassung). Nötig, damit die Merkliste
  // z. B. eine als Merkliste geöffnete Collection vollständig anzeigen kann.
  const wishlistProductCache = ref({})

  // --- Computed ---
  const isEmpty = computed(() => products.value.length === 0 && !loading.value)
  const wishlistCount = computed(() => wishlistIds.value.length)
  const searchActive = computed(() => search.value && search.value.trim().length > 0)

  function isInWishlist(productId) {
    return wishlistIds.value.includes(productId)
  }

  // Alle Merklisten-Produkte in Merklisten-Reihenfolge: bevorzugt die bereits im
  // Grid geladenen Produkte, ergänzt um serverseitig nachgeladene aus dem Cache.
  const wishlistProducts = computed(() => {
    const byId = {}
    for (const p of products.value) byId[p.id] = p
    const cache = wishlistProductCache.value
    const result = []
    for (const id of wishlistIds.value) {
      const p = byId[id] || cache[id]
      if (p) result.push(p)
    }
    return result
  })

  // Merklisten-IDs, für die (noch) keine Produktdaten vorliegen — z. B. inaktive/
  // gelöschte Produkte oder solche jenseits des Nachlade-Limits. Diese werden in der
  // Merkliste als "+ N weitere Produkte" zusammengefasst.
  const wishlistUnresolvedCount = computed(() => {
    const resolved = new Set(wishlistProducts.value.map((p) => p.id))
    return wishlistIds.value.filter((id) => !resolved.has(id)).length
  })

  // Lädt Produktdaten für Merklisten-IDs, die nicht bereits geladen/gecacht sind.
  async function fetchWishlistProducts() {
    const loaded = new Set(products.value.map((p) => p.id))
    const cache = wishlistProductCache.value
    const missing = wishlistIds.value.filter((id) => !loaded.has(id) && !cache[id])
    if (missing.length === 0) return
    try {
      const resp = await catalogApi.getProducts({
        ids: missing.slice(0, 200),
        perPage: 200,
        lang: locale.value,
      })
      const raw = Array.isArray(resp.data) ? resp.data : resp.data.data || resp.data
      const next = { ...wishlistProductCache.value }
      for (const p of raw) {
        next[p.id] = { ...p, image_url: resolveMediaUrl(p.image_url) }
      }
      wishlistProductCache.value = next
    } catch (e) {
      console.warn('Failed to load wishlist products:', e.message)
    }
  }

  // Persist wishlist
  watch(
    wishlistIds,
    (ids) => {
      localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids))
    },
    { deep: true },
  )

  // --- Actions ---

  async function fetchProducts() {
    loading.value = true
    error.value = null
    try {
      // Search overrides all other filters (category + facets + quick lookup)
      const isSearching = search.value && search.value.trim().length > 0
      const filtersPayload = isSearching
        ? undefined
        : (Object.keys(activeFilters).length > 0 ? { ...activeFilters } : undefined)
      const categoryPayload = isSearching
        ? undefined
        : (selectedCategoryId.value || undefined)

      const resp = await catalogApi.getProducts({
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: sort.value.field,
        order: sort.value.order,
        search: search.value || undefined,
        category: categoryPayload,
        hierarchyType: hierarchyType.value,
        lang: locale.value,
        filters: filtersPayload,
        name: !isSearching ? (quickLookupFilters.name || undefined) : undefined,
        sku: !isSearching ? (quickLookupFilters.sku || undefined) : undefined,
        quickAttributes: !isSearching && Object.keys(quickLookupFilters.attributes).length > 0
          ? quickLookupFilters.attributes
          : undefined,
      })
      // Response is now a bare array; pagination info in headers
      const rawProducts = Array.isArray(resp.data) ? resp.data : (resp.data.data || resp.data)
      // Resolve image URLs for cross-origin deployments
      products.value = rawProducts.map(p => ({
        ...p,
        image_url: resolveMediaUrl(p.image_url),
      }))
      const headers = resp.headers
      if (headers) {
        meta.value = {
          current_page: parseInt(headers['x-current-page'] || meta.value.current_page, 10),
          last_page: parseInt(headers['x-last-page'] || meta.value.last_page, 10),
          per_page: parseInt(headers['x-per-page'] || meta.value.per_page, 10),
          total: parseInt(headers['x-total-count'] || meta.value.total, 10),
        }
      }
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler beim Laden'
      products.value = []
    } finally {
      loading.value = false
    }
  }

  async function fetchProduct(id) {
    productLoading.value = true
    error.value = null
    try {
      const { data } = await catalogApi.getProduct(id, { lang: locale.value })
      const prod = data.data
      // Resolve media URLs for cross-origin deployments
      if (prod?.media) {
        prod.media = prod.media.map(m => ({ ...m, url: resolveMediaUrl(m.url) }))
      }
      currentProduct.value = prod
    } catch (e) {
      error.value = e.response?.data?.title || 'Produkt nicht gefunden'
      currentProduct.value = null
    } finally {
      productLoading.value = false
    }
  }

  async function fetchCategories() {
    categoriesLoading.value = true
    try {
      const opts = {
        type: hierarchyType.value,
        lang: locale.value,
      }
      // Use hierarchy_id from settings if available
      if (themeSettings.value.hierarchy_id) {
        opts.hierarchyId = themeSettings.value.hierarchy_id
      }
      const { data } = await catalogApi.getCategories(opts)
      categories.value = data.data.nodes || []
      hierarchyInfo.value = {
        hierarchy_id: data.data.hierarchy_id,
        hierarchy_name: data.data.hierarchy_name,
        type: data.data.type,
      }
    } catch (e) {
      console.error('Failed to load categories:', e)
      categories.value = []
    } finally {
      categoriesLoading.value = false
    }
  }

  // Wishlist
  function toggleWishlist(productId) {
    const idx = wishlistIds.value.indexOf(productId)
    if (idx === -1) {
      wishlistIds.value.push(productId)
    } else {
      wishlistIds.value.splice(idx, 1)
    }
  }

  function clearWishlist() {
    wishlistIds.value = []
  }

  function importWishlistFromUrl() {
    const params = new URLSearchParams(window.location.search)
    const wishlistParam = params.get('wishlist')
    if (!wishlistParam) return
    const ids = wishlistParam.split(',').filter(Boolean)
    if (ids.length === 0) return
    // Merge into existing wishlist (avoid duplicates)
    const existing = new Set(wishlistIds.value)
    for (const id of ids) {
      if (!existing.has(id)) {
        wishlistIds.value.push(id)
      }
    }
    // Clean the URL
    params.delete('wishlist')
    const newUrl = params.toString()
      ? `${window.location.pathname}?${params.toString()}`
      : window.location.pathname
    window.history.replaceState({}, '', newUrl)
  }

  // --- Collection als Merkliste (Deeplink ?collection=<id>) ---
  // Der kuratierte Katalog gibt nur die Collection-Referenz mit, nicht die Produkt-
  // liste selbst — so bleibt die URL konstant kurz, egal wie viele Produkte drin sind.
  // Der Katalog holt die Produkt-IDs serverseitig auf und ERSETZT die Merkliste
  // (die Collection = genau diese Produkte).
  const collectionInfo = ref(null)

  async function loadWishlistFromCollection(collectionId) {
    if (!collectionId) return null
    try {
      const { data } = await catalogApi.getCollectionWishlist(collectionId)
      const ids = data?.data?.product_ids || []
      wishlistIds.value = [...ids]
      collectionInfo.value = { id: collectionId, name: data?.data?.name || null, count: ids.length }
      return collectionInfo.value
    } catch (e) {
      console.warn('Failed to load collection wishlist:', e.message)
      return null
    }
  }

  async function importCollectionFromUrl() {
    const params = new URLSearchParams(window.location.search)
    const collectionId = params.get('collection')
    if (!collectionId) return
    await loadWishlistFromCollection(collectionId)
    // Clean the URL (Collection-Referenz nicht dauerhaft in der Adresszeile lassen)
    params.delete('collection')
    const newUrl = params.toString()
      ? `${window.location.pathname}?${params.toString()}`
      : window.location.pathname
    window.history.replaceState({}, '', newUrl)
  }

  // --- Freigabelink-Zugang (?share=<token>) ---
  // Der Empfänger öffnet den Katalog ohne PIM-Login: Token (+ evtl. Passwort) werden
  // gegen /catalog/share/<token> geprüft; bei Erfolg liefert der Server ein kurzlebiges
  // Access-Token (für login-gesicherte Kataloge) sowie die Produkt-IDs der Collection.
  function getShareTokenFromUrl() {
    return new URLSearchParams(window.location.search).get('share')
  }

  function clearShareParamFromUrl() {
    const params = new URLSearchParams(window.location.search)
    if (!params.has('share')) return
    params.delete('share')
    const newUrl = params.toString()
      ? `${window.location.pathname}?${params.toString()}`
      : window.location.pathname
    window.history.replaceState({}, '', newUrl)
  }

  async function fetchShareInfo(token) {
    // Liefert { expired, requires_password, collection_name } bzw. wirft bei 404/410.
    const { data } = await catalogApi.getShareInfo(token)
    return data.data
  }

  async function unlockShare(token, password) {
    const { data } = await catalogApi.unlockShare(token, password)
    const payload = data.data
    // Access-Token für Folge-Requests hinterlegen (Header X-Catalog-Share).
    if (payload.access) {
      sessionStorage.setItem(CATALOG_SHARE_KEY, payload.access)
    }
    // Collection als Merkliste laden (ersetzen — kuratierter Katalog = diese Produkte).
    wishlistIds.value = [...(payload.product_ids || [])]
    collectionInfo.value = {
      id: null,
      name: payload.collection_name || null,
      count: (payload.product_ids || []).length,
      fromShare: true,
    }
    return collectionInfo.value
  }

  // Navigation
  function setSearch(term) {
    search.value = term
    meta.value.current_page = 1
  }

  function setCategory(nodeId, nodeName = null) {
    selectedCategoryId.value = nodeId
    selectedCategoryName.value = nodeName
    meta.value.current_page = 1
  }

  function clearCategory() {
    selectedCategoryId.value = null
    selectedCategoryName.value = null
    meta.value.current_page = 1
  }

  function setPage(page) {
    meta.value.current_page = page
  }

  function setSort(field, order) {
    sort.value = { field, order }
    meta.value.current_page = 1
  }

  function setViewMode(mode) {
    viewMode.value = mode
    localStorage.setItem('catalog_view_mode', mode)
  }

  function setLocale(loc) {
    locale.value = loc
    localStorage.setItem('catalog_locale', loc)
  }

  // Facets
  async function fetchFacets() {
    try {
      const opts = { lang: locale.value }
      // Pass active filters so facet counts reflect current selection
      if (Object.keys(activeFilters).length > 0) {
        opts.filters = { ...activeFilters }
      }
      // Pass category so facet counts respect category selection
      if (selectedCategoryId.value) {
        opts.category = selectedCategoryId.value
        opts.hierarchyType = hierarchyType.value
      }
      const { data } = await catalogApi.getFacets(opts)
      facets.value = data.facets || []
    } catch (e) {
      console.warn('Failed to load facets:', e.message)
      facets.value = []
    }
  }

  function setFilter(attributeId, value) {
    activeFilters[attributeId] = value
    meta.value.current_page = 1
  }

  function clearFilter(attributeId) {
    delete activeFilters[attributeId]
    meta.value.current_page = 1
  }

  function clearAllFilters() {
    for (const key of Object.keys(activeFilters)) {
      delete activeFilters[key]
    }
    meta.value.current_page = 1
  }

  const activeFilterCount = computed(() => Object.keys(activeFilters).length)

  // Quick Lookup
  function setQuickLookupFilter(key, value) {
    if (key === 'name' || key === 'sku') {
      quickLookupFilters[key] = value
    } else {
      quickLookupFilters.attributes = { ...quickLookupFilters.attributes, [key]: value }
    }
    meta.value.current_page = 1
  }

  function clearQuickLookupFilters() {
    quickLookupFilters.name = ''
    quickLookupFilters.sku = ''
    quickLookupFilters.attributes = {}
    meta.value.current_page = 1
  }

  const quickLookupActive = computed(() =>
    quickLookupFilters.name !== '' || quickLookupFilters.sku !== '' || Object.keys(quickLookupFilters.attributes).length > 0
  )

  // --- Category Assets ---
  const categoryAssets = ref([])
  const categoryAssetsLoading = ref(false)

  async function fetchCategoryAssets(nodeId) {
    if (!nodeId) {
      categoryAssets.value = []
      return
    }
    categoryAssetsLoading.value = true
    try {
      const { data } = await catalogApi.getCategoryAssets(nodeId, { lang: locale.value, perPage: 12 })
      categoryAssets.value = data.data || data
    } catch {
      categoryAssets.value = []
    } finally {
      categoryAssetsLoading.value = false
    }
  }

  return {
    products,
    currentProduct,
    categories,
    hierarchyInfo,
    loading,
    productLoading,
    categoriesLoading,
    error,
    meta,
    search,
    selectedCategoryId,
    selectedCategoryName,
    hierarchyType,
    sort,
    viewMode,
    locale,
    wishlistIds,
    wishlistCount,
    wishlistProducts,
    wishlistUnresolvedCount,
    fetchWishlistProducts,
    isEmpty,
    isInWishlist,
    searchActive,
    fetchProducts,
    fetchProduct,
    fetchCategories,
    toggleWishlist,
    clearWishlist,
    importWishlistFromUrl,
    collectionInfo,
    loadWishlistFromCollection,
    importCollectionFromUrl,
    getShareTokenFromUrl,
    clearShareParamFromUrl,
    fetchShareInfo,
    unlockShare,
    setSearch,
    setCategory,
    clearCategory,
    setPage,
    setSort,
    setViewMode,
    setLocale,
    themeSettings,
    _themeLoaded,
    fetchThemeSettings,
    facets,
    activeFilters,
    activeFilterCount,
    fetchFacets,
    setFilter,
    clearFilter,
    clearAllFilters,
    quickLookupFilters,
    quickLookupActive,
    setQuickLookupFilter,
    clearQuickLookupFilters,
    categoryAssets,
    categoryAssetsLoading,
    fetchCategoryAssets,
  }
})
