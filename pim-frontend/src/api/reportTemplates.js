import client from './client'

export default {
  list() {
    return client.get('/report-templates')
  },

  get(id) {
    return client.get(`/report-templates/${id}`)
  },

  create(data) {
    return client.post('/report-templates', data)
  },

  update(id, data) {
    return client.put(`/report-templates/${id}`, data)
  },

  remove(id) {
    return client.delete(`/report-templates/${id}`)
  },

  fields() {
    return client.get('/report-templates/fields')
  },

  execute(id, params = {}) {
    return client.post(`/report-templates/${id}/execute`, params, { responseType: 'blob' })
  },

  executeAsync(id, params = {}) {
    return client.post(`/report-templates/${id}/execute`, { ...params, async: true })
  },

  preview(id, params = {}) {
    return client.post(`/report-templates/${id}/preview`, params, { responseType: 'blob' })
  },

  jobStatus(id) {
    return client.get(`/report-jobs/${id}`)
  },

  jobDownload(id) {
    return client.get(`/report-jobs/${id}/download`, { responseType: 'blob' })
  },
}
