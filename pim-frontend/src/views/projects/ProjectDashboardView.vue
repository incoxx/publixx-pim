<script setup>
import { ref, onMounted } from 'vue'
import { RefreshCw } from 'lucide-vue-next'
import dashboardApi from '@/api/dashboard'
import WorkflowStatusWidget from '@/components/dashboard/WorkflowStatusWidget.vue'
import TeamWorkloadWidget from '@/components/dashboard/TeamWorkloadWidget.vue'
import ProjectTimelineWidget from '@/components/dashboard/ProjectTimelineWidget.vue'
import ActiveProjectsWidget from '@/components/dashboard/ActiveProjectsWidget.vue'

const loading = ref(false)
const workflowSummary = ref(null)
const teamWorkload = ref([])
const projectTimeline = ref([])
const activeProjects = ref([])

async function fetchData() {
  loading.value = true
  try {
    const { data } = await dashboardApi.getData()
    const d = data.data || data
    workflowSummary.value = d.workflow_summary
    teamWorkload.value = d.team_workload || []
    projectTimeline.value = d.project_timeline || []
    activeProjects.value = d.active_projects || []
  } catch (e) {
    console.error('Failed to load project dashboard', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchData())
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Projekt-Dashboard</h2>
      <button
        class="pim-btn pim-btn-ghost text-xs"
        @click="fetchData"
        :disabled="loading"
      >
        <RefreshCw class="w-4 h-4" :class="loading ? 'animate-spin' : ''" :stroke-width="2" />
        <span>Aktualisieren</span>
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading && !activeProjects.length" class="flex items-center justify-center py-20">
      <div class="w-6 h-6 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
    </div>

    <template v-else>
      <!-- Timeline (full width) -->
      <ProjectTimelineWidget :projects="projectTimeline" />

      <!-- 2/3 + 1/3 grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
          <TeamWorkloadWidget :workload="teamWorkload" />
        </div>
        <div>
          <WorkflowStatusWidget :summary="workflowSummary" />
        </div>
      </div>

      <!-- Active projects list (full width) -->
      <ActiveProjectsWidget :projects="activeProjects" />
    </template>
  </div>
</template>
