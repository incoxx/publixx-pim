import client from './client'

export default {
  getData() {
    return client.get('/dashboard')
  },
}
