<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  Search, Filter, ChevronDown, ChevronUp, ChevronRight, X, Star,
  Regex, AudioLines, Languages, Download, GitCompareArrows, Pencil,
  Package, Sliders, GitBranch, Image, FolderTree, FileSpreadsheet, FileText, Code2, ListFilter,
} from 'lucide-vue-next'
import searchApi from '@/api/search'
import searchProfilesApi from '@/api/searchProfiles'
import watchlistApi from '@/api/watchlist'
import productsApi from '@/api/products'
import hierarchiesApi from '@/api/hierarchies'
import mediaApi from '@/api/media'
import attributesApiDefault from '@/api/attributes'
import PimTable from '@/components/shared/PimTable.vue'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'
import ColumnConfigPopover from '@/components/shared/ColumnConfigPopover.vue'
import { useColumnConfig } from '@/composables/useColumnConfig'
import { triggerDownload } from '@/utils/download'
import { useAttributeStore } from '@/stores/attributes'
import ReportTemplatePickerModal from '@/components/reports/ReportTemplatePickerModal.vue'

const router = useRouter()
const localeStore = useLocaleStore()
const attrStore = useAttributeStore()

// --- Search Profiles ---
const searchProfiles = ref([])
const selectedProfileId = ref(null)

async function loadProfiles() {
  try {
    const { data } = await searchProfilesApi.list()
    searchProfiles.value = data.data || data
  } catch (e) { /* ignore */ }
}

async function loadProfile(id) {
  const profile = searchProfiles.value.find(p => p.id === id)
  if (!profile) return
  searchInput.value = profile.search_text || ''
  searchMode.value = profile.search_mode || 'like'
  statusFilter.value = profile.status_filter || ''
  selectedCategories.value = profile.category_ids || []
  attributeFilters.value = profile.attribute_filters || {}
  doSearch(1)
}

async function saveProfile({ name, is_shared }) {
  try {
    await searchProfilesApi.create({
      name,
      is_shared,
      search_text: searchInput.value,
      search_mode: searchMode.value,
      status_filter: statusFilter.value || null,
      category_ids: selectedCategories.value,
      attribute_filters: attributeFilters.value,
    })
    await loadProfiles()
  } catch (e) {
    error.value = 'Profil konnte nicht gespeichert werden'
  }
}

async function updateProfile({ id, name, is_shared }) {
  try {
    await searchProfilesApi.update(id, {
      name,
      is_shared,
      search_text: searchInput.value,
      search_mode: searchMode.value,
      status_filter: statusFilter.value || null,
      category_ids: selectedCategories.value,
      attribute_filters: attributeFilters.value,
    })
    await loadProfiles()
  } catch (e) {
    error.value = 'Profil konnte nicht aktualisiert werden'
  }
}

async function deleteProfile(id) {
  try {
    await searchProfilesApi.remove(id)
    selectedProfileId.value = null
    await loadProfiles()
  } catch (e) {
    error.value = 'Profil konnte nicht gelöscht werden'
  }
}

// --- State ---
const searchInput = ref('')
const searchMode = ref('like') // 'like' | 'soundex' | 'regex'
const hasSearched = ref(false)
const results = ref([])
const resultMeta = ref({ total: 0, current_page: 1, last_page: 1 })
const loading = ref(false)
const error = ref(null)

// Search category (entity type)
const searchCategory = ref('products')
const sortField = ref('updated_at')
const sortOrder = ref('desc')

const searchCategoryDefs = [
  { key: 'products', label: 'Produkte', icon: Package },
  { key: 'attributes', label: 'Attribute', icon: Sliders },
  { key: 'nodes', label: 'Kategorieknoten', icon: FolderTree },
  { key: 'media', label: 'Medien', icon: Image },
]

// Column config for products search tab
const defaultSearchColumns = [
  { key: 'sku', label: 'SKU', mono: true, sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'product_type.name_de', label: 'Typ' },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'updated_at', label: 'Geändert', sortable: true },
]
const extraSearchColumns = [
  { key: 'ean', label: 'EAN', mono: true },
  { key: 'created_at', label: 'Erstellt', sortable: true },
]

// Dynamic attribute columns (populated after searchableAttributes are loaded)
const attributeColumns = computed(() =>
  searchableAttributes.value.map(attr => ({
    key: `attributes.${attr.id}`,
    label: attr.name_de || attr.technical_name,
    group: 'Attribute',
    exportKey: `attr:${attr.id}`,
  }))
)

const { visibleColumns: searchVisibleColumns, allColumns: searchAllColumns, visibleKeys: searchVisibleKeys, toggleColumn: searchToggleColumn, moveColumn: searchMoveColumn, resetColumns: searchResetColumns } = useColumnConfig('columns:search', defaultSearchColumns, extraSearchColumns, attributeColumns)

// Excel export
const excelExporting = ref(false)

async function exportSearchExcel() {
  excelExporting.value = true
  try {
    // Map attribute column keys to export keys (attr:id)
    const exportColumns = searchVisibleKeys.value.map(k => {
      if (k.startsWith('attributes.')) {
        const col = searchAllColumns.value.find(c => c.key === k)
        return col?.exportKey || k
      }
      return k
    })
    const params = {
      columns: exportColumns,
      search: searchInput.value.trim() || undefined,
      search_mode: searchMode.value,
      language: 'de',
    }
    if (selectedCategories.value.length > 0) {
      params.category_ids = selectedCategories.value
      params.include_descendants = true
      const h = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
      if (h?.hierarchy_type) params.hierarchy_type = h.hierarchy_type
    }
    if (statusFilter.value) params.status = statusFilter.value
    const resp = await productsApi.exportExcel(params)
    triggerDownload(resp.data, `suchergebnisse-${new Date().toISOString().slice(0, 10)}.xlsx`)
  } catch (e) {
    error.value = 'Excel-Export fehlgeschlagen'
    console.error('Excel export failed:', e)
  } finally { excelExporting.value = false }
}

const categoryColumns = {
  attributes: [
    { key: 'technical_name', label: 'Techn. Name', mono: true, sortable: true },
    { key: 'name_de', label: 'Name', sortable: true },
    { key: 'data_type', label: 'Datentyp', sortable: true },
    { key: 'attribute_type.name_de', label: 'Gruppe' },
    { key: 'status', label: 'Status' },
  ],
  nodes: [
    { key: 'name_de', label: 'Name', sortable: true },
    { key: 'path', label: 'Pfad' },
    { key: 'depth', label: 'Tiefe', sortable: true },
    { key: 'is_active', label: 'Aktiv' },
  ],
  media: [
    { key: 'file_name', label: 'Datei', mono: true, sortable: true },
    { key: 'title_de', label: 'Titel', sortable: true },
    { key: 'mime_type', label: 'Typ' },
    { key: 'media_type', label: 'Medientyp' },
  ],
}

// Category selection
const hierarchies = ref([])
const hierarchyTrees = ref({})
const selectedHierarchyId = ref(null)
const selectedCategories = ref([])
const showCategoryPicker = ref(false)

// Attribute filters
const searchableAttributes = ref([])
const attributeFilters = ref({})
const showAttributeFilters = ref(false)
const statusFilter = ref('')
const selectedProductTypes = ref([])

// Quick Lookup
const showQuickLookup = ref(false)
const quickLookupFilters = ref({})

// Watchlist quick-add
const watchlistIds = ref(new Set())

// Selection & XLIFF export
const selectedProductIds = ref([])
const showXliffPanel = ref(false)
const showReportPicker = ref(false)
const xliffSourceLang = ref('de')
const xliffTargetLang = ref('en')
const xliffExporting = ref(false)

// Product comparison
const showCompare = ref(false)
const compareData = ref(null)
const compareLoading = ref(false)
const showDiffsOnly = ref(false)

const canCompare = computed(() => selectedProductIds.value.length === 2)
const reportProductIds = computed(() =>
  selectedProductIds.value.length > 0
    ? selectedProductIds.value
    : results.value.map(r => r.id)
)

const compareRows = computed(() => {
  if (!compareData.value?.rows) return []
  if (showDiffsOnly.value) return compareData.value.rows.filter(r => r.is_different)
  return compareData.value.rows
})

const columns = computed(() => {
  if (searchCategory.value === 'products') return searchVisibleColumns.value
  return categoryColumns[searchCategory.value] || categoryColumns.products
})

// --- Quick Lookup ---
const statusOptions = [
  { value: 'active', label: 'Aktiv' },
  { value: 'draft', label: 'Entwurf' },
  { value: 'inactive', label: 'Inaktiv' },
  { value: 'discontinued', label: 'Auslaufend' },
]

const productTypeOptions = computed(() =>
  attrStore.prodTypes.map(pt => ({ value: pt.name_de || pt.technical_name, label: pt.name_de || pt.technical_name }))
)

const quickLookupConfig = computed(() => {
  const config = {
    sku: { type: 'text', placeholder: 'SKU...' },
    name: { type: 'text', placeholder: 'Name...' },
    'product_type.name_de': { type: 'select', options: productTypeOptions.value },
    status: { type: 'select', options: statusOptions },
    ean: { type: 'text', placeholder: 'EAN...' },
  }
  // Add dynamic attribute columns
  for (const attr of searchableAttributes.value) {
    const key = `attributes.${attr.id}`
    if (attr.data_type === 'Selection' || attr.data_type === 'Dictionary') {
      config[key] = {
        type: 'select',
        options: (attr.value_list?.entries || []).map(e => ({
          value: e.display_value_de || e.code,
          label: e.display_value_de || e.code,
        })),
      }
    } else if (attr.data_type !== 'Date') {
      config[key] = { type: 'text', placeholder: '...' }
    }
  }
  return config
})

function getCellValueForFilter(row, colKey) {
  const keys = colKey.split('.')
  let val = row
  for (const k of keys) val = val?.[k]
  return val
}

const filteredResults = computed(() => {
  if (!showQuickLookup.value) return results.value
  const filters = quickLookupFilters.value
  const activeFilters = Object.entries(filters).filter(([, v]) => v !== '' && v != null)
  if (activeFilters.length === 0) return results.value

  return results.value.filter(row => {
    return activeFilters.every(([colKey, filterVal]) => {
      const cellVal = getCellValueForFilter(row, colKey)
      if (cellVal == null || cellVal === '—') return false
      const config = quickLookupConfig.value[colKey]
      if (config?.type === 'select') {
        return String(cellVal) === String(filterVal)
      }
      // Text: prefix match
      return String(cellVal).toLowerCase().startsWith(String(filterVal).toLowerCase())
    })
  })
})

function onQuickLookupChange(filters) {
  quickLookupFilters.value = filters
}

// --- Computed ---
const activeFilterCount = computed(() => {
  let count = selectedCategories.value.length + selectedProductTypes.value.length
  if (statusFilter.value) count++
  for (const val of Object.values(attributeFilters.value)) {
    if (val !== '' && val !== null && val !== undefined) count++
  }
  return count
})

const currentHierarchyTree = computed(() => {
  if (!selectedHierarchyId.value) return []
  return hierarchyTrees.value[selectedHierarchyId.value] || []
})

const flatCategoryNodes = computed(() => {
  const result = []
  function flatten(nodes, prefix = '') {
    for (const node of nodes) {
      const name = node.name_de || node.name_en || node.id
      result.push({
        id: node.id,
        label: prefix + name,
        name: name,
      })
      if (node.children?.length) {
        flatten(node.children, prefix + name + ' > ')
      }
    }
  }
  flatten(currentHierarchyTree.value)
  return result
})

// Clear categories that don't belong to the new hierarchy
watch(selectedHierarchyId, () => {
  const validIds = new Set(flatCategoryNodes.value.map(n => n.id))
  selectedCategories.value = selectedCategories.value.filter(id => validIds.has(id))
})

const searchModeLabel = computed(() => ({
  like: 'LIKE (Standard)',
  soundex: 'SOUNDEX (Ähnlichkeit)',
  regex: 'REGEXP (Muster)',
}[searchMode.value]))

// --- Load data ---
onMounted(async () => {
  // Load hierarchies and all their trees
  try {
    const { data } = await hierarchiesApi.list()
    hierarchies.value = data.data || data
    if (hierarchies.value.length > 0) {
      selectedHierarchyId.value = hierarchies.value[0].id
      const trees = {}
      await Promise.all(hierarchies.value.map(async (h) => {
        const { data: treeData } = await hierarchiesApi.getTree(h.id)
        trees[h.id] = treeData.data || treeData
      }))
      hierarchyTrees.value = trees
    }
  } catch (e) {
    console.error('Failed to load hierarchies', e)
  }

  // Load searchable attributes (with value list entries!)
  try {
    const { data } = await searchApi.searchableAttributes()
    searchableAttributes.value = data.data || data
  } catch (e) {
    console.error('Failed to load searchable attributes', e)
  }

  // Load watchlist IDs for highlighting
  try {
    const { data } = await watchlistApi.productIds()
    watchlistIds.value = new Set(data.data || data)
  } catch (e) { /* ignore */ }

  // Load product types for filter
  attrStore.fetchProductTypes()

  // Load search profiles
  loadProfiles()
})

// --- Actions ---
function toggleProductType(id) {
  const idx = selectedProductTypes.value.indexOf(id)
  if (idx === -1) {
    selectedProductTypes.value.push(id)
  } else {
    selectedProductTypes.value.splice(idx, 1)
  }
}

function isProductTypeSelected(id) {
  return selectedProductTypes.value.includes(id)
}

function toggleCategory(categoryId) {
  const idx = selectedCategories.value.indexOf(categoryId)
  if (idx === -1) {
    selectedCategories.value.push(categoryId)
  } else {
    selectedCategories.value.splice(idx, 1)
  }
}

function isCategorySelected(id) {
  return selectedCategories.value.includes(id)
}

function clearAllFilters() {
  selectedCategories.value = []
  selectedProductTypes.value = []
  attributeFilters.value = {}
  statusFilter.value = ''
  searchInput.value = ''
}

function switchCategory(cat) {
  searchCategory.value = cat
  results.value = []
  resultMeta.value = { total: 0, current_page: 1, last_page: 1 }
  hasSearched.value = false
  sortField.value = 'updated_at'
  sortOrder.value = 'desc'
}

function handleSort(field, order) {
  sortField.value = field
  sortOrder.value = order
  doSearch(1)
}

async function doSearch(page = 1) {
  hasSearched.value = true
  loading.value = true
  error.value = null

  try {
    if (searchCategory.value === 'products') {
      await doProductSearch(page)
    } else {
      await doEntitySearch(page)
    }
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.detail || 'Suchfehler'
    results.value = []
  } finally {
    loading.value = false
  }
}

async function doProductSearch(page) {
  const params = {
    search: searchInput.value.trim() || undefined,
    search_mode: searchMode.value,
    page,
    per_page: 50,
    sort: sortField.value,
    order: sortOrder.value,
  }

  if (selectedCategories.value.length > 0) {
    params.category_ids = selectedCategories.value
    params.include_descendants = true
    const h = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
    if (h?.hierarchy_type) params.hierarchy_type = h.hierarchy_type
  }

  if (selectedProductTypes.value.length > 0) {
    params.product_type_ids = selectedProductTypes.value
  }

  if (statusFilter.value) {
    params.status = statusFilter.value
  }

  // Attribute columns (for visible attribute columns in table)
  const attrColumnIds = searchVisibleKeys.value
    .filter(k => k.startsWith('attributes.'))
    .map(k => k.replace('attributes.', ''))
  if (attrColumnIds.length > 0) params.attribute_columns = attrColumnIds

  // Build attribute filters
  const attrFilters = []
  for (const attr of searchableAttributes.value) {
    const val = attributeFilters.value[attr.id]
    if (val === '' || val === null || val === undefined) continue

    const filter = { attribute_id: attr.id, value: val }

    if (attr.data_type === 'String') {
      filter.operator = 'like'
    } else if (attr.data_type === 'Selection' || attr.data_type === 'Dictionary') {
      filter.operator = 'eq'
    } else if (attr.data_type === 'Flag') {
      filter.operator = 'eq'
      filter.value = val === 'true' || val === true ? 1 : 0
    } else {
      filter.operator = 'eq'
    }

    attrFilters.push(filter)
  }

  if (attrFilters.length > 0) {
    params.attribute_filters = attrFilters
  }

  const { data } = await searchApi.search(params)
  results.value = data.data || []
  resultMeta.value = data.meta || { total: results.value.length, current_page: 1, last_page: 1 }
}

async function doEntitySearch(page) {
  const options = {
    page,
    perPage: 50,
    sort: sortField.value,
    order: sortOrder.value,
  }

  if (searchInput.value.trim()) {
    options.search = searchInput.value.trim()
  }

  let response

  switch (searchCategory.value) {
    case 'attributes':
      // Only show searchable attributes
      options.filters = { is_searchable: 1, is_internal: 0 }
      options.include = 'attributeType'
      response = await attributesApiDefault.list(options)
      break
    case 'nodes':
      response = await hierarchiesApi.searchNodes(options)
      break
    case 'media':
      response = await mediaApi.list(options)
      break
    default:
      return
  }

  results.value = response.data.data || []
  resultMeta.value = response.data.meta || { total: results.value.length, current_page: 1, last_page: 1 }
}

function openResult(row) {
  switch (searchCategory.value) {
    case 'products':
      router.push(`/products/${row.id}`)
      break
    case 'attributes':
      router.push(`/attributes`)
      break
    case 'nodes':
      if (row.hierarchy_id) {
        router.push(`/hierarchies/${row.hierarchy_id}`)
      } else {
        router.push('/hierarchies')
      }
      break
    case 'media':
      router.push(`/media`)
      break
  }
}

function getFilterInputType(dataType) {
  switch (dataType) {
    case 'Number':
    case 'Float': return 'number'
    case 'Date': return 'date'
    case 'Flag': return 'select'
    default: return 'text'
  }
}

function isOnWatchlist(productId) {
  return watchlistIds.value.has(productId)
}

async function toggleWatchlist(productId) {
  try {
    if (isOnWatchlist(productId)) {
      await watchlistApi.removeByProduct(productId)
      watchlistIds.value.delete(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    } else {
      await watchlistApi.add(productId)
      watchlistIds.value.add(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    }
  } catch (e) {
    console.error('Watchlist toggle failed', e)
  }
}

// --- Selection & XLIFF ---
function handleSelect(ids) {
  selectedProductIds.value = ids
}

async function bulkAddToWatchlist() {
  if (selectedProductIds.value.length === 0) return
  try {
    await watchlistApi.bulkAdd(selectedProductIds.value)
    const { data } = await watchlistApi.productIds()
    watchlistIds.value = new Set(data.data || data)
  } catch (e) {
    console.error('Bulk watchlist add failed', e)
  }
}

async function exportXliff() {
  xliffExporting.value = true
  try {
    const resp = await productsApi.exportXliff({
      sourceLang: xliffSourceLang.value,
      targetLang: xliffTargetLang.value,
      productIds: selectedProductIds.value,
    })
    triggerDownload(resp.data, `suchergebnisse-${xliffSourceLang.value}-${xliffTargetLang.value}.xliff`)
  } catch (e) { console.error('XLIFF export failed:', e) }
  finally { xliffExporting.value = false }
}

// --- Product Comparison ---
async function openCompare() {
  if (!canCompare.value) return
  showCompare.value = true
  compareLoading.value = true
  compareData.value = null
  try {
    const { data } = await productsApi.compare(selectedProductIds.value[0], selectedProductIds.value[1])
    compareData.value = data.data || data
  } catch (e) { console.error('Compare failed:', e) }
  finally { compareLoading.value = false }
}

function openBulkEditor() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-edit', query: { ids } })
}

// --- API Call Display ---
const showApiCall = ref(false)
const apiBaseUrl = computed(() => import.meta.env.VITE_API_BASE_URL || '/api/v1')

const apiCallDisplay = computed(() => {
  if (searchCategory.value !== 'products') return null
  const params = {
    search: searchInput.value.trim() || undefined,
    search_mode: searchMode.value,
    page: resultMeta.value.current_page,
    per_page: 50,
    sort: sortField.value,
    order: sortOrder.value,
  }

  if (selectedProductTypes.value.length > 0) {
    params.product_type_ids = selectedProductTypes.value
  }

  if (selectedCategories.value.length > 0) {
    params.category_ids = selectedCategories.value
    params.include_descendants = true
    const h = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
    if (h?.hierarchy_type) params.hierarchy_type = h.hierarchy_type
  }

  if (statusFilter.value) {
    params.status = statusFilter.value
  }

  // Clean undefined values
  const cleanParams = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined))

  const url = `${apiBaseUrl.value}/products/search`
  const body = JSON.stringify(cleanParams, null, 2)
  const curl = `curl -X POST "${window.location.origin}${url}" \\\n  -H "Content-Type: application/json" \\\n  -H "Authorization: Bearer <TOKEN>" \\\n  -d '${JSON.stringify(cleanParams)}'`

  return { method: 'POST', url, body, curl }
})
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <!-- Entity category tabs -->
    <div class="flex items-center gap-1 border-b border-[var(--color-border)] pb-0">
      <button
        v-for="cat in searchCategoryDefs"
        :key="cat.key"
        :class="[
          'flex items-center gap-1.5 px-3 py-2 text-xs font-medium border-b-2 transition-colors -mb-px',
          searchCategory === cat.key
            ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
            : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] hover:border-[var(--color-border-strong)]',
        ]"
        @click="switchCategory(cat.key)"
      >
        <component :is="cat.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
        {{ cat.label }}
      </button>
    </div>

    <!-- Search Profile Selector (products only) -->
    <ProfileSelector
      v-if="searchCategory === 'products'"
      :profiles="searchProfiles"
      v-model="selectedProfileId"
      label="Suchprofil"
      @load="loadProfile"
      @save="saveProfile"
      @update="updateProfile"
      @delete="deleteProfile"
    />

    <!-- Search header -->
    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <Search class="w-5 h-5 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
        <input
          v-model="searchInput"
          type="text"
          :placeholder="searchCategory === 'products'
            ? (searchMode === 'regex' ? 'Regulärer Ausdruck eingeben...' : searchMode === 'soundex' ? 'Ähnlich klingend suchen...' : 'Produkte, Attribute, SKUs durchsuchen...')
            : searchCategory === 'attributes' ? 'Attribute durchsuchen...'
            : searchCategory === 'nodes' ? 'Kategorieknoten durchsuchen (inkl. Unterkategorien)...'
            : 'Medien durchsuchen...'"
          class="pim-input pl-4 pr-4 py-3 text-base w-full"
          @keydown.enter="doSearch(1)"
          autofocus
        />
      </div>
      <button
        v-if="searchCategory === 'products'"
        class="pim-btn pim-btn-secondary py-3 px-4 relative"
        @click="showAttributeFilters = !showAttributeFilters"
      >
        <Filter class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Filter</span>
        <ChevronUp v-if="showAttributeFilters" class="w-3.5 h-3.5 ml-0.5" :stroke-width="2" />
        <ChevronDown v-else class="w-3.5 h-3.5 ml-0.5" :stroke-width="2" />
        <span
          v-if="activeFilterCount > 0"
          class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-[var(--color-accent)] text-white text-[10px] flex items-center justify-center font-bold"
        >
          {{ activeFilterCount }}
        </span>
      </button>
      <ColumnConfigPopover
        v-if="searchCategory === 'products'"
        :allColumns="searchAllColumns"
        :visibleKeys="searchVisibleKeys"
        @toggle="searchToggleColumn"
        @move="searchMoveColumn"
        @reset="searchResetColumns"
      />
      <button
        v-if="searchCategory === 'products'"
        class="pim-btn pim-btn-secondary py-3 px-4"
        :class="showQuickLookup ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : ''"
        @click="showQuickLookup = !showQuickLookup"
        title="Quick Lookup"
      >
        <ListFilter class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Quick Lookup</span>
      </button>
      <button
        v-if="searchCategory === 'products' && hasSearched && results.length > 0"
        class="pim-btn pim-btn-secondary py-3 px-4"
        :disabled="excelExporting"
        @click="exportSearchExcel"
      >
        <FileSpreadsheet class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">{{ excelExporting ? 'Export...' : 'Excel' }}</span>
      </button>
      <button
        v-if="searchCategory === 'products' && hasSearched && results.length > 0"
        class="pim-btn pim-btn-secondary py-3 px-4"
        @click="showReportPicker = true"
      >
        <FileText class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Report</span>
      </button>
      <button class="pim-btn pim-btn-primary py-3 px-6" @click="doSearch(1)">
        Suchen
      </button>
    </div>

    <!-- Search mode toggle (products only) -->
    <div v-if="searchCategory === 'products'" class="flex flex-wrap items-center gap-2 text-xs">
      <span class="text-[var(--color-text-tertiary)]">Suchmodus:</span>
      <button
        v-for="mode in ['like', 'soundex', 'regex']"
        :key="mode"
        :class="[
          'px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors',
          searchMode === mode
            ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
            : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]',
        ]"
        @click="searchMode = mode"
      >
        <template v-if="mode === 'like'">LIKE</template>
        <template v-else-if="mode === 'soundex'">
          <AudioLines class="inline w-3 h-3 -mt-0.5 mr-0.5" :stroke-width="2" />
          SOUNDEX
        </template>
        <template v-else>
          <Regex class="inline w-3 h-3 -mt-0.5 mr-0.5" :stroke-width="2" />
          REGEXP
        </template>
      </button>
      <span class="text-[10px] text-[var(--color-text-tertiary)] ml-2 hidden sm:inline">
        {{ searchMode === 'like' ? 'Teiltext-Suche (enthält)' : searchMode === 'soundex' ? 'Ähnlich klingende Begriffe finden (Tippfehler-tolerant)' : 'Reguläre Ausdrücke für präzise Muster' }}
      </span>
    </div>

    <!-- Filter panel (products only) -->
    <transition name="slide">
      <div v-if="showAttributeFilters && searchCategory === 'products'" class="pim-card p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Suchfilter</h3>
          <div class="flex gap-2">
            <button
              v-if="activeFilterCount > 0"
              class="text-xs text-[var(--color-accent)] hover:underline"
              @click="clearAllFilters"
            >
              Alle zurücksetzen
            </button>
            <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="showAttributeFilters = false">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>
        </div>

        <!-- Status filter -->
        <div>
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Produkt-Status</p>
          <select class="pim-input text-xs w-48" v-model="statusFilter">
            <option value="">— Alle —</option>
            <option value="active">Aktiv</option>
            <option value="draft">Entwurf</option>
            <option value="inactive">Inaktiv</option>
            <option value="discontinued">Auslaufend</option>
          </select>
        </div>

        <!-- Product type filter -->
        <div v-if="attrStore.prodTypes.length > 0">
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">
            Produkttypen
            <span v-if="selectedProductTypes.length > 0" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5 ml-1">
              {{ selectedProductTypes.length }}
            </span>
          </p>
          <div class="max-h-36 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
            <label
              v-for="pt in attrStore.prodTypes"
              :key="pt.id"
              class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
            >
              <input
                type="checkbox"
                :checked="isProductTypeSelected(pt.id)"
                @change="toggleProductType(pt.id)"
                class="rounded border-[var(--color-border)]"
              />
              <span class="text-[var(--color-text-primary)]">{{ pt.name_de || pt.technical_name }}</span>
            </label>
          </div>
        </div>

        <!-- Category filter -->
        <div>
          <button
            class="flex items-center gap-2 text-[12px] font-medium text-[var(--color-text-secondary)] mb-2 cursor-pointer"
            @click="showCategoryPicker = !showCategoryPicker"
          >
            <component :is="showCategoryPicker ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" />
            Kategorien (inkl. Unterkategorien)
            <span v-if="selectedCategories.length > 0" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5">
              {{ selectedCategories.length }}
            </span>
          </button>
          <div v-if="showCategoryPicker" class="space-y-2">
            <!-- Hierarchy selector -->
            <div v-if="hierarchies.length > 1" class="flex items-center gap-2">
              <label class="text-[11px] font-medium text-[var(--color-text-tertiary)]">Hierarchie:</label>
              <select
                class="pim-input text-xs flex-1"
                :value="selectedHierarchyId"
                @change="selectedHierarchyId = $event.target.value"
              >
                <option v-for="h in hierarchies" :key="h.id" :value="h.id">
                  {{ h.name_de || h.name_en || h.technical_name }}
                </option>
              </select>
            </div>
            <!-- Category nodes -->
            <div class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
              <label
                v-for="cat in flatCategoryNodes"
                :key="cat.id"
                class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
              >
                <input
                  type="checkbox"
                  :checked="isCategorySelected(cat.id)"
                  @change="toggleCategory(cat.id)"
                  class="rounded border-[var(--color-border)]"
                />
                <span class="text-[var(--color-text-primary)]">{{ cat.label }}</span>
              </label>
              <p v-if="flatCategoryNodes.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">
                Keine Kategorien vorhanden
              </p>
            </div>
          </div>
        </div>

        <!-- Attribute filters -->
        <div v-if="searchableAttributes.length > 0">
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Attribute</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="attr in searchableAttributes" :key="attr.id">
              <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">
                {{ attr.name_de || attr.technical_name }}
              </label>
              <template v-if="attr.data_type === 'Flag'">
                <select
                  class="pim-input text-xs"
                  :value="attributeFilters[attr.id] ?? ''"
                  @change="attributeFilters[attr.id] = $event.target.value || ''"
                >
                  <option value="">— Alle —</option>
                  <option value="true">Ja</option>
                  <option value="false">Nein</option>
                </select>
              </template>
              <template v-else-if="(attr.data_type === 'Selection' || attr.data_type === 'Dictionary') && attr.value_list?.entries?.length">
                <select
                  class="pim-input text-xs"
                  :value="attributeFilters[attr.id] ?? ''"
                  @change="attributeFilters[attr.id] = $event.target.value || ''"
                >
                  <option value="">— Alle —</option>
                  <option
                    v-for="entry in attr.value_list.entries"
                    :key="entry.id"
                    :value="entry.id"
                  >
                    {{ entry.display_value_de || entry.code }}
                  </option>
                </select>
              </template>
              <template v-else>
                <input
                  class="pim-input text-xs"
                  :type="getFilterInputType(attr.data_type)"
                  :value="attributeFilters[attr.id] ?? ''"
                  :placeholder="attr.data_type === 'Date' ? '' : 'Wert eingeben...'"
                  @input="attributeFilters[attr.id] = $event.target.value"
                />
              </template>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Error display -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ error }}</p>
    </div>

    <!-- Result count -->
    <div v-if="hasSearched && !loading && !error && results.length > 0" class="text-xs text-[var(--color-text-tertiary)]">
      {{ resultMeta.total }} Ergebnis{{ resultMeta.total !== 1 ? 'se' : '' }}
      <span v-if="searchMode === 'soundex'" class="ml-1 text-[var(--color-accent)]">(SOUNDEX)</span>
      <span v-if="searchMode === 'regex'" class="ml-1 text-[var(--color-accent)]">(REGEXP)</span>
    </div>

    <!-- Selection toolbar (products only) -->
    <div v-if="searchCategory === 'products' && selectedProductIds.length > 0" class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs text-[var(--color-text-secondary)]">{{ selectedProductIds.length }} ausgewählt</span>
      <button class="pim-btn pim-btn-secondary text-xs" @click="bulkAddToWatchlist">
        <Star class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Zur Merkliste</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="showXliffPanel = !showXliffPanel">
        <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
        XLIFF
      </button>
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
      <button class="pim-btn pim-btn-secondary text-xs" @click="showReportPicker = true">
        <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Report</span>
      </button>
      <span v-if="selectedProductIds.length === 1" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Noch 1 Produkt auswählen zum Vergleichen
      </span>
    </div>

    <!-- XLIFF Export Panel (products only) -->
    <div v-if="searchCategory === 'products' && showXliffPanel && selectedProductIds.length > 0" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Languages class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          XLIFF Export ({{ selectedProductIds.length }} Produkte)
        </h3>
        <button class="pim-btn pim-btn-ghost text-xs p-1" @click="showXliffPanel = false">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
      <div class="flex flex-wrap items-end gap-3">
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
          class="pim-btn pim-btn-primary text-xs"
          :disabled="xliffExporting || xliffSourceLang === xliffTargetLang"
          @click="exportXliff"
        >
          <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ xliffExporting ? 'Export…' : 'Export' }}
        </button>
      </div>
    </div>

    <PimTable
      v-if="results.length > 0"
      :columns="columns"
      :rows="searchCategory === 'products' ? filteredResults : results"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :selectable="searchCategory === 'products'"
      :showActions="false"
      :quickLookup="showQuickLookup && searchCategory === 'products'"
      :quickLookupConfig="quickLookupConfig"
      @row-click="openResult"
      @select="handleSelect"
      @sort="handleSort"
      @quick-lookup-change="onQuickLookupChange"
    >
      <!-- Product-specific cells -->
      <template v-if="searchCategory === 'products'" #cell-sku="{ row, value }">
        <div class="flex items-center gap-2">
          <button
            class="p-0.5 rounded hover:bg-[var(--color-bg)] shrink-0"
            :title="isOnWatchlist(row.id) ? 'Von Merkliste entfernen' : 'Zur Merkliste hinzufügen'"
            @click.stop="toggleWatchlist(row.id)"
          >
            <Star
              class="w-3.5 h-3.5"
              :class="isOnWatchlist(row.id) ? 'text-amber-500 fill-amber-500' : 'text-[var(--color-text-tertiary)]'"
              :stroke-width="2"
            />
          </button>
          <span class="font-mono text-xs">{{ value }}</span>
        </div>
      </template>
      <template v-if="searchCategory === 'products'" #cell-product_type.name_de="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || 'Produkt' }}
        </span>
      </template>
      <template #cell-status="{ value }">
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

      <!-- Attribute-specific cells -->
      <template v-if="searchCategory === 'attributes'" #cell-data_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || '—' }}
        </span>
      </template>
      <template v-if="searchCategory === 'attributes'" #cell-attribute_type.name_de="{ value }">
        <span class="text-xs text-[var(--color-text-secondary)]">{{ value || '—' }}</span>
      </template>

      <!-- Node-specific cells -->
      <template v-if="searchCategory === 'nodes'" #cell-path="{ value }">
        <span class="text-[11px] font-mono text-[var(--color-text-tertiary)] truncate max-w-[200px] block" :title="value">
          {{ value || '/' }}
        </span>
      </template>
      <template v-if="searchCategory === 'nodes'" #cell-is_active="{ value }">
        <span
          :class="[
            'pim-badge text-[10px]',
            value ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]',
          ]"
        >
          {{ value ? 'Aktiv' : 'Inaktiv' }}
        </span>
      </template>
      <template v-if="searchCategory === 'nodes'" #cell-name_de="{ row, value }">
        <div>
          <span class="text-sm">{{ value }}</span>
          <span v-if="row.hierarchy?.name_de" class="ml-1.5 text-[10px] text-[var(--color-text-tertiary)]">
            ({{ row.hierarchy.name_de }})
          </span>
        </div>
      </template>

      <!-- Media-specific cells -->
      <template v-if="searchCategory === 'media'" #cell-mime_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || '—' }}
        </span>
      </template>
      <template v-if="searchCategory === 'media'" #cell-media_type="{ value }">
        <span class="text-xs text-[var(--color-text-secondary)]">{{ value || '—' }}</span>
      </template>

      <!-- Pagination -->
      <template #pagination v-if="resultMeta.last_page > 1">
        <div class="flex flex-col sm:flex-row items-center justify-between px-4 py-3 border-t border-[var(--color-border)] gap-2">
          <span class="text-xs text-[var(--color-text-tertiary)]">
            Seite {{ resultMeta.current_page }} / {{ resultMeta.last_page }}
          </span>
          <div class="flex items-center gap-1">
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="resultMeta.current_page <= 1"
              @click="doSearch(resultMeta.current_page - 1)"
            >Zurück</button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="resultMeta.current_page >= resultMeta.last_page"
              @click="doSearch(resultMeta.current_page + 1)"
            >Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <div v-if="loading" class="pim-card p-6">
      <div class="space-y-3">
        <div v-for="i in 5" :key="i" class="pim-skeleton h-8 rounded" />
      </div>
    </div>

    <div v-else-if="hasSearched && results.length === 0 && !error" class="text-center py-12">
      <Search class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Ergebnisse gefunden</p>
      <p v-if="searchCategory === 'products' && searchMode === 'like'" class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Tipp: Probiere den SOUNDEX-Modus für ähnlich klingende Begriffe
      </p>
      <p v-if="searchCategory === 'nodes'" class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Tipp: Die Suche findet auch alle Unterkategorien einer Hauptkategorie
      </p>
    </div>

    <div v-else-if="!hasSearched" class="text-center py-16">
      <component :is="searchCategoryDefs.find(c => c.key === searchCategory)?.icon || Search" class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">
        {{ searchCategory === 'products' ? 'Filter konfigurieren und Suche starten' :
           searchCategory === 'attributes' ? 'Durchsuchbare Attribute finden' :
           searchCategory === 'nodes' ? 'Kategorieknoten durchsuchen (inkl. Unterkategorien)' :
           'Medien durchsuchen' }}
      </p>
      <p v-if="searchCategory === 'products'" class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Suche mit LIKE, SOUNDEX oder REGEXP
      </p>
    </div>

    <!-- API Call Display -->
    <div v-if="hasSearched && searchCategory === 'products' && results.length > 0 && apiCallDisplay" class="mt-4">
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
          <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Request Body (JSON)</p>
          <pre class="text-[11px] bg-[var(--color-bg)] p-3 rounded overflow-x-auto text-[var(--color-text-primary)]">{{ apiCallDisplay.body }}</pre>
        </div>
        <div>
          <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">curl</p>
          <pre class="text-[11px] bg-[var(--color-bg)] p-3 rounded overflow-x-auto text-[var(--color-text-primary)]">{{ apiCallDisplay.curl }}</pre>
        </div>
      </div>
    </div>

    <!-- Product Comparison Modal (products only) -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="searchCategory === 'products' && showCompare" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showCompare = false" />
          <div class="relative w-full max-w-4xl max-h-[85vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
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
      :productIds="reportProductIds"
    />
  </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
