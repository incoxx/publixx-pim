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

  dependencies(id) {
    return client.get(`/attributes/${id}/dependencies`)
  },

  copy(id) {
    return client.post(`/attributes/${id}/copy`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/attributes/${id}`, { params: force ? { force: true } : {} })
  },

  bulkUpdate(ids, fields) {
    return client.put('/attributes/bulk-update', { ids, fields })
  },

  allIds(params = {}) {
    return client.post('/attributes/all-ids', params)
  },

  migrateLanguage(id, targetLanguage) {
    return client.post(`/attributes/${id}/migrate-language`, { target_language: targetLanguage })
  },

  bulkDelete(attributeIds) {
    return client.post('/attributes/bulk-delete', { attribute_ids: attributeIds })
  },

  bulkAssignViews(ids, mode, attributeViewIds) {
    return client.post('/attributes/bulk-assign-views', {
      ids,
      mode,
      attribute_view_ids: attributeViewIds,
    })
  },

  listSearchable() {
    return client.get('/attributes', {
      params: buildParams({
        filters: { is_searchable: true, is_internal: false, status: 'active' },
        perPage: 200,
      }),
    })
  },

  listVariantAttributes() {
    return client.get('/attributes', {
      params: buildParams({
        filters: { is_variant_attribute: true, status: 'active' },
        perPage: 200,
      }),
    })
  },
}

export const attributeTypes = {
  list(params = {}) {
    return client.get('/attribute-types', { params: buildParams(params) })
  },

  get(id, params = {}) {
    return client.get(`/attribute-types/${id}`, { params })
  },

  create(data) {
    return client.post('/attribute-types', data)
  },

  update(id, data) {
    return client.put(`/attribute-types/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/attribute-types/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/attribute-types/${id}`, { params: force ? { force: true } : {} })
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

  dependencies(id) {
    return client.get(`/unit-groups/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/unit-groups/${id}`, { params: force ? { force: true } : {} })
  },

  addUnit(groupId, data) {
    return client.post(`/unit-groups/${groupId}/units`, data)
  },

  updateUnit(unitId, data) {
    return client.put(`/units/${unitId}`, data)
  },

  dependenciesUnit(unitId) {
    return client.get(`/units/${unitId}/dependencies`)
  },

  deleteUnit(unitId, { force = false } = {}) {
    return client.delete(`/units/${unitId}`, { params: force ? { force: true } : {} })
  },
}

export const valueLists = {
  list(options = {}) {
    return client.get('/value-lists', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/value-lists/${id}`, { params: buildParams(options) })
  },

  create(data) {
    return client.post('/value-lists', data)
  },

  update(id, data) {
    return client.put(`/value-lists/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/value-lists/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/value-lists/${id}`, { params: force ? { force: true } : {} })
  },

  getEntries(listId, options = {}) {
    return client.get(`/value-lists/${listId}/entries`, { params: buildParams(options) })
  },

  addEntry(listId, data) {
    return client.post(`/value-lists/${listId}/entries`, data)
  },

  updateEntry(entryId, data) {
    return client.put(`/value-list-entries/${entryId}`, data)
  },

  dependenciesEntry(entryId) {
    return client.get(`/entries/${entryId}/dependencies`)
  },

  deleteEntry(entryId, { force = false } = {}) {
    return client.delete(`/value-list-entries/${entryId}`, { params: force ? { force: true } : {} })
  },
}

export const formattingRules = {
  list(options = {}) {
    return client.get('/attribute-formatting-rules', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/attribute-formatting-rules/${id}`, { params: buildParams(options) })
  },

  create(data) {
    return client.post('/attribute-formatting-rules', data)
  },

  update(id, data) {
    return client.put(`/attribute-formatting-rules/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/attribute-formatting-rules/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/attribute-formatting-rules/${id}`, { params: force ? { force: true } : {} })
  },
}

export const attributeMetadataDefinitions = {
  list(options = {}) {
    return client.get('/attribute-metadata-definitions', { params: buildParams(options) })
  },

  get(id, options = {}) {
    return client.get(`/attribute-metadata-definitions/${id}`, { params: buildParams(options) })
  },

  create(data) {
    return client.post('/attribute-metadata-definitions', data)
  },

  update(id, data) {
    return client.put(`/attribute-metadata-definitions/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/attribute-metadata-definitions/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/attribute-metadata-definitions/${id}`, { params: force ? { force: true } : {} })
  },
}

export const attributeViews = {
  list(params = {}) {
    return client.get('/attribute-views', { params: buildParams(params) })
  },

  create(data) {
    return client.post('/attribute-views', data)
  },

  update(id, data) {
    return client.put(`/attribute-views/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/attribute-views/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/attribute-views/${id}`, { params: force ? { force: true } : {} })
  },

  addAttribute(viewId, data) {
    return client.post(`/attribute-views/${viewId}/attributes`, data)
  },

  removeAttribute(viewId, attributeId) {
    return client.delete(`/attribute-views/${viewId}/attributes/${attributeId}`)
  },

  updateAssignment(viewId, attributeId, data) {
    return client.patch(`/attribute-views/${viewId}/attributes/${attributeId}`, data)
  },

  reorderAttributes(viewId, attributeIds) {
    return client.put(`/attribute-views/${viewId}/attributes/reorder`, { attribute_ids: attributeIds })
  },
}

export const productTypes = {
  list(params = {}) {
    return client.get('/product-types', { params: buildParams(params) })
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

  dependencies(id) {
    return client.get(`/product-types/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/product-types/${id}`, { params: force ? { force: true } : {} })
  },

  getSchema(id) {
    return client.get(`/product-types/${id}/schema`)
  },
}
