import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/products', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/products/${id}`, { params: buildParams(options) })
  },

  compare(id1, id2) {
    return client.get('/products/compare', { params: { ids: `${id1},${id2}` } })
  },

  create(data) {
    return client.post('/products', data)
  },

  update(id, data) {
    return client.put(`/products/${id}`, data)
  },

  delete(id) {
    return client.delete(`/products/${id}`)
  },

  duplicate(id, options = {}) {
    return client.post(`/products/${id}/duplicate`, options)
  },

  // Attribute values
  getAttributeValues(id, options = {}) {
    const params = buildParams(options)
    if (options.lang) params.lang = options.lang
    return client.get(`/products/${id}/attribute-values`, { params })
  },

  saveAttributeValues(id, values) {
    return client.put(`/products/${id}/attribute-values`, { values })
  },

  getResolvedAttributes(id, hierarchyNodeId = null) {
    const params = hierarchyNodeId ? { hierarchy_node_id: hierarchyNodeId } : {}
    return client.get(`/products/${id}/resolved-attributes`, { params })
  },

  // Variants
  getVariants(id) {
    return client.get(`/products/${id}/variants`)
  },

  createVariant(id, data) {
    return client.post(`/products/${id}/variants`, data)
  },

  getVariantRules(id) {
    return client.get(`/products/${id}/variant-rules`)
  },

  setVariantRules(id, rules) {
    return client.put(`/products/${id}/variant-rules`, { rules })
  },

  generateVariants(id, data) {
    return client.post(`/products/${id}/variants/generate`, data)
  },

  // Media
  getMedia(id) {
    return client.get(`/products/${id}/media`)
  },

  attachMedia(id, data) {
    return client.post(`/products/${id}/media`, data)
  },

  detachMedia(productMediaId) {
    return client.delete(`/product-media/${productMediaId}`)
  },

  // Prices
  getPrices(id) {
    return client.get(`/products/${id}/prices`)
  },

  createPrice(id, data) {
    return client.post(`/products/${id}/prices`, data)
  },

  updatePrice(priceId, data) {
    return client.put(`/product-prices/${priceId}`, data)
  },

  deletePrice(priceId) {
    return client.delete(`/product-prices/${priceId}`)
  },

  // Relations
  getRelations(id) {
    return client.get(`/products/${id}/relations`)
  },

  createRelation(id, data) {
    return client.post(`/products/${id}/relations`, data)
  },

  deleteRelation(relationId) {
    return client.delete(`/product-relations/${relationId}`)
  },

  // Preview
  getPreview(id) {
    return client.get(`/products/${id}/preview`)
  },

  getCompleteness(id) {
    return client.get(`/products/${id}/completeness`)
  },

  downloadPreviewExcel(id) {
    return client.get(`/products/${id}/preview/export.xlsx`, { responseType: 'blob' })
  },

  downloadPreviewPdf(id) {
    return client.get(`/products/${id}/preview/export.pdf`, { responseType: 'blob' })
  },

  // XLIFF Translation Export/Import
  exportXliff({ sourceLang, targetLang, productIds }) {
    const params = { source_lang: sourceLang, target_lang: targetLang }
    if (productIds?.length) params.product_ids = productIds.join(',')
    return client.get('/translations/xliff/export', { params, responseType: 'blob' })
  },

  importXliff(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/translations/xliff/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
