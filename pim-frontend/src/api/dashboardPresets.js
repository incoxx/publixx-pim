import client from './client'

export default {
  list() {
    return client.get('/dashboard-presets')
  },

  create(data) {
    return client.post('/dashboard-presets', data)
  },

  update(id, data) {
    return client.put(`/dashboard-presets/${id}`, data)
  },

  remove(id) {
    return client.delete(`/dashboard-presets/${id}`)
  },

  setDefault(id) {
    return client.post(`/dashboard-presets/${id}/set-default`)
  },

  activate(id) {
    return client.post(`/dashboard-presets/${id}/activate`)
  },

  saveFromCurrent(data) {
    return client.post('/dashboard-presets/save-current', data)
  },
}
