import { defineStore } from 'pinia'
import { ref } from 'vue'
import dashboardApi from '@/api/dashboard'
import dashboardPresetsApi from '@/api/dashboardPresets'
import userPreferencesApi from '@/api/userPreferences'

const STORAGE_KEY = 'pim_dashboard_widgets'

export const useDashboardStore = defineStore('dashboard', () => {
  const welcome = ref(null)
  const stats = ref(null)
  const trends = ref(null)
  const dataQuality = ref(null)
  const activityFeed = ref([])
  const dataFlows = ref({ imports: [], exports: [] })
  const myTasks = ref([])
  const recentlyEdited = ref([])
  const workflowSummary = ref(null)
  const completenessSummary = ref(null)
  const activeProjects = ref([])
  const teamWorkload = ref([])
  const projectTimeline = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Dashboard-Konfiguration (Widget-Sichtbarkeit + Profilkarten)
  const dashboardConfig = ref(null)
  const configLoaded = ref(false)

  // Dashboard-Presets
  const presets = ref([])
  const presetsLoaded = ref(false)

  async function fetchDashboard() {
    loading.value = true
    error.value = null
    try {
      const { data } = await dashboardApi.getData()
      const d = data.data || data
      welcome.value = d.welcome
      stats.value = d.stats
      trends.value = d.trends
      dataQuality.value = d.data_quality
      activityFeed.value = d.activity_feed || []
      dataFlows.value = d.data_flows || { imports: [], exports: [] }
      myTasks.value = d.my_tasks || []
      recentlyEdited.value = d.recently_edited || []
      workflowSummary.value = d.workflow_summary
      completenessSummary.value = d.completeness_summary
      activeProjects.value = d.active_projects || []
      teamWorkload.value = d.team_workload || []
      projectTimeline.value = d.project_timeline || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  /**
   * Dashboard-Config vom Server laden. Fällt auf localStorage zurück, migriert dann.
   */
  async function loadDashboardConfig() {
    try {
      const { data } = await userPreferencesApi.get('dashboard')
      const payload = data.data || data
      if (payload?.widgets) {
        dashboardConfig.value = payload
        configLoaded.value = true
        return
      }
    } catch { /* Server hat keine Config */ }

    // Fallback: localStorage migrieren
    try {
      const saved = localStorage.getItem(STORAGE_KEY)
      if (saved) {
        const parsed = JSON.parse(saved)
        dashboardConfig.value = { widgets: parsed }
        // Zur Server-Persistenz migrieren
        await saveDashboardConfig(dashboardConfig.value)
        localStorage.removeItem(STORAGE_KEY)
      }
    } catch { /* ignore */ }
    configLoaded.value = true
  }

  /**
   * Dashboard-Config auf Server speichern.
   */
  async function saveDashboardConfig(config) {
    dashboardConfig.value = config
    try {
      await userPreferencesApi.update('dashboard', config)
    } catch { /* ignore — Offline-Fallback */ }
  }

  async function loadPresets() {
    try {
      const { data } = await dashboardPresetsApi.list()
      presets.value = data.data || data
      presetsLoaded.value = true
    } catch { /* ignore */ }
  }

  async function activatePreset(id) {
    try {
      const { data } = await dashboardPresetsApi.activate(id)
      const payload = data.data || data
      dashboardConfig.value = payload
      return true
    } catch { return false }
  }

  return {
    welcome, stats, trends, dataQuality, activityFeed, dataFlows,
    myTasks, recentlyEdited, workflowSummary, completenessSummary, activeProjects, teamWorkload, projectTimeline,
    loading, error, fetchDashboard,
    dashboardConfig, configLoaded, loadDashboardConfig, saveDashboardConfig,
    presets, presetsLoaded, loadPresets, activatePreset,
  }
})
