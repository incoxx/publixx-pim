import client from './client'

export default {
  preview({ productIds, filter, operations }) {
    const payload = { operations }
    if (filter) {
      payload.filter = filter
    } else {
      payload.product_ids = productIds
    }
    return client.post('/products/bulk-update/preview', payload)
  },

  execute({ productIds, filter, operations }) {
    const payload = { operations }
    if (filter) {
      payload.filter = filter
    } else {
      payload.product_ids = productIds
    }
    return client.put('/products/bulk-update', payload, { timeout: 600000 })
  },

  commonAttributes({ productIds, filter, search, excludeIds }) {
    const payload = {
      search: search || undefined,
      exclude_ids: excludeIds || undefined,
    }
    if (filter) {
      payload.filter = filter
    } else {
      payload.product_ids = productIds
    }
    return client.post('/products/common-attributes', payload)
  },
}
