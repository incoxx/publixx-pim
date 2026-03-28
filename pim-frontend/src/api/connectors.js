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

  // Vorschau/Dry Run
  previewProduct(connectionId, productId, language = 'de') {
    return client.post(`/connectors/connections/${connectionId}/preview-product`, { product_id: productId, language })
  },

  // Produkt-IDs für Sync ermitteln (ohne zu syncen)
  syncProductIds(connectionId) {
    return client.get(`/connectors/connections/${connectionId}/sync-product-ids`)
  },

  // Profil-basierter Sync (Shopware)
  syncFromProfile(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/sync-profile`)
  },

  // Hierarchie-Sync (Kategorien an Shopware)
  syncHierarchy(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/sync-hierarchy`)
  },

  // Verbindung aktualisieren (Settings + Export-Profil)
  updateConnection(connectionId, data) {
    return client.put(`/connectors/connections/${connectionId}`, data)
  },

  // Vorschau-Profile für Connector-Konfiguration
  websiteProfiles() {
    return client.get('/connectors/website-profiles')
  },

  // Delta-Sync (nur neue/geänderte Produkte)
  deltaSync(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/delta-sync`)
  },

  // Reset: Alle PIM-Daten aus Shopware löschen
  resetShop(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/reset`)
  },

  // Purge: Alle Kategorien aus Shopware löschen
  purgeCategories(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/purge-categories`)
  },

  // Purge: Alle PIM-Medien aus Shopware löschen
  purgeMedia(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/purge-media`)
  },

  // Produkt-Checksums (Delta-Sync Verwaltung)
  checksums(connectionId, params = {}) {
    return client.get(`/connectors/connections/${connectionId}/checksums`, { params })
  },

  clearChecksums(connectionId) {
    return client.delete(`/connectors/connections/${connectionId}/checksums`)
  },

  // Sync-Logs
  syncLogs(connectionId, params = {}) {
    return client.get(`/connectors/connections/${connectionId}/sync-logs`, { params })
  },

  clearSyncLogs(connectionId) {
    return client.delete(`/connectors/connections/${connectionId}/sync-logs`)
  },

  deleteSyncLog(connectionId, logId) {
    return client.delete(`/connectors/connections/${connectionId}/sync-logs/${logId}`)
  },

  exportSyncLogs(connectionId, params = {}) {
    return client.get(`/connectors/connections/${connectionId}/sync-logs/export`, {
      params,
      responseType: 'blob',
    })
  },

  // Which plugins have API keys configured (available to all authenticated users)
  configuredPlugins() {
    return client.get('/settings/configured-plugins')
  },

  // Connector Credentials (Admin)
  getCredentials() {
    return client.get('/settings/connector-credentials')
  },

  updateCredentials(data) {
    return client.put('/settings/connector-credentials', data)
  },
}
