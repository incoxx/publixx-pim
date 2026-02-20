import client from './client'

export default {
  resetData(confirmation) {
    return client.post('/admin/reset-data', { confirmation })
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
}
