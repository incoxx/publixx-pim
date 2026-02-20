import client, { buildParams } from './client'

export default {
  exportProducts(options = {}) {
    return client.get('/export/products', { params: buildParams(options) })
  },

  exportSingleProduct(id) {
    return client.get(`/export/products/${id}`)
  },

  bulkExport(data) {
    return client.post('/export/products/bulk', data)
  },

  exportForPublixx(productId) {
    return client.get(`/export/products/${productId}/publixx`)
  },

  exportWithPql(data) {
    return client.post('/export/query', data)
  },

  exportAsImportFormat() {
    return client.get('/imports/export-format', { responseType: 'blob' })
  },
}

export const publixx = {
  getDatasets(mappingId, options = {}) {
    return client.get(`/publixx/datasets/${mappingId}`, { params: buildParams(options) })
  },

  getDataset(mappingId, productId) {
    return client.get(`/publixx/datasets/${mappingId}/${productId}`)
  },

  queryDatasets(mappingId, data) {
    return client.post(`/publixx/datasets/${mappingId}/pql`, data)
  },
}
