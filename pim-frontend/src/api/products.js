import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/products', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/products/${id}`, { params: buildParams(options) })
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

  // Attribute values
  getAttributeValues(id, options = {}) {
    return client.get(`/products/${id}/attribute-values`, { params: buildParams(options) })
  },

  saveAttributeValues(id, values) {
    return client.put(`/products/${id}/attribute-values`, { values })
  },

  getResolvedAttributes(id) {
    return client.get(`/products/${id}/resolved-attributes`)
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
}
