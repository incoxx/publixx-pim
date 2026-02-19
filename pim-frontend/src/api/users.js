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

  delete(id) {
    return client.delete(`/users/${id}`)
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
