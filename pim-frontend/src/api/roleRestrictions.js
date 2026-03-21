import client from './client'

export default {
  list(roleId) {
    return client.get(`/roles/${roleId}/restrictions`)
  },

  listByType(roleId, type) {
    return client.get(`/roles/${roleId}/restrictions/${type}`)
  },

  sync(roleId, type, restrictions) {
    return client.put(`/roles/${roleId}/restrictions/${type}`, { restrictions })
  },

  remove(roleId, type) {
    return client.delete(`/roles/${roleId}/restrictions/${type}`)
  },

  getTabPermissions(roleId) {
    return client.get(`/roles/${roleId}/tab-permissions`)
  },

  syncTabPermissions(roleId, tabs) {
    return client.put(`/roles/${roleId}/tab-permissions`, { tabs })
  },
}
