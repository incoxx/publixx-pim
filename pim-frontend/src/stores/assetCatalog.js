import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import assetCatalogApi from '@/api/assetCatalog'
import catalogApi from '@/api/catalog'

export const useAssetCatalogStore = defineStore('assetCatalog', () => {
  // --- State ---
  const assets = ref([])
  const currentAsset = ref(null)
  const folders = ref([])
  const folderHierarchyInfo = ref(null)
  const loading = ref(false)
  const assetLoading = ref(false)
  const foldersLoading = ref(false)
  const error = ref(null)

  // --- Theme Settings (shared with product catalog) ---
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
  })

  // --- "Verwendet von" (Used by) products ---
  const assetProducts = ref([])
  const assetProductsMeta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
  const assetProductsLoading = ref(false)

  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 24,
    total: 0,
  })

  const search = ref('')
  const selectedFolderId = ref(null)
  const selectedFolderName = ref(null)
  const usagePurposeFilter = ref(null)
  const mediaTypeFilter = ref(null)
  const sort = ref({ field: 'created_at', order: 'desc' })
  const viewMode = ref(localStorage.getItem('asset_catalog_view_mode') || 'grid')
  const locale = ref(localStorage.getItem('asset_catalog_locale') || 'de')

  // --- Wishlist (localStorage-backed) ---
  const WISHLIST_KEY = 'pim_asset_wishlist'
  let savedWishlist = []
  try {
    savedWishlist = JSON.parse(localStorage.getItem(WISHLIST_KEY) || '[]')
    if (!Array.isArray(savedWishlist)) savedWishlist = []
  } catch {
    localStorage.removeItem(WISHLIST_KEY)
    savedWishlist = []
  }
  const wishlistIds = ref(savedWishlist)

  // --- Computed ---
  const isEmpty = computed(() => assets.value.length === 0 && !loading.value)
  const wishlistCount = computed(() => wishlistIds.value.length)

  // Automatisch Relevanz-Sort bei aktiver Suche, wenn der User keinen anderen Sort gewählt hat
  const _userChangedSort = ref(false)
  const effectiveSort = computed(() => {
    if (search.value && search.value.trim() && !_userChangedSort.value) {
      return { field: 'relevance', order: 'desc' }
    }
    return sort.value
  })

  function isInWishlist(assetId) {
    return wishlistIds.value.includes(assetId)
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

  async function fetchThemeSettings() {
    try {
      const { data } = await catalogApi.getSettings()
      if (data.data) {
        themeSettings.value = { ...themeSettings.value, ...data.data }
        if (!localStorage.getItem('asset_catalog_locale') && data.data.default_locale) {
          locale.value = data.data.default_locale
        }
      }
    } catch (e) {
      console.warn('Failed to load catalog theme settings:', e.message)
    }
  }

  async function fetchAssetProducts(assetId, page = 1) {
    assetProductsLoading.value = true
    try {
      const { data } = await assetCatalogApi.getAssetProducts(assetId, {
        page,
        perPage: assetProductsMeta.value.per_page,
        lang: locale.value,
      })
      assetProducts.value = data.data
      if (data.meta) {
        assetProductsMeta.value = { ...assetProductsMeta.value, ...data.meta }
      }
    } catch (e) {
      console.error('Failed to load asset products:', e)
      assetProducts.value = []
    } finally {
      assetProductsLoading.value = false
    }
  }

  async function fetchAssets() {
    loading.value = true
    error.value = null
    try {
      const activeSort = effectiveSort.value
      const { data } = await assetCatalogApi.getAssets({
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: activeSort.field,
        order: activeSort.order,
        search: search.value || undefined,
        folder: selectedFolderId.value || undefined,
        usagePurpose: usagePurposeFilter.value || undefined,
        mediaType: mediaTypeFilter.value || undefined,
        lang: locale.value,
      })
      assets.value = data.data
      if (data.meta) {
        meta.value = { ...meta.value, ...data.meta }
      }
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler beim Laden'
      assets.value = []
    } finally {
      loading.value = false
    }
  }

  async function fetchAsset(id) {
    assetLoading.value = true
    error.value = null
    try {
      const { data } = await assetCatalogApi.getAsset(id, { lang: locale.value })
      currentAsset.value = data.data
    } catch (e) {
      error.value = e.response?.data?.title || 'Asset nicht gefunden'
      currentAsset.value = null
    } finally {
      assetLoading.value = false
    }
  }

  async function fetchFolders() {
    foldersLoading.value = true
    try {
      const { data } = await assetCatalogApi.getFolders({ lang: locale.value })
      folders.value = data.data.nodes || []
      folderHierarchyInfo.value = {
        hierarchy_id: data.data.hierarchy_id,
        hierarchy_name: data.data.hierarchy_name,
      }
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler beim Laden der Ordner'
      console.error('Failed to load folders:', e)
      folders.value = []
    } finally {
      foldersLoading.value = false
    }
  }

  async function downloadWishlist() {
    if (wishlistIds.value.length === 0) return
    try {
      const response = await assetCatalogApi.downloadZip(wishlistIds.value)
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `pim-assets-${new Date().toISOString().slice(0, 10)}.zip`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (e) {
      error.value = 'Download fehlgeschlagen'
      console.error('ZIP download failed:', e)
    }
  }

  // Wishlist
  function toggleWishlist(assetId) {
    const idx = wishlistIds.value.indexOf(assetId)
    if (idx === -1) {
      wishlistIds.value.push(assetId)
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
    _userChangedSort.value = false
  }

  function setFolder(nodeId, nodeName = null) {
    selectedFolderId.value = nodeId
    selectedFolderName.value = nodeName
    meta.value.current_page = 1
  }

  function clearFolder() {
    selectedFolderId.value = null
    selectedFolderName.value = null
    meta.value.current_page = 1
  }

  function setUsagePurpose(purpose) {
    usagePurposeFilter.value = purpose
    meta.value.current_page = 1
  }

  function setMediaType(type) {
    mediaTypeFilter.value = type
    meta.value.current_page = 1
  }

  function setPage(page) {
    meta.value.current_page = page
  }

  function setSort(field, order) {
    sort.value = { field, order }
    _userChangedSort.value = true
    meta.value.current_page = 1
  }

  function setViewMode(mode) {
    viewMode.value = mode
    localStorage.setItem('asset_catalog_view_mode', mode)
  }

  function setLocale(loc) {
    locale.value = loc
    localStorage.setItem('asset_catalog_locale', loc)
  }

  return {
    assets,
    currentAsset,
    folders,
    folderHierarchyInfo,
    loading,
    assetLoading,
    foldersLoading,
    error,
    meta,
    search,
    selectedFolderId,
    selectedFolderName,
    usagePurposeFilter,
    mediaTypeFilter,
    sort,
    effectiveSort,
    viewMode,
    locale,
    wishlistIds,
    wishlistCount,
    isEmpty,
    isInWishlist,
    themeSettings,
    fetchThemeSettings,
    assetProducts,
    assetProductsMeta,
    assetProductsLoading,
    fetchAssetProducts,
    fetchAssets,
    fetchAsset,
    fetchFolders,
    downloadWishlist,
    toggleWishlist,
    clearWishlist,
    setSearch,
    setFolder,
    clearFolder,
    setUsagePurpose,
    setMediaType,
    setPage,
    setSort,
    setViewMode,
    setLocale,
  }
})
