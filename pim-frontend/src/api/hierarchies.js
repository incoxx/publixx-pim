import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/hierarchies', { params: buildParams(options) })
  },

  searchNodes(options = {}) {
    return client.get('/hierarchy-nodes', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/hierarchies', data)
  },

  update(id, data) {
    return client.put(`/hierarchies/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/hierarchies/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/hierarchies/${id}`, { params: force ? { force: true } : {} })
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

  dependenciesNode(nodeId) {
    return client.get(`/hierarchy-nodes/${nodeId}/dependencies`)
  },

  deleteNode(nodeId, { force = false } = {}) {
    return client.delete(`/hierarchy-nodes/${nodeId}`, { params: force ? { force: true } : {} })
  },

  moveNode(nodeId, data) {
    return client.put(`/hierarchy-nodes/${nodeId}/move`, data)
  },

  duplicateNode(nodeId) {
    return client.post(`/hierarchy-nodes/${nodeId}/duplicate`)
  },

  // All unique attributes across all nodes of a hierarchy
  allNodeAttributes(hierarchyId) {
    return client.get(`/hierarchies/${hierarchyId}/all-node-attributes`)
  },

  // Hierarchy-level attribute assignments
  getHierarchyAttributes(hierarchyId, options = {}) {
    return client.get(`/hierarchies/${hierarchyId}/attributes`, { params: buildParams(options) })
  },

  assignHierarchyAttribute(hierarchyId, data) {
    return client.post(`/hierarchies/${hierarchyId}/attributes`, data)
  },

  updateHierarchyAttribute(assignmentId, data) {
    return client.patch(`/hierarchy-attribute-assignments/${assignmentId}`, data)
  },

  removeHierarchyAttribute(assignmentId) {
    return client.delete(`/hierarchy-attribute-assignments/${assignmentId}`)
  },

  // Node attribute values
  getNodeAttributeValues(nodeId, options = {}) {
    return client.get(`/hierarchy-nodes/${nodeId}/attribute-values`, { params: buildParams(options) })
  },

  updateNodeAttributeValues(nodeId, values) {
    return client.put(`/hierarchy-nodes/${nodeId}/attribute-values`, { values })
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

  // Output hierarchy product assignments
  getOutputProducts(nodeId, options = {}) {
    return client.get(`/hierarchy-nodes/${nodeId}/output-products`, { params: buildParams(options) })
  },

  assignOutputProduct(nodeId, data) {
    return client.post(`/hierarchy-nodes/${nodeId}/output-products`, data)
  },

  removeOutputProductAssignment(assignmentId) {
    return client.delete(`/output-hierarchy-product-assignments/${assignmentId}`)
  },

  getRelationshipAttributes(assignmentId, options = {}) {
    return client.get(`/output-hierarchy-product-assignments/${assignmentId}/relationship-attributes`, { params: buildParams(options) })
  },

  saveRelationshipAttributes(assignmentId, values) {
    return client.put(`/output-hierarchy-product-assignments/${assignmentId}/relationship-attributes`, { values })
  },

  sortOutputProducts(nodeId, items) {
    return client.put(`/hierarchy-nodes/${nodeId}/output-products/sort`, { items })
  },

  bulkAssignOutputProducts(nodeId, productIds) {
    return client.post(`/hierarchy-nodes/${nodeId}/output-products/bulk-assign`, { product_ids: productIds })
  },

  bulkAssignMasterProducts(nodeId, productIds) {
    return client.post(`/hierarchy-nodes/${nodeId}/master-products/bulk-assign`, { product_ids: productIds })
  },

  // Master hierarchy product assignments
  assignMasterProduct(nodeId, data) {
    return client.post(`/hierarchy-nodes/${nodeId}/master-products`, data)
  },

  removeMasterProduct(nodeId, productId) {
    return client.delete(`/hierarchy-nodes/${nodeId}/master-products/${productId}`)
  },

  // Node media assignments
  getNodeMedia(nodeId, options = {}) {
    return client.get(`/hierarchy-nodes/${nodeId}/media`, { params: buildParams(options) })
  },

  assignNodeMedia(nodeId, data) {
    return client.post(`/hierarchy-nodes/${nodeId}/media`, data)
  },

  removeNodeMedia(assignmentId) {
    return client.delete(`/hierarchy-node-media/${assignmentId}`)
  },
}
