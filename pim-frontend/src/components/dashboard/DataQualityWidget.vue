<script setup>
import { computed } from 'vue'
import { ShieldCheck } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

const props = defineProps({
  quality: { type: Object, default: null },
  title: { type: String, default: 'Datenqualität' },
  emptyText: { type: String, default: 'Keine Produkte vorhanden' },
})

// Leer-Zustand (z. B. leere Merkliste): keine Produkte → kein aussagekräftiger Score
const isEmpty = computed(() => props.quality && (props.quality.total_products === 0 || (props.quality.dimensions || []).length === 0))

function barColor(percentage) {
  if (percentage >= 80) return '#22c55e'
  if (percentage >= 50) return '#f59e0b'
  return '#ef4444'
}

const overallColor = computed(() => barColor(props.quality?.overall ?? 0))
</script>

<template>
  <DashboardWidgetWrapper :title="title" :icon="ShieldCheck">
    <div class="p-4">
      <div v-if="!quality" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine Daten verfügbar
      </div>
      <div v-else-if="isEmpty" class="text-center text-xs text-[var(--color-text-tertiary)] py-6">
        {{ emptyText }}
      </div>
      <template v-else>
        <!-- Gesamt-Score -->
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-[var(--color-text-secondary)]">Gesamt</span>
          <span class="text-2xl font-bold" :style="{ color: overallColor }">{{ quality.overall }}%</span>
        </div>
        <div class="h-2.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden mb-5">
          <div
            class="h-full rounded-full transition-all duration-700"
            :style="{ width: quality.overall + '%', background: overallColor }"
          />
        </div>

        <!-- Dimensionen -->
        <div class="space-y-3">
          <div v-for="dim in quality.dimensions" :key="dim.key" class="group">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs text-[var(--color-text-secondary)]">{{ dim.label }}</span>
              <div class="flex items-center gap-2">
                <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ dim.count }}/{{ quality.total_products }}</span>
                <span class="text-xs font-medium" :style="{ color: barColor(dim.percentage) }">{{ dim.percentage }}%</span>
              </div>
            </div>
            <div class="h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: dim.percentage + '%', background: barColor(dim.percentage) }"
              />
            </div>
          </div>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
