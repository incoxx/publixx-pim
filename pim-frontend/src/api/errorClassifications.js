import client, { buildParams } from './client'

export default {
  status() {
    return client.get('/error-classifications/status')
  },

  list(options = {}) {
    return client.get('/error-classifications', { params: buildParams(options) })
  },

  classify(payload = {}) {
    return client.post('/error-classifications/classify', payload)
  },

  update(id, payload = {}) {
    return client.patch(`/error-classifications/${id}`, payload)
  },

  deleteAll(params = {}) {
    return client.delete('/error-classifications', { params })
  },
}
