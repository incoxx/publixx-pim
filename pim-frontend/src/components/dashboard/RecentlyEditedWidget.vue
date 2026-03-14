<script setup>
import { useRouter } from 'vue-router'
import { Clock } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'

const props = defineProps({
  products: { type: Array, default: () => [] },
})

const router = useRouter()

function goToProduct(id) {
  if (id) router.push(`/products/${id}`)
}

function formatRelativeTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diffMs = now - d
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 1) return 'gerade eben'
  if (diffMin < 60) return `vor ${diffMin} Min.`
  const diffHours = Math.floor(diffMin / 60)
  if (diffHours < 24) return `vor ${diffHours} Std.`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return `vor ${diffDays} Tagen`
  return d.toLocaleDateString('de-DE', { dateStyle: 'medium' })
}

const statusColors = {
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  draft: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  archived: 'bg-gray-100 text-gray-500 dark:bg-gray-800/40 dark:text-gray-400',
}
</script>

<template>
  <DashboardWidgetWrapper title="Zuletzt bearbeitet" :icon="Clock">
    <div v-if="products.length === 0" class="px-4 py-6 text-center text-sm text-[var(--color-text-tertiary)]">
      Noch keine Bearbeitungen
    </div>
    <div v-else>
      <div
        v-for="p in products"
        :key="p.id"
        class="flex items-center gap-3 px-4 py-2.5 border-b border-[var(--color-border)] last:border-b-0 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
        @click="goToProduct(p.id)"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono text-[var(--color-accent)]">{{ p.sku || '—' }}</span>
            <span
              v-if="p.status"
              class="pim-badge text-[10px] px-1.5 py-0.5 rounded-full font-medium"
              :class="statusColors[p.status] || 'bg-gray-100 text-gray-600'"
            >{{ p.status === 'active' ? 'Aktiv' : p.status === 'draft' ? 'Entwurf' : p.status }}</span>
          </div>
          <p class="text-sm text-[var(--color-text-primary)] truncate mt-0.5">{{ p.name || '(kein Name)' }}</p>
        </div>
        <span class="text-[11px] text-[var(--color-text-tertiary)] shrink-0">{{ formatRelativeTime(p.updated_at) }}</span>
      </div>
    </div>
  </DashboardWidgetWrapper>
</template>
