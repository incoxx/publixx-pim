<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTranslationJobsStore } from '@/stores/translationJobs'
import {
  Plus, Trash2, Play, Eye, RefreshCw, Languages,
  CheckCircle, XCircle, Clock, Loader2, Ban,
} from 'lucide-vue-next'

const router = useRouter()
const store = useTranslationJobsStore()

const statusFilter = ref('')
const deleting = ref(null)

onMounted(() => {
  store.fetchJobs()
})

function loadPage(page) {
  store.fetchJobs({ page, status: statusFilter.value || undefined })
}

function applyFilter() {
  store.fetchJobs({ page: 1, status: statusFilter.value || undefined })
}

async function deleteJob(id) {
  if (!confirm('Übersetzungsjob wirklich löschen?')) return
  deleting.value = id
  try {
    await store.removeJob(id)
  } finally {
    deleting.value = null
  }
}

function statusLabel(status) {
  const labels = {
    draft: 'Entwurf',
    pending: 'Wartend',
    in_progress: 'In Bearbeitung',
    completed: 'Abgeschlossen',
    failed: 'Fehlgeschlagen',
    cancelled: 'Abgebrochen',
  }
  return labels[status] || status
}

function statusIcon(status) {
  return {
    draft: Clock,
    pending: Clock,
    in_progress: Loader2,
    completed: CheckCircle,
    failed: XCircle,
    cancelled: Ban,
  }[status] || Clock
}

function statusColor(status) {
  return {
    draft: 'text-[var(--color-text-tertiary)]',
    pending: 'text-yellow-500',
    in_progress: 'text-blue-500',
    completed: 'text-green-500',
    failed: 'text-[var(--color-error)]',
    cancelled: 'text-[var(--color-text-tertiary)]',
  }[status] || ''
}

function progressPercent(job) {
  if (!job.total_items) return 0
  return Math.round((job.translated_items / job.total_items) * 100)
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Languages class="w-6 h-6 text-[var(--color-accent)]" />
        <div>
          <h1 class="text-xl font-bold">Übersetzungsjobs</h1>
          <p class="text-sm text-[var(--color-text-secondary)]">Übersetzen Sie Produkt-Attribute und System-Texte mit DeepL</p>
        </div>
      </div>
      <button class="pim-btn pim-btn-primary" @click="router.push('/translation-jobs/create')">
        <Plus class="w-4 h-4" />
        Neuer Job
      </button>
    </div>

    <!-- Filter -->
    <div class="flex items-center gap-3">
      <select class="pim-input text-xs w-48" v-model="statusFilter" @change="applyFilter">
        <option value="">Alle Status</option>
        <option value="draft">Entwurf</option>
        <option value="pending">Wartend</option>
        <option value="in_progress">In Bearbeitung</option>
        <option value="completed">Abgeschlossen</option>
        <option value="failed">Fehlgeschlagen</option>
        <option value="cancelled">Abgebrochen</option>
      </select>
      <button class="pim-btn pim-btn-secondary text-xs" @click="applyFilter">
        <RefreshCw class="w-3.5 h-3.5" />
        Aktualisieren
      </button>
    </div>

    <!-- Table -->
    <div class="pim-card overflow-hidden">
      <div v-if="store.loading" class="p-8 text-center text-sm text-[var(--color-text-tertiary)]">
        Lade...
      </div>
      <div v-else-if="store.jobs.length === 0" class="p-8 text-center text-sm text-[var(--color-text-tertiary)]">
        Keine Übersetzungsjobs vorhanden.
      </div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-[var(--color-border)] text-left text-xs text-[var(--color-text-tertiary)]">
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Sprachen</th>
            <th class="px-4 py-3 font-medium">Scope</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Fortschritt</th>
            <th class="px-4 py-3 font-medium">Erstellt</th>
            <th class="px-4 py-3 font-medium w-24">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="job in store.jobs"
            :key="job.id"
            class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-pointer"
            @click="router.push(`/translation-jobs/${job.id}`)"
          >
            <td class="px-4 py-3 font-medium">{{ job.name }}</td>
            <td class="px-4 py-3">
              <span class="uppercase text-xs">{{ job.source_language }}</span>
              <span class="mx-1 text-[var(--color-text-tertiary)]">→</span>
              <span class="uppercase text-xs font-semibold">{{ job.target_language }}</span>
            </td>
            <td class="px-4 py-3 text-xs">
              {{ job.scope === 'products' ? 'Produkte' : job.scope === 'system' ? 'System' : 'Gemischt' }}
            </td>
            <td class="px-4 py-3">
              <span class="flex items-center gap-1.5 text-xs" :class="statusColor(job.status)">
                <component :is="statusIcon(job.status)" class="w-3.5 h-3.5" :class="{ 'animate-spin': job.status === 'in_progress' }" />
                {{ statusLabel(job.status) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 h-1.5 bg-[var(--color-border)] rounded-full overflow-hidden">
                  <div
                    class="h-full bg-[var(--color-accent)] rounded-full transition-all"
                    :style="{ width: progressPercent(job) + '%' }"
                  />
                </div>
                <span class="text-[11px] text-[var(--color-text-tertiary)] w-16 text-right">
                  {{ job.translated_items }}/{{ job.total_items }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3 text-xs text-[var(--color-text-tertiary)]">
              {{ formatDate(job.created_at) }}
            </td>
            <td class="px-4 py-3" @click.stop>
              <div class="flex items-center gap-1">
                <button
                  class="p-1.5 rounded hover:bg-[var(--color-bg-secondary)]"
                  title="Anzeigen"
                  @click="router.push(`/translation-jobs/${job.id}`)"
                >
                  <Eye class="w-3.5 h-3.5" />
                </button>
                <button
                  v-if="job.status !== 'in_progress'"
                  class="p-1.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-error)]"
                  title="Löschen"
                  :disabled="deleting === job.id"
                  @click="deleteJob(job.id)"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="store.meta.last_page > 1" class="flex justify-center gap-2">
      <button
        v-for="p in store.meta.last_page"
        :key="p"
        class="pim-btn text-xs"
        :class="p === store.meta.current_page ? 'pim-btn-primary' : 'pim-btn-secondary'"
        @click="loadPage(p)"
      >
        {{ p }}
      </button>
    </div>
  </div>
</template>
