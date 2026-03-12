import client from './client'

export default {
  list(params = {}) {
    return client.get('/scheduled-actions', { params })
  },

  create(data) {
    return client.post('/scheduled-actions', data)
  },

  show(id) {
    return client.get(`/scheduled-actions/${id}`)
  },

  update(id, data) {
    return client.put(`/scheduled-actions/${id}`, data)
  },

  destroy(id) {
    return client.delete(`/scheduled-actions/${id}`)
  },

  forProduct(productId, params = {}) {
    return client.get(`/products/${productId}/scheduled-actions`, { params })
  },
}
