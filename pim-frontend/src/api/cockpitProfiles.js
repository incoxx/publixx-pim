import client from './client'

/**
 * Rollenspezifische Cockpit-Layouts (Phase 4).
 * - mine(): Layout der eigenen Primärrolle (jede:r Nutzer:in)
 * - get/update/remove: Verwaltung je Rolle (Berechtigung roles.edit)
 */
export default {
  mine() {
    return client.get('/cockpit-profiles/mine')
  },

  get(roleId) {
    return client.get(`/cockpit-profiles/${roleId}`)
  },

  update(roleId, layout) {
    return client.put(`/cockpit-profiles/${roleId}`, layout)
  },

  remove(roleId) {
    return client.delete(`/cockpit-profiles/${roleId}`)
  },
}
