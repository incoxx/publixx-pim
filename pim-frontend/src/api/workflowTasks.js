import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/workflow-tasks', { params: buildParams(options) })
  },

  show(id) {
    return client.get(`/workflow-tasks/${id}`)
  },

  create(data) {
    return client.post('/workflow-tasks', data)
  },

  update(id, data) {
    return client.patch(`/workflow-tasks/${id}`, data)
  },

  delete(id) {
    return client.delete(`/workflow-tasks/${id}`)
  },
}
