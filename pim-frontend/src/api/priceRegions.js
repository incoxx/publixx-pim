import client from './client'

export const priceRegions = {
  list() {
    return client.get('/price-regions')
  },
  create(data) {
    return client.post('/price-regions', data)
  },
  update(id, data) {
    return client.put(`/price-regions/${id}`, data)
  },
  dependencies(id) {
    return client.get(`/price-regions/${id}/dependencies`)
  },
  delete(id, { force = false } = {}) {
    return client.delete(`/price-regions/${id}`, { params: force ? { force: true } : {} })
  },
}

export default priceRegions
