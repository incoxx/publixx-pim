import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const messages = ref([])
  let nextId = 0

  function showToast(text, type = 'success', duration = 3000) {
    const id = ++nextId
    messages.value.push({ id, text, type })
    setTimeout(() => {
      messages.value = messages.value.filter(m => m.id !== id)
    }, duration)
  }

  return { messages, showToast }
})
