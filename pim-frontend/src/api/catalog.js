import catalogClient from './catalogClient'

/**
 * Resolve a catalog media URL so <img src> works in all deployment scenarios.
 * The API returns paths like "/api/v1/catalog/media/file.jpg" which only work
 * when the SPA and API are on the same origin. When VITE_API_BASE_URL points
 * to a different host, we need to prepend the API origin.
 */
const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export function resolveMediaUrl(path) {
  if (!path) return null
  // Already a full URL — nothing to do
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  // If apiBase is a full URL (e.g. "http://backend:8000/api/v1"), extract the origin
  if (apiBase.startsWith('http')) {
    try {
      const url = new URL(apiBase)
      return url.origin + path
    } catch { /* fall through */ }
  }
  // Same-origin deployment — relative path works as-is
  return path
}

function buildParams(options = {}) {
  const params = {}
  if (options.page) params.page = options.page
  if (options.perPage) params.per_page = options.perPage
  if (options.sort) params.sort = options.sort
  if (options.order) params.order = options.order
  if (options.search) params.search = options.search
  if (options.category) params.category = options.category
  if (options.hierarchyType) params.hierarchy_type = options.hierarchyType
  if (options.lang) params.lang = options.lang
  if (options.type) params.type = options.type
  if (options.hierarchyId) params.hierarchy_id = options.hierarchyId
  return params
}

export default {
  getProducts(options = {}) {
    return catalogClient.get('/catalog/products', { params: buildParams(options) })
  },

  getProduct(id, options = {}) {
    return catalogClient.get(`/catalog/products/${id}`, { params: buildParams(options) })
  },

  getCategories(options = {}) {
    return catalogClient.get('/catalog/categories', { params: buildParams(options) })
  },

  getSettings() {
    return catalogClient.get('/catalog/settings')
  },
}
