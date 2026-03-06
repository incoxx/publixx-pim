import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/dictionary-entries', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/dictionary-entries/${id}`)
  },

  create(data) {
    return client.post('/dictionary-entries', data)
  },

  update(id, data) {
    return client.put(`/dictionary-entries/${id}`, data)
  },

  delete(id) {
    return client.delete(`/dictionary-entries/${id}`)
  },
}
