import client from './client'

export default {
  list() {
    return client.get('/catalog-templates')
  },

  get(id) {
    return client.get(`/catalog-templates/${id}`)
  },

  create(data) {
    return client.post('/catalog-templates', data)
  },

  update(id, data) {
    return client.put(`/catalog-templates/${id}`, data)
  },

  remove(id) {
    return client.delete(`/catalog-templates/${id}`)
  },

  duplicate(id) {
    return client.post(`/catalog-templates/${id}/duplicate`)
  },

  preview(id) {
    return client.get(`/catalog-templates/${id}/preview`)
  },

  presets() {
    return client.get('/catalog-templates/presets')
  },
}
