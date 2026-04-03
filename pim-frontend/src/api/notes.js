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

  forProduct(productId) {
    return client.get(`/products/${productId}/notes`)
  },
}
