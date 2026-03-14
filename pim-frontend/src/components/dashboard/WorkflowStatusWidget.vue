<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { GitBranch } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  summary: { type: Object, default: null },
})

const chartData = computed(() => {
  if (!props.summary) return null
  const { editing, review, approved } = props.summary
  const total = editing + review + approved
  if (total === 0) return null
  return {
    labels: ['Bearbeitung', 'Review', 'Freigegeben'],
    datasets: [{
      data: [editing, review, approved],
      backgroundColor: ['#3b82f6', '#f59e0b', '#22c55e'],
      borderWidth: 0,
      hoverOffset: 4,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '65%',
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: 'rgba(0,0,0,0.8)',
      titleFont: { size: 12 },
      bodyFont: { size: 11 },
      padding: 8,
      cornerRadius: 6,
    },
  },
}

const items = computed(() => {
  if (!props.summary) return []
  return [
    { label: 'Bearbeitung', count: props.summary.editing, color: '#3b82f6' },
    { label: 'Review', count: props.summary.review, color: '#f59e0b' },
    { label: 'Freigegeben', count: props.summary.approved, color: '#22c55e' },
    { label: 'Nicht zugewiesen', count: props.summary.unassigned, color: '#9ca3af' },
  ]
})

const total = computed(() => {
  if (!props.summary) return 0
  return props.summary.editing + props.summary.review + props.summary.approved
})
</script>

<template>
  <DashboardWidgetWrapper title="Workflow-Status" :icon="GitBranch">
    <div class="p-4">
      <div v-if="!summary || total === 0" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine aktiven Workflows
      </div>
      <template v-else>
        <!-- Chart -->
        <div class="relative mx-auto" style="height: 160px; max-width: 160px;">
          <Doughnut :data="chartData" :options="chartOptions" />
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="text-center">
              <p class="text-2xl font-bold text-[var(--color-text-primary)]">{{ total }}</p>
              <p class="text-[10px] text-[var(--color-text-tertiary)]">Aktiv</p>
            </div>
          </div>
        </div>
        <!-- Legend -->
        <div class="mt-4 space-y-1.5">
          <div v-for="item in items" :key="item.label" class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: item.color }" />
            <span class="text-xs text-[var(--color-text-secondary)] flex-1">{{ item.label }}</span>
            <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ item.count }}</span>
          </div>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
