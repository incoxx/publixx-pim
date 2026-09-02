import client, { buildParams } from './client'

export const tags = {
  list(params = {}) {
    return client.get('/tags', { params: buildParams(params) })
  },
  create(data) {
    return client.post('/tags', data)
  },
  update(id, data) {
    return client.put(`/tags/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/tags/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/tags/${id}`, { params: force ? { force: true } : {} })
  },
  // Tags eines Produkts/Mediums komplett setzen (Reihenfolge = sort_order)
  syncProduct(productId, tagIds) {
    return client.put(`/products/${productId}/tags`, { tag_ids: tagIds })
  },
  syncMedia(mediaId, tagIds) {
    return client.put(`/media/${mediaId}/tags`, { tag_ids: tagIds })
  },
  // Massenzuordnung: mode = add | remove | replace
  bulkAssignProducts(productIds, tagIds, mode = 'add') {
    return client.post('/products/bulk-tags', { product_ids: productIds, tag_ids: tagIds, mode })
  },
}

export default tags
