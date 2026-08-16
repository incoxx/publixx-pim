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

  // Terminologie-Angaben: nicht uebersetzen, Bedeutung, Wortart, Synonym
  updateUnitMetadata(unitId, data) {
    return client.patch(`/tms/units/${unitId}`, data)
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

  // Rollt eine Zielsprache aus: plant alle fehlenden Begriffe ein.
  // Gedeckelt — die Antwort enthaelt `remaining`, der Aufrufer schleift.
  translateMissing(lang, limit = 1000) {
    return client.post('/tms/translate-missing', { lang, limit })
  },

  // Glossar-Austausch ohne MT-Anbieter: Datei raus, uebersetzt wieder rein
  glossaryExportUrl(params) {
    const qs = new URLSearchParams(params).toString()
    return `/tms/glossary/export?${qs}`
  },

  importGlossary(file) {
    const form = new FormData()
    form.append('file', file)
    return client.post('/tms/glossary/import', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
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

  purgeAllUnits() {
    return client.delete('/tms/units')
  },
}
