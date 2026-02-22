import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import catalogApi from '@/api/catalog'

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

  // --- Wishlist (localStorage-backed) ---
  const WISHLIST_KEY = 'pim_catalog_wishlist'
  const wishlistIds = ref(JSON.parse(localStorage.getItem(WISHLIST_KEY) || '[]'))

  // --- Computed ---
  const isEmpty = computed(() => products.value.length === 0 && !loading.value)
  const wishlistCount = computed(() => wishlistIds.value.length)

  // JSON URLs
  const productsJsonUrl = computed(() => {
    const params = new URLSearchParams()
    params.set('lang', locale.value)
    params.set('per_page', String(meta.value.per_page))
    params.set('page', String(meta.value.current_page))
    params.set('sort', sort.value.field)
    params.set('order', sort.value.order)
    if (search.value) params.set('search', search.value)
    if (selectedCategoryId.value) params.set('category', selectedCategoryId.value)
    return '/api/v1/catalog/products?' + params.toString()
  })

  const exportJsonUrl = computed(() => {
    const params = new URLSearchParams()
    params.set('lang', locale.value)
    params.set('start', '0')
    params.set('limit', '100')
    return '/api/v1/catalog/products/export.json?' + params.toString()
  })

  function productJsonUrl(productId) {
    return '/api/v1/catalog/products/' + productId + '/json?lang=' + locale.value
  }

  function isInWishlist(productId) {
    return wishlistIds.value.includes(productId)
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
      const resp = await catalogApi.getProducts({
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: sort.value.field,
        order: sort.value.order,
        search: search.value || undefined,
        category: selectedCategoryId.value || undefined,
        hierarchyType: hierarchyType.value,
        lang: locale.value,
      })
      // Response is now a bare array; pagination info in headers
      products.value = Array.isArray(resp.data) ? resp.data : (resp.data.data || resp.data)
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
      currentProduct.value = data.data
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
      const { data } = await catalogApi.getCategories({
        type: hierarchyType.value,
        lang: locale.value,
      })
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
    isEmpty,
    isInWishlist,
    fetchProducts,
    fetchProduct,
    fetchCategories,
    toggleWishlist,
    clearWishlist,
    setSearch,
    setCategory,
    clearCategory,
    setPage,
    setSort,
    setViewMode,
    setLocale,
    productsJsonUrl,
    exportJsonUrl,
    productJsonUrl,
  }
})
