import client from './client'

export default {
  getEvents(from, to) {
    return client.get('/calendar', { params: { from, to } })
  },
}
