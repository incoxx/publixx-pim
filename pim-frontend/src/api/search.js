import client from './client'

export default {
  /**
   * POST /products/search
   *
   * SQL-based search with LIKE, SOUNDEX, REGEXP support.
   */
  search(params = {}) {
    return client.post('/products/search', params)
  },

  /**
   * POST /products/search/count
   *
   * Return count of products matching search/filter criteria.
   */
  count(params = {}) {
    return client.post('/products/search/count', params)
  },

  /**
   * POST /products/search/ids
   *
   * Return all product IDs matching search/filter criteria.
   */
  allIds(params = {}) {
    return client.post('/products/search/ids', params)
  },

  /**
   * POST /products/bulk-delete
   *
   * Delete multiple products at once (Admin only).
   */
  bulkDelete(productIds) {
    return client.post('/products/bulk-delete', { product_ids: productIds })
  },

  /**
   * GET /products/search/attributes
   *
   * Returns searchable attributes with value list entries.
   */
  searchableAttributes() {
    return client.get('/products/search/attributes')
  },
}
