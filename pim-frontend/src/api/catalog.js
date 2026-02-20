import catalogClient from './catalogClient'

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
}
