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
    return client.post('/admin/search-reindex', {}, { timeout: 30000 })
  },

  reindexProgress() {
    return client.get('/admin/search-reindex/progress')
  },

  cancelReindex() {
    return client.post('/admin/search-reindex/cancel')
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

  getTestDataProgress() {
    return client.get('/admin/test-data/progress')
  },

  cancelTestData() {
    return client.post('/admin/test-data/cancel')
  },

  batchProcessPdfs(mode = 'missing') {
    return client.post('/admin/pdf/batch-process', { mode }, { timeout: 300000 })
  },

  // ── Process / Queue Management ──
  getQueueJobs() {
    return client.get('/admin/queue-jobs')
  },

  flushQueue(queue = null) {
    return client.post('/admin/queue-flush', { queue })
  },

  cancelJob(jobId, jobType) {
    return client.post('/admin/queue-cancel-job', { job_id: jobId, job_type: jobType })
  },

  flushFailedJobs() {
    return client.delete('/admin/failed-jobs')
  },

  restartApache() {
    return client.post('/admin/restart-apache', {}, { timeout: 20000 })
  },

  restartHorizon() {
    return client.post('/admin/restart-horizon', {}, { timeout: 15000 })
  },

  getSystemProcesses() {
    return client.get('/admin/system-processes')
  },

  // ── Database Consistency ──
  checkConsistency() {
    return client.get('/admin/db-consistency/check', { timeout: 60000 })
  },

  fixConsistencyIssue(issueType) {
    return client.post(`/admin/db-consistency/fix/${issueType}`)
  },
}
