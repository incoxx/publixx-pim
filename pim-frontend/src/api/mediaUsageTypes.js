import client, { buildParams } from './client'

export const mediaUsageTypes = {
  list(params = {}) {
    return client.get('/media-usage-types', { params: buildParams(params) })
  },
  create(data) {
    return client.post('/media-usage-types', data)
  },
  update(id, data) {
    return client.put(`/media-usage-types/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/media-usage-types/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/media-usage-types/${id}`, { params: force ? { force: true } : {} })
  },
  updateDefaultAttributes(id, attributes) {
    return client.put(`/media-usage-types/${id}/default-attributes`, { attributes })
  },
}

export default mediaUsageTypes
