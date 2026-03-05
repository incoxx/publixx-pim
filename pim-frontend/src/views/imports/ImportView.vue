<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  Upload, FileSpreadsheet, Download, ArrowRight, ArrowLeft,
  CheckCircle, AlertTriangle, XCircle, Play, RefreshCw, Columns3,
  Wand2, Sparkles, Plus,
} from 'lucide-vue-next'
import importsApi from '@/api/imports'
import importProfilesApi from '@/api/importProfiles'
import hierarchiesApi from '@/api/hierarchies'
import { attributeViews as attributeViewsApi, productTypes as productTypesApi, attributeTypes as attributeTypesApi } from '@/api/attributes'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'

// --- Wizard State ---
const step = ref(1) // 1=Upload, 2=Mapping, 3=Preview, 4=Execute
const error = ref('')

// Step 1: Upload
const file = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
const importJob = ref(null)
const analysis = ref(null)

// Import Profiles
const importProfiles = ref([])
const selectedProfileId = ref(null)

// Step 2: Mapping
const mappingTab = ref('products')
const importMode = ref('update') // 'update', 'delete_insert', 'delete'
const importModes = [
  { value: 'update', label: 'Update', hint: 'vorhandene aktualisieren' },
  { value: 'delete_insert', label: 'Delete / Insert', hint: 'komplett neu anlegen' },
  { value: 'delete', label: 'Löschen', hint: 'per SKU löschen' },
]
const skuColumn = ref('SKU')
const nameColumn = ref('')
const eanColumn = ref('')
const productTypeId = ref(null)
const columnMappings = ref([])
const priceMappings = ref([])
const relationMappings = ref([])

// Product Types
const productTypes = ref([])

// Auto-Generate Attributes
const autoGenerateChecked = ref({}) // header → boolean
const detectedTypes = ref({}) // header → { type, confidence, samples }
const hierarchies = ref([])
const hierarchyNodes = ref([])
const attributeViews = ref([])
const selectedHierarchyId = ref(null)
const selectedNodeId = ref(null)
const selectedViewId = ref(null)
const selectedAttributeTypeId = ref(null)
const attributeTypesList = ref([])
const autoGenerating = ref(false)
const autoGenerateResult = ref(null)

// Step 3: Preview
const preview = ref(null)
const previewLoading = ref(false)

// Step 4: Execute
const executing = ref(false)
const executionResult = ref(null)
const logs = ref([])
const logPolling = ref(null)

onMounted(async () => {
  try {
    const [profilesRes, hierarchiesRes, viewsRes, typesRes, attrTypesRes] = await Promise.all([
      importProfilesApi.list(),
      hierarchiesApi.list(),
      attributeViewsApi.list(),
      productTypesApi.list(),
      attributeTypesApi.list(),
    ])
    importProfiles.value = profilesRes.data.data || profilesRes.data
    hierarchies.value = (hierarchiesRes.data.data || hierarchiesRes.data).filter(h => h.hierarchy_type === 'master')
    attributeViews.value = viewsRes.data.data || viewsRes.data
    productTypes.value = typesRes.data.data || typesRes.data
    attributeTypesList.value = attrTypesRes.data.data || attrTypesRes.data
  } catch (e) { /* ignore */ }
})

onUnmounted(() => {
  if (logPolling.value) clearInterval(logPolling.value)
})

// --- Step 1: Upload ---
async function handleUpload(e) {
  const f = e.target?.files?.[0]
  if (!f) return
  file.value = f
  uploading.value = true
  error.value = ''

  try {
    const [uploadRes, analyzeRes] = await Promise.all([
      importsApi.upload(f),
      importProfilesApi.analyze(f),
    ])
    importJob.value = uploadRes.data.data || uploadRes.data
    analysis.value = analyzeRes.data.data || analyzeRes.data

    if (analysis.value?.sheets) {
      const firstSheet = Object.values(analysis.value.sheets)[0]
      if (firstSheet?.headers) {
        columnMappings.value = firstSheet.headers
          .filter(h => h.toLowerCase() !== 'sku')
          .map(h => ({ source: h, target_attribute_id: '', language: '' }))

        // Erkannte Datentypen speichern + Checkboxen initialisieren
        if (firstSheet.detected_types) {
          detectedTypes.value = firstSheet.detected_types
        }
        const checked = {}
        for (const h of firstSheet.headers) {
          if (h.toLowerCase() !== 'sku') checked[h] = false
        }
        autoGenerateChecked.value = checked
      }
    }
  } catch (err) {
    error.value = err.response?.data?.title || err.response?.data?.message || 'Upload fehlgeschlagen'
  } finally { uploading.value = false }
}

function handleDrop(e) {
  e.preventDefault()
  const f = e.dataTransfer?.files?.[0]
  if (f) handleUpload({ target: { files: [f] } })
}

// --- Profile ---
function loadProfile(id) {
  const profile = importProfiles.value.find(p => p.id === id)
  if (!profile) return
  skuColumn.value = profile.sku_column || 'SKU'
  productTypeId.value = profile.product_type_id || null
  columnMappings.value = (profile.column_mappings || []).map(m => ({ ...m }))
  priceMappings.value = (profile.price_mappings || []).map(m => ({ ...m }))
  relationMappings.value = (profile.relation_mappings || []).map(m => ({ ...m }))
}

async function saveProfile({ name, is_shared }) {
  try {
    await importProfilesApi.create({
      name,
      is_shared,
      sku_column: skuColumn.value,
      product_type_id: productTypeId.value,
      column_mappings: columnMappings.value.filter(m => m.target_attribute_id),
      price_mappings: priceMappings.value.length ? priceMappings.value : null,
      relation_mappings: relationMappings.value.length ? relationMappings.value : null,
    })
    const { data } = await importProfilesApi.list()
    importProfiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht gespeichert werden'
  }
}

async function updateProfile({ id, name, is_shared }) {
  try {
    await importProfilesApi.update(id, {
      name,
      is_shared,
      sku_column: skuColumn.value,
      product_type_id: productTypeId.value,
      column_mappings: columnMappings.value.filter(m => m.target_attribute_id),
      price_mappings: priceMappings.value.length ? priceMappings.value : null,
      relation_mappings: relationMappings.value.length ? relationMappings.value : null,
    })
    const { data } = await importProfilesApi.list()
    importProfiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht aktualisiert werden'
  }
}

async function deleteProfile(id) {
  try {
    await importProfilesApi.remove(id)
    selectedProfileId.value = null
    const { data } = await importProfilesApi.list()
    importProfiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht gelöscht werden'
  }
}

const autoMatchedSources = ref(new Set()) // Track which sources were auto-matched

function autoMatch() {
  if (!analysis.value?.available_attributes) return
  const attrs = analysis.value.available_attributes
  const matched = new Set()
  columnMappings.value = columnMappings.value.map(m => {
    if (m.target_attribute_id) return m
    const headerLower = m.source.toLowerCase()
    const match = attrs.find(a =>
      a.technical_name.toLowerCase() === headerLower ||
      (a.name_de && a.name_de.toLowerCase() === headerLower)
    )
    if (match) {
      matched.add(m.source)
      return { ...m, target_attribute_id: match.id }
    }
    return m
  })
  autoMatchedSources.value = matched
}

// --- Auto-Generate ---
async function loadNodes() {
  if (!selectedHierarchyId.value) { hierarchyNodes.value = []; selectedNodeId.value = null; return }
  try {
    const { data } = await hierarchiesApi.getTree(selectedHierarchyId.value)
    const tree = data.data || data
    // Flatten tree into a list with indentation
    const flat = []
    function walk(nodes, depth = 0) {
      for (const node of (nodes || [])) {
        flat.push({ ...node, _depth: depth, _label: '\u00A0\u00A0'.repeat(depth) + (node.name_de || node.name_en || node.id) })
        if (node.children?.length) walk(node.children, depth + 1)
      }
    }
    walk(Array.isArray(tree) ? tree : tree.nodes || [tree])
    hierarchyNodes.value = flat
  } catch (e) { hierarchyNodes.value = [] }
}

const autoGenerateSelectedCount = computed(() =>
  Object.values(autoGenerateChecked.value).filter(Boolean).length
)
const unmappedCount = computed(() => columnMappings.value.filter(m => !m.target_attribute_id).length)
const allUnmappedChecked = computed(() =>
  unmappedCount.value > 0 && autoGenerateSelectedCount.value === unmappedCount.value
)

function toggleAllAutoGenerate() {
  const allChecked = autoGenerateSelectedCount.value === columnMappings.value.filter(m => !m.target_attribute_id).length
  for (const m of columnMappings.value) {
    if (!m.target_attribute_id) {
      autoGenerateChecked.value[m.source] = !allChecked
    }
  }
}

async function runAutoGenerate() {
  if (!selectedNodeId.value || !selectedViewId.value) {
    error.value = 'Bitte Kategorie und Attribut-Sicht auswählen'
    return
  }
  const columns = columnMappings.value
    .filter(m => autoGenerateChecked.value[m.source])
    .map(m => ({
      header: m.source,
      auto_generate: true,
      detected_type: detectedTypes.value[m.source]?.type || 'String',
    }))

  if (!columns.length) { error.value = 'Keine Spalten zum Anlegen ausgewählt'; return }

  autoGenerating.value = true
  error.value = ''
  autoGenerateResult.value = null

  try {
    const { data } = await importProfilesApi.autoGenerateAttributes({
      hierarchy_node_id: selectedNodeId.value,
      attribute_view_id: selectedViewId.value,
      attribute_type_id: selectedAttributeTypeId.value || undefined,
      columns,
    })
    autoGenerateResult.value = data.data || data

    // Neu-erstellte und existierende Attribute dem Mapping zuordnen + Attributliste aktualisieren
    const allNew = [...(autoGenerateResult.value.created || []), ...(autoGenerateResult.value.existing || [])]
    for (const attr of allNew) {
      // Zur verfügbaren Liste hinzufügen falls nicht vorhanden
      if (!analysis.value.available_attributes.find(a => a.id === attr.id)) {
        analysis.value.available_attributes.push(attr)
      }
      // Auto-Zuordnung im Mapping
      const mapping = columnMappings.value.find(m =>
        m.source.toLowerCase() === attr.name_de?.toLowerCase() ||
        m.source.toLowerCase() === attr.technical_name?.toLowerCase()
      )
      if (mapping && !mapping.target_attribute_id) {
        mapping.target_attribute_id = attr.id
      }
    }

    // Checkboxen zurücksetzen
    for (const key of Object.keys(autoGenerateChecked.value)) {
      autoGenerateChecked.value[key] = false
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Attribute konnten nicht angelegt werden'
  } finally { autoGenerating.value = false }
}

// Erkennt ob es ein Flat-Import ist (keine PIM-Sheets erkannt)
const isFlatImport = computed(() => {
  const found = importJob.value?.sheets_found
  return !found || found.length === 0
})

// --- Step 3: Preview ---
async function loadPreview() {
  if (!importJob.value) return
  previewLoading.value = true
  error.value = ''
  try {
    if (isFlatImport.value && analysis.value?.sheets) {
      // Flat-Import: Preview direkt aus Analyse-Daten bauen (Backend kennt kein Flat-Format)
      const flatSummary = {}
      for (const [sheetName, sheet] of Object.entries(analysis.value.sheets)) {
        if (sheet.row_count > 0) {
          flatSummary[sheetName] = {
            total: sheet.row_count || 0,
            valid: sheet.row_count || 0,
            creates: 0,
            updates: 0,
            errors: 0,
          }
        }
      }
      preview.value = {
        import_id: importJob.value.id,
        status: importJob.value.status,
        summary: flatSummary,
        errors: [],
        _flat_import: true,
      }
    } else {
      // PIM-Format: Backend-Preview nutzen
      const { data } = await importsApi.getPreview(importJob.value.id)
      preview.value = data.data || data
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Vorschau fehlgeschlagen'
  } finally { previewLoading.value = false }
}

async function downloadErrors() {
  if (!importJob.value) return
  try {
    const response = await importsApi.downloadErrors(importJob.value.id)
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `fehlerbericht-${importJob.value.id}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'Keine Fehler vorhanden — kein Fehlerbericht verfügbar.'
    } else {
      error.value = 'Fehlerbericht konnte nicht heruntergeladen werden'
    }
  }
}

// --- Step 4: Execute ---
async function executeImport() {
  if (!importJob.value) return
  executing.value = true
  error.value = ''
  logs.value = []

  logPolling.value = setInterval(pollLogs, 2000)

  try {
    const executePayload = { mode: importMode.value }
    // Flat-Import: Mappings mitsenden, damit das Backend die Flat-Datei importieren kann
    if (isFlatImport.value) {
      executePayload.flat_import = true
      executePayload.sku_column = skuColumn.value
      executePayload.name_column = nameColumn.value || null
      executePayload.ean_column = eanColumn.value || null
      executePayload.product_type_id = productTypeId.value
      executePayload.master_hierarchy_node_id = selectedNodeId.value
      executePayload.column_mappings = columnMappings.value
        .filter(m => m.target_attribute_id)
        .map(m => ({
          source: m.source,
          target_attribute_id: m.target_attribute_id,
          language: m.language || null,
        }))
    }
    await importsApi.execute(importJob.value.id, executePayload)
    await pollStatus()
  } catch (e) {
    error.value = e.response?.data?.message || 'Import fehlgeschlagen'
  } finally {
    executing.value = false
    if (logPolling.value) { clearInterval(logPolling.value); logPolling.value = null }
    await pollLogs()
  }
}

async function pollLogs() {
  if (!importJob.value) return
  try {
    const { data } = await importsApi.getLogs(importJob.value.id)
    logs.value = data.data || data
  } catch (e) { /* ignore */ }
}

async function pollStatus() {
  if (!importJob.value) return
  for (let i = 0; i < 60; i++) {
    await new Promise(r => setTimeout(r, 2000))
    try {
      const { data } = await importsApi.getStatus(importJob.value.id)
      importJob.value = data.data || data
      if (['completed', 'failed'].includes(importJob.value.status)) {
        const { data: resultData } = await importsApi.getResult(importJob.value.id)
        executionResult.value = resultData.data || resultData
        return
      }
    } catch (e) { break }
  }
}

async function downloadTemplate(type) {
  try {
    const { data } = await importsApi.downloadTemplate(type)
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `import-template-${type}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = 'Template-Download fehlgeschlagen'
  }
}

function resetWizard() {
  step.value = 1
  file.value = null
  importJob.value = null
  analysis.value = null
  preview.value = null
  executionResult.value = null
  logs.value = []
  error.value = ''
}

const errorCount = computed(() => preview.value?.errors?.length ?? 0)
const sheetsInfo = computed(() => {
  if (!analysis.value?.sheets) return []
  // Leere Sheets (z.B. "Tabelle1" mit 0 Zeilen) ausfiltern
  return Object.values(analysis.value.sheets).filter(s => s.row_count > 0 || s.headers?.length > 0)
})
const availableAttributes = computed(() => analysis.value?.available_attributes || [])
const mappedCount = computed(() => columnMappings.value.filter(m => m.target_attribute_id).length)

/** Sprache ist nur bei Text-artigen Attributen relevant (nicht bei Number, Float, Date, Flag). */
function isLanguageRelevant(mapping) {
  // Wenn ein Attribut zugeordnet ist, prüfe dessen Datentyp
  if (mapping.target_attribute_id) {
    const attr = availableAttributes.value.find(a => a.id === mapping.target_attribute_id)
    if (attr) {
      const nonTranslatable = ['Number', 'Float', 'Integer', 'Date', 'Flag', 'Boolean']
      return !nonTranslatable.includes(attr.data_type)
    }
  }
  // Ohne Zuordnung: prüfe erkannten Typ
  const detected = detectedTypes.value[mapping.source]
  if (detected) {
    return !['Number', 'Float', 'Integer', 'Date', 'Flag', 'Boolean'].includes(detected.type)
  }
  return true // Default: anzeigen
}

const logLevelClass = {
  info: 'text-[var(--color-text-tertiary)]',
  warning: 'text-amber-600',
  error: 'text-[var(--color-error)]',
}
const logLevelIcon = { info: CheckCircle, warning: AlertTriangle, error: XCircle }
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Import</h2>
      <div class="flex gap-2">
        <button class="pim-btn pim-btn-secondary text-xs" @click="downloadTemplate('products')">
          <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
          Template
        </button>
        <button v-if="step > 1" class="pim-btn pim-btn-secondary text-xs" @click="resetWizard">
          <RefreshCw class="w-3.5 h-3.5" :stroke-width="1.75" />
          Neuer Import
        </button>
      </div>
    </div>

    <!-- Wizard Steps -->
    <div class="flex items-center gap-1 text-[11px]">
      <template v-for="(s, i) in ['Datei hochladen', 'Mapping', 'Vorschau & Validierung', 'Ausführen']" :key="i">
        <div
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full font-medium transition-colors',
            step > i + 1 ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' :
            step === i + 1 ? 'bg-[var(--color-accent)] text-white' :
            'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]',
          ]"
        >
          <CheckCircle v-if="step > i + 1" class="w-3 h-3" :stroke-width="2" />
          <span v-else>{{ i + 1 }}</span>
          <span class="hidden sm:inline">{{ s }}</span>
        </div>
        <ArrowRight v-if="i < 3" class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
      </template>
    </div>

    <!-- Import Profile Selector -->
    <ProfileSelector
      v-if="step <= 2"
      :profiles="importProfiles"
      v-model="selectedProfileId"
      label="Import-Profil"
      @load="loadProfile"
      @save="saveProfile"
      @update="updateProfile"
      @delete="deleteProfile"
    />

    <!-- Step 1: Upload -->
    <div v-if="step === 1" class="pim-card p-8">
      <div
        class="border-2 border-dashed border-[var(--color-border)] rounded-xl p-12 text-center hover:border-[var(--color-accent)] transition-colors cursor-pointer"
        @dragover.prevent
        @drop="handleDrop"
        @click="fileInput?.click()"
      >
        <Upload class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <p class="text-sm text-[var(--color-text-secondary)]">
          {{ uploading ? 'Wird hochgeladen und analysiert...' : 'Excel-Datei hierhin ziehen oder klicken' }}
        </p>
        <p class="text-xs text-[var(--color-text-tertiary)] mt-1">.xlsx Dateien bis 50 MB</p>
        <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="handleUpload" />
      </div>

      <div v-if="analysis && !uploading" class="mt-6 space-y-3">
        <div class="flex items-center gap-2 text-sm text-[var(--color-text-primary)]">
          <FileSpreadsheet class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
          <span class="font-medium">{{ file?.name }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <div v-for="sheet in sheetsInfo" :key="sheet.name" class="p-2 rounded-lg bg-[var(--color-bg)] text-xs">
            <p class="font-medium text-[var(--color-text-primary)]">{{ sheet.name }}</p>
            <p class="text-[var(--color-text-tertiary)]">{{ sheet.row_count }} Zeilen, {{ sheet.headers.length }} Spalten</p>
          </div>
        </div>
        <button class="pim-btn pim-btn-primary text-xs" @click="step = 2">
          Weiter zum Mapping
          <ArrowRight class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Step 2: Mapping -->
    <div v-if="step === 2" class="space-y-4">
      <!-- Import-Modus -->
      <div class="flex items-center gap-4 px-1 flex-wrap">
        <span class="text-[11px] font-medium text-[var(--color-text-secondary)]">Import-Modus:</span>
        <label v-for="mode in importModes" :key="mode.value" class="flex items-center gap-1.5 cursor-pointer">
          <input type="radio" v-model="importMode" :value="mode.value" class="radio radio-xs radio-accent" />
          <span class="text-xs" :class="importMode === mode.value ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-tertiary)]'">
            {{ mode.label }}
          </span>
          <span class="text-[10px] text-[var(--color-text-tertiary)]">({{ mode.hint }})</span>
        </label>
      </div>

      <div v-if="importMode === 'delete_insert'" class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
        <AlertTriangle class="inline w-3.5 h-3.5 -mt-0.5 mr-1" :stroke-width="2" />
        Achtung: Alle Produkte aus der Datei werden zuerst gelöscht (inkl. Werte, Preise, Beziehungen, Medien) und dann komplett neu angelegt.
      </div>

      <div v-if="importMode === 'delete'" class="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800">
        <XCircle class="inline w-3.5 h-3.5 -mt-0.5 mr-1" :stroke-width="2" />
        Achtung: Alle Produkte aus der Datei (anhand SKU) werden unwiderruflich gelöscht inkl. aller zugehörigen Daten.
      </div>

      <div class="flex border-b border-[var(--color-border)]">
        <button
          v-for="tab in [{ key: 'products', label: 'Produkte' }, { key: 'prices', label: 'Preise' }, { key: 'relations', label: 'Beziehungen' }]"
          :key="tab.key"
          :class="[
            'px-4 py-2.5 text-xs font-medium border-b-2 -mb-px',
            mappingTab === tab.key
              ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
              : 'border-transparent text-[var(--color-text-tertiary)]',
          ]"
          @click="mappingTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="mappingTab === 'products'" class="pim-card p-5 space-y-4">
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">SKU-Spalte *</label>
            <select class="pim-input text-xs w-full" v-model="skuColumn">
              <template v-for="sheet in sheetsInfo" :key="sheet.name">
                <option v-for="h in (sheet.headers || [])" :key="h" :value="h">{{ h }}</option>
              </template>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Name-Spalte</label>
            <select class="pim-input text-xs w-full" v-model="nameColumn">
              <option value="">— Nicht zuordnen —</option>
              <template v-for="sheet in sheetsInfo" :key="sheet.name">
                <option v-for="h in (sheet.headers || [])" :key="h" :value="h">{{ h }}</option>
              </template>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">EAN-Spalte</label>
            <select class="pim-input text-xs w-full" v-model="eanColumn">
              <option value="">— Nicht zuordnen —</option>
              <template v-for="sheet in sheetsInfo" :key="sheet.name">
                <option v-for="h in (sheet.headers || [])" :key="h" :value="h">{{ h }}</option>
              </template>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Produkttyp</label>
            <select class="pim-input text-xs w-full" v-model="productTypeId">
              <option :value="null">— Optional —</option>
              <option v-for="pt in productTypes" :key="pt.id" :value="pt.id">{{ pt.name_de || pt.technical_name }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Kategorie</label>
            <div class="flex gap-2">
              <select class="pim-input text-xs flex-1" v-model="selectedHierarchyId" @change="loadNodes">
                <option :value="null">— Hierarchie —</option>
                <option v-for="h in hierarchies" :key="h.id" :value="h.id">{{ h.name_de || h.technical_name }}</option>
              </select>
              <select class="pim-input text-xs flex-1" v-model="selectedNodeId" :disabled="!hierarchyNodes.length">
                <option :value="null">— Knoten —</option>
                <option v-for="n in hierarchyNodes" :key="n.id" :value="n.id">{{ n._label }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
            <Columns3 class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
            Attribut-Mapping ({{ mappedCount }}/{{ columnMappings.length }})
          </h3>
          <button class="pim-btn pim-btn-secondary text-xs" @click="autoMatch">
            <Wand2 class="w-3.5 h-3.5" :stroke-width="1.75" />
            Auto-Match
          </button>
        </div>

        <div class="space-y-2 max-h-[400px] overflow-y-auto">
          <!-- Header mit Select-All Checkbox -->
          <div class="flex items-center gap-2 p-2 rounded-lg bg-[var(--color-bg)] border-b border-[var(--color-border)] sticky top-0 z-10">
            <input
              type="checkbox"
              :checked="allUnmappedChecked"
              :indeterminate="autoGenerateSelectedCount > 0 && !allUnmappedChecked"
              @change="toggleAllAutoGenerate"
              class="checkbox checkbox-xs checkbox-accent shrink-0"
              title="Alle nicht zugeordneten auswählen / abwählen"
            />
            <span class="text-[10px] font-medium text-[var(--color-text-tertiary)]">Alle auswählen ({{ autoGenerateSelectedCount }}/{{ unmappedCount }})</span>
          </div>
          <div
            v-for="(mapping, i) in columnMappings"
            :key="i"
            :class="[
              'flex items-center gap-2 p-2 rounded-lg transition-colors',
              autoMatchedSources.has(mapping.source)
                ? 'bg-emerald-50 ring-1 ring-emerald-200'
                : 'hover:bg-[var(--color-bg)]',
            ]"
          >
            <input
              type="checkbox"
              v-model="autoGenerateChecked[mapping.source]"
              :disabled="!!mapping.target_attribute_id"
              class="checkbox checkbox-xs checkbox-accent shrink-0"
              :title="mapping.target_attribute_id ? 'Bereits zugeordnet' : 'Für Auto-Anlage markieren'"
            />
            <span class="text-xs font-mono text-[var(--color-text-secondary)] w-36 truncate" :title="mapping.source">
              {{ mapping.source }}
            </span>
            <span
              v-if="detectedTypes[mapping.source]"
              class="text-[9px] px-1.5 py-0.5 rounded-full font-medium shrink-0"
              :class="{
                'bg-blue-100 text-blue-700': detectedTypes[mapping.source].type === 'String',
                'bg-emerald-100 text-emerald-700': detectedTypes[mapping.source].type === 'Number',
                'bg-amber-100 text-amber-700': detectedTypes[mapping.source].type === 'Float',
                'bg-purple-100 text-purple-700': detectedTypes[mapping.source].type === 'Date',
                'bg-pink-100 text-pink-700': detectedTypes[mapping.source].type === 'Flag',
              }"
              :title="`Erkannt: ${detectedTypes[mapping.source].type} (${detectedTypes[mapping.source].confidence}% Konfidenz, ${detectedTypes[mapping.source].samples} Werte)`"
            >
              {{ detectedTypes[mapping.source].type }}
            </span>
            <ArrowRight class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
            <select class="pim-input text-xs flex-1" v-model="mapping.target_attribute_id">
              <option value="">— Nicht zuordnen —</option>
              <option v-for="attr in availableAttributes" :key="attr.id" :value="attr.id">
                {{ attr.name_de || attr.technical_name }} ({{ attr.data_type }})
              </option>
            </select>
            <select
              v-if="isLanguageRelevant(mapping)"
              class="pim-input text-xs w-16"
              v-model="mapping.language"
            >
              <option value="">—</option>
              <option value="de">DE</option>
              <option value="en">EN</option>
              <option value="fr">FR</option>
            </select>
            <span v-else class="w-16 text-center text-[10px] text-[var(--color-text-tertiary)]">—</span>
          </div>
        </div>

        <!-- Auto-Generate Attribute Panel -->
        <div v-if="autoGenerateSelectedCount > 0 || autoGenerateResult" class="border border-dashed border-[var(--color-accent)]/40 rounded-xl p-4 space-y-3 bg-[var(--color-accent)]/5">
          <div class="flex items-center gap-2">
            <Sparkles class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
            <h4 class="text-xs font-semibold text-[var(--color-text-primary)]">
              Attribute automatisch anlegen
              <span v-if="autoGenerateSelectedCount > 0" class="text-[var(--color-accent)]">({{ autoGenerateSelectedCount }} ausgewählt)</span>
            </h4>
            <button
              class="ml-auto text-[10px] text-[var(--color-accent)] hover:underline"
              @click="toggleAllAutoGenerate"
            >
              {{ allUnmappedChecked ? 'Keine' : 'Alle nicht zugeordneten' }} auswählen
            </button>
          </div>

          <div v-if="!selectedNodeId" class="text-[10px] text-amber-600">
            Bitte oben eine Kategorie (Hierarchie + Knoten) auswählen, um Attribute zuordnen zu können.
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[10px] font-medium text-[var(--color-text-secondary)] mb-1">Attribut-Sicht</label>
              <select class="pim-input text-xs w-full" v-model="selectedViewId">
                <option :value="null">— Sicht wählen —</option>
                <option v-for="v in attributeViews" :key="v.id" :value="v.id">{{ v.name_de || v.technical_name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-medium text-[var(--color-text-secondary)] mb-1">Attributgruppe</label>
              <select class="pim-input text-xs w-full" v-model="selectedAttributeTypeId">
                <option :value="null">— Optional —</option>
                <option v-for="at in attributeTypesList" :key="at.id" :value="at.id">{{ at.name_de || at.technical_name }}</option>
              </select>
            </div>
          </div>

          <button
            class="pim-btn pim-btn-primary text-xs"
            :disabled="autoGenerating || autoGenerateSelectedCount === 0 || !selectedNodeId || !selectedViewId"
            @click="runAutoGenerate"
          >
            <Plus v-if="!autoGenerating" class="w-3.5 h-3.5" :stroke-width="2" />
            <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
            {{ autoGenerating ? 'Wird angelegt...' : `${autoGenerateSelectedCount} Attribute anlegen` }}
          </button>

          <!-- Result -->
          <div v-if="autoGenerateResult" class="text-xs p-3 rounded-lg bg-[var(--color-success-light)] text-[var(--color-success)]">
            <CheckCircle class="inline w-3.5 h-3.5 -mt-0.5 mr-1" :stroke-width="2" />
            {{ autoGenerateResult.created?.length || 0 }} neu angelegt,
            {{ autoGenerateResult.existing?.length || 0 }} bereits vorhanden,
            {{ autoGenerateResult.assigned_to_category || 0 }} der Kategorie zugeordnet,
            {{ autoGenerateResult.assigned_to_view || 0 }} der Sicht zugeordnet.
          </div>
        </div>
      </div>

      <div v-if="mappingTab === 'prices'" class="pim-card p-5">
        <p class="text-xs text-[var(--color-text-tertiary)]">
          Preise werden aus dem Sheet "Preise" importiert, sofern vorhanden. Standard-Format: SKU, Preistyp, Betrag, Währung, Ab Menge, Gültig von, Gültig bis.
        </p>
      </div>

      <div v-if="mappingTab === 'relations'" class="pim-card p-5">
        <p class="text-xs text-[var(--color-text-tertiary)]">
          Beziehungen werden aus dem Sheet "Beziehungen" importiert, sofern vorhanden. Standard-Format: Quell-SKU, Ziel-SKU, Beziehungstyp, Position.
        </p>
      </div>

      <div class="flex gap-3">
        <button class="pim-btn pim-btn-secondary text-xs" @click="step = 1">
          <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
          Zurück
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="step = 3; loadPreview()">
          Weiter zur Vorschau
          <ArrowRight class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Step 3: Preview & Validation -->
    <div v-if="step === 3" class="space-y-4">
      <div v-if="previewLoading" class="pim-card p-8">
        <div class="space-y-3">
          <div v-for="i in 5" :key="i" class="pim-skeleton h-8 rounded" />
        </div>
      </div>

      <template v-else-if="preview">
        <div class="grid grid-cols-3 gap-3">
          <div class="pim-card p-4 text-center">
            <p class="text-2xl font-bold text-[var(--color-accent)]">{{ Object.keys(preview.summary || {}).reduce((sum, k) => sum + ((preview.summary[k]?.total) || 0), 0) }}</p>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">Zeilen gesamt</p>
          </div>
          <div class="pim-card p-4 text-center">
            <p class="text-2xl font-bold text-[var(--color-success)]">{{ Object.keys(preview.summary || {}).reduce((sum, k) => sum + ((preview.summary[k]?.valid) || 0), 0) }}</p>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">Gültige Zeilen</p>
          </div>
          <div class="pim-card p-4 text-center">
            <p class="text-2xl font-bold" :class="errorCount > 0 ? 'text-[var(--color-error)]' : 'text-[var(--color-text-tertiary)]'">{{ errorCount }}</p>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">Fehler</p>
          </div>
        </div>

        <!-- Errors -->
        <div v-if="errorCount > 0" class="pim-card border-[var(--color-error)]/30 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 bg-[var(--color-error-light)]">
            <div class="flex items-center gap-2">
              <XCircle class="w-4 h-4 text-[var(--color-error)]" :stroke-width="2" />
              <span class="text-sm font-semibold text-[var(--color-error)]">{{ errorCount }} Validierungsfehler</span>
            </div>
            <button class="pim-btn pim-btn-secondary text-xs" @click="downloadErrors">
              <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
              Fehlerbericht
            </button>
          </div>
          <div class="max-h-[300px] overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="sticky top-0 bg-[var(--color-surface)]">
                <tr class="border-b border-[var(--color-border)]">
                  <th class="px-3 py-2 text-left text-[10px] uppercase text-[var(--color-text-tertiary)]">Sheet</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase text-[var(--color-text-tertiary)]">Zeile</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase text-[var(--color-text-tertiary)]">Spalte</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase text-[var(--color-text-tertiary)]">Wert</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase text-[var(--color-text-tertiary)]">Fehler</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase text-[var(--color-text-tertiary)]">Vorschlag</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(err, i) in preview.errors" :key="i" class="border-b border-[var(--color-border)]">
                  <td class="px-3 py-1.5 text-[var(--color-text-tertiary)]">{{ err.sheet }}</td>
                  <td class="px-3 py-1.5 font-mono">{{ err.row }}</td>
                  <td class="px-3 py-1.5 font-mono">{{ err.column }}</td>
                  <td class="px-3 py-1.5 font-mono text-[var(--color-error)]">{{ err.value }}</td>
                  <td class="px-3 py-1.5 text-[var(--color-error)]">{{ err.error }}</td>
                  <td class="px-3 py-1.5 text-[var(--color-success)]">{{ err.suggestion }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Sheet Summary -->
        <div v-if="preview.summary" class="pim-card p-4">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">Sheet-Übersicht</h3>
          <div class="space-y-2">
            <div
              v-for="(info, sheetKey) in preview.summary"
              :key="sheetKey"
              class="flex items-center justify-between text-xs p-2 rounded-lg bg-[var(--color-bg)]"
            >
              <span class="font-medium text-[var(--color-text-primary)]">{{ sheetKey }}</span>
              <div class="flex gap-4 text-[var(--color-text-tertiary)]">
                <span>{{ info.total ?? 0 }} Zeilen</span>
                <span>{{ info.creates ?? 0 }} neu</span>
                <span>{{ info.updates ?? 0 }} aktualisieren</span>
                <span v-if="info.errors" class="text-[var(--color-error)]">{{ info.errors }} Fehler</span>
              </div>
            </div>
          </div>
        </div>
      </template>

      <div class="flex gap-3">
        <button class="pim-btn pim-btn-secondary text-xs" @click="step = 2">
          <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
          Zurück
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="step = 4; executeImport()">
          <Play class="w-3.5 h-3.5" :stroke-width="2" />
          Import ausführen
        </button>
      </div>
    </div>

    <!-- Step 4: Execute -->
    <div v-if="step === 4" class="space-y-4">
      <div class="pim-card p-5 text-center">
        <div v-if="executing" class="space-y-3">
          <RefreshCw class="w-8 h-8 mx-auto text-[var(--color-accent)] animate-spin" :stroke-width="1.5" />
          <p class="text-sm font-medium text-[var(--color-text-primary)]">Import wird ausgeführt...</p>
          <p class="text-xs text-[var(--color-text-tertiary)]">{{ importJob?.status }}</p>
        </div>
        <div v-else-if="importJob?.status === 'completed'" class="space-y-3">
          <CheckCircle class="w-10 h-10 mx-auto text-[var(--color-success)]" :stroke-width="1.5" />
          <p class="text-sm font-semibold text-[var(--color-success)]">Import erfolgreich abgeschlossen</p>
        </div>
        <div v-else-if="importJob?.status === 'failed'" class="space-y-3">
          <XCircle class="w-10 h-10 mx-auto text-[var(--color-error)]" :stroke-width="1.5" />
          <p class="text-sm font-semibold text-[var(--color-error)]">Import fehlgeschlagen</p>
          <p class="text-xs text-[var(--color-error)]">{{ executionResult?.result?.error }}</p>
        </div>
      </div>

      <div v-if="executionResult?.result && importJob?.status === 'completed'" class="pim-card p-4">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">Ergebnis</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <template v-for="(value, key) in executionResult.result" :key="key">
            <div v-if="typeof value === 'number'" class="p-2 rounded-lg bg-[var(--color-bg)] text-center">
              <p class="text-lg font-bold text-[var(--color-accent)]">{{ value }}</p>
              <p class="text-[10px] text-[var(--color-text-tertiary)]">{{ key }}</p>
            </div>
          </template>
        </div>
      </div>

      <!-- Live Log -->
      <div v-if="logs.length > 0" class="pim-card overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 bg-[var(--color-bg)] border-b border-[var(--color-border)]">
          <span class="text-xs font-semibold text-[var(--color-text-primary)]">Import-Protokoll ({{ logs.length }})</span>
          <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="downloadErrors">
            <Download class="w-3 h-3" :stroke-width="2" />
            Fehlerbericht
          </button>
        </div>
        <div class="max-h-[300px] overflow-y-auto p-3 space-y-1 font-mono text-[11px]">
          <div
            v-for="log in logs"
            :key="log.id"
            :class="['flex items-start gap-2 px-2 py-1 rounded', logLevelClass[log.level]]"
          >
            <component :is="logLevelIcon[log.level]" class="w-3 h-3 mt-0.5 shrink-0" :stroke-width="2" />
            <span class="text-[var(--color-text-tertiary)] shrink-0">{{ log.phase }}</span>
            <span v-if="log.sheet" class="text-[var(--color-text-tertiary)] shrink-0">[{{ log.sheet }}:{{ log.row }}]</span>
            <span>{{ log.message }}</span>
          </div>
        </div>
      </div>

      <button class="pim-btn pim-btn-secondary text-xs" @click="resetWizard">
        <RefreshCw class="w-3.5 h-3.5" :stroke-width="1.75" />
        Neuen Import starten
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- REST/CLI Hinweis -->
    <div class="pim-card p-4 bg-[var(--color-bg)]">
      <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-2">REST / CLI</p>
      <div class="space-y-1 text-[10px] font-mono text-[var(--color-text-tertiary)]">
        <p>curl -X POST /api/v1/imports -F "file=@datei.xlsx" -H "Authorization: Bearer {token}"</p>
        <p>curl -X POST /api/v1/imports/{id}/execute -H "Authorization: Bearer {token}"</p>
        <p>php artisan pim:import /pfad/datei.xlsx --force</p>
        <p>php artisan pim:import --list-jobs</p>
      </div>
    </div>
  </div>
</template>
