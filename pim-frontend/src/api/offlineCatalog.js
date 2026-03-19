import client from './client'

export default {
  generate(lang = 'de', templateId = null) {
    const payload = { lang }
    if (templateId) payload.template_id = templateId
    return client.post('/admin/offline-catalog/generate', payload, { timeout: 600000 })
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

  buildBundle() {
    return client.post('/admin/offline-catalog/build-bundle', {}, { timeout: 120000 })
  },

  bundleStatus() {
    return client.get('/admin/offline-catalog/bundle-status')
  },
}
