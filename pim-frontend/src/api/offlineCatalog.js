import client from './client'
import { resolveApiUrl } from './client'

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

  /**
   * Returns a direct download URL with auth token as query parameter.
   * This avoids loading the entire ZIP into browser memory via axios blob.
   */
  downloadUrl(token) {
    const base = resolveApiUrl('admin/offline-catalog/download')
    return `${base}?token=${encodeURIComponent(token)}`
  },

  /**
   * Returns the preview URL (opens in new tab).
   */
  previewUrl(token) {
    const base = resolveApiUrl('admin/offline-catalog/preview')
    return `${base}?token=${encodeURIComponent(token)}`
  },

  cleanup() {
    return client.delete('/admin/offline-catalog/cleanup')
  },

  buildBundle() {
    return client.post('/admin/offline-catalog/build-bundle', {}, { timeout: 120000 })
  },

  bundleStatus() {
    return client.get('/admin/offline-catalog/bundle-status')
  },
}
