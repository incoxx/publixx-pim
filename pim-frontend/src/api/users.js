import client, { buildParams } from './client'

export default {
  list(options = {}) {
    return client.get('/users', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/users', data)
  },

  update(id, data) {
    return client.put(`/users/${id}`, data)
  },

  dependencies(id) {
    return client.get(`/users/${id}/dependencies`)
  },

  delete(id, { force = false } = {}) {
    return client.delete(`/users/${id}`, { params: force ? { force: true } : {} })
  },
}

export const roles = {
  list(options = {}) {
    return client.get('/roles', { params: buildParams(options) })
  },

  create(data) {
    return client.post('/roles', data)
  },

  update(id, data) {
    return client.put(`/roles/${id}`, data)
  },

  delete(id) {
    return client.delete(`/roles/${id}`)
  },

  setPermissions(id, permissions) {
    return client.put(`/roles/${id}/permissions`, { permissions })
  },
}
