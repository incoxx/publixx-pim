<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip } from 'chart.js'
import { Users } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip)

const props = defineProps({
  workload: { type: Array, default: () => [] },
})

const teamsWithTasks = computed(() =>
  props.workload.filter(t => t.open_tasks > 0)
)

const chartData = computed(() => {
  if (!teamsWithTasks.value.length) return null
  return {
    labels: teamsWithTasks.value.map(t => t.name),
    datasets: [{
      data: teamsWithTasks.value.map(t => t.open_tasks),
      backgroundColor: teamsWithTasks.value.map((_, i) => {
        const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#22c55e', '#06b6d4', '#ef4444']
        return colors[i % colors.length]
      }),
      borderRadius: 4,
      barPercentage: 0.7,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        font: { size: 10 },
        color: '#9ca3af',
      },
      grid: { display: false },
    },
    y: {
      ticks: {
        font: { size: 11 },
        color: '#6b7280',
      },
      grid: { display: false },
    },
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: 'rgba(0,0,0,0.8)',
      titleFont: { size: 12 },
      bodyFont: { size: 11 },
      padding: 8,
      cornerRadius: 6,
      callbacks: {
        label: (ctx) => `${ctx.raw} offene Aufgaben`,
      },
    },
  },
}

const totalOpen = computed(() => props.workload.reduce((sum, t) => sum + (t.open_tasks || 0), 0))
</script>

<template>
  <DashboardWidgetWrapper title="Team-Auslastung" :icon="Users">
    <div class="p-4">
      <div v-if="!workload.length || totalOpen === 0" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine offenen Aufgaben
      </div>
      <template v-else>
        <div :style="{ height: Math.max(120, teamsWithTasks.length * 36) + 'px' }">
          <Bar :data="chartData" :options="chartOptions" />
        </div>
        <div class="mt-3 flex items-center justify-between text-[10px] text-[var(--color-text-tertiary)]">
          <span>{{ workload.length }} Teams</span>
          <span>{{ totalOpen }} offene Aufgaben gesamt</span>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
