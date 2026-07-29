<script setup>
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ArrowRight } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import { ClipboardList } from 'lucide-vue-next'

const props = defineProps({
  tasks: { type: Array, default: () => [] },
})

const { t, locale } = useI18n()
const router = useRouter()

const statusLabels = {
  open: 'Offen',
  in_progress: 'In Bearbeitung',
  closed: 'Geschlossen',
}

const statusColors = {
  open: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  closed: 'bg-gray-100 text-gray-500 dark:bg-gray-800/40 dark:text-gray-400',
}

function goToProduct(id) {
  if (id) router.push(`/products/${id}`)
}

function goToWorkflow() {
  router.push('/workflow')
}

function formatDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diffMs = now - d
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 1) return t('gerade eben')
  if (diffMin < 60) return t('vor {count} Min.', { count: diffMin })
  const diffHours = Math.floor(diffMin / 60)
  if (diffHours < 24) return t('vor {count} Std.', { count: diffHours })
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return t('vor {count} Tagen', { count: diffDays })
  return d.toLocaleDateString(locale.value === 'de' ? 'de-DE' : 'en-US', { dateStyle: 'medium' })
}
</script>

<template>
  <DashboardWidgetWrapper title="Meine Aufgaben" :icon="ClipboardList">
    <div v-if="tasks.length === 0" class="px-4 py-6 text-center text-sm text-[var(--color-text-tertiary)]">
      Keine offenen Aufgaben
    </div>
    <div v-else>
      <div
        v-for="task in tasks"
        :key="task.id"
        class="flex items-center gap-3 px-4 py-2.5 border-b border-[var(--color-border)] last:border-b-0 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
        @click="goToProduct(task.product?.id)"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono text-[var(--color-accent)]">{{ task.product?.sku || '—' }}</span>
            <span
              class="pim-badge text-[10px] px-1.5 py-0.5 rounded-full font-medium"
              :class="statusColors[task.status]"
            >{{ t(statusLabels[task.status]) }}</span>
          </div>
          <p class="text-sm text-[var(--color-text-primary)] truncate mt-0.5">{{ task.title }}</p>
        </div>
        <span class="text-[11px] text-[var(--color-text-tertiary)] shrink-0">{{ formatDate(task.created_at) }}</span>
      </div>
      <!-- Footer link -->
      <button
        class="w-full flex items-center justify-center gap-1 px-4 py-2.5 text-xs text-[var(--color-accent)] hover:bg-[var(--color-bg)] transition-colors font-medium"
        @click="goToWorkflow"
      >
        Alle Aufgaben anzeigen
        <ArrowRight class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>
  </DashboardWidgetWrapper>
</template>
