import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/hierarchies', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/hierarchies', data)
  },

  update(id, data) {
    return client.put(`/hierarchies/${id}`, data)
  },

  delete(id) {
    return client.delete(`/hierarchies/${id}`)
  },

  getTree(id, options = {}) {
    return client.get(`/hierarchies/${id}/tree`, { params: buildParams(options) })
  },

  getNodes(id, options = {}) {
    return client.get(`/hierarchies/${id}/nodes`, { params: buildParams(options) })
  },

  createNode(hierarchyId, data) {
    return client.post(`/hierarchies/${hierarchyId}/nodes`, data)
  },

  updateNode(nodeId, data) {
    return client.put(`/hierarchy-nodes/${nodeId}`, data)
  },

  deleteNode(nodeId) {
    return client.delete(`/hierarchy-nodes/${nodeId}`)
  },

  moveNode(nodeId, data) {
    return client.put(`/hierarchy-nodes/${nodeId}/move`, data)
  },

  duplicateNode(nodeId) {
    return client.post(`/hierarchy-nodes/${nodeId}/duplicate`)
  },

  // Node attributes
  getNodeAttributes(nodeId, options = {}) {
    return client.get(`/hierarchy-nodes/${nodeId}/attributes`, { params: buildParams(options) })
  },

  assignNodeAttribute(nodeId, data) {
    return client.post(`/hierarchy-nodes/${nodeId}/attributes`, data)
  },

  updateNodeAttributeAssignment(assignmentId, data) {
    return client.put(`/node-attribute-assignments/${assignmentId}`, data)
  },

  removeNodeAttributeAssignment(assignmentId) {
    return client.delete(`/node-attribute-assignments/${assignmentId}`)
  },

  bulkSortAssignments(data) {
    return client.put('/node-attribute-assignments/bulk-sort', data)
  },
}
