import client, { buildParams } from './client'

export const priceTypes = {
  list() {
    return client.get('/price-types')
  },
  create(data) {
    return client.post('/price-types', data)
  },
  update(id, data) {
    return client.put(`/price-types/${id}`, data)
  },
  delete(id) {
    return client.delete(`/price-types/${id}`)
  },
}

export const relationTypes = {
  list() {
    return client.get('/relation-types')
  },
  create(data) {
    return client.post('/relation-types', data)
  },
  update(id, data) {
    return client.put(`/relation-types/${id}`, data)
  },
  delete(id) {
    return client.delete(`/relation-types/${id}`)
  },
}

export default { priceTypes, relationTypes }
