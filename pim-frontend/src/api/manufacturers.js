import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/manufacturers', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/manufacturers/${id}`)
  },

  create(data) {
    return client.post('/manufacturers', data)
  },

  update(id, data) {
    return client.put(`/manufacturers/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/manufacturers/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/manufacturers/${id}`, { params: force ? { force: true } : {} })
  },
}
