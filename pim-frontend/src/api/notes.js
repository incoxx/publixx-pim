import client from './client'

export default {
  list() {
    return client.get('/notes')
  },

  create(data) {
    return client.post('/notes', data)
  },

  update(id, data) {
    return client.put(`/notes/${id}`, data)
  },

  remove(id) {
    return client.delete(`/notes/${id}`)
  },

  reorder(order) {
    return client.post('/notes/reorder', { order })
  },

  resolve(id) {
    return client.post(`/notes/${id}/resolve`)
  },

  reopen(id) {
    return client.post(`/notes/${id}/reopen`)
  },

  addComment(id, body) {
    return client.post(`/notes/${id}/comments`, { body })
  },

  forProduct(productId, showDone = false) {
    return client.get(`/products/${productId}/notes`, { params: { show_done: showDone ? 1 : 0 } })
  },

  openCounts(productId) {
    return client.get(`/products/${productId}/notes/open-counts`)
  },
}
