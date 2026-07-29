<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ShieldCheck, ArrowRight, Sparkles } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

const { t } = useI18n()

const props = defineProps({
  quality: { type: Object, default: null },
  title: { type: String, default: null },
  emptyText: { type: String, default: null },
  // actionable: Lücken (< 100 %) werden klickbar und lösen 'action' aus
  actionable: { type: Boolean, default: false },
})

const resolvedTitle = computed(() => props.title || t('Datenqualität'))
const resolvedEmptyText = computed(() => props.emptyText || t('Keine Produkte vorhanden'))

const emit = defineEmits(['action'])

// Leer-Zustand (z. B. leere Merkliste): keine Produkte → kein aussagekräftiger Score
const isEmpty = computed(() => props.quality && (props.quality.total_products === 0 || (props.quality.dimensions || []).length === 0))

// Größte Lücke (niedrigste, noch nicht vollständige Dimension) → "Nächster Schritt"
const worstGap = computed(() => {
  const gaps = (props.quality?.dimensions || []).filter(d => d.percentage < 100)
  return gaps.sort((a, b) => a.percentage - b.percentage)[0] || null
})

function onDimClick(dim) {
  if (props.actionable && dim.percentage < 100) emit('action', dim)
}

function barColor(percentage) {
  if (percentage >= 80) return '#22c55e'
  if (percentage >= 50) return '#f59e0b'
  return '#ef4444'
}

const overallColor = computed(() => barColor(props.quality?.overall ?? 0))
</script>

<template>
  <DashboardWidgetWrapper :title="resolvedTitle" :icon="ShieldCheck">
    <div class="p-4">
      <div v-if="!quality" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine Daten verfügbar
      </div>
      <div v-else-if="isEmpty" class="text-center text-xs text-[var(--color-text-tertiary)] py-6">
        {{ resolvedEmptyText }}
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

        <!-- Nächster Schritt (größte Lücke) -->
        <button
          v-if="actionable && worstGap"
          class="w-full mb-4 flex items-center gap-2 px-3 py-2 rounded-lg text-left transition-colors bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] hover:bg-[color-mix(in_srgb,var(--color-accent)_14%,transparent)]"
          @click="emit('action', worstGap)"
        >
          <Sparkles class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
          <span class="flex-1 min-w-0 text-xs text-[var(--color-text-secondary)]">
            <span class="font-medium text-[var(--color-text-primary)]">Nächster Schritt:</span>
            {{ worstGap.label }} ({{ worstGap.count }}/{{ quality.total_products }})
          </span>
          <ArrowRight class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
        </button>

        <!-- Dimensionen -->
        <div class="space-y-3">
          <component
            :is="actionable && dim.percentage < 100 ? 'button' : 'div'"
            v-for="dim in quality.dimensions"
            :key="dim.key"
            class="block w-full text-left group"
            :class="actionable && dim.percentage < 100 ? 'cursor-pointer rounded-md -mx-1 px-1 py-0.5 hover:bg-[var(--color-bg)]' : ''"
            @click="onDimClick(dim)"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs text-[var(--color-text-secondary)] flex items-center gap-1">
                {{ dim.label }}
                <ArrowRight
                  v-if="actionable && dim.percentage < 100"
                  class="w-3 h-3 opacity-0 group-hover:opacity-100 text-[var(--color-accent)] transition-opacity"
                  :stroke-width="2"
                />
              </span>
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
          </component>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
