import client, { buildParams } from './client'

const base = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export default {
  list(options = {}) {
    return client.get('/media', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/media/${id}`)
  },

  upload(file, metadata = {}, onProgress = null) {
    const formData = new FormData()
    formData.append('file', file)
    for (const [key, val] of Object.entries(metadata)) {
      if (val != null) formData.append(key, val)
    }
    return client.post('/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: onProgress
        ? (e) => onProgress({ loaded: e.loaded, total: e.total, percent: Math.round((e.loaded / (e.total || 1)) * 100) })
        : undefined,
    })
  },

  update(id, data) {
    return client.put(`/media/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/media/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/media/${id}`, { params: force ? { force: true } : {} })
  },

  bulkDelete(mediaIds, { force = false } = {}) {
    return client.post('/media/bulk-delete', { media_ids: mediaIds, force })
  },

  revisions(mediaId) {
    return client.get(`/media/${mediaId}/revisions`)
  },

  importFromUrl(url, options = {}) {
    return client.post('/media/import-url', { url, ...options })
  },

  bulkImportFromUrls(file, options = {}) {
    const formData = new FormData()
    formData.append('file', file)
    for (const [key, val] of Object.entries(options)) {
      if (val != null) formData.append(key, val)
    }
    return client.post('/media/bulk-import-urls', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  autoMatch(pattern, options = {}) {
    return client.post('/media/auto-match', { pattern, ...options })
  },

  bulkMove(mediaIds, assetFolderId) {
    return client.post('/media/bulk-move', {
      media_ids: mediaIds,
      asset_folder_id: assetFolderId,
    })
  },

  getAttributeValues(mediaId, params = {}) {
    return client.get(`/media/${mediaId}/attribute-values`, { params: buildParams(params) })
  },

  updateAttributeValues(mediaId, values) {
    return client.put(`/media/${mediaId}/attribute-values`, { values })
  },

  fileUrl(filename) {
    return `${base}/media/file/${encodeURIComponent(filename)}`
  },

  thumbUrl(mediaId, w = 300, h = 300) {
    return `${base}/media/thumb/${mediaId}?w=${w}&h=${h}`
  },
}
