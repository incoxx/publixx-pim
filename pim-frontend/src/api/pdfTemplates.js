import client from './client'

export default {
  list() {
    return client.get('/pdf-templates')
  },

  get(id) {
    return client.get(`/pdf-templates/${id}`)
  },

  create(data) {
    return client.post('/pdf-templates', data)
  },

  update(id, data) {
    return client.put(`/pdf-templates/${id}`, data)
  },

  remove(id) {
    return client.delete(`/pdf-templates/${id}`)
  },

  fields() {
    return client.get('/pdf-templates/fields')
  },

  preview(id, params = {}) {
    return client.post(`/pdf-templates/${id}/preview`, params, { responseType: 'blob' })
  },

  execute(id, params = {}) {
    return client.post(`/pdf-templates/${id}/execute`, params, { responseType: 'blob' })
  },
}
