import client from './client'

export const mediaRenditionPresets = {
  list() {
    return client.get('/media-rendition-presets')
  },
  create(data) {
    return client.post('/media-rendition-presets', data)
  },
  update(id, data) {
    return client.put(`/media-rendition-presets/${id}`, data)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/media-rendition-presets/${id}`, { params: force ? { force: true } : {} })
  },
}

export default mediaRenditionPresets
