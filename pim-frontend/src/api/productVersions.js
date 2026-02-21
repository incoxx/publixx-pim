import client, { buildParams } from './client'

export default {
  list(productId, options = {}) {
    return client.get(`/products/${productId}/versions`, { params: buildParams(options) })
  },

  get(productId, versionId) {
    return client.get(`/products/${productId}/versions/${versionId}`)
  },

  create(productId, data = {}) {
    return client.post(`/products/${productId}/versions`, data)
  },

  activate(productId, versionId) {
    return client.post(`/products/${productId}/versions/${versionId}/activate`)
  },

  schedule(productId, versionId, data) {
    return client.post(`/products/${productId}/versions/${versionId}/schedule`, data)
  },

  cancelSchedule(productId, versionId) {
    return client.post(`/products/${productId}/versions/${versionId}/cancel-schedule`)
  },

  revert(productId, versionId) {
    return client.post(`/products/${productId}/versions/${versionId}/revert`)
  },

  compare(productId, fromId, toId) {
    return client.get(`/products/${productId}/versions/compare`, {
      params: { from: fromId, to: toId },
    })
  },
}
