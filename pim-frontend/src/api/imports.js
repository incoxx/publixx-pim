import client from './client'

export default {
  upload(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/imports', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  getStatus(id) {
    return client.get(`/imports/${id}`)
  },

  getPreview(id) {
    return client.get(`/imports/${id}/preview`)
  },

  execute(id, params = {}) {
    return client.post(`/imports/${id}/execute`, params)
  },

  getResult(id) {
    return client.get(`/imports/${id}/result`)
  },

  downloadTemplate(type) {
    return client.get(`/imports/templates/${type}`, { responseType: 'blob' })
  },

  cancel(id) {
    return client.delete(`/imports/${id}`)
  },

  getLogs(id, params = {}) {
    return client.get(`/imports/${id}/logs`, { params })
  },

  downloadErrors(id) {
    return client.get(`/imports/${id}/errors/download`, { responseType: 'blob' })
  },
}
