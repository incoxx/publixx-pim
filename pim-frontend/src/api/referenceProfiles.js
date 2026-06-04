import client from './client'

/**
 * Virtuelle Referenzprodukte (Referenz-Profile) + Konformität.
 */
export const referenceProfiles = {
  list(params = {}) {
    return client.get('/reference-profiles', { params })
  },

  get(id) {
    return client.get(`/reference-profiles/${id}`)
  },

  create(data) {
    return client.post('/reference-profiles', data)
  },

  update(id, data) {
    return client.put(`/reference-profiles/${id}`, data)
  },

  delete(id) {
    return client.delete(`/reference-profiles/${id}`)
  },

  // Regel-Vorschläge aus Musterprodukten ableiten
  suggestRules(productIds, tolerancePercent = 20) {
    return client.post('/reference-profiles/suggest-rules', {
      product_ids: productIds,
      tolerance_percent: tolerancePercent,
    })
  },

  // Batch-Re-Check aller referenzierenden Produkte anstoßen
  recheck(id) {
    return client.post(`/reference-profiles/${id}/recheck`)
  },
}

/**
 * Konformität pro Produkt + aggregierter Report.
 */
export const conformance = {
  show(productId) {
    return client.get(`/products/${productId}/conformance`)
  },

  check(productId) {
    return client.post(`/products/${productId}/conformance/check`)
  },

  assign(productId, referenceProfileId) {
    return client.put(`/products/${productId}/reference-profile`, {
      reference_profile_id: referenceProfileId,
    })
  },

  report(params = {}) {
    return client.get('/conformance/report', { params })
  },
}

export default referenceProfiles
