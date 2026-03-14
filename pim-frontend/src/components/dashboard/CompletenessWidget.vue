<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js'
import { BarChart3 } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

ChartJS.register(ArcElement, Tooltip)

const props = defineProps({
  summary: { type: Object, default: null },
})

const chartData = computed(() => {
  if (!props.summary) return null
  const { fully_complete, incomplete } = props.summary
  return {
    labels: ['Vollständig', 'Unvollständig'],
    datasets: [{
      data: [fully_complete, incomplete],
      backgroundColor: ['#22c55e', '#e5e7eb'],
      borderWidth: 0,
      hoverOffset: 4,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '70%',
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

const percentageColor = computed(() => {
  const p = props.summary?.average_percentage ?? 0
  if (p >= 80) return '#22c55e'
  if (p >= 50) return '#f59e0b'
  return '#ef4444'
})
</script>

<template>
  <DashboardWidgetWrapper title="Produkt-Füllstand" :icon="BarChart3">
    <div class="p-4">
      <div v-if="!summary" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine Daten verfügbar
      </div>
      <template v-else>
        <!-- Chart -->
        <div class="relative mx-auto" style="height: 160px; max-width: 160px;">
          <Doughnut v-if="summary.total > 0" :data="chartData" :options="chartOptions" />
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="text-center">
              <p class="text-2xl font-bold" :style="{ color: percentageColor }">{{ summary.average_percentage }}%</p>
              <p class="text-[10px] text-[var(--color-text-tertiary)]">Vollständig</p>
            </div>
          </div>
        </div>
        <!-- Details -->
        <div class="mt-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" />
              <span class="text-xs text-[var(--color-text-secondary)]">Vollständig</span>
            </div>
            <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ summary.fully_complete }}</span>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-gray-300" />
              <span class="text-xs text-[var(--color-text-secondary)]">Unvollständig</span>
            </div>
            <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ summary.incomplete }}</span>
          </div>
          <!-- Progress bar -->
          <div class="mt-2">
            <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: summary.average_percentage + '%', background: percentageColor }"
              />
            </div>
            <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1 text-right">
              {{ summary.fully_complete }} von {{ summary.total }} Produkten
            </p>
          </div>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
