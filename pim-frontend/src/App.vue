<script setup>
import { onMounted, onUnmounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import { useConnectorsStore } from '@/stores/connectors'
import AppLayout from '@/components/layout/AppLayout.vue'
import PimCommandPalette from '@/components/shared/PimCommandPalette.vue'
import PimToast from '@/components/shared/PimToast.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const connectorsStore = useConnectorsStore()

const isCatalogRoute = computed(() => route.matched.some((r) => r.meta.public))

// Global keyboard shortcuts (PIM only)
function handleKeydown(e) {
  if (isCatalogRoute.value) return

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

  // Navigation-Shortcuts: Ctrl+Shift+Buchstabe (kein Konflikt mit Browser-Shortcuts)
  if (isMeta && e.shiftKey && !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
    const navMap = {
      f: '/quick-search',    // F = Finden (Schnellsuche)
      p: '/products',        // P = Produkte
      h: '/hierarchies',     // H = Hierarchien
      m: '/media',           // M = Medien
      a: '/attributes',      // A = Attribute
      e: '/exports',         // E = Export
      i: '/imports',         // I = Import
      d: '/dashboard',       // D = Dashboard
      w: '/workflow',        // W = Workflow
    }
    const target = navMap[e.key.toLowerCase()]
    if (target) {
      e.preventDefault()
      router.push(target)
    }
  }
}

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown)
  await authStore.checkAuth()
  if (authStore.isAuthenticated) {
    licenseStore.fetchLicense()
    connectorsStore.loadConfiguredPlugins()
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <!-- Public catalog routes get their own layout -->
  <router-view v-if="isCatalogRoute" />

  <!-- PIM application (existing) -->
  <template v-else>
    <AppLayout />
    <PimCommandPalette />
    <PimToast />
  </template>
</template>
