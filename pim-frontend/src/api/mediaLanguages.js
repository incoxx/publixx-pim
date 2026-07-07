import client from './client'

export const mediaLanguages = {
  list() {
    return client.get('/media-languages')
  },
  create(data) {
    return client.post('/media-languages', data)
  },
  update(id, data) {
    return client.put(`/media-languages/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/media-languages/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/media-languages/${id}`, { params: force ? { force: true } : {} })
  },
}

export default mediaLanguages
