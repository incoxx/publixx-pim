import client, { buildParams } from './client'

export const mediaMotifs = {
  list(options = {}) {
    return client.get('/media-motifs', { params: buildParams(options) })
  },
  get(id) {
    return client.get(`/media-motifs/${id}`)
  },
  promote(mediaId, metadata = {}) {
    return client.post('/media-motifs', { media_id: mediaId, ...metadata })
  },
  update(id, data) {
    return client.put(`/media-motifs/${id}`, data)
  },
  delete(id) {
    return client.delete(`/media-motifs/${id}`)
  },
  generateRenditions(id, presetIds = null) {
    return client.post(`/media-motifs/${id}/generate-renditions`, presetIds ? { preset_ids: presetIds } : {})
  },
}

export default mediaMotifs
