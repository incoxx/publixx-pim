import client, { buildParams } from './client'

export default {
  // Wirksame Konfiguration + schreibgeschützte Referenzdaten laden.
  show() {
    return client.get('/admin/security-guard')
  },

  // Änderbare Guard-Einstellungen speichern.
  update(payload) {
    return client.put('/admin/security-guard', payload)
  },

  // Erkannte Security-Events (paginiert, filterbar).
  events(options = {}) {
    return client.get('/admin/security-guard/events', { params: buildParams(options) })
  },

  // Aggregierte Kennzahlen der letzten 24 Stunden.
  stats() {
    return client.get('/admin/security-guard/stats')
  },
}
