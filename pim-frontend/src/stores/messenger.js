import { defineStore } from 'pinia'
import { ref } from 'vue'
import messengerApi from '@/api/messenger'

// Kein Websocket/Broadcasting im Backend vorhanden -- Aktualisierung per Polling.
const UNREAD_POLL_INTERVAL = 20000
const ACTIVE_CONVERSATION_POLL_INTERVAL = 5000

export const useMessengerStore = defineStore('messenger', () => {
  const conversations = ref([])
  const unreadCount = ref(0)
  const activeConversation = ref(null)
  const messages = ref([])
  const loading = ref(false)
  const error = ref(null)
  // Serverseitige Suche (Empfängername + Nachrichteninhalt). Im Store gehalten, damit
  // auch das Polling denselben Filter anwendet und die Liste nicht "aufblitzt".
  const search = ref('')

  let unreadTimer = null
  let activeTimer = null

  async function fetchUnreadCount() {
    try {
      const { data } = await messengerApi.unreadCount()
      unreadCount.value = data.data.count
    } catch {
      // rein informativ -- Badge bleibt auf letztem bekannten Stand
    }
  }

  async function fetchConversations() {
    loading.value = true
    error.value = null
    try {
      const params = search.value ? { search: search.value } : {}
      const { data } = await messengerApi.listConversations(params)
      conversations.value = data.data || []
    } catch (e) {
      error.value = e.response?.data?.message || e.response?.data?.detail || 'Konversationen konnten nicht geladen werden'
    } finally {
      loading.value = false
    }
  }

  function setSearch(term) {
    search.value = term || ''
    return fetchConversations()
  }

  async function openConversation(id) {
    stopActivePolling()
    loading.value = true
    error.value = null
    try {
      const { data } = await messengerApi.getConversation(id)
      activeConversation.value = data.data
      messages.value = data.messages?.data || []
      await messengerApi.markRead(id)
      await fetchUnreadCount()
      await fetchConversations()
    } catch (e) {
      error.value = e.response?.data?.message || e.response?.data?.detail || 'Konversation konnte nicht geladen werden'
    } finally {
      loading.value = false
    }
    startActivePolling()
  }

  async function refreshActiveConversation() {
    if (!activeConversation.value) return
    try {
      const { data } = await messengerApi.getConversation(activeConversation.value.id)
      messages.value = data.messages?.data || []
      if (document.hasFocus()) {
        await messengerApi.markRead(activeConversation.value.id)
        await fetchUnreadCount()
      }
    } catch {
      // stiller Retry beim naechsten Poll-Intervall
    }
  }

  async function sendMessage({ body, replyToMessageId, attachments } = {}) {
    if (!activeConversation.value) return null
    const { data } = await messengerApi.sendMessage(activeConversation.value.id, {
      body: body || undefined,
      reply_to_message_id: replyToMessageId || undefined,
      attachments: attachments?.length ? attachments : undefined,
    })
    messages.value.push(data.data)
    return data.data
  }

  async function startConversation({ recipients, body, attachments } = {}) {
    const { data } = await messengerApi.startConversation({
      recipients,
      body: body || undefined,
      attachments: attachments?.length ? attachments : undefined,
    })
    await fetchConversations()
    return data.data
  }

  function closeConversation() {
    stopActivePolling()
    activeConversation.value = null
    messages.value = []
  }

  async function deleteConversation(id) {
    await messengerApi.deleteConversation(id)
    conversations.value = conversations.value.filter(c => c.id !== id)
    if (activeConversation.value?.id === id) closeConversation()
  }

  async function resolveConversation(id) {
    await messengerApi.resolveConversation(id)
    if (activeConversation.value?.id === id) activeConversation.value.status = 'done'
    const conversation = conversations.value.find(c => c.id === id)
    if (conversation) conversation.status = 'done'
  }

  async function reopenConversation(id) {
    await messengerApi.reopenConversation(id)
    if (activeConversation.value?.id === id) activeConversation.value.status = 'open'
    const conversation = conversations.value.find(c => c.id === id)
    if (conversation) conversation.status = 'open'
  }

  function startPolling() {
    if (unreadTimer) return
    fetchUnreadCount()
    unreadTimer = setInterval(fetchUnreadCount, UNREAD_POLL_INTERVAL)
  }

  function stopPolling() {
    if (unreadTimer) {
      clearInterval(unreadTimer)
      unreadTimer = null
    }
    stopActivePolling()
  }

  function startActivePolling() {
    stopActivePolling()
    activeTimer = setInterval(refreshActiveConversation, ACTIVE_CONVERSATION_POLL_INTERVAL)
  }

  function stopActivePolling() {
    if (activeTimer) {
      clearInterval(activeTimer)
      activeTimer = null
    }
  }

  return {
    conversations,
    unreadCount,
    activeConversation,
    messages,
    loading,
    error,
    search,
    fetchUnreadCount,
    fetchConversations,
    setSearch,
    openConversation,
    refreshActiveConversation,
    sendMessage,
    startConversation,
    closeConversation,
    deleteConversation,
    resolveConversation,
    reopenConversation,
    startPolling,
    stopPolling,
  }
})
