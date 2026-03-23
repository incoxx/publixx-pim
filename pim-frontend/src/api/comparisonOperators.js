import client, { buildParams } from './client'

export const comparisonOperatorGroups = {
  list(options = {}) {
    return client.get('/comparison-operator-groups', { params: buildParams(options) })
  },
  get(id) {
    return client.get(`/comparison-operator-groups/${id}`, { params: { include: 'operators' } })
  },
  create(data) {
    return client.post('/comparison-operator-groups', data)
  },
  update(id, data) {
    return client.put(`/comparison-operator-groups/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/comparison-operator-groups/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/comparison-operator-groups/${id}`, { params: force ? { force: true } : {} })
  },
}

export const comparisonOperators = {
  list(groupId) {
    return client.get(`/comparison-operator-groups/${groupId}/comparison-operators`)
  },
  create(groupId, data) {
    return client.post(`/comparison-operator-groups/${groupId}/comparison-operators`, data)
  },
  update(operatorId, data) {
    return client.put(`/comparison-operators/${operatorId}`, data)
  },
  dependencies(operatorId) {
    return client.get(`/comparison-operators/${operatorId}/dependencies`)
  },
  delete(operatorId, { force = false } = {}) {
    return client.delete(`/comparison-operators/${operatorId}`, { params: force ? { force: true } : {} })
  },
}
