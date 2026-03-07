<script setup>
import { ref, onMounted, computed } from 'vue'
import {
  Play, Plus, Trash2, Edit3, Download, FileJson, FileSpreadsheet,
  FileText, FileCode, Clock, CheckCircle, XCircle, Loader2,
  ChevronDown, ChevronUp, Save, X,
} from 'lucide-vue-next'
import exportJobsApi from '@/api/exportJobs'
import searchProfilesApi from '@/api/searchProfiles'
import jsonExportImportApi from '@/api/jsonExportImport'

// --- State ---
const jobs = ref([])
const loading = ref(true)
const error = ref('')
const executing = ref({}) // jobId → true/false
const expandedJob = ref(null)

// --- Create/Edit Modal ---
const showModal = ref(false)
const editingJob = ref(null)
const form = ref(createEmptyForm())

// --- Search Profiles ---
const searchProfiles = ref([])

// --- Available Sections (JSON) ---
const availableSections = ref([])

function createEmptyForm() {
  return {
    name: '',
    description: '',
    format: 'json',
    search_profile_id: null,
    sections: [],
    filters: {
      status: '',
      product_type: '',
      search_text: '',
    },
    is_active: true,
    is_shared: true,
  }
}

// --- Load ---
onMounted(async () => {
  await Promise.all([loadJobs(), loadSearchProfiles(), loadSections()])
})

async function loadJobs() {
  loading.value = true
  try {
    const { data } = await exportJobsApi.list()
    jobs.value = data.data || data
  } catch (e) {
    error.value = 'Export-Jobs konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

async function loadSearchProfiles() {
  try {
    const { data } = await searchProfilesApi.list()
    searchProfiles.value = data.data || data
  } catch (e) { /* ignore */ }
}

async function loadSections() {
  try {
    const { data } = await jsonExportImportApi.sections()
    availableSections.value = data.data || data
  } catch (e) { /* ignore */ }
}

// --- Actions ---
function openCreate() {
  editingJob.value = null
  form.value = createEmptyForm()
  showModal.value = true
}

function openEdit(job) {
  editingJob.value = job
  form.value = {
    name: job.name,
    description: job.description || '',
    format: job.format,
    search_profile_id: job.search_profile_id,
    sections: job.sections || [],
    filters: job.filters || { status: '', product_type: '', search_text: '' },
    is_active: job.is_active,
    is_shared: job.is_shared,
  }
  showModal.value = true
}

async function saveJob() {
  error.value = ''
  const payload = {
    ...form.value,
    filters: Object.fromEntries(Object.entries(form.value.filters).filter(([, v]) => v)),
    sections: form.value.sections.length > 0 ? form.value.sections : null,
    search_profile_id: form.value.search_profile_id || null,
  }

  try {
    if (editingJob.value) {
      await exportJobsApi.update(editingJob.value.id, payload)
    } else {
      await exportJobsApi.create(payload)
    }
    showModal.value = false
    await loadJobs()
  } catch (e) {
    error.value = e.response?.data?.message || 'Speichern fehlgeschlagen'
  }
}

async function deleteJob(job) {
  if (!confirm(`Job "${job.name}" wirklich löschen?`)) return
  try {
    await exportJobsApi.remove(job.id)
    await loadJobs()
  } catch (e) {
    error.value = 'Löschen fehlgeschlagen'
  }
}

async function executeJob(job) {
  executing.value = { ...executing.value, [job.id]: true }
  error.value = ''
  try {
    await exportJobsApi.execute(job.id)
    await loadJobs()
  } catch (e) {
    error.value = e.response?.data?.message || `Ausführung von "${job.name}" fehlgeschlagen`
  } finally {
    executing.value = { ...executing.value, [job.id]: false }
  }
}

async function downloadResult(job) {
  try {
    const response = await exportJobsApi.download(job.id)
    const blob = new Blob([response.data])
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = job.last_output_path?.split('/').pop() || `export.${job.format === 'excel' ? 'xlsx' : job.format}`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 200)
  } catch (e) {
    error.value = 'Download fehlgeschlagen'
  }
}

function toggleExpand(jobId) {
  expandedJob.value = expandedJob.value === jobId ? null : jobId
}

const formatIcon = (fmt) => ({
  json: FileJson,
  excel: FileSpreadsheet,
  csv: FileText,
  xml: FileCode,
}[fmt] || FileJson)

const formatLabel = (fmt) => ({
  json: 'JSON',
  excel: 'Excel',
  csv: 'CSV',
  xml: 'XML',
}[fmt] || fmt)

const statusColor = (status) => ({
  completed: 'text-green-600',
  running: 'text-blue-500',
  failed: 'text-red-500',
  pending: 'text-yellow-500',
}[status] || 'text-[var(--color-text-tertiary)]')

const statusIcon = (status) => ({
  completed: CheckCircle,
  running: Loader2,
  failed: XCircle,
  pending: Clock,
}[status] || Clock)

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatBytes(bytes) {
  if (!bytes) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++ }
  return `${Math.round(bytes * 100) / 100} ${units[i]}`
}

function toggleSection(section) {
  const idx = form.value.sections.indexOf(section)
  if (idx >= 0) {
    form.value.sections.splice(idx, 1)
  } else {
    form.value.sections.push(section)
  }
}

const sectionLabel = (s) => ({
  unit_groups: 'Einheitengruppen',
  units: 'Einheiten',
  attribute_views: 'Attributsichten',
  attribute_groups: 'Attributgruppen',
  value_lists: 'Wertelisten',
  attributes: 'Attribute',
  product_types: 'Produkttypen',
  price_types: 'Preisarten',
  relation_types: 'Beziehungstypen',
  hierarchies: 'Hierarchien',
  hierarchy_attribute_assignments: 'Hierarchie-Attribute',
  products: 'Produkte',
  product_attribute_values: 'Produktwerte',
  variants: 'Varianten',
  product_hierarchy_assignments: 'Produkt-Hierarchien',
  product_relations: 'Produktbeziehungen',
  prices: 'Preise',
  media_assignments: 'Medien',
}[s] || s)
</script>

<template>
  <div class="space-y-4 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Export-Jobs</h2>
      <button class="pim-btn pim-btn-primary text-xs" @click="openCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neuer Job
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-red-50 text-red-600 text-xs flex items-center justify-between">
      <span>{{ error }}</span>
      <button @click="error = ''" class="text-red-400 hover:text-red-600"><X class="w-3.5 h-3.5" /></button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <Loader2 class="w-6 h-6 animate-spin text-[var(--color-text-tertiary)]" />
    </div>

    <!-- Empty state -->
    <div v-else-if="jobs.length === 0" class="pim-card p-12 text-center">
      <Clock class="w-10 h-10 text-[var(--color-text-tertiary)] mx-auto mb-3" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-secondary)]">Noch keine Export-Jobs vorhanden.</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle einen Job um Exporte zu konfigurieren und wiederholt auszuführen.</p>
      <button class="pim-btn pim-btn-primary text-xs mt-4" @click="openCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Ersten Job anlegen
      </button>
    </div>

    <!-- Job List -->
    <div v-else class="space-y-2">
      <div
        v-for="job in jobs"
        :key="job.id"
        class="pim-card overflow-hidden"
      >
        <!-- Job Row -->
        <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-[var(--color-bg)]" @click="toggleExpand(job.id)">
          <!-- Format icon -->
          <component
            :is="formatIcon(job.format)"
            class="w-5 h-5 shrink-0 text-[var(--color-text-tertiary)]"
            :stroke-width="1.75"
          />

          <!-- Name + Description -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ job.name }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] font-mono uppercase">
                {{ formatLabel(job.format) }}
              </span>
              <span v-if="!job.is_active" class="text-[10px] px-1.5 py-0.5 rounded bg-yellow-50 text-yellow-600">
                Inaktiv
              </span>
            </div>
            <p v-if="job.description" class="text-[11px] text-[var(--color-text-tertiary)] truncate mt-0.5">{{ job.description }}</p>
          </div>

          <!-- Status -->
          <div v-if="job.last_status" class="flex items-center gap-1.5 shrink-0">
            <component
              :is="statusIcon(job.last_status)"
              class="w-3.5 h-3.5"
              :class="[statusColor(job.last_status), job.last_status === 'running' ? 'animate-spin' : '']"
              :stroke-width="2"
            />
            <span class="text-[11px]" :class="statusColor(job.last_status)">
              {{ formatDate(job.last_run_at) }}
            </span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 shrink-0" @click.stop>
            <button
              class="p-1.5 rounded-md hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-green-600 transition-colors"
              :disabled="executing[job.id]"
              @click="executeJob(job)"
              title="Ausführen"
            >
              <Loader2 v-if="executing[job.id]" class="w-4 h-4 animate-spin" />
              <Play v-else class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button
              v-if="job.last_status === 'completed' && job.last_output_path"
              class="p-1.5 rounded-md hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-blue-600 transition-colors"
              @click="downloadResult(job)"
              title="Herunterladen"
            >
              <Download class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button
              class="p-1.5 rounded-md hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] transition-colors"
              @click="openEdit(job)"
              title="Bearbeiten"
            >
              <Edit3 class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button
              class="p-1.5 rounded-md hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-red-500 transition-colors"
              @click="deleteJob(job)"
              title="Löschen"
            >
              <Trash2 class="w-4 h-4" :stroke-width="1.75" />
            </button>
          </div>

          <!-- Expand chevron -->
          <component
            :is="expandedJob === job.id ? ChevronUp : ChevronDown"
            class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0"
            :stroke-width="1.75"
          />
        </div>

        <!-- Expanded Details -->
        <div v-if="expandedJob === job.id" class="border-t border-[var(--color-border)] px-4 py-3 bg-[var(--color-bg)] text-xs space-y-2">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <span class="text-[var(--color-text-tertiary)]">Format</span>
              <p class="font-medium text-[var(--color-text-primary)]">{{ formatLabel(job.format) }}</p>
            </div>
            <div>
              <span class="text-[var(--color-text-tertiary)]">Letzte Dauer</span>
              <p class="font-medium text-[var(--color-text-primary)]">{{ job.last_duration_seconds ? `${job.last_duration_seconds}s` : '-' }}</p>
            </div>
            <div>
              <span class="text-[var(--color-text-tertiary)]">Dateigröße</span>
              <p class="font-medium text-[var(--color-text-primary)]">{{ formatBytes(job.last_result?.size_bytes) }}</p>
            </div>
            <div>
              <span class="text-[var(--color-text-tertiary)]">Aktiv</span>
              <p class="font-medium text-[var(--color-text-primary)]">{{ job.is_active ? 'Ja' : 'Nein' }}</p>
            </div>
          </div>

          <div v-if="job.filters && Object.keys(job.filters).length > 0">
            <span class="text-[var(--color-text-tertiary)]">Filter</span>
            <p class="font-mono text-[var(--color-text-secondary)] mt-0.5">{{ JSON.stringify(job.filters) }}</p>
          </div>

          <div v-if="job.sections && job.sections.length > 0">
            <span class="text-[var(--color-text-tertiary)]">Sektionen</span>
            <div class="flex flex-wrap gap-1 mt-0.5">
              <span v-for="s in job.sections" :key="s" class="px-1.5 py-0.5 rounded bg-[var(--color-surface)] text-[var(--color-text-secondary)] text-[10px]">
                {{ sectionLabel(s) }}
              </span>
            </div>
          </div>

          <div v-if="job.last_error">
            <span class="text-red-500">Fehler</span>
            <p class="text-red-600 mt-0.5">{{ job.last_error }}</p>
          </div>

          <div class="pt-1">
            <span class="text-[var(--color-text-tertiary)]">ID</span>
            <p class="font-mono text-[var(--color-text-tertiary)] select-all">{{ job.id }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- CLI Hinweis -->
    <div class="pim-card p-4 bg-[var(--color-bg)]">
      <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-2">REST / CLI</p>
      <div class="space-y-1 text-[10px] font-mono text-[var(--color-text-tertiary)]">
        <p>curl -X POST /api/v1/export-jobs/{id}/execute -H "Authorization: Bearer {token}"</p>
        <p>php artisan pim:export-job {job-id}</p>
        <p>php artisan pim:export-job --list</p>
        <p>php artisan pim:export-job --create --name="Mein Export" --format=json --filter-status=active</p>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showModal = false">
        <div class="bg-[var(--color-surface)] rounded-xl shadow-2xl w-full max-w-xl mx-4 max-h-[85vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
              {{ editingJob ? 'Job bearbeiten' : 'Neuer Export-Job' }}
            </h3>
            <button @click="showModal = false" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name *</label>
              <input v-model="form.name" class="pim-input text-xs w-full" placeholder="z.B. Elektrowerkzeuge Export" />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beschreibung</label>
              <input v-model="form.description" class="pim-input text-xs w-full" placeholder="z.B. Aktive Produkte mit kaufmännischen Daten" />
            </div>

            <!-- Format -->
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Format *</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button
                  v-for="fmt in ['json', 'excel', 'csv', 'xml']"
                  :key="fmt"
                  :class="[
                    'flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 transition-colors cursor-pointer',
                    form.format === fmt
                      ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]'
                      : 'border-[var(--color-border)] hover:border-[var(--color-accent)]/50',
                  ]"
                  @click="form.format = fmt"
                >
                  <component
                    :is="formatIcon(fmt)"
                    class="w-5 h-5"
                    :class="form.format === fmt ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-tertiary)]'"
                    :stroke-width="1.5"
                  />
                  <span class="text-[11px] font-medium" :class="form.format === fmt ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-secondary)]'">
                    {{ formatLabel(fmt) }}
                  </span>
                </button>
              </div>
            </div>

            <!-- Suchprofil -->
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Suchprofil (optional)</label>
              <select v-model="form.search_profile_id" class="pim-input text-xs w-full">
                <option :value="null">-- Kein Suchprofil --</option>
                <option v-for="sp in searchProfiles" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
              </select>
            </div>

            <!-- Filter -->
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Filter</label>
              <div class="grid grid-cols-3 gap-2">
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Status</label>
                  <select v-model="form.filters.status" class="pim-input text-xs w-full">
                    <option value="">Alle</option>
                    <option value="active">Aktiv</option>
                    <option value="draft">Entwurf</option>
                    <option value="inactive">Inaktiv</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Produkttyp</label>
                  <input v-model="form.filters.product_type" class="pim-input text-xs w-full" placeholder="techn. Name" />
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Suche</label>
                  <input v-model="form.filters.search_text" class="pim-input text-xs w-full" placeholder="SKU, Name..." />
                </div>
              </div>
            </div>

            <!-- Sections (nur bei JSON) -->
            <div v-if="form.format === 'json'">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                Sektionen
                <span class="text-[10px] text-[var(--color-text-tertiary)] font-normal ml-1">(leer = alle)</span>
              </label>
              <div class="grid grid-cols-3 gap-1.5">
                <label
                  v-for="s in availableSections"
                  :key="s"
                  class="flex items-center gap-1.5 text-[11px] cursor-pointer hover:bg-[var(--color-bg)] px-1.5 py-1 rounded"
                >
                  <input
                    type="checkbox"
                    :checked="form.sections.includes(s)"
                    @change="toggleSection(s)"
                    class="rounded"
                  />
                  {{ sectionLabel(s) }}
                </label>
              </div>
            </div>

            <!-- Active / Shared -->
            <div class="flex gap-6">
              <label class="flex items-center gap-2 text-xs cursor-pointer">
                <input type="checkbox" v-model="form.is_active" class="rounded" />
                Aktiv
              </label>
              <label class="flex items-center gap-2 text-xs cursor-pointer">
                <input type="checkbox" v-model="form.is_shared" class="rounded" />
                Für alle Benutzer sichtbar
              </label>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="showModal = false">
              Abbrechen
            </button>
            <button class="pim-btn pim-btn-primary text-xs" @click="saveJob" :disabled="!form.name">
              <Save class="w-3.5 h-3.5" :stroke-width="2" />
              {{ editingJob ? 'Speichern' : 'Anlegen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
