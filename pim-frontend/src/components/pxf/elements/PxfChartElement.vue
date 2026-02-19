<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { resolveBinding } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
})

const canvasRef = ref(null)
let chartInstance = null

const chartData = computed(() => {
  const val = resolveBinding(props.data, props.element.bind) || []
  return Array.isArray(val) ? val : []
})

const cc = computed(() => props.element.chartConfig || {})

const defaultColors = [
  '#2E75B6', '#1B3A5C', '#059669', '#D97706', '#DC2626',
  '#7C3AED', '#EC4899', '#06B6D4', '#84CC16', '#F97316',
]

async function renderChart() {
  if (!canvasRef.value || chartData.value.length === 0) return
  const { Chart, registerables } = await import('chart.js')
  Chart.register(...registerables)

  if (chartInstance) chartInstance.destroy()

  const labels = chartData.value.map(d => d.label || d.name || '')
  const values = chartData.value.map(d => d.value ?? d.count ?? 0)
  const colors = cc.value.colors?.length ? cc.value.colors : defaultColors.slice(0, labels.length)

  const type = cc.value.chartType === 'horizontalBar' ? 'bar' : (cc.value.chartType || 'bar')

  chartInstance = new Chart(canvasRef.value, {
    type,
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderColor: colors,
        borderWidth: 1,
        tension: cc.value.tension || 0,
        fill: cc.value.fill || false,
        pointRadius: cc.value.pointRadius || 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: cc.value.chartType === 'horizontalBar' ? 'y' : 'x',
      animation: { duration: cc.value.animationDuration || 0 },
      plugins: {
        legend: {
          display: cc.value.showLegend ?? false,
          position: cc.value.legendPosition || 'top',
        },
      },
      scales: ['pie', 'doughnut', 'radar', 'polarArea'].includes(type) ? {} : {
        y: { beginAtZero: cc.value.beginAtZero ?? true },
      },
      cutout: type === 'doughnut' ? (cc.value.cutout || '55%') : undefined,
    },
  })
}

onMounted(() => renderChart())
watch(chartData, () => renderChart(), { deep: true })
</script>

<template>
  <div class="w-full h-full p-1">
    <canvas ref="canvasRef" />
  </div>
</template>
