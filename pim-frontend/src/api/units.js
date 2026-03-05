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
  delete(id) {
    return client.delete(`/unit-groups/${id}`)
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
  delete(unitId) {
    return client.delete(`/units/${unitId}`)
  },
}
