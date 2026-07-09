<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  Star, Trash2, Download, FileSpreadsheet, FileText,
  Languages, Archive, X, GitCompareArrows, Pencil, Settings, ListFilter,
  Code2, ChevronDown, ChevronUp, FolderTree, FolderOpen,
} from 'lucide-vue-next'
import { useAttributeStore } from '@/stores/attributes'
import { useServerQuickLookup } from '@/composables/useServerQuickLookup'
import watchlistApi from '@/api/watchlist'
import productsApi from '@/api/products'
import searchApi from '@/api/search'
import PimTable from '@/components/shared/PimTable.vue'
import ColumnConfigPopover from '@/components/shared/ColumnConfigPopover.vue'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'
import { useColumnConfig } from '@/composables/useColumnConfig'
import { useColumnProfiles } from '@/composables/useColumnProfiles'
import { triggerDownload } from '@/utils/download'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ReportTemplatePickerModal from '@/components/reports/ReportTemplatePickerModal.vue'
import PdfTemplatePickerModal from '@/components/pdf-templates/PdfTemplatePickerModal.vue'
import { useLicenseStore } from '@/stores/license'
import BulkAssignProjectDialog from '@/components/dialogs/BulkAssignProjectDialog.vue'
import BulkAssignHierarchyNodeDialog from '@/components/dialogs/BulkAssignHierarchyNodeDialog.vue'
import BulkAddToCollectionDialog from '@/components/dialogs/BulkAddToCollectionDialog.vue'
import { useRecordNavigatorStore } from '@/stores/recordNavigator'

const router = useRouter()
const localeStore = useLocaleStore()
const attrStore = useAttributeStore()
const licenseStore = useLicenseStore()
const recordNavigatorStore = useRecordNavigatorStore()

const items = ref([])
const pimTableRef = ref(null)
const loading = ref(false)
const error = ref(null)

// Pagination — Merkliste wird serverseitig paginiert
const currentPage = ref(1)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })

// Export state
const showExportPanel = ref(false)
const showReportPicker = ref(false)
const showPdfTemplatePicker = ref(false)
const showPdfTemplatePickerSelected = ref(false)
const exporting = ref(null) // 'excel' | 'pdf' | 'pdf-zip' | 'xliff' | null
const xliffSourceLang = ref('de')
const xliffTargetLang = ref('en')

// Delete state
const deleteTarget = ref(null)
const deleting = ref(false)

// Selection & comparison
const selectedIds = ref([])
const showCompare = ref(false)
const compareData = ref(null)
const compareLoading = ref(false)
const showDiffsOnly = ref(false)

const canCompare = computed(() => {
  // Need exactly 2 selected, and they must map to product_ids
  const productIds = selectedProductIds.value
  return productIds.length === 2
})

const selectedProductIds = computed(() => {
  return selectedIds.value
    .map(id => items.value.find(i => i.id === id)?.product_id)
    .filter(Boolean)
})

const compareRows = computed(() => {
  if (!compareData.value?.rows) return []
  if (showDiffsOnly.value) return compareData.value.rows.filter(r => r.is_different)
  return compareData.value.rows
})

const defaultWatchlistColumns = [
  { key: 'product.sku', label: 'SKU', mono: true, exportKey: 'sku' },
  { key: 'product.name', label: 'Name', exportKey: 'name' },
  { key: 'product.status', label: 'Status', exportKey: 'status' },
  { key: 'product.product_type.name_de', label: 'Typ', exportKey: 'product_type' },
  { key: 'created_at', label: 'Hinzugefügt', exportKey: 'created_at' },
]

const extraWatchlistColumns = [
  { key: 'product.ean', label: 'EAN', mono: true, exportKey: 'ean' },
]

// Dynamic attribute columns
const searchableAttributes = ref([])

const attributeColumns = computed(() =>
  searchableAttributes.value.map(attr => ({
    key: `product.attributes.${attr.id}`,
    label: attr.name_de || attr.technical_name,
    group: 'Attribute',
    exportKey: `attr:${attr.id}`,
  }))
)

const { visibleColumns, allColumns, visibleKeys, toggleColumn, moveColumn, resetColumns } = useColumnConfig('columns:watchlist', defaultWatchlistColumns, extraWatchlistColumns, attributeColumns)

const { columnProfiles, selectedColumnProfileId, loadColumnProfiles, loadColumnProfile, saveColumnProfile, updateColumnProfile, deleteColumnProfile } = useColumnProfiles('watchlist', visibleKeys)

// Quick Lookup — filtert serverseitig über die komplette Treffermenge, nicht nur
// die aktuell angezeigte Seite.
const statusOptions = [
  { value: 'active', label: 'Aktiv' },
  { value: 'draft', label: 'Entwurf' },
  { value: 'inactive', label: 'Inaktiv' },
  { value: 'discontinued', label: 'Auslaufend' },
]

const productTypeOptions = computed(() =>
  attrStore.prodTypes.map(pt => ({ value: pt.id, label: pt.name_de || pt.technical_name }))
)

const quickLookupConfig = computed(() => {
  const config = {
    'product.sku': { type: 'text', placeholder: 'SKU...' },
    'product.name': { type: 'text', placeholder: 'Name...' },
    'product.status': { type: 'select', options: statusOptions },
    'product.product_type.name_de': { type: 'select', options: productTypeOptions.value },
    'product.ean': { type: 'text', placeholder: 'EAN...' },
  }
  searchableAttributes.value.forEach(attr => {
    config[`product.attributes.${attr.id}`] = { type: 'text', placeholder: `${attr.name_de || attr.technical_name}...` }
  })
  return config
})

// Spalten-Key → Backend-Filterfeld. product.attributes.{id} landet im verschachtelten
// filter[attributes][id]-Parameter (siehe WatchlistController::index()).
const QUICK_LOOKUP_FIELD_MAP = {
  'product.sku': 'sku',
  'product.name': 'name',
  'product.status': 'status',
  'product.product_type.name_de': 'product_type_id',
  'product.ean': 'ean',
}

const quickLookupMappedFilters = ref({})

function applyQuickLookupFilters(rawFilters) {
  const mapped = {}
  const attributeFilters = {}
  for (const [colKey, value] of Object.entries(rawFilters)) {
    if (value === '' || value == null) continue
    if (colKey.startsWith('product.attributes.')) {
      attributeFilters[colKey.replace('product.attributes.', '')] = value
      continue
    }
    const field = QUICK_LOOKUP_FIELD_MAP[colKey]
    if (!field) continue
    mapped[field] = value
  }
  if (Object.keys(attributeFilters).length > 0) mapped.attributes = attributeFilters
  quickLookupMappedFilters.value = mapped
  currentPage.value = 1
  loadWatchlist()
}

const { showQuickLookup, onQuickLookupChange, toggleQuickLookup } = useServerQuickLookup(applyQuickLookupFilters)

const tableRows = computed(() =>
  items.value.map(item => ({
    ...item,
    id: item.id,
    _productId: item.product_id,
  }))
)

async function loadWatchlist() {
  loading.value = true
  error.value = null
  try {
    const attrIds = visibleKeys.value
      .filter(k => k.startsWith('product.attributes.'))
      .map(k => k.replace('product.attributes.', ''))
    const params = { page: currentPage.value }
    if (attrIds.length > 0) {
      params.attribute_columns = attrIds
      params.language = 'de'
    }
    for (const [field, value] of Object.entries(quickLookupMappedFilters.value)) {
      if (field === 'attributes') {
        for (const [attrId, val] of Object.entries(value)) {
          params[`filter[attributes][${attrId}]`] = val
        }
      } else {
        params[`filter[${field}]`] = value
      }
    }
    const { data } = await watchlistApi.list(params)
    items.value = data.data || data
    if (data.meta) meta.value = data.meta
  } catch (e) {
    error.value = 'Fehler beim Laden der Merkliste'
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > meta.value.last_page) return
  currentPage.value = page
  loadWatchlist()
}

function openProduct(row) {
  const ids = tableRows.value.map(r => r.product_id).filter(Boolean)
  recordNavigatorStore.setContext('Merkliste', ids)
  router.push(`/products/${row.product_id}`)
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await watchlistApi.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await Promise.all([loadWatchlist(), loadWatchlistProductIds()])
  } finally {
    deleting.value = false
  }
}

async function exportExcel() {
  exporting.value = 'excel'
  try {
    // Map watchlist column keys to export keys via explicit exportKey property
    const exportColumns = visibleKeys.value.map(k => {
      const col = allColumns.value.find(c => c.key === k)
      return col?.exportKey || k
    })
    // Export bezieht sich immer auf die gesamte Merkliste, nicht nur die aktuell
    // angezeigte Seite.
    const productIds = watchlistProductIds.value
    const resp = await productsApi.exportExcel({
      columns: exportColumns,
      product_ids: productIds,
      language: 'de',
    })
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0,10)}.xlsx`)
  } catch (e) {
    error.value = 'Excel-Export fehlgeschlagen'
    console.error('Excel export failed', e)
  } finally { exporting.value = null }
}

async function exportPdf() {
  exporting.value = 'pdf'
  try {
    const resp = await watchlistApi.exportPdf()
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0,10)}.pdf`)
  } catch (e) { console.error('PDF export failed', e) }
  finally { exporting.value = null }
}

async function exportPdfZip() {
  exporting.value = 'pdf-zip'
  try {
    const resp = await watchlistApi.exportPdfZip()
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0,10)}.zip`)
  } catch (e) { console.error('PDF-ZIP export failed', e) }
  finally { exporting.value = null }
}

async function exportXliff() {
  exporting.value = 'xliff'
  try {
    const resp = await watchlistApi.exportXliff(xliffSourceLang.value, xliffTargetLang.value)
    triggerDownload(resp.data, `merkliste-${xliffSourceLang.value}-${xliffTargetLang.value}.xliff`)
  } catch (e) { console.error('XLIFF export failed', e) }
  finally { exporting.value = null }
}

// --- Selection & Comparison ---
function handleSelect(ids) {
  selectedIds.value = ids
}

async function openCompare() {
  if (!canCompare.value) return
  const pids = selectedProductIds.value
  showCompare.value = true
  compareLoading.value = true
  compareData.value = null
  try {
    const { data } = await productsApi.compare(pids[0], pids[1])
    compareData.value = data.data || data
  } catch (e) { console.error('Compare failed:', e) }
  finally { compareLoading.value = false }
}

function openBulkEditor() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-edit', query: { ids } })
}

function openBulkUpdate() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-update', query: { ids } })
}

// --- Bulk Remove ---
const bulkRemoving = ref(false)
const showRemoveAllConfirm = ref(false)

async function bulkRemoveSelected() {
  if (selectedIds.value.length === 0) return
  bulkRemoving.value = true
  try {
    await watchlistApi.bulkRemove(selectedIds.value)
    selectedIds.value = []
    await Promise.all([loadWatchlist(), loadWatchlistProductIds()])
  } catch (e) { console.error('Bulk remove failed', e) }
  finally { bulkRemoving.value = false }
}

async function removeAllItems() {
  bulkRemoving.value = true
  showRemoveAllConfirm.value = false
  try {
    await watchlistApi.removeAll()
    selectedIds.value = []
    await Promise.all([loadWatchlist(), loadWatchlistProductIds()])
  } catch (e) { console.error('Remove all failed', e) }
  finally { bulkRemoving.value = false }
}

// Vollständige Produkt-ID-Liste der Merkliste (für Export/Report/PDF-Vorlage —
// diese Aktionen beziehen sich immer auf die ganze Merkliste, nicht auf die
// gerade angezeigte, paginierte Seite).
const watchlistProductIds = ref([])

async function loadWatchlistProductIds() {
  try {
    const { data } = await watchlistApi.productIds()
    watchlistProductIds.value = data.data || data || []
  } catch (e) { /* ignore */ }
}

// ─── Bulk Assign to Project ──────────────────────────
const showAssignProject = ref(false)
const showAssignHierarchy = ref(false)
const showAddToCollection = ref(false)
const selectedProductIdsForBulk = computed(() =>
  selectedIds.value.map(id => items.value.find(i => i.id === id)?.product_id).filter(Boolean)
)

// --- API Call Display ---
const showApiCall = ref(false)
const apiBaseUrl = computed(() => import.meta.env.VITE_API_BASE_URL || '/api/v1')

const apiCallDisplay = computed(() => {
  const url = `${apiBaseUrl.value}/watchlist`
  const curl = `curl -X GET "${window.location.origin}${url}" \\\n  -H "Accept: application/json" \\\n  -H "Authorization: Bearer <TOKEN>"`
  return { method: 'GET', url, curl }
})

onMounted(async () => {
  attrStore.fetchProductTypes()
  try {
    const { data } = await searchApi.searchableAttributes()
    searchableAttributes.value = data.data || data
  } catch (e) { /* ignore */ }
  loadWatchlist()
  loadWatchlistProductIds()
  loadColumnProfiles()
})
</script>

<template>
  <div class="space-y-4" data-testid="watchlist-view">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
          <Star class="w-4 h-4 text-amber-500 fill-amber-500" :stroke-width="2" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Merkliste</h2>
          <p class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Produkt{{ meta.total !== 1 ? 'e' : '' }} gespeichert</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <ColumnConfigPopover
          v-if="meta.total > 0"
          :allColumns="allColumns"
          :visibleKeys="visibleKeys"
          @toggle="toggleColumn"
          @move="moveColumn"
          @reset="resetColumns"
          @reorder="visibleKeys = $event"
        />
        <ProfileSelector
          v-if="meta.total > 0"
          :profiles="columnProfiles"
          v-model="selectedColumnProfileId"
          label="Spaltenprofil"
          @load="loadColumnProfile"
          @save="saveColumnProfile"
          @update="updateColumnProfile"
          @delete="deleteColumnProfile"
        />
        <button
          v-if="meta.total > 0"
          class="pim-btn pim-btn-secondary text-xs"
          :class="showQuickLookup ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : ''"
          @click="toggleQuickLookup(pimTableRef)"
          title="Quick Lookup"
        >
          <ListFilter class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Quick Lookup</span>
        </button>
        <button
          v-if="meta.total > 0"
          class="pim-btn pim-btn-secondary text-xs"
          @click="showExportPanel = !showExportPanel"
        >
          <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
          Export
        </button>
        <button
          v-if="meta.total > 0"
          class="pim-btn pim-btn-danger text-xs"
          @click="showRemoveAllConfirm = true"
        >
          <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
          Alle entfernen
        </button>
      </div>
    </div>

    <!-- Export Panel -->
    <div v-if="showExportPanel && meta.total > 0" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Download class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          Merkliste exportieren
        </h3>
        <button class="pim-btn pim-btn-ghost text-xs p-1" @click="showExportPanel = false">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <!-- Excel -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="exportExcel"
        >
          <FileSpreadsheet class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ exporting === 'excel' ? 'Export…' : 'Excel' }}
        </button>

        <!-- PDF: All in one -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="exportPdf"
        >
          <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ exporting === 'pdf' ? 'Export…' : 'PDF (Gesamt)' }}
        </button>

        <!-- PDF: Per SKU as ZIP -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="exportPdfZip"
        >
          <Archive class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ exporting === 'pdf-zip' ? 'Export…' : 'PDF pro SKU (ZIP)' }}
        </button>

        <div class="border-l border-[var(--color-border)] h-8 hidden sm:block" />

        <!-- Report -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="showReportPicker = true"
        >
          <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
          Report
        </button>

        <!-- PDF Template -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="showPdfTemplatePicker = true"
        >
          <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
          PDF-Vorlage
        </button>

        <div class="border-l border-[var(--color-border)] h-8 hidden sm:block" />

        <!-- XLIFF -->
        <div class="flex flex-wrap items-end gap-2">
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Quellsprache</label>
            <select class="pim-input text-xs w-24" v-model="xliffSourceLang">
              <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
            </select>
          </div>
          <div class="text-[var(--color-text-tertiary)] text-lg pb-1">→</div>
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Zielsprache</label>
            <select class="pim-input text-xs w-24" v-model="xliffTargetLang">
              <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
            </select>
          </div>
          <button
            class="pim-btn pim-btn-secondary text-xs"
            :disabled="exporting !== null || xliffSourceLang === xliffTargetLang"
            @click="exportXliff"
          >
            <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ exporting === 'xliff' ? 'Export…' : 'XLIFF' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Selection toolbar -->
    <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs text-[var(--color-text-secondary)]">{{ selectedIds.length }} ausgewählt</span>
      <button
        v-if="canCompare"
        class="pim-btn pim-btn-primary text-xs"
        @click="openCompare"
      >
        <GitCompareArrows class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Vergleichen</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="openBulkEditor">
        <Pencil class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Bulk bearbeiten</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="openBulkUpdate">
        <Settings class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Massendatenpflege</span>
      </button>
      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="showPdfTemplatePickerSelected = true"
      >
        <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">PDF-Vorlage</span>
      </button>
      <button
        v-if="licenseStore.isModuleActive('workflow')"
        class="pim-btn pim-btn-secondary text-xs"
        @click="showAssignProject = true"
      >
        <FolderTree class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Projekt zuordnen</span>
      </button>
      <button
        v-if="licenseStore.isModuleActive('collections')"
        class="pim-btn pim-btn-secondary text-xs"
        data-testid="add-to-collection-trigger"
        @click="showAddToCollection = true"
      >
        <FolderOpen class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">In Collection übertragen</span>
      </button>
      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="showAssignHierarchy = true"
      >
        <FolderTree class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Hierarchie zuordnen</span>
      </button>
      <button
        class="pim-btn pim-btn-danger text-xs"
        :disabled="bulkRemoving"
        @click="bulkRemoveSelected"
      >
        <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">{{ bulkRemoving ? 'Entferne…' : 'Von Merkliste entfernen' }}</span>
      </button>
      <span v-if="selectedIds.length === 1" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Noch 1 Produkt auswählen zum Vergleichen
      </span>
      <span v-else-if="selectedIds.length > 2" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Max. 2 Produkte zum Vergleichen
      </span>
    </div>

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ error }}</p>
    </div>

    <!-- Table -->
    <PimTable
      ref="pimTableRef"
      :columns="visibleColumns"
      :rows="tableRows"
      :loading="loading"
      selectable
      showActions
      emptyText="Keine Produkte auf der Merkliste"
      :quickLookup="showQuickLookup"
      :quickLookupConfig="quickLookupConfig"
      @row-click="openProduct"
      @row-action="handleRowAction"
      @select="handleSelect"
      @quick-lookup-change="onQuickLookupChange"
    >
      <template #cell-product.sku="{ row, value }">
        <div class="flex items-center gap-2">
          <Star class="w-3.5 h-3.5 text-amber-500 fill-amber-500 shrink-0" :stroke-width="2" />
          <span class="font-mono text-xs">{{ value || '—' }}</span>
        </div>
      </template>
      <template #cell-product.status="{ value }">
        <span
          :class="[
            'pim-badge',
            value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' :
            value === 'draft' ? 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' :
            'bg-[var(--color-error-light)] text-[var(--color-error)]'
          ]"
        >
          {{ value === 'active' ? 'Aktiv' : value === 'draft' ? 'Entwurf' : value === 'inactive' ? 'Inaktiv' : 'Auslaufend' }}
        </span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-[var(--color-text-tertiary)] text-xs">
          {{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}
        </span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Produkte</span>
          <div class="flex items-center gap-1">
            <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">Zurück</button>
            <span class="text-xs text-[var(--color-text-secondary)] px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <!-- Empty state -->
    <div v-if="!loading && meta.total === 0 && !error" class="text-center py-16">
      <Star class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Noch keine Produkte auf der Merkliste</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Füge Produkte über die Suche oder die Produktliste hinzu
      </p>
    </div>

    <!-- API Call Display -->
    <div v-if="meta.total > 0" class="mt-4">
      <button
        class="flex items-center gap-1.5 text-xs text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors"
        @click="showApiCall = !showApiCall"
      >
        <Code2 class="w-3.5 h-3.5" :stroke-width="1.75" />
        API Aufruf
        <ChevronUp v-if="showApiCall" class="w-3 h-3" :stroke-width="2" />
        <ChevronDown v-else class="w-3 h-3" :stroke-width="2" />
      </button>
      <div v-if="showApiCall" class="pim-card p-4 mt-2 space-y-3">
        <div>
          <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Endpoint</p>
          <code class="text-xs text-[var(--color-accent)] bg-[var(--color-bg)] px-2 py-1 rounded">
            {{ apiCallDisplay.method }} {{ apiCallDisplay.url }}
          </code>
        </div>
        <div>
          <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">curl</p>
          <pre class="text-[11px] bg-[var(--color-bg)] p-3 rounded overflow-x-auto text-[var(--color-text-primary)]">{{ apiCallDisplay.curl }}</pre>
        </div>
      </div>
    </div>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Von Merkliste entfernen?"
      :message="`'${deleteTarget?.product?.name || deleteTarget?.product?.sku || ''}' wird von der Merkliste entfernt.`"
      confirm-label="Entfernen"
      :danger="true"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <PimConfirmDialog
      :open="showRemoveAllConfirm"
      title="Alle Einträge entfernen?"
      :message="`Alle ${meta.total} Produkte werden von der Merkliste entfernt. Diese Aktion kann nicht rückgängig gemacht werden.`"
      confirm-label="Alle entfernen"
      :danger="true"
      :loading="bulkRemoving"
      @confirm="removeAllItems"
      @cancel="showRemoveAllConfirm = false"
    />

    <!-- Product Comparison Modal -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showCompare" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showCompare = false" />
          <div class="relative z-10 w-full max-w-6xl max-h-[90vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)] shrink-0">
              <div class="flex items-center gap-3">
                <GitCompareArrows class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
                <span class="text-sm font-semibold text-[var(--color-text-primary)]">Produktvergleich</span>
                <span v-if="compareData" class="text-[11px] text-[var(--color-text-tertiary)]">
                  {{ compareData.total_differences }} Unterschiede von {{ compareData.total_attributes }} Feldern
                </span>
              </div>
              <div class="flex items-center gap-2">
                <label class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-secondary)] cursor-pointer">
                  <input type="checkbox" v-model="showDiffsOnly" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                  Nur Unterschiede
                </label>
                <button class="p-1.5 rounded hover:bg-[var(--color-bg)]" @click="showCompare = false">
                  <X class="w-4 h-4" :stroke-width="2" />
                </button>
              </div>
            </div>
            <div class="flex-1 overflow-y-auto">
              <div v-if="compareLoading" class="p-8 space-y-3">
                <div v-for="i in 8" :key="i" class="pim-skeleton h-8 w-full rounded" />
              </div>
              <table v-else-if="compareData" class="w-full text-[13px]">
                <thead class="sticky top-0 z-10">
                  <tr class="bg-[var(--color-bg)] border-b border-[var(--color-border)]">
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] w-[200px]">Attribut</th>
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-accent)]">
                      {{ compareData.product_a?.sku }}
                      <span class="font-normal normal-case text-[var(--color-text-tertiary)]">{{ compareData.product_a?.name }}</span>
                    </th>
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-accent)]">
                      {{ compareData.product_b?.sku }}
                      <span class="font-normal normal-case text-[var(--color-text-tertiary)]">{{ compareData.product_b?.name }}</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, i) in compareRows"
                    :key="i"
                    :class="['border-b border-[var(--color-border)]', row.is_different ? 'bg-amber-50/50' : '']"
                  >
                    <td class="px-4 py-2 text-[12px] font-medium text-[var(--color-text-secondary)]">
                      {{ row.attribute_name }}
                      <span v-if="row.data_type && row.data_type !== 'base'" class="ml-1 text-[10px] text-[var(--color-text-tertiary)]">({{ row.data_type }})</span>
                    </td>
                    <td :class="['px-4 py-2', row.is_different ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)]']">
                      {{ row.value_a !== null && row.value_a !== '' ? row.value_a : '—' }}
                    </td>
                    <td :class="['px-4 py-2', row.is_different ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)]']">
                      {{ row.value_b !== null && row.value_b !== '' ? row.value_b : '—' }}
                    </td>
                  </tr>
                  <tr v-if="compareRows.length === 0">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-[var(--color-text-tertiary)]">
                      {{ showDiffsOnly ? 'Keine Unterschiede gefunden — Produkte sind identisch' : 'Keine Daten zum Vergleichen' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Report Template Picker -->
    <ReportTemplatePickerModal
      v-model:open="showReportPicker"
      :productIds="watchlistProductIds"
    />

    <!-- PDF Template Picker (whole watchlist) -->
    <PdfTemplatePickerModal
      v-model:open="showPdfTemplatePicker"
      :productIds="watchlistProductIds"
    />

    <!-- PDF Template Picker (selected only) -->
    <PdfTemplatePickerModal
      v-model:open="showPdfTemplatePickerSelected"
      :productIds="selectedProductIds"
    />

    <!-- Bulk Assign to Project Dialog -->
    <BulkAssignProjectDialog
      v-model:open="showAssignProject"
      :productIds="selectedProductIds"
    />

    <!-- Bulk Assign to Hierarchy Node Dialog -->
    <BulkAssignHierarchyNodeDialog
      v-model:open="showAssignHierarchy"
      :productIds="selectedProductIdsForBulk"
    />

    <!-- Merkliste -> Collection uebertragen -->
    <BulkAddToCollectionDialog
      v-model:open="showAddToCollection"
      :productIds="selectedProductIdsForBulk"
    />
  </div>
</template>
