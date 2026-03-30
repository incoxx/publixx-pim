import catalogClient from './catalogClient'

export default {
  getSettings: () => catalogClient.get('/document-portal/settings'),
  getCountries: () => catalogClient.get('/document-portal/countries'),
  search: (params) => catalogClient.get('/document-portal/search', { params }),
  getProductDocuments: (id, params) =>
    catalogClient.get(`/document-portal/products/${id}/documents`, { params }),
}
