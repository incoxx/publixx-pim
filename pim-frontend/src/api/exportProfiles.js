import client from './client'

export default {
  list() {
    return client.get('/export-profiles')
  },

  create(data) {
    return client.post('/export-profiles', data)
  },

  update(id, data) {
    return client.put(`/export-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/export-profiles/${id}`)
  },

  execute(id, params = {}) {
    return client.post(`/export-profiles/${id}/execute`, params, { responseType: 'blob' })
  },
}
