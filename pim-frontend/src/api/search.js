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
   * GET /products/search/attributes
   *
   * Returns searchable attributes with value list entries.
   */
  searchableAttributes() {
    return client.get('/products/search/attributes')
  },
}
