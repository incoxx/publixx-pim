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

  dependencies(id) {
    return client.get(`/pxf-templates/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/pxf-templates/${id}`, { params: force ? { force: true } : {} })
  },

  preview(templateId, productId) {
    return client.get(`/pxf-templates/${templateId}/preview/${productId}`)
  },

  importPxf(name, pxfData) {
    return client.post('/pxf-templates/import', { name, pxf_data: pxfData })
  },
}
