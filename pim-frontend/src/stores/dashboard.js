import { defineStore } from 'pinia'
import { ref } from 'vue'
import dashboardApi from '@/api/dashboard'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref(null)
  const myTasks = ref([])
  const recentlyEdited = ref([])
  const workflowSummary = ref(null)
  const completenessSummary = ref(null)
  const activeProjects = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function fetchDashboard() {
    loading.value = true
    error.value = null
    try {
      const { data } = await dashboardApi.getData()
      const d = data.data || data
      stats.value = d.stats
      myTasks.value = d.my_tasks || []
      recentlyEdited.value = d.recently_edited || []
      workflowSummary.value = d.workflow_summary
      completenessSummary.value = d.completeness_summary
      activeProjects.value = d.active_projects || []
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  return {
    stats, myTasks, recentlyEdited, workflowSummary, completenessSummary, activeProjects,
    loading, error, fetchDashboard,
  }
})
