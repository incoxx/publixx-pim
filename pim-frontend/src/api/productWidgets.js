import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/product-widgets', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/product-widgets', data)
  },

  update(id, data) {
    return client.put(`/product-widgets/${id}`, data)
  },

  delete(id) {
    return client.delete(`/product-widgets/${id}`)
  },
}
