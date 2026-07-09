import client, { buildParams } from './client'

export const collectionTypes = {
  list(options = {}) {
    return client.get('/collection-types', { params: buildParams(options) })
  },
  get(id, options = {}) {
    return client.get(`/collection-types/${id}`, { params: buildParams(options) })
  },
  create(data) {
    return client.post('/collection-types', data)
  },
  update(id, data) {
    return client.put(`/collection-types/${id}`, data)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/collection-types/${id}`, { params: force ? { force: true } : {} })
  },
}

export const collections = {
  list(options = {}) {
    return client.get('/collections', { params: buildParams(options) })
  },
  get(id, options = {}) {
    return client.get(`/collections/${id}`, { params: buildParams(options) })
  },
  create(data) {
    return client.post('/collections', data)
  },
  update(id, data) {
    return client.put(`/collections/${id}`, data)
  },
  delete(id) {
    return client.delete(`/collections/${id}`)
  },
  getAttributeValues(id) {
    return client.get(`/collections/${id}/attribute-values`)
  },
  saveAttributeValues(id, values) {
    return client.put(`/collections/${id}/attribute-values`, { values })
  },
  import(formData) {
    // @see api/bmecatImport.js, api/excelTemplates.js -- gleiches Muster fuer Multipart-Uploads
    // in diesem Repo. client.js setzt global Content-Type: application/json, das ueberschreibt
    // axios' FormData-Auto-Erkennung; muss pro Request explizit ueberschrieben werden.
    return client.post('/collections/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}

// @see api/reportTemplates.js -- identisches Job-ID/Poll/Download-Muster. jobDownload() geht
// bewusst ueber eine authentifizierte Axios-Blob-Anfrage, nicht ueber eine rohe <a href>-URL --
// die API nutzt Bearer-Token-Auth (kein Cookie), ein direkter Link haette keinen Auth-Header.
export const collectionRenders = {
  execute(collectionId, { format = 'pdf' } = {}) {
    return client.post(`/collections/${collectionId}/render`, { format }, { responseType: 'blob' })
  },
  executeAsync(collectionId, { format = 'pdf' } = {}) {
    return client.post(`/collections/${collectionId}/render`, { format, async: true })
  },
  preview(collectionId, { format = 'pdf' } = {}) {
    return client.post(`/collections/${collectionId}/render/preview`, { format }, { responseType: 'blob' })
  },
  jobStatus(jobId) {
    return client.get(`/collection-render-jobs/${jobId}`)
  },
  jobDownload(jobId) {
    return client.get(`/collection-render-jobs/${jobId}/download`, { responseType: 'blob' })
  },
}

export const collectionItems = {
  list(collectionId, options = {}) {
    return client.get(`/collections/${collectionId}/items`, { params: buildParams(options) })
  },
  get(collectionId, itemId) {
    return client.get(`/collections/${collectionId}/items/${itemId}`)
  },
  create(collectionId, data) {
    return client.post(`/collections/${collectionId}/items`, data)
  },
  // @see api/projects.js::bulkAddProducts -- gleiches Muster fuer Massen-Zuordnung.
  bulkAdd(collectionId, productIds) {
    return client.post(`/collections/${collectionId}/items/bulk`, { product_ids: productIds })
  },
  update(collectionId, itemId, data) {
    return client.put(`/collections/${collectionId}/items/${itemId}`, data)
  },
  delete(collectionId, itemId) {
    return client.delete(`/collections/${collectionId}/items/${itemId}`)
  },
  reorder(collectionId, itemIds) {
    return client.patch(`/collections/${collectionId}/items/reorder`, { item_ids: itemIds })
  },
  getAttributeValues(collectionId, itemId) {
    return client.get(`/collections/${collectionId}/items/${itemId}/attribute-values`)
  },
  saveAttributeValues(collectionId, itemId, values) {
    return client.put(`/collections/${collectionId}/items/${itemId}/attribute-values`, { values })
  },
  needsReview(collectionId) {
    return client.get(`/collections/${collectionId}/items/needs-review`)
  },
  confirmMatch(collectionId, itemId, productId) {
    return client.post(`/collections/${collectionId}/items/${itemId}/confirm-match`, { product_id: productId })
  },
}
