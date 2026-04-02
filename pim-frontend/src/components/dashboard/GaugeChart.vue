<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js'

ChartJS.register(ArcElement, Tooltip)

const props = defineProps({
  value: { type: Number, required: true },
  max: { type: Number, default: 100 },
  color: { type: String, default: '#2E75B6' },
  label: { type: String, default: '' },
  thresholds: { type: Boolean, default: true },
})

const displayColor = computed(() => {
  if (!props.thresholds) return props.color
  const pct = (props.value / props.max) * 100
  if (pct >= 80) return '#059669'
  if (pct >= 50) return '#D97706'
  return '#DC2626'
})

const chartData = computed(() => {
  const val = Math.min(props.value, props.max)
  const remaining = props.max - val
  return {
    labels: [props.label || 'Wert', 'Verbleibend'],
    datasets: [{
      data: [val, remaining],
      backgroundColor: [displayColor.value, '#e5e7eb'],
      borderWidth: 0,
      hoverOffset: 2,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  rotation: -90,
  circumference: 180,
  cutout: '75%',
  plugins: {
    legend: { display: false },
    tooltip: {
      enabled: false,
    },
  },
}

const displayValue = computed(() => {
  if (props.max === 100) return `${props.value}%`
  return props.value.toLocaleString('de-DE')
})
</script>

<template>
  <div class="relative" style="height: 100px;">
    <Doughnut :data="chartData" :options="chartOptions" />
    <div class="absolute inset-0 flex items-end justify-center pb-1">
      <div class="text-center">
        <p class="text-xl font-bold" :style="{ color: displayColor }">{{ displayValue }}</p>
        <p v-if="label" class="text-[10px] text-[var(--color-text-tertiary)] mt--0.5">{{ label }}</p>
      </div>
    </div>
  </div>
</template>
