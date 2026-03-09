import { ref } from 'vue'

export function useClipboard(resetMs = 2000) {
  const copied = ref(false)

  async function copy(text) {
    if (!text) return
    try {
      await navigator.clipboard.writeText(text)
      copied.value = true
      setTimeout(() => { copied.value = false }, resetMs)
    } catch (e) { /* clipboard not available */ }
  }

  return { copied, copy }
}
