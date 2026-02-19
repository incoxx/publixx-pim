import client, { buildParams } from './client'

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
      formData.append(key, val)
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

  fileUrl(filename) {
    const base = import.meta.env.VITE_API_BASE_URL || '/api/v1'
    return `${base}/media/file/${filename}`
  },
}
