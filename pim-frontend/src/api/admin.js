import client from './client'

export default {
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
