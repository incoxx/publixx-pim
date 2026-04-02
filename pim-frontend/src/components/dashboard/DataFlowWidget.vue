<script setup>
import { ArrowDownToLine, ArrowUpFromLine } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

defineProps({
  flows: { type: Object, default: () => ({ imports: [], exports: [] }) },
})

function statusIcon(status) {
  if (['completed', 'success'].includes(status)) return { class: 'text-emerald-500', label: '\u2705' }
  if (status === 'failed') return { class: 'text-red-500', label: '\u274c' }
  if (['processing', 'running'].includes(status)) return { class: 'text-blue-500 animate-pulse', label: '\ud83d\udd04' }
  if (['pending', 'queued'].includes(status)) return { class: 'text-gray-400', label: '\u23f3' }
  return { class: 'text-gray-400', label: '\u2022' }
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

function formatBytes(bytes) {
  if (!bytes) return null
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}
</script>

<template>
  <DashboardWidgetWrapper title="Datenflüsse" :icon="ArrowDownToLine">
    <div class="p-4">
      <div v-if="!flows.imports?.length && !flows.exports?.length" class="text-center text-sm text-[var(--color-text-tertiary)] py-4">
        Keine Datenflüsse
      </div>
      <template v-else>
        <!-- Imports -->
        <div v-if="flows.imports?.length">
          <div class="flex items-center gap-1.5 mb-2">
            <ArrowDownToLine class="w-3.5 h-3.5 text-[var(--color-accent)]" :stroke-width="2" />
            <span class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">Imports</span>
          </div>
          <div class="space-y-1.5 mb-4">
            <div
              v-for="item in flows.imports"
              :key="item.id"
              class="flex items-center gap-2 text-xs py-1.5 px-2 rounded-md hover:bg-[var(--color-bg)] transition-colors"
            >
              <span>{{ statusIcon(item.status).label }}</span>
              <span class="flex-1 truncate text-[var(--color-text-primary)]">{{ item.name }}</span>
              <span v-if="item.user_name" class="text-[var(--color-text-tertiary)] hidden sm:inline">{{ item.user_name }}</span>
              <span class="text-[var(--color-text-tertiary)] whitespace-nowrap">{{ relativeTime(item.completed_at || item.started_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Exports -->
        <div v-if="flows.exports?.length">
          <div class="flex items-center gap-1.5 mb-2">
            <ArrowUpFromLine class="w-3.5 h-3.5 text-[var(--color-success)]" :stroke-width="2" />
            <span class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">Exports</span>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="item in flows.exports"
              :key="item.id"
              class="flex items-center gap-2 text-xs py-1.5 px-2 rounded-md hover:bg-[var(--color-bg)] transition-colors"
            >
              <span>{{ statusIcon(item.status).label }}</span>
              <span class="flex-1 truncate text-[var(--color-text-primary)]">{{ item.name }}</span>
              <span v-if="item.format" class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] font-medium uppercase">
                {{ item.format }}
              </span>
              <span class="text-[var(--color-text-tertiary)] whitespace-nowrap">{{ relativeTime(item.last_run_at) }}</span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
