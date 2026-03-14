import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/user-audit-logs', { params: buildParams(options) })
  },

  export(params = {}) {
    return client.get('/user-audit-logs/export', {
      params,
      responseType: 'blob',
    })
  },

  deleteAll(params = {}) {
    return client.delete('/user-audit-logs', { params })
  },
}
