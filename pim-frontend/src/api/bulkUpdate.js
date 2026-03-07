import client from './client'

export default {
  preview({ productIds, operations }) {
    return client.post('/products/bulk-update/preview', {
      product_ids: productIds,
      operations,
    })
  },

  execute({ productIds, operations }) {
    return client.put('/products/bulk-update', {
      product_ids: productIds,
      operations,
    })
  },

  commonAttributes({ productIds, search, excludeIds }) {
    return client.post('/products/common-attributes', {
      product_ids: productIds,
      search: search || undefined,
      exclude_ids: excludeIds || undefined,
    })
  },
}
