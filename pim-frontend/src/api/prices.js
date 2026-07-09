import client, { buildParams } from './client'

export const priceTypes = {
  list(params = {}) {
    return client.get('/price-types', { params: buildParams(params) })
  },
  create(data) {
    return client.post('/price-types', data)
  },
  update(id, data) {
    return client.put(`/price-types/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/price-types/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/price-types/${id}`, { params: force ? { force: true } : {} })
  },
}

export const relationTypes = {
  list(params = {}) {
    return client.get('/relation-types', { params: buildParams(params) })
  },
  create(data) {
    return client.post('/relation-types', data)
  },
  update(id, data) {
    return client.put(`/relation-types/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/relation-types/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/relation-types/${id}`, { params: force ? { force: true } : {} })
  },
  updateDefaultAttributes(id, attributes) {
    return client.put(`/relation-types/${id}/default-attributes`, { attributes })
  },
}

export default { priceTypes, relationTypes }
