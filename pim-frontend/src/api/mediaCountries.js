import client from './client'

export const mediaCountries = {
  list() {
    return client.get('/media-countries')
  },
  create(data) {
    return client.post('/media-countries', data)
  },
  update(id, data) {
    return client.put(`/media-countries/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/media-countries/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/media-countries/${id}`, { params: force ? { force: true } : {} })
  },
}

export default mediaCountries
