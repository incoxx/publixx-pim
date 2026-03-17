/**
 * Lightweight API client for the PIM Catalog endpoints.
 * No axios dependency — uses native fetch().
 */

let _baseUrl = '/api/v1'
let _token = null
let _timeout = 15000

export function configureApi({ baseUrl, token, timeout }) {
  if (baseUrl) _baseUrl = baseUrl.replace(/\/+$/, '')
  if (token) _token = token
  if (timeout) _timeout = timeout
}

export function getBaseUrl() {
  return _baseUrl
}

/**
 * Resolve a media URL so <img src> works when API and frontend are on different origins.
 */
export function resolveMediaUrl(path) {
  if (!path) return null
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (_baseUrl.startsWith('http')) {
    try {
      const url = new URL(_baseUrl)
      return url.origin + path
    } catch { /* fall through */ }
  }
  return path
}

async function request(path, options = {}) {
  const url = new URL(path, _baseUrl.startsWith('http') ? _baseUrl : window.location.origin + _baseUrl)
  if (!url.pathname.startsWith(_baseUrl.replace(/^https?:\/\/[^/]+/, ''))) {
    url.pathname = _baseUrl.replace(/^https?:\/\/[^/]+/, '') + path
  }

  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }
  if (_token) headers.Authorization = `Bearer ${_token}`

  const storedToken = typeof localStorage !== 'undefined' ? localStorage.getItem('pim_token') : null
  if (!_token && storedToken) headers.Authorization = `Bearer ${storedToken}`

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), _timeout)

  try {
    const resp = await fetch(url.toString(), {
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
  async getProducts(options = {}) {
    const resp = await request(`/catalog/products${buildQuery(options)}`)
    const data = await resp.json()
    return {
      products: Array.isArray(data) ? data : (data.data || data),
      meta: {
        current_page: parseInt(resp.headers.get('x-current-page') || '1', 10),
        last_page: parseInt(resp.headers.get('x-last-page') || '1', 10),
        per_page: parseInt(resp.headers.get('x-per-page') || '24', 10),
        total: parseInt(resp.headers.get('x-total-count') || '0', 10),
      },
    }
  },

  async getProduct(id, options = {}) {
    const resp = await request(`/catalog/products/${id}${buildQuery(options)}`)
    const data = await resp.json()
    return data.data || data
  },

  async getCategories(options = {}) {
    const resp = await request(`/catalog/categories${buildQuery(options)}`)
    const data = await resp.json()
    return data.data || data
  },

  async getSettings() {
    const resp = await request('/catalog/settings')
    const data = await resp.json()
    return data.data || data
  },

  async getFacets(options = {}) {
    const resp = await request(`/catalog/facets${buildQuery(options)}`)
    const data = await resp.json()
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
