import client from './client'

export default {
  list() {
    return client.get('/export-profiles')
  },

  create(data) {
    return client.post('/export-profiles', data)
  },

  update(id, data) {
    return client.put(`/export-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/export-profiles/${id}`)
  },

  execute(id, params = {}) {
    return client.post(`/export-profiles/${id}/execute`, params, { responseType: 'blob' })
  },

  /**
   * Async-Export starten — gibt sofort {export_key} zurück.
   */
  executeAsync(id, params = {}) {
    return client.post(`/export-profiles/${id}/execute-async`, params)
  },

  /**
   * Fortschritt eines laufenden Async-Exports abfragen.
   */
  exportProgress(exportKey) {
    return client.get(`/export-profiles/progress/${exportKey}`)
  },

  /**
   * Laufenden Async-Export abbrechen.
   */
  cancelExport(exportKey) {
    return client.post(`/export-profiles/cancel/${exportKey}`)
  },

  /**
   * Fertige Export-Datei herunterladen.
   */
  downloadExport(exportKey) {
    return client.get(`/export-profiles/download/${exportKey}`, { responseType: 'blob' })
  },
}
