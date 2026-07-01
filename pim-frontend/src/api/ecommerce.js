import client from './client'

export const ecommerceCartTypes = {
  list() {
    return client.get('/ecommerce/cart-types')
  },
  create(data) {
    return client.post('/ecommerce/cart-types', data)
  },
  get(id) {
    return client.get(`/ecommerce/cart-types/${id}`)
  },
  update(id, data) {
    return client.patch(`/ecommerce/cart-types/${id}`, data)
  },
  delete(id) {
    return client.delete(`/ecommerce/cart-types/${id}`)
  },
}

export const ecommerceAddressTypes = {
  list() {
    return client.get('/ecommerce/address-types')
  },
  create(data) {
    return client.post('/ecommerce/address-types', data)
  },
  get(id) {
    return client.get(`/ecommerce/address-types/${id}`)
  },
  update(id, data) {
    return client.patch(`/ecommerce/address-types/${id}`, data)
  },
  delete(id) {
    return client.delete(`/ecommerce/address-types/${id}`)
  },
}

export const ecommercePaymentTypes = {
  list() {
    return client.get('/ecommerce/payment-types')
  },
  create(data) {
    return client.post('/ecommerce/payment-types', data)
  },
  get(id) {
    return client.get(`/ecommerce/payment-types/${id}`)
  },
  update(id, data) {
    return client.patch(`/ecommerce/payment-types/${id}`, data)
  },
  delete(id) {
    return client.delete(`/ecommerce/payment-types/${id}`)
  },
}

export const ecommerceOrders = {
  list(params = {}) {
    return client.get('/ecommerce/orders', { params })
  },
  get(id) {
    return client.get(`/ecommerce/orders/${id}`)
  },
  update(id, data) {
    return client.patch(`/ecommerce/orders/${id}`, data)
  },
  retry(id) {
    return client.post(`/ecommerce/orders/${id}/retry`)
  },
}
