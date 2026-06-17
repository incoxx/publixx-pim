/**
 * Lightweight API client for the PIM Catalog endpoints.
 * No axios dependency — uses native fetch().
 */

let _baseUrl = '/api/v1'
const TOKEN_STORAGE_KEY = 'pxc_token'

function loadStoredToken() {
  try {
    return typeof localStorage !== 'undefined' ? localStorage.getItem(TOKEN_STORAGE_KEY) : null
  } catch {
    return null
  }
}

let _token = loadStoredToken()
let _timeout = 15000

// In-memory response cache (GET requests only)
const _cache = new Map()
const _cacheTTL = 60000 // 60 seconds default
let _cacheEnabled = true

function getCached(key) {
  if (!_cacheEnabled) return null
  const entry = _cache.get(key)
  if (!entry) return null
  if (Date.now() - entry.ts > _cacheTTL) {
    _cache.delete(key)
    return null
  }
  return entry.data
}

function setCache(key, data) {
  if (!_cacheEnabled) return
  _cache.set(key, { data, ts: Date.now() })
}

export function clearCache() {
  _cache.clear()
}

export function configureApi({ baseUrl, token, timeout, cache }) {
  if (baseUrl) {
    let url = baseUrl.replace(/\/+$/, '')
    // Auto-upgrade http to https when page is served over HTTPS (prevents mixed content)
    if (typeof window !== 'undefined' && window.location.protocol === 'https:' && url.startsWith('http://')) {
      url = url.replace(/^http:\/\//, 'https://')
    }
    _baseUrl = url
  }
  if (token) _token = token
  if (timeout) _timeout = timeout
  if (cache === false) _cacheEnabled = false
}

/**
 * Setzt (oder löscht bei null) das Bearer-Token und persistiert es im
 * localStorage, damit ein Login über Reloads hinweg erhalten bleibt.
 */
export function setToken(token) {
  _token = token || null
  try {
    if (typeof localStorage !== 'undefined') {
      if (token) localStorage.setItem(TOKEN_STORAGE_KEY, token)
      else localStorage.removeItem(TOKEN_STORAGE_KEY)
    }
  } catch {
    /* localStorage nicht verfügbar — nur In-Memory-Token */
  }
}

export function getToken() {
  return _token
}

export function getBaseUrl() {
  return _baseUrl
}

/**
 * Resolve a media URL so <img src> works when API and frontend are on different origins.
 */
export function resolveMediaUrl(path) {
  if (!path) return null
  if (path.startsWith('http://') || path.startsWith('https://')) {
    // Auto-upgrade http to https when page is served over HTTPS (prevents mixed content)
    if (typeof window !== 'undefined' && window.location.protocol === 'https:' && path.startsWith('http://')) {
      return path.replace(/^http:\/\//, 'https://')
    }
    return path
  }
  if (_baseUrl.startsWith('http')) {
    try {
      const url = new URL(_baseUrl)
      return url.origin + path
    } catch { /* fall through */ }
  }
  return path
}

async function request(path, options = {}) {
  const url = _baseUrl + path

  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }
  if (_token) headers.Authorization = `Bearer ${_token}`

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), _timeout)

  try {
    const resp = await fetch(url, {
      ...options,
      headers: { ...headers, ...options.headers },
      signal: controller.signal,
    })
    clearTimeout(timer)

    if (!resp.ok) {
      const err = new Error(`HTTP ${resp.status}`)
      err.status = resp.status
      try { err.data = await resp.json() } catch {}
      throw err
    }

    return resp
  } catch (e) {
    clearTimeout(timer)
    throw e
  }
}

function buildQuery(options = {}) {
  const params = new URLSearchParams()
  if (options.page) params.set('page', options.page)
  if (options.perPage) params.set('per_page', options.perPage)
  if (options.sort) params.set('sort', options.sort)
  if (options.order) params.set('order', options.order)
  if (options.search) params.set('search', options.search)
  if (options.category) params.set('category', options.category)
  if (options.hierarchyType) params.set('hierarchy_type', options.hierarchyType)
  if (options.lang) params.set('lang', options.lang)
  if (options.type) params.set('type', options.type)
  if (options.hierarchyId) params.set('hierarchy_id', options.hierarchyId)
  if (options.filters) {
    for (const [attrId, value] of Object.entries(options.filters)) {
      params.set(`filters[${attrId}]`, value)
    }
  }
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export const catalogApi = {
  /**
   * Meldet einen PIM-Benutzer an und gibt das Sanctum-Token zurück.
   * Genutzt vom Login-Gate, wenn catalog_access_mode = 'login'.
   */
  async login(email, password) {
    const resp = await request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    const data = await resp.json()
    return data.data || data
  },

  async getProducts(options = {}) {
    const path = `/catalog/products${buildQuery(options)}`
    const cached = getCached(path)
    if (cached) return cached
    const resp = await request(path)
    const data = await resp.json()
    // Dynamische Kategorie-Counts (nur vorhanden wenn Facetten-Filter aktiv)
    const ccHeader = resp.headers.get('x-category-counts')
    const categoryCounts = ccHeader ? JSON.parse(ccHeader) : null

    const result = {
      products: Array.isArray(data) ? data : (data.data || data),
      meta: {
        current_page: parseInt(resp.headers.get('x-current-page') || '1', 10),
        last_page: parseInt(resp.headers.get('x-last-page') || '1', 10),
        per_page: parseInt(resp.headers.get('x-per-page') || '24', 10),
        total: parseInt(resp.headers.get('x-total-count') || '0', 10),
      },
      category_counts: categoryCounts,
    }
    setCache(path, result)
    return result
  },

  async getProduct(id, options = {}) {
    const path = `/catalog/products/${id}${buildQuery(options)}`
    const cached = getCached(path)
    if (cached) return cached
    const resp = await request(path)
    const data = await resp.json()
    const result = data.data || data
    setCache(path, result)
    return result
  },

  async getCategories(options = {}) {
    const path = `/catalog/categories${buildQuery(options)}`
    const cached = getCached(path)
    if (cached) return cached
    const resp = await request(path)
    const data = await resp.json()
    const result = data.data || data
    setCache(path, result)
    return result
  },

  async getCategoryAssets(nodeId, options = {}) {
    const path = `/catalog/categories/${nodeId}/assets${buildQuery(options)}`
    const cached = getCached(path)
    if (cached) return cached
    const resp = await request(path)
    const data = await resp.json()
    const result = data.data || data
    setCache(path, result)
    return result
  },

  async getSettings() {
    const path = '/catalog/settings'
    const cached = getCached(path)
    if (cached) return cached
    const resp = await request(path)
    const data = await resp.json()
    const result = data.data || data
    setCache(path, result)
    return result
  },

  async getFacets(options = {}) {
    const path = `/catalog/facets${buildQuery(options)}`
    // Skip cache when filters are active — counts change with each selection
    const hasFilters = options.filters && Object.keys(options.filters).length > 0
    if (!hasFilters) {
      const cached = getCached(path)
      if (cached) return cached
    }
    const resp = await request(path)
    const data = await resp.json()
    if (!hasFilters) {
      setCache(path, data)
    }
    return data
  },

  async getAttributeGroups(options = {}) {
    const path = `/catalog/attribute-groups${buildQuery(options)}`
    const cached = getCached(path)
    if (cached) return cached
    const resp = await request(path)
    const data = await resp.json()
    setCache(path, data)
    return data
  },

  async downloadProductPdf(id, options = {}) {
    const resp = await request(`/catalog/products/${id}/pdf${buildQuery(options)}`)
    return resp.blob()
  },

  async downloadWishlistPdf(productIds, lang) {
    const resp = await request('/catalog/wishlist/pdf', {
      method: 'POST',
      body: JSON.stringify({ product_ids: productIds, lang }),
    })
    return resp.blob()
  },

  async downloadWishlistExcel(productIds) {
    const resp = await request('/catalog/wishlist/excel', {
      method: 'POST',
      body: JSON.stringify({ product_ids: productIds }),
    })
    return resp.blob()
  },

  async compareProducts(productIds, lang) {
    const resp = await request('/catalog/products/compare', {
      method: 'POST',
      body: JSON.stringify({ product_ids: productIds, lang }),
    })
    const data = await resp.json()
    return data.data || data
  },
}
