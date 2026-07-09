import catalogClient from './catalogClient'

function buildParams(options = {}) {
  const params = {}
  if (options.page) params.page = options.page
  if (options.perPage) params.per_page = options.perPage
  if (options.sort) params.sort = options.sort
  if (options.order) params.order = options.order
  if (options.search) params.search = options.search
  if (options.folder) params.folder = options.folder
  if (options.usagePurpose) params.usage_purpose = options.usagePurpose
  if (options.mediaType) params.media_type = options.mediaType
  if (options.usageType) params.usage_type = options.usageType
  if (options.lang) params.lang = options.lang
  if (options.filters) {
    for (const [attrId, value] of Object.entries(options.filters)) {
      params[`filters[${attrId}]`] = value
    }
  }
  return params
}

export default {
  getAssets(options = {}) {
    return catalogClient.get('/asset-catalog/assets', { params: buildParams(options) })
  },

  getAsset(id, options = {}) {
    return catalogClient.get(`/asset-catalog/assets/${id}`, { params: buildParams(options) })
  },

  getFolders(options = {}) {
    return catalogClient.get('/asset-catalog/folders', { params: buildParams(options) })
  },

  getFacets(options = {}) {
    return catalogClient.get('/asset-catalog/facets', { params: buildParams(options) })
  },

  getUsageTypes() {
    return catalogClient.get('/asset-catalog/usage-types')
  },

  getAssetProducts(assetId, options = {}) {
    return catalogClient.get(`/asset-catalog/assets/${assetId}/products`, { params: buildParams(options) })
  },

  getAssetNodes(assetId, options = {}) {
    return catalogClient.get(`/asset-catalog/assets/${assetId}/nodes`, { params: buildParams(options) })
  },

  downloadZip(mediaIds) {
    return catalogClient.post('/asset-catalog/download', { media_ids: mediaIds }, {
      responseType: 'blob',
      timeout: 120000,
    })
  },
}
