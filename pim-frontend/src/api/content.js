import client, { buildParams } from './client'

export default {
  // ─── Content-Seiten ─────────────────────────────────────────────
  list(options = {}) {
    return client.get('/content-pages', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/content-pages/${id}`, { params: buildParams(options) })
  },

  search(options = {}) {
    return client.get('/content-pages-search', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/content-pages', data)
  },

  update(id, data) {
    return client.put(`/content-pages/${id}`, data)
  },

  delete(id) {
    return client.delete(`/content-pages/${id}`)
  },

  duplicate(id) {
    return client.post(`/content-pages/${id}/duplicate`)
  },

  pasteSection(pageId, sourceSectionId) {
    return client.post(`/content-pages/${pageId}/paste-section`, { source_section_id: sourceSectionId })
  },

  // ─── Sektionen (Bausteine) einer Seite ──────────────────────────
  createSection(pageId, data) {
    return client.post(`/content-pages/${pageId}/sections`, data)
  },

  updateSection(sectionId, data) {
    return client.put(`/content-sections/${sectionId}`, data)
  },

  moveSection(sectionId, data) {
    return client.put(`/content-sections/${sectionId}/move`, data)
  },

  deleteSection(sectionId) {
    return client.delete(`/content-sections/${sectionId}`)
  },

  // ─── Seitentypen ────────────────────────────────────────────────
  listTypes(options = {}) {
    return client.get('/content-types', { params: buildParams(options) })
  },

  createType(data) {
    return client.post('/content-types', data)
  },

  updateType(id, data) {
    return client.put(`/content-types/${id}`, data)
  },

  deleteType(id, { force = false } = {}) {
    return client.delete(`/content-types/${id}`, { params: force ? { force: true } : {} })
  },

  // ─── Sektionstypen ──────────────────────────────────────────────
  listSectionTypes(options = {}) {
    return client.get('/section-types', { params: buildParams(options) })
  },

  createSectionType(data) {
    return client.post('/section-types', data)
  },

  updateSectionType(id, data) {
    return client.put(`/section-types/${id}`, data)
  },

  deleteSectionType(id) {
    return client.delete(`/section-types/${id}`)
  },

  // ─── Konfigurations-Export/-Import (JSON-Bundle) ─────────────────
  exportConfig(sections = []) {
    return client.get('/content-config/export', {
      params: sections.length ? { sections: sections.join(',') } : {},
      responseType: 'blob',
    })
  },

  importConfig(bundle, sections = []) {
    return client.post('/content-config/import', {
      bundle,
      ...(sections.length ? { sections: sections.join(',') } : {}),
    })
  },

  // ─── Cache-Status & -Steuerung ──────────────────────────────────
  cacheStats() {
    return client.get('/content-cache/stats')
  },

  clearCache(payload = {}) {
    return client.post('/content-cache/clear', payload)
  },
}
