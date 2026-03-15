import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/workflow-statuses', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/workflow-statuses/${id}`)
  },

  create(data) {
    return client.post('/workflow-statuses', data)
  },

  update(id, data) {
    return client.put(`/workflow-statuses/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/workflow-statuses/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/workflow-statuses/${id}`, { params: force ? { force: true } : {} })
  },
}
