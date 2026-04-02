<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Settings2, TrendingUp, TrendingDown } from 'lucide-vue-next'
import { Doughnut, Line } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip } from 'chart.js'
import GaugeChart from './GaugeChart.vue'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import dashboardApi from '@/api/dashboard'

ChartJS.register(ArcElement, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip)

const props = defineProps({
  config: { type: Object, required: true },
  // config = { id, search_profile_id, chart_type, metric, color, title }
})

const emit = defineEmits(['configure', 'remove'])

const loading = ref(false)
const data = ref(null)
const error = ref(null)

const chartType = computed(() => props.config.chart_type || 'gauge')
const metric = computed(() => props.config.metric || 'completeness')
const color = computed(() => props.config.color || '#2E75B6')
const title = computed(() => data.value?.search_profile_name || props.config.title || 'Profil')

// Metriken basierend auf Chart-Typ anfordern
const metricsToFetch = computed(() => {
  const base = ['total']
  switch (chartType.value) {
    case 'gauge': return [...base, metric.value]
    case 'number': return [...base, metric.value]
    case 'trend': return [...base, 'weekly_trend']
    case 'bar': return [...base, 'status_distribution']
    case 'donut': return [...base, 'completeness', 'media_coverage', 'price_coverage']
    default: return [...base, metric.value]
  }
})

async function fetchData() {
  if (!props.config.search_profile_id) return
  loading.value = true
  error.value = null
  try {
    const { data: resp } = await dashboardApi.getProfileStats(
      props.config.search_profile_id,
      metricsToFetch.value,
    )
    data.value = resp.data || resp
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
watch(() => props.config.search_profile_id, fetchData)
watch(() => props.config.metric, fetchData)

// Chart-Daten für verschiedene Typen
const metricValue = computed(() => data.value?.[metric.value] ?? 0)
const totalCount = computed(() => data.value?.total ?? 0)

const metricLabel = computed(() => {
  const labels = {
    completeness: 'Vollständigkeit',
    media_coverage: 'Medien',
    price_coverage: 'Preise',
    translation_coverage: 'Übersetzungen',
    total: 'Gesamt',
    active: 'Aktiv',
    draft: 'Entwurf',
  }
  return labels[metric.value] || metric.value
})

// Line-Chart für Trend
const trendChartData = computed(() => {
  const trend = data.value?.weekly_trend || []
  return {
    labels: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'].slice(0, trend.length),
    datasets: [{
      data: trend,
      borderColor: color.value,
      backgroundColor: color.value + '20',
      fill: true,
      tension: 0.4,
      pointRadius: 2,
      pointHoverRadius: 4,
      borderWidth: 2,
    }],
  }
})

const trendChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { enabled: false } },
  scales: {
    x: { display: false },
    y: { display: false, beginAtZero: true },
  },
}

// Donut-Chart für Multi-Metrik
const donutChartData = computed(() => {
  if (!data.value) return null
  const items = [
    { label: 'Vollständigkeit', value: data.value.completeness ?? 0, color: '#059669' },
    { label: 'Medien', value: data.value.media_coverage ?? 0, color: '#2563EB' },
    { label: 'Preise', value: data.value.price_coverage ?? 0, color: '#D97706' },
  ]
  return {
    labels: items.map(i => i.label),
    datasets: [{
      data: items.map(i => i.value),
      backgroundColor: items.map(i => i.color),
      borderWidth: 0,
    }],
  }
})

const donutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '65%',
  plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', titleFont: { size: 11 }, bodyFont: { size: 10 }, padding: 6, cornerRadius: 4 } },
}

// Bar-Chart für Status-Verteilung
const statusColors = { active: '#059669', draft: '#D97706', inactive: '#6B7280', discontinued: '#DC2626' }
const statusLabels = { active: 'Aktiv', draft: 'Entwurf', inactive: 'Inaktiv', discontinued: 'Eingestellt' }

const trendTotal = computed(() => {
  const trend = data.value?.weekly_trend || []
  return trend.reduce((a, b) => a + b, 0)
})
</script>

<template>
  <div class="pim-card overflow-hidden h-full">
    <!-- Header -->
    <div class="flex items-center gap-2 px-4 py-2.5 border-b border-[var(--color-border)]">
      <div class="w-2 h-2 rounded-full shrink-0" :style="{ background: color }" />
      <h3 class="text-xs font-semibold text-[var(--color-text-primary)] flex-1 truncate">{{ title }}</h3>
      <button
        class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors text-[var(--color-text-tertiary)]"
        @click="emit('configure', config)"
        title="Konfigurieren"
      >
        <Settings2 class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>

    <!-- Content -->
    <div class="p-4">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-6">
        <div class="w-5 h-5 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center text-xs text-[var(--color-error)] py-4">
        {{ error }}
      </div>

      <!-- No Profile -->
      <div v-else-if="!config.search_profile_id" class="text-center py-6">
        <p class="text-xs text-[var(--color-text-tertiary)]">Kein Suchprofil ausgewählt</p>
        <button
          class="mt-2 text-xs text-[var(--color-accent)] hover:underline"
          @click="emit('configure', config)"
        >Konfigurieren</button>
      </div>

      <!-- Gauge-Typ -->
      <template v-else-if="chartType === 'gauge'">
        <GaugeChart :value="metricValue" :color="color" :label="metricLabel" />
        <p class="text-center text-xs text-[var(--color-text-tertiary)] mt-2">
          {{ totalCount.toLocaleString('de-DE') }} Produkte
        </p>
      </template>

      <!-- Number-Typ -->
      <template v-else-if="chartType === 'number'">
        <div class="text-center py-2">
          <p class="text-3xl font-bold" :style="{ color }">
            {{ (metric.endsWith('coverage') || metric === 'completeness') ? metricValue + '%' : metricValue.toLocaleString('de-DE') }}
          </p>
          <p class="text-xs text-[var(--color-text-tertiary)] mt-1">{{ metricLabel }}</p>
          <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
            {{ totalCount.toLocaleString('de-DE') }} Produkte
          </p>
        </div>
      </template>

      <!-- Trend-Typ -->
      <template v-else-if="chartType === 'trend'">
        <div style="height: 80px;">
          <Line v-if="data?.weekly_trend" :data="trendChartData" :options="trendChartOptions" />
        </div>
        <div class="flex items-center justify-between mt-2">
          <span class="text-[10px] text-[var(--color-text-tertiary)]">Letzte 7 Tage</span>
          <span v-if="trendTotal > 0" class="text-xs font-medium text-emerald-600 flex items-center gap-0.5">
            <TrendingUp class="w-3 h-3" :stroke-width="2" />
            +{{ trendTotal }}
          </span>
        </div>
      </template>

      <!-- Bar-Typ (Status-Verteilung) -->
      <template v-else-if="chartType === 'bar' && data?.status_distribution">
        <div class="space-y-2">
          <div
            v-for="(count, status) in data.status_distribution"
            :key="status"
            class="flex items-center gap-2"
          >
            <span class="text-[10px] text-[var(--color-text-secondary)] w-16 shrink-0">{{ statusLabels[status] || status }}</span>
            <div class="flex-1 h-4 rounded bg-gray-100 dark:bg-gray-800 overflow-hidden">
              <div
                class="h-full rounded transition-all duration-500"
                :style="{ width: (totalCount > 0 ? (count / totalCount) * 100 : 0) + '%', background: statusColors[status] || '#6B7280' }"
              />
            </div>
            <span class="text-xs font-medium text-[var(--color-text-primary)] w-10 text-right">{{ count }}</span>
          </div>
        </div>
      </template>

      <!-- Donut-Typ -->
      <template v-else-if="chartType === 'donut'">
        <div style="height: 100px;">
          <Doughnut v-if="donutChartData" :data="donutChartData" :options="donutOptions" />
        </div>
        <div class="flex items-center justify-center gap-3 mt-2">
          <span class="text-[10px] flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-600" /> Vollst.</span>
          <span class="text-[10px] flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-600" /> Medien</span>
          <span class="text-[10px] flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-600" /> Preise</span>
        </div>
      </template>
    </div>
  </div>
</template>
