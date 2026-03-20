import client from './client'

export default {
  // Verfügbare Connectoren
  list() {
    return client.get('/connectors')
  },

  // Aktive Verbindungen
  connections() {
    return client.get('/connectors/connections')
  },

  // Verbindungs-Details
  getConnection(id) {
    return client.get(`/connectors/connections/${id}`)
  },

  // Verbindung erstellen
  createConnection(data) {
    return client.post('/connectors/connections', data)
  },

  // Verbindung löschen
  deleteConnection(id) {
    return client.delete(`/connectors/connections/${id}`)
  },

  // OAuth-Autorisierung starten
  authorize(type) {
    return client.get(`/connectors/${type}/authorize`)
  },

  // OAuth-Callback
  callback(type, data) {
    return client.post(`/connectors/${type}/callback`, data)
  },

  // Media sync
  syncMedia(connectionId, mediaId) {
    return client.post(`/connectors/connections/${connectionId}/sync-media`, { media_id: mediaId })
  },

  syncMediaBulk(connectionId, mediaIds) {
    return client.post(`/connectors/connections/${connectionId}/sync-media-bulk`, { media_ids: mediaIds })
  },

  // Produkt sync
  syncProduct(connectionId, productId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/sync-product`, { product_id: productId, ...options })
  },

  syncProductBulk(connectionId, productIds, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/sync-product-bulk`, { product_ids: productIds, ...options })
  },

  // Sync-Logs
  syncLogs(connectionId, params = {}) {
    return client.get(`/connectors/connections/${connectionId}/sync-logs`, { params })
  },

  // Connector Credentials (Admin)
  getCredentials() {
    return client.get('/settings/connector-credentials')
  },

  updateCredentials(data) {
    return client.put('/settings/connector-credentials', data)
  },
}
