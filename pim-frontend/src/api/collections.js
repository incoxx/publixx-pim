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
}
