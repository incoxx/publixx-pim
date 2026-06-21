import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import authApi from '@/api/auth'
import userPreferencesApi from '@/api/userPreferences'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('pim_token') || null)
  const locale = ref(localStorage.getItem('pim_locale') || 'de')
  // Ansichtsmodus: 'cockpit' | 'gui' | null (null = der Rollen-Standard entscheidet).
  // Persönliche Wahl wird lokal + serverseitig gespeichert und schlägt die Rolle.
  const viewModePref = ref(localStorage.getItem('pim_view_mode') || null)
  const commandPaletteOpen = ref(false)
  const sidebarCollapsed = ref(false)
  const sidebarMobileOpen = ref(false)
  const sidebarWidth = ref(parseInt(localStorage.getItem('pim_sidebar_width')) || 240)
  const sidebarCollapsedSections = ref(JSON.parse(localStorage.getItem('pim_sidebar_sections') || '{}'))
  const panelOpen = ref(false)
  const panelComponent = ref(null)
  const panelProps = ref({})
  const panelWidth = ref('360px')

  const isAuthenticated = computed(() => !!token.value)
  const userName = computed(() => user.value?.name || '')
  const userRole = computed(() => user.value?.roles?.[0]?.name || '')
  const isAdmin = computed(() => (user.value?.roles || []).some(r => ['Admin', 'Sysadmin'].includes(r.name)))
  const permissions = computed(() => user.value?.all_permissions || [])
  const entityRestrictions = computed(() => user.value?.entity_restrictions || [])
  const tabPermissions = computed(() => user.value?.tab_permissions || {})

  // Standard-Ansicht der Primärrolle (vom Backend geliefert, sonst 'gui').
  const roleDefaultViewMode = computed(() => user.value?.roles?.[0]?.default_view_mode || 'gui')

  // Wirksamer Modus: Benutzereinstellung schlägt Rolle, sonst Rollen-Standard, sonst 'gui'.
  const effectiveViewMode = computed(() => viewModePref.value || roleDefaultViewMode.value || 'gui')
  const isCockpitMode = computed(() => effectiveViewMode.value === 'cockpit')

  /**
   * Get the access level for a product editor tab.
   * Returns 'write' (default), 'read', or 'hidden'.
   */
  function getTabAccess(tabKey) {
    if (userRole.value === 'Admin') return 'write'
    return tabPermissions.value[tabKey] || 'write'
  }

  function hasPermission(permission) {
    if (userRole.value === 'Admin') return true
    return permissions.value.includes(permission)
  }

  function hasInstanceAccess(restrictableType, instanceId, requiredLevel = 'read') {
    if (userRole.value === 'Admin') return true

    const typeRestrictions = entityRestrictions.value.filter(
      r => r.restrictable_type === restrictableType
    )

    // No restrictions = full access
    if (typeRestrictions.length === 0) return true

    const restriction = typeRestrictions.find(r => r.restrictable_id === instanceId)
    if (!restriction) return false

    const hierarchy = { read: 1, write: 2, delete: 3 }
    return (hierarchy[restriction.access_level] || 0) >= (hierarchy[requiredLevel] || 0)
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
      loadViewMode()
    } catch {
      token.value = null
      localStorage.removeItem('pim_token')
    }
  }

  function setLocale(loc) {
    locale.value = loc
    localStorage.setItem('pim_locale', loc)
  }

  /**
   * Ansichtsmodus setzen (persönliche Wahl, schlägt die Rolle).
   * mode: 'cockpit' | 'gui' | null (null = wieder dem Rollen-Standard folgen).
   */
  function setViewMode(mode) {
    viewModePref.value = mode
    if (mode) {
      localStorage.setItem('pim_view_mode', mode)
    } else {
      localStorage.removeItem('pim_view_mode')
    }
    // Serverseitig persistieren (überlebt Gerätewechsel), Fehler bewusst ignorieren.
    userPreferencesApi.update('view_mode', { mode }).catch(() => {})
  }

  function toggleViewMode() {
    setViewMode(effectiveViewMode.value === 'cockpit' ? 'gui' : 'cockpit')
  }

  /** Serverseitig gespeicherte Modus-Wahl laden (überschreibt lokalen Stand). */
  async function loadViewMode() {
    try {
      const { data } = await userPreferencesApi.get('view_mode')
      const payload = data.data
      // payload null/[] = nie gesetzt → lokalen Stand belassen
      if (!payload || Array.isArray(payload)) return
      const mode = payload.mode
      if (mode === 'cockpit' || mode === 'gui') {
        viewModePref.value = mode
        localStorage.setItem('pim_view_mode', mode)
      } else if (mode === null) {
        // Server: "der Rolle folgen" → veralteten lokalen Override entfernen
        viewModePref.value = null
        localStorage.removeItem('pim_view_mode')
      }
    } catch { /* ignore */ }
  }

  function toggleCommandPalette() {
    commandPaletteOpen.value = !commandPaletteOpen.value
  }

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function toggleMobileSidebar() {
    sidebarMobileOpen.value = !sidebarMobileOpen.value
  }

  function closeMobileSidebar() {
    sidebarMobileOpen.value = false
  }

  function setSidebarWidth(w) {
    sidebarWidth.value = w
    localStorage.setItem('pim_sidebar_width', String(w))
  }

  function toggleSidebarSection(key) {
    const sections = { ...sidebarCollapsedSections.value }
    // All sections default to collapsed (undefined = true)
    const isCollapsed = sections[key] !== false
    sections[key] = !isCollapsed
    sidebarCollapsedSections.value = sections
    localStorage.setItem('pim_sidebar_sections', JSON.stringify(sections))
  }

  function collapseAllSections() {
    const sections = {}
    // Set all to collapsed (true = collapsed), including 'daily' which defaults to open
    for (const key of Object.keys(sidebarCollapsedSections.value)) {
      sections[key] = true
    }
    sections['daily'] = true
    sidebarCollapsedSections.value = sections
    localStorage.setItem('pim_sidebar_sections', JSON.stringify(sections))
  }

  function openPanel(component, props = {}, width = '360px') {
    panelComponent.value = component
    panelProps.value = props
    panelWidth.value = width || '360px'
    panelOpen.value = true
  }

  function closePanel() {
    panelOpen.value = false
    panelComponent.value = null
    panelProps.value = {}
    panelWidth.value = '360px'
  }

  return {
    user, token, locale,
    commandPaletteOpen, sidebarCollapsed, sidebarMobileOpen, sidebarWidth, sidebarCollapsedSections,
    panelOpen, panelComponent, panelProps, panelWidth,
    isAuthenticated, userName, userRole, isAdmin, permissions, entityRestrictions, tabPermissions,
    viewModePref, roleDefaultViewMode, effectiveViewMode, isCockpitMode,
    hasPermission, hasInstanceAccess, getTabAccess, login, logout, checkAuth, setLocale,
    setViewMode, toggleViewMode, loadViewMode,
    toggleCommandPalette, toggleSidebar, toggleMobileSidebar, closeMobileSidebar, setSidebarWidth, toggleSidebarSection, collapseAllSections,
    openPanel, closePanel,
  }
})
