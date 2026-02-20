import client from './client'

export default {
  resetData(confirmation) {
    return client.post('/admin/reset-data', { confirmation })
  },
}
