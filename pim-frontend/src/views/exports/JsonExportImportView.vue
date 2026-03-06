<script setup>
import { ref, onMounted } from 'vue'
import {
  FileJson, Download, Upload, CheckCircle, XCircle, Loader2,
  AlertTriangle, ChevronDown, ChevronUp, X, FileUp,
} from 'lucide-vue-next'
import jsonExportImportApi from '@/api/jsonExportImport'
import SearchFilterPanel from '@/components/shared/SearchFilterPanel.vue'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'
import searchProfilesApi from '@/api/searchProfiles'

// --- State ---
const activeTab = ref('export') // 'export' | 'import'
const error = ref('')

// --- Export ---
const exporting = ref(false)
const availableSections = ref([])
const selectedSections = ref([])
const filters = ref({
  status: '',
  product_type: '',
  search_text: '',
  updated_after: '',
})
const searchProfiles = ref([])
const selectedSearchProfileId = ref(null)

// --- Import ---
const importFile = ref(null)
const importMode = ref('update')
const importing = ref(false)
const validating = ref(false)
const validationResult = ref(null)
const importResult = ref(null)
const dragOver = ref(false)

// --- Load ---
onMounted(async () => {
  try {
    const [sectionsRes, profilesRes] = await Promise.all([
      jsonExportImportApi.sections(),
      searchProfilesApi.list(),
    ])
    availableSections.value = sectionsRes.data.data || sectionsRes.data
    searchProfiles.value = profilesRes.data.data || profilesRes.data
  } catch (e) { /* ignore */ }
})

// --- Export Actions ---
async function runExport() {
  exporting.value = true
  error.value = ''
  try {
    const activeFilters = Object.fromEntries(
      Object.entries(filters.value).filter(([, v]) => v)
    )

    const response = await jsonExportImportApi.exportFiltered({
      sections: selectedSections.value.length > 0 ? selectedSections.value : undefined,
      filter: Object.keys(activeFilters).length > 0 ? activeFilters : undefined,
    })

    const blob = new Blob([response.data], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `pim-export-${new Date().toISOString().slice(0, 10)}.json`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 200)
  } catch (e) {
    error.value = e.response?.data?.message || 'Export fehlgeschlagen'
  } finally {
    exporting.value = false
  }
}

function toggleSection(section) {
  const idx = selectedSections.value.indexOf(section)
  if (idx >= 0) {
    selectedSections.value.splice(idx, 1)
  } else {
    selectedSections.value.push(section)
  }
}

function selectAllSections() {
  selectedSections.value = [...availableSections.value]
}

function clearSections() {
  selectedSections.value = []
}

function loadSearchProfile(id) {
  const profile = searchProfiles.value.find(p => p.id === id)
  if (!profile) return
  selectedSearchProfileId.value = id
  if (profile.status_filter) filters.value.status = profile.status_filter
  if (profile.search_text) filters.value.search_text = profile.search_text
}

// --- Import Actions ---
function handleFileDrop(e) {
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file && (file.type === 'application/json' || file.name.endsWith('.json'))) {
    importFile.value = file
    validationResult.value = null
    importResult.value = null
  }
}

function handleFileSelect(e) {
  const file = e.target.files?.[0]
  if (file) {
    importFile.value = file
    validationResult.value = null
    importResult.value = null
  }
}

function clearFile() {
  importFile.value = null
  validationResult.value = null
  importResult.value = null
}

async function validateImport() {
  if (!importFile.value) return
  validating.value = true
  error.value = ''
  validationResult.value = null
  try {
    const { data } = await jsonExportImportApi.validateFile(importFile.value)
    validationResult.value = data
  } catch (e) {
    error.value = e.response?.data?.errors?.[0] || 'Validierung fehlgeschlagen'
  } finally {
    validating.value = false
  }
}

async function runImport() {
  if (!importFile.value) return
  importing.value = true
  error.value = ''
  importResult.value = null
  try {
    const { data } = await jsonExportImportApi.importFile(importFile.value, importMode.value)
    importResult.value = data.data || data
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Import fehlgeschlagen'
  } finally {
    importing.value = false
  }
}

function formatBytes(bytes) {
  if (!bytes) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++ }
  return `${Math.round(bytes * 100) / 100} ${units[i]}`
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

const tabs = [
  { key: 'export', label: 'JSON Export', icon: Download },
  { key: 'import', label: 'JSON Import', icon: Upload },
]
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <FileJson class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">JSON Export / Import</h2>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-[var(--color-border)]">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 -mb-px transition-colors',
          activeTab === tab.key
            ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
            : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]',
        ]"
        @click="activeTab = tab.key"
      >
        <component :is="tab.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-red-50 text-red-600 text-xs flex items-center justify-between">
      <span>{{ error }}</span>
      <button @click="error = ''" class="text-red-400 hover:text-red-600"><X class="w-3.5 h-3.5" /></button>
    </div>

    <!-- ═══════ EXPORT TAB ═══════ -->
    <template v-if="activeTab === 'export'">
      <!-- Filter -->
      <div class="pim-card p-5 space-y-4">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Filter (optional)</h3>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">Ohne Filter werden alle Daten exportiert.</p>

        <!-- Suchprofil -->
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Suchprofil</label>
          <select
            v-model="selectedSearchProfileId"
            class="pim-input text-xs w-full max-w-sm"
            @change="loadSearchProfile(selectedSearchProfileId)"
          >
            <option :value="null">-- Kein Suchprofil --</option>
            <option v-for="sp in searchProfiles" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Status</label>
            <select v-model="filters.status" class="pim-input text-xs w-full">
              <option value="">Alle</option>
              <option value="active">Aktiv</option>
              <option value="draft">Entwurf</option>
              <option value="inactive">Inaktiv</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Produkttyp</label>
            <input v-model="filters.product_type" class="pim-input text-xs w-full" placeholder="techn. Name" />
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Suche</label>
            <input v-model="filters.search_text" class="pim-input text-xs w-full" placeholder="SKU, Name..." />
          </div>
          <div>
            <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Aktualisiert nach</label>
            <input v-model="filters.updated_after" type="date" class="pim-input text-xs w-full" />
          </div>
        </div>
      </div>

      <!-- Sektionen -->
      <div class="pim-card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Sektionen</h3>
          <div class="flex gap-2">
            <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="selectAllSections">Alle</button>
            <button class="text-[10px] text-[var(--color-text-tertiary)] hover:underline" @click="clearSections">Keine (=Alle)</button>
          </div>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">
          Leer = alle Sektionen. Wähle spezifische Sektionen für einen gezielten Export.
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
          <label
            v-for="s in availableSections"
            :key="s"
            class="flex items-center gap-1.5 text-[11px] cursor-pointer hover:bg-[var(--color-bg)] px-2 py-1.5 rounded transition-colors"
          >
            <input
              type="checkbox"
              :checked="selectedSections.includes(s)"
              @change="toggleSection(s)"
              class="rounded"
            />
            {{ sectionLabel(s) }}
          </label>
        </div>
      </div>

      <!-- Export Button -->
      <button
        class="pim-btn pim-btn-primary"
        :disabled="exporting"
        @click="runExport"
      >
        <Loader2 v-if="exporting" class="w-4 h-4 animate-spin" />
        <Download v-else class="w-4 h-4" :stroke-width="2" />
        {{ exporting ? 'Exportiere...' : 'JSON Export starten' }}
      </button>
    </template>

    <!-- ═══════ IMPORT TAB ═══════ -->
    <template v-if="activeTab === 'import'">
      <!-- Datei-Upload -->
      <div
        :class="[
          'pim-card p-8 border-2 border-dashed text-center transition-colors',
          dragOver ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]' : 'border-[var(--color-border)]',
        ]"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="handleFileDrop"
      >
        <template v-if="!importFile">
          <FileUp class="w-10 h-10 text-[var(--color-text-tertiary)] mx-auto mb-3" :stroke-width="1.5" />
          <p class="text-sm text-[var(--color-text-secondary)]">JSON-Datei hierher ziehen</p>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1">oder</p>
          <label class="pim-btn pim-btn-secondary text-xs mt-3 cursor-pointer inline-flex">
            <Upload class="w-3.5 h-3.5" :stroke-width="1.75" />
            Datei auswählen
            <input type="file" accept=".json,application/json" class="hidden" @change="handleFileSelect" />
          </label>
        </template>
        <template v-else>
          <FileJson class="w-10 h-10 text-[var(--color-accent)] mx-auto mb-2" :stroke-width="1.5" />
          <p class="text-sm font-medium text-[var(--color-text-primary)]">{{ importFile.name }}</p>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">{{ formatBytes(importFile.size) }}</p>
          <button class="text-xs text-red-500 hover:underline mt-2" @click="clearFile">Datei entfernen</button>
        </template>
      </div>

      <!-- Import-Modus -->
      <div v-if="importFile" class="pim-card p-5 space-y-3">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Import-Modus</h3>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="radio" v-model="importMode" value="update" name="mode" />
            <span>
              <strong>Update (Upsert)</strong>
              <span class="text-[var(--color-text-tertiary)] ml-1">— Bestehende aktualisieren, neue anlegen</span>
            </span>
          </label>
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="radio" v-model="importMode" value="delete_insert" name="mode" />
            <span>
              <strong>Delete & Insert</strong>
              <span class="text-[var(--color-text-tertiary)] ml-1">— Löschen und neu anlegen</span>
            </span>
          </label>
        </div>
      </div>

      <!-- Aktionen -->
      <div v-if="importFile" class="flex gap-3">
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="validating"
          @click="validateImport"
        >
          <Loader2 v-if="validating" class="w-3.5 h-3.5 animate-spin" />
          <CheckCircle v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ validating ? 'Validiere...' : 'Validieren' }}
        </button>
        <button
          class="pim-btn pim-btn-primary text-xs"
          :disabled="importing"
          @click="runImport"
        >
          <Loader2 v-if="importing" class="w-3.5 h-3.5 animate-spin" />
          <Upload v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ importing ? 'Importiere...' : 'Import starten' }}
        </button>
      </div>

      <!-- Validation Result -->
      <div v-if="validationResult" class="pim-card p-4 space-y-2">
        <div class="flex items-center gap-2">
          <CheckCircle v-if="validationResult.valid" class="w-4 h-4 text-green-600" :stroke-width="2" />
          <XCircle v-else class="w-4 h-4 text-red-500" :stroke-width="2" />
          <span class="text-sm font-medium" :class="validationResult.valid ? 'text-green-600' : 'text-red-500'">
            {{ validationResult.valid ? 'Validierung erfolgreich' : 'Validierungsfehler' }}
          </span>
        </div>

        <div v-if="validationResult.sections?.length">
          <p class="text-[11px] text-[var(--color-text-tertiary)] mb-1">Gefundene Sektionen:</p>
          <div class="flex flex-wrap gap-1">
            <span v-for="s in validationResult.sections" :key="s" class="px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px]">
              {{ sectionLabel(s) }}
            </span>
          </div>
        </div>

        <div v-if="validationResult.errors?.length">
          <p class="text-[11px] text-red-500 mb-1">Fehler:</p>
          <ul class="space-y-0.5">
            <li v-for="(err, i) in validationResult.errors" :key="i" class="text-[11px] text-red-600 flex items-start gap-1">
              <AlertTriangle class="w-3 h-3 mt-0.5 shrink-0" :stroke-width="2" />
              {{ err }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Import Result -->
      <div v-if="importResult" class="pim-card p-4 space-y-3">
        <div class="flex items-center gap-2">
          <CheckCircle class="w-4 h-4 text-green-600" :stroke-width="2" />
          <span class="text-sm font-medium text-green-600">Import abgeschlossen</span>
          <span v-if="importResult.duration_seconds" class="text-[11px] text-[var(--color-text-tertiary)]">
            ({{ importResult.duration_seconds }}s)
          </span>
        </div>

        <!-- Stats Table -->
        <div v-if="importResult.stats && Object.keys(importResult.stats).length > 0">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--color-border)]">
                <th class="text-left py-1.5 text-[var(--color-text-tertiary)] font-medium">Sektion</th>
                <th class="text-right py-1.5 text-[var(--color-text-tertiary)] font-medium">Erstellt</th>
                <th class="text-right py-1.5 text-[var(--color-text-tertiary)] font-medium">Aktualisiert</th>
                <th class="text-right py-1.5 text-[var(--color-text-tertiary)] font-medium">Übersprungen</th>
                <th class="text-right py-1.5 text-[var(--color-text-tertiary)] font-medium">Fehler</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(stats, section) in importResult.stats" :key="section" class="border-b border-[var(--color-border)]/50">
                <td class="py-1.5 text-[var(--color-text-primary)]">{{ section }}</td>
                <td class="text-right py-1.5 text-green-600">{{ stats.created || 0 }}</td>
                <td class="text-right py-1.5 text-blue-600">{{ stats.updated || 0 }}</td>
                <td class="text-right py-1.5 text-yellow-600">{{ stats.skipped || 0 }}</td>
                <td class="text-right py-1.5 text-red-500">{{ stats.errors || 0 }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="importResult.affected_product_ids?.length" class="text-[11px] text-[var(--color-text-tertiary)]">
          {{ importResult.affected_product_ids.length }} Produkte betroffen
        </p>
      </div>
    </template>

    <!-- REST/CLI Hinweis -->
    <div class="pim-card p-4 bg-[var(--color-bg)]">
      <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-2">REST / CLI</p>
      <div class="space-y-1 text-[10px] font-mono text-[var(--color-text-tertiary)]">
        <template v-if="activeTab === 'export'">
          <p>curl /api/v1/json-export -H "Authorization: Bearer {token}" -o export.json</p>
          <p>curl -X POST /api/v1/json-export -d '{"filter":{"status":"active"}}' -H "Authorization: Bearer {token}"</p>
          <p>php artisan pim:json-export --status=active --sections=products,prices</p>
        </template>
        <template v-else>
          <p>curl -X POST /api/v1/json-import -F file=@export.json -H "Authorization: Bearer {token}"</p>
          <p>curl -X POST /api/v1/json-import/validate -F file=@export.json -H "Authorization: Bearer {token}"</p>
          <p>php artisan pim:json-import /pfad/export.json --mode=update</p>
        </template>
      </div>
    </div>
  </div>
</template>
