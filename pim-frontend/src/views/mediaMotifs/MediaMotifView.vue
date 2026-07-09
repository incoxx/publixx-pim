<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  Plus, X, Save, Search, AlertCircle, Check,
  Trash2, ImageOff, Wand2, Settings, Link2,
  ArrowUp, ArrowDown, ArrowUpDown,
} from 'lucide-vue-next'
import { mediaMotifs } from '@/api/mediaMotifs'
import mediaApi from '@/api/media'
import { useAuthStore } from '@/stores/auth'
import MotifMetadataFields from '@/components/mediaMotifs/MotifMetadataFields.vue'
import RenditionPresetsDialog from '@/components/mediaMotifs/RenditionPresetsDialog.vue'

const authStore = useAuthStore()
const route = useRoute()
const showPresetsDialog = ref(false)

// ─── Liste: Suche & Filter ────────────────────────
const items = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const searchTerm = ref('')
const usedFilter = ref('all') // all | used | unused
const licenseExpiredOnly = ref(false)
let listSearchDebounce = null
const sortField = ref('created_at')
const sortOrder = ref('desc')

async function fetchItems(targetPage = 1) {
  loading.value = true
  try {
    const filters = {}
    if (usedFilter.value !== 'all') filters.is_used = usedFilter.value === 'used'
    if (licenseExpiredOnly.value) filters.license_expired = true

    const { data } = await mediaMotifs.list({
      page: targetPage,
      perPage: 25,
      search: searchTerm.value || undefined,
      filters: Object.keys(filters).length ? filters : undefined,
      sort: sortField.value,
      order: sortOrder.value,
    })
    items.value = data.data || []
    meta.value = data.meta || null
    page.value = targetPage
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  clearTimeout(listSearchDebounce)
  listSearchDebounce = setTimeout(() => fetchItems(1), 300)
}

function onFilterChange() {
  fetchItems(1)
}

function handleSort(field) {
  sortOrder.value = sortField.value === field && sortOrder.value === 'asc' ? 'desc' : 'asc'
  sortField.value = field
  fetchItems(1)
}

function formatDate(value) {
  if (!value) return null
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('de-DE')
}

// ─── Neues Motiv: Asset-Picker ───────────────────
const showPicker = ref(false)
const pickerSearch = ref('')
const pickerResults = ref([])
const pickerLoading = ref(false)
let pickerDebounce = null

function openPicker() {
  showPicker.value = true
  pickerSearch.value = ''
  pickerResults.value = []
  searchPickerMedia()
}

function onPickerInput() {
  clearTimeout(pickerDebounce)
  pickerDebounce = setTimeout(searchPickerMedia, 300)
}

async function searchPickerMedia() {
  pickerLoading.value = true
  try {
    const { data } = await mediaApi.list({ search: pickerSearch.value, perPage: 24 })
    const all = data.data || []
    // Nur eigenständige Medien anbieten (noch keinem Motiv zugeordnet)
    pickerResults.value = all.filter(m => !m.motif_id && m.media_type === 'image')
  } finally {
    pickerLoading.value = false
  }
}

// ─── Neues Motiv: Metadaten-Formular ─────────────
const showCreateForm = ref(false)
const selectedMedia = ref(null)
const createErrors = ref({})
const createSaving = ref(false)
const createForm = ref(emptyMotifForm())

function emptyMotifForm() {
  return {
    title_de: '', title_en: '',
    rights_holder: '', creator: '', credit_line: '',
    license_type: '', license_valid_until: '',
    copyright_notice: '', usage_restrictions: '',
    keywordsInput: '',
    valid_from: '', valid_until: '',
  }
}

function selectPickerMedia(media) {
  selectedMedia.value = media
  createForm.value = emptyMotifForm()
  createForm.value.title_de = media.title_de || ''
  createForm.value.title_en = media.title_en || ''
  showPicker.value = false
  showCreateForm.value = true
}

function cancelCreate() {
  showCreateForm.value = false
  selectedMedia.value = null
  createErrors.value = {}
}

function buildMotifPayload(form) {
  const payload = { ...form }
  delete payload.keywordsInput
  payload.keywords = form.keywordsInput
    ? form.keywordsInput.split(',').map(k => k.trim()).filter(Boolean)
    : []
  for (const key of Object.keys(payload)) {
    if (payload[key] === '') payload[key] = null
  }
  return payload
}

async function submitCreate() {
  if (!selectedMedia.value) return
  createSaving.value = true
  createErrors.value = {}
  try {
    await mediaMotifs.promote(selectedMedia.value.id, buildMotifPayload(createForm.value))
    showCreateForm.value = false
    selectedMedia.value = null
    await fetchItems(1)
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        createErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
      if (!Object.keys(errs).length && e.response.data.message) {
        createErrors.value._general = e.response.data.message
      }
    }
  } finally {
    createSaving.value = false
  }
}

// ─── Motiv-Detail / Bearbeiten ────────────────────
const detail = ref(null)
const detailLoading = ref(false)
const editForm = ref(emptyMotifForm())
const editSaving = ref(false)
const editErrors = ref({})
const editSaved = ref(false)
const generating = ref(false)
const generateResult = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)
const selectedRendition = ref(null)

function openRendition(rendition) {
  selectedRendition.value = rendition
}

function formatBytes(bytes) {
  if (!bytes) return '—'
  const units = ['B', 'KB', 'MB', 'GB']
  let value = bytes
  let unitIndex = 0
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024
    unitIndex++
  }
  return `${value.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`
}

async function openDetail(row) {
  detailLoading.value = true
  generateResult.value = null
  editSaved.value = false
  try {
    const { data } = await mediaMotifs.get(row.id)
    detail.value = data.data
    editForm.value = {
      title_de: detail.value.title_de || '',
      title_en: detail.value.title_en || '',
      rights_holder: detail.value.rights_holder || '',
      creator: detail.value.creator || '',
      credit_line: detail.value.credit_line || '',
      license_type: detail.value.license_type || '',
      license_valid_until: detail.value.license_valid_until || '',
      copyright_notice: detail.value.copyright_notice || '',
      usage_restrictions: detail.value.usage_restrictions || '',
      keywordsInput: (detail.value.keywords || []).join(', '),
      valid_from: detail.value.valid_from || '',
      valid_until: detail.value.valid_until || '',
    }
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  detail.value = null
  editErrors.value = {}
  generateResult.value = null
  selectedRendition.value = null
}

async function saveDetail() {
  if (!detail.value) return
  editSaving.value = true
  editErrors.value = {}
  editSaved.value = false
  try {
    const { data } = await mediaMotifs.update(detail.value.id, buildMotifPayload(editForm.value))
    detail.value = { ...detail.value, ...data.data }
    editSaved.value = true
    setTimeout(() => { editSaved.value = false }, 2500)
    const idx = items.value.findIndex(i => i.id === detail.value.id)
    if (idx >= 0) items.value[idx] = { ...items.value[idx], ...data.data }
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        editErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    editSaving.value = false
  }
}

async function runGenerateRenditions() {
  if (!detail.value) return
  generating.value = true
  generateResult.value = null
  try {
    const { data } = await mediaMotifs.generateRenditions(detail.value.id)
    generateResult.value = data
    const { data: fresh } = await mediaMotifs.get(detail.value.id)
    detail.value = fresh.data
    const idx = items.value.findIndex(i => i.id === detail.value.id)
    if (idx >= 0) items.value[idx] = { ...items.value[idx], rendition_count: fresh.data.rendition_count }
  } finally {
    generating.value = false
  }
}

async function confirmDeleteMotif() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await mediaMotifs.delete(deleteTarget.value.id)
    if (detail.value?.id === deleteTarget.value.id) closeDetail()
    deleteTarget.value = null
    await fetchItems(page.value)
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchItems()
  if (route.query.open) {
    openDetail({ id: route.query.open })
  }
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Motive</h2>
        <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">
          Ein Motiv bündelt mehrere Ausgabeformate desselben Bildinhalts (Print, Web, Mobile, Social).
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button v-if="authStore.hasPermission('media.edit')" class="pim-btn pim-btn-secondary" @click="showPresetsDialog = true">
          <Settings class="w-4 h-4" :stroke-width="2" /> Presets verwalten
        </button>
        <button v-if="authStore.hasPermission('media.create')" class="pim-btn pim-btn-primary" @click="openPicker">
          <Plus class="w-4 h-4" :stroke-width="2" /> Neues Motiv
        </button>
      </div>
    </div>

    <RenditionPresetsDialog :open="showPresetsDialog" @close="showPresetsDialog = false" />

    <!-- ─── Suche & Filter ───────────────────────── -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative flex-1 min-w-[220px]">
        <Search class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <input
          v-model="searchTerm"
          @input="onSearchInput"
          class="pim-input pim-input-icon text-xs"
          placeholder="Motive durchsuchen (Titel, Rechteinhaber, Urheber, Schlagworte, …)"
        />
      </div>
      <select v-model="usedFilter" @change="onFilterChange" class="pim-select text-xs">
        <option value="all">Alle Motive</option>
        <option value="used">Nur verwendete</option>
        <option value="unused">Nur unverwendete</option>
      </select>
      <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs text-[var(--color-text-secondary)] px-2">
        <input type="checkbox" v-model="licenseExpiredOnly" @change="onFilterChange" class="w-3.5 h-3.5 accent-[var(--color-primary)]" />
        Nur abgelaufene Lizenzen
      </label>
    </div>

    <!-- ─── Asset-Picker ─────────────────────────── -->
    <div v-if="showPicker" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Bestehendes Medium als Motiv-Master wählen</h3>
        <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="showPicker = false">
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>
      <div class="relative">
        <Search class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <input
          v-model="pickerSearch"
          @input="onPickerInput"
          class="pim-input pim-input-icon text-xs"
          placeholder="Dateiname oder Titel durchsuchen…"
        />
      </div>
      <div v-if="pickerLoading" class="text-xs text-[var(--color-text-tertiary)] py-6 text-center">Suche läuft…</div>
      <div v-else-if="pickerResults.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-6 text-center">
        Keine passenden, noch nicht gruppierten Bilder gefunden.
      </div>
      <div v-else class="grid grid-cols-3 sm:grid-cols-6 gap-2 max-h-[360px] overflow-auto">
        <button
          v-for="m in pickerResults"
          :key="m.id"
          class="group relative aspect-square rounded-lg overflow-hidden border border-[var(--color-border)] hover:border-[var(--color-accent)] transition-colors"
          @click="selectPickerMedia(m)"
        >
          <img :src="m.thumb_url" :alt="m.title_de || m.file_name" class="w-full h-full object-cover" />
          <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-1 py-0.5 truncate">
            {{ m.title_de || m.file_name }}
          </span>
        </button>
      </div>
    </div>

    <!-- ─── Neues Motiv: Metadaten ───────────────── -->
    <div v-if="showCreateForm" class="pim-card p-4 space-y-3">
      <div class="flex items-center gap-3">
        <img v-if="selectedMedia" :src="selectedMedia.thumb_url" class="w-12 h-12 object-cover rounded-lg border border-[var(--color-border)]" />
        <div>
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Neues Motiv anlegen</h3>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Master: {{ selectedMedia?.file_name }}</p>
        </div>
      </div>

      <p v-if="createErrors._general" class="text-xs text-[var(--color-error)] flex items-center gap-1.5">
        <AlertCircle class="w-3.5 h-3.5" :stroke-width="2" /> {{ createErrors._general }}
      </p>

      <MotifMetadataFields v-model="createForm" :errors="createErrors" />

      <div class="flex gap-2 pt-1">
        <button class="pim-btn pim-btn-primary text-xs" :disabled="createSaving" @click="submitCreate">
          {{ createSaving ? 'Speichern…' : 'Motiv anlegen' }}
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="cancelCreate">Abbrechen</button>
      </div>
    </div>

    <!-- ─── Motiv-Detail ─────────────────────────── -->
    <div v-if="detail" class="pim-card p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Motiv bearbeiten</h3>
        <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="closeDetail">
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>

      <div v-if="detailLoading" class="text-xs text-[var(--color-text-tertiary)] py-6 text-center">Lädt…</div>

      <template v-else>
        <MotifMetadataFields v-model="editForm" :errors="editErrors" />

        <div class="flex items-center gap-3">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="editSaving" @click="saveDetail">
            <Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ editSaving ? 'Speichern…' : 'Speichern' }}
          </button>
          <span v-if="editSaved" class="flex items-center gap-1 text-xs text-[var(--color-success)]">
            <Check class="w-3.5 h-3.5" :stroke-width="2" /> Gespeichert
          </span>
          <div class="flex-1" />
          <button
            v-if="authStore.hasPermission('media.delete')"
            class="pim-btn pim-btn-ghost text-xs text-[var(--color-error)]"
            @click="deleteTarget = detail"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> Motiv auflösen
          </button>
        </div>

        <!-- Renditions -->
        <div class="border-t border-[var(--color-border)] pt-4 space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-[var(--color-text-primary)]">Renditions</h4>
            <button class="pim-btn pim-btn-accent text-xs" :disabled="generating" @click="runGenerateRenditions">
              <Wand2 class="w-3.5 h-3.5" :stroke-width="2" /> {{ generating ? 'Erzeuge…' : 'Renditions generieren' }}
            </button>
          </div>

          <div v-if="generateResult" class="text-xs space-y-1 bg-[var(--color-bg)] rounded-lg p-3 border border-[var(--color-border)]/50">
            <p class="text-[var(--color-text-primary)]">{{ generateResult.message }}</p>
            <p v-for="(msg, preset) in generateResult.errors" :key="preset" class="text-[var(--color-warning)]">
              {{ preset }}: {{ msg }}
            </p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <button
              v-for="rendition in detail.renditions || []"
              :key="rendition.id"
              type="button"
              class="text-left rounded-lg border border-[var(--color-border)] overflow-hidden hover:border-[var(--color-accent)] transition-colors"
              :class="rendition.is_master_rendition ? 'ring-2 ring-[var(--color-accent)]' : ''"
              @click="openRendition(rendition)"
            >
              <img :src="rendition.thumb_url" class="w-full aspect-square object-cover" />
              <div class="p-1.5 text-[10px] text-[var(--color-text-tertiary)] space-y-0.5">
                <p class="font-medium text-[var(--color-text-primary)] truncate">
                  {{ rendition.is_master_rendition ? 'Master' : (rendition.rendition_channel || '—') }}
                </p>
                <p>{{ rendition.width }}×{{ rendition.height }}px</p>
                <p v-if="rendition.colorspace">{{ rendition.colorspace.toUpperCase() }} · {{ rendition.dpi }}dpi</p>
              </div>
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- ─── Liste ─────────────────────────────────── -->
    <div class="pim-card overflow-hidden">
      <table class="w-full text-[13px]">
        <thead>
          <tr class="bg-[var(--color-bg)] border-b border-[var(--color-border)]">
            <th class="w-14 px-3 py-2.5"></th>
            <th
              class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] cursor-pointer select-none hover:text-[var(--color-text-secondary)]"
              @click="handleSort('title_de')"
            >
              <div class="flex items-center gap-1">
                <span>Titel</span>
                <ArrowUp v-if="sortField === 'title_de' && sortOrder === 'asc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowDown v-else-if="sortField === 'title_de' && sortOrder === 'desc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
            <th
              class="px-3 py-2.5 text-center font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] cursor-pointer select-none hover:text-[var(--color-text-secondary)]"
              @click="handleSort('renditions_count')"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Renditions</span>
                <ArrowUp v-if="sortField === 'renditions_count' && sortOrder === 'asc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowDown v-else-if="sortField === 'renditions_count' && sortOrder === 'desc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
            <th class="px-3 py-2.5 text-center font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)]">Verwendet</th>
            <th
              class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] cursor-pointer select-none hover:text-[var(--color-text-secondary)]"
              @click="handleSort('license_type')"
            >
              <div class="flex items-center gap-1">
                <span>Lizenz</span>
                <ArrowUp v-if="sortField === 'license_type' && sortOrder === 'asc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowDown v-else-if="sortField === 'license_type' && sortOrder === 'desc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
            <th
              class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] cursor-pointer select-none hover:text-[var(--color-text-secondary)]"
              @click="handleSort('valid_until')"
            >
              <div class="flex items-center gap-1">
                <span>Gültigkeit</span>
                <ArrowUp v-if="sortField === 'valid_until' && sortOrder === 'asc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowDown v-else-if="sortField === 'valid_until' && sortOrder === 'desc'" class="w-3 h-3 text-[var(--color-accent)]" />
                <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="py-16 text-center text-xs text-[var(--color-text-tertiary)]">Lädt…</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="6" class="py-16 text-center text-sm text-[var(--color-text-tertiary)]">Noch keine Motive angelegt</td>
          </tr>
          <tr
            v-else
            v-for="row in items"
            :key="row.id"
            class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
            @click="openDetail(row)"
          >
            <td class="px-3 py-2">
              <img v-if="row.master_rendition?.thumb_url" :src="row.master_rendition.thumb_url" class="w-9 h-9 object-cover rounded-md border border-[var(--color-border)]" />
              <div v-else class="w-9 h-9 rounded-md bg-[var(--color-bg)] flex items-center justify-center text-[var(--color-text-tertiary)]">
                <ImageOff class="w-4 h-4" :stroke-width="1.75" />
              </div>
            </td>
            <td class="px-3 py-2 text-[var(--color-text-primary)]">{{ row.title_de || '(ohne Titel)' }}</td>
            <td class="px-3 py-2 text-center">
              <span class="pim-badge bg-[var(--color-surface)] text-[var(--color-text-secondary)]">{{ row.rendition_count ?? 0 }}</span>
            </td>
            <td class="px-3 py-2 text-center" :title="row.is_used ? 'Motiv ist einem Produkt/Knoten zugeordnet' : 'Noch keine Zuordnung'">
              <Link2 v-if="row.is_used" class="w-4 h-4 inline text-[var(--color-success)]" :stroke-width="2" />
              <span v-else class="text-[var(--color-text-tertiary)]">—</span>
            </td>
            <td class="px-3 py-2">
              <span v-if="row.license_expired" class="pim-badge bg-[var(--color-error)]/10 text-[var(--color-error)]">Lizenz abgelaufen</span>
              <span v-else-if="row.license_type" class="text-xs text-[var(--color-text-secondary)]">{{ row.license_type }}</span>
              <span v-else class="text-xs text-[var(--color-text-tertiary)]">—</span>
            </td>
            <td class="px-3 py-2 text-xs text-[var(--color-text-secondary)]">
              <span v-if="!row.valid_from && !row.valid_until" class="text-[var(--color-text-tertiary)]">unbegrenzt</span>
              <span v-else>{{ formatDate(row.valid_from) || '…' }} – {{ formatDate(row.valid_until) || '…' }}</span>
              <span v-if="(row.valid_from || row.valid_until) && !row.is_currently_valid" class="pim-badge ml-1.5 bg-[var(--color-warning)]/10 text-[var(--color-warning)]">außerhalb</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between px-3 py-2.5 border-t border-[var(--color-border)] text-xs text-[var(--color-text-tertiary)]">
        <span>Seite {{ meta.current_page }} von {{ meta.last_page }} ({{ meta.total }} Motive)</span>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-ghost pim-btn-sm" :disabled="page <= 1" @click="fetchItems(page - 1)">Zurück</button>
          <button class="pim-btn pim-btn-ghost pim-btn-sm" :disabled="page >= meta.last_page" @click="fetchItems(page + 1)">Weiter</button>
        </div>
      </div>
    </div>

    <!-- ─── Rendition-Lightbox ───────────────────── -->
    <Teleport to="body">
      <div v-if="selectedRendition" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="selectedRendition = null" />
        <div class="relative z-10 w-full max-w-[720px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl overflow-hidden shadow-xl mx-4 max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
              {{ selectedRendition.is_master_rendition ? 'Master' : (selectedRendition.rendition_channel || 'Rendition') }}
            </h3>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="selectedRendition = null">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>
          <div class="overflow-auto p-4 space-y-4">
            <img :src="selectedRendition.thumb_url?.replace(/w=\d+&h=\d+/, 'w=1200&h=1200')" :alt="selectedRendition.file_name" class="w-full max-h-[50vh] object-contain rounded-lg bg-[var(--color-bg)]" />
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
              <div>
                <dt class="text-[var(--color-text-tertiary)]">Dateiname</dt>
                <dd class="text-[var(--color-text-primary)] font-mono truncate">{{ selectedRendition.file_name }}</dd>
              </div>
              <div>
                <dt class="text-[var(--color-text-tertiary)]">Abmessungen</dt>
                <dd class="text-[var(--color-text-primary)]">{{ selectedRendition.width }}×{{ selectedRendition.height }}px</dd>
              </div>
              <div>
                <dt class="text-[var(--color-text-tertiary)]">Format</dt>
                <dd class="text-[var(--color-text-primary)]">{{ selectedRendition.mime_type }}</dd>
              </div>
              <div v-if="selectedRendition.colorspace">
                <dt class="text-[var(--color-text-tertiary)]">Farbraum</dt>
                <dd class="text-[var(--color-text-primary)]">{{ selectedRendition.colorspace.toUpperCase() }}</dd>
              </div>
              <div v-if="selectedRendition.dpi">
                <dt class="text-[var(--color-text-tertiary)]">Auflösung</dt>
                <dd class="text-[var(--color-text-primary)]">{{ selectedRendition.dpi }} dpi</dd>
              </div>
              <div>
                <dt class="text-[var(--color-text-tertiary)]">Dateigröße</dt>
                <dd class="text-[var(--color-text-primary)]">{{ formatBytes(selectedRendition.file_size) }}</dd>
              </div>
            </dl>
          </div>
          <div class="flex justify-end gap-2 px-4 py-3 border-t border-[var(--color-border)]">
            <a :href="selectedRendition.file_url" target="_blank" rel="noopener" class="pim-btn pim-btn-secondary text-xs">Original öffnen</a>
            <a :href="selectedRendition.file_url" :download="selectedRendition.file_name" class="pim-btn pim-btn-primary text-xs">Herunterladen</a>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Lösch-Bestätigung ────────────────────── -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="deleteTarget = null" />
        <div class="relative z-10 w-full max-w-[440px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl p-6 space-y-3 shadow-xl mx-4">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Motiv auflösen?</h3>
          <p class="text-xs text-[var(--color-text-tertiary)] leading-relaxed">
            Generierte Renditions werden gelöscht. Die Master-Datei "{{ deleteTarget.title_de || deleteTarget.file_name }}" bleibt
            als eigenständiges Medium erhalten und ist weiterhin über die Medienverwaltung nutzbar.
          </p>
          <div class="flex justify-end gap-2 pt-2">
            <button class="pim-btn pim-btn-secondary text-xs" :disabled="deleting" @click="deleteTarget = null">Abbrechen</button>
            <button class="pim-btn text-xs bg-[var(--color-error)] text-white hover:bg-[var(--color-error)]/90" :disabled="deleting" @click="confirmDeleteMotif">
              {{ deleting ? 'Bitte warten…' : 'Auflösen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
