import client from './client'

export default {
  list() {
    return client.get('/website-profiles')
  },

  create(data) {
    return client.post('/website-profiles', data)
  },

  update(id, data) {
    return client.put(`/website-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/website-profiles/${id}`)
  },

  activate(id) {
    return client.post(`/website-profiles/${id}/activate`)
  },
}
