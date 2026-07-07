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

  dependencies(id) {
    return client.get(`/products/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/products/${id}`, { params: force ? { force: true } : {} })
  },

  bulkDelete(productIds) {
    return client.post('/products/bulk-delete', { product_ids: productIds })
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
  getMedia(id, { page = 1, perPage = 100 } = {}) {
    return client.get(`/products/${id}/media`, { params: { page, per_page: perPage } })
  },

  attachMedia(id, data) {
    return client.post(`/products/${id}/media`, data)
  },

  detachMedia(productMediaId) {
    return client.delete(`/product-media/${productMediaId}`)
  },

  downloadMediaZip(id, assignmentIds) {
    return client.post(`/products/${id}/media/download-zip`, { assignment_ids: assignmentIds }, { responseType: 'blob' })
  },

  reorderMedia(id, assignmentIds) {
    return client.put(`/products/${id}/media/reorder`, { assignment_ids: assignmentIds })
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

  getRelationAttributeValues(relationId) {
    return client.get(`/product-relations/${relationId}/attribute-values`)
  },

  saveRelationAttributeValues(relationId, values) {
    return client.put(`/product-relations/${relationId}/attribute-values`, { values })
  },

  // Virtuelle Produkte (dynamische Cluster)
  getVirtualMembers(id, options = {}) {
    return client.get(`/products/${id}/virtual-members`, { params: buildParams(options) })
  },

  getVirtualDefinition(id) {
    return client.get(`/products/${id}/virtual-definition`)
  },

  saveVirtualDefinition(id, data) {
    return client.put(`/products/${id}/virtual-definition`, data)
  },

  deleteVirtualDefinition(id) {
    return client.delete(`/products/${id}/virtual-definition`)
  },

  virtualDefinitionFromWatchlist(id) {
    return client.post(`/products/${id}/virtual-definition/from-watchlist`)
  },

  syncVirtualDefinition(id) {
    return client.post(`/products/${id}/virtual-definition/sync`)
  },

  getVirtualInheritanceRules(id) {
    return client.get(`/products/${id}/virtual-inheritance-rules`)
  },

  saveVirtualInheritanceRules(id, rules) {
    return client.put(`/products/${id}/virtual-inheritance-rules`, { rules })
  },

  // Output Hierarchy Assignments
  getOutputHierarchyAssignments(id) {
    return client.get(`/products/${id}/output-hierarchy-assignments`)
  },

  // Output Hierarchy Resolved Attributes
  getOutputHierarchyResolvedAttributes(id, hierarchyId = null) {
    const params = hierarchyId ? { hierarchy_id: hierarchyId } : {}
    return client.get(`/products/${id}/output-hierarchy-resolved-attributes`, { params })
  },

  saveOutputHierarchyAttributeValues(id, outputHierarchyId, values) {
    return client.put(`/products/${id}/output-hierarchy-attribute-values`, { output_hierarchy_id: outputHierarchyId, values })
  },

  // Preview
  getPreview(id, params = {}) {
    return client.get(`/products/${id}/preview`, { params })
  },

  getCompleteness(id) {
    return client.get(`/products/${id}/completeness`)
  },

  getAvailableTransitions(id) {
    return client.get(`/products/${id}/available-transitions`)
  },

  getWorkflowHistory(id) {
    return client.get(`/products/${id}/workflow-history`)
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

  // Excel Export with configurable columns + filters
  exportExcel(params) {
    return client.post('/products/export/excel', params, { responseType: 'blob' })
  },
}
