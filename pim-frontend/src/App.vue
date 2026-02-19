<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/components/layout/AppLayout.vue'
import PimCommandPalette from '@/components/shared/PimCommandPalette.vue'

const router = useRouter()
const authStore = useAuthStore()

// Global keyboard shortcuts
function handleKeydown(e) {
  const isMeta = e.metaKey || e.ctrlKey

  // Cmd+K — Command Palette
  if (isMeta && e.key === 'k') {
    e.preventDefault()
    authStore.toggleCommandPalette()
  }

  // Cmd+S — Save (prevent default, emit to active form)
  if (isMeta && e.key === 's') {
    e.preventDefault()
    window.dispatchEvent(new CustomEvent('pim:save'))
  }

  // Cmd+N — New element
  if (isMeta && e.key === 'n') {
    e.preventDefault()
    window.dispatchEvent(new CustomEvent('pim:new'))
  }

  // / — Focus search (only if not in input)
  if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) && !e.target.isContentEditable) {
    e.preventDefault()
    window.dispatchEvent(new CustomEvent('pim:focus-search'))
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  authStore.checkAuth()
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <AppLayout />
  <PimCommandPalette />
</template>
