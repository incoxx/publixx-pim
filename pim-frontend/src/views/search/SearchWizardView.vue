<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLocaleStore } from '@/stores/locale'
import { useLocalizedName } from '@/composables/useLocalizedName'
import {
  Search, Filter, ChevronDown, ChevronUp, ChevronRight, X, Star,
  Regex, AudioLines, Languages, Download, GitCompareArrows, Pencil, Settings,
  Package, Sliders, GitBranch, Image, FolderTree, FileSpreadsheet, FileText, Code2, ListFilter,
  Trash2, CheckCheck, FileOutput, LayoutGrid, List,
  Factory, Type, Plus,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useServerQuickLookup } from '@/composables/useServerQuickLookup'
import searchApi from '@/api/search'
import searchProfilesApi from '@/api/searchProfiles'
import { useColumnProfiles } from '@/composables/useColumnProfiles'
import watchlistApi from '@/api/watchlist'
import productsApi from '@/api/products'
import hierarchiesApi from '@/api/hierarchies'
import mediaApi from '@/api/media'
import attributesApiDefault from '@/api/attributes'
import contentApi from '@/api/content'
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
import { tags as tagsApi } from '@/api/tags'

const localeStore = useLocaleStore()
// Sprachen aus der Verwaltung (Tabelle `languages`), nicht hart kodiert
const translationLanguages = computed(() => localeStore.availableLocales)

const { t, locale } = useI18n()
const numberLocale = computed(() => (locale.value === 'de' ? 'de-DE' : 'en-US'))
const { localizedName } = useLocalizedName()
const router = useRouter()

// Ansichtsmodus (Liste / Kacheln) mit localStorage-Persistenz
const viewMode = ref(localStorage.getItem('viewMode:search') || 'list')
function setViewMode(mode) {
  viewMode.value = mode
  localStorage.setItem('viewMode:search', mode)
  if (mode === 'grid') loadGridThumbnails()
}

// Thumbnail-Cache für Kachelansicht
const gridThumbs = ref({})

async function loadGridThumbnails() {
  const ids = results.value
    .map(p => p.id)
    .filter(id => id && !gridThumbs.value[id])
  if (!ids.length) return
  const settled = await Promise.allSettled(
    ids.map(async (pid) => {
      const { data } = await productsApi.getMedia(pid)
      const items = data.data || data
      const primary = items.find(m => m.is_primary)
        || items.find(m => m.usage_type?.technical_name === 'teaser')
        || items.find(m => (m.mime_type || m.media?.mime_type || '').startsWith('image/'))
      if (primary) {
        const mid = primary.media_id || primary.media?.id || primary.id
        return { pid, url: mid ? mediaApi.thumbUrl(mid, 300, 300) : null }
      }
      return { pid, url: null }
    })
  )
  const updated = { ...gridThumbs.value }
  for (const r of settled) {
    if (r.status === 'fulfilled' && r.value.url) {
      updated[r.value.pid] = r.value.url
    }
  }
  gridThumbs.value = updated
}

const attrStore = useAttributeStore()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()

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
  selectedProductTypes.value = profile.product_type_ids || []
  selectedManufacturers.value = profile.manufacturer_ids || []
  selectedTags.value = profile.tag_ids || []
  tagMatch.value = profile.tag_match || 'any'
  attributeFilters.value = profile.attribute_filters || {}
  attributeFilterGroups.value = profile.attribute_filter_groups || { operator: 'AND', rules: [] }
  if (profile.sort_field) sortField.value = profile.sort_field
  if (profile.sort_order) sortOrder.value = profile.sort_order
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
      product_type_ids: selectedProductTypes.value,
      manufacturer_ids: selectedManufacturers.value,
      tag_ids: selectedTags.value,
      tag_match: tagMatch.value,
      attribute_filters: attributeFilters.value,
      attribute_filter_groups: attributeFilterGroups.value.rules?.length ? attributeFilterGroups.value : null,
      sort_field: sortField.value,
      sort_order: sortOrder.value,
    })
    await loadProfiles()
  } catch (e) {
    error.value = t('Profil konnte nicht gespeichert werden')
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
      product_type_ids: selectedProductTypes.value,
      manufacturer_ids: selectedManufacturers.value,
      tag_ids: selectedTags.value,
      tag_match: tagMatch.value,
      attribute_filters: attributeFilters.value,
      attribute_filter_groups: attributeFilterGroups.value.rules?.length ? attributeFilterGroups.value : null,
      sort_field: sortField.value,
      sort_order: sortOrder.value,
    })
    await loadProfiles()
  } catch (e) {
    error.value = t('Profil konnte nicht aktualisiert werden')
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
      if (confirm(t('Dieses Suchprofil wird noch verwendet: {labels}. Trotzdem löschen?', { labels }))) {
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
    error.value = t('Profil konnte nicht gelöscht werden')
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

const searchCategoryDefs = computed(() => {
  const defs = [
    { key: 'products', label: t('Produkte'), icon: Package },
    { key: 'attributes', label: t('Attribute'), icon: Sliders },
    { key: 'nodes', label: t('Kategorieknoten'), icon: FolderTree },
    { key: 'media', label: t('Medien'), icon: Image },
  ]
  // Content-Tab nur anzeigen, wenn das Content-Lizenzmodul aktiv ist
  if (licenseStore.isModuleActive('content')) {
    defs.push({ key: 'content', label: t('Content'), icon: FileText })
  }
  return defs
})

// Column config for products search tab
const defaultSearchColumns = [
  { key: 'sku', label: t('SKU'), mono: true, sortable: true },
  { key: 'name', label: t('Name'), sortable: true },
  { key: 'product_type.name_de', label: t('Typ'), render: (row) => row.product_type ? (localizedName(row.product_type) || row.product_type.technical_name) : '—' },
  { key: 'status', label: t('Status'), sortable: true },
  { key: 'updated_at', label: t('Geändert'), sortable: true },
]
const extraSearchColumns = [
  { key: 'ean', label: t('EAN'), mono: true },
  { key: 'tags', label: t('Tags'), render: (row) => (row.tags || []).map(tag => localizedName(tag) || tag.technical_name).join(', ') || '—' },
  { key: 'manufacturer.name', label: t('Hersteller') },
  { key: 'created_at', label: t('Erstellt'), sortable: true },
]

// Dynamic attribute columns (populated after searchableAttributes are loaded)
const attributeColumns = computed(() =>
  searchableAttributes.value.map(attr => ({
    key: `attributes.${attr.id}`,
    label: localizedName(attr) || attr.technical_name,
    hint: attr.technical_name,
    group: t('Attribute'),
    exportKey: `attr:${attr.id}`,
  }))
)

const { visibleColumns: searchVisibleColumns, allColumns: searchAllColumns, visibleKeys: searchVisibleKeys, toggleColumn: searchToggleColumn, moveColumn: searchMoveColumn, resetColumns: searchResetColumns } = useColumnConfig('columns:search', defaultSearchColumns, extraSearchColumns, attributeColumns)

const { columnProfiles, selectedColumnProfileId, loadColumnProfiles, loadColumnProfile, saveColumnProfile, updateColumnProfile, deleteColumnProfile } = useColumnProfiles('search', searchVisibleKeys)

// Neu eingeblendete Attribut-Spalten benötigen ihre Werte vom Server. Wird eine
// solche Spalte eingeblendet, während bereits Ergebnisse vorliegen, die aktuelle
// Ergebnisseite neu laden, damit die Zellen sofort gefüllt sind.
const searchAttributeColumnKeys = computed(() =>
  searchVisibleKeys.value.filter(k => k.startsWith('attributes.'))
)
watch(searchAttributeColumnKeys, (newKeys, oldKeys) => {
  if (searchCategory.value !== 'products' || results.value.length === 0) return
  const added = newKeys.some(k => !oldKeys.includes(k))
  if (added) doProductSearch(resultMeta.value.current_page || 1).catch(() => {})
})

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
      language: localeStore.currentLocale,
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
    error.value = t('Excel-Export fehlgeschlagen')
    console.error('Excel export failed:', e)
  } finally { excelExporting.value = false }
}

const categoryColumns = {
  attributes: [
    { key: 'technical_name', label: t('Techn. Name'), mono: true, sortable: true },
    { key: 'name_de', label: t('Name'), sortable: true, render: (row) => localizedName(row) || row.technical_name },
    { key: 'data_type', label: t('Datentyp'), sortable: true },
    { key: 'attribute_type.name_de', label: t('Gruppe'), render: (row) => row.attribute_type ? (localizedName(row.attribute_type) || row.attribute_type.technical_name) : '—' },
    { key: 'status', label: t('Status') },
  ],
  nodes: [
    { key: 'name_de', label: t('Name'), sortable: true, render: (row) => localizedName(row) || row.technical_name },
    { key: 'path', label: t('Pfad') },
    { key: 'depth', label: t('Tiefe'), sortable: true },
    { key: 'is_active', label: t('Aktiv') },
  ],
  media: [
    { key: 'file_name', label: t('Datei'), mono: true, sortable: true },
    { key: 'title_de', label: t('Titel'), sortable: true, render: (row) => localizedName(row, 'title') || row.file_name },
    { key: 'mime_type', label: t('Typ') },
    { key: 'media_type', label: t('Medientyp') },
  ],
  content: [
    { key: 'title', label: t('Titel'), sortable: true },
    { key: 'content_type', label: t('Seitentyp') },
    { key: 'status', label: t('Status') },
    { key: 'snippet', label: t('Fundstelle') },
    { key: 'updated_at', label: t('Geändert'), sortable: true },
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
// Tags: Mehrfachauswahl, tagMatch entscheidet zwischen "eines davon" und "alle"
const selectedTags = ref([])
const tagMatch = ref('any')
const tagList = ref([])

// Quick Lookup — filtert serverseitig über die komplette Treffermenge, nicht nur
// die aktuell angezeigte Seite. quickLookupMappedFilters wird in buildSearchParams()
// mit den übrigen Filtern (Panel, Query Builder) zusammengeführt.
const quickLookupMappedFilters = ref({})

// Live count: debounced update when query builder changes
watch(autoShowFilterColumns, (val) => {
  localStorage.setItem('search:autoShowFilterColumns', val ? 'true' : 'false')
})

watch([attributeFilterGroups, quickLookupMappedFilters], () => {
  if (liveCountTimer) clearTimeout(liveCountTimer)
  if (!attributeFilterGroups.value.rules?.length) {
    liveCount.value = null
    return
  }
  liveCountTimer = setTimeout(async () => {
    try {
      // buildSearchParams() statt manuell — enthält alle aktiven Filter
      const { data } = await searchApi.count(buildSearchParams())
      liveCount.value = data.count
    } catch {
      liveCount.value = null
    }
  }, 600)
}, { deep: true })

// Watchlist quick-add
const watchlistIds = ref(new Set())

// Selection & XLIFF export
const selectedProductIds = ref([])
const allPagesSelected = ref(false)
const selectingAll = ref(false)

// Wenn Filter sich ändern und "alle Seiten" selektiert waren → Auswahl verwerfen,
// da die IDs zur alten Suche gehören und nicht mehr zur aktuellen passen.
watch(
  [attributeFilterGroups, statusFilter, selectedCategories, selectedProductTypes, selectedManufacturers, missingTranslationFilter, searchInput, quickLookupMappedFilters],
  () => {
    if (allPagesSelected.value) {
      selectedProductIds.value = []
      allPagesSelected.value = false
      // searchTableRef kann hier noch nicht referenziert werden (vor onMounted),
      // clearSelection wird daher defensiv aufgerufen
      searchTableRef.value?.clearSelection()
    }
  },
  { deep: true },
)
const bulkDeleting = ref(false)
const showConfirmBulkDelete = ref(false)
const searchTableRef = ref(null)
const showXliffPanel = ref(false)
const showReportPicker = ref(false)
const showPdfPicker = ref(false)
const showExportMenu = ref(false)
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
const statusOptions = computed(() => [
  { value: 'active', label: t('Aktiv') },
  { value: 'draft', label: t('Entwurf') },
  { value: 'inactive', label: t('Inaktiv') },
  { value: 'discontinued', label: t('Auslaufend') },
])

const productTypeOptions = computed(() =>
  attrStore.prodTypes.map(pt => ({ value: pt.id, label: localizedName(pt) || pt.technical_name }))
)

const manufacturerQuickOptions = computed(() =>
  manufacturerList.value.map(m => ({ value: m.id, label: m.name }))
)

const quickLookupConfig = computed(() => {
  const config = {
    sku: { type: 'text', placeholder: t('SKU...') },
    name: { type: 'text', placeholder: t('Name...') },
    'product_type.name_de': { type: 'select', options: productTypeOptions.value },
    'manufacturer.name': { type: 'select', options: manufacturerQuickOptions.value },
    status: { type: 'select', options: statusOptions.value },
    ean: { type: 'text', placeholder: t('EAN...') },
  }
  // Add dynamic attribute columns
  for (const attr of searchableAttributes.value) {
    const key = `attributes.${attr.id}`
    if (attr.data_type === 'Selection' || attr.data_type === 'Dictionary') {
      config[key] = {
        type: 'select',
        // Wert = Werteliste-Eintrags-ID, damit der Backend-Operator 'eq' (Vergleich
        // gegen value_selection_id) greift — siehe ProductSearchFilters::applyAttributeFilter().
        options: (attr.value_list?.entries || []).map(e => ({
          value: e.id,
          label: localizedName(e, 'display_value') || e.code,
        })),
      }
    } else if (attr.data_type !== 'Date') {
      config[key] = { type: 'text', placeholder: '...' }
    }
  }
  return config
})

// Spalten-Key → Backend-Parameter. sku/name/status/ean sind flache Präfix-/Exakt-Filter,
// product_type/manufacturer werden mit der Panel-Auswahl zur Vereinigungsmenge
// zusammengeführt (siehe buildSearchParams()), attributes.{id} landet im
// attribute_filters-Array (starts_with für Text, eq für Selection/Dictionary).
const QUICK_LOOKUP_FIELD_MAP = {
  sku: 'sku',
  name: 'name',
  status: 'status',
  ean: 'ean',
  'product_type.name_de': 'product_type_id',
  'manufacturer.name': 'manufacturer_id',
}

function applyQuickLookupFilters(rawFilters) {
  const mapped = {}
  const attributeFilters = {}
  for (const [colKey, value] of Object.entries(rawFilters)) {
    if (value === '' || value == null) continue
    if (colKey.startsWith('attributes.')) {
      attributeFilters[colKey.replace('attributes.', '')] = value
      continue
    }
    const field = QUICK_LOOKUP_FIELD_MAP[colKey]
    if (!field) continue
    mapped[field] = value
  }
  if (Object.keys(attributeFilters).length > 0) mapped.attributes = attributeFilters
  quickLookupMappedFilters.value = mapped
  doSearch(1)
}

const { showQuickLookup, onQuickLookupChange, toggleQuickLookup } = useServerQuickLookup(applyQuickLookupFilters)

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
  let count = selectedCategories.value.length + selectedProductTypes.value.length
    + selectedManufacturers.value.length + selectedTags.value.length
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
      const name = localizedName(node) || node.id
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

// ─────────────────────────────────────────────────────────────
// Geführter Modus ("Was suchen Sie?")
// Vorgeschaltete Ebene, die aus Intent-Bausteinen die vorhandenen
// Filter-Refs füllt. KEINE neue Suchlogik — nur ein freundlicher
// Einstieg über doSearch(). "Erweiterte Suche" zeigt weiterhin das
// komplette technische Formular.
// ─────────────────────────────────────────────────────────────
const guidedMode = ref(localStorage.getItem('search:mode') !== 'expert') // Default: geführt, letzte Wahl gemerkt
watch(guidedMode, (v) => localStorage.setItem('search:mode', v ? 'guided' : 'expert'))

const isProductGuided = computed(() => guidedMode.value && searchCategory.value === 'products')

const guidedIntents = [
  { key: 'merkmal', title: 'mit einem bestimmten Merkmal', sub: 'Attribut · Wert', icon: Sliders },
  { key: 'kategorie', title: 'aus einer Kategorie', sub: 'Hierarchie-Knoten', icon: FolderTree },
  { key: 'hersteller', title: 'von einem Hersteller', sub: 'Marke / Lieferant', icon: Factory },
  { key: 'typ', title: 'eines bestimmten Produkttyps', sub: 'z. B. BMEcat, Schulung', icon: Package },
  { key: 'translation', title: 'mit fehlender Übersetzung', sub: 'Datenqualität', icon: Languages },
  { key: 'name', title: 'mit einem Namen oder einer SKU', sub: 'Volltext', icon: Type },
  { key: 'muster', title: 'nach einem Muster (REGEXP)', sub: 'für Profis', icon: Regex },
]

const openIntents = ref([]) // manuell aufgeklappte Bausteine

function intentHasValue(key) {
  switch (key) {
    case 'merkmal': return !!attributeFilterGroups.value.rules?.some(r => r.type === 'rule' && r.attribute_id)
    case 'kategorie': return selectedCategories.value.length > 0
    case 'hersteller': return selectedManufacturers.value.length > 0
    case 'typ': return selectedProductTypes.value.length > 0
    case 'translation': return !!(missingTranslationFilter.value.attribute_id && missingTranslationFilter.value.target_language)
    case 'name': return !!searchInput.value.trim() && searchMode.value !== 'regex'
    case 'muster': return !!searchInput.value.trim() && searchMode.value === 'regex'
  }
  return false
}
// Baustein ist sichtbar, wenn manuell geöffnet ODER der zugehörige Filter Werte hat
// (z.B. aus einem geladenen Suchprofil).
function isIntentOpen(key) {
  return openIntents.value.includes(key) || intentHasValue(key)
}
function openIntent(key) {
  if (!openIntents.value.includes(key)) openIntents.value = [...openIntents.value, key]
  if (key === 'merkmal' && !attributeFilterGroups.value.rules?.some(r => r.type === 'rule')) {
    attributeFilterGroups.value = {
      operator: attributeFilterGroups.value.operator || 'AND',
      rules: [...(attributeFilterGroups.value.rules || []), { type: 'rule', attribute_id: '', operator: 'eq', value: '' }],
    }
  }
  if (key === 'kategorie') showCategoryPicker.value = true
  if (key === 'name' && searchMode.value === 'regex') searchMode.value = 'like'
  if (key === 'muster') searchMode.value = 'regex'
}
function closeIntent(key) {
  openIntents.value = openIntents.value.filter(k => k !== key)
  switch (key) {
    case 'merkmal': attributeFilterGroups.value = { operator: attributeFilterGroups.value.operator || 'AND', rules: [] }; break
    case 'kategorie': selectedCategories.value = []; break
    case 'hersteller': selectedManufacturers.value = []; break
    case 'typ': selectedProductTypes.value = []; break
    case 'translation': missingTranslationFilter.value = { attribute_id: null, target_language: null }; break
    case 'name': searchInput.value = ''; break
    case 'muster': searchInput.value = ''; searchMode.value = 'like'; break
  }
}
function toggleIntent(key) {
  if (isIntentOpen(key)) closeIntent(key)
  else openIntent(key)
}
function resetGuided() {
  openIntents.value = []
  statusFilter.value = ''
  selectedCategories.value = []
  selectedManufacturers.value = []
  selectedProductTypes.value = []
  selectedTags.value = []
  tagMatch.value = 'any'
  missingTranslationFilter.value = { attribute_id: null, target_language: null }
  attributeFilterGroups.value = { operator: 'AND', rules: [] }
  searchInput.value = ''
  searchMode.value = 'like'
}

// Ganze Phrase pro Status uebersetzt statt Wort-fuer-Wort komponiert -- die
// deutsche Adjektivbeugung ("aktiven", "Entwurfs-") hat im Englischen keine
// Entsprechung, daher pro Kombination ein eigener, natuerlich klingender Key.
const guidedStatusPhrase = computed(() => {
  const phrases = {
    '': t('Alle Produkte'),
    active: t('Alle aktiven Produkte'),
    draft: t('Alle Entwurfs-Produkte'),
    inactive: t('Alle inaktiven Produkte'),
    discontinued: t('Alle auslaufenden Produkte'),
  }
  return phrases[statusFilter.value] || phrases['']
})

const guidedOpLabels = {
  like: 'enthält', eq: 'ist', neq: 'ist nicht', gt: '>', gte: '≥', lt: '<', lte: '≤',
  starts_with: 'beginnt mit', ends_with: 'endet mit', regex: 'Regex', soundex: 'klingt wie', between: 'zwischen',
}
function guidedOpLabel(op) {
  const raw = guidedOpLabels[op]
  return raw ? t(raw) : op
}

// Klartext-Suchsatz aus dem tatsächlichen Filterzustand (immer wahrheitsgetreu).
const guidedClauses = computed(() => {
  const c = []
  for (const r of (attributeFilterGroups.value.rules || [])) {
    if (r.type === 'rule' && r.attribute_id) {
      const a = searchableAttributes.value.find(x => x.id === r.attribute_id)
      const name = localizedName(a) || a?.technical_name || t('Merkmal')
      const val = (r.value !== '' && r.value != null) ? r.value : '…'
      c.push(t('mit {name} {op} {val}', { name, op: guidedOpLabel(r.operator), val }))
    }
  }
  if (selectedCategories.value.length) {
    const names = selectedCategories.value.map(id => flatCategoryNodes.value.find(n => n.id === id)?.name).filter(Boolean)
    if (names.length) c.push(t('aus Kategorie {list}', { list: names.map(n => `"${n}"`).join(', ') }))
  }
  if (selectedManufacturers.value.length) {
    const names = selectedManufacturers.value.map(id => manufacturerList.value.find(m => m.id === id)?.name).filter(Boolean)
    if (names.length) c.push(t('von {names}', { names: names.join(', ') }))
  }
  if (selectedProductTypes.value.length) {
    const names = selectedProductTypes.value.map(id => attrStore.prodTypes.find(p => p.id === id)).map(p => localizedName(p) || p?.technical_name).filter(Boolean)
    if (names.length) c.push(t('vom Typ {names}', { names: names.join(', ') }))
  }
  if (missingTranslationFilter.value.attribute_id && missingTranslationFilter.value.target_language) {
    const lang = translationLanguages.value.find(l => l.code === missingTranslationFilter.value.target_language)
    c.push(t('ohne {lang}-Übersetzung', { lang: lang ? t(lang.label) : missingTranslationFilter.value.target_language }))
  }
  const q = searchInput.value.trim()
  if (q) {
    if (searchMode.value === 'regex') c.push(t('mit Muster /{q}/', { q }))
    else if (searchMode.value === 'soundex') c.push(t('ähnlich klingend zu "{q}"', { q }))
    else c.push(t('mit "{q}" im Namen/SKU', { q }))
  }
  return c
})

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

  // Load tags for filter — bewusst inklusive inaktiver Tags: ein gespeichertes
  // Suchprofil kann einen inzwischen deaktivierten Tag referenzieren. Fehlt der
  // in der Liste, filtert das Profil weiter, ohne dass ein Haken sichtbar waere.
  if (authStore.hasPermission('tags.view')) {
    try {
      const { data } = await tagsApi.list({ perPage: 200, sort: 'name_de', order: 'asc' })
      tagList.value = data.data || data
    } catch (e) { /* ignore */ }
  }

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

function toggleTag(id) {
  const idx = selectedTags.value.indexOf(id)
  if (idx === -1) {
    selectedTags.value.push(id)
  } else {
    selectedTags.value.splice(idx, 1)
  }
}

function isTagSelected(id) {
  return selectedTags.value.includes(id)
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
    error.value = e.response?.data?.message || e.response?.data?.detail || t('Suchfehler')
    results.value = []
  } finally {
    loading.value = false
  }
}

async function doProductSearch(page) {
  // Auto-show columns für gefilterte Attribute (Seiten-Effekt, vor buildSearchParams)
  if (autoShowFilterColumns.value) {
    const filterAttrIds = collectFilterAttributeIds(attributeFilterGroups.value)
    for (const id of filterAttrIds) {
      const colKey = `attributes.${id}`
      if (!searchVisibleKeys.value.includes(colKey)) {
        searchVisibleKeys.value.push(colKey)
      }
    }
    for (const attr of searchableAttributes.value) {
      const val = attributeFilters.value[attr.id]
      if (val === '' || val === null || val === undefined) continue
      const legacyColKey = `attributes.${attr.id}`
      if (!searchVisibleKeys.value.includes(legacyColKey)) {
        searchVisibleKeys.value.push(legacyColKey)
      }
    }
  }

  // Alle Filter-Parameter aus der zentralen Funktion holen
  const params = buildSearchParams()
  params.page = page
  params.per_page = 50
  params.sort = sortField.value
  params.order = sortOrder.value

  // Sichtbare Attribut-Spalten
  const attrColumnIds = searchVisibleKeys.value
    .filter(k => k.startsWith('attributes.'))
    .map(k => k.replace('attributes.', ''))
  if (attrColumnIds.length > 0) params.attribute_columns = attrColumnIds

  const { data } = await searchApi.search(params)
  results.value = data.data || []
  resultMeta.value = data.meta || { total: results.value.length, current_page: 1, last_page: 1 }
  if (viewMode.value === 'grid') loadGridThumbnails()
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
    case 'content':
      response = await contentApi.search(options)
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
    case 'content':
      router.push(`/content/${row.id}`)
      break
  }
}

function escapeHtml(str) {
  return str.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

// Suchbegriff im Textausschnitt hervorheben (Text wird vorher escaped, daher XSS-sicher)
function highlightSnippet(text, term) {
  if (!text) return ''
  const escaped = escapeHtml(text)
  const t = (term || '').trim()
  if (!t) return escaped
  const escapedTerm = t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return escaped.replace(new RegExp(`(${escapedTerm})`, 'ig'), '<mark class="bg-[var(--color-accent-light)] text-[var(--color-accent)] rounded px-0.5">$1</mark>')
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
    language: localeStore.currentLocale,
  }
  if (selectedCategories.value.length > 0) {
    params.category_ids = selectedCategories.value
    params.include_descendants = true
    const h = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
    if (h?.hierarchy_type) params.hierarchy_type = h.hierarchy_type
  }
  // Produkttyp/Hersteller: Panel-Auswahl + Quick-Lookup-Auswahl zur Vereinigungsmenge
  // zusammengeführt, da beide denselben Backend-Parameter nutzen.
  const productTypeIds = new Set(selectedProductTypes.value)
  if (quickLookupMappedFilters.value.product_type_id) productTypeIds.add(quickLookupMappedFilters.value.product_type_id)
  if (productTypeIds.size > 0) params.product_type_ids = [...productTypeIds]

  const manufacturerIds = new Set(selectedManufacturers.value)
  if (quickLookupMappedFilters.value.manufacturer_id) manufacturerIds.add(quickLookupMappedFilters.value.manufacturer_id)
  if (manufacturerIds.size > 0) params.manufacturer_ids = [...manufacturerIds]

  if (selectedTags.value.length > 0) {
    params.tag_ids = [...selectedTags.value]
    params.tag_match = tagMatch.value
  }

  if (statusFilter.value) {
    params.status = statusFilter.value
  } else if (quickLookupMappedFilters.value.status) {
    params.status = quickLookupMappedFilters.value.status
  }

  if (quickLookupMappedFilters.value.sku) params.sku = quickLookupMappedFilters.value.sku
  if (quickLookupMappedFilters.value.name) params.name = quickLookupMappedFilters.value.name
  if (quickLookupMappedFilters.value.ean) params.ean = quickLookupMappedFilters.value.ean

  // Attribut-Filter (Query Builder) — wird auch von selectAllPages() benötigt
  if (attributeFilterGroups.value.rules && attributeFilterGroups.value.rules.length > 0) {
    params.attribute_filter_groups = attributeFilterGroups.value
  }

  // Legacy Flat-Filter (Filter-Panel) + Quick-Lookup-Attributspalten zusammengeführt
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
  for (const [attrId, value] of Object.entries(quickLookupMappedFilters.value.attributes || {})) {
    const attr = searchableAttributes.value.find(a => a.id === attrId)
    const filter = { attribute_id: attrId, value }
    filter.operator = (attr?.data_type === 'Selection' || attr?.data_type === 'Dictionary') ? 'eq' : 'starts_with'
    attrFilters.push(filter)
  }
  if (attrFilters.length > 0) params.attribute_filters = attrFilters

  // Übersetzungs-Filter
  if (missingTranslationFilter.value.attribute_id && missingTranslationFilter.value.target_language) {
    params.missing_translation = {
      attribute_id: missingTranslationFilter.value.attribute_id,
      target_language: missingTranslationFilter.value.target_language,
    }
  }

  return params
}

async function selectAllPages() {
  if (selectingAll.value) return   // Doppelklick-Schutz
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
    // Neu hinzugefügte IDs direkt in bestehendes Set schreiben — kein Full-Reload nötig
    const updated = new Set(watchlistIds.value)
    selectedProductIds.value.forEach(id => updated.add(id))
    watchlistIds.value = updated
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

  if (selectedTags.value.length > 0) {
    params.tag_ids = selectedTags.value
    params.tag_match = tagMatch.value
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
  <div class="space-y-4" data-testid="search-view">
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

    <!-- Modus-Umschalter: Geführt / Erweitert (nur Produkte) -->
    <div v-if="searchCategory === 'products'" class="flex justify-end">
      <div class="inline-flex rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-0.5">
        <button
          class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
          :class="guidedMode ? 'bg-[var(--color-accent)] text-white' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
          @click="guidedMode = true"
        >Geführt</button>
        <button
          class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
          :class="!guidedMode ? 'bg-[var(--color-accent)] text-white' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
          @click="guidedMode = false"
        >Erweitert</button>
      </div>
    </div>

    <!-- ═══ Geführter Modus: „Was möchten Sie finden?" ═══ -->
    <div v-if="isProductGuided" class="space-y-4" data-testid="guided-search">
      <div>
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Was möchten Sie finden?</h2>
        <p class="text-xs text-[var(--color-text-secondary)] mt-0.5">
          Wählen Sie einen Ausgangspunkt — daraus entsteht Ihre Suche in Klartext. Mehreres lässt sich kombinieren.
        </p>
      </div>

      <!-- Status-Vorfilter -->
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-tertiary)]">Status</span>
        <div class="inline-flex rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-0.5">
          <button
            v-for="opt in [{ v: '', l: t('Alle') }, { v: 'active', l: t('Aktiv') }, { v: 'draft', l: t('Entwurf') }]"
            :key="opt.v"
            class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
            :class="statusFilter === opt.v ? 'bg-[var(--color-surface)] text-[var(--color-text-primary)] shadow-sm' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
            @click="statusFilter = opt.v"
          >{{ opt.l }}</button>
        </div>
      </div>

      <!-- Intent-Bausteine -->
      <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-tertiary)]">Ich brauche alle Produkte …</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        <button
          v-for="it in guidedIntents"
          :key="it.key"
          type="button"
          class="flex items-start gap-3 p-3.5 rounded-xl border text-left transition-all"
          :class="isIntentOpen(it.key)
            ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_10%,var(--color-surface))] ring-1 ring-[var(--color-accent)]'
            : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-accent)]'"
          @click="toggleIntent(it.key)"
        >
          <span class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center bg-[var(--color-accent)] text-white">
            <component :is="it.icon" class="w-4 h-4" :stroke-width="1.75" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[13px] font-semibold text-[var(--color-text-primary)] leading-tight">… {{ t(it.title) }}</span>
            <span class="block text-[11px] text-[var(--color-text-tertiary)] mt-0.5">{{ t(it.sub) }}</span>
          </span>
          <CheckCheck v-if="isIntentOpen(it.key)" class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
          <Plus v-else class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
        </button>
      </div>

      <!-- Ihre Suche: Klartext + Detailfelder -->
      <div class="pim-card overflow-hidden p-0">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--color-border)]">
          <span class="w-2 h-2 rounded-full bg-[var(--color-accent)]"></span>
          <span class="text-sm font-semibold text-[var(--color-text-primary)]">Ihre Suche</span>
          <span class="ml-auto text-xs text-[var(--color-text-secondary)]">
            <span v-if="liveCount !== null"><strong class="text-[var(--color-text-primary)]">{{ liveCount.toLocaleString(numberLocale) }}</strong> Treffer (Vorschau)</span>
            <span v-else-if="activeFilterCount > 0">{{ t('{count} Filter aktiv', { count: activeFilterCount }) }}</span>
          </span>
        </div>

        <!-- Klartext-Suchsatz -->
        <div class="px-4 pt-3 text-[15px] leading-relaxed">
          <span class="text-[var(--color-text-tertiary)]">{{ guidedStatusPhrase }}</span><template
            v-for="(cl, i) in guidedClauses" :key="i"
          ><span class="text-[var(--color-text-tertiary)]">{{ i === 0 ? ' ' : (i === guidedClauses.length - 1 ? ` ${t('und')} ` : ', ') }}</span><span
            class="font-semibold text-[var(--color-accent-dark)] bg-[color-mix(in_srgb,var(--color-accent)_12%,transparent)] rounded px-1.5 py-0.5"
          >{{ cl }}</span></template><span v-if="!guidedClauses.length" class="text-[var(--color-text-tertiary)]"> …</span><span class="text-[var(--color-text-tertiary)]">.</span>
        </div>

        <!-- Detailfelder je aktivem Baustein -->
        <div class="px-4 py-3 space-y-3">
          <!-- Merkmal -->
          <div v-if="isIntentOpen('merkmal')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><Sliders class="w-3.5 h-3.5" :stroke-width="1.75" />Merkmal</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('merkmal')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <QueryBuilderGroup :group="attributeFilterGroups" :attributes="searchableAttributes" @update="attributeFilterGroups = $event" />
          </div>

          <!-- Kategorie -->
          <div v-if="isIntentOpen('kategorie')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><FolderTree class="w-3.5 h-3.5" :stroke-width="1.75" />Kategorie (inkl. Unterkategorien)</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('kategorie')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <div v-if="hierarchies.length > 1" class="flex items-center gap-2 mb-2">
              <label class="text-[11px] font-medium text-[var(--color-text-tertiary)]">Hierarchie:</label>
              <select class="pim-input text-xs flex-1" :value="selectedHierarchyId" @change="selectedHierarchyId = $event.target.value">
                <option v-for="h in hierarchies" :key="h.id" :value="h.id">{{ localizedName(h) || h.technical_name }}</option>
              </select>
            </div>
            <div class="max-h-44 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5 bg-[var(--color-surface)]">
              <label v-for="cat in flatCategoryNodes" :key="cat.id" class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs">
                <input type="checkbox" :checked="isCategorySelected(cat.id)" @change="toggleCategory(cat.id)" class="rounded border-[var(--color-border)]" />
                <span class="text-[var(--color-text-primary)]">{{ cat.label }}</span>
              </label>
              <p v-if="flatCategoryNodes.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">Keine Kategorien vorhanden</p>
            </div>
          </div>

          <!-- Hersteller -->
          <div v-if="isIntentOpen('hersteller')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><Factory class="w-3.5 h-3.5" :stroke-width="1.75" />Hersteller</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('hersteller')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <div v-if="manufacturerList.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">Keine Hersteller angelegt</div>
            <div v-else class="max-h-44 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5 bg-[var(--color-surface)]">
              <label v-for="m in manufacturerList" :key="m.id" class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs">
                <input type="checkbox" :checked="isManufacturerSelected(m.id)" @change="toggleManufacturer(m.id)" class="rounded border-[var(--color-border)]" />
                <span class="text-[var(--color-text-primary)]">{{ m.name }}</span>
              </label>
            </div>
          </div>

          <!-- Produkttyp -->
          <div v-if="isIntentOpen('typ')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><Package class="w-3.5 h-3.5" :stroke-width="1.75" />Produkttyp</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('typ')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <div v-if="attrStore.prodTypes.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">Keine Produkttypen vorhanden</div>
            <div v-else class="max-h-44 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5 bg-[var(--color-surface)]">
              <label v-for="pt in attrStore.prodTypes" :key="pt.id" class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs">
                <input type="checkbox" :checked="isProductTypeSelected(pt.id)" @change="toggleProductType(pt.id)" class="rounded border-[var(--color-border)]" />
                <span class="text-[var(--color-text-primary)]">{{ localizedName(pt) || pt.technical_name }}</span>
              </label>
            </div>
          </div>

          <!-- Fehlende Übersetzung -->
          <div v-if="isIntentOpen('translation')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><Languages class="w-3.5 h-3.5" :stroke-width="1.75" />Fehlende Übersetzung</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('translation')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">Attribut</label>
                <select class="pim-input text-xs w-full" :value="missingTranslationFilter.attribute_id || ''" @change="missingTranslationFilter.attribute_id = $event.target.value || null">
                  <option value="">— Keins —</option>
                  <option v-for="attr in translatableSearchAttributes" :key="attr.id" :value="attr.id">{{ localizedName(attr) || attr.technical_name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">Zielsprache</label>
                <select class="pim-input text-xs w-full" :value="missingTranslationFilter.target_language || ''" @change="missingTranslationFilter.target_language = $event.target.value || null">
                  <option value="">— Wählen —</option>
                  <option v-for="lang in translationLanguages" :key="lang.code" :value="lang.code">{{ t(lang.label) }} ({{ lang.code }})</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Name / SKU -->
          <div v-if="isIntentOpen('name')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><Type class="w-3.5 h-3.5" :stroke-width="1.75" />Name oder SKU</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('name')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <input v-model="searchInput" type="text" class="pim-input text-sm w-full" placeholder="z. B. Akkubohrer oder 10023" @keydown.enter="doSearch(1)" />
            <label class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-secondary)] mt-2 cursor-pointer select-none">
              <input type="checkbox" :checked="searchMode === 'soundex'" @change="searchMode = $event.target.checked ? 'soundex' : 'like'" class="rounded border-[var(--color-border)]" />
              Ähnlich klingend suchen (Tippfehler-tolerant, SOUNDEX)
            </label>
          </div>

          <!-- Muster (REGEXP) -->
          <div v-if="isIntentOpen('muster')" class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] p-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-[var(--color-text-secondary)] flex items-center gap-1.5"><Regex class="w-3.5 h-3.5" :stroke-width="1.75" />Muster (REGEXP)</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="closeIntent('muster')" title="Entfernen"><X class="w-4 h-4" :stroke-width="2" /></button>
            </div>
            <input v-model="searchInput" type="text" class="pim-input text-sm w-full font-mono" placeholder="^AK-[0-9]{4}$" @keydown.enter="doSearch(1)" />
          </div>
        </div>

        <!-- Aktionen -->
        <div class="flex items-center gap-3 px-4 py-3 border-t border-[var(--color-border)] bg-[var(--color-bg)]">
          <button class="pim-btn pim-btn-primary" @click="doSearch(1)" data-testid="btn-guided-search">
            <Search class="w-4 h-4" :stroke-width="2" /> Suchen
          </button>
          <button class="pim-btn pim-btn-ghost text-xs" @click="resetGuided">Zurücksetzen</button>
          <button class="ml-auto text-xs text-[var(--color-text-secondary)] hover:text-[var(--color-accent)] flex items-center gap-1" @click="guidedMode = false" title="Zur erweiterten Suche">
            <Settings class="w-3.5 h-3.5" :stroke-width="1.75" /> Erweiterte Suche
          </button>
        </div>
      </div>
    </div>

    <!-- Search header -->
    <div v-if="!isProductGuided" class="space-y-2 sm:space-y-0 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <Search class="w-5 h-5 text-[var(--color-text-tertiary)] shrink-0 hidden sm:block" :stroke-width="1.75" />
        <input
          v-model="searchInput"
          type="text"
          :placeholder="searchCategory === 'products'
            ? (searchMode === 'regex' ? t('Regulärer Ausdruck eingeben...') : searchMode === 'soundex' ? t('Ähnlich klingend suchen...') : t('Produkte, Attribute, SKUs durchsuchen...'))
            : searchCategory === 'attributes' ? t('Attribute durchsuchen...')
            : searchCategory === 'nodes' ? t('Kategorieknoten durchsuchen (inkl. Unterkategorien)...')
            : searchCategory === 'content' ? t('Content-Seiten durchsuchen (Titel & Seiteninhalt)...')
            : t('Medien durchsuchen...')"
          class="pim-input pl-4 pr-4 py-2.5 sm:py-3 text-sm sm:text-base w-full"
          @keydown.enter="doSearch(1)"
          autofocus
          data-testid="search-input"
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
      <!-- Ansichtsmodus -->
      <div v-if="searchCategory === 'products'" class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
        <button
          class="p-1.5 transition-colors"
          :class="viewMode === 'grid' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
          @click="setViewMode('grid')"
          title="Kachelansicht"
        >
          <LayoutGrid class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <button
          class="p-1.5 transition-colors"
          :class="viewMode === 'list' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
          @click="setViewMode('list')"
          title="Listenansicht"
        >
          <List class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
      <ColumnConfigPopover
        v-if="searchCategory === 'products'"
        :allColumns="searchAllColumns"
        :visibleKeys="searchVisibleKeys"
        @toggle="searchToggleColumn"
        @move="searchMoveColumn"
        @reset="searchResetColumns"
        @reorder="searchVisibleKeys = $event"
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
        @click="toggleQuickLookup(searchTableRef)"
        title="Quick Lookup"
      >
        <ListFilter class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Quick Lookup</span>
      </button>
      <!-- Export-Dropdown (Excel / Report / PDF) -->
      <div
        v-if="searchCategory === 'products' && hasSearched && results.length > 0"
        class="relative"
      >
        <!-- transparenter Overlay zum Schließen bei Klick außerhalb -->
        <div v-if="showExportMenu" class="fixed inset-0 z-40" @click="showExportMenu = false" />
        <button
          class="pim-btn pim-btn-secondary py-2 px-3 sm:py-3 sm:px-4 flex items-center gap-1"
          :class="showExportMenu ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : ''"
          @click="showExportMenu = !showExportMenu"
        >
          <Download class="w-4 h-4" :stroke-width="1.75" />
          <span class="ml-1 text-sm hidden sm:inline">Export</span>
          <ChevronDown class="w-3 h-3 ml-0.5" :stroke-width="2.5" />
        </button>
        <div
          v-if="showExportMenu"
          class="absolute right-0 top-full mt-1 z-50 pim-card shadow-lg min-w-[160px] py-1 border border-[var(--color-border)]"
        >
          <button
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--color-bg)] text-left transition-colors"
            :disabled="excelExporting"
            @click="exportSearchExcel(); showExportMenu = false"
          >
            <FileSpreadsheet class="w-4 h-4 shrink-0 text-[var(--color-text-secondary)]" :stroke-width="1.75" />
            {{ excelExporting ? t('Exportiere…') : 'Excel' }}
          </button>
          <button
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--color-bg)] text-left transition-colors"
            @click="showReportPicker = true; showExportMenu = false"
          >
            <FileText class="w-4 h-4 shrink-0 text-[var(--color-text-secondary)]" :stroke-width="1.75" />
            Report
          </button>
          <button
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--color-bg)] text-left transition-colors"
            @click="showPdfPicker = true; showExportMenu = false"
          >
            <FileOutput class="w-4 h-4 shrink-0 text-[var(--color-text-secondary)]" :stroke-width="1.75" />
            PDF
          </button>
        </div>
      </div>
      <button class="pim-btn pim-btn-primary py-2 px-4 sm:py-3 sm:px-6" @click="doSearch(1)" data-testid="btn-search">
        Suchen
      </button>
      </div>
    </div>

    <!-- Search mode toggle (products only) -->
    <div v-if="searchCategory === 'products' && !guidedMode" class="flex flex-wrap items-center gap-2 text-xs">
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
        {{ searchMode === 'like' ? t('Teiltext-Suche (enthält)') : searchMode === 'soundex' ? t('Ähnlich klingende Begriffe finden (Tippfehler-tolerant)') : t('Reguläre Ausdrücke für präzise Muster') }}
      </span>
    </div>

    <!-- Filter panel (products only) -->
    <transition name="slide">
      <div v-if="showAttributeFilters && searchCategory === 'products' && !guidedMode" class="pim-card p-4 space-y-4">
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
              <span class="text-[var(--color-text-primary)]">{{ localizedName(pt) || pt.technical_name }}</span>
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

        <!-- Tag filter -->
        <div v-if="authStore.hasPermission('tags.view')">
          <div class="flex items-center justify-between mb-2">
            <p class="text-[12px] font-medium text-[var(--color-text-secondary)]">
              Tags
              <span v-if="selectedTags.length > 0" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5 ml-1">
                {{ selectedTags.length }}
              </span>
            </p>
            <select v-if="selectedTags.length > 1" v-model="tagMatch" class="pim-select text-[10px] h-6">
              <option value="any">eines davon</option>
              <option value="all">alle</option>
            </select>
          </div>
          <div v-if="tagList.length === 0" class="border border-[var(--color-border)] rounded-lg p-3 text-center">
            <p class="text-xs text-[var(--color-text-tertiary)]">Keine Tags angelegt</p>
          </div>
          <div v-else class="max-h-36 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
            <label
              v-for="tag in tagList"
              :key="tag.id"
              class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
              :title="tag.technical_name"
            >
              <input
                type="checkbox"
                :checked="isTagSelected(tag.id)"
                @change="toggleTag(tag.id)"
                class="rounded border-[var(--color-border)]"
              />
              <span class="text-[var(--color-text-primary)]">{{ localizedName(tag) || tag.technical_name }}</span>
              <span v-if="tag.is_active === false" class="text-[10px] text-[var(--color-text-tertiary)] italic">inaktiv</span>
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
                  {{ localizedName(h) || h.technical_name }}
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
                  {{ localizedName(attr) || attr.technical_name }}
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
                <option v-for="lang in translationLanguages" :key="lang.code" :value="lang.code">{{ t(lang.label) }} ({{ lang.code }})</option>
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
              {{ liveCount.toLocaleString(numberLocale) }} Treffer
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
    <div v-if="hasSearched && !loading && !error && results.length > 0" class="text-xs text-[var(--color-text-tertiary)]" data-testid="search-results">
      {{ resultMeta.total === 1 ? t('{count} Ergebnis', { count: resultMeta.total }) : t('{count} Ergebnisse', { count: resultMeta.total }) }}
      <span v-if="searchMode === 'soundex'" class="ml-1 text-[var(--color-accent)]">(SOUNDEX)</span>
      <span v-if="searchMode === 'regex'" class="ml-1 text-[var(--color-accent)]">(REGEXP)</span>
    </div>

    <!-- Selection toolbar (products only) -->
    <div v-if="searchCategory === 'products' && selectedProductIds.length > 0" class="space-y-2">
      <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
        <span class="text-xs text-[var(--color-text-secondary)]">
          {{ t('{count} ausgewählt', { count: selectedProductIds.length }) }}{{ allPagesSelected ? ' ' + t('Produkte') : '' }}
        </span>

        <!-- Select all pages hint -->
        <template v-if="!allPagesSelected && selectedProductIds.length === results.length && resultMeta.total > results.length">
          <button
            class="pim-btn pim-btn-secondary text-xs border-dashed"
            :disabled="selectingAll"
            @click="selectAllPages"
          >
            <CheckCheck class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ selectingAll ? t('Lade...') : t('Alle {count} Produkte auswählen', { count: resultMeta.total.toLocaleString(numberLocale) }) }}
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
          <span class="hidden sm:inline">{{ bulkDeleting ? t('Lösche...') : t('Löschen') }}</span>
        </button>

        <button class="pim-btn pim-btn-ghost text-xs ml-auto" @click="clearAllSelection">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
          Auswahl aufheben
        </button>
      </div>

      <!-- Bulk delete confirm -->
      <div v-if="showConfirmBulkDelete" class="flex items-center gap-3 px-3 py-2 bg-[var(--color-error-light)] border border-[var(--color-error)]/20 rounded-lg">
        <span class="text-xs text-[var(--color-error)] font-medium">
          {{ selectedProductIds.length.toLocaleString(numberLocale) }} Produkte unwiderruflich löschen?
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
          {{ xliffExporting ? t('Export…') : t('Export') }}
        </button>
      </div>
    </div>

    <!-- Kachelansicht (nur für Produkte) -->
    <div v-if="viewMode === 'grid' && searchCategory === 'products' && results.length > 0">
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
        <div
          v-for="row in results"
          :key="row.id"
          class="pim-card overflow-hidden group cursor-pointer hover:shadow-md transition-shadow"
          @click="openResult(row)"
        >
          <div class="aspect-[4/3] bg-[var(--color-bg)] flex items-center justify-center overflow-hidden p-2">
            <img v-if="gridThumbs[row.id]" :src="gridThumbs[row.id]" class="w-full h-full object-contain" loading="lazy" alt="" />
            <Package v-else class="w-10 h-10 text-[var(--color-text-tertiary)]/20" :stroke-width="1.25" />
          </div>
          <div class="p-2.5 space-y-1">
            <div class="flex items-center gap-1.5">
              <button
                class="p-0.5 rounded hover:bg-[var(--color-bg)] shrink-0"
                :title="isOnWatchlist(row.id) ? t('Von Merkliste entfernen') : t('Zur Merkliste')"
                @click.stop="toggleWatchlist(row.id)"
              >
                <Star class="w-3.5 h-3.5" :class="isOnWatchlist(row.id) ? 'text-amber-500 fill-amber-500' : 'text-[var(--color-text-tertiary)]'" :stroke-width="2" />
              </button>
              <span class="text-[11px] font-mono text-[var(--color-text-secondary)]">{{ row.sku }}</span>
              <span
                class="ml-auto text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                :class="row.status === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : row.status === 'draft' ? 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' : 'bg-[var(--color-error-light)] text-[var(--color-error)]'"
              >
                {{ row.status === 'active' ? t('Aktiv') : row.status === 'draft' ? t('Entwurf') : row.status === 'inactive' ? t('Inaktiv') : t('Auslaufend') }}
              </span>
            </div>
            <p class="text-xs text-[var(--color-text-primary)] truncate font-medium">{{ row.name || '—' }}</p>
            <p class="text-[10px] text-[var(--color-text-tertiary)] truncate">{{ localizedName(row.product_type) }}</p>
          </div>
        </div>
      </div>
      <!-- Pagination -->
      <div class="flex items-center justify-between px-1 py-3">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ resultMeta.total }} Ergebnisse</span>
        <div class="flex items-center gap-1">
          <button class="pim-btn pim-btn-ghost text-xs" :disabled="resultMeta.current_page <= 1" @click="doSearch(resultMeta.current_page - 1)">Zurück</button>
          <span class="text-xs text-[var(--color-text-secondary)] px-2">{{ resultMeta.current_page }} / {{ resultMeta.last_page }}</span>
          <button class="pim-btn pim-btn-ghost text-xs" :disabled="resultMeta.current_page >= resultMeta.last_page" @click="doSearch(resultMeta.current_page + 1)">Weiter</button>
        </div>
      </div>
    </div>

    <!-- Listenansicht -->
    <PimTable
      ref="searchTableRef"
      v-if="results.length > 0 && (viewMode === 'list' || searchCategory !== 'products')"
      :columns="columns"
      :rows="results"
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
            :title="isOnWatchlist(row.id) ? t('Von Merkliste entfernen') : t('Zur Merkliste hinzufügen')"
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
      <template v-if="searchCategory === 'products'" v-slot:[`cell-product_type.name_de`]="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || t('Produkt') }}
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
          {{ value === 'active' ? t('Aktiv') : value === 'draft' ? t('Entwurf') : value === 'inactive' ? t('Inaktiv') : value === 'archived' ? t('Archiviert') : t('Auslaufend') }}
        </span>
      </template>

      <!-- Attribute-specific cells -->
      <template v-if="searchCategory === 'attributes'" #cell-data_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || '—' }}
        </span>
      </template>
      <template v-if="searchCategory === 'attributes'" v-slot:[`cell-attribute_type.name_de`]="{ value }">
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
          {{ value ? t('Aktiv') : t('Inaktiv') }}
        </span>
      </template>
      <template v-if="searchCategory === 'nodes'" #cell-name_de="{ row, value }">
        <div>
          <span class="text-sm">{{ value }}</span>
          <span v-if="row.hierarchy" class="ml-1.5 text-[10px] text-[var(--color-text-tertiary)]">
            ({{ localizedName(row.hierarchy) }})
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

      <!-- Content-specific cells -->
      <template v-if="searchCategory === 'content'" #cell-title="{ row, value }">
        <div>
          <span class="text-sm">{{ value }}</span>
          <span class="ml-1.5 text-[10px] font-mono text-[var(--color-text-tertiary)]">/{{ row.slug }}</span>
        </div>
      </template>
      <template v-if="searchCategory === 'content'" #cell-content_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || '—' }}
        </span>
      </template>
      <template v-if="searchCategory === 'content'" #cell-snippet="{ row }">
        <div v-if="row.snippet" class="max-w-[360px]">
          <span class="text-xs text-[var(--color-text-secondary)]" v-html="highlightSnippet(row.snippet, searchInput)" />
          <span v-if="row.matched_section_type" class="block text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
            {{ t('in „{type}“', { type: row.matched_section_type }) }}
          </span>
        </div>
        <span v-else-if="row.match_in === 'title'" class="text-[11px] text-[var(--color-text-tertiary)]">Treffer im Titel</span>
        <span v-else class="text-[11px] text-[var(--color-text-tertiary)]">—</span>
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

    <div v-else-if="!hasSearched && !isProductGuided" class="text-center py-16">
      <component :is="searchCategoryDefs.find(c => c.key === searchCategory)?.icon || Search" class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">
        {{ searchCategory === 'products' ? t('Filter konfigurieren und Suche starten') :
           searchCategory === 'attributes' ? t('Durchsuchbare Attribute finden') :
           searchCategory === 'nodes' ? t('Kategorieknoten durchsuchen (inkl. Unterkategorien)') :
           searchCategory === 'content' ? t('Content-Seiten nach Titel oder Seiteninhalt durchsuchen') :
           t('Medien durchsuchen') }}
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
                      {{ showDiffsOnly ? t('Keine Unterschiede gefunden — Produkte sind identisch') : t('Keine Daten zum Vergleichen') }}
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
