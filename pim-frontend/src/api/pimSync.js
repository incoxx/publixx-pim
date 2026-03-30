import client from './client'

export default {
  // =====================================================================
  // User-API-Keys (eigene Keys verwalten)
  // =====================================================================

  listApiKeys() {
    return client.get('/user/api-keys')
  },

  createApiKey(data) {
    return client.post('/user/api-keys', data)
  },

  deleteApiKey(tokenId) {
    return client.delete(`/user/api-keys/${tokenId}`)
  },

  // Admin: Alle API-Keys
  adminListApiKeys() {
    return client.get('/admin/api-keys')
  },

  adminUserApiKeys(userId) {
    return client.get(`/admin/users/${userId}/api-keys`)
  },

  adminCreateApiKey(userId, data) {
    return client.post(`/admin/users/${userId}/api-keys`, data)
  },

  adminDeleteApiKey(tokenId) {
    return client.delete(`/admin/api-keys/${tokenId}`)
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
