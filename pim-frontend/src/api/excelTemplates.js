import client from './client'

export default {
  list() {
    return client.get('/excel-templates')
  },

  get(id) {
    return client.get(`/excel-templates/${id}`)
  },

  create(data) {
    return client.post('/excel-templates', data)
  },

  update(id, data) {
    return client.put(`/excel-templates/${id}`, data)
  },

  remove(id, { force = false } = {}) {
    return client.delete(`/excel-templates/${id}`, { params: force ? { force: true } : {} })
  },

  fields() {
    return client.get('/excel-templates/fields')
  },

  preview(id, params = {}) {
    return client.post(`/excel-templates/${id}/preview`, params)
  },

  download(id, params = {}) {
    return client.post(`/excel-templates/${id}/download`, params, { responseType: 'blob' })
  },

  startExport(id, params = {}) {
    return client.post(`/excel-templates/${id}/export`, params)
  },

  exportProgress(exportKey) {
    return client.get(`/excel-templates/export-progress/${exportKey}`)
  },

  cancelExport(exportKey) {
    return client.post(`/excel-templates/export-cancel/${exportKey}`)
  },

  downloadExport(exportKey) {
    return client.get(`/excel-templates/export-download/${exportKey}`, { responseType: 'blob' })
  },

  import(file, name = null) {
    const formData = new FormData()
    formData.append('file', file)
    if (name) {
      formData.append('name', name)
    }
    return client.post('/excel-templates/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
