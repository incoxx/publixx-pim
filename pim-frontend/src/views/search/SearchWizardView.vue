<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  Search, Filter, ChevronDown, ChevronUp, ChevronRight, X, Star,
  Regex, AudioLines, Languages, Download, GitCompareArrows, Pencil, Settings,
  Package, Sliders, GitBranch, Image, FolderTree, FileSpreadsheet, FileText, Code2, ListFilter,
  Trash2, CheckCheck, FileOutput,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import searchApi from '@/api/search'
import searchProfilesApi from '@/api/searchProfiles'
import columnProfilesApi from '@/api/columnProfiles'
import { translationLanguages } from '@/config/languages'
import watchlistApi from '@/api/watchlist'
import productsApi from '@/api/products'
import hierarchiesApi from '@/api/hierarchies'
import mediaApi from '@/api/media'
import attributesApiDefault from '@/api/attributes'
import manufacturersApi from '@/api/manufacturers'
import PimTable from '@/components/shared/PimTable.vue'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'
import ColumnConfigPopover from '@/components/shared/ColumnConfigPopover.vue'
import { useColumnConfig } from '@/composables/useColumnConfig'
import { triggerDownload } from '@/utils/download'
import { useAttributeStore } from '@/stores/attributes'
import ReportTemplatePickerModal from '@/components/reports/ReportTemplatePickerModal.vue'
import PdfTemplatePickerModal from '@/components/pdf-templates/PdfTemplatePickerModal.vue'
import { useLicenseStore } from '@/stores/license'
import BulkAssignProjectDialog from '@/components/dialogs/BulkAssignProjectDialog.vue'
import BulkAssignHierarchyNodeDialog from '@/components/dialogs/BulkAssignHierarchyNodeDialog.vue'
import QueryBuilderGroup from '@/components/search/QueryBuilderGroup.vue'

const router = useRouter()
const localeStore = useLocaleStore()
const attrStore = useAttributeStore()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()

// --- Search Profiles ---
const searchProfiles = ref([])
const selectedProfileId = ref(null)
const columnProfiles = ref([])
const selectedColumnProfileId = ref(null)

async function loadColumnProfiles() {
  try {
    const { data } = await columnProfilesApi.list('search')
    columnProfiles.value = data.data || data
  } catch { /* ignore */ }
}

async function loadColumnProfile(id) {
  const profile = columnProfiles.value.find(p => p.id === id)
  if (profile?.visible_keys) {
    searchVisibleKeys.value = [...profile.visible_keys]
  }
}

async function saveColumnProfile({ name, is_shared }) {
  await columnProfilesApi.create({
    name,
    is_shared,
    context: 'search',
    visible_keys: searchVisibleKeys.value,
  })
  await loadColumnProfiles()
}

async function updateColumnProfile({ id, name, is_shared }) {
  await columnProfilesApi.update(id, {
    name,
    is_shared,
    visible_keys: searchVisibleKeys.value,
  })
  await loadColumnProfiles()
}

async function deleteColumnProfile(id) {
  await columnProfilesApi.remove(id)
  selectedColumnProfileId.value = null
  await loadColumnProfiles()
}

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
  attributeFilterGroups.value = profile.attribute_filter_groups || { operator: 'AND', rules: [] }
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
      attribute_filter_groups: attributeFilterGroups.value.rules?.length ? attributeFilterGroups.value : null,
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
    if (e.response?.status === 409) {
      const deps = e.response.data?.dependencies || {}
      const labels = Object.values(deps).map(d => `${d.label} (${d.count})`).join(', ')
      if (confirm(`Dieses Suchprofil wird noch verwendet: ${labels}. Trotzdem löschen?`)) {
        try {
          await searchProfilesApi.remove(id, { force: true })
          selectedProfileId.value = null
          await loadProfiles()
          return
        } catch (e2) { /* fall through */ }
      } else {
        return
      }
    }
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
  { key: 'manufacturer.name', label: 'Hersteller' },
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
const attributeFilterGroups = ref({ operator: 'AND', rules: [] })
const autoShowFilterColumns = ref(localStorage.getItem('search:autoShowFilterColumns') !== 'false')
const showAttributeFilters = ref(false)
const liveCount = ref(null)
let liveCountTimer = null
const statusFilter = ref('')
const missingTranslationFilter = ref({ attribute_id: null, target_language: null })
const selectedProductTypes = ref([])
const selectedManufacturers = ref([])
const manufacturerList = ref([])

// Live count: debounced update when query builder changes
watch(autoShowFilterColumns, (val) => {
  localStorage.setItem('search:autoShowFilterColumns', val ? 'true' : 'false')
})

watch(attributeFilterGroups, () => {
  if (liveCountTimer) clearTimeout(liveCountTimer)
  if (!attributeFilterGroups.value.rules?.length) {
    liveCount.value = null
    return
  }
  liveCountTimer = setTimeout(async () => {
    try {
      const params = { language: 'de' }
      if (statusFilter.value) params.status = statusFilter.value
      if (attributeFilterGroups.value.rules.length > 0) {
        params.attribute_filter_groups = attributeFilterGroups.value
      }
      const { data } = await searchApi.count(params)
      liveCount.value = data.count
    } catch {
      liveCount.value = null
    }
  }, 600)
}, { deep: true })

// Quick Lookup
const showQuickLookup = ref(false)
const quickLookupFilters = ref({})

// Watchlist quick-add
const watchlistIds = ref(new Set())

// Selection & XLIFF export
const selectedProductIds = ref([])
const allPagesSelected = ref(false)
const selectingAll = ref(false)
const bulkDeleting = ref(false)
const showConfirmBulkDelete = ref(false)
const searchTableRef = ref(null)
const showXliffPanel = ref(false)
const showReportPicker = ref(false)
const showPdfPicker = ref(false)
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

const manufacturerQuickOptions = computed(() =>
  manufacturerList.value.map(m => ({ value: m.name, label: m.name }))
)

const quickLookupConfig = computed(() => {
  const config = {
    sku: { type: 'text', placeholder: 'SKU...' },
    name: { type: 'text', placeholder: 'Name...' },
    'product_type.name_de': { type: 'select', options: productTypeOptions.value },
    'manufacturer.name': { type: 'select', options: manufacturerQuickOptions.value },
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

const translatableSearchAttributes = computed(() => {
  return searchableAttributes.value.filter(a => a.is_translatable && (a.data_type === 'String' || a.data_type === 'RichText'))
})

function countRules(group) {
  let n = 0
  for (const r of group.rules || []) {
    if (r.type === 'group') n += countRules(r)
    else if (r.attribute_id) n++
  }
  return n
}

function collectFilterAttributeIds(group) {
  const ids = new Set()
  for (const r of group.rules || []) {
    if (r.type === 'group') {
      for (const id of collectFilterAttributeIds(r)) ids.add(id)
    } else if (r.attribute_id) {
      ids.add(r.attribute_id)
    }
  }
  return ids
}

// --- Computed ---
const activeFilterCount = computed(() => {
  let count = selectedCategories.value.length + selectedProductTypes.value.length + selectedManufacturers.value.length
  if (statusFilter.value) count++
  if (missingTranslationFilter.value.attribute_id && missingTranslationFilter.value.target_language) count++
  // Count query builder rules
  if (attributeFilterGroups.value.rules?.length) {
    count += countRules(attributeFilterGroups.value)
  }
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

  // Load manufacturers for filter
  try {
    const { data } = await manufacturersApi.list({ perPage: 500 })
    manufacturerList.value = data.data || data
  } catch (e) { /* ignore */ }

  // Load search profiles & column profiles
  loadProfiles()
  loadColumnProfiles()
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

function toggleManufacturer(id) {
  const idx = selectedManufacturers.value.indexOf(id)
  if (idx === -1) {
    selectedManufacturers.value.push(id)
  } else {
    selectedManufacturers.value.splice(idx, 1)
  }
}

function isManufacturerSelected(id) {
  return selectedManufacturers.value.includes(id)
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
  selectedManufacturers.value = []
  attributeFilters.value = {}
  attributeFilterGroups.value = { operator: 'AND', rules: [] }
  liveCount.value = null
  statusFilter.value = ''
  missingTranslationFilter.value = { attribute_id: null, target_language: null }
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
  // Auto-close filter panel so results are immediately visible
  showAttributeFilters.value = false

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

  if (selectedManufacturers.value.length > 0) {
    params.manufacturer_ids = selectedManufacturers.value
  }

  if (statusFilter.value) {
    params.status = statusFilter.value
  }

  // Auto-show columns for filtered attributes (if enabled)
  if (autoShowFilterColumns.value) {
    const filterAttrIds = collectFilterAttributeIds(attributeFilterGroups.value)
    for (const id of filterAttrIds) {
      const colKey = `attributes.${id}`
      if (!searchVisibleKeys.value.includes(colKey)) {
        searchVisibleKeys.value.push(colKey)
      }
    }
  }

  // Attribute columns (for visible attribute columns in table)
  const attrColumnIds = searchVisibleKeys.value
    .filter(k => k.startsWith('attributes.'))
    .map(k => k.replace('attributes.', ''))
  if (attrColumnIds.length > 0) params.attribute_columns = attrColumnIds

  // Build attribute filter groups (new query builder)
  if (attributeFilterGroups.value.rules && attributeFilterGroups.value.rules.length > 0) {
    params.attribute_filter_groups = attributeFilterGroups.value
  }

  // Legacy flat attribute filters (for backward compatibility with saved profiles)
  const attrFilters = []
  for (const attr of searchableAttributes.value) {
    const val = attributeFilters.value[attr.id]
    if (val === '' || val === null || val === undefined) continue

    // Auto-show column for this filtered attribute (if enabled)
    if (autoShowFilterColumns.value) {
      const legacyColKey = `attributes.${attr.id}`
      if (!searchVisibleKeys.value.includes(legacyColKey)) {
        searchVisibleKeys.value.push(legacyColKey)
      }
    }

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

  // Missing translation filter
  if (missingTranslationFilter.value.attribute_id && missingTranslationFilter.value.target_language) {
    params.missing_translation = {
      attribute_id: missingTranslationFilter.value.attribute_id,
      target_language: missingTranslationFilter.value.target_language,
    }
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
      options.filters = { is_internal: 0, status: 'active' }
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
  if (allPagesSelected.value && ids.length < resultMeta.value.total) {
    allPagesSelected.value = false
  }
}

function buildSearchParams() {
  const params = {
    search: searchInput.value.trim() || undefined,
    search_mode: searchMode.value,
  }
  if (selectedCategories.value.length > 0) {
    params.category_ids = selectedCategories.value
    params.include_descendants = true
    const h = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
    if (h?.hierarchy_type) params.hierarchy_type = h.hierarchy_type
  }
  if (selectedProductTypes.value.length > 0) params.product_type_ids = selectedProductTypes.value
  if (selectedManufacturers.value.length > 0) params.manufacturer_ids = selectedManufacturers.value
  if (statusFilter.value) params.status = statusFilter.value
  return params
}

async function selectAllPages() {
  selectingAll.value = true
  try {
    const { data } = await searchApi.allIds(buildSearchParams())
    const allIds = data.data || data
    selectedProductIds.value = allIds
    allPagesSelected.value = true
    if (searchTableRef.value) {
      searchTableRef.value.setSelectedIds(allIds)
    }
  } catch (e) {
    console.error('Failed to select all', e)
  } finally {
    selectingAll.value = false
  }
}

function clearAllSelection() {
  selectedProductIds.value = []
  allPagesSelected.value = false
  if (searchTableRef.value) {
    searchTableRef.value.clearSelection()
  }
}

async function bulkDeleteProducts() {
  showConfirmBulkDelete.value = false
  bulkDeleting.value = true
  try {
    await searchApi.bulkDelete(selectedProductIds.value)
    selectedProductIds.value = []
    allPagesSelected.value = false
    if (searchTableRef.value) {
      searchTableRef.value.clearSelection()
    }
    await doSearch(1)
  } catch (e) {
    console.error('Bulk delete failed', e)
  } finally {
    bulkDeleting.value = false
  }
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

function openTranslationJobCreate() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/translation-jobs/create', query: { product_ids: ids } })
}

function openBulkEditor() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-edit', query: { ids } })
}

function openBulkUpdate() {
  // For large selections or "all pages selected", pass filter instead of IDs
  if (allPagesSelected.value || selectedProductIds.value.length > 100) {
    const filter = buildSearchParams()
    router.push({
      path: '/products/bulk-update',
      query: {
        filter: JSON.stringify(filter),
        count: String(resultMeta.value.total || selectedProductIds.value.length),
      },
    })
  } else {
    const ids = selectedProductIds.value.join(',')
    router.push({ path: '/products/bulk-update', query: { ids } })
  }
}

// ─── Bulk Assign to Project ──────────────────────────
const showAssignProject = ref(false)
const showAssignHierarchy = ref(false)

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

  if (selectedManufacturers.value.length > 0) {
    params.manufacturer_ids = selectedManufacturers.value
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
  <div class="space-y-4">
    <!-- Entity category tabs -->
    <div class="flex items-center gap-1 border-b border-[var(--color-border)] pb-0 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
      <button
        v-for="cat in searchCategoryDefs"
        :key="cat.key"
        :class="[
          'flex items-center gap-1.5 px-3 py-2 text-xs font-medium border-b-2 transition-colors -mb-px whitespace-nowrap shrink-0',
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
    <div class="space-y-2 sm:space-y-0 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <Search class="w-5 h-5 text-[var(--color-text-tertiary)] shrink-0 hidden sm:block" :stroke-width="1.75" />
        <input
          v-model="searchInput"
          type="text"
          :placeholder="searchCategory === 'products'
            ? (searchMode === 'regex' ? 'Regulärer Ausdruck eingeben...' : searchMode === 'soundex' ? 'Ähnlich klingend suchen...' : 'Produkte, Attribute, SKUs durchsuchen...')
            : searchCategory === 'attributes' ? 'Attribute durchsuchen...'
            : searchCategory === 'nodes' ? 'Kategorieknoten durchsuchen (inkl. Unterkategorien)...'
            : 'Medien durchsuchen...'"
          class="pim-input pl-4 pr-4 py-2.5 sm:py-3 text-sm sm:text-base w-full"
          @keydown.enter="doSearch(1)"
          autofocus
        />
      </div>
      <div class="flex items-center gap-2 flex-wrap">
      <button
        v-if="searchCategory === 'products'"
        class="pim-btn pim-btn-secondary py-2 px-3 sm:py-3 sm:px-4 relative"
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
      <label
        v-if="searchCategory === 'products'"
        class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-secondary)] cursor-pointer select-none"
        title="Gefilterte Attribute automatisch als Spalten einblenden"
      >
        <input type="checkbox" v-model="autoShowFilterColumns" class="rounded border-[var(--color-border)]" />
        Auto-Spalten
      </label>
      <ProfileSelector
        v-if="searchCategory === 'products'"
        :profiles="columnProfiles"
        v-model="selectedColumnProfileId"
        label="Spaltenprofil"
        @load="loadColumnProfile"
        @save="saveColumnProfile"
        @update="updateColumnProfile"
        @delete="deleteColumnProfile"
      />
      <button
        v-if="searchCategory === 'products'"
        class="pim-btn pim-btn-secondary py-2 px-3 sm:py-3 sm:px-4"
        :class="showQuickLookup ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : ''"
        @click="showQuickLookup = !showQuickLookup"
        title="Quick Lookup"
      >
        <ListFilter class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Quick Lookup</span>
      </button>
      <button
        v-if="searchCategory === 'products' && hasSearched && results.length > 0"
        class="pim-btn pim-btn-secondary py-2 px-3 sm:py-3 sm:px-4"
        :disabled="excelExporting"
        @click="exportSearchExcel"
      >
        <FileSpreadsheet class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">{{ excelExporting ? 'Export...' : 'Excel' }}</span>
      </button>
      <button
        v-if="searchCategory === 'products' && hasSearched && results.length > 0"
        class="pim-btn pim-btn-secondary py-2 px-3 sm:py-3 sm:px-4"
        @click="showReportPicker = true"
      >
        <FileText class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Report</span>
      </button>
      <button
        v-if="searchCategory === 'products' && hasSearched && results.length > 0"
        class="pim-btn pim-btn-secondary py-2 px-3 sm:py-3 sm:px-4"
        @click="showPdfPicker = true"
      >
        <FileOutput class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">PDF</span>
      </button>
      <button class="pim-btn pim-btn-primary py-2 px-4 sm:py-3 sm:px-6" @click="doSearch(1)">
        Suchen
      </button>
      </div>
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

        <!-- Manufacturer filter -->
        <div>
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">
            Hersteller
            <span v-if="selectedManufacturers.length > 0" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5 ml-1">
              {{ selectedManufacturers.length }}
            </span>
          </p>
          <div v-if="manufacturerList.length === 0" class="border border-[var(--color-border)] rounded-lg p-3 text-center">
            <p class="text-xs text-[var(--color-text-tertiary)]">Keine Hersteller angelegt</p>
          </div>
          <div v-else class="max-h-36 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
            <label
              v-for="m in manufacturerList"
              :key="m.id"
              class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
            >
              <input
                type="checkbox"
                :checked="isManufacturerSelected(m.id)"
                @change="toggleManufacturer(m.id)"
                class="rounded border-[var(--color-border)]"
              />
              <span class="text-[var(--color-text-primary)]">{{ m.name }}</span>
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

        <!-- Missing Translation filter -->
        <div v-if="translatableSearchAttributes.length > 0">
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Fehlende Übersetzung</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">Attribut</label>
              <select
                class="pim-input text-xs"
                :value="missingTranslationFilter.attribute_id || ''"
                @change="missingTranslationFilter.attribute_id = $event.target.value || null"
              >
                <option value="">— Keins —</option>
                <option v-for="attr in translatableSearchAttributes" :key="attr.id" :value="attr.id">
                  {{ attr.name_de || attr.technical_name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">Zielsprache</label>
              <select
                class="pim-input text-xs"
                :value="missingTranslationFilter.target_language || ''"
                @change="missingTranslationFilter.target_language = $event.target.value || null"
              >
                <option value="">— Wählen —</option>
                <option v-for="lang in translationLanguages" :key="lang.code" :value="lang.code">{{ lang.label }} ({{ lang.code }})</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Attribute filters -->
        <!-- Attribute Query Builder -->
        <div v-if="searchableAttributes.length > 0">
          <div class="flex items-center justify-between mb-2">
            <p class="text-[12px] font-medium text-[var(--color-text-secondary)]">Attributfilter</p>
            <span v-if="liveCount !== null" class="text-[11px] text-[var(--color-text-tertiary)]">
              {{ liveCount.toLocaleString() }} Treffer
            </span>
          </div>
          <QueryBuilderGroup
            :group="attributeFilterGroups"
            :attributes="searchableAttributes"
            @update="attributeFilterGroups = $event"
          />
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
    <div v-if="searchCategory === 'products' && selectedProductIds.length > 0" class="space-y-2">
      <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
        <span class="text-xs text-[var(--color-text-secondary)]">
          {{ selectedProductIds.length }} {{ allPagesSelected ? 'Produkte' : '' }} ausgewählt
        </span>

        <!-- Select all pages hint -->
        <template v-if="!allPagesSelected && selectedProductIds.length === filteredResults.length && resultMeta.total > filteredResults.length">
          <button
            class="pim-btn pim-btn-secondary text-xs border-dashed"
            :disabled="selectingAll"
            @click="selectAllPages"
          >
            <CheckCheck class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ selectingAll ? 'Lade...' : `Alle ${resultMeta.total.toLocaleString()} Produkte auswählen` }}
          </button>
        </template>

        <button class="pim-btn pim-btn-secondary text-xs" @click="bulkAddToWatchlist">
          <Star class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Zur Merkliste</span>
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showXliffPanel = !showXliffPanel">
          <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
          XLIFF
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="openTranslationJobCreate">
          <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Übersetzungsjob</span>
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
        <button class="pim-btn pim-btn-secondary text-xs" @click="openBulkUpdate">
          <Settings class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Massendatenpflege</span>
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showReportPicker = true">
          <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Report</span>
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showPdfPicker = true">
          <FileOutput class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">PDF</span>
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
          class="pim-btn pim-btn-secondary text-xs"
          @click="showAssignHierarchy = true"
        >
          <FolderTree class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Hierarchie zuordnen</span>
        </button>

        <!-- Admin: Bulk Delete -->
        <button
          v-if="authStore.userRole === 'Admin'"
          class="pim-btn text-xs bg-[var(--color-error-light)] text-[var(--color-error)] hover:bg-[var(--color-error)] hover:text-white"
          :disabled="bulkDeleting"
          @click="showConfirmBulkDelete = true"
        >
          <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">{{ bulkDeleting ? 'Lösche...' : 'Löschen' }}</span>
        </button>

        <button class="pim-btn pim-btn-ghost text-xs ml-auto" @click="clearAllSelection">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
          Auswahl aufheben
        </button>
      </div>

      <!-- Bulk delete confirm -->
      <div v-if="showConfirmBulkDelete" class="flex items-center gap-3 px-3 py-2 bg-[var(--color-error-light)] border border-[var(--color-error)]/20 rounded-lg">
        <span class="text-xs text-[var(--color-error)] font-medium">
          {{ selectedProductIds.length.toLocaleString() }} Produkte unwiderruflich löschen?
        </span>
        <button
          class="pim-btn text-xs bg-[var(--color-error)] text-white hover:opacity-90"
          @click="bulkDeleteProducts"
        >
          Ja, löschen
        </button>
        <button
          class="pim-btn pim-btn-ghost text-xs"
          @click="showConfirmBulkDelete = false"
        >
          Abbrechen
        </button>
      </div>
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
      ref="searchTableRef"
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
          <div class="relative z-10 w-full max-w-4xl max-h-[85vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
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

    <!-- PDF Template Picker -->
    <PdfTemplatePickerModal
      v-model:open="showPdfPicker"
      :productIds="reportProductIds"
    />

    <!-- Bulk Assign to Project Dialog -->
    <BulkAssignProjectDialog
      v-model:open="showAssignProject"
      :productIds="selectedProductIds"
    />

    <!-- Bulk Assign to Hierarchy Node Dialog -->
    <BulkAssignHierarchyNodeDialog
      v-model:open="showAssignHierarchy"
      :productIds="selectedProductIds"
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
