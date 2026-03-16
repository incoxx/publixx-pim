import catalogClient from './catalogClient'

const baseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export default {
  getDocument(id) {
    return catalogClient.get(`/pdf/${id}`)
  },

  getByMedia(mediaId) {
    return catalogClient.get(`/pdf/by-media/${mediaId}`)
  },

  getStatus(id) {
    return catalogClient.get(`/pdf/${id}/status`)
  },

  getPages(id) {
    return catalogClient.get(`/pdf/${id}/pages`)
  },

  getPageUrl(id, pageNumber) {
    return `${baseURL}/pdf/${id}/page/${pageNumber}`
  },

  search(params = {}) {
    return catalogClient.get('/pdf/search', {
      params: {
        q: params.q,
        lang: params.lang,
        per_page: params.perPage || 20,
        page: params.page || 1,
      },
    })
  },

  reprocess(id) {
    return catalogClient.post(`/pdf/${id}/reprocess`)
  },
}
