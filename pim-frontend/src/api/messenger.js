import client from './client'

export default {
  listConversations() {
    return client.get('/messenger/conversations')
  },

  startConversation(payload) {
    return client.post('/messenger/conversations', payload)
  },

  getConversation(id, page = 1) {
    return client.get(`/messenger/conversations/${id}`, { params: { page } })
  },

  markRead(id) {
    return client.post(`/messenger/conversations/${id}/read`)
  },

  sendMessage(conversationId, payload) {
    return client.post(`/messenger/conversations/${conversationId}/messages`, payload)
  },

  unreadCount() {
    return client.get('/messenger/unread-count')
  },

  colleagues() {
    return client.get('/messenger/colleagues')
  },

  teams() {
    return client.get('/messenger/teams')
  },

  tasks: {
    list() {
      return client.get('/messenger/tasks')
    },
    create(data) {
      return client.post('/messenger/tasks', data)
    },
    update(id, data) {
      return client.put(`/messenger/tasks/${id}`, data)
    },
    remove(id) {
      return client.delete(`/messenger/tasks/${id}`)
    },
  },
}
