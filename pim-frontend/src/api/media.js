import client, { buildParams } from './client'

const base = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export default {
  list(options = {}) {
    return client.get('/media', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/media/${id}`)
  },

  upload(file, metadata = {}) {
    const formData = new FormData()
    formData.append('file', file)
    for (const [key, val] of Object.entries(metadata)) {
      if (val != null) formData.append(key, val)
    }
    return client.post('/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  update(id, data) {
    return client.put(`/media/${id}`, data)
  },

  delete(id) {
    return client.delete(`/media/${id}`)
  },

  getAttributeValues(mediaId, params = {}) {
    return client.get(`/media/${mediaId}/attribute-values`, { params: buildParams(params) })
  },

  updateAttributeValues(mediaId, values) {
    return client.put(`/media/${mediaId}/attribute-values`, { values })
  },

  fileUrl(filename) {
    return `${base}/media/file/${filename}`
  },

  thumbUrl(mediaId, w = 300, h = 300) {
    return `${base}/media/thumb/${mediaId}?w=${w}&h=${h}`
  },
}
