import client from './client'

export default {
  /**
   * BMEcat-XML-Datei importieren.
   */
  importFile(file, mode = 'update', productType = null) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)
    if (productType) {
      formData.append('product_type', productType)
    }
    return client.post('/bmecat-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 300000,
    })
  },

  /**
   * BMEcat-XML-Datei validieren (ohne Import).
   */
  validateFile(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/bmecat-import/validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  /**
   * PIM-Daten als BMEcat-XML exportieren.
   */
  exportFile(params = {}) {
    return client.post('/bmecat-export', params, {
      responseType: 'blob',
      timeout: 300000,
    })
  },
}
