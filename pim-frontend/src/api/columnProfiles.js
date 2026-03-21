import client from './client'

export default {
  list(context) {
    return client.get('/column-profiles', { params: context ? { context } : {} })
  },

  create(data) {
    return client.post('/column-profiles', data)
  },

  update(id, data) {
    return client.put(`/column-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/column-profiles/${id}`)
  },
}
