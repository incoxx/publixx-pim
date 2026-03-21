import client from './client'

export default {
  list(params = {}) {
    return client.get('/translation-jobs', { params })
  },

  create(data) {
    return client.post('/translation-jobs', data)
  },

  get(id, params = {}) {
    return client.get(`/translation-jobs/${id}`, { params })
  },

  submit(id) {
    return client.post(`/translation-jobs/${id}/submit`)
  },

  approve(id) {
    return client.post(`/translation-jobs/${id}/approve`)
  },

  cancel(id) {
    return client.post(`/translation-jobs/${id}/cancel`)
  },

  retry(id) {
    return client.post(`/translation-jobs/${id}/retry`)
  },

  remove(id) {
    return client.delete(`/translation-jobs/${id}`)
  },
}
