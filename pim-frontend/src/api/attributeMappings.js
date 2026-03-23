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
}
