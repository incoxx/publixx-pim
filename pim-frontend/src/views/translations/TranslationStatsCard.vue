<script setup>
import { computed } from 'vue'

const props = defineProps({
  lang: { type: String, required: true },
  stats: { type: Object, required: true },
})

const langNames = {
  en: 'Englisch',
  fr: 'Französisch',
  es: 'Spanisch',
  it: 'Italienisch',
  nl: 'Niederländisch',
  pl: 'Polnisch',
  pt: 'Portugiesisch',
  cs: 'Tschechisch',
  da: 'Dänisch',
  sv: 'Schwedisch',
}

const langName = computed(() => langNames[props.lang] || props.lang.toUpperCase())

const coverageColor = computed(() => {
  if (props.stats.coverage >= 90) return 'bg-green-500'
  if (props.stats.coverage >= 50) return 'bg-amber-500'
  return 'bg-red-500'
})
</script>

<template>
  <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 sm:p-5">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <span class="text-xs font-mono uppercase bg-[var(--color-bg)] px-1.5 py-0.5 rounded border border-[var(--color-border)]">{{ lang }}</span>
        <span class="text-sm font-medium text-[var(--color-text-primary)]">{{ langName }}</span>
      </div>
      <span class="text-lg font-semibold text-[var(--color-text-primary)]">{{ stats.coverage }}%</span>
    </div>

    <!-- Progress bar -->
    <div class="w-full bg-[var(--color-bg)] rounded-full h-2 mb-3">
      <div :class="[coverageColor, 'h-2 rounded-full transition-all duration-500']" :style="{ width: stats.coverage + '%' }" />
    </div>

    <div class="grid grid-cols-3 gap-3 text-xs">
      <div class="min-w-0">
        <div class="text-[var(--color-text-tertiary)] truncate">Übersetzt</div>
        <div class="font-medium text-green-600">{{ stats.translated }}</div>
      </div>
      <div class="min-w-0">
        <div class="text-[var(--color-text-tertiary)] truncate">Geprüft</div>
        <div class="font-medium text-blue-600">{{ stats.reviewed }}</div>
      </div>
      <div class="min-w-0">
        <div class="text-[var(--color-text-tertiary)] truncate">Fehlend</div>
        <div class="font-medium text-amber-600">{{ stats.missing }}</div>
      </div>
    </div>
  </div>
</template>
