import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import authApi from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('pim_token') || null)
  const locale = ref(localStorage.getItem('pim_locale') || 'de')
  const commandPaletteOpen = ref(false)
  const sidebarCollapsed = ref(false)
  const panelOpen = ref(false)
  const panelComponent = ref(null)
  const panelProps = ref({})

  const isAuthenticated = computed(() => !!token.value)
  const userName = computed(() => user.value?.name || '')
  const userRole = computed(() => user.value?.role?.name || '')
  const permissions = computed(() => user.value?.permissions || [])

  function hasPermission(permission) {
    if (userRole.value === 'Admin') return true
    return permissions.value.includes(permission)
  }

  async function login(credentials) {
    const { data } = await authApi.login(credentials)
    const payload = data.data || data
    token.value = payload.token
    user.value = payload.user
    localStorage.setItem('pim_token', payload.token)
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch { /* ignore */ }
    token.value = null
    user.value = null
    localStorage.removeItem('pim_token')
  }

  async function checkAuth() {
    if (!token.value) return
    try {
      const { data } = await authApi.me()
      user.value = data.data || data
    } catch {
      token.value = null
      localStorage.removeItem('pim_token')
    }
  }

  function setLocale(loc) {
    locale.value = loc
    localStorage.setItem('pim_locale', loc)
  }

  function toggleCommandPalette() {
    commandPaletteOpen.value = !commandPaletteOpen.value
  }

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function openPanel(component, props = {}) {
    panelComponent.value = component
    panelProps.value = props
    panelOpen.value = true
  }

  function closePanel() {
    panelOpen.value = false
    panelComponent.value = null
    panelProps.value = {}
  }

  return {
    user, token, locale,
    commandPaletteOpen, sidebarCollapsed, panelOpen, panelComponent, panelProps,
    isAuthenticated, userName, userRole, permissions,
    hasPermission, login, logout, checkAuth, setLocale,
    toggleCommandPalette, toggleSidebar, openPanel, closePanel,
  }
})
