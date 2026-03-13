import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/audit-logs', { params: buildParams(options) })
  },

  export(params = {}) {
    return client.get('/audit-logs/export', {
      params,
      responseType: 'blob',
    })
  },

  deleteAll(params = {}) {
    return client.delete('/audit-logs', { params })
  },
}
