import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/pxf-templates', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/pxf-templates/${id}`)
  },

  create(data) {
    return client.post('/pxf-templates', data)
  },

  update(id, data) {
    return client.put(`/pxf-templates/${id}`, data)
  },

  delete(id) {
    return client.delete(`/pxf-templates/${id}`)
  },

  preview(templateId, productId) {
    return client.get(`/pxf-templates/${templateId}/preview/${productId}`)
  },

  importPxf(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/pxf-templates/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
