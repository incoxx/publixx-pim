import client from './client'

export default {
  list() {
    return client.get('/watchlist')
  },

  add(productId, note = null) {
    return client.post('/watchlist', { product_id: productId, note })
  },

  bulkAdd(productIds) {
    return client.post('/watchlist/bulk', { product_ids: productIds })
  },

  remove(watchlistItemId) {
    return client.delete(`/watchlist/${watchlistItemId}`)
  },

  removeByProduct(productId) {
    return client.delete(`/watchlist/product/${productId}`)
  },

  productIds() {
    return client.get('/watchlist/product-ids')
  },

  exportExcel(lang = 'de') {
    return client.get('/watchlist/export/excel', { params: { lang }, responseType: 'blob' })
  },

  exportPdf(lang = 'de') {
    return client.get('/watchlist/export/pdf', { params: { lang }, responseType: 'blob' })
  },

  exportPdfZip(lang = 'de') {
    return client.get('/watchlist/export/pdf-zip', { params: { lang }, responseType: 'blob' })
  },

  exportXliff(sourceLang, targetLang) {
    return client.get('/watchlist/export/xliff', {
      params: { source_lang: sourceLang, target_lang: targetLang },
      responseType: 'blob',
    })
  },
}
