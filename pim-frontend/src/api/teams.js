import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/teams', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/teams/${id}`, { params: { include: 'users' } })
  },

  create(data) {
    return client.post('/teams', data)
  },

  update(id, data) {
    return client.put(`/teams/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/teams/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/teams/${id}`, { params: force ? { force: true } : {} })
  },
}
