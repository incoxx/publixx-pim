<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CalendarDays } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

const { t, locale } = useI18n()
const numberLocale = computed(() => (locale.value === 'de' ? 'de-DE' : 'en-US'))

const props = defineProps({
  projects: { type: Array, default: () => [] },
})

const statusLabels = {
  planning: 'Planung',
  active: 'Aktiv',
  on_hold: 'Pausiert',
}

const statusColors = {
  planning: '#3b82f6',
  active: '#22c55e',
  on_hold: '#f59e0b',
}

// Calculate timeline range (earliest start to latest end)
const timelineRange = computed(() => {
  if (!props.projects.length) return null
  const now = new Date()
  let minDate = new Date(now)
  let maxDate = new Date(now)

  for (const p of props.projects) {
    if (p.start_date) {
      const d = new Date(p.start_date)
      if (d < minDate) minDate = d
    }
    if (p.end_date) {
      const d = new Date(p.end_date)
      if (d > maxDate) maxDate = d
    }
  }

  // Add padding
  minDate.setDate(minDate.getDate() - 7)
  maxDate.setDate(maxDate.getDate() + 14)

  return { min: minDate, max: maxDate, totalDays: Math.ceil((maxDate - minDate) / (1000 * 60 * 60 * 24)) }
})

function barStyle(project) {
  if (!timelineRange.value || !project.start_date || !project.end_date) return {}
  const range = timelineRange.value
  const start = new Date(project.start_date)
  const end = new Date(project.end_date)
  const leftPct = Math.max(0, ((start - range.min) / (range.max - range.min)) * 100)
  const widthPct = Math.max(2, ((end - start) / (range.max - range.min)) * 100)
  return {
    left: leftPct + '%',
    width: Math.min(widthPct, 100 - leftPct) + '%',
    backgroundColor: statusColors[project.status] || '#6b7280',
  }
}

function todayMarkerStyle() {
  if (!timelineRange.value) return {}
  const range = timelineRange.value
  const now = new Date()
  const leftPct = ((now - range.min) / (range.max - range.min)) * 100
  if (leftPct < 0 || leftPct > 100) return { display: 'none' }
  return { left: leftPct + '%' }
}

// Month markers for the header
const monthMarkers = computed(() => {
  if (!timelineRange.value) return []
  const range = timelineRange.value
  const markers = []
  const current = new Date(range.min)
  current.setDate(1)
  current.setMonth(current.getMonth() + 1)

  const monthFormatter = new Intl.DateTimeFormat(numberLocale.value, { month: 'short' })

  while (current <= range.max) {
    const leftPct = ((current - range.min) / (range.max - range.min)) * 100
    if (leftPct >= 0 && leftPct <= 100) {
      markers.push({
        label: monthFormatter.format(current) + ' ' + current.getFullYear(),
        left: leftPct + '%',
      })
    }
    current.setMonth(current.getMonth() + 1)
  }
  return markers
})

function daysRemaining(endDate) {
  if (!endDate) return null
  return Math.ceil((new Date(endDate) - new Date()) / (1000 * 60 * 60 * 24))
}
</script>

<template>
  <DashboardWidgetWrapper title="Projekt-Timeline" :icon="CalendarDays">
    <div class="p-4">
      <div v-if="!projects.length" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine aktiven Projekte
      </div>
      <template v-else>
        <!-- Legend -->
        <div class="flex items-center gap-4 mb-4">
          <div v-for="(label, key) in statusLabels" :key="key" class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-sm" :style="{ backgroundColor: statusColors[key] }" />
            <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ t(label) }}</span>
          </div>
        </div>

        <!-- Month header -->
        <div class="relative h-5 mb-1 border-b border-[var(--color-border)]">
          <span
            v-for="(marker, i) in monthMarkers"
            :key="i"
            class="absolute text-[9px] text-[var(--color-text-tertiary)] -translate-x-1/2 top-0"
            :style="{ left: marker.left }"
          >{{ marker.label }}</span>
        </div>

        <!-- Gantt rows -->
        <div class="space-y-1.5">
          <div v-for="project in projects" :key="project.id" class="flex items-center gap-2">
            <!-- Project name -->
            <div class="w-32 shrink-0 text-right pr-2">
              <span class="text-[11px] text-[var(--color-text-primary)] truncate block">{{ project.name }}</span>
            </div>
            <!-- Bar area -->
            <div class="flex-1 relative h-6 bg-[var(--color-bg)] rounded overflow-hidden">
              <!-- Today marker -->
              <div
                class="absolute top-0 bottom-0 w-px bg-red-400 z-10"
                :style="todayMarkerStyle()"
              />
              <!-- Project bar -->
              <div
                class="absolute top-1 bottom-1 rounded-sm transition-all opacity-85 hover:opacity-100 cursor-default"
                :style="barStyle(project)"
                :title="`${project.name}: ${project.start_date} – ${project.end_date} ` + t('({count} Produkte)', { count: project.products_count })"
              />
            </div>
            <!-- Days remaining -->
            <div class="w-16 shrink-0 text-right">
              <span
                v-if="daysRemaining(project.end_date) !== null"
                :class="[
                  'text-[10px] font-medium',
                  daysRemaining(project.end_date) <= 0 ? 'text-red-500' :
                  daysRemaining(project.end_date) <= 14 ? 'text-amber-500' :
                  'text-[var(--color-text-tertiary)]'
                ]"
              >
                {{ daysRemaining(project.end_date) > 0 ? t('{count}d', { count: daysRemaining(project.end_date) }) : t('Überfällig') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Today label -->
        <div class="flex items-center gap-1.5 mt-3">
          <span class="w-3 h-px bg-red-400" />
          <span class="text-[9px] text-red-400">Heute</span>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
