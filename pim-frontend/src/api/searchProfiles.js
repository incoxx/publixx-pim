import client from './client'

export default {
  list() {
    return client.get('/search-profiles')
  },

  create(data) {
    return client.post('/search-profiles', data)
  },

  update(id, data) {
    return client.put(`/search-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/search-profiles/${id}`)
  },
}
