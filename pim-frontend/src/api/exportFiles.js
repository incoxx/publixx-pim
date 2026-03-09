import client from './client'

export default {
  list() {
    return client.get('/export-files')
  },

  remove(name) {
    return client.delete(`/export-files/${encodeURIComponent(name)}`)
  },
}
