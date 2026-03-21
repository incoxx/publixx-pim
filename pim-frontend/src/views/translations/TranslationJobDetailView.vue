<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTranslationJobsStore } from '@/stores/translationJobs'
import {
  ArrowLeft, Play, CheckCircle, XCircle, Clock, Loader2, Ban,
  RefreshCw, Trash2, Languages, Download,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const store = useTranslationJobsStore()

const error = ref('')
const actionLoading = ref(false)
const itemStatusFilter = ref('')
const itemPage = ref(1)
let pollTimer = null

const job = computed(() => store.currentJob?.data || store.currentJob || null)
const items = computed(() => store.currentJob?.items || [])
const itemsMeta = computed(() => store.currentJob?.items_meta || { total: 0, current_page: 1, last_page: 1 })

onMounted(() => {
  loadJob()
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})

async function loadJob() {
  try {
    await store.fetchJob(route.params.id, {
      item_status: itemStatusFilter.value || undefined,
      page: itemPage.value,
      per_page: 50,
    })

    // Auto-poll while in progress
    if (job.value?.status === 'in_progress' || job.value?.status === 'pending') {
      if (!pollTimer) {
        pollTimer = setInterval(loadJob, 5000)
      }
    } else {
      if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
      }
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Laden'
  }
}

async function submitJob() {
  actionLoading.value = true
  error.value = ''
  try {
    await store.submitJob(job.value.id)
    await loadJob()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Absenden'
  } finally {
    actionLoading.value = false
  }
}

async function approveJob() {
  if (!confirm('Alle Übersetzungen freigeben und importieren?')) return
  actionLoading.value = true
  error.value = ''
  try {
    const result = await store.approveJob(job.value.id)
    alert(result.message || 'Übersetzungen importiert.')
    await loadJob()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler bei der Freigabe'
  } finally {
    actionLoading.value = false
  }
}

async function cancelJob() {
  if (!confirm('Job wirklich abbrechen?')) return
  actionLoading.value = true
  try {
    await store.cancelJob(job.value.id)
    await loadJob()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Abbrechen'
  } finally {
    actionLoading.value = false
  }
}

async function retryJob() {
  actionLoading.value = true
  error.value = ''
  try {
    await store.retryJob(job.value.id)
    await loadJob()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Retry'
  } finally {
    actionLoading.value = false
  }
}

async function deleteJob() {
  if (!confirm('Übersetzungsjob wirklich löschen?')) return
  try {
    await store.removeJob(job.value.id)
    router.push('/translation-jobs')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Löschen'
  }
}

function filterItems() {
  itemPage.value = 1
  loadJob()
}

function loadItemPage(page) {
  itemPage.value = page
  loadJob()
}

function statusLabel(status) {
  return { draft: 'Entwurf', pending: 'Wartend', in_progress: 'In Bearbeitung', completed: 'Abgeschlossen', failed: 'Fehlgeschlagen', cancelled: 'Abgebrochen' }[status] || status
}

function statusColor(status) {
  return { draft: 'text-[var(--color-text-tertiary)]', pending: 'text-yellow-500', in_progress: 'text-blue-500', completed: 'text-green-500', failed: 'text-[var(--color-error)]', cancelled: 'text-[var(--color-text-tertiary)]' }[status] || ''
}

function itemStatusLabel(status) {
  return { pending: 'Wartend', translated: 'Übersetzt', failed: 'Fehlgeschlagen' }[status] || status
}

function progressPercent() {
  if (!job.value?.total_items) return 0
  return Math.round((job.value.translated_items / job.value.total_items) * 100)
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="space-y-6 max-w-6xl mx-auto">
    <!-- Back navigation -->
    <button class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]" @click="router.push('/translation-jobs')">
      <ArrowLeft class="w-4 h-4" />
      Zurück zu Übersetzungsjobs
    </button>

    <div v-if="store.loading && !job" class="text-center text-sm text-[var(--color-text-tertiary)] py-12">Lade...</div>

    <template v-else-if="job">
      <!-- Header -->
      <div class="pim-card p-6 space-y-4">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-xl font-bold">{{ job.name }}</h1>
            <div class="flex items-center gap-4 mt-2 text-sm text-[var(--color-text-secondary)]">
              <span>
                <span class="uppercase font-medium">{{ job.source_language }}</span>
                <span class="mx-1">→</span>
                <span class="uppercase font-semibold text-[var(--color-accent)]">{{ job.target_language }}</span>
              </span>
              <span>{{ job.scope === 'products' ? 'Produkte' : job.scope === 'system' ? 'System' : 'Gemischt' }}</span>
              <span :class="statusColor(job.status)" class="flex items-center gap-1">
                <component :is="job.status === 'in_progress' ? Loader2 : (job.status === 'completed' ? CheckCircle : (job.status === 'failed' ? XCircle : Clock))" class="w-4 h-4" :class="{ 'animate-spin': job.status === 'in_progress' }" />
                {{ statusLabel(job.status) }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <button
              v-if="job.status === 'draft' || job.status === 'failed'"
              class="pim-btn pim-btn-primary text-xs"
              :disabled="actionLoading"
              @click="submitJob"
            >
              <Play class="w-3.5 h-3.5" />
              {{ job.status === 'failed' ? 'Erneut senden' : 'An DeepL senden' }}
            </button>
            <button
              v-if="job.status === 'completed' && job.translated_items > 0"
              class="pim-btn text-xs bg-green-500 text-white hover:bg-green-600"
              :disabled="actionLoading"
              @click="approveJob"
            >
              <CheckCircle class="w-3.5 h-3.5" />
              Freigeben & Importieren
            </button>
            <button
              v-if="job.status === 'completed' || job.status === 'failed'"
              class="pim-btn pim-btn-secondary text-xs"
              :disabled="actionLoading"
              @click="retryJob"
            >
              <RefreshCw class="w-3.5 h-3.5" />
              Fehlgeschlagene wiederholen
            </button>
            <button
              v-if="job.status === 'pending' || job.status === 'in_progress'"
              class="pim-btn pim-btn-secondary text-xs"
              :disabled="actionLoading"
              @click="cancelJob"
            >
              <Ban class="w-3.5 h-3.5" />
              Abbrechen
            </button>
            <button
              v-if="job.status !== 'in_progress'"
              class="pim-btn text-xs bg-[var(--color-error-light)] text-[var(--color-error)] hover:bg-[var(--color-error)] hover:text-white"
              @click="deleteJob"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Progress -->
        <div class="space-y-2">
          <div class="flex justify-between text-xs text-[var(--color-text-tertiary)]">
            <span>{{ job.translated_items }} übersetzt, {{ job.failed_items }} fehlgeschlagen von {{ job.total_items }} gesamt</span>
            <span>{{ progressPercent() }}%</span>
          </div>
          <div class="h-2 bg-[var(--color-border)] rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all"
              :class="job.failed_items > 0 && job.translated_items === 0 ? 'bg-[var(--color-error)]' : 'bg-[var(--color-accent)]'"
              :style="{ width: progressPercent() + '%' }"
            />
          </div>
        </div>

        <!-- Metadata -->
        <div class="flex gap-6 text-xs text-[var(--color-text-tertiary)]">
          <span>Erstellt: {{ formatDate(job.created_at) }}</span>
          <span v-if="job.submitted_at">Gesendet: {{ formatDate(job.submitted_at) }}</span>
          <span v-if="job.completed_at">Abgeschlossen: {{ formatDate(job.completed_at) }}</span>
          <span v-if="job.created_by">Von: {{ job.created_by.name || job.created_by.email }}</span>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">
        {{ error }}
      </div>

      <!-- Items -->
      <div class="pim-card overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--color-border)] flex items-center justify-between">
          <h2 class="text-sm font-semibold">Übersetzungs-Einträge</h2>
          <select class="pim-input text-xs w-40" v-model="itemStatusFilter" @change="filterItems">
            <option value="">Alle Status</option>
            <option value="pending">Wartend</option>
            <option value="translated">Übersetzt</option>
            <option value="failed">Fehlgeschlagen</option>
          </select>
        </div>

        <div v-if="items.length === 0" class="p-8 text-center text-sm text-[var(--color-text-tertiary)]">
          Keine Einträge.
        </div>
        <table v-else class="w-full text-xs">
          <thead>
            <tr class="border-b border-[var(--color-border)] text-left text-[var(--color-text-tertiary)]">
              <th class="px-4 py-2 font-medium">Typ</th>
              <th class="px-4 py-2 font-medium">Quelltext</th>
              <th class="px-4 py-2 font-medium">Übersetzung</th>
              <th class="px-4 py-2 font-medium w-24">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in items"
              :key="item.id"
              class="border-b border-[var(--color-border)]"
            >
              <td class="px-4 py-2 text-[var(--color-text-tertiary)]">
                {{ item.entity_type }}
              </td>
              <td class="px-4 py-2 max-w-xs truncate" :title="item.source_text">
                {{ item.source_text }}
              </td>
              <td class="px-4 py-2 max-w-xs truncate" :title="item.translated_text">
                <span v-if="item.translated_text">{{ item.translated_text }}</span>
                <span v-else class="text-[var(--color-text-tertiary)] italic">—</span>
              </td>
              <td class="px-4 py-2">
                <span
                  class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full"
                  :class="{
                    'bg-yellow-100 text-yellow-700': item.status === 'pending',
                    'bg-green-100 text-green-700': item.status === 'translated',
                    'bg-red-100 text-red-700': item.status === 'failed',
                  }"
                >
                  {{ itemStatusLabel(item.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Items Pagination -->
        <div v-if="itemsMeta.last_page > 1" class="px-4 py-3 border-t border-[var(--color-border)] flex justify-center gap-2">
          <button
            v-for="p in itemsMeta.last_page"
            :key="p"
            class="pim-btn text-xs"
            :class="p === itemsMeta.current_page ? 'pim-btn-primary' : 'pim-btn-secondary'"
            @click="loadItemPage(p)"
          >
            {{ p }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
