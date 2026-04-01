<script setup>
import { Activity } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

defineProps({
  items: { type: Array, default: () => [] },
})

function iconClass(item) {
  if (item.icon === 'success' || item.icon === 'product') return 'bg-emerald-500/15 text-emerald-600'
  if (item.icon === 'error') return 'bg-red-500/15 text-red-600'
  if (item.icon === 'running') return 'bg-blue-500/15 text-blue-600'
  if (item.icon === 'media') return 'bg-purple-500/15 text-purple-600'
  if (item.icon === 'attribute') return 'bg-amber-500/15 text-amber-600'
  return 'bg-gray-500/15 text-gray-600'
}

function iconDot(item) {
  if (item.icon === 'success' || item.icon === 'product') return 'bg-emerald-500'
  if (item.icon === 'error') return 'bg-red-500'
  if (item.icon === 'running') return 'bg-blue-500 animate-pulse'
  if (item.icon === 'media') return 'bg-purple-500'
  if (item.icon === 'attribute') return 'bg-amber-500'
  return 'bg-gray-400'
}

function typeLabel(item) {
  if (item.type === 'import') return 'Import'
  if (item.type === 'export') return 'Export'
  return null
}

function relativeTime(timestamp) {
  if (!timestamp) return ''
  const now = new Date()
  const date = new Date(timestamp)
  const diff = Math.floor((now - date) / 1000)

  if (diff < 60) return 'gerade eben'
  if (diff < 3600) return `vor ${Math.floor(diff / 60)} Min.`
  if (diff < 86400) return `vor ${Math.floor(diff / 3600)} Std.`
  if (diff < 172800) return 'gestern'
  return `vor ${Math.floor(diff / 86400)} Tagen`
}
</script>

<template>
  <DashboardWidgetWrapper title="Aktivit\u00e4ten" :icon="Activity">
    <div class="p-4">
      <div v-if="items.length === 0" class="text-center text-sm text-[var(--color-text-tertiary)] py-6">
        Noch keine Aktivit\u00e4ten
      </div>
      <div v-else class="space-y-0">
        <div
          v-for="(item, index) in items"
          :key="index"
          class="flex items-start gap-3 py-2.5 border-b border-[var(--color-border)] last:border-0"
        >
          <!-- Dot-Indikator -->
          <div class="mt-1.5 shrink-0">
            <div class="w-2 h-2 rounded-full" :class="iconDot(item)" />
          </div>

          <!-- Inhalt -->
          <div class="flex-1 min-w-0">
            <p class="text-xs text-[var(--color-text-primary)] leading-relaxed">
              {{ item.description }}
            </p>
            <div class="flex items-center gap-2 mt-0.5">
              <span v-if="typeLabel(item)" class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] font-medium">
                {{ typeLabel(item) }}
              </span>
            </div>
          </div>

          <!-- Zeit -->
          <span class="text-[10px] text-[var(--color-text-tertiary)] whitespace-nowrap shrink-0 mt-0.5">
            {{ relativeTime(item.timestamp) }}
          </span>
        </div>
      </div>
    </div>
  </DashboardWidgetWrapper>
</template>
