import { defineStore } from 'pinia'
import { ref } from 'vue'
import dashboardApi from '@/api/dashboard'

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

  return {
    welcome, stats, trends, dataQuality, activityFeed, dataFlows,
    myTasks, recentlyEdited, workflowSummary, completenessSummary, activeProjects, teamWorkload, projectTimeline,
    loading, error, fetchDashboard,
  }
})
