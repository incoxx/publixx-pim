import client from './client'

export default {
  list() {
    return client.get('/import-profiles')
  },

  create(data) {
    return client.post('/import-profiles', data)
  },

  update(id, data) {
    return client.put(`/import-profiles/${id}`, data)
  },

  remove(id) {
    return client.delete(`/import-profiles/${id}`)
  },

  analyze(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/import-profiles/analyze', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  preview(profileId, file, maxRows = 20) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('max_rows', maxRows)
    return client.post(`/import-profiles/${profileId}/preview`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  autoGenerateAttributes(data) {
    return client.post('/import-profiles/auto-generate-attributes', data)
  },
}
