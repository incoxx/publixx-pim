import client, { buildParams } from './client'

export const unitGroups = {
  list(options = {}) {
    return client.get('/unit-groups', { params: buildParams(options) })
  },
  get(id) {
    return client.get(`/unit-groups/${id}`, { params: { include: 'units' } })
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
}

export const units = {
  list(groupId) {
    return client.get(`/unit-groups/${groupId}/units`)
  },
  create(groupId, data) {
    return client.post(`/unit-groups/${groupId}/units`, data)
  },
  update(unitId, data) {
    return client.put(`/units/${unitId}`, data)
  },
  dependencies(unitId) {
    return client.get(`/units/${unitId}/dependencies`)
  },
  delete(unitId, { force = false } = {}) {
    return client.delete(`/units/${unitId}`, { params: force ? { force: true } : {} })
  },
}
