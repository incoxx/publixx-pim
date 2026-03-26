import client from './client'

export default {
  /**
   * GET /quick-search
   *
   * Schnellsuche über Produkte, Medien, Hierarchien und Attribute.
   * Params: q, type, limit, category_id, attribute_id, media_id
   */
  search(params = {}) {
    return client.get('/quick-search', { params })
  },
}
