<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { GitBranch } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  summary: { type: [Array, Object], default: null },
})

// Support both old object format and new array format
const items = computed(() => {
  if (!props.summary) return []
  if (Array.isArray(props.summary)) {
    return props.summary.map(s => ({
      label: s.status_name,
      count: s.count,
      color: s.color || '#6b7280',
    }))
  }
  // Legacy fallback
  return [
    { label: 'Bearbeitung', count: props.summary.editing || 0, color: '#3b82f6' },
    { label: 'Review', count: props.summary.review || 0, color: '#f59e0b' },
    { label: 'Freigegeben', count: props.summary.approved || 0, color: '#22c55e' },
  ]
})

const total = computed(() => items.value.reduce((sum, i) => sum + (i.count || 0), 0))

const chartData = computed(() => {
  if (total.value === 0) return null
  return {
    labels: items.value.map(i => i.label),
    datasets: [{
      data: items.value.map(i => i.count),
      backgroundColor: items.value.map(i => i.color),
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
