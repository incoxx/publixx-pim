import client, { buildParams } from './client'

export default {
  list(options = {}) {
    const params = buildParams(options)
    if (options.status) params.status = options.status
    return client.get('/access-links', { params })
  },

  create(data) {
    return client.post('/access-links', data)
  },

  remove(id) {
    return client.delete(`/access-links/${id}`)
  },

  report() {
    return client.get('/access-links/report')
  },

  redeem(token) {
    return client.get(`/access-links/redeem/${token}`)
  },
}
