import client from './client'

/**
 * Rollenspezifische Cockpit-Layouts (Phase 4).
 * - mine(): Layout der eigenen Primärrolle (jede:r Nutzer:in)
 * - roles(): Rollenliste für den Editor (Berechtigung cockpit-layouts.view)
 * - get/update/remove: Verwaltung je Rolle (cockpit-layouts.view/edit)
 */
export default {
  mine() {
    return client.get('/cockpit-profiles/mine')
  },

  // Rollenliste für den Cockpit-Layout-Editor (inkl. has_custom_layout-Flag)
  roles() {
    return client.get('/cockpit-profiles/roles')
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
