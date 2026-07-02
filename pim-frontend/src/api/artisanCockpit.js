import client from './client'

export default {
  listCommands() {
    return client.get('/admin/artisan-cockpit/commands')
  },

  run(payload) {
    return client.post('/admin/artisan-cockpit/run', payload)
  },
}
