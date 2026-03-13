<script setup>
import { ref, onMounted } from 'vue'
import {
  FileCode, Download, Upload, CheckCircle, XCircle, Loader2,
  AlertTriangle, X, FileUp, Info, ChevronRight,
} from 'lucide-vue-next'
import bmecatApi from '@/api/bmecatImport'
import hierarchiesApi from '@/api/hierarchies'
import attributesApi from '@/api/attributes'
import { productTypes } from '@/api/attributes'
import { priceTypes, relationTypes } from '@/api/prices'

// --- State ---
const activeTab = ref('import') // 'import' | 'export'
const error = ref('')
const errorExpanded = ref(false)

// --- Import ---
const importFile = ref(null)
const importMode = ref('update')
const importProductType = ref('')
const importing = ref(false)
const validating = ref(false)
const validationResult = ref(null)
const importResult = ref(null)
const importProgress = ref(null) // { phase, message, current, total, percent }
const dragOver = ref(false)

// --- Export ---
const exporting = ref(false)
const exportProgress = ref(null) // { loaded, total, percent }
const exportVersion = ref('2005')
const exportHierarchyId = ref('')
const exportProductTypeIds = ref([])
const exportAttributeIds = ref([])
const exportPriceTypeIds = ref([])
const exportRelationTypeIds = ref([])

// --- Lookup Data ---
const hierarchyList = ref([])
const productTypeList = ref([])
const attributeList = ref([])
const priceTypeList = ref([])
const relationTypeList = ref([])

// --- Load ---
onMounted(async () => {
  try {
    const [hierRes, ptRes, attrRes, prRes, relRes] = await Promise.all([
      hierarchiesApi.list({ per_page: 200 }),
      productTypes.list(),
      attributesApi.list({ per_page: 500 }),
      priceTypes.list(),
      relationTypes.list(),
    ])
    hierarchyList.value = hierRes.data.data || hierRes.data
    productTypeList.value = ptRes.data.data || ptRes.data
    attributeList.value = attrRes.data.data || attrRes.data
    priceTypeList.value = prRes.data.data || prRes.data
    relationTypeList.value = relRes.data.data || relRes.data
  } catch (e) { /* ignore */ }
})

// --- Import Actions ---
function handleFileDrop(e) {
  dragOver.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file && (file.type === 'application/xml' || file.type === 'text/xml' || file.name.endsWith('.xml'))) {
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
    const { data } = await bmecatApi.validateFile(importFile.value)
    validationResult.value = data.data || data
  } catch (e) {
    error.value = e.response?.data?.errors?.[0] || e.response?.data?.message || 'Validierung fehlgeschlagen'
  } finally {
    validating.value = false
  }
}

async function runImport() {
  if (!importFile.value) return
  importing.value = true
  error.value = ''
  importResult.value = null
  importProgress.value = { phase: 'upload', message: 'Datei wird hochgeladen...', current: 0, total: 0, percent: 0 }
  try {
    const result = await bmecatApi.importFileWithProgress(
      importFile.value,
      importMode.value,
      importProductType.value || null,
      (progress) => {
        importProgress.value = {
          ...progress,
          percent: progress.total > 0 ? Math.round((progress.current / progress.total) * 100) : 0,
        }
      },
    )
    importResult.value = result
    importProgress.value = null
  } catch (e) {
    error.value = e.message || 'Import fehlgeschlagen'
    importProgress.value = null
  } finally {
    importing.value = false
  }
}

// --- Export Actions ---
async function runExport() {
  exporting.value = true
  error.value = ''
  exportProgress.value = { loaded: 0, total: 0, percent: 0 }
  try {
    const response = await bmecatApi.exportFile(
      {
        version: exportVersion.value,
        hierarchy_id: exportHierarchyId.value || undefined,
        product_type_ids: exportProductTypeIds.value.length > 0 ? exportProductTypeIds.value : undefined,
        attribute_ids: exportAttributeIds.value.length > 0 ? exportAttributeIds.value : undefined,
        price_type_ids: exportPriceTypeIds.value.length > 0 ? exportPriceTypeIds.value : undefined,
        relation_type_ids: exportRelationTypeIds.value.length > 0 ? exportRelationTypeIds.value : undefined,
      },
      (progress) => {
        exportProgress.value = progress
      },
    )

    const blob = new Blob([response.data], { type: 'application/xml' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `bmecat-export-${exportVersion.value}-${new Date().toISOString().slice(0, 10)}.xml`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 200)
  } catch (e) {
    if (e.response?.data instanceof Blob) {
      try {
        const text = await e.response.data.text()
        const json = JSON.parse(text)
        error.value = json.message || json.error || 'Export fehlgeschlagen'
      } catch {
        error.value = 'Export fehlgeschlagen'
      }
    } else {
      error.value = e.response?.data?.message || 'Export fehlgeschlagen'
    }
  } finally {
    exporting.value = false
    exportProgress.value = null
  }
}

function toggleItem(list, id) {
  const idx = list.indexOf(id)
  if (idx >= 0) {
    list.splice(idx, 1)
  } else {
    list.push(id)
  }
}

function formatBytes(bytes) {
  if (!bytes) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++ }
  return `${Math.round(bytes * 100) / 100} ${units[i]}`
}

const tabs = [
  { key: 'import', label: 'BMEcat Import', icon: Upload },
  { key: 'export', label: 'BMEcat Export', icon: Download },
]
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <FileCode class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">BMEcat Import / Export</h2>
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
    <div v-if="error" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 text-xs">
      <div class="flex items-center justify-between">
        <button
          class="flex items-center gap-1 font-medium hover:underline text-left"
          @click="errorExpanded = !errorExpanded"
        >
          <ChevronRight class="w-3 h-3 transition-transform" :class="errorExpanded ? 'rotate-90' : ''" :stroke-width="2" />
          {{ errorExpanded ? 'Fehlerdetails' : (error.length > 120 ? error.slice(0, 120) + '…' : error) }}
        </button>
        <button @click="error = ''; errorExpanded = false" class="text-red-400 hover:text-red-600 flex-shrink-0 ml-2"><X class="w-3.5 h-3.5" /></button>
      </div>
      <pre v-if="errorExpanded" class="mt-2 whitespace-pre-wrap break-all text-[11px] leading-relaxed max-h-64 overflow-y-auto">{{ error }}</pre>
    </div>

    <!-- ═══════ IMPORT TAB ═══════ -->
    <template v-if="activeTab === 'import'">
      <!-- Info -->
      <div class="pim-card p-3 bg-[var(--color-bg)] flex items-start gap-2">
        <Info class="w-4 h-4 text-[var(--color-accent)] mt-0.5 shrink-0" :stroke-width="1.75" />
        <p class="text-[11px] text-[var(--color-text-secondary)]">
          BMEcat-XML-Dateien der Versionen <strong>1.2</strong> und <strong>2005</strong> werden unterstützt.
          Die Version wird automatisch erkannt. Unterstützter Transaktionstyp: <strong>T_NEW_CATALOG</strong>.
        </p>
      </div>

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
          <p class="text-sm text-[var(--color-text-secondary)]">BMEcat-XML-Datei hierher ziehen</p>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1">oder</p>
          <label class="pim-btn pim-btn-secondary text-xs mt-3 cursor-pointer inline-flex">
            <Upload class="w-3.5 h-3.5" :stroke-width="1.75" />
            Datei auswählen
            <input type="file" accept=".xml,application/xml,text/xml" class="hidden" @change="handleFileSelect" />
          </label>
        </template>
        <template v-else>
          <FileCode class="w-10 h-10 text-[var(--color-accent)] mx-auto mb-2" :stroke-width="1.5" />
          <p class="text-sm font-medium text-[var(--color-text-primary)]">{{ importFile.name }}</p>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">{{ formatBytes(importFile.size) }}</p>
          <button class="text-xs text-red-500 hover:underline mt-2" @click="clearFile">Datei entfernen</button>
        </template>
      </div>

      <!-- Import-Optionen -->
      <div v-if="importFile" class="pim-card p-5 space-y-4">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Import-Optionen</h3>

        <!-- Import-Modus -->
        <div>
          <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1.5">Import-Modus</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 text-xs cursor-pointer">
              <input type="radio" v-model="importMode" value="update" name="import-mode" />
              <span>
                <strong>Update (Upsert)</strong>
                <span class="text-[var(--color-text-tertiary)] ml-1">— Bestehende aktualisieren, neue anlegen</span>
              </span>
            </label>
            <label class="flex items-center gap-2 text-xs cursor-pointer">
              <input type="radio" v-model="importMode" value="delete_insert" name="import-mode" />
              <span>
                <strong>Delete &amp; Insert</strong>
                <span class="text-[var(--color-text-tertiary)] ml-1">— Löschen und neu anlegen</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Produkttyp -->
        <div>
          <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-1">Produkttyp (optional)</label>
          <select v-model="importProductType" class="pim-input text-xs w-full max-w-sm">
            <option value="">Standard (bmecat_product)</option>
            <option v-for="pt in productTypeList" :key="pt.id" :value="pt.technical_name">
              {{ pt.name_de || pt.technical_name }}
            </option>
          </select>
          <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
            Leer = automatisch Produkttyp „bmecat_product" anlegen
          </p>
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

      <!-- Import Progress -->
      <div v-if="importProgress" class="pim-card p-4 space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="text-[var(--color-text-secondary)] flex items-center gap-2">
            <Loader2 class="w-3.5 h-3.5 animate-spin text-[var(--color-accent)]" :stroke-width="2" />
            {{ importProgress.message }}
          </span>
          <span v-if="importProgress.total > 0" class="text-[var(--color-text-tertiary)]">
            {{ importProgress.current }} / {{ importProgress.total }}
          </span>
        </div>
        <div class="w-full bg-[var(--color-bg)] rounded-full h-2 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300 ease-out"
            :class="importProgress.total > 0 ? 'bg-[var(--color-accent)]' : 'bg-[var(--color-accent)] animate-pulse'"
            :style="{ width: importProgress.total > 0 ? importProgress.percent + '%' : '100%' }"
          />
        </div>
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

        <div v-if="validationResult.valid" class="grid grid-cols-3 gap-3 mt-2">
          <div class="text-center p-2 rounded bg-[var(--color-bg)]">
            <p class="text-[10px] text-[var(--color-text-tertiary)]">Version</p>
            <p class="text-sm font-semibold text-[var(--color-text-primary)]">{{ validationResult.version }}</p>
          </div>
          <div class="text-center p-2 rounded bg-[var(--color-bg)]">
            <p class="text-[10px] text-[var(--color-text-tertiary)]">Transaktion</p>
            <p class="text-sm font-semibold text-[var(--color-text-primary)]">{{ validationResult.transaction_type }}</p>
          </div>
          <div class="text-center p-2 rounded bg-[var(--color-bg)]">
            <p class="text-[10px] text-[var(--color-text-tertiary)]">Produkte</p>
            <p class="text-sm font-semibold text-[var(--color-text-primary)]">{{ validationResult.product_count }}</p>
          </div>
        </div>

        <div v-if="validationResult.errors?.length">
          <p class="text-[11px] text-red-500 mb-1">Fehler:</p>
          <ul class="space-y-0.5">
            <li v-for="(err, i) in validationResult.errors" :key="i" class="text-[11px] text-red-600 flex items-start gap-1">
              <XCircle class="w-3 h-3 mt-0.5 shrink-0" :stroke-width="2" />
              {{ err }}
            </li>
          </ul>
        </div>

        <div v-if="validationResult.warnings?.length">
          <p class="text-[11px] text-yellow-600 mb-1">Warnungen:</p>
          <ul class="space-y-0.5">
            <li v-for="(warn, i) in validationResult.warnings" :key="'w'+i" class="text-[11px] text-yellow-700 flex items-start gap-1">
              <AlertTriangle class="w-3 h-3 mt-0.5 shrink-0" :stroke-width="2" />
              {{ warn }}
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

    <!-- ═══════ EXPORT TAB ═══════ -->
    <template v-if="activeTab === 'export'">
      <!-- Info -->
      <div class="pim-card p-3 bg-[var(--color-bg)] flex items-start gap-2">
        <Info class="w-4 h-4 text-[var(--color-accent)] mt-0.5 shrink-0" :stroke-width="1.75" />
        <p class="text-[11px] text-[var(--color-text-secondary)]">
          PIM-Daten als BMEcat-XML exportieren. Wähle Version, Hierarchie und die zu exportierenden Datentypen.
        </p>
      </div>

      <!-- Version -->
      <div class="pim-card p-5 space-y-3">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">BMEcat-Version</h3>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="radio" v-model="exportVersion" value="2005" name="export-version" />
            <span><strong>BMEcat 2005</strong> <span class="text-[var(--color-text-tertiary)]">— Empfohlen</span></span>
          </label>
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="radio" v-model="exportVersion" value="1.2" name="export-version" />
            <span><strong>BMEcat 1.2</strong> <span class="text-[var(--color-text-tertiary)]">— Legacy</span></span>
          </label>
        </div>
      </div>

      <!-- Hierarchie -->
      <div class="pim-card p-5 space-y-3">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Hierarchie</h3>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">
          Welche Hierarchie soll als CATALOG_GROUP_SYSTEM exportiert werden?
        </p>
        <select v-model="exportHierarchyId" class="pim-input text-xs w-full max-w-sm">
          <option value="">-- Keine Hierarchie --</option>
          <option v-for="h in hierarchyList" :key="h.id" :value="h.id">
            {{ h.name_de || h.technical_name }}
          </option>
        </select>
      </div>

      <!-- Produkttypen -->
      <div class="pim-card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Produkttypen</h3>
          <div class="flex gap-2">
            <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="exportProductTypeIds = productTypeList.map(pt => pt.id)">Alle</button>
            <button class="text-[10px] text-[var(--color-text-tertiary)] hover:underline" @click="exportProductTypeIds = []">Keine (=Alle)</button>
          </div>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">Leer = alle Produkttypen exportieren.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-40 overflow-y-auto">
          <label
            v-for="pt in productTypeList"
            :key="pt.id"
            class="flex items-center gap-1.5 text-[11px] cursor-pointer hover:bg-[var(--color-bg)] px-2 py-1.5 rounded transition-colors"
          >
            <input type="checkbox" :checked="exportProductTypeIds.includes(pt.id)" @change="toggleItem(exportProductTypeIds, pt.id)" class="rounded" />
            {{ pt.name_de || pt.technical_name }}
          </label>
        </div>
      </div>

      <!-- Attribute -->
      <div class="pim-card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Attribute</h3>
          <div class="flex gap-2">
            <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="exportAttributeIds = attributeList.map(a => a.id)">Alle</button>
            <button class="text-[10px] text-[var(--color-text-tertiary)] hover:underline" @click="exportAttributeIds = []">Keine (=Alle)</button>
          </div>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">Leer = alle Attribute als FEATURE exportieren.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 max-h-48 overflow-y-auto">
          <label
            v-for="attr in attributeList"
            :key="attr.id"
            class="flex items-center gap-1.5 text-[11px] cursor-pointer hover:bg-[var(--color-bg)] px-2 py-1.5 rounded transition-colors"
          >
            <input type="checkbox" :checked="exportAttributeIds.includes(attr.id)" @change="toggleItem(exportAttributeIds, attr.id)" class="rounded" />
            {{ attr.name_de || attr.technical_name }}
          </label>
        </div>
      </div>

      <!-- Preistypen -->
      <div class="pim-card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Preistypen</h3>
          <div class="flex gap-2">
            <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="exportPriceTypeIds = priceTypeList.map(p => p.id)">Alle</button>
            <button class="text-[10px] text-[var(--color-text-tertiary)] hover:underline" @click="exportPriceTypeIds = []">Keine (=Alle)</button>
          </div>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">Leer = alle Preistypen exportieren.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
          <label
            v-for="pt in priceTypeList"
            :key="pt.id"
            class="flex items-center gap-1.5 text-[11px] cursor-pointer hover:bg-[var(--color-bg)] px-2 py-1.5 rounded transition-colors"
          >
            <input type="checkbox" :checked="exportPriceTypeIds.includes(pt.id)" @change="toggleItem(exportPriceTypeIds, pt.id)" class="rounded" />
            {{ pt.name_de || pt.technical_name }}
          </label>
        </div>
      </div>

      <!-- Beziehungstypen -->
      <div class="pim-card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Beziehungstypen</h3>
          <div class="flex gap-2">
            <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="exportRelationTypeIds = relationTypeList.map(r => r.id)">Alle</button>
            <button class="text-[10px] text-[var(--color-text-tertiary)] hover:underline" @click="exportRelationTypeIds = []">Keine (=Alle)</button>
          </div>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">Leer = alle Beziehungstypen exportieren.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
          <label
            v-for="rt in relationTypeList"
            :key="rt.id"
            class="flex items-center gap-1.5 text-[11px] cursor-pointer hover:bg-[var(--color-bg)] px-2 py-1.5 rounded transition-colors"
          >
            <input type="checkbox" :checked="exportRelationTypeIds.includes(rt.id)" @change="toggleItem(exportRelationTypeIds, rt.id)" class="rounded" />
            {{ rt.name_de || rt.technical_name }}
          </label>
        </div>
      </div>

      <!-- Export Progress -->
      <div v-if="exportProgress" class="pim-card p-4 space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="text-[var(--color-text-secondary)] flex items-center gap-2">
            <Loader2 class="w-3.5 h-3.5 animate-spin text-[var(--color-accent)]" :stroke-width="2" />
            Export wird generiert...
          </span>
          <span v-if="exportProgress.total > 0" class="text-[var(--color-text-tertiary)]">
            {{ Math.round(exportProgress.loaded / 1024) }} KB
            <template v-if="exportProgress.percent > 0"> ({{ exportProgress.percent }}%)</template>
          </span>
        </div>
        <div class="w-full bg-[var(--color-bg)] rounded-full h-2 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300 ease-out"
            :class="exportProgress.percent > 0 ? 'bg-[var(--color-accent)]' : 'bg-[var(--color-accent)] animate-pulse'"
            :style="{ width: exportProgress.percent > 0 ? exportProgress.percent + '%' : '100%' }"
          />
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
        {{ exporting ? 'Exportiere...' : 'BMEcat Export starten' }}
      </button>
    </template>

    <!-- REST/CLI Hinweis -->
    <div class="pim-card p-4 bg-[var(--color-bg)]">
      <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-2">REST / CLI</p>
      <div class="space-y-1 text-[10px] font-mono text-[var(--color-text-tertiary)]">
        <template v-if="activeTab === 'import'">
          <p>curl -X POST /api/v1/bmecat-import -F file=@katalog.xml -H "Authorization: Bearer {token}"</p>
          <p>curl -X POST /api/v1/bmecat-import/validate -F file=@katalog.xml -H "Authorization: Bearer {token}"</p>
          <p>php artisan pim:bmecat-import /pfad/katalog.xml --mode=update</p>
        </template>
        <template v-else>
          <p>curl -X POST /api/v1/bmecat-export -d '{"version":"2005"}' -H "Content-Type: application/json" -H "Authorization: Bearer {token}" -o export.xml</p>
          <p>php artisan pim:bmecat-export --version=2005 --hierarchy=main -o export.xml</p>
        </template>
      </div>
    </div>
  </div>
</template>
