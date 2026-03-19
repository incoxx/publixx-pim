import client from './client'

export default {
  generate(lang = 'de') {
    return client.post('/admin/offline-catalog/generate', { lang }, { timeout: 600000 })
  },

  progress() {
    return client.get('/admin/offline-catalog/progress')
  },

  cancel() {
    return client.post('/admin/offline-catalog/cancel')
  },

  download() {
    return client.get('/admin/offline-catalog/download', { responseType: 'blob' })
  },
}
