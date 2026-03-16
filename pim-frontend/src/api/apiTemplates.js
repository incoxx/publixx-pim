import client from './client'

export default {
  list() {
    return client.get('/api-templates')
  },

  get(id) {
    return client.get(`/api-templates/${id}`)
  },

  create(data) {
    return client.post('/api-templates', data)
  },

  update(id, data) {
    return client.put(`/api-templates/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/api-templates/${id}/dependencies`)
  },

  remove(id, { force = false } = {}) {
    return client.delete(`/api-templates/${id}`, { params: force ? { force: true } : {} })
  },

  fields() {
    return client.get('/api-templates/fields')
  },

  preview(id, params = {}) {
    return client.post(`/api-templates/${id}/preview`, params)
  },

  regenerateKey(id) {
    return client.post(`/api-templates/${id}/regenerate-key`)
  },
}
