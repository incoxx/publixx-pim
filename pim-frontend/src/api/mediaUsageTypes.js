import client from './client'

export const mediaUsageTypes = {
  list() {
    return client.get('/media-usage-types')
  },
  create(data) {
    return client.post('/media-usage-types', data)
  },
  update(id, data) {
    return client.put(`/media-usage-types/${id}`, data)
  },
  delete(id) {
    return client.delete(`/media-usage-types/${id}`)
  },
}

export default mediaUsageTypes
