<script setup>
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  projects: { type: Array, default: () => [] },
})

const { t } = useI18n()
const router = useRouter()

const statusLabels = {
  planning: 'Planung',
  active: 'Aktiv',
  completed: 'Fertig',
  on_hold: 'Pausiert',
}

const statusColors = {
  planning: 'text-blue-600 bg-blue-50',
  active: 'text-green-600 bg-green-50',
  completed: 'text-gray-600 bg-gray-100',
  on_hold: 'text-amber-600 bg-amber-50',
}

function daysRemaining(endDate) {
  if (!endDate) return null
  const diff = Math.ceil((new Date(endDate) - new Date()) / (1000 * 60 * 60 * 24))
  return diff
}
</script>

<template>
  <div class="pim-card p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">Aktive Projekte</h3>

    <div v-if="projects.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-4 text-center">
      Keine aktiven Projekte
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="project in projects"
        :key="project.id"
        class="flex items-center gap-3 p-2 rounded-md hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
        @click="router.push('/projects')"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ project.name }}</span>
            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', statusColors[project.status] || '']">
              {{ statusLabels[project.status] ? t(statusLabels[project.status]) : project.status }}
            </span>
          </div>
          <div class="flex items-center gap-3 mt-0.5">
            <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ project.start_date }} – {{ project.end_date }}</span>
            <span v-if="project.manager" class="text-[10px] text-[var(--color-text-tertiary)]">{{ project.manager.name }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ t('{count} Teams', { count: project.teams_count }) }}</span>
          <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ t('{count} Produkte', { count: project.products_count }) }}</span>
          <span
            v-if="daysRemaining(project.end_date) !== null"
            :class="[
              'text-[10px] font-medium px-1.5 py-0.5 rounded',
              daysRemaining(project.end_date) <= 7 ? 'text-red-600 bg-red-50' :
              daysRemaining(project.end_date) <= 30 ? 'text-amber-600 bg-amber-50' :
              'text-green-600 bg-green-50'
            ]"
          >
            {{ daysRemaining(project.end_date) > 0 ? t('{count} Tage', { count: daysRemaining(project.end_date) }) : t('Überfällig') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
