import client from './client'

export default {
  list() {
    return client.get('/connectors/canva-export-profiles')
  },

  create(data) {
    return client.post('/connectors/canva-export-profiles', data)
  },

  update(id, data) {
    return client.put(`/connectors/canva-export-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/connectors/canva-export-profiles/${id}`)
  },

  execute(id) {
    return client.post(`/connectors/canva-export-profiles/${id}/execute`)
  },
}
