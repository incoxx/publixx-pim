import client from './client'

export default {
  get(group) {
    return client.get(`/user/preferences/${group}`)
  },

  update(group, payload) {
    return client.put(`/user/preferences/${group}`, payload)
  },
}
