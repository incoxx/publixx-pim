import client, { buildParams } from './client'

export default {
  // ─── Navigationen ───────────────────────────────────────────────
  list(options = {}) {
    return client.get('/navigations', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/navigations', data)
  },

  update(id, data) {
    return client.put(`/navigations/${id}`, data)
  },

  delete(id) {
    return client.delete(`/navigations/${id}`)
  },

  getTree(id) {
    return client.get(`/navigations/${id}/tree`)
  },

  // ─── Knoten ─────────────────────────────────────────────────────
  createNode(navigationId, data) {
    return client.post(`/navigations/${navigationId}/nodes`, data)
  },

  updateNode(nodeId, data) {
    return client.put(`/navigation-nodes/${nodeId}`, data)
  },

  moveNode(nodeId, data) {
    return client.put(`/navigation-nodes/${nodeId}/move`, data)
  },

  deleteNode(nodeId) {
    return client.delete(`/navigation-nodes/${nodeId}`)
  },
}
