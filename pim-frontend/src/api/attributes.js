import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/attributes', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/attributes/${id}`, { params: buildParams(options) })
  },

  create(data) {
    return client.post('/attributes', data)
  },

  update(id, data) {
    return client.put(`/attributes/${id}`, data)
  },

  delete(id) {
    return client.delete(`/attributes/${id}`)
  },
}

export const attributeTypes = {
  list() {
    return client.get('/attribute-types')
  },

  create(data) {
    return client.post('/attribute-types', data)
  },

  update(id, data) {
    return client.put(`/attribute-types/${id}`, data)
  },

  delete(id) {
    return client.delete(`/attribute-types/${id}`)
  },
}

export const unitGroups = {
  list(options = {}) {
    return client.get('/unit-groups', { params: buildParams(options) })
  },

  get(id) {
    return client.get(`/unit-groups/${id}`)
  },

  create(data) {
    return client.post('/unit-groups', data)
  },

  update(id, data) {
    return client.put(`/unit-groups/${id}`, data)
  },

  delete(id) {
    return client.delete(`/unit-groups/${id}`)
  },

  addUnit(groupId, data) {
    return client.post(`/unit-groups/${groupId}/units`, data)
  },

  updateUnit(unitId, data) {
    return client.put(`/units/${unitId}`, data)
  },

  deleteUnit(unitId) {
    return client.delete(`/units/${unitId}`)
  },
}

export const valueLists = {
  list(options = {}) {
    return client.get('/value-lists', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/value-lists', data)
  },

  update(id, data) {
    return client.put(`/value-lists/${id}`, data)
  },

  delete(id) {
    return client.delete(`/value-lists/${id}`)
  },

  addEntry(listId, data) {
    return client.post(`/value-lists/${listId}/entries`, data)
  },

  updateEntry(entryId, data) {
    return client.put(`/value-list-entries/${entryId}`, data)
  },

  deleteEntry(entryId) {
    return client.delete(`/value-list-entries/${entryId}`)
  },
}

export const attributeViews = {
  list() {
    return client.get('/attribute-views')
  },

  create(data) {
    return client.post('/attribute-views', data)
  },

  update(id, data) {
    return client.put(`/attribute-views/${id}`, data)
  },

  delete(id) {
    return client.delete(`/attribute-views/${id}`)
  },

  addAttribute(viewId, data) {
    return client.post(`/attribute-views/${viewId}/attributes`, data)
  },

  removeAttribute(viewId, attributeId) {
    return client.delete(`/attribute-views/${viewId}/attributes/${attributeId}`)
  },
}

export const productTypes = {
  list() {
    return client.get('/product-types')
  },

  get(id) {
    return client.get(`/product-types/${id}`)
  },

  create(data) {
    return client.post('/product-types', data)
  },

  update(id, data) {
    return client.put(`/product-types/${id}`, data)
  },

  delete(id) {
    return client.delete(`/product-types/${id}`)
  },

  getSchema(id) {
    return client.get(`/product-types/${id}/schema`)
  },
}
