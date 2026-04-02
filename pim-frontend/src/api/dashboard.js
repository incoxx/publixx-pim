import client from './client'

export default {
  getData() {
    return client.get('/dashboard')
  },

  getProfileStats(searchProfileId, metrics = []) {
    return client.post('/dashboard/profile-stats', {
      search_profile_id: searchProfileId,
      metrics,
    })
  },
}
