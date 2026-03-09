<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import {
  Play, Plus, Trash2, Edit3, Download, FileJson, FileSpreadsheet,
  FileText, FileCode, Clock, CheckCircle, XCircle, Loader2,
  ChevronDown, ChevronUp, Save, X, Send, HardDrive, Globe, Server,
  History, FolderOpen, Timer,
} from 'lucide-vue-next'
import exportJobsApi from '@/api/exportJobs'
import exportFilesApi from '@/api/exportFiles'
import searchProfilesApi from '@/api/searchProfiles'
import jsonExportImportApi from '@/api/jsonExportImport'

// --- State ---
const jobs = ref([])
const loading = ref(true)
const error = ref('')
const executing = ref({}) // jobId → true/false
const expandedJob = ref(null)
const expandedTab = ref('details') // 'details' | 'logs'

// --- Create/Edit Modal ---
const showModal = ref(false)
const editingJob = ref(null)
const form = ref(createEmptyForm())

// --- Search Profiles ---
const searchProfiles = ref([])

// --- Available Sections (JSON) ---
const availableSections = ref([])

// --- Logs ---
const jobLogs = ref({}) // jobId → logs[]
const loadingLogs = ref({})

// --- Filesystem Viewer ---
const showFilesViewer = ref(false)
const exportFiles = ref([])
const loadingFiles = ref(false)

// --- CRON Presets ---
const cronPresets = [
  { label: 'Stündlich', value: '0 * * * *' },
  { label: 'Täglich 02:00', value: '0 2 * * *' },
  { label: 'Täglich 06:00', value: '0 6 * * *' },
  { label: 'Wöchentlich Mo 03:00', value: '0 3 * * 1' },
  { label: 'Monatlich 1. um 04:00', value: '0 4 1 * *' },
  { label: 'Benutzerdefiniert', value: '__custom__' },
]

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
    cron_expression: null,
    delivery_type: null,
    delivery_config: {},
    is_active: true,
    is_shared: true,
  }
}

function createEmptyDeliveryConfig(type) {
  if (type === 'filesystem') return { disk: 'exports', path: '' }
  if (type === 'sftp') return { host: '', port: 22, username: '', password: '', private_key: '', remote_path: '/' }
  if (type === 'http') return { url: '', method: 'POST', headers: {} }
  return {}
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

async function loadLogs(jobId) {
  if (loadingLogs.value[jobId]) return
  loadingLogs.value = { ...loadingLogs.value, [jobId]: true }
  try {
    const { data } = await exportJobsApi.logs(jobId)
    jobLogs.value = { ...jobLogs.value, [jobId]: data.data || data }
  } catch (e) { /* ignore */ }
  finally {
    loadingLogs.value = { ...loadingLogs.value, [jobId]: false }
  }
}

async function loadExportFiles() {
  loadingFiles.value = true
  try {
    const { data } = await exportFilesApi.list()
    exportFiles.value = data.data || data
  } catch (e) {
    error.value = 'Export-Dateien konnten nicht geladen werden'
  } finally {
    loadingFiles.value = false
  }
}

async function deleteExportFile(name) {
  if (!confirm(`Datei "${name}" wirklich löschen?`)) return
  try {
    await exportFilesApi.remove(name)
    await loadExportFiles()
  } catch (e) {
    error.value = 'Datei konnte nicht gelöscht werden'
  }
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
    cron_expression: job.cron_expression || null,
    delivery_type: job.delivery_type || null,
    delivery_config: job.delivery_config || {},
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
    cron_expression: form.value.cron_expression || null,
    delivery_type: form.value.delivery_type || null,
    delivery_config: form.value.delivery_type ? form.value.delivery_config : null,
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
    // Logs neu laden falls geöffnet
    if (expandedJob.value === job.id && expandedTab.value === 'logs') {
      await loadLogs(job.id)
    }
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
  if (expandedJob.value === jobId) {
    expandedJob.value = null
  } else {
    expandedJob.value = jobId
    expandedTab.value = 'details'
  }
}

function switchTab(jobId, tab) {
  expandedTab.value = tab
  if (tab === 'logs' && !jobLogs.value[jobId]) {
    loadLogs(jobId)
  }
}

function onDeliveryTypeChange() {
  form.value.delivery_config = createEmptyDeliveryConfig(form.value.delivery_type)
}

function applyCronPreset(preset) {
  if (preset === '__custom__') return
  form.value.cron_expression = preset
}

function addHeader() {
  if (!form.value.delivery_config.headers) form.value.delivery_config.headers = {}
  form.value.delivery_config.headers[''] = ''
}

function removeHeader(key) {
  const h = { ...form.value.delivery_config.headers }
  delete h[key]
  form.value.delivery_config.headers = h
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

const deliveryLabel = (type) => ({
  filesystem: 'Filesystem',
  sftp: 'SFTP',
  http: 'HTTP REST',
}[type] || '-')

const deliveryIcon = (type) => ({
  filesystem: HardDrive,
  sftp: Server,
  http: Globe,
}[type] || Send)

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

function formatTimestamp(ts) {
  if (!ts) return '-'
  return new Date(ts * 1000).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
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
      <div class="flex items-center gap-2">
        <button
          class="pim-btn pim-btn-secondary text-xs"
          @click="showFilesViewer = !showFilesViewer; if (showFilesViewer) loadExportFiles()"
        >
          <FolderOpen class="w-3.5 h-3.5" :stroke-width="2" />
          Dateien
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="openCreate">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          Neuer Job
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-red-50 text-red-600 text-xs flex items-center justify-between">
      <span>{{ error }}</span>
      <button @click="error = ''" class="text-red-400 hover:text-red-600"><X class="w-3.5 h-3.5" /></button>
    </div>

    <!-- Filesystem Viewer -->
    <div v-if="showFilesViewer" class="pim-card overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
        <div class="flex items-center gap-2">
          <FolderOpen class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          <span class="text-sm font-medium text-[var(--color-text-primary)]">Export-Dateien</span>
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">
            {{ exportFiles.length }} Dateien
          </span>
        </div>
        <button @click="showFilesViewer = false" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
          <X class="w-4 h-4" />
        </button>
      </div>

      <div v-if="loadingFiles" class="flex justify-center py-6">
        <Loader2 class="w-5 h-5 animate-spin text-[var(--color-text-tertiary)]" />
      </div>

      <div v-else-if="exportFiles.length === 0" class="px-4 py-6 text-center text-xs text-[var(--color-text-tertiary)]">
        Keine Export-Dateien vorhanden.
      </div>

      <div v-else class="divide-y divide-[var(--color-border)]">
        <div
          v-for="file in exportFiles"
          :key="file.name"
          class="flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-[var(--color-bg)]"
        >
          <FileText class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
          <span class="flex-1 font-mono text-[var(--color-text-primary)] truncate">{{ file.name }}</span>
          <span class="text-[var(--color-text-tertiary)] shrink-0">{{ formatBytes(file.size) }}</span>
          <span class="text-[var(--color-text-tertiary)] shrink-0">{{ formatTimestamp(file.last_modified) }}</span>
          <button
            class="p-1 rounded hover:bg-red-50 text-[var(--color-text-tertiary)] hover:text-red-500 transition-colors shrink-0"
            @click="deleteExportFile(file.name)"
            title="Löschen"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
          </button>
        </div>
      </div>
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
              <span v-if="job.delivery_type" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 flex items-center gap-0.5">
                <component :is="deliveryIcon(job.delivery_type)" class="w-2.5 h-2.5" :stroke-width="2" />
                {{ deliveryLabel(job.delivery_type) }}
              </span>
              <span v-if="job.cron_expression" class="text-[10px] px-1.5 py-0.5 rounded bg-purple-50 text-purple-600 flex items-center gap-0.5">
                <Timer class="w-2.5 h-2.5" :stroke-width="2" />
                CRON
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
        <div v-if="expandedJob === job.id" class="border-t border-[var(--color-border)]">
          <!-- Tab Bar -->
          <div class="flex border-b border-[var(--color-border)] px-4 bg-[var(--color-bg)]">
            <button
              :class="['px-3 py-2 text-[11px] font-medium border-b-2 transition-colors', expandedTab === 'details' ? 'border-[var(--color-accent)] text-[var(--color-accent)]' : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]']"
              @click="switchTab(job.id, 'details')"
            >Details</button>
            <button
              :class="['px-3 py-2 text-[11px] font-medium border-b-2 transition-colors', expandedTab === 'logs' ? 'border-[var(--color-accent)] text-[var(--color-accent)]' : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]']"
              @click="switchTab(job.id, 'logs')"
            >
              <span class="flex items-center gap-1">
                <History class="w-3 h-3" :stroke-width="2" />
                Protokoll
              </span>
            </button>
          </div>

          <!-- Details Tab -->
          <div v-if="expandedTab === 'details'" class="px-4 py-3 bg-[var(--color-bg)] text-xs space-y-2">
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

            <!-- Delivery Info -->
            <div v-if="job.delivery_type" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <span class="text-[var(--color-text-tertiary)]">Zustellung</span>
                <p class="font-medium text-[var(--color-text-primary)] flex items-center gap-1">
                  <component :is="deliveryIcon(job.delivery_type)" class="w-3 h-3" :stroke-width="2" />
                  {{ deliveryLabel(job.delivery_type) }}
                </p>
              </div>
              <div v-if="job.last_result?.delivery_status">
                <span class="text-[var(--color-text-tertiary)]">Delivery-Status</span>
                <p :class="['font-medium', job.last_result.delivery_status === 'delivered' ? 'text-green-600' : job.last_result.delivery_status === 'failed' ? 'text-red-500' : 'text-[var(--color-text-primary)]']">
                  {{ job.last_result.delivery_status === 'delivered' ? 'Zugestellt' : job.last_result.delivery_status === 'failed' ? 'Fehlgeschlagen' : job.last_result.delivery_status }}
                </p>
              </div>
            </div>

            <!-- CRON Info -->
            <div v-if="job.cron_expression" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <span class="text-[var(--color-text-tertiary)]">CRON</span>
                <p class="font-mono font-medium text-[var(--color-text-primary)]">{{ job.cron_expression }}</p>
              </div>
              <div>
                <span class="text-[var(--color-text-tertiary)]">Nächste Ausführung</span>
                <p class="font-medium text-[var(--color-text-primary)]">{{ formatDate(job.next_run_at) }}</p>
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

          <!-- Logs Tab -->
          <div v-if="expandedTab === 'logs'" class="px-4 py-3 bg-[var(--color-bg)] text-xs">
            <div v-if="loadingLogs[job.id]" class="flex justify-center py-4">
              <Loader2 class="w-4 h-4 animate-spin text-[var(--color-text-tertiary)]" />
            </div>

            <div v-else-if="!jobLogs[job.id] || jobLogs[job.id].length === 0" class="text-center py-4 text-[var(--color-text-tertiary)]">
              Noch keine Ausführungen protokolliert.
            </div>

            <div v-else class="space-y-0">
              <table class="w-full text-[11px]">
                <thead>
                  <tr class="text-left text-[var(--color-text-tertiary)] border-b border-[var(--color-border)]">
                    <th class="pb-1.5 font-medium">Status</th>
                    <th class="pb-1.5 font-medium">Gestartet</th>
                    <th class="pb-1.5 font-medium">Dauer</th>
                    <th class="pb-1.5 font-medium">Größe</th>
                    <th class="pb-1.5 font-medium">Zustellung</th>
                    <th class="pb-1.5 font-medium">Fehler</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="log in jobLogs[job.id]"
                    :key="log.id"
                    class="border-b border-[var(--color-border)]/50"
                  >
                    <td class="py-1.5">
                      <span class="flex items-center gap-1" :class="statusColor(log.status)">
                        <component :is="statusIcon(log.status)" class="w-3 h-3" :stroke-width="2" />
                        {{ log.status }}
                      </span>
                    </td>
                    <td class="py-1.5 text-[var(--color-text-secondary)]">{{ formatDate(log.started_at) }}</td>
                    <td class="py-1.5 text-[var(--color-text-secondary)]">{{ log.duration_seconds ? `${log.duration_seconds}s` : '-' }}</td>
                    <td class="py-1.5 text-[var(--color-text-secondary)]">{{ formatBytes(log.file_size_bytes) }}</td>
                    <td class="py-1.5">
                      <span v-if="log.delivery_status" :class="log.delivery_status === 'delivered' ? 'text-green-600' : log.delivery_status === 'failed' ? 'text-red-500' : 'text-yellow-500'">
                        {{ log.delivery_status === 'delivered' ? 'OK' : log.delivery_status === 'failed' ? 'Fehler' : log.delivery_status }}
                      </span>
                      <span v-else class="text-[var(--color-text-tertiary)]">-</span>
                    </td>
                    <td class="py-1.5 text-red-500 truncate max-w-[200px]" :title="log.error || log.delivery_error || ''">
                      {{ log.error || log.delivery_error || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
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
        <p>php artisan pim:export-job --run-scheduled</p>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showModal = false">
        <div class="bg-[var(--color-surface)] rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-y-auto">
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

            <!-- Divider -->
            <div class="border-t border-[var(--color-border)] pt-4">
              <p class="text-[12px] font-semibold text-[var(--color-text-primary)] mb-3 flex items-center gap-1.5">
                <Timer class="w-3.5 h-3.5" :stroke-width="2" />
                Zeitplanung (optional)
              </p>

              <!-- CRON Expression -->
              <div class="space-y-2">
                <div class="flex gap-2">
                  <select class="pim-input text-xs flex-1" @change="applyCronPreset($event.target.value); $event.target.value = ''">
                    <option value="">Vorlage wählen...</option>
                    <option v-for="p in cronPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                  </select>
                  <input
                    v-model="form.cron_expression"
                    class="pim-input text-xs flex-1 font-mono"
                    placeholder="z.B. 0 2 * * *"
                  />
                  <button
                    v-if="form.cron_expression"
                    class="p-1.5 rounded hover:bg-red-50 text-[var(--color-text-tertiary)] hover:text-red-500"
                    @click="form.cron_expression = null"
                    title="Zeitplan entfernen"
                  >
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
                <p v-if="form.cron_expression" class="text-[10px] text-[var(--color-text-tertiary)]">
                  Cron: <span class="font-mono">{{ form.cron_expression }}</span>
                </p>
              </div>
            </div>

            <!-- Delivery -->
            <div class="border-t border-[var(--color-border)] pt-4">
              <p class="text-[12px] font-semibold text-[var(--color-text-primary)] mb-3 flex items-center gap-1.5">
                <Send class="w-3.5 h-3.5" :stroke-width="2" />
                Zustellung (optional)
              </p>

              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Zustellungsart</label>
                <select v-model="form.delivery_type" class="pim-input text-xs w-full" @change="onDeliveryTypeChange()">
                  <option :value="null">Keine (nur Download)</option>
                  <option value="filesystem">Filesystem</option>
                  <option value="sftp">SFTP</option>
                  <option value="http">HTTP REST</option>
                </select>
              </div>

              <!-- Filesystem Config -->
              <div v-if="form.delivery_type === 'filesystem'" class="mt-3 space-y-2 p-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)]">
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Disk</label>
                  <input v-model="form.delivery_config.disk" class="pim-input text-xs w-full font-mono" placeholder="exports" />
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Pfad (optional)</label>
                  <input v-model="form.delivery_config.path" class="pim-input text-xs w-full font-mono" placeholder="z.B. kunden/elektro" />
                </div>
              </div>

              <!-- SFTP Config -->
              <div v-if="form.delivery_type === 'sftp'" class="mt-3 space-y-2 p-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)]">
                <div class="grid grid-cols-3 gap-2">
                  <div class="col-span-2">
                    <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Host *</label>
                    <input v-model="form.delivery_config.host" class="pim-input text-xs w-full" placeholder="sftp.kunde.de" />
                  </div>
                  <div>
                    <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Port</label>
                    <input v-model.number="form.delivery_config.port" type="number" class="pim-input text-xs w-full" placeholder="22" />
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Benutzername *</label>
                    <input v-model="form.delivery_config.username" class="pim-input text-xs w-full" />
                  </div>
                  <div>
                    <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Passwort</label>
                    <input v-model="form.delivery_config.password" type="password" class="pim-input text-xs w-full" />
                  </div>
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Remote-Pfad</label>
                  <input v-model="form.delivery_config.remote_path" class="pim-input text-xs w-full font-mono" placeholder="/" />
                </div>
                <div>
                  <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Private Key (optional)</label>
                  <textarea v-model="form.delivery_config.private_key" class="pim-input text-xs w-full font-mono h-16 resize-none" placeholder="-----BEGIN RSA PRIVATE KEY-----"></textarea>
                </div>
              </div>

              <!-- HTTP Config -->
              <div v-if="form.delivery_type === 'http'" class="mt-3 space-y-2 p-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)]">
                <div class="grid grid-cols-4 gap-2">
                  <div class="col-span-3">
                    <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">URL *</label>
                    <input v-model="form.delivery_config.url" class="pim-input text-xs w-full" placeholder="https://api.kunde.de/import" />
                  </div>
                  <div>
                    <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Methode</label>
                    <select v-model="form.delivery_config.method" class="pim-input text-xs w-full">
                      <option value="POST">POST</option>
                      <option value="PUT">PUT</option>
                    </select>
                  </div>
                </div>
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-[10px] text-[var(--color-text-tertiary)]">Headers</label>
                    <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="addHeader">+ Header</button>
                  </div>
                  <div v-for="(val, key) in (form.delivery_config.headers || {})" :key="key" class="flex gap-1.5 mb-1">
                    <input
                      :value="key"
                      class="pim-input text-xs flex-1 font-mono"
                      placeholder="Key"
                      @input="(e) => { const h = {...form.delivery_config.headers}; const v = h[key]; delete h[key]; h[e.target.value] = v; form.delivery_config.headers = h }"
                    />
                    <input
                      :value="val"
                      class="pim-input text-xs flex-1 font-mono"
                      placeholder="Value"
                      @input="(e) => { form.delivery_config.headers[key] = e.target.value }"
                    />
                    <button class="p-1 text-[var(--color-text-tertiary)] hover:text-red-500" @click="removeHeader(key)">
                      <X class="w-3 h-3" />
                    </button>
                  </div>
                </div>
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
