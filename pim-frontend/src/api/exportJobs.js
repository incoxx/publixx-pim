import client from './client'

export default {
  list() {
    return client.get('/export-jobs')
  },

  get(id) {
    return client.get(`/export-jobs/${id}`)
  },

  create(data) {
    return client.post('/export-jobs', data)
  },

  update(id, data) {
    return client.put(`/export-jobs/${id}`, data)
  },

  remove(id) {
    return client.delete(`/export-jobs/${id}`)
  },

  execute(id, { async = false } = {}) {
    return client.post(`/export-jobs/${id}/execute`, { async })
  },

  download(id) {
    return client.get(`/export-jobs/${id}/download`, { responseType: 'blob' })
  },
}
