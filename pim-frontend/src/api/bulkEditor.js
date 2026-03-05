import client from './client'

export default {
  /**
   * POST /products/bulk-edit
   * Load current attribute values for multiple products.
   */
  load({ productIds, attributeIds, language }) {
    return client.post('/products/bulk-edit', {
      product_ids: productIds,
      attribute_ids: attributeIds || undefined,
      language: language || 'de',
    })
  },

  /**
   * PUT /products/bulk-edit
   * Save changed values across products.
   */
  save(changes) {
    return client.put('/products/bulk-edit', { changes })
  },
}
