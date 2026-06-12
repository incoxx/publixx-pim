import client from './client'

export default {
  /**
   * POST /semantic-search
   *
   * Hybrid-Suche (Keyword + Vektor) mit deterministischer Constraint-Extraktion.
   * Gibt gerankten Treffer-Array + angewandte Constraints zurück.
   *
   * @param {Object} body - { q, filters, facets, limit, offset, mode, semantic_ratio }
   * @param {AbortSignal} [signal]
   */
  search(body = {}, signal = undefined) {
    return client.post('/semantic-search', body, { signal })
  },

  /**
   * GET /semantic-search/health
   *
   * Prüft ob Meilisearch erreichbar und der Embedder konfiguriert ist.
   */
  health() {
    return client.get('/semantic-search/health')
  },
}
