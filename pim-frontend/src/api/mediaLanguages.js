import client, { buildParams } from './client'

export const mediaLanguages = {
  list(params = {}) {
    return client.get('/media-languages', { params: buildParams(params) })
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
