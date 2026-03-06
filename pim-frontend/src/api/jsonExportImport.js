import client from './client'

export default {
  // --- JSON Export ---

  /**
   * Vollexport als JSON-Download.
   */
  exportAll(params = {}) {
    return client.get('/json-export', {
      params,
      responseType: 'blob',
    })
  },

  /**
   * Gefilterter Export (Download oder inline).
   */
  exportFiltered(data) {
    return client.post('/json-export', data, {
      responseType: data.inline ? 'json' : 'blob',
    })
  },

  /**
   * Verfügbare Sektionen auflisten.
   */
  sections() {
    return client.get('/json-export/sections')
  },

  // --- JSON Import ---

  /**
   * JSON-Datei importieren.
   */
  importFile(file, mode = 'update') {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)
    return client.post('/json-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    })
  },

  /**
   * JSON-Daten direkt importieren.
   */
  importData(data, mode = 'update') {
    return client.post(`/json-import?mode=${mode}`, data, {
      timeout: 120000,
    })
  },

  /**
   * JSON-Datei validieren (ohne Import).
   */
  validateFile(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/json-import/validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
