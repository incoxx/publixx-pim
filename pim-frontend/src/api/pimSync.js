import client from './client'

export default {
  // =====================================================================
  // API-Client Verwaltung (Machine-to-Machine Auth)
  // =====================================================================

  listClients() {
    return client.get('/api-clients')
  },

  getClient(id) {
    return client.get(`/api-clients/${id}`)
  },

  createClient(data) {
    return client.post('/api-clients', data)
  },

  updateClient(id, data) {
    return client.put(`/api-clients/${id}`, data)
  },

  deleteClient(id) {
    return client.delete(`/api-clients/${id}`)
  },

  regenerateSecret(id) {
    return client.post(`/api-clients/${id}/regenerate-secret`)
  },

  // =====================================================================
  // anyPIM Connector-Erweiterungen (Pull / Bidirektional)
  // =====================================================================

  pullProducts(connectionId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/pull-products`, options)
  },

  pullTranslations(connectionId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/pull-translations`, options)
  },

  syncBidirectional(connectionId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/sync-bidirectional`, options)
  },

  testConnection(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/test-connection`)
  },
}
