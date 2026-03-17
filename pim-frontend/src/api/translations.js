import client from './client'

export default {
  getUnits(params) {
    return client.get('/tms/units', { params })
  },

  getUnit(id) {
    return client.get(`/tms/units/${id}`)
  },

  updateTranslation(unitId, lang, data) {
    return client.put(`/tms/units/${unitId}/translations/${lang}`, data)
  },

  getStats() {
    return client.get('/tms/stats')
  },

  getMissing(params) {
    return client.get('/tms/missing', { params })
  },

  retranslate(data) {
    return client.post('/tms/retranslate', data)
  },

  triggerIngest() {
    return client.post('/tms/ingest')
  },

  syncToDatabase() {
    return client.post('/tms/sync')
  },

  deleteAllTranslations(targetLang = null) {
    const params = targetLang ? { target_lang: targetLang } : {}
    return client.delete('/tms/translations', { params })
  },
}
