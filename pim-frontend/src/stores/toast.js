import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

export const useToastStore = defineStore('toast', () => {
  const messages = ref([])
  let nextId = 0

  function showToast(text, type = 'success', duration = 3000) {
    // Während des Logout-Übergangs keine Fehler-Toasts zeigen — Hintergrund-
    // Requests der verlassenen Seite laufen dann ins 401 und würden sonst kurz
    // rot aufblitzen.
    if (type === 'error') {
      try {
        if (useAuthStore().loggingOut) return
      } catch { /* Store evtl. noch nicht bereit — ignorieren */ }
    }

    const id = ++nextId
    messages.value.push({ id, text, type })
    setTimeout(() => {
      messages.value = messages.value.filter(m => m.id !== id)
    }, duration)
  }

  return { messages, showToast }
})
