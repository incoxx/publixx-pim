import client from './client'

export default {
  /**
   * GET /quick-search
   *
   * Schnellsuche über Produkte, Medien, Hierarchien und Attribute.
   * Params: q, type, limit, category_id, attribute_id, media_id
   *
   * @param {Object} params - Query-Parameter
   * @param {AbortSignal} [signal] - AbortController-Signal für Request-Cancellation
   */
  search(params = {}, signal = undefined) {
    return client.get('/quick-search', { params, signal })
  },
}
