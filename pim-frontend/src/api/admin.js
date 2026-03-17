import client from './client'

export default {
  getResetCategories() {
    return client.get('/admin/reset-categories')
  },

  resetData(confirmation, categories = null) {
    const payload = { confirmation }
    if (categories && categories.length > 0) {
      payload.categories = categories
    }
    return client.post('/admin/reset-data', payload)
  },

  loadDemoData() {
    return client.post('/admin/load-demo-data', {}, { timeout: 300000 })
  },

  getDeployStatus() {
    return client.get('/admin/deploy/status')
  },

  deploy() {
    return client.post('/admin/deploy', {}, { timeout: 300000 })
  },

  rollback(commitHash) {
    return client.post('/admin/deploy/rollback', { commit_hash: commitHash })
  },

  updateCatalogTheme(payload) {
    return client.put('/settings/catalog-theme', payload)
  },

  reindexSearch() {
    return client.post('/admin/search-reindex', {}, { timeout: 300000 })
  },

  getEnvInfo() {
    return client.get('/admin/env-info')
  },

  getSystemStatus() {
    return client.get('/admin/system-status')
  },

  // ── Test Data Generator ──
  generateTestData(params = {}) {
    return client.post('/admin/test-data/generate', params, { timeout: 600000 })
  },

  cleanupTestData() {
    return client.delete('/admin/test-data', { timeout: 300000 })
  },

  getTestDataStats() {
    return client.get('/admin/test-data/stats')
  },
}
