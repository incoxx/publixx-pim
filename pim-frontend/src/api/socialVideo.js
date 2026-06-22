import client from './client'

/**
 * API-Client für Social-Media-Produktvideos (Publish → Social-Video).
 */
export default {
  // Standard-Mapping (Zielfelder + Default-Regeln) als Vorlage
  defaultMapping() {
    return client.get('/social-video/default-mapping')
  },

  // Render-Job anstoßen → { job_id, reel }
  generate(payload) {
    return client.post('/social-video', payload)
  },

  // Render-Status abfragen
  status(jobId) {
    return client.get(`/social-video/${jobId}/status`)
  },

  // Download-URL für das fertige MP4
  downloadUrl(jobId) {
    return `/api/v1/social-video/${jobId}/download`
  },
}
