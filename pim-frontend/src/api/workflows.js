import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/workflows', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/workflows/${id}`, { params: { include: 'statuses,transitions.fromStatus,transitions.toStatus,startStatus,endStatus' } })
  },

  create(data) {
    return client.post('/workflows', data)
  },

  update(id, data) {
    return client.put(`/workflows/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/workflows/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/workflows/${id}`, { params: force ? { force: true } : {} })
  },
}
