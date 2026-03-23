import client from './client'

export default {
  // ─── Mappings CRUD ─────────────────────────────────────
  list(params = {}) {
    return client.get('/attribute-mappings', { params })
  },

  get(id) {
    return client.get(`/attribute-mappings/${id}`)
  },

  create(data) {
    return client.post('/attribute-mappings', data)
  },

  update(id, data) {
    return client.put(`/attribute-mappings/${id}`, data)
  },

  remove(id) {
    return client.delete(`/attribute-mappings/${id}`)
  },

  bulkStore(mappings) {
    return client.post('/attribute-mappings/bulk', { mappings })
  },

  // ─── Bedingte Regeln ───────────────────────────────────
  listRules(params = {}) {
    return client.get('/attribute-mapping-rules', { params })
  },

  createRule(data) {
    return client.post('/attribute-mapping-rules', data)
  },

  updateRule(id, data) {
    return client.put(`/attribute-mapping-rules/${id}`, data)
  },

  removeRule(id) {
    return client.delete(`/attribute-mapping-rules/${id}`)
  },

  // ─── Sync ──────────────────────────────────────────────
  syncProduct(productId, params = {}) {
    return client.post(`/attribute-mappings/sync/product/${productId}`, params)
  },

  syncBatch(data) {
    return client.post('/attribute-mappings/sync/batch', data)
  },

  syncBulk(productIds) {
    return client.post('/attribute-mappings/sync/bulk', { product_ids: productIds })
  },

  // ─── Excel Export / Import ──────────────────────────────
  exportExcel(params) {
    return client.post('/attribute-mappings/export-excel', params, { responseType: 'blob' })
  },

  importExcel(file, sourceHierarchyId, targetHierarchyId) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('source_hierarchy_id', sourceHierarchyId)
    formData.append('target_hierarchy_id', targetHierarchyId)
    return client.post('/attribute-mappings/import-excel', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
