import client from './client'

export default {
  list(params = {}) {
    // per_page hoch: die Sprachliste ist kurz und wird als Ganzes gebraucht
    return client.get('/languages', { params: { per_page: 200, ...params } })
  },

  create(data) {
    return client.post('/languages', data)
  },

  update(id, data) {
    return client.put(`/languages/${id}`, data)
  },

  remove(id) {
    return client.delete(`/languages/${id}`)
  },
}
