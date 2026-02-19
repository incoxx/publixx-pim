import client from './client'

export default {
  login(credentials) {
    return client.post('/auth/login', credentials)
  },

  logout() {
    return client.post('/auth/logout')
  },

  me() {
    return client.get('/auth/me')
  },

  refresh() {
    return client.post('/auth/refresh')
  },
}
