<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Languages, ExternalLink, Clock, Loader2, CheckCircle, XCircle } from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import translationJobsApi from '@/api/translationJobs'

const router = useRouter()
const jobs = ref([])
const loading = ref(false)

// Status-Definitionen (Reihenfolge = Anzeige)
const STATUS_DEFS = [
  { key: 'in_progress', label: 'In Bearbeitung', icon: Loader2,     color: 'text-blue-500',                       spin: true },
  { key: 'pending',     label: 'Wartend',        icon: Clock,       color: 'text-yellow-500' },
  { key: 'draft',       label: 'Entwurf',        icon: Clock,       color: 'text-[var(--color-text-tertiary)]' },
  { key: 'completed',   label: 'Abgeschlossen',  icon: CheckCircle, color: 'text-green-500' },
  { key: 'failed',      label: 'Fehlgeschlagen', icon: XCircle,     color: 'text-[var(--color-error)]' },
]

const counts = computed(() => {
  const c = {}
  for (const job of jobs.value) {
    c[job.status] = (c[job.status] || 0) + 1
  }
  return c
})

// Nur Status mit Treffern anzeigen
const visibleStatuses = computed(() => STATUS_DEFS.filter(s => (counts.value[s.key] || 0) > 0))

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await translationJobsApi.list({ per_page: 100 })
    const payload = data.data || data
    jobs.value = Array.isArray(payload) ? payload : (payload.data || [])
  } catch { /* ignore */ }
  loading.value = false
})
</script>

<template>
  <DashboardWidgetWrapper title="Übersetzungs-Status" :icon="Languages">
    <div class="p-4">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-6">
        <div class="w-4 h-4 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
      </div>

      <!-- Leer -->
      <div v-else-if="jobs.length === 0" class="text-center text-xs text-[var(--color-text-tertiary)] py-6">
        Keine Übersetzungsjobs
      </div>

      <template v-else>
        <div class="grid grid-cols-2 gap-2">
          <div
            v-for="s in visibleStatuses"
            :key="s.key"
            class="flex items-center gap-2 p-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)]"
          >
            <component :is="s.icon" class="w-4 h-4 shrink-0" :class="[s.color, { 'animate-spin': s.spin }]" :stroke-width="2" />
            <div class="min-w-0">
              <p class="text-sm font-semibold text-[var(--color-text-primary)] leading-none">{{ counts[s.key] }}</p>
              <p class="text-[10px] text-[var(--color-text-tertiary)] truncate">{{ s.label }}</p>
            </div>
          </div>
        </div>

        <div class="mt-3 text-right">
          <button
            class="text-xs text-[var(--color-accent)] hover:underline inline-flex items-center gap-1"
            @click="router.push({ name: 'translation-jobs' })"
          >
            Alle Jobs
            <ExternalLink class="w-3 h-3" :stroke-width="2" />
          </button>
        </div>
      </template>
    </div>
  </DashboardWidgetWrapper>
</template>
