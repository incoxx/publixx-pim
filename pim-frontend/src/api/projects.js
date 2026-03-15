import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/projects', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/projects/${id}`, { params: { include: 'teams,products,manager,subProjects' } })
  },

  create(data) {
    return client.post('/projects', data)
  },

  update(id, data) {
    return client.put(`/projects/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/projects/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/projects/${id}`, { params: force ? { force: true } : {} })
  },
}
