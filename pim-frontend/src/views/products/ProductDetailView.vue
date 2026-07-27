<script>
// Module-level cache — shared across all ProductDetailView instances,
// survives component unmount/remount (tab switching).
// Only cleared on full page reload.
const _refDataCache = {
  manufacturers: null,
  productTypes: null,
  projects: null,
  filterOptions: null,   // { views, types }
  workflowUsers: null,
  workflowTeams: null,
}
</script>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useTabStore } from '@/stores/tabs'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useToastStore } from '@/stores/toast'
import { useRecordNavigatorStore } from '@/stores/recordNavigator'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Save, Plus, Trash2, Image, Star, X, Search, Download, Languages, Copy, Sparkles, Tags, LayoutGrid, List, FileText, GitBranch, CheckCircle2, Eye, RotateCcw, ArrowRightLeft, RefreshCw, ChevronDown, ChevronLeft, ChevronRight, ChevronUp, ExternalLink, Filter, Upload, ClipboardList, Lightbulb, AlertTriangle, XCircle, Wand2, Images, Lock, FolderTree } from 'lucide-vue-next'
import productsApi from '@/api/products'
import projectsApi from '@/api/projects'
import usersApi from '@/api/users'
import mediaApi from '@/api/media'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import { priceTypes, relationTypes } from '@/api/prices'
import { priceRegions } from '@/api/priceRegions'
import attributesApiDefault, { productTypes, valueLists, attributeViews, attributeTypes } from '@/api/attributes'
import dictionaryApi from '@/api/dictionary'
import hierarchiesApi from '@/api/hierarchies'
import attributeMappingsApi from '@/api/attributeMappings'
import watchlistApi from '@/api/watchlist'
import searchProfilesApi from '@/api/searchProfiles'
import manufacturersApi from '@/api/manufacturers'
import { triggerDownload, blobErrorMessage } from '@/utils/download'
import PimCollectionGroup from '@/components/shared/PimCollectionGroup.vue'
import ProductNotesTab from '@/components/products/ProductNotesTab.vue'
import ProductConformanceTab from '@/components/products/ProductConformanceTab.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import PimMultipliableInput from '@/components/shared/PimMultipliableInput.vue'
import PimTable from '@/components/shared/PimTable.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import PdfPreview from '@/components/shared/PdfPreview.vue'
import PimCompositeModal from '@/components/shared/PimCompositeModal.vue'
import PimMultipliableComposite from '@/components/shared/PimMultipliableComposite.vue'
import ProductVersionsTab from '@/components/products/ProductVersionsTab.vue'
import ProductScheduledActionsTab from '@/components/products/ProductScheduledActionsTab.vue'
import MediaPickerDialog from '@/components/shared/MediaPickerDialog.vue'
import MotifPickerDialog from '@/components/shared/MotifPickerDialog.vue'
import ColumnConfigPopover from '@/components/shared/ColumnConfigPopover.vue'
import { useDragDrop } from '@/composables/useDragDrop'
import MediaUploadQueue from '@/components/media/MediaUploadQueue.vue'
import { useColumnConfig } from '@/composables/useColumnConfig'
import { formatCompositeSummary } from '@/utils/formatting'
import PdfTemplatePickerModal from '@/components/pdf-templates/PdfTemplatePickerModal.vue'
import MasterHierarchyNodePickerDialog from '@/components/products/MasterHierarchyNodePickerDialog.vue'
import OutputHierarchyNodeTree from '@/components/products/OutputHierarchyNodeTree.vue'

const route = useRoute()
const router = useRouter()
const store = useProductStore()
const tabStore = useTabStore()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const toastStore = useToastStore()
const recordNavigatorStore = useRecordNavigatorStore()
const { t } = useI18n()

// Navigator "Produkt X/Y" — nur sichtbar, wenn das Produkt aus einer Kontextliste
// (z.B. Merkliste) geöffnet wurde und dort mehr als ein Eintrag steht.
const recordNavIndex = computed(() => recordNavigatorStore.indexOf(route.params.id))
const recordNavTotal = computed(() => recordNavigatorStore.ids.length)
const hasRecordNav = computed(() => recordNavIndex.value !== -1 && recordNavTotal.value > 1)
const recordNavPrevId = computed(() => recordNavIndex.value > 0 ? recordNavigatorStore.ids[recordNavIndex.value - 1] : null)
const recordNavNextId = computed(() => recordNavIndex.value !== -1 && recordNavIndex.value < recordNavTotal.value - 1 ? recordNavigatorStore.ids[recordNavIndex.value + 1] : null)

function goToPrevRecord() {
  if (recordNavPrevId.value) router.push(`/products/${recordNavPrevId.value}`)
}

function goToNextRecord() {
  if (recordNavNextId.value) router.push(`/products/${recordNavNextId.value}`)
}

const activeTab = ref('base-data')
const activeAttrSubTab = ref('master')  // 'master' oder hierarchy_id
const moreMenuOpen = ref(false)         // Dropdown "Mehr" geöffnet?
const saving = ref(false)
const activeDataLang = ref(localeStore.activeDataLocales[0] || 'de')

// Offene Notizen-Zähler für Header-Badges { task: 2, error: 1, ... }
const noteOpenCounts = ref({})
const NOTE_BADGE_TYPES = [
  { key: 'error',   icon: XCircle,       bg: '#fee2e2', border: '#f87171', text: '#7f1d1d' },
  { key: 'warning', icon: AlertTriangle,  bg: '#ffedd5', border: '#fb923c', text: '#7c2d12' },
  { key: 'task',    icon: ClipboardList,  bg: '#dbeafe', border: '#60a5fa', text: '#1e3a5f' },
  { key: 'hint',    icon: Lightbulb,      bg: '#fef9c3', border: '#facc15', text: '#713f12' },
]
function onNoteCountsUpdated(counts) {
  noteOpenCounts.value = counts
}

// Generation counter — wird bei jedem Produktwechsel erhöht.
// Async-Funktionen prüfen nach await, ob die Generation noch aktuell ist,
// um veraltete Responses bei schnellem Produktwechsel zu verwerfen.
let _loadGeneration = 0

// Master-Hierarchie-Knoten-Zuordnung
const showMasterNodePicker = ref(false)

// Manufacturer assignment
const manufacturers = ref([])

async function loadManufacturers() {
  if (_refDataCache.manufacturers) {
    manufacturers.value = _refDataCache.manufacturers
    return
  }
  try {
    const { data } = await manufacturersApi.list({ perPage: 500 })
    manufacturers.value = data.data || data
    _refDataCache.manufacturers = manufacturers.value
  } catch { /* silently fail */ }
}

// Project assignment
const availableProjects = ref([])
const selectedProjectIds = ref([])

async function loadProjects() {
  if (_refDataCache.projects) {
    availableProjects.value = _refDataCache.projects
  } else {
    try {
      const { data } = await projectsApi.list({ perPage: 500 })
      availableProjects.value = data.data || data
      _refDataCache.projects = availableProjects.value
    } catch { /* silently fail */ }
  }
  // Initialize selected projects from current product
  if (product.value?.projects) {
    selectedProjectIds.value = product.value.projects.map(p => p.id)
  }
}

// Product type assignment
const productTypesList = ref([])

async function loadProductTypes() {
  if (_refDataCache.productTypes) {
    productTypesList.value = _refDataCache.productTypes
    return
  }
  try {
    const { data } = await productTypes.list()
    productTypesList.value = data.data || data
    _refDataCache.productTypes = productTypesList.value
  } catch { /* silently fail */ }
}

// Flache Liste aller verfügbaren Content-Tabs (Quelle für Sichtbarkeit,
// Reset-Logik und Lazy-Loading). Die GUI rendert daraus gruppierte Reiter
// (siehe navGroups), die Content-Blöcke schalten weiterhin über activeTab.
// Attribut-Sichten, die für den Produkttyp des aktuellen Produkts gelten.
// Leere allowed_product_type_ids = für alle Produkttypen gültig (Default);
// gefüllt = nur wenn die product_type_id enthalten ist. Steuert sowohl die
// dynamischen Sicht-Tabs als auch den Sicht-Auswahlfilter im Attribute-Tab.
const productTypeAttrViews = computed(() => {
  const typeId = product.value?.product_type_id
  return availableAttrViews.value.filter(v =>
    !v.allowed_product_type_ids?.length || v.allowed_product_type_ids.includes(typeId)
  )
})

// Attribut-Sichten, die als eigener Tab im Produkteditor aktiviert wurden
// (per "Eigener Tab im Produkteditor"-Option in der Attribut-Sicht-Verwaltung),
// sortiert nach der Sicht-eigenen Sortierung (dieselbe, die auch die Sichten-Liste ordnet).
const attributeViewTabs = computed(() =>
  productTypeAttrViews.value
    .filter(v => v.show_as_tab)
    .slice()
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    .map(v => ({ key: v.tab_key, label: v.name_de || v.technical_name }))
)

// Ist der aktive Tab einer der dynamischen Attribut-Sicht-Tabs? Über die tab_key-Liste aus der
// API geprüft, statt das "attribute-view:"-Präfix im Frontend erneut zu bilden.
// Ebenfalls produkttyp-gefiltert, damit eine für den Typ unzulässige Sicht nicht
// (z.B. über einen Deeplink) doch als erzwungene Sicht greift.
const activeAttributeView = computed(() =>
  productTypeAttrViews.value.find(v => v.show_as_tab && v.tab_key === activeTab.value) || null
)
const activeAttributeViewId = computed(() => activeAttributeView.value?.id || null)

const tabs = computed(() => {
  const pt = product.value?.product_type
  const base = [
    { key: 'base-data', label: 'Grunddaten' },
    { key: 'attributes', label: t('product.attributes') },
    ...attributeViewTabs.value,
  ]
  if (!pt || pt.has_variants) {
    base.push({ key: 'variant-attributes', label: 'Varianten-Attribute' })
    base.push({ key: 'variants', label: t('product.variants') })
  }
  if (!pt || pt.has_media) {
    base.push({ key: 'media', label: t('product.media') })
  }
  if (!pt || pt.has_prices) {
    base.push({ key: 'prices', label: t('product.prices') })
  }
  base.push(
    { key: 'relations', label: t('product.relations') },
  )
  // Produkttypen mit aktivierter Cluster-Vererbung: Klammer-Produkt mit
  // dynamisch aufgelösten Mitgliedern (Suchprofil/PQL/Merkliste) plus
  // Attribut-/Medien-Vererbung an diese Mitglieder.
  if (pt?.has_dynamic_cluster) {
    base.push({ key: 'virtual-cluster', label: 'Cluster-Vererbung' })
  }
  base.push(
    { key: 'output-hierarchies', label: 'Ausgabehierarchien' },
    { key: 'conformance', label: 'Konformität' },
    { key: 'notes', label: 'Notizen' },
    { key: 'preview', label: 'Export' },
    { key: 'versions', label: t('product.versions') },
    { key: 'scheduled-actions', label: 'Planung' },
  )
  if (workflowEnabled.value) {
    base.push({ key: 'workflow-history', label: 'Workflow-Verlauf' })
  }
  // Filter out tabs the user's role has set to 'hidden'
  return base.filter(tab => authStore.getTabAccess(tab.key) !== 'hidden')
})

/** Whether the currently active tab is read-only for this user's role. */
const isTabReadOnly = computed(() => authStore.getTabAccess(activeTab.value) === 'read')

// ─── Gruppierte Tab-Navigation ────────────────────────
// Content-Keys, die zur Varianten-Gruppe bzw. zum "Mehr"-Dropdown gehören.
const VARIANT_TAB_KEYS = ['variant-attributes', 'variants']
const MORE_TAB_KEYS = ['notes', 'preview', 'versions', 'scheduled-actions', 'workflow-history']

/** Liefert das Label eines Content-Tabs aus der flachen Liste. */
function tabLabel(key) {
  return tabs.value.find(t => t.key === key)?.label || key
}

/**
 * Baut die sichtbare Reiter-Struktur auf:
 *  - leaf:     einzelner Reiter
 *  - subtabs:  Gruppe "Varianten" mit zweiter Tab-Zeile
 *  - dropdown: Gruppe "Mehr" mit Klapp-Menü
 * Rollen-Sichtbarkeit wird über die flache tabs-Liste bereits gefiltert.
 */
const navGroups = computed(() => {
  const available = new Set(tabs.value.map(t => t.key))
  const has = (k) => available.has(k)
  const leaf = (key) => ({ type: 'leaf', key, label: tabLabel(key) })
  const groups = []

  if (has('base-data')) groups.push(leaf('base-data'))
  if (has('attributes')) groups.push(leaf('attributes'))
  for (const viewTab of attributeViewTabs.value) {
    if (has(viewTab.key)) groups.push(leaf(viewTab.key))
  }

  // Varianten-Attribute + Varianten zu einer Gruppe mit Sub-Tabs zusammenfassen
  const variantChildren = []
  if (has('variant-attributes')) variantChildren.push({ key: 'variant-attributes', label: 'Attribute' })
  if (has('variants')) variantChildren.push({ key: 'variants', label: 'Liste' })
  if (variantChildren.length) {
    groups.push({ type: 'subtabs', key: 'variants-group', label: t('product.variants'), children: variantChildren })
  }

  if (has('media')) groups.push(leaf('media'))
  if (has('prices')) groups.push(leaf('prices'))
  if (has('relations')) groups.push(leaf('relations'))
  if (has('virtual-cluster')) groups.push(leaf('virtual-cluster'))
  if (has('output-hierarchies')) groups.push(leaf('output-hierarchies'))
  if (has('conformance')) groups.push(leaf('conformance'))

  // Selten genutzte Verwaltungs-Tabs in "Mehr" bündeln
  const moreChildren = MORE_TAB_KEYS.filter(has).map(k => ({ key: k, label: tabLabel(k) }))
  if (moreChildren.length) {
    groups.push({ type: 'dropdown', key: 'more', label: 'Mehr', children: moreChildren })
  }

  return groups
})

/** Sub-Tabs der Varianten-Gruppe (für die zweite Tab-Zeile). */
const variantSubTabs = computed(() =>
  navGroups.value.find(g => g.key === 'variants-group')?.children || []
)

// "Mehr"-Dropdown wird außerhalb des scrollenden <nav> gerendert, damit das
// Klapp-Menü nicht vom overflow-x-auto des Containers abgeschnitten wird.
const moreGroup = computed(() => navGroups.value.find(g => g.type === 'dropdown') || null)
const navMainGroups = computed(() => navGroups.value.filter(g => g.type !== 'dropdown'))

const isVariantTabActive = computed(() => VARIANT_TAB_KEYS.includes(activeTab.value))
const isMoreTabActive = computed(() => MORE_TAB_KEYS.includes(activeTab.value))

/** CSS-Klassen für einen Top-Level-Reiter. */
function tabBtnClass(active) {
  return [
    'group relative px-4 py-2.5 text-[13px] font-medium border-b-2 transition-all duration-150 whitespace-nowrap inline-flex items-center rounded-t-md',
    active
      ? 'border-[var(--color-accent)] text-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_7%,transparent)]'
      : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-bg)]',
  ]
}

/** Reiter aktivieren und ggf. das "Mehr"-Menü schließen. */
function selectTab(key) {
  activeTab.value = key
  moreMenuOpen.value = false
}

/** Klick auf die "Varianten"-Gruppe → ersten verfügbaren Sub-Tab öffnen. */
function openVariantGroup() {
  if (!isVariantTabActive.value) {
    const first = variantSubTabs.value[0]?.key
    if (first) selectTab(first)
  }
}

/** Öffnet die öffentliche Katalog-Vorschau des Produkts in neuem Tab.
 *  Wir öffnen den Katalog mit der SKU als Suchparameter (?search=…), statt den
 *  Produkt-Deeplink zu nutzen — robuster und an anderer Stelle wiederverwendbar. */
function openPreview() {
  const term = product.value?.sku || product.value?.name
  if (!term) return
  const url = router.resolve({ name: 'catalog', query: { search: term } }).href
  window.open(url, '_blank')
}

// ─── Attribute Filters ──────────────────────────────────
const attrFilterSearch = ref('')
const attrFilterView = ref(null)
const attrFilterGroup = ref(null)
const attrFilterMandatory = ref(false)
const attrFilterFilledOnly = ref(false)
const availableAttrViews = ref([])
const availableAttrGroups = ref([])
const filterOptionsLoaded = ref(false)

async function loadFilterOptions() {
  if (filterOptionsLoaded.value) return
  if (_refDataCache.filterOptions) {
    availableAttrViews.value = _refDataCache.filterOptions.views
    availableAttrGroups.value = _refDataCache.filterOptions.types
    filterOptionsLoaded.value = true
    return
  }
  try {
    const [viewsRes, typesRes] = await Promise.all([
      attributeViews.list({ include: 'attributes', perPage: 500 }),
      attributeTypes.list(),
    ])
    availableAttrViews.value = viewsRes.data.data || viewsRes.data || []
    availableAttrGroups.value = typesRes.data.data || typesRes.data || []
    _refDataCache.filterOptions = { views: availableAttrViews.value, types: availableAttrGroups.value }
    filterOptionsLoaded.value = true
  } catch (e) {
    console.error('Failed to load filter options:', e)
  }
}

const product = computed(() => store.current)

// ─── Workflow (dynamic FK-based) ─────────────────────
const workflowUsers = ref([])
const workflowTeams = ref([])
const workflowSaving = ref(false)
const availableTransitions = ref([])

const workflowEnabled = computed(() => !!product.value?.workflow_id)
const productTypeHasWorkflow = computed(() => !!product.value?.product_type?.workflow_id)

async function startWorkflow() {
  if (!product.value?.product_type?.workflow_id) return
  workflowSaving.value = true
  workflowError.value = null
  try {
    await store.update(product.value.id, { workflow_id: product.value.product_type.workflow_id })
    await store.fetchOne(product.value.id)
    await loadAvailableTransitions()
    loadWorkflowUsers()
    loadWorkflowTeams()
  } catch (e) {
    workflowError.value = e.response?.data?.message || 'Workflow konnte nicht gestartet werden'
  } finally {
    workflowSaving.value = false
  }
}

async function loadWorkflowUsers() {
  if (_refDataCache.workflowUsers) {
    workflowUsers.value = _refDataCache.workflowUsers
    return
  }
  try {
    const { data } = await usersApi.list({ perPage: 200 })
    workflowUsers.value = data.data || data
    _refDataCache.workflowUsers = workflowUsers.value
  } catch { /* ignore */ }
}

async function loadWorkflowTeams() {
  if (_refDataCache.workflowTeams) {
    workflowTeams.value = _refDataCache.workflowTeams
    return
  }
  try {
    const teamsApi = (await import('@/api/teams')).default
    const { data } = await teamsApi.list({ perPage: 200 })
    workflowTeams.value = data.data || data
    _refDataCache.workflowTeams = workflowTeams.value
  } catch { /* ignore */ }
}

async function loadAvailableTransitions() {
  if (!product.value?.workflow_id) {
    availableTransitions.value = []
    return
  }
  try {
    const { data } = await productsApi.getAvailableTransitions(product.value.id)
    availableTransitions.value = data.data || []
  } catch {
    availableTransitions.value = []
  }
}

const workflowError = ref(null)

async function transitionTo(toStatusId) {
  if (!product.value) return
  workflowSaving.value = true
  workflowError.value = null
  try {
    await store.update(product.value.id, { current_workflow_status_id: toStatusId })
    await store.fetchOne(product.value.id)
    await loadAvailableTransitions()
  } catch (e) {
    workflowError.value = e.response?.data?.message || 'Workflow-Aktion fehlgeschlagen'
  } finally {
    workflowSaving.value = false
  }
}

async function updateWorkflowAssignee(userId) {
  if (!product.value) return
  workflowSaving.value = true
  try {
    await store.update(product.value.id, { workflow_assignee_id: userId || null })
    await store.fetchOne(product.value.id)
  } catch { /* ignore */ }
  finally { workflowSaving.value = false }
}

async function updateWorkflowTeam(teamId) {
  if (!product.value) return
  workflowSaving.value = true
  try {
    await store.update(product.value.id, { workflow_team_id: teamId || null })
    await store.fetchOne(product.value.id)
  } catch { /* ignore */ }
  finally { workflowSaving.value = false }
}

// ─── Workflow History ─────────────────────────────────
const workflowHistory = ref([])
const workflowHistoryLoaded = ref(false)

async function loadWorkflowHistory() {
  if (workflowHistoryLoaded.value || !product.value) return
  try {
    const { data } = await productsApi.getWorkflowHistory(product.value.id)
    workflowHistory.value = data.data || []
    workflowHistoryLoaded.value = true
  } catch (e) {
    console.error('Failed to load workflow history:', e)
  }
}

async function cancelWorkflow() {
  if (!product.value) return
  workflowSaving.value = true
  workflowError.value = null
  try {
    await store.update(product.value.id, {
      current_workflow_status_id: null,
      workflow_assignee_id: null,
      workflow_team_id: null,
    })
    await store.fetchOne(product.value.id)
    availableTransitions.value = []
    await loadAvailableTransitions()
  } catch (e) {
    workflowError.value = e.response?.data?.message || 'Workflow-Aktion fehlgeschlagen'
  } finally {
    workflowSaving.value = false
  }
}

// Basiert auf dem vom Backend aufgelösten master_hierarchy_node (siehe include=
// masterHierarchyNode.hierarchy in stores/products.js) statt einer lokal
// zusammengestellten Baum-Liste — zeigt dadurch auch nachträglich zugeordnete
// Knoten zuverlässig an, unabhängig davon, ob deren Hierarchie-Baum lokal geladen ist.
const masterNodePath = computed(() => {
  const node = product.value?.master_hierarchy_node
  if (!node) return null
  const hierarchyName = node.hierarchy?.name_de || node.hierarchy?.name_en || ''
  const nodeName = node.name_de || node.name_en || node.id
  return hierarchyName ? `${hierarchyName} › ${nodeName}` : nodeName
})

const masterNodeName = computed(() => {
  const node = product.value?.master_hierarchy_node
  return node ? (node.name_de || node.name_en || node.id) : null
})

function openMasterNodePicker() {
  showMasterNodePicker.value = true
}

function onMasterNodeSelected(selection) {
  product.value.master_hierarchy_node_id = selection.id
  product.value.master_hierarchy_node = {
    id: selection.id,
    name_de: selection.name_de,
    name_en: selection.name_en,
    hierarchy: selection.hierarchy,
  }
}

function clearMasterNode() {
  product.value.master_hierarchy_node_id = null
  product.value.master_hierarchy_node = null
}

// ─── Attribute Values ─────────────────────────────────
const schema = ref(null)
const attributeValues = ref({})       // non-translatable: { attrId: value }
const translatedValues = ref({})      // translatable: { `${attrId}_${lang}`: value }
const multipliableValues = ref({})    // multipliable: { attrId: [{ value, multiplied_index }, ...] }
const multipliableCompositeValues = ref({})  // multipliable composites: { attrId: [{ multiplied_index, children: { childId: value } }] }
const unitValues = ref({})            // unit per attribute: { attrId: unitId }
const formattedValues = ref({})       // read-only Formatierungsregel-Vorschau (Fallback-Pfad ohne Hierarchie): { attrId: formattedValue }
const comparisonOperatorValues = ref({})  // comparison operator per attribute: { attrId: operatorId }
const attrLoaded = ref(false)
const valueListMap = ref({})
const dictionaryEntries = ref([])

// ─── Composite Modal State ────────────────────────────
const compositeModalOpen = ref(false)
const activeComposite = ref(null)

function openCompositeModal(compositeAttr) {
  activeComposite.value = compositeAttr
  compositeModalOpen.value = true
}

function onCompositeValuesUpdate(newValues) {
  for (const [childId, value] of Object.entries(newValues)) {
    if (value && typeof value === 'object' && value.data_type === 'Composite') {
      // Sub-Composite: Enkel-Werte flach speichern
      for (const [gcId, gcValue] of Object.entries(value.children || {})) {
        attributeValues.value[gcId] = gcValue
      }
    } else {
      attributeValues.value[childId] = value
    }
  }
}

function getCompositeSummary(compositeAttr) {
  return formatCompositeSummary({
    compositeFormat: compositeAttr.composite_format,
    children: compositeAttr._children || [],
    getValue: c => attributeValues.value[c.id],
  })
}

function mapDataTypeToInput(backendType) {
  const map = {
    'String': 'text', 'Number': 'number', 'Float': 'decimal',
    'Date': 'date', 'Flag': 'boolean', 'Selection': 'select', 'MultiSelection': 'multicombobox',
    'Dictionary': 'dictionary', 'Composite': 'composite', 'RichText': 'richtext', 'Textarea': 'textarea',
    'Hyperlink': 'hyperlink', 'ImageLink': 'imagelink', 'PdfLink': 'pdflink', 'VideoLink': 'videolink',
    'DelimitedValue': 'delimitedvalue', 'JsonArtefact': 'jsonartefact',
    // Freie Selects nutzen die bestehenden Select-Inputs (Optionen aus simple_options)
    'SimpleSelect': 'select', 'SimpleMultiSelect': 'multicombobox',
    // Referenz-Typen: eigene Picker-Zweige in PimAttributeInput
    'HierarchyNodeReference': 'hierarchyreference', 'ProductReference': 'productreference',
  }
  return map[backendType] || 'text'
}

function isMultiValueType(dataType) {
  return dataType === 'MultiSelection' || dataType === 'SimpleMultiSelect'
}

function parseSimpleOption(opt) {
  // Syntax "Label::value" → gespeichert wird value, angezeigt "Label (value)".
  // Ohne "::" ist Label = value (unveränderte Anzeige).
  const raw = String(opt)
  const idx = raw.indexOf('::')
  if (idx === -1) {
    return { value: raw, label: raw }
  }
  const label = raw.slice(0, idx).trim()
  const value = raw.slice(idx + 2).trim()
  return { value, label: label ? `${label} (${value})` : value }
}

function getSelectionOptions(attr) {
  // Freie Selects: Optionen direkt aus simple_options (kein Werteliste-Lookup)
  if (attr.data_type === 'SimpleSelect' || attr.data_type === 'SimpleMultiSelect') {
    return (attr.simple_options || []).map(parseSimpleOption)
  }
  // Try embedded value_list entries first (from attribute API with include)
  if (attr.value_list?.entries?.length) {
    return attr.value_list.entries.map(e => ({ value: e.id, label: e.display_value_de || e.value_de || e.label_de || e.code || e.technical_name }))
  }
  // Fallback to valueListMap (loaded separately for resolved attributes)
  const vlId = attr.value_list_id
  if (vlId && valueListMap.value[vlId]?.entries?.length) {
    return valueListMap.value[vlId].entries.map(e => ({ value: e.id, label: e.display_value_de || e.value_de || e.label_de || e.code || e.technical_name }))
  }
  return []
}

async function loadAttributeData(overrideNodeId = null, generation = null) {
  if (attrLoaded.value || !product.value) return
  const gen = generation ?? _loadGeneration
  try {
    // Ob dieser Produkttyp zusätzlich freie Attribute erlaubt (unabhängig
    // von einer evtl. Cluster-Vererbung oder Hierarchie-Zuordnung — siehe
    // Attribut-Picker im Template).
    const allowsFreeAttributes = !!product.value.product_type?.allows_free_attributes

    // Try resolved attributes from hierarchy first (includes inheritance info)
    let resolvedAttrs = null
    const nodeId = overrideNodeId || product.value.master_hierarchy_node_id
    if (nodeId) {
      try {
        const { data: resolvedData } = await productsApi.getResolvedAttributes(product.value.id, nodeId)
        if (gen !== _loadGeneration) return // Produktwechsel — veraltete Response verwerfen
        resolvedAttrs = resolvedData.data || resolvedData
      } catch (e) { console.warn('Resolved attributes unavailable, falling back to schema:', e.message) }
    }

    if (resolvedAttrs && resolvedAttrs.length > 0) {
      // Use hierarchy-resolved attributes as schema
      schema.value = resolvedAttrs.map(ra => ({
        id: ra.attribute_id,
        technical_name: ra.attribute_technical_name,
        name_de: ra.attribute_name_de || ra.attribute_technical_name,
        name_en: ra.attribute_name_en,
        data_type: ra.data_type,
        value_list_id: ra.value_list_id || null,
        simple_options: ra.simple_options || null,
        delimiter: ra.delimiter || null,
        is_mandatory: !!(ra.is_mandatory || ra.is_required),
        is_translatable: ra.is_translatable,
        is_variant_attribute: ra.is_variant_attribute || false,
        is_multipliable: ra.is_multipliable || false,
        is_primary: ra.is_primary || false,
        max_multiplied: ra.max_multiplied || null,
        attribute_type_id: ra.attribute_type_id || null,
        parent_attribute_id: ra.parent_attribute_id || null,
        composite_format: ra.composite_format || null,
        unit_group: ra.unit_group || null,
        comparison_operators: ra.comparison_operators || null,
        formatted_value: ra.formatted_value ?? null,
        group: ra.collection_name || 'Vererbte Attribute',
        _source: ra.source,
        _is_inherited: ra.is_inherited,
        _access: ra.access_product,
      }))
      // Populate values from resolved data (primary language)
      for (const ra of resolvedAttrs) {
        // Einheit setzen (gespeicherte unit_id oder default)
        if (ra.unit_id) {
          unitValues.value[ra.attribute_id] = ra.unit_id
        }
        // Vergleichsoperator setzen
        if (ra.comparison_operator_id) {
          comparisonOperatorValues.value[ra.attribute_id] = ra.comparison_operator_id
        }
        if (ra.is_multipliable && ra.data_type === 'Composite' && ra.multiplied_composites) {
          // Vermehrbares Composite: Array von Instanzen mit Kind-Werten
          multipliableCompositeValues.value[ra.attribute_id] = ra.multiplied_composites.length > 0
            ? ra.multiplied_composites.map(mc => ({
                multiplied_index: mc.multiplied_index,
                children: mc.children || {},
              }))
            : [{ multiplied_index: 0, children: {} }]
        } else if (ra.is_multipliable && ra.multiplied_values) {
          // Multipliable: store array of { value, multiplied_index, unit_id }
          multipliableValues.value[ra.attribute_id] = ra.multiplied_values.length > 0
            ? ra.multiplied_values.map(mv => ({ value: mv.value, multiplied_index: mv.multiplied_index, unit_id: mv.unit_id || ra.unit_id || null }))
            : [{ value: null, multiplied_index: 0, unit_id: ra.unit_id || null }]
        } else if (ra.value !== null && ra.value !== undefined) {
          if (ra.is_translatable) {
            const lang = activeDataLang.value || 'de'
            translatedValues.value[`${ra.attribute_id}_${lang}`] = ra.value
          } else {
            attributeValues.value[ra.attribute_id] = ra.value
          }
        }
      }
    } else {
      // Fallback: load attribute values + product type schema
      const langs = localeStore.activeDataLocales.join(',')
      const { data: valData } = await productsApi.getAttributeValues(product.value.id, { lang: langs })
      if (gen !== _loadGeneration) return
      const vals = valData.data || valData
      const ownAttributesById = new Map()
      if (Array.isArray(vals)) {
        for (const val of vals) {
          const attrId = val.attribute_id || val.attribute?.id
          if (!attrId) continue
          if (val.attribute && !ownAttributesById.has(attrId)) {
            ownAttributesById.set(attrId, val.attribute)
          }
          let rawValue = val.value_string ?? val.value_number ?? val.value_date ?? val.value_flag ?? val.value_selection_id ?? ''
          // Mehrfachauswahl (MultiSelection/SimpleMultiSelect): JSON-Array-String → Array,
          // damit die multicombobox-Eingabe die gespeicherten Werte korrekt anzeigt und
          // beim Bearbeiten nicht zeichenweise korrumpiert.
          if (isMultiValueType(val.attribute?.data_type) && typeof rawValue === 'string' && rawValue.startsWith('[')) {
            try { const parsed = JSON.parse(rawValue); if (Array.isArray(parsed)) rawValue = parsed } catch { /* roh belassen */ }
          }
          const mIdx = val.multiplied_index ?? 0
          if (mIdx > 0 || (val.attribute && val.attribute.is_multipliable)) {
            // Multipliable value — collect into array
            if (!multipliableValues.value[attrId]) {
              multipliableValues.value[attrId] = []
            }
            multipliableValues.value[attrId].push({ value: rawValue, multiplied_index: mIdx })
          } else if (val.language) {
            translatedValues.value[`${attrId}_${val.language}`] = rawValue
          } else {
            attributeValues.value[attrId] = rawValue
          }
          if (val.formatted_value) {
            formattedValues.value[attrId] = val.formatted_value
          }
        }
      }
      if (product.value.product_type_id) {
        try {
          const { data: schemaData } = await productTypes.getSchema(product.value.product_type_id)
          if (gen !== _loadGeneration) return
          schema.value = schemaData.data || schemaData
        } catch (e) { console.warn('Product type schema not found:', e.message) }
      }
      // Kein Produkttyp-Schema ermittelbar (z.B. Klammer-Produkt ohne eigenes
      // Schema) und freie Attribute erlaubt: auf die bereits befüllten
      // Attribute zurückfallen, damit nichts verloren geht — weitere können
      // über den Attribut-Picker im Template ergänzt werden.
      if (allowsFreeAttributes && (!schema.value || schema.value.length === 0)) {
        schema.value = [...ownAttributesById.values()]
      }
    }

    // Load translated values for all active data languages
    if (schema.value) {
      const translatableAttrs = (Array.isArray(schema.value) ? schema.value : []).filter(a => a.is_translatable)
      if (translatableAttrs.length > 0) {
        const langs = localeStore.activeDataLocales.join(',')
        try {
          const { data: tvData } = await productsApi.getAttributeValues(product.value.id, { lang: langs })
          if (gen !== _loadGeneration) return
          const tvVals = tvData.data || tvData
          if (Array.isArray(tvVals)) {
            for (const val of tvVals) {
              const attrId = val.attribute_id || val.attribute?.id
              if (!attrId || !val.language) continue
              const rawValue = val.value_string ?? val.value_number ?? val.value_date ?? val.value_flag ?? val.value_selection_id ?? ''
              translatedValues.value[`${attrId}_${val.language}`] = rawValue
            }
          }
        } catch (e) { console.warn('Failed to load translated values:', e.message) }
      }
    }

    attrLoaded.value = true

    // Load value lists for Selection-type attributes
    const selectionAttrs = (Array.isArray(schema.value) ? schema.value : schema.value?.attributes || [])
      .filter(a => a.data_type === 'Selection' && a.value_list_id)
    if (selectionAttrs.length > 0) {
      try {
        const { data: vlData } = await valueLists.list({ include: 'entries', perPage: 200 })
        const allLists = vlData.data || vlData
        const map = {}
        for (const vl of allLists) {
          map[vl.id] = vl
        }
        valueListMap.value = map
      } catch (e) { console.error('Failed to load value lists:', e.message) }
    }

    // Load dictionary entries for Dictionary-type attributes
    const dictAttrs = (Array.isArray(schema.value) ? schema.value : schema.value?.attributes || [])
      .filter(a => a.data_type === 'Dictionary')
    if (dictAttrs.length > 0) {
      try {
        const { data: dictData } = await dictionaryApi.list({ perPage: 1000 })
        dictionaryEntries.value = (dictData.data || dictData).map(e => ({
          value: e.id,
          label: e.short_text_de || e.short_text_en || e.category || String(e.id),
        }))
      } catch (e) { console.error('Failed to load dictionary entries:', e.message) }
    }
  } catch (e) { console.error('Failed to load attribute data:', e.message) }
}

// ─── Freier Attribut-Picker (Produkttyp erlaubt freie Attribute) ────
// Zusätzlich zu Hierarchie-/Schema-Attributen kann hier jedes beliebige
// Attribut aus dem Katalog hinzugefügt werden (analog zum Attribut-Picker
// der Produktbeziehungen, siehe relationAttrList).
const virtualAttributeCatalog = ref([])
const virtualAttributeCatalogLoaded = ref(false)
const virtualAttributePicker = ref({ attribute_id: '' })

async function loadVirtualAttributeCatalog() {
  if (virtualAttributeCatalogLoaded.value) return
  try {
    const { data } = await attributesApiDefault.list({ perPage: 9999 })
    virtualAttributeCatalog.value = data.data || data
    virtualAttributeCatalogLoaded.value = true
  } catch (e) { console.error('Failed to load attribute catalog:', e.message) }
}

const virtualAttributeCatalogOptions = computed(() => {
  const usedIds = new Set(schemaAttributes.value.map(a => a.id))
  return virtualAttributeCatalog.value
    .filter(a => !usedIds.has(a.id))
    .map(a => ({ value: a.id, label: a.name_de || a.technical_name }))
})

function addVirtualAttribute() {
  const attrId = virtualAttributePicker.value.attribute_id
  if (!attrId) return
  const attr = virtualAttributeCatalog.value.find(a => a.id === attrId)
  if (!attr || schema.value?.some?.(a => a.id === attrId)) return
  schema.value = [...(Array.isArray(schema.value) ? schema.value : []), attr]
  virtualAttributePicker.value.attribute_id = ''
}

const schemaAttributes = computed(() => {
  if (!schema.value) return []
  // Schema may have attributes directly or grouped
  if (Array.isArray(schema.value.attributes)) return schema.value.attributes
  if (Array.isArray(schema.value)) return schema.value
  return []
})

// Primärattribute: im Stammdaten-Bereich des Produkteditors angezeigt
const primaryAttributes = computed(() => {
  return schemaAttributes.value.filter(a => a.is_primary && !a.is_hidden && !a.is_variant_attribute)
})

const attributeGroups = computed(() => {
  let allAttrs = schemaAttributes.value.filter(a => !a.is_variant_attribute)
  // Hide internal attributes for non-admin users
  if (authStore.userRole !== 'Admin') {
    allAttrs = allAttrs.filter(a => !a.is_internal)
  }
  if (allAttrs.length === 0) return []

  // Collect IDs of all composite attributes in the schema
  const compositeIds = new Set(allAttrs.filter(a => a.data_type === 'Composite').map(a => a.id))

  // Filter out child attributes whose parent composite is also in the schema
  // (they will only appear inside the composite modal/inline editor)
  // Auch Enkel-Attribute (Kinder von Sub-Composites) herausfiltern
  const attrs = allAttrs.filter(a => {
    if (a.parent_attribute_id && compositeIds.has(a.parent_attribute_id)) return false
    return true
  })

  // Enrich composite attributes with their children for the modal
  // Rekursiv: Sub-Composites erhalten ebenfalls _children (Tiefe 2)
  for (const attr of attrs) {
    if (attr.data_type === 'Composite') {
      attr._children = allAttrs.filter(c => c.parent_attribute_id === attr.id).map(child => {
        if (child.data_type === 'Composite') {
          // Sub-Composite: Enkel-Attribute anhängen
          return { ...child, _children: allAttrs.filter(gc => gc.parent_attribute_id === child.id) }
        }
        return child
      })
    }
  }

  const groups = {}
  for (const attr of attrs) {
    const groupName = attr.attribute_type?.name_de || attr.group || 'Weitere Attribute'
    if (!groups[groupName]) groups[groupName] = []
    groups[groupName].push(attr)
  }
  return Object.entries(groups).map(([name, attributes]) => ({ name, attributes }))
})

const variantAttributeGroups = computed(() => {
  let attrs = schemaAttributes.value.filter(a => a.is_variant_attribute)
  // Hide internal attributes for non-admin users
  if (authStore.userRole !== 'Admin') {
    attrs = attrs.filter(a => !a.is_internal)
  }
  if (attrs.length === 0) return []
  const groups = {}
  for (const attr of attrs) {
    const groupName = attr.attribute_type?.name_de || attr.group || 'Varianten-Attribute'
    if (!groups[groupName]) groups[groupName] = []
    groups[groupName].push(attr)
  }
  return Object.entries(groups).map(([name, attributes]) => ({ name, attributes }))
})

// ─── Attribut-Werte für Suche & "Nur gefüllt"-Filter ─────
// Liefert {attr, value}-Paare für ein Attribut. Bei Composite-/Cluster-
// Attributen werden rekursiv die Werte der Kind-Attribute berücksichtigt,
// da der Wert eines Composites nicht am Composite selbst, sondern an
// dessen Kindern hängt.
function getAttributeValueEntries(attr) {
  if (attr.data_type === 'Composite') {
    if (attr.is_multipliable) {
      const instances = multipliableCompositeValues.value[attr.id] || []
      return instances.flatMap(inst =>
        (attr._children || []).map(child => ({ attr: child, value: inst.children?.[child.id] })),
      )
    }
    return (attr._children || []).flatMap(child => getAttributeValueEntries(child))
  }
  if (attr.is_multipliable) {
    return (multipliableValues.value[attr.id] || []).map(v => ({ attr, value: v.value }))
  }
  if (attr.is_translatable) {
    return localeStore.activeDataLocales.map(loc => ({ attr, value: translatedValues.value[`${attr.id}_${loc}`] }))
  }
  return [{ attr, value: attributeValues.value[attr.id] }]
}

function isValueFilled(v) {
  if (v === null || v === undefined) return false
  if (typeof v === 'string') return v.trim() !== ''
  if (Array.isArray(v)) return v.length > 0
  return true
}

function isAttributeFilled(attr) {
  return getAttributeValueEntries(attr).some(e => isValueFilled(e.value))
}

function attributeValueToSearchText(attr, value) {
  if (value === null || value === undefined || value === '') return ''
  if (['Selection', 'MultiSelection'].includes(attr.data_type)) {
    const options = getSelectionOptions(attr)
    const ids = Array.isArray(value) ? value : [value]
    return ids.map(id => options.find(o => o.value === id)?.label || String(id)).join(' ')
  }
  if (attr.data_type === 'Dictionary') {
    const entry = dictionaryEntries.value.find(d => d.id === value)
    return entry?.value_de || entry?.label_de || String(value)
  }
  if (typeof value === 'boolean') return value ? 'ja' : 'nein'
  return String(value)
}

function getAttributeValueSearchText(attr) {
  return getAttributeValueEntries(attr)
    .map(e => attributeValueToSearchText(e.attr, e.value))
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
}

// ─── Filtered Attributes (flat list for Attribute tab) ──
const filteredAttributes = computed(() => {
  const allAttrs = schemaAttributes.value.filter(a => !a.is_variant_attribute)
  if (allAttrs.length === 0) return []

  const compositeIds = new Set(allAttrs.filter(a => a.data_type === 'Composite').map(a => a.id))
  let attrs = allAttrs.filter(a => !a.parent_attribute_id || !compositeIds.has(a.parent_attribute_id))

  for (const attr of attrs) {
    if (attr.data_type === 'Composite') {
      attr._children = allAttrs.filter(c => c.parent_attribute_id === attr.id).map(child => {
        if (child.data_type === 'Composite') {
          return { ...child, _children: allAttrs.filter(gc => gc.parent_attribute_id === child.id) }
        }
        return child
      })
    }
  }

  // Filter: Versteckte Attribute ausblenden
  attrs = attrs.filter(a => !a.is_hidden)

  // Filter: Interne Attribute für Nicht-Admins ausblenden
  if (authStore.userRole !== 'Admin') {
    attrs = attrs.filter(a => !a.is_internal)
  }

  // Filter: Attribut-Sicht — im dynamischen Sicht-Tab ist die Sicht durch den Tab
  // selbst vorgegeben (Filter-Dropdown ausgeblendet); sonst zählt die manuelle Auswahl.
  const forcedView = activeAttributeView.value
  const effectiveView = forcedView || (attrFilterView.value
    ? productTypeAttrViews.value.find(v => v.id === attrFilterView.value)
    : null)
  if (effectiveView) {
    const viewAttrIds = new Set((effectiveView.attributes || []).map(a => a.id))
    attrs = attrs.filter(a => viewAttrIds.has(a.id))
  }

  // Filter: Attributgruppe (AttributeType)
  if (attrFilterGroup.value) {
    attrs = attrs.filter(a => a.attribute_type_id === attrFilterGroup.value || a.attribute_type?.id === attrFilterGroup.value)
  }

  // Filter: Freitext-Suche (Name, Attribut-ID, Wert — inkl. Cluster-Attribute)
  if (attrFilterSearch.value.trim()) {
    const q = attrFilterSearch.value.toLowerCase()
    attrs = attrs.filter(a => {
      const name = (a.name_de || '').toLowerCase()
      const tech = (a.technical_name || '').toLowerCase()
      const id = (a.id || '').toLowerCase()
      if (name.includes(q) || tech.includes(q) || id.includes(q)) return true
      return getAttributeValueSearchText(a).includes(q)
    })
  }

  // Filter: Nur Pflichtfelder
  if (attrFilterMandatory.value) {
    attrs = attrs.filter(a => a.is_mandatory)
  }

  // Filter: Nur gefüllte Attribute (Cluster-/Composite-Attribute gelten als
  // gefüllt, sobald mindestens eines ihrer Kind-Attribute einen Wert hat)
  if (attrFilterFilledOnly.value) {
    attrs = attrs.filter(a => isAttributeFilled(a))
  }

  // Im dynamischen Sicht-Tab: Reihenfolge der Sicht übernehmen (per Drag&Drop
  // in der Attribut-Sicht-Verwaltung festgelegt), statt der Schema-Reihenfolge.
  if (forcedView) {
    const position = new Map((forcedView.attributes || []).map((a, i) => [a.id, i]))
    attrs = attrs.slice().sort((a, b) => (position.get(a.id) ?? 0) - (position.get(b.id) ?? 0))
  }

  return attrs
})

/**
 * Read-only-Override für ein Attribut, das speziell für die aktuell aktive
 * Attribut-Sicht (Produkteditor-Tab) gilt — unabhängig vom globalen
 * Attribute::is_readonly-Flag.
 */
function isAttrReadOnlyInActiveView(attributeId) {
  if (!activeAttributeView.value) return false
  const entry = (activeAttributeView.value.attributes || []).find(a => a.id === attributeId)
  return !!entry?.is_readonly_in_view
}

// ─── Variants ─────────────────────────────────────────
const variants = ref([])
const variantsLoaded = ref(false)
const variantsLoading = ref(false)
const showVariantForm = ref(false)
const variantForm = ref({ sku: '', name: '', ean: '', status: 'draft', axis_values: {} })
const variantErrors = ref({})
const variantSaving = ref(false)

const variantAttributeDefs = ref([])
const variantAttrValuesMap = ref({})

// ─── Variant Axes (Merkmalsachsen) ───────────────────
// Welche Attribute die Varianten DIESES Produkts unterscheiden — frei
// konfigurierbar, keine feste Achsenliste. variantAttributeDefs wird aus
// dieser pro-Produkt-Konfiguration befüllt (nicht mehr aus der globalen
// is_variant_attribute-Liste).
const variantAxisAttributeIds = ref([])
const variantAxisSaving = ref(false)
const variantAxisError = ref('')
const showVariantAxisConfig = ref(false)

const variantAxisEligibleAttributes = computed(() => {
  let attrs = schemaAttributes.value.filter(a => a.is_variant_attribute)
  if (authStore.userRole !== 'Admin') {
    attrs = attrs.filter(a => !a.is_internal)
  }
  return attrs
})

async function loadVariantAxes() {
  if (!product.value) return
  try {
    const { data } = await productsApi.getVariantAxes(product.value.id)
    const axes = data.data || data

    if (axes.length > 0) {
      variantAxisAttributeIds.value = axes.map(ax => ax.attribute_id)
      variantAttributeDefs.value = axes.map(ax => ({ id: ax.attribute_id, ...ax.attribute }))
      return
    }

    // Für dieses Produkt sind noch keine Achsen konfiguriert. Anzeige-Fallback
    // auf die globale is_variant_attribute-Liste, damit bereits gepflegte
    // Varianten-Attribute (Matrix, Generator) weiterhin sichtbar bleiben.
    variantAxisAttributeIds.value = []
    try {
      const { data: attrData } = await attributesApiDefault.listVariantAttributes()
      variantAttributeDefs.value = attrData.data || attrData
    } catch (e) { console.warn('Failed to load global variant attributes:', e.message) }

    // Vorschlag aus der Produkttyp-Vorlage vorbelegen (nur Vorauswahl in der
    // Achsen-Konfiguration, nicht automatisch gespeichert).
    const defaults = product.value.product_type?.default_variant_axes
    if (Array.isArray(defaults) && defaults.length > 0) {
      variantAxisAttributeIds.value = [...defaults]
    }
  } catch (e) { console.warn('Failed to load variant axes:', e.message) }
}

function toggleVariantAxis(attributeId) {
  const idx = variantAxisAttributeIds.value.indexOf(attributeId)
  if (idx === -1) variantAxisAttributeIds.value = [...variantAxisAttributeIds.value, attributeId]
  else variantAxisAttributeIds.value = variantAxisAttributeIds.value.filter(id => id !== attributeId)
}

function moveVariantAxis(index, direction) {
  const target = index + direction
  if (target < 0 || target >= variantAxisAttributeIds.value.length) return
  const ids = [...variantAxisAttributeIds.value]
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  variantAxisAttributeIds.value = ids
}

async function saveVariantAxes() {
  variantAxisSaving.value = true
  variantAxisError.value = ''
  try {
    await productsApi.setVariantAxes(product.value.id, variantAxisAttributeIds.value)
    variantsLoaded.value = false
    await loadVariants()
    showVariantAxisConfig.value = false
  } catch (e) {
    variantAxisError.value = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(' ')
      : (e.response?.data?.message || e.message)
  } finally { variantAxisSaving.value = false }
}

const variantColumns = computed(() => {
  const base = [
    { key: 'sku', label: 'SKU', mono: true },
    { key: 'name', label: 'Name' },
    { key: 'status', label: 'Status' },
  ]
  for (const attr of variantAttributeDefs.value) {
    base.push({ key: `_va_${attr.id}`, label: attr.name_de || attr.technical_name })
  }
  return base
})

const variantRows = computed(() => {
  return variants.value.map(v => {
    const row = { ...v }
    for (const attr of variantAttributeDefs.value) {
      const vals = variantAttrValuesMap.value[v.id] || []
      const attrVal = vals.find(av => (av.attribute_id || av.attribute?.id) === attr.id)
      row[`_va_${attr.id}`] = attrVal
        ? (attrVal.value_string ?? attrVal.value_number ?? attrVal.value_date ?? (attrVal.value_flag !== null ? (attrVal.value_flag ? 'Ja' : 'Nein') : '') ?? '')
        : ''
    }
    return row
  })
})

async function loadVariants() {
  if (variantsLoaded.value || !product.value) return
  variantsLoading.value = true
  try {
    const { data } = await productsApi.getVariants(product.value.id)
    variants.value = data.data || data
    variantsLoaded.value = true

    // Merkmalsachsen dieses Produkts laden (befüllt variantAttributeDefs)
    await loadVariantAxes()

    // Load attribute values for each variant
    if (variantAttributeDefs.value.length > 0 && variants.value.length > 0) {
      const promises = variants.value.map(async (v) => {
        try {
          const { data: valData } = await productsApi.getAttributeValues(v.id)
          return { id: v.id, values: valData.data || valData }
        } catch (e) { console.warn(`Failed to load attribute values for variant ${v.id}:`, e.message); return { id: v.id, values: [] } }
      })
      const results = await Promise.all(promises)
      const map = {}
      for (const r of results) {
        map[r.id] = Array.isArray(r.values) ? r.values : []
      }
      variantAttrValuesMap.value = map
    }
  } catch (e) { console.error('Failed to load variants:', e.message) }
  finally { variantsLoading.value = false }
}

async function createVariant() {
  variantSaving.value = true
  variantErrors.value = {}
  try {
    await productsApi.createVariant(product.value.id, variantForm.value)
    showVariantForm.value = false
    variantForm.value = { sku: '', name: '', ean: '', status: 'draft', axis_values: {} }
    variantsLoaded.value = false
    await loadVariants()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        variantErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { variantSaving.value = false }
}

// ─── Variant Delete ──────────────────────────────────
const variantDeleteTarget = ref(null)
const variantDeleting = ref(false)

async function confirmDeleteVariant({ force = false } = {}) {
  variantDeleting.value = true
  try {
    await productsApi.delete(variantDeleteTarget.value.id, { force })
    variantDeleteTarget.value = null
    variantsLoaded.value = false
    await loadVariants()
  } finally { variantDeleting.value = false }
}

// ─── Product Copy ───────────────────────────────────
const showCopyDialog = ref(false)
const copyOptions = ref({
  include_attributes: true,
  include_prices: true,
  include_media: true,
  include_relations: true,
})
const copying = ref(false)

function selectAllCopyOptions(val) {
  copyOptions.value.include_attributes = val
  copyOptions.value.include_prices = val
  copyOptions.value.include_media = val
  copyOptions.value.include_relations = val
}

async function duplicateProduct() {
  copying.value = true
  try {
    const { data } = await productsApi.duplicate(product.value.id, copyOptions.value)
    const newId = data.data?.id || data.id
    showCopyDialog.value = false
    router.push(`/products/${newId}`)
  } catch (e) {
    console.error('Failed to duplicate product:', e.message)
    alert('Fehler beim Kopieren: ' + (e.response?.data?.message || e.message))
  } finally { copying.value = false }
}

// ─── Variant Generator ──────────────────────────────
const showGenerator = ref(false)
const generatorStep = ref(1)
const generatorDimensions = ref([])
const generatorSKUPrefix = ref('')
const generatorLoading = ref(false)
const generatorResult = ref(null)
const generatorExcluded = ref(new Set())

function initGenerator() {
  showGenerator.value = true
  generatorStep.value = 1
  generatorResult.value = null
  generatorExcluded.value = new Set()
  generatorSKUPrefix.value = product.value?.sku || ''
  generatorDimensions.value = variantAttributeDefs.value.map(attr => ({
    attribute_id: attr.id,
    attribute: attr,
    selected: false,
    values: [],
    textInput: '',
  }))
}

const generatorPreview = computed(() => {
  const activeDims = generatorDimensions.value.filter(d => d.selected && d.values.length > 0)
  if (activeDims.length === 0) return []
  // Cartesian product
  let combos = [[]]
  for (const dim of activeDims) {
    const next = []
    for (const combo of combos) {
      for (const val of dim.values) {
        next.push([...combo, { attribute_id: dim.attribute_id, label: dim.attribute.name_de || dim.attribute.technical_name, value: val }])
      }
    }
    combos = next
  }
  const prefix = generatorSKUPrefix.value || product.value?.sku || 'VAR'
  return combos.map((combo, idx) => {
    const slugParts = combo.map(c => String(c.value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '').substring(0, 20))
    const sku = `${prefix}-${slugParts.join('-')}`
    const name = `${product.value?.name || ''} — ${combo.map(c => c.value).join(' / ')}`
    return { idx, sku, name, combo }
  })
})

const generatorPreviewFiltered = computed(() => {
  return generatorPreview.value.filter(p => !generatorExcluded.value.has(p.idx))
})

const generatorTotalCombinations = computed(() => {
  const activeDims = generatorDimensions.value.filter(d => d.selected && d.values.length > 0)
  if (activeDims.length === 0) return 0
  return activeDims.reduce((acc, d) => acc * d.values.length, 1)
})

function toggleGeneratorRow(idx) {
  const s = new Set(generatorExcluded.value)
  if (s.has(idx)) s.delete(idx)
  else s.add(idx)
  generatorExcluded.value = s
}

function addDimensionValues(dim) {
  if (!dim.textInput.trim()) return
  const newVals = dim.textInput.split(',').map(v => v.trim()).filter(v => v)
  dim.values = [...new Set([...dim.values, ...newVals])]
  dim.textInput = ''
}

function removeDimensionValue(dim, val) {
  dim.values = dim.values.filter(v => v !== val)
}

function toggleValueListEntry(dim, entryValue) {
  const idx = dim.values.indexOf(entryValue)
  if (idx >= 0) dim.values.splice(idx, 1)
  else dim.values.push(entryValue)
}

async function runGenerator() {
  generatorLoading.value = true
  try {
    const activeDims = generatorDimensions.value.filter(d => d.selected && d.values.length > 0)
    const excluded = generatorExcluded.value
    // Filter out excluded combos by regenerating only included ones
    const dimensions = activeDims.map(d => ({
      attribute_id: d.attribute_id,
      values: d.values,
    }))
    const { data } = await productsApi.generateVariants(product.value.id, {
      dimensions,
      sku_prefix: generatorSKUPrefix.value || undefined,
      status: 'draft',
    })
    generatorResult.value = data
    generatorStep.value = 3
    // Reload variants
    variantsLoaded.value = false
    await loadVariants()
  } catch (e) {
    console.error('Failed to generate variants:', e.message)
    alert('Fehler: ' + (e.response?.data?.message || e.message))
  } finally { generatorLoading.value = false }
}

// ─── Variant Inheritance Rules ───────────────────────
const inheritanceRulesLoaded = ref(false)
const inheritanceRulesLoading = ref(false)
const inheritanceRulesSaving = ref(false)
const showInheritanceRules = ref(false)
const editedInheritanceRules = ref({})

const inheritedAttributeIds = computed(() => {
  const ids = new Set()
  for (const [attrId, mode] of Object.entries(editedInheritanceRules.value)) {
    if (mode === 'inherit') ids.add(attrId)
  }
  return ids
})

function isAttributeInherited(attrId) {
  if (product.value?.product_type_ref !== 'variant') return false
  return inheritedAttributeIds.value.has(attrId)
}

async function loadInheritanceRules() {
  if (inheritanceRulesLoaded.value || !product.value) return
  const rulesProductId = product.value.product_type_ref === 'variant'
    ? product.value.parent_product_id
    : product.value.id
  if (!rulesProductId) return
  inheritanceRulesLoading.value = true
  try {
    const { data } = await productsApi.getVariantRules(rulesProductId)
    const rules = data.data || data
    const map = {}
    for (const rule of rules) {
      map[rule.attribute_id] = rule.inheritance_mode
    }
    editedInheritanceRules.value = map
    inheritanceRulesLoaded.value = true
  } catch { /* silently fail */ }
  finally { inheritanceRulesLoading.value = false }
}

function toggleInheritance(attrId) {
  editedInheritanceRules.value = {
    ...editedInheritanceRules.value,
    [attrId]: (editedInheritanceRules.value[attrId] || 'override') === 'inherit' ? 'override' : 'inherit',
  }
}

async function saveInheritanceRules() {
  inheritanceRulesSaving.value = true
  try {
    const rules = Object.entries(editedInheritanceRules.value).map(([attribute_id, inheritance_mode]) => ({
      attribute_id,
      inheritance_mode,
    }))
    await productsApi.setVariantRules(product.value.id, rules)
  } catch { /* silently fail */ }
  finally { inheritanceRulesSaving.value = false }
}

// ─── Media ────────────────────────────────────────────
const mediaItems = ref([])
const mediaLoaded = ref(false)
const mediaLoading = ref(false)
const showMediaPicker = ref(false)
const usageTypesList = ref([])
const selectedUsageTypeId = ref(null)

// Medientypen, die für den Produkttyp des aktuellen Produkts gelten.
// Leere allowed_product_type_ids = für alle Produkttypen gültig (Default);
// gefüllt = nur wenn die product_type_id enthalten ist. Steuert die im
// Medien-Tab (Upload-Auswahl + Zuordnungs-Dialog) angebotenen Bildtypen.
const productTypeUsageTypes = computed(() => {
  const typeId = product.value?.product_type_id
  return usageTypesList.value.filter(ut =>
    !ut.allowed_product_type_ids?.length || ut.allowed_product_type_ids.includes(typeId)
  )
})
const mediaViewMode = ref('grid') // 'grid' | 'list'
const mediaFilter = ref('')
const selectedMediaIds = ref(new Set())
const downloadingMediaZip = ref(false)

// Spalten-Konfiguration
const defaultMediaColumns = [
  { key: 'thumb',      label: 'Bild',       sortable: false },
  { key: 'file_name',  label: 'Dateiname',  sortable: true, mono: true },
  { key: 'usage_type', label: 'Bildtyp',    sortable: true },
  { key: 'mime_type',  label: 'MIME',        sortable: true },
]
const extraMediaColumns = [
  { key: 'title',          label: 'Titel (DE)',         sortable: true },
  { key: 'media_type',     label: 'Medientyp',          sortable: true },
  { key: 'usage_purpose',  label: 'Verwendungszweck',   sortable: true },
  { key: 'file_size',      label: 'Dateigröße',         sortable: true },
  { key: 'dimensions',     label: 'Abmessungen',        sortable: false },
  { key: 'alt_text',       label: 'Alt-Text',           sortable: false },
  { key: 'sort_order',     label: 'Sortierung',         sortable: true },
  { key: 'is_primary',     label: 'Primär',             sortable: true },
]
const {
  allColumns: allMediaColumns,
  visibleColumns: visibleMediaColumns,
  visibleKeys: visibleMediaKeys,
  toggleColumn: toggleMediaColumn,
  moveColumn: moveMediaColumn,
  resetColumns: resetMediaColumns,
} = useColumnConfig('columns:product-media', defaultMediaColumns, extraMediaColumns)

// Quick Lookup
const showMediaQuickLookup = ref(false)
const mediaQuickLookupFilters = ref({})
watch(showMediaQuickLookup, (val) => {
  if (!val) mediaQuickLookupFilters.value = {}
})

// Direkt-Upload
const showMediaUpload = ref(false)
const uploadUsageTypeId = ref(null)
const uploadFolderId = ref(null)
const assetFolders = ref([])
const uploadQueueRef = ref(null)

const selectedUsageTypeExtensions = computed(() => {
  if (!selectedUsageTypeId.value) return null
  const ut = usageTypesList.value.find(t => t.id === selectedUsageTypeId.value)
  return ut?.allowed_extensions ?? null
})

async function loadMedia() {
  if (mediaLoaded.value || !product.value) return
  mediaLoading.value = true
  try {
    const items = []
    let page = 1
    let lastPage = 1
    do {
      const { data } = await productsApi.getMedia(product.value.id, { page, perPage: 100 })
      items.push(...(data.data || data))
      lastPage = data.meta?.last_page || 1
      page++
    } while (page <= lastPage)
    mediaItems.value = items
    mediaLoaded.value = true
    selectedMediaIds.value = new Set()
  } catch (e) { console.error('Failed to load media:', e.message) }
  finally { mediaLoading.value = false }
}

function isMediaSelectable(item) {
  return item.can_download !== false
}

function isMediaSelected(item) {
  return selectedMediaIds.value.has(item.id)
}

function toggleMediaSelection(item) {
  if (!isMediaSelectable(item)) return
  const next = new Set(selectedMediaIds.value)
  if (next.has(item.id)) {
    next.delete(item.id)
  } else {
    next.add(item.id)
  }
  selectedMediaIds.value = next
}

const selectableDisplayMediaItems = computed(() => displayMediaItems.value.filter(isMediaSelectable))

const allDisplayedMediaSelected = computed(() => {
  return selectableDisplayMediaItems.value.length > 0
    && selectableDisplayMediaItems.value.every(m => selectedMediaIds.value.has(m.id))
})

function toggleSelectAllMedia() {
  const next = new Set(selectedMediaIds.value)
  const displayedIds = selectableDisplayMediaItems.value.map(m => m.id)
  if (allDisplayedMediaSelected.value) {
    displayedIds.forEach(id => next.delete(id))
  } else {
    displayedIds.forEach(id => next.add(id))
  }
  selectedMediaIds.value = next
}

async function downloadSelectedMediaZip() {
  if (selectedMediaIds.value.size === 0 || !product.value) return
  downloadingMediaZip.value = true
  try {
    const resp = await productsApi.downloadMediaZip(product.value.id, [...selectedMediaIds.value])
    triggerDownload(resp.data, `${product.value.sku || product.value.id}-medien-${new Date().toISOString().slice(0, 10)}.zip`)
    const skippedCount = parseInt(resp.headers?.['x-skipped-count'] || '0', 10)
    if (skippedCount > 0) {
      toastStore.showToast(`${skippedCount} Datei(en) konnten nicht in die ZIP-Datei aufgenommen werden`, 'info')
    }
  } catch (e) {
    console.error('Media ZIP download failed:', e.message)
    toastStore.showToast('ZIP-Download fehlgeschlagen: ' + (await blobErrorMessage(e)), 'error')
  } finally {
    downloadingMediaZip.value = false
  }
}

async function openMediaPicker() {
  showMediaPicker.value = true
  if (usageTypesList.value.length === 0) {
    try {
      const { data } = await mediaUsageTypes.list()
      const types = data.data || data
      usageTypesList.value = types
    } catch (e) { console.error('Failed to load usage types:', e.message) }
  }
  // Immer sicherstellen dass ein (für den Produkttyp erlaubter) Usage Type gewählt ist
  if (!selectedUsageTypeId.value && productTypeUsageTypes.value.length > 0) {
    selectedUsageTypeId.value = productTypeUsageTypes.value[0].id
  }
}

const assignedMediaIds = computed(() => {
  return mediaItems.value.map(m => m.media_id || m.media?.id || m.id).filter(Boolean)
})

const filteredMediaItems = computed(() => {
  if (!mediaFilter.value.trim()) return mediaItems.value
  const q = mediaFilter.value.toLowerCase()
  return mediaItems.value.filter(m => {
    const fname = (m.file_name || m.media?.file_name || m.motif?.title_de || '').toLowerCase()
    const usageType = (m.usage_type?.name_de || m.usage_type?.technical_name || '').toLowerCase()
    const mime = (m.mime_type || m.media?.mime_type || '').toLowerCase()
    return fname.includes(q) || usageType.includes(q) || mime.includes(q)
  })
})

// Quick Lookup: Spaltenweise Filterung (AND-Logik)
const displayMediaItems = computed(() => {
  let items = filteredMediaItems.value
  if (!showMediaQuickLookup.value) return items
  const filters = mediaQuickLookupFilters.value
  const active = Object.entries(filters).filter(([, v]) => v !== '' && v != null)
  if (active.length === 0) return items
  return items.filter(item => {
    return active.every(([key, val]) => {
      const v = val.toLowerCase()
      switch (key) {
        case 'file_name': return (item.file_name || item.media?.file_name || '').toLowerCase().includes(v)
        case 'usage_type': return (item.usage_type?.name_de || item.usage_type?.technical_name || '').toLowerCase().includes(v)
        case 'mime_type': return (item.mime_type || item.media?.mime_type || '').toLowerCase().includes(v)
        case 'title': return (item.media?.title_de || item.media?.file_name || '').toLowerCase().includes(v)
        case 'media_type': return item.media?.media_type === val
        case 'usage_purpose': return item.media?.usage_purpose === val
        case 'alt_text': return (item.media?.alt_text_de || '').toLowerCase().includes(v)
        default: return true
      }
    })
  })
})

// Drag&Drop-Sortierung der Bildergalerie (Grid-Ansicht) — nur aktiv ohne Filter/Quick-Lookup,
// da displayMediaItems dann exakt mediaItems.value entspricht und Indizes übereinstimmen.
const canReorderMedia = computed(() => !mediaFilter.value.trim() && !showMediaQuickLookup.value && !mediaReordering.value)
const mediaReordering = ref(false)
let mediaOrderRequestId = 0

async function persistMediaOrder(newItems) {
  const previous = mediaItems.value
  const requestId = ++mediaOrderRequestId
  mediaItems.value = newItems
  mediaReordering.value = true
  try {
    await productsApi.reorderMedia(product.value.id, newItems.map(m => m.id))
  } catch (e) {
    // Nur zurücksetzen, wenn dies noch der jüngste Reorder-Request ist — sonst würde ein
    // spät fehlschlagender, längst überholter Request einen bereits erfolgreich gespeicherten
    // neueren Zustand wieder überschreiben.
    if (requestId === mediaOrderRequestId) {
      mediaItems.value = previous
      toastStore.showToast('Reihenfolge konnte nicht gespeichert werden: ' + (e.response?.data?.message || e.message), 'error')
    }
  } finally {
    if (requestId === mediaOrderRequestId) mediaReordering.value = false
  }
}

const {
  dragging: mediaDragging,
  dragIndex: mediaDragIndex,
  onDragStart: onMediaDragStart,
  onDragEnd: onMediaDragEnd,
  onDrop: onMediaDrop,
} = useDragDrop(persistMediaOrder)

function handleMediaDrop(targetIndex) {
  if (!canReorderMedia.value || mediaDragIndex.value === null || mediaDragIndex.value === targetIndex) {
    onMediaDragEnd()
    return
  }
  const arr = [...mediaItems.value]
  const [moved] = arr.splice(mediaDragIndex.value, 1)
  arr.splice(targetIndex, 0, moved)
  onMediaDrop(arr)
}

function formatFileSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const uploadMetadata = computed(() => {
  const meta = {}
  if (uploadFolderId.value) meta.asset_folder_id = uploadFolderId.value
  return meta
})

async function loadAssetFolders() {
  if (assetFolders.value.length > 0) return
  try {
    const { data } = await hierarchiesApi.list({ filters: { hierarchy_type: 'asset' } })
    const hierarchies = data.data || data
    if (hierarchies.length > 0) {
      const treeRes = await hierarchiesApi.getTree(hierarchies[0].id)
      assetFolders.value = flattenFolderTree(treeRes.data.data || treeRes.data || [])
    }
  } catch (e) { console.error('Failed to load asset folders:', e) }
}

function flattenFolderTree(nodes, depth = 0) {
  const result = []
  for (const node of nodes) {
    const indent = '\u00A0\u00A0'.repeat(depth)
    const prefix = depth > 0 ? indent + '└ ' : ''
    result.push({ id: node.id, name: prefix + (node.name_de || node.name || node.technical_name || node.id) })
    if (node.children?.length) {
      result.push(...flattenFolderTree(node.children, depth + 1))
    }
  }
  return result
}

async function openMediaUpload() {
  showMediaUpload.value = true
  if (usageTypesList.value.length === 0) {
    try {
      const { data } = await mediaUsageTypes.list()
      const types = data.data || data
      usageTypesList.value = types
      if (productTypeUsageTypes.value.length > 0 && !uploadUsageTypeId.value) {
        uploadUsageTypeId.value = productTypeUsageTypes.value[0].id
      }
    } catch (e) { console.error('Failed to load usage types:', e.message) }
  } else if (!uploadUsageTypeId.value && productTypeUsageTypes.value.length > 0) {
    uploadUsageTypeId.value = productTypeUsageTypes.value[0].id
  }
  loadAssetFolders()
}

function handleMediaUpload(event) {
  const files = event.target.files
  if (!files?.length) return
  uploadQueueRef.value?.addFiles(files)
  event.target.value = ''
}

let _reloadMediaTimer = null
async function onFileUploaded(mediaItem) {
  try {
    await productsApi.attachMedia(product.value.id, {
      media_id: mediaItem.id,
      usage_type_id: uploadUsageTypeId.value || selectedUsageTypeId.value,
      sort_order: mediaItems.value.length,
    })
    // Debounce: bei Multi-Upload (MAX_CONCURRENT=3) kommen Events fast gleichzeitig
    clearTimeout(_reloadMediaTimer)
    _reloadMediaTimer = setTimeout(() => {
      mediaLoaded.value = false
      loadMedia()
    }, 300)
  } catch (e) {
    console.error('Failed to attach uploaded media:', e.message)
    toastStore.showToast('Hochgeladenes Medium konnte nicht zugeordnet werden: ' + (e.response?.data?.message || e.message), 'error')
  }
}

async function attachMedia(mediaItem) {
  if (!selectedUsageTypeId.value) {
    toastStore.showToast('Bitte zuerst einen Bildtyp auswählen', 'error')
    return
  }
  try {
    await productsApi.attachMedia(product.value.id, {
      media_id: mediaItem.id,
      usage_type_id: selectedUsageTypeId.value,
      sort_order: mediaItems.value.length,
    })
    showMediaPicker.value = false
    mediaLoaded.value = false
    await loadMedia()
  } catch (e) {
    console.error('Failed to attach media:', e.message)
    toastStore.showToast('Medium konnte nicht zugeordnet werden: ' + (e.response?.data?.message || e.message), 'error')
  }
}

const showMotifPicker = ref(false)

async function openMotifPicker() {
  if (usageTypesList.value.length === 0) {
    try {
      const { data } = await mediaUsageTypes.list()
      usageTypesList.value = data.data || data
    } catch (e) { console.error('Failed to load usage types:', e.message) }
  }
  if (!selectedUsageTypeId.value && productTypeUsageTypes.value.length > 0) {
    selectedUsageTypeId.value = productTypeUsageTypes.value[0].id
  }
  showMotifPicker.value = true
}

async function attachMotif(motif) {
  if (!selectedUsageTypeId.value) {
    toastStore.showToast('Bitte zuerst einen Bildtyp auswählen', 'error')
    return
  }
  try {
    await productsApi.attachMedia(product.value.id, {
      motif_id: motif.id,
      usage_type_id: selectedUsageTypeId.value,
      sort_order: mediaItems.value.length,
    })
    mediaLoaded.value = false
    await loadMedia()
    toastStore.showToast(`Motiv "${motif.title_de || motif.id}" zugeordnet`, 'success')
  } catch (e) {
    console.error('Failed to attach motif:', e.message)
    toastStore.showToast('Motiv konnte nicht zugeordnet werden: ' + (e.response?.data?.message || e.message), 'error')
  }
}

async function attachMediaBulk(mediaItemsList) {
  if (!selectedUsageTypeId.value) {
    toastStore.showToast('Bitte zuerst einen Bildtyp auswählen', 'error')
    return
  }
  try {
    for (let i = 0; i < mediaItemsList.length; i++) {
      await productsApi.attachMedia(product.value.id, {
        media_id: mediaItemsList[i].id,
        usage_type_id: selectedUsageTypeId.value,
        sort_order: mediaItems.value.length + i,
      })
    }
    showMediaPicker.value = false
    mediaLoaded.value = false
    await loadMedia()
    toastStore.showToast(`${mediaItemsList.length} Medien zugeordnet`, 'success')
  } catch (e) {
    console.error('Failed to bulk attach media:', e.message)
    toastStore.showToast('Fehler beim Zuordnen: ' + (e.response?.data?.message || e.message), 'error')
    mediaLoaded.value = false
    await loadMedia()
  }
}

async function detachMedia(item) {
  const pivotId = item.pivot?.id || item.id
  try {
    await productsApi.detachMedia(pivotId)
    mediaLoaded.value = false
    await loadMedia()
  } catch (e) { console.error('Failed to detach media:', e.message) }
}

function getMediaUrl(item) {
  if (item.preview_thumb_url) return item.preview_thumb_url
  const media = item.media || item
  const id = media.id || item.media_id
  if (id && (media.media_type === 'image' || !media.media_type)) {
    return mediaApi.thumbUrl(id, 300, 300)
  }
  const fname = media.file_name || item.file_name
  if (fname) return mediaApi.fileUrl(fname)
  return ''
}

function assignmentLabel(item) {
  return item.motif_id ? (item.motif?.title_de || '(Motiv ohne Titel)') : (item.file_name || item.media?.file_name || '—')
}

function isMediaPdf(item) {
  const mime = item.mime_type || item.media?.mime_type || ''
  if (mime.includes('pdf')) return true
  const fname = item.file_name || item.media?.file_name || ''
  return fname.toLowerCase().endsWith('.pdf')
}

// ─── Watchlist (Header + Beziehungen) ────────────────
const watchlistIds = ref(new Set())
const isOnWatchlist = computed(() => product.value && watchlistIds.value.has(product.value.id))

async function loadWatchlistIds() {
  try {
    const { data } = await watchlistApi.productIds()
    watchlistIds.value = new Set(data.data || data)
  } catch { /* ignore */ }
}

async function toggleWatchlist() {
  if (!product.value) return
  try {
    if (isOnWatchlist.value) {
      await watchlistApi.removeByProduct(product.value.id)
      watchlistIds.value.delete(product.value.id)
      watchlistIds.value = new Set(watchlistIds.value)
    } else {
      await watchlistApi.add(product.value.id)
      watchlistIds.value.add(product.value.id)
      watchlistIds.value = new Set(watchlistIds.value)
    }
  } catch (e) { console.error('Watchlist toggle failed', e) }
}

async function toggleTargetWatchlist(productId) {
  if (!productId) return
  try {
    if (watchlistIds.value.has(productId)) {
      await watchlistApi.removeByProduct(productId)
      watchlistIds.value.delete(productId)
    } else {
      await watchlistApi.add(productId)
      watchlistIds.value.add(productId)
    }
    watchlistIds.value = new Set(watchlistIds.value)
  } catch (e) { console.error('Watchlist toggle failed', e) }
}

// ─── Hauptbild Vorschau (Header) ─────────────────────
const primaryMediaThumb = computed(() => {
  const primary = mediaItems.value.find(m => m.is_primary)
    || mediaItems.value.find(m => m.usage_type?.technical_name === 'teaser')
    || mediaItems.value.find(m => (m.mime_type || m.media?.mime_type || '').startsWith('image/'))
  if (!primary) return null
  const id = primary.media_id || primary.media?.id || primary.id
  return id ? mediaApi.thumbUrl(id, 64, 64) : null
})

// ─── Prices ───────────────────────────────────────────
const prices = ref([])
const pricesLoaded = ref(false)
const pricesLoading = ref(false)
const priceTypesList = ref([])
const priceRegionsList = ref([])
const showPriceForm = ref(false)
const priceForm = ref({ price_type_id: '', amount: '', currency: 'EUR', valid_from: '', valid_to: '', price_region_id: '', scale_from: '', scale_to: '' })
const priceErrors = ref({})
const priceSaving = ref(false)
const priceEditId = ref(null)
const priceDeleteTarget = ref(null)
const priceDeleting = ref(false)

const priceColumns = [
  { key: 'price_type.name_de', label: 'Preistyp' },
  { key: 'amount', label: 'Betrag', align: 'right' },
  { key: 'currency', label: 'Währung' },
  { key: 'valid_from', label: 'Gültig ab' },
  { key: 'valid_to', label: 'Gültig bis' },
  { key: 'price_region.name', label: 'Preisregion' },
]

const priceQuickLookup = ref({})
const priceQuickLookupConfig = computed(() => ({
  'price_type.name_de': { type: 'select', options: priceTypesList.value.map(t => ({ value: t.name_de || t.technical_name, label: t.name_de || t.technical_name })) },
  'currency': { type: 'select', options: [{ value: 'EUR', label: 'EUR' }, { value: 'USD', label: 'USD' }, { value: 'CHF', label: 'CHF' }, { value: 'GBP', label: 'GBP' }] },
  'price_region.name': { type: 'text', placeholder: 'Region…' },
}))
const filteredPrices = computed(() => {
  const f = priceQuickLookup.value
  if (!Object.values(f).some(v => v)) return prices.value
  return prices.value.filter(p => {
    if (f['price_type.name_de'] && !(p.price_type?.name_de || '').toLowerCase().includes(f['price_type.name_de'].toLowerCase())) return false
    if (f['currency'] && p.currency !== f['currency']) return false
    if (f['price_region.name'] && !(p.price_region?.name || '').toLowerCase().includes(f['price_region.name'].toLowerCase())) return false
    return true
  })
})

async function loadPrices() {
  if (pricesLoaded.value || !product.value) return
  pricesLoading.value = true
  try {
    const [pricesResp, typesResp, regionsResp] = await Promise.all([
      productsApi.getPrices(product.value.id),
      priceTypesList.value.length ? Promise.resolve(null) : priceTypes.list(),
      priceRegionsList.value.length ? Promise.resolve(null) : priceRegions.list(),
    ])
    prices.value = pricesResp.data.data || pricesResp.data
    if (typesResp) priceTypesList.value = typesResp.data.data || typesResp.data
    if (regionsResp) priceRegionsList.value = regionsResp.data.data || regionsResp.data
    pricesLoaded.value = true
  } catch (e) { console.error('Failed to load prices:', e.message) }
  finally { pricesLoading.value = false }
}

function openPriceForm(price = null) {
  if (price) {
    priceEditId.value = price.id
    priceForm.value = {
      price_type_id: price.price_type_id || price.price_type?.id || '',
      amount: price.amount || '',
      currency: price.currency || 'EUR',
      valid_from: price.valid_from ? price.valid_from.substring(0, 10) : '',
      valid_to: price.valid_to ? price.valid_to.substring(0, 10) : '',
      price_region_id: price.price_region_id || '',
      scale_from: price.scale_from || '',
      scale_to: price.scale_to || '',
    }
  } else {
    priceEditId.value = null
    priceForm.value = { price_type_id: '', amount: '', currency: 'EUR', valid_from: '', valid_to: '', price_region_id: '', scale_from: '', scale_to: '' }
  }
  priceErrors.value = {}
  showPriceForm.value = true
}

async function savePrice() {
  priceSaving.value = true
  priceErrors.value = {}
  const payload = { ...priceForm.value }
  if (!payload.valid_to) delete payload.valid_to
  if (!payload.price_region_id) delete payload.price_region_id
  if (!payload.scale_from) delete payload.scale_from
  if (!payload.scale_to) delete payload.scale_to
  try {
    if (priceEditId.value) {
      await productsApi.updatePrice(priceEditId.value, payload)
    } else {
      await productsApi.createPrice(product.value.id, payload)
    }
    showPriceForm.value = false
    pricesLoaded.value = false
    await loadPrices()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        priceErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { priceSaving.value = false }
}

async function confirmDeletePrice() {
  priceDeleting.value = true
  try {
    await productsApi.deletePrice(priceDeleteTarget.value.id)
    priceDeleteTarget.value = null
    pricesLoaded.value = false
    await loadPrices()
  } finally { priceDeleting.value = false }
}

// ─── Relations ────────────────────────────────────────
const relations = ref([])
const relationsLoaded = ref(false)
const relationsLoading = ref(false)
const relationTypesList = ref([])
const showRelationForm = ref(false)
const relationFilter = ref('')
const relationViewMode = ref('list') // 'list' | 'grid'
const relationQuickLookup = ref({})
const relationSortField = ref('')
const relationSortOrder = ref('asc') // 'asc' | 'desc'

// Beziehungstypen, die für den Produkttyp dieses Produkts als Quelle
// zulässig sind. Leere allowed_source_product_type_ids = für alle Typen erlaubt.
const availableRelationTypesForProduct = computed(() => {
  const typeId = product.value?.product_type_id
  return relationTypesList.value.filter(t =>
    !t.allowed_source_product_type_ids?.length || t.allowed_source_product_type_ids.includes(typeId)
  )
})

const filteredRelations = computed(() => {
  let result = relations.value

  // Global filter (Grid-Modus Suchfeld)
  if (relationFilter.value.trim()) {
    const q = relationFilter.value.toLowerCase()
    result = result.filter(r =>
      (r.target_product?.sku || '').toLowerCase().includes(q) ||
      (r.target_product?.name || '').toLowerCase().includes(q) ||
      (r.relation_type?.name_de || '').toLowerCase().includes(q)
    )
  }

  // Spaltenfilter (Quick Lookup)
  const f = relationQuickLookup.value
  if (Object.values(f).some(v => v)) {
    result = result.filter(r => {
      if (f['relation_type'] && !(r.relation_type?.name_de || '').toLowerCase().includes(f['relation_type'].toLowerCase())) return false
      if (f['target_sku'] && !(r.target_product?.sku || '').toLowerCase().includes(f['target_sku'].toLowerCase())) return false
      if (f['target_name'] && !(r.target_product?.name || '').toLowerCase().includes(f['target_name'].toLowerCase())) return false
      return true
    })
  }

  // Sortierung
  if (relationSortField.value) {
    const field = relationSortField.value
    const dir = relationSortOrder.value === 'asc' ? 1 : -1
    result = [...result].sort((a, b) => {
      let va, vb
      if (field === 'relation_type') { va = a.relation_type?.name_de || ''; vb = b.relation_type?.name_de || '' }
      else if (field === 'target_sku') { va = a.target_product?.sku || ''; vb = b.target_product?.sku || '' }
      else if (field === 'target_name') { va = a.target_product?.name || ''; vb = b.target_product?.name || '' }
      else if (field === 'sort_order') { return (Number(a.sort_order) - Number(b.sort_order)) * dir }
      else return 0
      return va.localeCompare(vb, 'de') * dir
    })
  }

  return result
})

function toggleRelationSort(field) {
  if (relationSortField.value === field) {
    relationSortOrder.value = relationSortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    relationSortField.value = field
    relationSortOrder.value = 'asc'
  }
}

const relationQuickLookupConfig = computed(() => ({
  'relation_type': {
    type: 'select',
    options: relationTypesList.value.map(t => ({ value: t.name_de || t.technical_name, label: t.name_de || t.technical_name })),
  },
  'target_sku': { type: 'text', placeholder: 'SKU…' },
  'target_name': { type: 'text', placeholder: 'Name…' },
}))
const relationForm = ref({ relation_type_id: '', target_product_id: '', sort_order: 0 })
const relationErrors = ref({})
const relationSaving = ref(false)
const relationDeleteTarget = ref(null)
const relationDeleting = ref(false)
const productSearch = ref('')
const productSearchResults = ref([])
const productSearching = ref(false)

// Relation attribute editing state
const expandedRelationId = ref(null)
const relationAttrValues = ref([])
const relationAttrLoading = ref(false)
const relationAttrSaving = ref(false)
const relationAttrList = ref([]) // all available attributes for dropdown
const relationAttrListLoaded = ref(false)
const newRelationAttr = ref({ attribute_id: '' })

// Kernspalten der Beziehungs-Tabelle (Standard sichtbar). Die Keys entsprechen
// den Sortierfeldern in filteredRelations, damit toggleRelationSort(col.key) greift.
const relationCoreColumns = [
  { key: 'relation_type', label: 'Beziehungstyp', sortable: true },
  { key: 'target_sku',    label: 'Ziel-SKU',      sortable: true, mono: true },
  { key: 'target_name',   label: 'Zielprodukt',   sortable: true },
  { key: 'sort_order',    label: 'Reihenfolge',   sortable: true },
]

// Dynamische Metadaten-Spalten: Vereinigung aus (a) den in den Beziehungen real
// gepflegten Attributwerten und (b) den Default-Attributen der Beziehungstypen.
// Key-Präfix 'attributes.' — damit die localStorage-Persistenz in useColumnConfig
// (Whitelist für 'attributes.*') die Spaltenauswahl über Reloads hinweg hält.
const relationMetadataColumns = computed(() => {
  const map = new Map()
  const add = (attr, sortOrder) => {
    if (!attr?.id || map.has(attr.id)) return
    map.set(attr.id, {
      key: 'attributes.' + attr.id,
      label: attr.name_de || attr.technical_name || 'Attribut',
      group: 'Beziehungsattribute',
      attribute_id: attr.id,
      metaSort: sortOrder ?? 9999,
    })
  }
  for (const rel of relations.value) {
    for (const av of (rel.attribute_values || [])) add(av.attribute)
    for (const da of (rel.relation_type?.default_attributes || [])) add(da, da.pivot?.sort_order ?? da.sort_order)
  }
  return [...map.values()].sort((a, b) =>
    (a.metaSort - b.metaSort) || a.label.localeCompare(b.label, 'de')
  )
})

const {
  allColumns: allRelationColumns,
  visibleColumns: visibleRelationColumns,
  visibleKeys: visibleRelationKeys,
  toggleColumn: toggleRelationColumn,
  moveColumn: moveRelationColumn,
  resetColumns: resetRelationColumns,
} = useColumnConfig('columns:product-relations', relationCoreColumns, [], relationMetadataColumns)

// Anzeigewert einer Metadaten-Zelle: alle Werte des Attributs für diese Beziehung
// (mehrere möglich bei Sprache/Wiederholung) menschenlesbar zusammenfassen.
function getRelationMetaCell(rel, col) {
  const vals = (rel.attribute_values || []).filter(v => v.attribute_id === col.attribute_id)
  if (!vals.length) return ''
  return vals.map(getRelationAttrDisplayValue).filter(s => s !== '' && s != null).join(', ')
}

// Thumbnail-Cache für Zielprodukte (Beziehungen Kachelansicht)
const relationTargetThumbs = ref({}) // { productId: thumbUrl }

async function loadRelations() {
  if (relationsLoaded.value || !product.value) return
  relationsLoading.value = true
  try {
    const [relResp, typesResp] = await Promise.all([
      productsApi.getRelations(product.value.id),
      relationTypesList.value.length ? Promise.resolve(null) : relationTypes.list(),
    ])
    relations.value = relResp.data.data || relResp.data
    if (typesResp) relationTypesList.value = typesResp.data.data || typesResp.data
    relationsLoaded.value = true
    // Thumbnails der Zielprodukte im Hintergrund laden
    loadRelationThumbnails()
  } catch (e) { console.error('Failed to load relations:', e.message) }
  finally { relationsLoading.value = false }
}

async function loadRelationThumbnails() {
  const targetIds = relations.value
    .map(r => r.target_product?.id)
    .filter(id => id && !relationTargetThumbs.value[id])
  if (!targetIds.length) return
  // Parallel pro Zielprodukt: erstes Bild ermitteln
  const results = await Promise.allSettled(
    targetIds.map(async (pid) => {
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
  const updated = { ...relationTargetThumbs.value }
  for (const r of results) {
    if (r.status === 'fulfilled' && r.value.url) {
      updated[r.value.pid] = r.value.url
    }
  }
  relationTargetThumbs.value = updated
}

// ─── Virtuelle Produkte (dynamischer Cluster) ─────────
const virtualLoading = ref(false)
const virtualSaving = ref(false)
const virtualMembers = ref([])
const virtualMembersLoading = ref(false)
const virtualSearchProfiles = ref([])
const virtualMemberThumbs = ref({}) // { productId: thumbUrl }
const virtualForm = ref({
  source_type: 'manual',
  search_profile_id: '',
  pql_query: '',
  manual_product_ids: [],
  relation_type_id: '',
  max_members: null,
})
// Manuelle Auswahl: Produktsuche
const virtualProductSearch = ref('')
const virtualProductSearchResults = ref([])
const virtualManualProducts = ref([]) // [{ id, sku, name }] für Anzeige

async function loadVirtualCluster() {
  if (!product.value) return
  virtualLoading.value = true
  try {
    const [defResp, profilesResp, typesResp] = await Promise.all([
      productsApi.getVirtualDefinition(product.value.id),
      virtualSearchProfiles.value.length ? Promise.resolve(null) : searchProfilesApi.list(),
      relationTypesList.value.length ? Promise.resolve(null) : relationTypes.list(),
    ])
    if (profilesResp) {
      virtualSearchProfiles.value = profilesResp.data.data || profilesResp.data || []
    }
    if (typesResp) {
      relationTypesList.value = typesResp.data.data || typesResp.data || []
    }
    const def = defResp.data?.data ?? null
    if (def) {
      virtualForm.value = {
        source_type: def.source_type || 'manual',
        search_profile_id: def.search_profile_id || '',
        pql_query: def.pql_query || '',
        manual_product_ids: def.manual_product_ids || [],
        relation_type_id: def.relation_type_id || '',
        max_members: def.max_members ?? null,
      }
      await hydrateVirtualManualProducts(def.manual_product_ids || [])
    }
    await loadVirtualMembers()
    await loadVirtualInheritanceData()
    await loadVirtualMediaInheritanceData()
  } catch (e) { console.error('Failed to load virtual cluster:', e.message) }
  finally { virtualLoading.value = false }
}

async function loadVirtualMembers() {
  if (!product.value) return
  virtualMembersLoading.value = true
  try {
    const { data } = await productsApi.getVirtualMembers(product.value.id, { perPage: 100 })
    virtualMembers.value = data.data || data || []
    loadVirtualMemberThumbnails()
  } catch (e) { console.error('Failed to load virtual members:', e.message); virtualMembers.value = [] }
  finally { virtualMembersLoading.value = false }
}

async function loadVirtualMemberThumbnails() {
  const ids = virtualMembers.value.map(p => p.id).filter(id => id && !virtualMemberThumbs.value[id])
  if (!ids.length) return
  const results = await Promise.allSettled(ids.map(async (pid) => {
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
  }))
  const updated = { ...virtualMemberThumbs.value }
  for (const r of results) {
    if (r.status === 'fulfilled' && r.value.url) updated[r.value.pid] = r.value.url
  }
  virtualMemberThumbs.value = updated
}

async function hydrateVirtualManualProducts(ids) {
  if (!ids?.length) { virtualManualProducts.value = []; return }
  try {
    const results = await Promise.allSettled(ids.map(id => productsApi.get(id)))
    const byId = {}
    results.forEach((r, i) => {
      if (r.status === 'fulfilled') {
        const p = r.value.data?.data || r.value.data
        if (p) byId[ids[i]] = { id: p.id, sku: p.sku, name: p.name }
      }
    })
    // Reihenfolge gemäß ids beibehalten
    virtualManualProducts.value = ids.map(id => byId[id]).filter(Boolean)
  } catch (e) { console.warn('Hydrate manual products failed:', e.message) }
}

let virtualSearchTimeout = null
function searchVirtualProducts() {
  clearTimeout(virtualSearchTimeout)
  virtualSearchTimeout = setTimeout(async () => {
    if (!virtualProductSearch.value.trim()) { virtualProductSearchResults.value = []; return }
    try {
      const { data } = await productsApi.list({ search: virtualProductSearch.value, perPage: 10 })
      // Ein Produkt, das selbst schon als Cluster konfiguriert ist, wird vom
      // Resolver ohnehin nie als Mitglied aufgelöst (kein Cluster-im-Cluster) —
      // die Suche hier filtert daher nur das Produkt selbst und bereits
      // ausgewählte Mitglieder heraus.
      const selected = new Set(virtualForm.value.manual_product_ids)
      virtualProductSearchResults.value = (data.data || data)
        .filter(p => p.id !== product.value.id && !selected.has(p.id))
    } catch (e) { console.warn('Product search failed:', e.message); virtualProductSearchResults.value = [] }
  }, 300)
}

function addVirtualManualProduct(p) {
  virtualForm.value.manual_product_ids = [...virtualForm.value.manual_product_ids, p.id]
  virtualManualProducts.value = [...virtualManualProducts.value, p]
  virtualProductSearch.value = ''
  virtualProductSearchResults.value = []
}

function removeVirtualManualProduct(id) {
  virtualForm.value.manual_product_ids = virtualForm.value.manual_product_ids.filter(x => x !== id)
  virtualManualProducts.value = virtualManualProducts.value.filter(p => p.id !== id)
}

async function saveVirtualDefinition() {
  if (!product.value) return
  virtualSaving.value = true
  try {
    const payload = {
      source_type: virtualForm.value.source_type,
      relation_type_id: virtualForm.value.relation_type_id || null,
      max_members: virtualForm.value.max_members || null,
    }
    if (virtualForm.value.source_type === 'search_profile') payload.search_profile_id = virtualForm.value.search_profile_id
    if (virtualForm.value.source_type === 'pql') payload.pql_query = virtualForm.value.pql_query
    if (virtualForm.value.source_type === 'manual') payload.manual_product_ids = virtualForm.value.manual_product_ids
    await productsApi.saveVirtualDefinition(product.value.id, payload)
    toastStore.showToast('Cluster-Definition gespeichert', 'success')
    await loadVirtualMembers()
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Speichern fehlgeschlagen', 'error')
  } finally { virtualSaving.value = false }
}

async function virtualFromWatchlist() {
  if (!product.value) return
  virtualSaving.value = true
  try {
    const { data } = await productsApi.virtualDefinitionFromWatchlist(product.value.id)
    const def = data.data || data
    virtualForm.value.source_type = 'manual'
    virtualForm.value.manual_product_ids = def.manual_product_ids || []
    await hydrateVirtualManualProducts(virtualForm.value.manual_product_ids)
    toastStore.showToast('Merkliste übernommen', 'success')
    await loadVirtualMembers()
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Übernahme fehlgeschlagen', 'error')
  } finally { virtualSaving.value = false }
}

// ─── Vererbungsregeln (Phase 1: Attribute) ────────────
// Eigene Attribute der Klammer, aus denen Regeln ausgewählt werden können.
const virtualOwnAttributes = ref([]) // [{ attribute_id, name }]
// { [attribute_id]: { enabled: bool, conflict_mode: 'keep_local'|'force_override' } }
const virtualRules = ref({})
const virtualRulesLoading = ref(false)
const virtualRulesSaving = ref(false)
const virtualSyncing = ref(false)
const virtualSyncReport = ref(null)

async function loadVirtualInheritanceData() {
  if (!product.value) return
  virtualRulesLoading.value = true
  try {
    const [ownValuesResp, rulesResp] = await Promise.all([
      productsApi.getAttributeValues(product.value.id),
      productsApi.getVirtualInheritanceRules(product.value.id),
    ])
    const ownRows = ownValuesResp.data.data || ownValuesResp.data || []
    const byId = new Map()
    for (const row of ownRows) {
      if (!row.attribute_id || byId.has(row.attribute_id)) continue
      byId.set(row.attribute_id, {
        attribute_id: row.attribute_id,
        name: row.attribute?.name_de || row.attribute?.technical_name || row.attribute_id,
      })
    }
    virtualOwnAttributes.value = [...byId.values()].sort((a, b) => a.name.localeCompare(b.name, 'de'))

    const existingRules = rulesResp.data.data || rulesResp.data || []
    const rulesMap = {}
    for (const attr of virtualOwnAttributes.value) {
      const existing = existingRules.find(r => r.attribute_id === attr.attribute_id)
      rulesMap[attr.attribute_id] = {
        enabled: !!existing,
        conflict_mode: existing?.conflict_mode || 'keep_local',
      }
    }
    virtualRules.value = rulesMap
  } catch (e) { console.error('Failed to load inheritance rules:', e.message) }
  finally { virtualRulesLoading.value = false }
}

async function saveVirtualInheritanceRules() {
  if (!product.value) return
  virtualRulesSaving.value = true
  try {
    const rules = Object.entries(virtualRules.value)
      .filter(([, r]) => r.enabled)
      .map(([attribute_id, r]) => ({ attribute_id, conflict_mode: r.conflict_mode }))
    await productsApi.saveVirtualInheritanceRules(product.value.id, rules)
    toastStore.showToast('Vererbungsregeln gespeichert', 'success')
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Speichern fehlgeschlagen', 'error')
  } finally { virtualRulesSaving.value = false }
}

// ─── Vererbungsregeln (Phase 2: Medien) ───────────────
// { [usage_type_id]: { enabled: bool, conflict_mode: 'keep_local'|'force_override' } }
const virtualMediaRules = ref({})
const virtualMediaRulesLoading = ref(false)
const virtualMediaRulesSaving = ref(false)

async function loadVirtualMediaInheritanceData() {
  if (!product.value) return
  virtualMediaRulesLoading.value = true
  try {
    if (usageTypesList.value.length === 0) {
      const { data } = await mediaUsageTypes.list()
      usageTypesList.value = data.data || data
    }
    const { data } = await productsApi.getVirtualMediaInheritanceRules(product.value.id)
    const existingRules = data.data || data || []
    const rulesMap = {}
    for (const ut of productTypeUsageTypes.value) {
      const existing = existingRules.find(r => r.usage_type_id === ut.id)
      rulesMap[ut.id] = {
        enabled: !!existing,
        conflict_mode: existing?.conflict_mode || 'keep_local',
      }
    }
    virtualMediaRules.value = rulesMap
  } catch (e) { console.error('Failed to load media inheritance rules:', e.message) }
  finally { virtualMediaRulesLoading.value = false }
}

async function saveVirtualMediaInheritanceRules() {
  if (!product.value) return
  virtualMediaRulesSaving.value = true
  try {
    const rules = Object.entries(virtualMediaRules.value)
      .filter(([, r]) => r.enabled)
      .map(([usage_type_id, r]) => ({ usage_type_id, conflict_mode: r.conflict_mode }))
    await productsApi.saveVirtualMediaInheritanceRules(product.value.id, rules)
    toastStore.showToast('Medien-Vererbungsregeln gespeichert', 'success')
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Speichern fehlgeschlagen', 'error')
  } finally { virtualMediaRulesSaving.value = false }
}

async function syncVirtualCluster() {
  if (!product.value) return
  virtualSyncing.value = true
  virtualSyncReport.value = null
  try {
    const { data } = await productsApi.syncVirtualDefinition(product.value.id)
    virtualSyncReport.value = data.data || data
    toastStore.showToast('Synchronisierung abgeschlossen', 'success')
    await loadVirtualMembers()
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Synchronisierung fehlgeschlagen', 'error')
  } finally { virtualSyncing.value = false }
}

let searchTimeout = null
function searchProducts() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (!productSearch.value.trim()) { productSearchResults.value = []; return }
    productSearching.value = true
    try {
      const { data } = await productsApi.list({ search: productSearch.value, perPage: 10 })
      productSearchResults.value = (data.data || data).filter(p => p.id !== product.value.id)
    } catch (e) { console.warn('Product search failed:', e.message); productSearchResults.value = [] }
    finally { productSearching.value = false }
  }, 300)
}

function selectTargetProduct(p) {
  relationForm.value.target_product_id = p.id
  productSearch.value = `${p.sku} — ${p.name || ''}`
  productSearchResults.value = []
}

async function createRelation() {
  relationSaving.value = true
  relationErrors.value = {}
  try {
    await productsApi.createRelation(product.value.id, relationForm.value)
    showRelationForm.value = false
    relationForm.value = { relation_type_id: '', target_product_id: '', sort_order: 0 }
    productSearch.value = ''
    relationsLoaded.value = false
    await loadRelations()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        relationErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { relationSaving.value = false }
}

async function confirmDeleteRelation() {
  relationDeleting.value = true
  try {
    await productsApi.deleteRelation(relationDeleteTarget.value.id)
    relationDeleteTarget.value = null
    relationsLoaded.value = false
    await loadRelations()
  } finally { relationDeleting.value = false }
}

// ─── Relation Attribute Editing ──────────────────────
async function toggleRelationExpand(relation) {
  if (expandedRelationId.value === relation.id) {
    expandedRelationId.value = null
    return
  }
  expandedRelationId.value = relation.id
  await loadRelationAttrValues(relation.id)
  // Pre-populate default attributes from relation type if no attributes exist yet
  if (relationAttrValues.value.length === 0) {
    const defaults = relation.relation_type?.default_attributes || []
    if (defaults.length > 0) {
      relationAttrValues.value = defaults.map(attr => ({
        attribute_id: attr.id,
        attribute: attr,
        value_string: null,
        value_number: null,
        value_date: null,
        value_flag: null,
        value_selection_id: null,
        unit_id: null,
        language: null,
        multiplied_index: 0,
      }))
    }
  }
  if (!relationAttrListLoaded.value) {
    try {
      const { data } = await attributesApiDefault.list({ perPage: 9999 })
      relationAttrList.value = data.data || data
      relationAttrListLoaded.value = true
    } catch (e) { console.error('Failed to load attributes:', e.message) }
  }
}

async function loadRelationAttrValues(relationId) {
  relationAttrLoading.value = true
  try {
    const { data } = await productsApi.getRelationAttributeValues(relationId)
    relationAttrValues.value = (data.data || data).map(v => ({ ...v }))
  } catch (e) {
    console.error('Failed to load relation attribute values:', e.message)
    relationAttrValues.value = []
  } finally {
    relationAttrLoading.value = false
  }
}

function getRelationAttrValueField(attrVal) {
  const attr = attrVal.attribute
  if (!attr) return attrVal.value_string ?? attrVal.value_number ?? ''
  switch (attr.data_type) {
    case 'Number': case 'Float': return 'value_number'
    case 'Date': return 'value_date'
    case 'Flag': return 'value_flag'
    case 'Selection': return 'value_selection_id'
    default: return 'value_string'
  }
}

function getRelationAttrDisplayValue(attrVal) {
  if (attrVal.value_flag !== null && attrVal.value_flag !== undefined && attrVal.attribute?.data_type === 'Flag') return attrVal.value_flag ? 'Ja' : 'Nein'
  if (attrVal.value_selection_id && attrVal.value_list_entry) return attrVal.value_list_entry.value_de || attrVal.value_list_entry.code
  if (attrVal.value_string != null && attrVal.value_string !== '') return attrVal.value_string
  // value_number kommt als decimal:6-String ("1.800000") — überflüssige Nachkommanullen entfernen.
  if (attrVal.value_number != null && attrVal.value_number !== '') {
    const n = Number(attrVal.value_number)
    return Number.isFinite(n) ? String(n) : String(attrVal.value_number)
  }
  return attrVal.value_date ?? ''
}

function addRelationAttribute() {
  const attrId = newRelationAttr.value.attribute_id
  if (!attrId) return
  const attr = relationAttrList.value.find(a => a.id === attrId)
  if (!attr) return
  // Don't add duplicates
  if (relationAttrValues.value.some(v => v.attribute_id === attrId)) return
  relationAttrValues.value.push({
    attribute_id: attrId,
    attribute: attr,
    value_string: null,
    value_number: null,
    value_date: null,
    value_flag: null,
    value_selection_id: null,
    unit_id: null,
    language: null,
    multiplied_index: 0,
  })
  newRelationAttr.value.attribute_id = ''
}

function removeRelationAttribute(index) {
  relationAttrValues.value.splice(index, 1)
}

async function saveRelationAttrValues() {
  if (!expandedRelationId.value) return
  relationAttrSaving.value = true
  try {
    const values = relationAttrValues.value.map(v => {
      const entry = {
        attribute_id: v.attribute_id,
        language: v.language || null,
        multiplied_index: v.multiplied_index || 0,
      }
      if (v.value_string !== null && v.value_string !== '') entry.value_string = v.value_string
      if (v.value_number !== null && v.value_number !== '') entry.value_number = v.value_number
      if (v.value_date !== null && v.value_date !== '') entry.value_date = v.value_date
      if (v.value_flag !== null) entry.value_flag = v.value_flag
      if (v.value_selection_id) entry.value_selection_id = v.value_selection_id
      if (v.unit_id) entry.unit_id = v.unit_id
      return entry
    })
    const { data } = await productsApi.saveRelationAttributeValues(expandedRelationId.value, values)
    relationAttrValues.value = (data.data || data).map(v => ({ ...v }))
  } catch (e) {
    console.error('Failed to save relation attribute values:', e.message)
  } finally {
    relationAttrSaving.value = false
  }
}

function getPreviewCompositeSummary(compositeAttr, allAttrs) {
  return formatCompositeSummary({
    compositeFormat: compositeAttr.composite_format,
    children: allAttrs.filter(a => a.parent_attribute_id === compositeAttr.attribute_id),
    getValue: c => c.display_value,
  })
}

// ─── Output Hierarchy Assignments ──────────────────────
const outputHierarchyAssignments = ref([])
const outputHierarchyLoading = ref(false)
const outputHierarchyLoaded = ref(false)
const showOutputHierarchyForm = ref(false)
const selectedOutputHierarchyId = ref(null)
const selectedOutputNodeIds = ref([])
const outputHierarchyTreeNodes = ref([])
const outputHierarchyTreeLoading = ref(false)
const outputHierarchyDeleteTarget = ref(null)
const outputHierarchyDeleting = ref(false)
const bulkAssigningOutputNodes = ref(false)
const bulkAssignOutputError = ref('')

// Filter für die Zuordnungs-Tabelle (Tab-Ansicht)
const outputHierarchyFilterHierarchyId = ref('')
const outputHierarchyFilterNodeText = ref('')

// Liste der verfügbaren Ausgabehierarchien (für das "Zuordnung hinzufügen"-Formular) —
// global/statisch, daher einmalig geladen statt pro Produkt.
const outputHierarchiesList = ref([])
const outputHierarchiesListLoaded = ref(false)

async function loadOutputHierarchiesList() {
  if (outputHierarchiesListLoaded.value) return
  try {
    const { data } = await hierarchiesApi.list({ filters: { hierarchy_type: 'output' } })
    outputHierarchiesList.value = data.data || data
    outputHierarchiesListLoaded.value = true
  } catch (e) { console.error('Failed to load output hierarchies list:', e.message) }
}

const outputHierarchies = computed(() => outputHierarchiesList.value)

async function loadOutputHierarchyAssignments() {
  if (outputHierarchyLoaded.value || !product.value) return
  outputHierarchyLoading.value = true
  try {
    // Alle Seiten laden, nicht nur Seite 1 — bei vielen Zuordnungen (z.B. ganze
    // Länderbäume) reicht eine Serverseite sonst nicht für die filterbare Tabelle.
    const all = []
    let page = 1
    let lastPage = 1
    do {
      const { data } = await productsApi.getOutputHierarchyAssignments(product.value.id, { page, perPage: 100 })
      all.push(...(data.data || data))
      lastPage = data.meta?.last_page || 1
      page++
    } while (page <= lastPage)
    outputHierarchyAssignments.value = all
    outputHierarchyLoaded.value = true
  } catch (e) { console.error('Failed to load output hierarchy assignments:', e.message) }
  finally { outputHierarchyLoading.value = false }
}

// Bereits zugeordnete Knoten-IDs der im Formular gewählten Hierarchie — im Baum
// als "bereits zugeordnet" markiert, damit sie nicht doppelt ausgewählt werden.
const alreadyAssignedNodeIds = computed(() =>
  outputHierarchyAssignments.value
    .filter(a => a.hierarchy_node?.hierarchy_id === selectedOutputHierarchyId.value)
    .map(a => a.hierarchy_node?.id)
    .filter(Boolean)
)

async function onOutputHierarchyChange(hierarchyId) {
  selectedOutputHierarchyId.value = hierarchyId
  selectedOutputNodeIds.value = []
  outputHierarchyTreeNodes.value = []
  if (!hierarchyId) return
  outputHierarchyTreeLoading.value = true
  try {
    // Sicherstellen, dass die bestehenden Zuordnungen geladen sind, bevor der Baum
    // interaktiv wird — sonst könnten bereits zugeordnete Knoten kurzzeitig als
    // auswählbar erscheinen (loadOutputHierarchyAssignments ist idempotent).
    await loadOutputHierarchyAssignments()
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    outputHierarchyTreeNodes.value = data.data || data
  } catch (e) {
    console.error('Failed to load hierarchy tree:', e.message)
    outputHierarchyTreeNodes.value = []
  } finally {
    outputHierarchyTreeLoading.value = false
  }
}

function cancelOutputHierarchyForm() {
  showOutputHierarchyForm.value = false
  selectedOutputHierarchyId.value = null
  selectedOutputNodeIds.value = []
  outputHierarchyTreeNodes.value = []
  bulkAssignOutputError.value = ''
}

function toggleOutputHierarchyForm() {
  if (showOutputHierarchyForm.value) {
    cancelOutputHierarchyForm()
  } else {
    showOutputHierarchyForm.value = true
  }
}

async function bulkAssignSelectedOutputNodes() {
  if (!selectedOutputNodeIds.value.length || !product.value) return
  bulkAssigningOutputNodes.value = true
  bulkAssignOutputError.value = ''
  try {
    const { data } = await productsApi.bulkAssignOutputHierarchyNodes(product.value.id, selectedOutputNodeIds.value)
    const assigned = data.assigned ?? 0
    const skippedExisting = data.skipped_existing ?? 0
    const skippedUnauthorized = data.skipped_unauthorized ?? 0

    cancelOutputHierarchyForm()
    outputHierarchyLoaded.value = false
    await loadOutputHierarchyAssignments()

    if (assigned === 0) {
      toastStore.showToast(
        skippedUnauthorized > 0
          ? `Keine Zuordnung erstellt — ${skippedUnauthorized} Knoten ohne Berechtigung übersprungen.`
          : 'Keine neue Zuordnung erstellt — alle gewählten Knoten waren bereits zugeordnet.',
        'info',
      )
    } else if (skippedExisting > 0 || skippedUnauthorized > 0) {
      const parts = []
      if (skippedExisting > 0) parts.push(`${skippedExisting} bereits vorhanden`)
      if (skippedUnauthorized > 0) parts.push(`${skippedUnauthorized} ohne Berechtigung übersprungen`)
      toastStore.showToast(`${assigned} Zuordnung(en) erstellt (${parts.join(', ')}).`, 'info')
    } else {
      toastStore.showToast(`${assigned} Zuordnung(en) erstellt.`, 'success')
    }
  } catch (e) {
    bulkAssignOutputError.value = e.response?.data?.message || 'Zuordnung fehlgeschlagen.'
    console.error('Failed to bulk-assign output hierarchy nodes:', e.message)
  } finally {
    bulkAssigningOutputNodes.value = false
  }
}

// Gefilterte Zuordnungsliste für die Tabelle (Hierarchie-Auswahl + Freitext auf Knotenname)
const filteredOutputHierarchyAssignments = computed(() => {
  const nodeText = outputHierarchyFilterNodeText.value.trim().toLowerCase()
  return outputHierarchyAssignments.value.filter(a => {
    if (outputHierarchyFilterHierarchyId.value && a.hierarchy_node?.hierarchy_id !== outputHierarchyFilterHierarchyId.value) return false
    if (nodeText && !(a.hierarchy_node?.name_de || a.hierarchy_node?.name_en || '').toLowerCase().includes(nodeText)) return false
    return true
  })
})

async function confirmDeleteOutputHierarchyAssignment() {
  if (!outputHierarchyDeleteTarget.value) return
  outputHierarchyDeleting.value = true
  try {
    await hierarchiesApi.removeOutputProductAssignment(outputHierarchyDeleteTarget.value.id)
    outputHierarchyDeleteTarget.value = null
    outputHierarchyLoaded.value = false
    await loadOutputHierarchyAssignments()
  } catch (e) { console.error('Failed to remove output hierarchy assignment:', e.message) }
  finally { outputHierarchyDeleting.value = false }
}

// ─── Relationship Attributes (Beziehungs-Attribute auf Kante Produkt↔Knoten) ──
const expandedAssignmentId = ref(null)
const relationshipAttrs = ref([])
const relationshipAttrValues = ref({})
const relationshipAttrLoading = ref(false)
const relationshipAttrSaving = ref(false)

async function toggleRelationshipAttrs(assignmentId) {
  if (expandedAssignmentId.value === assignmentId) {
    expandedAssignmentId.value = null
    return
  }
  expandedAssignmentId.value = assignmentId
  relationshipAttrLoading.value = true
  relationshipAttrs.value = []
  relationshipAttrValues.value = {}
  try {
    const { data } = await hierarchiesApi.getRelationshipAttributes(assignmentId)
    const attrs = data.data || data
    relationshipAttrs.value = attrs
    // Werte in reaktives Objekt laden
    for (const attr of attrs) {
      relationshipAttrValues.value[attr.attribute_id] = attr.value ?? ''
    }
  } catch (e) {
    console.error('Failed to load relationship attributes:', e.message)
  } finally {
    relationshipAttrLoading.value = false
  }
}

async function saveRelationshipAttrs() {
  if (!expandedAssignmentId.value) return
  relationshipAttrSaving.value = true
  try {
    const values = relationshipAttrs.value.map(attr => ({
      attribute_id: attr.attribute_id,
      value: relationshipAttrValues.value[attr.attribute_id] ?? null,
      language: attr.is_translatable ? 'de' : null,
    }))
    await hierarchiesApi.saveRelationshipAttributes(expandedAssignmentId.value, values)
  } catch (e) {
    console.error('Failed to save relationship attributes:', e.message)
  } finally {
    relationshipAttrSaving.value = false
  }
}

// ─── Output Hierarchy Attributes (Channel Attributes) ──
const outputHierarchyAttributes = ref([])  // Array of { hierarchy_id, hierarchy_name_de, attributes: [...] }
const outputHierarchyAttrValues = ref({})  // { `${hierarchyId}_${attrId}`: value }
const outputHierarchyTranslatedValues = ref({})  // { `${hierarchyId}_${attrId}_${lang}`: value }
const outputHierarchyMultipliableValues = ref({})  // { `${hierarchyId}_${attrId}`: [{ value, multiplied_index }] }
const outputHierarchyAttrLoaded = ref(false)
const outputHierarchyAttrLoading = ref(false)
const outputHierarchySyncing = ref(false)

async function syncOutputHierarchyMappings(hierarchyId) {
  if (!product.value || outputHierarchySyncing.value) return
  outputHierarchySyncing.value = true
  try {
    await attributeMappingsApi.syncProduct(product.value.id, {
      target_hierarchy_id: hierarchyId,
    })
    // Reload output hierarchy attributes to show new synced values
    outputHierarchyAttrLoaded.value = false
    await loadOutputHierarchyAttributes()
  } catch (e) {
    console.error('Mapping-Sync fehlgeschlagen:', e.message)
  } finally {
    outputHierarchySyncing.value = false
  }
}

async function loadOutputHierarchyAttributes() {
  if (outputHierarchyAttrLoaded.value || !product.value) return
  outputHierarchyAttrLoading.value = true
  try {
    const { data } = await productsApi.getOutputHierarchyResolvedAttributes(product.value.id)
    const hierarchies = data.data || data
    outputHierarchyAttributes.value = hierarchies
    // Populate values
    for (const h of hierarchies) {
      for (const attr of (h.attributes || [])) {
        const key = `${h.hierarchy_id}_${attr.attribute_id}`
        if (attr.is_multipliable && attr.multiplied_values?.length) {
          outputHierarchyMultipliableValues.value[key] = attr.multiplied_values
        } else if (attr.value !== null && attr.value !== undefined) {
          if (attr.is_translatable) {
            const lang = activeDataLang.value || 'de'
            outputHierarchyTranslatedValues.value[`${key}_${lang}`] = attr.value
          } else {
            outputHierarchyAttrValues.value[key] = attr.value
          }
        }
      }
    }
    outputHierarchyAttrLoaded.value = true
  } catch (e) { console.error('Failed to load output hierarchy attributes:', e.message) }
  finally { outputHierarchyAttrLoading.value = false }
}

async function saveOutputHierarchyAttributes() {
  if (!product.value || outputHierarchyAttributes.value.length === 0) return
  for (const h of outputHierarchyAttributes.value) {
    const values = []
    for (const attr of (h.attributes || [])) {
      if (attr.is_multipliable) {
        const key = `${h.hierarchy_id}_${attr.attribute_id}`
        const entries = outputHierarchyMultipliableValues.value[key]
        if (entries?.length) {
          for (const entry of entries) {
            values.push({ attribute_id: attr.attribute_id, value: entry.value, multiplied_index: entry.multiplied_index })
          }
        }
      } else if (attr.is_translatable) {
        for (const lang of localeStore.activeDataLocales) {
          const key = `${h.hierarchy_id}_${attr.attribute_id}_${lang}`
          const val = outputHierarchyTranslatedValues.value[key]
          if (val !== undefined) {
            values.push({ attribute_id: attr.attribute_id, value: val, language: lang })
          }
        }
      } else {
        const key = `${h.hierarchy_id}_${attr.attribute_id}`
        const val = outputHierarchyAttrValues.value[key]
        if (val !== undefined) {
          values.push({ attribute_id: attr.attribute_id, value: val })
        }
      }
    }
    if (values.length > 0) {
      await productsApi.saveOutputHierarchyAttributeValues(product.value.id, h.hierarchy_id, values)
    }
  }
}

// ─── Preview (Generic) ───────────────────────────────
const PREVIEW_LINK_DATA_TYPES = ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']

function getPreviewVideoEmbedUrl(url) {
  if (!url) return null
  const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([\w-]+)/)
  if (yt) return `https://www.youtube.com/embed/${yt[1]}`
  const vim = url.match(/vimeo\.com\/(\d+)/)
  if (vim) return `https://player.vimeo.com/video/${vim[1]}`
  return null
}

const previewData = ref(null)
const previewLoading = ref(false)
const completenessData = ref(null)
const previewLang = ref(null) // null = alle Sprachen (kein Filter)

async function loadPreview() {
  if (!product.value) return
  previewLoading.value = true
  try {
    const params = previewLang.value ? { lang: previewLang.value } : {}
    const [prevResp, compResp] = await Promise.all([
      productsApi.getPreview(product.value.id, params),
      productsApi.getCompleteness(product.value.id),
    ])
    previewData.value = prevResp.data.data || prevResp.data
    completenessData.value = compResp.data.data || compResp.data
  } catch (e) { console.error('Failed to load preview:', e.message) }
  finally { previewLoading.value = false }
}

function switchPreviewLang(lang) {
  previewLang.value = lang
  loadPreview()
}

const previewVariantColumns = computed(() => {
  const base = [
    { key: 'sku', label: 'SKU', mono: true },
    { key: 'name', label: 'Name' },
    { key: 'ean', label: 'EAN', mono: true },
    { key: 'status', label: 'Status' },
  ]
  if (previewData.value?.variants?.[0]?.variant_attributes?.length) {
    for (const va of previewData.value.variants[0].variant_attributes) {
      base.push({ key: `_pva_${va.label}`, label: va.label })
    }
  }
  return base
})

const previewVariantRows = computed(() => {
  if (!previewData.value?.variants) return []
  return previewData.value.variants.map(v => {
    const row = { ...v }
    if (v.variant_attributes) {
      for (const va of v.variant_attributes) {
        row[`_pva_${va.label}`] = va.value ? `${va.value}${va.unit ? ' ' + va.unit : ''}` : '—'
      }
    }
    return row
  })
})

const excelLoading = ref(false)
const pdfLoading = ref(false)
const downloadError = ref(null)
const showPdfTemplatePicker = ref(false)

function triggerBlobDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  try {
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
  } finally {
    setTimeout(() => URL.revokeObjectURL(url), 200)
  }
}

async function downloadExcel() {
  excelLoading.value = true
  downloadError.value = null
  try {
    const resp = await productsApi.downloadPreviewExcel(product.value.id)
    triggerBlobDownload(resp.data, `product-preview-${product.value.sku || product.value.id}.xlsx`)
  } catch (err) {
    console.error('Excel download failed:', err)
    downloadError.value = 'Excel-Download fehlgeschlagen'
  } finally {
    excelLoading.value = false
  }
}

async function downloadPdf() {
  pdfLoading.value = true
  downloadError.value = null
  try {
    const resp = await productsApi.downloadPreviewPdf(product.value.id)
    triggerBlobDownload(resp.data, `product-preview-${product.value.sku || product.value.id}.pdf`)
  } catch (err) {
    console.error('PDF download failed:', err)
    downloadError.value = 'PDF-Download fehlgeschlagen'
  } finally {
    pdfLoading.value = false
  }
}

// ─── Mandatory Validation ─────────────────────────────
const mandatoryWarnings = ref(new Set())
const showMandatoryConfirm = ref(false)

function validateMandatoryAttributes() {
  const missing = []
  const primaryLang = localeStore.activeDataLocales[0] || 'de'
  for (const attr of schemaAttributes.value) {
    if (!attr.is_mandatory) continue
    if (attr.is_translatable) {
      // Only check the primary language for mandatory fields
      const key = `${attr.id}_${primaryLang}`
      const val = translatedValues.value[key]
      if (val === null || val === undefined || val === '') {
        missing.push(attr)
      }
    } else {
      const val = attributeValues.value[attr.id]
      if (val === null || val === undefined || val === '') {
        missing.push(attr)
      }
    }
  }
  return missing
}

async function saveWithValidation() {
  const missing = validateMandatoryAttributes()
  mandatoryWarnings.value = new Set(missing.map(a => a.id))
  if (missing.length > 0) {
    showMandatoryConfirm.value = true
    return
  }
  await save()
}

function confirmSaveDespiteWarnings() {
  showMandatoryConfirm.value = false
  save()
}

// ─── Save ─────────────────────────────────────────────
async function save() {
  if (!product.value) return
  saving.value = true
  try {
    // Save base product fields
    const updateData = {
      name: product.value.name,
      status: product.value.status,
      ean: product.value.ean,
      master_hierarchy_node_id: product.value.master_hierarchy_node_id || null,
      manufacturer_id: product.value.manufacturer_id || null,
      product_type_id: product.value.product_type_id || null,
    }
    if (workflowEnabled.value) {
      updateData.current_workflow_status_id = product.value.current_workflow_status_id || null
      updateData.workflow_assignee_id = product.value.workflow_assignee_id || null
      updateData.workflow_team_id = product.value.workflow_team_id || null
    }
    updateData.project_ids = selectedProjectIds.value
    await store.update(product.value.id, updateData)

    // Build attribute values payload with language support
    const values = []

    // Non-translatable attribute values
    for (const [attribute_id, value] of Object.entries(attributeValues.value)) {
      const entry = { attribute_id, value }
      if (unitValues.value[attribute_id]) entry.unit_id = unitValues.value[attribute_id]
      if (comparisonOperatorValues.value[attribute_id]) entry.comparison_operator_id = comparisonOperatorValues.value[attribute_id]
      values.push(entry)
    }

    // Translatable attribute values (one entry per language)
    for (const [key, value] of Object.entries(translatedValues.value)) {
      const lastUnderscore = key.lastIndexOf('_')
      const attribute_id = key.substring(0, lastUnderscore)
      const language = key.substring(lastUnderscore + 1)
      const entry = { attribute_id, value, language }
      if (unitValues.value[attribute_id]) entry.unit_id = unitValues.value[attribute_id]
      if (comparisonOperatorValues.value[attribute_id]) entry.comparison_operator_id = comparisonOperatorValues.value[attribute_id]
      values.push(entry)
    }

    // Multipliable attribute values (one entry per multiplied_index)
    for (const [attribute_id, entries] of Object.entries(multipliableValues.value)) {
      for (const e of entries) {
        const entry = {
          attribute_id,
          value: e.value,
          multiplied_index: e.multiplied_index,
        }
        if (e.unit_id) entry.unit_id = e.unit_id
        else if (unitValues.value[attribute_id]) entry.unit_id = unitValues.value[attribute_id]
        if (comparisonOperatorValues.value[attribute_id]) entry.comparison_operator_id = comparisonOperatorValues.value[attribute_id]
        values.push(entry)
      }
    }

    // Vermehrbare Composite-Werte: pro Instanz pro Kind einen Eintrag
    for (const [compositeId, instances] of Object.entries(multipliableCompositeValues.value)) {
      // Schema-Attribute für Übersetzbarkeit nachschlagen
      const compositeSchema = schemaAttributes.value.find(a => a.id === compositeId)
      const childSchemaMap = {}
      for (const child of compositeSchema?._children || []) {
        childSchemaMap[child.id] = child
        if (child._children) {
          for (const gc of child._children) {
            childSchemaMap[gc.id] = gc
          }
        }
      }

      for (const inst of instances) {
        for (const [childId, childValue] of Object.entries(inst.children || {})) {
          if (childValue && typeof childValue === 'object' && childValue.data_type === 'Composite') {
            // Sub-Composite: Enkel-Werte flach speichern
            for (const [gcId, gcValue] of Object.entries(childValue.children || {})) {
              const entry = {
                attribute_id: gcId,
                value: gcValue,
                multiplied_index: inst.multiplied_index,
              }
              // Übersetzbare Kinder: aktuelle Sprache mitgeben
              if (childSchemaMap[gcId]?.is_translatable) {
                entry.language = activeDataLang.value || 'de'
              }
              values.push(entry)
            }
          } else {
            const entry = {
              attribute_id: childId,
              value: childValue,
              multiplied_index: inst.multiplied_index,
            }
            if (childSchemaMap[childId]?.is_translatable) {
              entry.language = activeDataLang.value || 'de'
            }
            values.push(entry)
          }
        }
        // Auch den Composite-Parent selbst mit Index speichern (für Cleanup-Logik)
        values.push({
          attribute_id: compositeId,
          value: null,
          multiplied_index: inst.multiplied_index,
        })
      }
    }

    if (values.length > 0) {
      await store.saveAttributeValues(product.value.id, values)
    }

    // Save output hierarchy (channel) attribute values
    await saveOutputHierarchyAttributes()
  } finally {
    saving.value = false
  }
}

// ─── Hierarchy node change → reload attributes ───────
watch(() => product.value?.master_hierarchy_node_id, async (newNodeId, oldNodeId) => {
  if (newNodeId === oldNodeId) return
  // Reset attribute state and reload with new hierarchy
  attrLoaded.value = false
  schema.value = null
  attributeValues.value = {}
  translatedValues.value = {}
  unitValues.value = {}
  formattedValues.value = {}
  comparisonOperatorValues.value = {}
  valueListMap.value = {}
  await loadAttributeData(newNodeId || null)
})

// ─── Tab lazy loading ─────────────────────────────────
function isAttributeViewTabKey(tab) {
  return attributeViewTabs.value.some(t => t.key === tab)
}

watch(activeTab, (tab) => {
  if (tab === 'base-data') loadAttributeData()
  if (tab === 'attributes' || isAttributeViewTabKey(tab)) {
    loadAttributeData(); loadFilterOptions(); loadOutputHierarchyAttributes()
    if (product.value?.product_type?.allows_free_attributes) loadVirtualAttributeCatalog()
  }
  // Attribut-Sicht-Tabs zeigen immer die Master-Attribute (Sicht ist klassifikationsübergreifend) —
  // eine zuvor gewählte ETIM/ONYX-Klassifikations-Sub-Tab-Auswahl würde sonst zu einer falschen/leeren Liste führen.
  if (isAttributeViewTabKey(tab)) activeAttrSubTab.value = 'master'
  if (tab === 'variant-attributes') loadAttributeData()
  if (tab === 'variants') { loadVariants(); loadAttributeData() }
  if (tab === 'media') loadMedia()
  if (tab === 'prices') loadPrices()
  if (tab === 'relations') loadRelations()
  if (tab === 'virtual-cluster') loadVirtualCluster()
  if (tab === 'output-hierarchies') { loadOutputHierarchyAssignments(); loadOutputHierarchiesList() }
  if (tab === 'preview') loadPreview()
  if (tab === 'workflow-history') loadWorkflowHistory()
})

// Reset active tab if it becomes hidden (e.g. after product type change)
watch(tabs, (newTabs) => {
  if (!newTabs.find(t => t.key === activeTab.value)) {
    activeTab.value = newTabs[0]?.key || 'base-data'
  }
})

// Nach Versions-Aktivierung/-Wiederherstellung den kompletten Produktzustand
// neu laden – die Aktivierung ersetzt Basisfelder, Attribute, Preise, Medien
// und Beziehungen, daher reicht ein reines fetchOne() nicht aus.
async function reloadAfterVersionChange() {
  const id = product.value?.id || route.params.id
  await store.fetchOne(id)
  // "Geladen"-Guards zurücksetzen, sonst brechen die Loader sofort ab und
  // der Tab zeigt weiter den alten (vor der Aktivierung geladenen) Stand.
  attrLoaded.value = false
  pricesLoaded.value = false
  mediaLoaded.value = false
  relationsLoaded.value = false
  loadAttributeData()
  loadPrices()
  loadMedia()
  loadRelations()
}

onMounted(async () => {
  await store.fetchOne(route.params.id)
  // Update tab title with SKU instead of UUID
  if (product.value?.sku) {
    tabStore.updateTabTitle(route, `Produkt: ${product.value.sku}`)
  }
  loadAttributeData()
  loadManufacturers()
  loadProductTypes()
  loadProjects()
  loadMedia()         // Für Hauptbild-Vorschau im Header
  loadWatchlistIds()  // Merkliste-Status
  // Früh laden (nicht erst beim Öffnen des "Attribute"-Tabs), damit als
  // Sicht-Tab aktivierte Attribut-Sichten sofort in der Tab-Leiste erscheinen.
  loadFilterOptions()
  if (workflowEnabled.value) {
    loadWorkflowUsers()
    loadWorkflowTeams()
    loadAvailableTransitions()
  }
  // If variant, load parent's inheritance rules
  if (product.value?.product_type_ref === 'variant' && product.value?.parent_product_id) {
    loadInheritanceRules()
  }

  // Deep-Link (z. B. aus dem Änderungsprotokoll): optional direkt einen Tab
  // öffnen, sofern er für die Rolle verfügbar ist.
  if (route.query.tab && tabs.value.some(t => t.key === route.query.tab)) {
    selectTab(route.query.tab)
  }
})

// Re-load when navigating between products/variants (same component, different ID)
// Debounce: Bei schnellem Klicken durch Produktliste werden Zwischenprodukte übersprungen
let _switchDebounceTimer = null
watch(() => route.params.id, (newId, oldId) => {
  if (newId === oldId) return
  if (route.name !== 'product-detail') return

  clearTimeout(_switchDebounceTimer)
  _switchDebounceTimer = setTimeout(() => switchToProduct(newId), 150)
})

async function switchToProduct(newId) {
  // Alte Generation invalidieren — laufende Requests werden nach Rückkehr verworfen
  _loadGeneration++
  const gen = _loadGeneration

  // Reset all loaded flags
  attrLoaded.value = false
  variantsLoaded.value = false
  mediaLoaded.value = false
  pricesLoaded.value = false
  relationsLoaded.value = false
  outputHierarchyLoaded.value = false
  outputHierarchyAttrLoaded.value = false

  // Clear stale data
  schema.value = null
  attributeValues.value = {}
  translatedValues.value = {}
  unitValues.value = {}
  formattedValues.value = {}
  comparisonOperatorValues.value = {}
  outputHierarchyAttributes.value = []
  outputHierarchyAttrValues.value = {}
  outputHierarchyTranslatedValues.value = {}
  activeAttrSubTab.value = 'master'
  variants.value = []
  variantAttributeDefs.value = []
  variantAttrValuesMap.value = {}
  mediaItems.value = []
  selectedMediaIds.value = new Set()
  prices.value = []
  relations.value = []
  expandedRelationId.value = null
  relationAttrValues.value = []
  relationTargetThumbs.value = {}
  relationQuickLookup.value = {}
  relationFilter.value = ''
  previewData.value = null
  completenessData.value = null

  // Reset inheritance state
  inheritanceRulesLoaded.value = false
  editedInheritanceRules.value = {}
  showInheritanceRules.value = false

  // Close open forms
  showVariantForm.value = false
  showPriceForm.value = false
  showRelationForm.value = false
  showMediaPicker.value = false
  showCopyDialog.value = false
  showGenerator.value = false

  // Reset tab to attributes
  activeTab.value = 'attributes'

  // Reload product/variant data
  await store.fetchOne(newId)
  if (gen !== _loadGeneration) return // Nächster Wechsel kam dazwischen

  // Update tab title with SKU
  if (product.value?.sku) {
    tabStore.updateTabTitle(route, `Produkt: ${product.value.sku}`)
  }
  loadAttributeData(null, gen)
  loadMedia()         // Für Hauptbild-Vorschau im Header
  // If variant, load parent's inheritance rules
  if (product.value?.product_type_ref === 'variant' && product.value?.parent_product_id) {
    loadInheritanceRules()
  }
}

onUnmounted(() => {
  clearTimeout(_switchDebounceTimer)
  _loadGeneration++ // Invalidiert noch laufende Requests
})
</script>

<template>
  <div class="space-y-4" data-testid="product-detail">
    <!-- Header -->
    <div class="pim-card flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2.5 sm:px-4 sm:py-3 bg-gradient-to-r from-[var(--color-surface)] to-[color-mix(in_srgb,var(--color-accent)_4%,var(--color-surface))]">
      <button class="pim-btn pim-btn-ghost p-1.5" @click="router.push('/products')">
        <ArrowLeft class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <div class="flex-1">
        <div v-if="store.loading" class="space-y-2">
          <div class="pim-skeleton h-5 w-48 rounded" />
          <div class="pim-skeleton h-3 w-32 rounded" />
        </div>
        <template v-else-if="product">
          <div class="flex items-center gap-2.5">
            <img v-if="primaryMediaThumb" :src="primaryMediaThumb" class="w-10 h-10 rounded-lg object-cover border border-[var(--color-border)] shadow-sm shrink-0" alt="" />
            <h2 class="text-lg font-semibold text-[var(--color-text-primary)] tracking-tight">
              {{ product.name || product.sku }}
            </h2>
            <span v-if="product.product_type_ref === 'variant'" class="pim-badge bg-purple-100 text-purple-700 text-[10px] px-1.5 py-0.5 rounded">
              Variante
            </span>
            <span
              v-if="product.current_workflow_status"
              class="pim-badge text-[10px] px-1.5 py-0.5 rounded-full font-medium"
              :style="{ background: (product.current_workflow_status.color || '#6b7280') + '20', color: product.current_workflow_status.color || '#6b7280' }"
            >
              {{ product.current_workflow_status.name }}
            </span>
          </div>
          <p class="text-xs text-[var(--color-text-tertiary)] font-mono">
            {{ product.sku }}
            <router-link
              v-if="product.parent_product_id"
              class="ml-2 text-xs text-[var(--color-accent)] hover:underline"
              :to="`/products/${product.parent_product_id}`"
            >
              ← Zum Elternprodukt
            </router-link>
          </p>
        </template>
      </div>
      <!-- Navigator: vor/zurück innerhalb der Kontextliste (z.B. Merkliste) -->
      <div
        v-if="hasRecordNav"
        class="flex items-center gap-0.5 px-1 py-1 rounded-md border border-[var(--color-border)] bg-[var(--color-bg)] shrink-0"
        :title="`${recordNavigatorStore.label}: Produkt ${recordNavIndex + 1} von ${recordNavTotal}`"
      >
        <button
          class="pim-btn pim-btn-ghost p-1 disabled:opacity-30"
          :disabled="!recordNavPrevId"
          title="Vorheriges Produkt"
          @click="goToPrevRecord"
        >
          <ChevronLeft class="w-4 h-4" :stroke-width="1.75" />
        </button>
        <span class="text-xs font-medium text-[var(--color-text-secondary)] tabular-nums whitespace-nowrap px-0.5">
          {{ recordNavIndex + 1 }}/{{ recordNavTotal }}
        </span>
        <button
          class="pim-btn pim-btn-ghost p-1 disabled:opacity-30"
          :disabled="!recordNavNextId"
          title="Nächstes Produkt"
          @click="goToNextRecord"
        >
          <ChevronRight class="w-4 h-4" :stroke-width="1.75" />
        </button>
      </div>

      <button
        v-if="product"
        class="pim-btn pim-btn-ghost p-1.5"
        :title="isOnWatchlist ? 'Von Merkliste entfernen' : 'Zur Merkliste hinzufügen'"
        @click="toggleWatchlist"
      >
        <Star class="w-4 h-4" :stroke-width="1.75" :class="isOnWatchlist ? 'text-amber-500 fill-amber-500' : 'text-[var(--color-text-tertiary)]'" />
      </button>

      <!-- Notizen-Badges (offene Notizen pro Typ) -->
      <template v-if="product">
        <button
          v-for="badge in NOTE_BADGE_TYPES.filter(b => noteOpenCounts[b.key] > 0)"
          :key="badge.key"
          class="flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold border transition-opacity hover:opacity-80"
          :style="{ background: badge.bg, borderColor: badge.border, color: badge.text }"
          title="Zu Notizen"
          @click="activeTab = 'notes'"
        >
          <component :is="badge.icon" class="w-3.5 h-3.5" :stroke-width="2" />
          {{ noteOpenCounts[badge.key] }}
        </button>
      </template>

      <button
        v-if="product"
        class="pim-btn pim-btn-secondary text-xs"
        title="Produkt im Katalog ansehen"
        @click="openPreview"
      >
        <Eye class="w-4 h-4" :stroke-width="1.75" />
        <span class="hidden sm:inline">Vorschau</span>
      </button>
      <button
        v-if="product"
        class="pim-btn pim-btn-secondary text-xs"
        @click="showPdfTemplatePicker = true"
      >
        <FileText class="w-4 h-4" :stroke-width="1.75" />
        <span class="hidden sm:inline">PDF-Vorlage</span>
      </button>
      <button
        v-if="authStore.hasPermission('products.create') && product && product.product_type_ref !== 'variant'"
        class="pim-btn pim-btn-secondary text-xs"
        @click="showCopyDialog = true"
      >
        <Copy class="w-4 h-4" :stroke-width="1.75" />
        <span class="hidden sm:inline">Kopieren</span>
      </button>
      <button v-if="authStore.hasPermission('products.edit') && !isTabReadOnly" class="pim-btn pim-btn-primary" :disabled="saving" @click="saveWithValidation">
        <Save class="w-4 h-4" :stroke-width="1.75" />
        {{ saving ? 'Speichern…' : t('common.save') }}
      </button>
    </div>

    <!-- Start Workflow Bar (product has no workflow but ProductType has one) -->
    <div
      v-if="!workflowEnabled && productTypeHasWorkflow && product && authStore.hasPermission('products.edit')"
      class="flex items-center gap-3 px-4 py-2.5 rounded-lg border border-dashed border-[var(--color-border)] bg-[var(--color-surface)]"
    >
      <GitBranch class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
      <span class="text-xs text-[var(--color-text-secondary)]">
        Der Produkttyp <strong>{{ product.product_type?.name_de || product.product_type?.technical_name }}</strong> hat einen zugeordneten Workflow.
      </span>
      <button
        class="pim-btn pim-btn-sm text-xs font-medium px-3 py-1.5 rounded-md bg-[var(--color-accent)] text-white ml-auto"
        :disabled="workflowSaving"
        @click="startWorkflow"
      >
        Workflow starten
      </button>
      <p v-if="workflowError" class="w-full text-xs text-[var(--color-error)] mt-1">{{ workflowError }}</p>
    </div>

    <!-- Workflow Bar (dynamic FK-based) -->
    <div
      v-if="workflowEnabled && product && authStore.hasPermission('products.edit')"
      class="flex flex-wrap items-center gap-3 px-4 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]"
    >
      <GitBranch class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />

      <!-- Current status badge -->
      <span
        v-if="product.current_workflow_status"
        class="pim-badge text-[11px] px-2 py-0.5 rounded-full font-medium"
        :style="{ background: (product.current_workflow_status.color || '#6b7280') + '20', color: product.current_workflow_status.color || '#6b7280' }"
      >
        {{ product.current_workflow_status.name }}
      </span>
      <span v-else class="text-xs text-[var(--color-text-secondary)]">Kein Status aktiv</span>

      <!-- Assignee select -->
      <select
        class="pim-input text-xs w-36"
        :value="product.workflow_assignee_id || ''"
        :disabled="workflowSaving"
        @change="updateWorkflowAssignee($event.target.value || null)"
      >
        <option value="">— Bearbeiter —</option>
        <option v-for="u in workflowUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>

      <!-- Team select -->
      <select
        class="pim-input text-xs w-36"
        :value="product.workflow_team_id || ''"
        :disabled="workflowSaving"
        @change="updateWorkflowTeam($event.target.value || null)"
      >
        <option value="">— Team —</option>
        <option v-for="team in workflowTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
      </select>

      <!-- Transition buttons -->
      <div class="flex items-center gap-2 ml-auto">
        <button
          v-for="tr in availableTransitions"
          :key="tr.to_status_id"
          class="pim-btn text-xs font-medium px-3 py-1.5 rounded-md border transition-colors"
          :style="{
            borderColor: tr.to_status?.color || 'var(--color-border)',
            color: tr.to_status?.color || 'var(--color-text-primary)',
          }"
          :disabled="workflowSaving"
          @click="transitionTo(tr.to_status_id)"
        >
          {{ tr.name || tr.to_status?.name }}
        </button>
        <button
          v-if="product.current_workflow_status"
          class="pim-btn pim-btn-ghost text-xs"
          :disabled="workflowSaving"
          @click="cancelWorkflow"
        >
          Abbrechen
        </button>
      </div>

      <p v-if="workflowError" class="w-full text-xs text-[var(--color-error)] mt-1">{{ workflowError }}</p>
    </div>

    <!-- Copy Dialog -->
    <div v-if="showCopyDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showCopyDialog = false">
      <div class="pim-card p-6 w-full max-w-sm space-y-4 shadow-xl">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Produkt kopieren</h3>
          <button class="pim-btn pim-btn-ghost p-1" @click="showCopyDialog = false"><X class="w-4 h-4" /></button>
        </div>
        <p class="text-xs text-[var(--color-text-secondary)]">Was soll in die Kopie übernommen werden?</p>
        <div class="space-y-2">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_attributes" class="pim-checkbox" />
            Attributwerte
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_prices" class="pim-checkbox" />
            Preise
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_media" class="pim-checkbox" />
            Medien-Zuordnungen
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_relations" class="pim-checkbox" />
            Relationen
          </label>
        </div>
        <div class="flex gap-3 text-xs text-[var(--color-accent)]">
          <button class="hover:underline" @click="selectAllCopyOptions(true)">Alle auswählen</button>
          <button class="hover:underline" @click="selectAllCopyOptions(false)">Keine auswählen</button>
        </div>
        <div class="flex gap-2 pt-2">
          <button class="pim-btn pim-btn-primary text-xs flex-1" :disabled="copying" @click="duplicateProduct">
            <Copy class="w-3.5 h-3.5" :stroke-width="2" />
            {{ copying ? 'Wird kopiert…' : 'Kopie erstellen' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showCopyDialog = false">Abbrechen</button>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[var(--color-border)] flex items-stretch">
      <nav class="flex gap-0.5 -mb-px overflow-x-auto scrollbar-none flex-1 min-w-0">
        <template v-for="group in navMainGroups" :key="group.key">
          <!-- Einzelner Reiter -->
          <button
            v-if="group.type === 'leaf'"
            :data-testid="'tab-' + group.key"
            :class="tabBtnClass(activeTab === group.key)"
            @click="selectTab(group.key)"
          >
            {{ group.label }}
            <Eye v-if="authStore.getTabAccess(group.key) === 'read'" class="w-3 h-3 inline-block ml-1 opacity-50" :stroke-width="1.75" />
          </button>

          <!-- Varianten-Gruppe (Sub-Tabs erscheinen in der Zeile darunter) -->
          <button
            v-else-if="group.type === 'subtabs'"
            :data-testid="'tab-' + group.key"
            :class="tabBtnClass(isVariantTabActive)"
            @click="openVariantGroup"
          >
            <LayoutGrid class="w-3.5 h-3.5 mr-1.5 opacity-70" :stroke-width="1.75" />
            {{ group.label }}
          </button>
        </template>
      </nav>

      <!-- "Mehr"-Dropdown (außerhalb des scrollenden nav, damit es nicht abgeschnitten wird) -->
      <div v-if="moreGroup" class="relative shrink-0 -mb-px flex items-stretch">
        <button
          :data-testid="'tab-' + moreGroup.key"
          :class="tabBtnClass(isMoreTabActive)"
          @click="moreMenuOpen = !moreMenuOpen"
        >
          {{ moreGroup.label }}
          <ChevronDown class="w-3.5 h-3.5 ml-1 transition-transform duration-150" :class="{ 'rotate-180': moreMenuOpen }" :stroke-width="2" />
        </button>
        <!-- Klick-außerhalb-Backdrop -->
        <div v-if="moreMenuOpen" class="fixed inset-0 z-20" @click="moreMenuOpen = false" />
        <div
          v-if="moreMenuOpen"
          class="absolute right-0 top-full mt-1.5 z-30 min-w-48 py-1.5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg"
        >
          <button
            v-for="child in moreGroup.children"
            :key="child.key"
            :data-testid="'tab-' + child.key"
            class="w-full text-left px-3.5 py-2 text-[13px] flex items-center gap-2 transition-colors"
            :class="activeTab === child.key
              ? 'text-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] font-medium'
              : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'"
            @click="selectTab(child.key)"
          >
            {{ child.label }}
            <Eye v-if="authStore.getTabAccess(child.key) === 'read'" class="w-3 h-3 ml-auto opacity-50" :stroke-width="1.75" />
          </button>
        </div>
      </div>
    </div>

    <!-- Varianten Sub-Tab-Zeile -->
    <div
      v-if="isVariantTabActive && variantSubTabs.length"
      class="flex items-center gap-1 px-1 py-1.5 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)] w-fit"
    >
      <button
        v-for="child in variantSubTabs"
        :key="child.key"
        :data-testid="'subtab-' + child.key"
        class="px-3.5 py-1.5 text-[12px] font-medium rounded-md transition-all duration-150"
        :class="activeTab === child.key
          ? 'bg-[var(--color-surface)] text-[var(--color-accent)] shadow-sm'
          : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'"
        @click="selectTab(child.key)"
      >
        {{ child.label }}
      </button>
    </div>

    <!-- Read-only banner -->
    <div v-if="isTabReadOnly" class="bg-[var(--color-warning)]/10 border border-[var(--color-warning)]/30 rounded-lg px-3 py-2 flex items-center gap-2">
      <Eye class="w-4 h-4 text-[var(--color-warning)]" :stroke-width="1.75" />
      <span class="text-xs text-[var(--color-warning)]">Dieser Tab ist schreibgeschützt für Ihre Rolle.</span>
    </div>

    <!-- Tab content -->
    <div v-if="store.loading" class="space-y-4">
      <div class="pim-card p-6">
        <div class="space-y-4">
          <div class="pim-skeleton h-4 w-1/3 rounded" />
          <div class="pim-skeleton h-8 w-full rounded" />
          <div class="pim-skeleton h-4 w-1/4 rounded" />
          <div class="pim-skeleton h-8 w-full rounded" />
        </div>
      </div>
    </div>

    <!-- ═══ Base Data Tab ═══ -->
    <div v-else-if="activeTab === 'base-data' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <PimCollectionGroup title="Stammdaten" :filledCount="3" :totalCount="5">
        <div class="space-y-2 pt-3">
          <div class="md:flex md:items-center md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block">SKU</label>
            <div class="md:flex-1 md:min-w-0">
              <input class="pim-input font-mono" :value="product.sku" readonly />
            </div>
          </div>
          <div class="md:flex md:items-center md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block">Name</label>
            <div class="md:flex-1 md:min-w-0">
              <input class="pim-input" v-model="product.name" />
            </div>
          </div>
          <div v-if="!product.product_type || product.product_type.has_ean !== false" class="md:flex md:items-center md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block">EAN</label>
            <div class="md:flex-1 md:min-w-0">
              <input class="pim-input font-mono" v-model="product.ean" />
            </div>
          </div>
          <div class="md:flex md:items-center md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block">Status</label>
            <div class="md:flex-1 md:min-w-0">
              <PimAttributeInput
                type="select"
                v-model="product.status"
                :options="[{ value: 'active', label: 'Aktiv' }, { value: 'draft', label: 'Entwurf' }, { value: 'inactive', label: 'Inaktiv' }, { value: 'discontinued', label: 'Auslaufend' }]"
              />
            </div>
          </div>
          <div class="md:flex md:items-center md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block">Produkttyp</label>
            <div class="md:flex-1 md:min-w-0">
              <select class="pim-input text-xs" :value="product.product_type_id || ''" @change="product.product_type_id = $event.target.value || null">
                <option v-for="pt in productTypesList" :key="pt.id" :value="pt.id">{{ pt.name_de || pt.technical_name }}</option>
              </select>
            </div>
          </div>
          <div v-if="product.product_type_ref !== 'variant'" class="md:flex md:items-start md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 md:mt-2 mb-1 block">Master-Hierarchie-Knoten</label>
            <div class="md:flex-1 md:min-w-0 flex items-center gap-2">
              <div class="pim-input text-xs flex-1 flex items-center gap-1.5 min-h-[30px]" :class="masterNodePath ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)] italic'">
                <FolderTree class="w-3.5 h-3.5 shrink-0 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                <span class="truncate">{{ masterNodePath || 'Kein Knoten zugeordnet' }}</span>
              </div>
              <button type="button" class="pim-btn pim-btn-secondary text-xs shrink-0" @click="openMasterNodePicker">
                {{ product.master_hierarchy_node_id ? 'Ändern' : 'Auswählen' }}
              </button>
              <button
                v-if="product.master_hierarchy_node_id"
                type="button"
                class="pim-btn pim-btn-ghost p-1.5 shrink-0"
                title="Zuordnung entfernen"
                @click="clearMasterNode"
              >
                <X class="w-3.5 h-3.5" :stroke-width="1.75" />
              </button>
            </div>
          </div>
          <div class="md:flex md:items-center md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block">Hersteller</label>
            <div class="md:flex-1 md:min-w-0">
              <select class="pim-input text-xs" :value="product.manufacturer_id || ''" @change="product.manufacturer_id = $event.target.value || null">
                <option value="">— Kein Hersteller —</option>
                <option v-for="m in manufacturers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
          </div>
          <!-- Primärattribute -->
          <div
            v-for="attr in primaryAttributes"
            :key="'primary-' + attr.id"
            :class="['md:flex md:gap-4', ['RichText', 'Textarea'].includes(attr.data_type) ? 'md:items-start' : 'md:items-center']"
          >
            <label
              class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block truncate"
              :class="{ 'md:mt-2': ['RichText', 'Textarea'].includes(attr.data_type) }"
              :title="attr.name_de || attr.technical_name"
            >
              {{ attr.name_de || attr.technical_name }}
              <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
              <span v-if="attr.is_translatable" class="ml-1 text-[10px] text-[var(--color-accent)] font-normal">
                <Languages class="inline w-3 h-3 -mt-0.5" :stroke-width="1.75" /> {{ activeDataLang.toUpperCase() }}
              </span>
            </label>
            <div class="md:flex-1 md:min-w-0">
              <div class="flex gap-1.5 items-start">
                <PimAttributeInput
                  class="flex-1 min-w-0"
                  :type="mapDataTypeToInput(attr.data_type)"
                  :modelValue="attr.is_translatable ? translatedValues[`${attr.id}_${activeDataLang}`] : attributeValues[attr.id]"
                  :options="['Selection', 'MultiSelection', 'SimpleSelect', 'SimpleMultiSelect'].includes(attr.data_type) ? getSelectionOptions(attr) : (attr.data_type === 'Dictionary' ? dictionaryEntries : [])"
                  :disabled="attr._access === 'read_only' || attr.is_readonly || isTabReadOnly"
                  :delimiter="attr.delimiter || '|'"
                  :min="attr.min_value != null ? Number(attr.min_value) : undefined"
                  :max="attr.max_value != null ? Number(attr.max_value) : undefined"
                  :rows="attr.textarea_rows || undefined"
                  :cols="attr.textarea_cols || undefined"
                  @update:modelValue="attr.is_translatable ? (translatedValues[`${attr.id}_${activeDataLang}`] = $event) : (attributeValues[attr.id] = $event)"
                />
                <select
                  v-if="['Number', 'Float'].includes(attr.data_type) && attr.unit_group?.units?.length"
                  class="pim-input text-[12px] !w-16 !min-w-0 shrink-0 !px-1"
                  :value="unitValues[attr.id] || ''"
                  :disabled="attr._access === 'read_only' || attr.is_readonly || isTabReadOnly"
                  @change="unitValues[attr.id] = $event.target.value || null"
                >
                  <option value="">—</option>
                  <option v-for="u in attr.unit_group.units" :key="u.id" :value="u.id">{{ u.abbreviation }}</option>
                </select>
              </div>
              <p v-if="attr.formatted_value ?? formattedValues[attr.id]" class="flex items-center gap-1 text-[11px] text-[var(--color-text-tertiary)] mt-1" :title="'Formatierte Vorschau (nur bei Export/Ansicht angewendet)'">
                <Wand2 class="w-3 h-3 shrink-0" :stroke-width="1.75" />
                {{ attr.formatted_value ?? formattedValues[attr.id] }}
              </p>
            </div>
          </div>
        </div>
      </PimCollectionGroup>

      <PimCollectionGroup title="Beschreibung" :filledCount="1" :totalCount="3" :defaultOpen="false">
        <div class="space-y-2 pt-3">
          <div class="md:flex md:items-start md:gap-4">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 md:mt-2 mb-1 block">Kurzbeschreibung</label>
            <div class="md:flex-1 md:min-w-0">
              <PimAttributeInput type="textarea" v-model="product.description_short" />
            </div>
          </div>
        </div>
      </PimCollectionGroup>

      <PimCollectionGroup title="Projekte" :filledCount="selectedProjectIds.length" :totalCount="availableProjects.length" :defaultOpen="false">
        <div class="space-y-3 pt-3">
          <div v-if="selectedProjectIds.length" class="flex flex-wrap gap-1.5">
            <span
              v-for="pid in selectedProjectIds"
              :key="pid"
              class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] border border-[var(--color-accent)]/20"
            >
              {{ availableProjects.find(p => p.id === pid)?.name || pid }}
              <button class="hover:text-[var(--color-error)]" @click="selectedProjectIds = selectedProjectIds.filter(id => id !== pid)">
                <X class="w-3 h-3" :stroke-width="2" />
              </button>
            </span>
          </div>
          <div class="max-h-40 overflow-y-auto border border-[var(--color-border)] rounded">
            <button
              v-for="proj in availableProjects"
              :key="proj.id"
              :class="[
                'w-full flex items-center gap-2 px-3 py-2 text-xs hover:bg-[var(--color-bg)] transition-colors cursor-pointer text-left',
                selectedProjectIds.includes(proj.id) ? 'bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]' : ''
              ]"
              @click="selectedProjectIds.includes(proj.id) ? selectedProjectIds = selectedProjectIds.filter(id => id !== proj.id) : selectedProjectIds = [...selectedProjectIds, proj.id]"
            >
              <input type="checkbox" :checked="selectedProjectIds.includes(proj.id)" class="pointer-events-none" />
              <span class="text-[var(--color-text-primary)]">{{ proj.name }}</span>
              <span v-if="proj.status" class="text-[var(--color-text-tertiary)]">({{ proj.status }})</span>
            </button>
          </div>
          <p v-if="availableProjects.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Projekte vorhanden.</p>
        </div>
      </PimCollectionGroup>
    </div>

    <!-- ═══ Attributes Tab ═══ -->
    <div v-else-if="(activeTab === 'attributes' || activeAttributeViewId) && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <!-- Sub-Tabs: Master Node Name | ETIM | ONYX | ... — im dynamischen Sicht-Tab
           ausgeblendet, da eine Attribut-Sicht klassifikationsübergreifend (nur Master-
           Attribute) ist; ein Wechsel würde die Sicht-Beschränkung und deren Nur-Lesen-
           Overrides sonst umgehen. -->
      <div v-if="!activeAttributeViewId" class="flex gap-0 border border-[var(--color-border)] rounded-lg overflow-hidden">
        <button
          :class="[
            'px-4 py-1.5 text-xs font-medium transition-colors',
            activeAttrSubTab === 'master'
              ? 'bg-indigo-600 text-white'
              : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]',
          ]"
          :title="masterNodePath || 'Attribute'"
          @click="activeAttrSubTab = 'master'"
        >
          {{ masterNodeName || 'Attribute' }}
        </button>
        <button
          v-for="h in outputHierarchyAttributes"
          :key="h.hierarchy_id"
          :class="[
            'px-4 py-1.5 text-xs font-medium transition-colors border-l border-[var(--color-border)]',
            activeAttrSubTab === h.hierarchy_id
              ? 'bg-indigo-600 text-white'
              : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]',
          ]"
          @click="activeAttrSubTab = h.hierarchy_id"
        >
          {{ h.hierarchy_name_de || h.hierarchy_technical_name }}
        </button>
      </div>

      <!-- ══ Master Attributes (Sub-Tab) ══ -->
      <template v-if="activeAttrSubTab === 'master'">
      <!-- Filter bar -->
      <div class="pim-card p-3">
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative flex-1 min-w-[200px] max-w-sm">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)] z-10 pointer-events-none" :stroke-width="1.75" />
            <input v-model="attrFilterSearch" class="pim-input text-xs pim-input-icon w-full" placeholder="Attribut suchen (Name, ID oder Wert)…" />
          </div>
          <select v-if="!activeAttributeViewId" v-model="attrFilterView" class="pim-select text-xs">
            <option :value="null">Alle Sichten</option>
            <option v-for="view in productTypeAttrViews" :key="view.id" :value="view.id">
              {{ view.name_de || view.technical_name }}
            </option>
          </select>
          <select v-model="attrFilterGroup" class="pim-select text-xs">
            <option :value="null">Alle Gruppen</option>
            <option v-for="group in availableAttrGroups" :key="group.id" :value="group.id">
              {{ group.name_de || group.technical_name }}
            </option>
          </select>
          <label class="flex items-center gap-1.5 cursor-pointer select-none">
            <input type="checkbox" v-model="attrFilterMandatory" class="w-3.5 h-3.5 rounded border-[var(--color-border)] accent-[var(--color-error)]" />
            <span class="text-xs text-[var(--color-text-secondary)]">Nur Pflichtfelder</span>
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer select-none">
            <input type="checkbox" v-model="attrFilterFilledOnly" class="w-3.5 h-3.5 rounded border-[var(--color-border)] accent-[var(--color-accent)]" />
            <span class="text-xs text-[var(--color-text-secondary)]">Nur gefüllte Attribute</span>
          </label>
        </div>
        <div v-if="attrFilterSearch || (!activeAttributeViewId && attrFilterView) || attrFilterGroup || attrFilterMandatory || attrFilterFilledOnly" class="flex items-center gap-2 mt-2">
          <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ filteredAttributes.length }} Attribute</span>
          <button class="text-[11px] text-[var(--color-accent)] hover:underline" @click="attrFilterSearch = ''; attrFilterView = null; attrFilterGroup = null; attrFilterMandatory = false; attrFilterFilledOnly = false">
            Filter zurücksetzen
          </button>
        </div>
      </div>

      <!-- Freier Attribut-Picker (Produkttyp erlaubt freie Attribute) -->
      <div v-if="product.product_type?.allows_free_attributes" class="pim-card p-3">
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attribut hinzufügen</label>
        <div class="flex items-end gap-2">
          <div class="flex-1 max-w-sm">
            <PimAttributeInput
              type="select"
              v-model="virtualAttributePicker.attribute_id"
              :options="virtualAttributeCatalogOptions"
              placeholder="Attribut wählen…"
            />
          </div>
          <button class="pim-btn pim-btn-secondary text-xs px-3 py-1.5" :disabled="!virtualAttributePicker.attribute_id" @click="addVirtualAttribute">
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Hinzufügen
          </button>
        </div>
        <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1">Zusätzlich zu Hierarchie-/Schema-Attributen kann hier jedes Attribut aus dem Katalog frei ergänzt werden.</p>
      </div>

      <!-- Language switcher for translatable attributes -->
      <div v-if="localeStore.activeDataLocales.length > 1 && schemaAttributes.some(a => a.is_translatable)" class="flex items-center gap-2 px-1">
        <Languages class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <div class="flex gap-0 border border-[var(--color-border)] rounded-lg overflow-hidden">
          <button
            v-for="loc in localeStore.activeDataLocales"
            :key="loc"
            :class="[
              'px-3 py-1 text-[11px] font-medium transition-colors',
              activeDataLang === loc
                ? 'bg-[var(--color-accent)] text-white'
                : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]',
            ]"
            @click="activeDataLang = loc"
          >
            {{ loc.toUpperCase() }}
          </button>
        </div>
        <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ t('product.dataLanguage') }}</span>
      </div>

      <!-- Flat attribute list -->
      <div class="pim-card p-4 space-y-2">
        <div v-if="filteredAttributes.length === 0" class="text-center py-8">
          <p class="text-sm text-[var(--color-text-tertiary)]">Keine Attribute gefunden</p>
        </div>
        <div
          v-for="attr in filteredAttributes"
          :key="attr.id"
          :class="[
            'md:flex md:gap-4',
            attr.is_multipliable || ['Composite', 'RichText', 'Textarea'].includes(attr.data_type) ? 'md:items-start' : 'md:items-center',
            { 'ring-1 ring-red-400 rounded-lg p-2 -m-2': mandatoryWarnings.has(attr.id) },
          ]"
        >
          <label
            :class="[
              'text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block truncate',
              (attr.is_multipliable || ['Composite', 'RichText', 'Textarea'].includes(attr.data_type)) ? 'md:pt-1.5' : '',
            ]"
            :title="attr.name_de || attr.technical_name"
          >
            {{ attr.name_de || attr.technical_name }}
            <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
            <span v-if="attr.is_translatable" class="ml-1 text-[10px] text-[var(--color-accent)] font-normal">
              <Languages class="inline w-3 h-3 -mt-0.5" :stroke-width="1.75" /> {{ activeDataLang.toUpperCase() }}
            </span>
            <span v-if="attr._is_inherited" class="ml-1 text-[10px] text-blue-500 font-normal">(vererbt)</span>
            <span v-if="isAttributeInherited(attr.id)" class="ml-1 text-[10px] text-purple-500 font-normal">(vererbt vom Elternprodukt)</span>
          </label>
          <div class="md:flex-1 md:min-w-0">
          <!-- Vermehrbares Composite: Instanzen als Accordion -->
          <PimMultipliableComposite
            v-if="attr.data_type === 'Composite' && attr.is_multipliable"
            :compositeAttribute="attr"
            :modelValue="multipliableCompositeValues[attr.id] || [{ multiplied_index: 0, children: {} }]"
            :maxMultiplied="attr.max_multiplied"
            :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
            :mapType="mapDataTypeToInput"
            @update:modelValue="multipliableCompositeValues[attr.id] = $event"
          />
          <!-- Composite (nicht vermehrbar): Button with summary -->
          <button
            v-else-if="attr.data_type === 'Composite'"
            class="w-full flex items-center justify-between pim-input text-left cursor-pointer hover:border-[var(--color-accent)] transition-colors"
            :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
            @click="openCompositeModal(attr)"
          >
            <span class="text-[13px]" :class="getCompositeSummary(attr) ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)]'">
              {{ getCompositeSummary(attr) || 'Bearbeiten…' }}
            </span>
            <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0 ml-2">{{ (attr._children || []).length }} Felder</span>
          </button>
          <!-- Multipliable attribute: multiple values with +/- and reordering -->
          <PimMultipliableInput
            v-else-if="attr.is_multipliable"
            :type="mapDataTypeToInput(attr.data_type)"
            :modelValue="multipliableValues[attr.id] || [{ value: null, multiplied_index: 0 }]"
            :options="['Selection', 'MultiSelection', 'SimpleSelect', 'SimpleMultiSelect'].includes(attr.data_type) ? getSelectionOptions(attr) : (attr.data_type === 'Dictionary' ? dictionaryEntries : [])"
            :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
            :maxMultiplied="attr.max_multiplied"
            :unitGroup="attr.unit_group"
            :delimiter="attr.delimiter || '|'"
            :rows="attr.textarea_rows || undefined"
            :cols="attr.textarea_cols || undefined"
            :min="attr.min_value != null ? Number(attr.min_value) : undefined"
            :max="attr.max_value != null ? Number(attr.max_value) : undefined"
            @update:modelValue="multipliableValues[attr.id] = $event"
          />
          <!-- Translatable attribute: bind to translatedValues -->
          <div v-else-if="attr.is_translatable">
          <div class="flex gap-1.5 items-start">
            <select
              v-if="['Number', 'Float'].includes(attr.data_type) && attr.comparison_operators?.length"
              class="pim-input text-[12px] !w-12 !min-w-0 shrink-0 text-center !px-1"
              :value="comparisonOperatorValues[attr.id] || ''"
              :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
              @change="comparisonOperatorValues[attr.id] = $event.target.value || null"
            >
              <option value="">=</option>
              <option v-for="op in attr.comparison_operators" :key="op.id" :value="op.id" :title="op.description_de">{{ op.symbol }}</option>
            </select>
            <PimAttributeInput
              class="flex-1 min-w-0"
              :type="mapDataTypeToInput(attr.data_type)"
              :modelValue="translatedValues[`${attr.id}_${activeDataLang}`]"
              :options="['Selection', 'MultiSelection', 'SimpleSelect', 'SimpleMultiSelect'].includes(attr.data_type) ? getSelectionOptions(attr) : (attr.data_type === 'Dictionary' ? dictionaryEntries : [])"
              :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
              :delimiter="attr.delimiter || '|'"
              :min="attr.min_value != null ? Number(attr.min_value) : undefined"
              :max="attr.max_value != null ? Number(attr.max_value) : undefined"
              :rows="attr.textarea_rows || undefined"
              :cols="attr.textarea_cols || undefined"
              @update:modelValue="translatedValues[`${attr.id}_${activeDataLang}`] = $event"
            />
            <select
              v-if="['Number', 'Float'].includes(attr.data_type) && attr.unit_group?.units?.length"
              class="pim-input text-[12px] !w-16 !min-w-0 shrink-0 !px-1"
              :value="unitValues[attr.id] || ''"
              :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
              @change="unitValues[attr.id] = $event.target.value || null"
            >
              <option value="">—</option>
              <option v-for="u in attr.unit_group.units" :key="u.id" :value="u.id">{{ u.abbreviation }}</option>
            </select>
          </div>
          <p v-if="attr.formatted_value ?? formattedValues[attr.id]" class="flex items-center gap-1 text-[11px] text-[var(--color-text-tertiary)] mt-1" title="Formatierte Vorschau (nur bei Export/Ansicht angewendet)">
            <Wand2 class="w-3 h-3 shrink-0" :stroke-width="1.75" />
            {{ attr.formatted_value ?? formattedValues[attr.id] }}
          </p>
          </div>
          <!-- Normal (non-translatable) attribute -->
          <div v-else>
          <div class="flex gap-1.5 items-start">
            <select
              v-if="['Number', 'Float'].includes(attr.data_type) && attr.comparison_operators?.length"
              class="pim-input text-[12px] !w-12 !min-w-0 shrink-0 text-center !px-1"
              :value="comparisonOperatorValues[attr.id] || ''"
              :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
              @change="comparisonOperatorValues[attr.id] = $event.target.value || null"
            >
              <option value="">=</option>
              <option v-for="op in attr.comparison_operators" :key="op.id" :value="op.id" :title="op.description_de">{{ op.symbol }}</option>
            </select>
            <PimAttributeInput
              class="flex-1 min-w-0"
              :type="mapDataTypeToInput(attr.data_type)"
              :modelValue="attributeValues[attr.id]"
              :options="['Selection', 'MultiSelection', 'SimpleSelect', 'SimpleMultiSelect'].includes(attr.data_type) ? getSelectionOptions(attr) : (attr.data_type === 'Dictionary' ? dictionaryEntries : [])"
              :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
              :delimiter="attr.delimiter || '|'"
              :min="attr.min_value != null ? Number(attr.min_value) : undefined"
              :max="attr.max_value != null ? Number(attr.max_value) : undefined"
              :rows="attr.textarea_rows || undefined"
              :cols="attr.textarea_cols || undefined"
              @update:modelValue="attributeValues[attr.id] = $event"
            />
            <select
              v-if="['Number', 'Float'].includes(attr.data_type) && attr.unit_group?.units?.length"
              class="pim-input text-[12px] !w-16 !min-w-0 shrink-0 !px-1"
              :value="unitValues[attr.id] || ''"
              :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
              @change="unitValues[attr.id] = $event.target.value || null"
            >
              <option value="">—</option>
              <option v-for="u in attr.unit_group.units" :key="u.id" :value="u.id">{{ u.abbreviation }}</option>
            </select>
          </div>
          <p v-if="attr.formatted_value ?? formattedValues[attr.id]" class="flex items-center gap-1 text-[11px] text-[var(--color-text-tertiary)] mt-1" title="Formatierte Vorschau (nur bei Export/Ansicht angewendet)">
            <Wand2 class="w-3 h-3 shrink-0" :stroke-width="1.75" />
            {{ attr.formatted_value ?? formattedValues[attr.id] }}
          </p>
          </div>
          </div>
        </div>
      </div>

      </template>

      <!-- ══ Output Hierarchy Sub-Tab (ETIM, ONYX, etc.) ══ -->
      <template v-for="h in outputHierarchyAttributes" :key="h.hierarchy_id">
        <template v-if="activeAttrSubTab === h.hierarchy_id">
          <!-- Language switcher -->
          <div v-if="localeStore.activeDataLocales.length > 1 && (h.attributes || []).some(a => a.is_translatable)" class="flex items-center gap-2 px-1">
            <Languages class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <div class="flex gap-0 border border-[var(--color-border)] rounded-lg overflow-hidden">
              <button
                v-for="loc in localeStore.activeDataLocales"
                :key="loc"
                :class="[
                  'px-3 py-1 text-[11px] font-medium transition-colors',
                  activeDataLang === loc
                    ? 'bg-[var(--color-accent)] text-white'
                    : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]',
                ]"
                @click="activeDataLang = loc"
              >
                {{ loc.toUpperCase() }}
              </button>
            </div>
            <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ t('product.dataLanguage') }}</span>
          </div>

          <!-- Sync button -->
          <div class="pim-card p-4 space-y-2">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ (h.attributes || []).length }} Attribute</span>
              <button
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                :disabled="outputHierarchySyncing"
                @click="syncOutputHierarchyMappings(h.hierarchy_id)"
                title="Mapping-Werte für dieses Produkt synchronisieren"
              >
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': outputHierarchySyncing }" :stroke-width="1.75" />
                {{ outputHierarchySyncing ? 'Synchronisiere…' : 'Mapping sync' }}
              </button>
            </div>
            <div v-if="(h.attributes || []).length === 0" class="text-center py-8">
              <p class="text-sm text-[var(--color-text-tertiary)]">Keine Attribute in dieser Ausgabehierarchie</p>
            </div>
            <div
              v-for="attr in (h.attributes || [])"
              :key="attr.attribute_id"
              :class="['md:flex md:gap-4', ['RichText', 'Textarea'].includes(attr.data_type) ? 'md:items-start' : 'md:items-center']"
            >
              <label
                class="text-[12px] font-medium text-[var(--color-text-secondary)] md:w-48 md:shrink-0 md:text-right md:mb-0 mb-1 block truncate"
                :title="attr.attribute_name_de || attr.attribute_technical_name"
              >
                {{ attr.attribute_name_de || attr.attribute_technical_name }}
                <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
                <span v-if="attr.is_translatable" class="ml-1 text-[10px] text-[var(--color-accent)] font-normal">
                  <Languages class="inline w-3 h-3 -mt-0.5" :stroke-width="1.75" /> {{ activeDataLang.toUpperCase() }}
                </span>
                <span v-if="attr.is_mapped" class="ml-1 text-[10px] text-amber-600 font-normal inline-flex items-center gap-0.5" title="Wert per Mapping berechnet">
                  <ArrowRightLeft class="inline w-3 h-3" :stroke-width="1.75" /> Mapping
                </span>
                <span v-if="attr.is_inherited" class="ml-1 text-[10px] text-blue-500 font-normal">(Master-Fallback)</span>
              </label>
              <div class="md:flex-1 md:min-w-0">
              <PimMultipliableInput
                v-if="attr.is_multipliable"
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="outputHierarchyMultipliableValues[`${h.hierarchy_id}_${attr.attribute_id}`] || [{ value: null, multiplied_index: 0 }]"
                :options="getSelectionOptions(attr)"
                :maxMultiplied="attr.max_multiplied"
                :unitGroup="attr.unit_group"
                :delimiter="attr.delimiter || '|'"
                :rows="attr.textarea_rows || undefined"
                :cols="attr.textarea_cols || undefined"
                :min="attr.min_value != null ? Number(attr.min_value) : undefined"
                :max="attr.max_value != null ? Number(attr.max_value) : undefined"
                @update:modelValue="outputHierarchyMultipliableValues[`${h.hierarchy_id}_${attr.attribute_id}`] = $event"
              />
              <PimAttributeInput
                v-else-if="attr.is_translatable"
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="outputHierarchyTranslatedValues[`${h.hierarchy_id}_${attr.attribute_id}_${activeDataLang}`]"
                :options="getSelectionOptions(attr)"
                :delimiter="attr.delimiter || '|'"
                :rows="attr.textarea_rows || undefined"
                :cols="attr.textarea_cols || undefined"
                :min="attr.min_value != null ? Number(attr.min_value) : undefined"
                :max="attr.max_value != null ? Number(attr.max_value) : undefined"
                @update:modelValue="outputHierarchyTranslatedValues[`${h.hierarchy_id}_${attr.attribute_id}_${activeDataLang}`] = $event"
              />
              <PimAttributeInput
                v-else
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="outputHierarchyAttrValues[`${h.hierarchy_id}_${attr.attribute_id}`]"
                :options="getSelectionOptions(attr)"
                :delimiter="attr.delimiter || '|'"
                :rows="attr.textarea_rows || undefined"
                :cols="attr.textarea_cols || undefined"
                :min="attr.min_value != null ? Number(attr.min_value) : undefined"
                :max="attr.max_value != null ? Number(attr.max_value) : undefined"
                @update:modelValue="outputHierarchyAttrValues[`${h.hierarchy_id}_${attr.attribute_id}`] = $event"
              />
              </div>
            </div>
          </div>
        </template>
      </template>
      <div v-if="outputHierarchyAttrLoading && activeAttrSubTab !== 'master'" class="pim-card p-4">
        <div class="pim-skeleton h-8 rounded" />
      </div>

      <!-- Composite Modal -->
      <PimCompositeModal
        :open="compositeModalOpen"
        :compositeAttribute="activeComposite ? { ...activeComposite, children: activeComposite._children || [] } : null"
        :modelValue="attributeValues"
        :mapType="mapDataTypeToInput"
        @update:open="compositeModalOpen = $event"
        @update:modelValue="onCompositeValuesUpdate"
      />
    </div>

    <!-- ═══ Variant Attributes Tab ═══ -->
    <div v-else-if="activeTab === 'variant-attributes' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <template v-if="variantAttributeGroups.length > 0">
        <PimCollectionGroup
          v-for="group in variantAttributeGroups"
          :key="group.name"
          :title="group.name"
          :filledCount="group.attributes.filter(a => attributeValues[a.id]).length"
          :totalCount="group.attributes.length"
          :defaultOpen="true"
        >
          <div class="space-y-3 pt-3">
            <div v-for="attr in group.attributes" :key="attr.id">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                {{ attr.name_de || attr.technical_name }}
                <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
                <span v-if="attr._is_inherited" class="ml-1 text-[10px] text-blue-500 font-normal">(vererbt)</span>
                <span v-if="isAttributeInherited(attr.id)" class="ml-1 text-[10px] text-purple-500 font-normal">(vererbt vom Elternprodukt)</span>
              </label>
              <PimAttributeInput
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="attributeValues[attr.id]"
                :options="attr.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || []"
                :disabled="attr._access === 'read_only' || attr.is_readonly || isAttributeInherited(attr.id) || isTabReadOnly || isAttrReadOnlyInActiveView(attr.id)"
                @update:modelValue="attributeValues[attr.id] = $event"
              />
            </div>
          </div>
        </PimCollectionGroup>
      </template>
      <div v-else class="pim-card p-12 text-center">
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Varianten-Attribute zugewiesen</p>
      </div>
    </div>

    <!-- ═══ Variants Tab ═══ -->
    <div v-else-if="activeTab === 'variants' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Varianten</h3>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-secondary text-xs" @click="showVariantAxisConfig = !showVariantAxisConfig">
            Merkmalsachsen
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="initGenerator">
            <Sparkles class="w-3.5 h-3.5" :stroke-width="2" /> Varianten generieren
          </button>
          <button class="pim-btn pim-btn-primary text-xs" @click="showVariantForm = !showVariantForm">
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neue Variante
          </button>
        </div>
      </div>

      <!-- Variant Axis Configuration: welche Attribute unterscheiden die Varianten dieses Produkts -->
      <div v-if="showVariantAxisConfig" class="pim-card p-4 space-y-3">
        <h4 class="text-sm font-semibold text-[var(--color-text-primary)]">Merkmalsachsen dieses Produkts</h4>
        <p class="text-xs text-[var(--color-text-secondary)]">
          Legen Sie fest, welche Attribute die Varianten unterscheiden. Jede Variante muss in diesen Achsen
          eine eigene, eindeutige Wertekombination haben.
        </p>
        <div v-if="variantAxisEligibleAttributes.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-800">
          Keine Attribute mit <em>is_variant_attribute</em> vorhanden. Markieren Sie zuerst Attribute dafür
          (unter <strong>Attribute</strong> → Attribut bearbeiten → <em>Varianten-Attribut</em>).
        </div>
        <div v-else class="flex flex-wrap gap-1.5">
          <label
            v-for="attr in variantAxisEligibleAttributes"
            :key="attr.id"
            class="flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer transition-colors"
            :class="variantAxisAttributeIds.includes(attr.id)
              ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
              : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)]'"
          >
            <input type="checkbox" class="pim-checkbox w-3 h-3" :checked="variantAxisAttributeIds.includes(attr.id)" @change="toggleVariantAxis(attr.id)" />
            {{ attr.name_de || attr.technical_name }}
          </label>
        </div>
        <div v-if="variantAxisAttributeIds.length > 0" class="space-y-1">
          <p class="text-[11px] font-medium text-[var(--color-text-secondary)]">Reihenfolge (= Spaltenreihenfolge in der Matrix):</p>
          <div v-for="(attrId, idx) in variantAxisAttributeIds" :key="attrId" class="flex items-center gap-2 text-xs">
            <span class="flex-1">{{ variantAxisEligibleAttributes.find(a => a.id === attrId)?.name_de || attrId }}</span>
            <button class="pim-btn pim-btn-ghost p-1" :disabled="idx === 0" @click="moveVariantAxis(idx, -1)">↑</button>
            <button class="pim-btn pim-btn-ghost p-1" :disabled="idx === variantAxisAttributeIds.length - 1" @click="moveVariantAxis(idx, 1)">↓</button>
          </div>
        </div>
        <p v-if="variantAxisError" class="text-[11px] text-[var(--color-error)]">{{ variantAxisError }}</p>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="variantAxisSaving" @click="saveVariantAxes">
            {{ variantAxisSaving ? 'Speichern…' : 'Achsen speichern' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showVariantAxisConfig = false">Abbrechen</button>
        </div>
      </div>

      <!-- Variant creation form -->
      <div v-if="showVariantForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SKU <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" v-model="variantForm.sku" />
            <p v-if="variantErrors.sku" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ variantErrors.sku }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" v-model="variantForm.name" />
            <p v-if="variantErrors.name" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ variantErrors.name }}</p>
          </div>
          <div v-if="!product.product_type || product.product_type.has_ean !== false">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">EAN</label>
            <input class="pim-input" v-model="variantForm.ean" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Status</label>
            <PimAttributeInput type="select" v-model="variantForm.status" :options="[{ value: 'draft', label: 'Entwurf' }, { value: 'active', label: 'Aktiv' }]" />
          </div>
          <div v-for="attr in variantAttributeDefs" :key="attr.id">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ attr.name_de || attr.technical_name }}</label>
            <PimAttributeInput
              :type="mapDataTypeToInput(attr.data_type)"
              :modelValue="variantForm.axis_values[attr.id]"
              :options="getSelectionOptions(attr)"
              @update:modelValue="variantForm.axis_values[attr.id] = $event"
            />
          </div>
        </div>
        <p v-if="variantErrors.variant" class="text-[11px] text-[var(--color-error)]">{{ variantErrors.variant }}</p>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="variantSaving" @click="createVariant">
            {{ variantSaving ? 'Speichern…' : 'Erstellen' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showVariantForm = false">Abbrechen</button>
        </div>
      </div>

      <!-- Variant Generator Panel -->
      <div v-if="showGenerator" class="pim-card p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-semibold text-[var(--color-text-primary)] flex items-center gap-1.5">
            <Sparkles class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
            Variantengenerator
          </h4>
          <button class="pim-btn pim-btn-ghost p-1" @click="showGenerator = false"><X class="w-4 h-4" /></button>
        </div>

        <!-- Step 1: Select attributes + enter values -->
        <template v-if="generatorStep === 1">
          <p class="text-xs text-[var(--color-text-secondary)]">Wählen Sie Variantenattribute und geben Sie die gewünschten Werte ein.</p>
          <div v-if="generatorDimensions.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-xs text-yellow-800 space-y-1">
            <p class="font-medium">Keine Varianten-Attribute vorhanden</p>
            <p>Markieren Sie zuerst mindestens ein Attribut als Varianten-Attribut (unter <strong>Attribute</strong> → Attribut bearbeiten → <em>Varianten-Attribut</em> aktivieren).</p>
          </div>
          <div class="space-y-3">
            <div v-for="dim in generatorDimensions" :key="dim.attribute_id" class="border border-[var(--color-border)] rounded-lg p-3">
              <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input type="checkbox" v-model="dim.selected" class="pim-checkbox" />
                {{ dim.attribute.name_de || dim.attribute.technical_name }}
              </label>
              <div v-if="dim.selected" class="mt-2 space-y-2">
                <!-- Value list entries (Selection/Dictionary) -->
                <template v-if="dim.attribute.value_list?.entries?.length">
                  <div class="flex flex-wrap gap-1.5">
                    <label
                      v-for="entry in dim.attribute.value_list.entries"
                      :key="entry.id"
                      class="flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer transition-colors"
                      :class="dim.values.includes(entry.display_value_de || entry.value_de || entry.code)
                        ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
                        : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)]'"
                      @click.prevent="toggleValueListEntry(dim, entry.display_value_de || entry.value_de || entry.code)"
                    >
                      <input
                        type="checkbox"
                        :checked="dim.values.includes(entry.display_value_de || entry.value_de || entry.code)"
                        class="pim-checkbox w-3 h-3"
                        @click.stop
                        @change="toggleValueListEntry(dim, entry.display_value_de || entry.value_de || entry.code)"
                      />
                      {{ entry.display_value_de || entry.value_de || entry.code }}
                    </label>
                  </div>
                </template>
                <!-- Free text input (String/Number) -->
                <template v-else>
                  <div class="flex gap-2">
                    <input
                      class="pim-input text-xs flex-1"
                      v-model="dim.textInput"
                      placeholder="Werte kommasepariert eingeben (z.B. 30, 31, 32)"
                      @keydown.enter.prevent="addDimensionValues(dim)"
                    />
                    <button class="pim-btn pim-btn-secondary text-xs" @click="addDimensionValues(dim)">
                      <Plus class="w-3 h-3" /> Hinzufügen
                    </button>
                  </div>
                </template>
                <!-- Show selected values as tags -->
                <div v-if="dim.values.length" class="flex flex-wrap gap-1">
                  <span
                    v-for="val in dim.values"
                    :key="val"
                    class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-[var(--color-accent)]/10 text-[var(--color-accent)]"
                  >
                    {{ val }}
                    <button class="hover:text-[var(--color-error)]" @click="removeDimensionValue(dim, val)"><X class="w-3 h-3" /></button>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2">
            <p class="text-xs text-[var(--color-text-tertiary)]">
              {{ generatorTotalCombinations }} Kombination{{ generatorTotalCombinations !== 1 ? 'en' : '' }}
            </p>
            <div class="flex gap-2">
              <button class="pim-btn pim-btn-secondary text-xs" @click="showGenerator = false">Abbrechen</button>
              <button
                class="pim-btn pim-btn-primary text-xs"
                :disabled="generatorTotalCombinations === 0"
                @click="generatorStep = 2"
              >
                Weiter zur Vorschau
              </button>
            </div>
          </div>
        </template>

        <!-- Step 2: Preview -->
        <template v-if="generatorStep === 2">
          <div class="space-y-3">
            <div class="flex flex-wrap items-end gap-3">
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">SKU-Prefix</label>
                <input class="pim-input text-xs w-48" v-model="generatorSKUPrefix" />
              </div>
              <p class="text-xs text-[var(--color-text-tertiary)] pb-1.5">
                {{ generatorPreviewFiltered.length }} von {{ generatorPreview.length }} Varianten ausgewählt
              </p>
            </div>

            <div class="max-h-80 overflow-auto border border-[var(--color-border)] rounded-lg">
              <table class="w-full text-xs">
                <thead class="bg-[var(--color-bg)] sticky top-0">
                  <tr>
                    <th class="px-3 py-2 text-left font-medium text-[var(--color-text-secondary)]"></th>
                    <th class="px-3 py-2 text-left font-medium text-[var(--color-text-secondary)]">SKU</th>
                    <th
                      v-for="dim in generatorDimensions.filter(d => d.selected && d.values.length > 0)"
                      :key="dim.attribute_id"
                      class="px-3 py-2 text-left font-medium text-[var(--color-text-secondary)]"
                    >
                      {{ dim.attribute.name_de || dim.attribute.technical_name }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in generatorPreview"
                    :key="row.idx"
                    class="border-t border-[var(--color-border)]"
                    :class="generatorExcluded.has(row.idx) ? 'opacity-40' : ''"
                  >
                    <td class="px-3 py-1.5">
                      <input
                        type="checkbox"
                        class="pim-checkbox"
                        :checked="!generatorExcluded.has(row.idx)"
                        @change="toggleGeneratorRow(row.idx)"
                      />
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[var(--color-text-tertiary)]">{{ row.sku }}</td>
                    <td
                      v-for="c in row.combo"
                      :key="c.attribute_id"
                      class="px-3 py-1.5"
                    >
                      {{ c.value }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex gap-2 justify-end pt-1">
              <button class="pim-btn pim-btn-secondary text-xs" @click="generatorStep = 1">Zurück</button>
              <button
                class="pim-btn pim-btn-primary text-xs"
                :disabled="generatorLoading || generatorPreviewFiltered.length === 0"
                @click="runGenerator"
              >
                <Sparkles class="w-3.5 h-3.5" />
                {{ generatorLoading ? 'Wird erstellt…' : `${generatorPreviewFiltered.length} Varianten erstellen` }}
              </button>
            </div>
          </div>
        </template>

        <!-- Step 3: Result -->
        <template v-if="generatorStep === 3 && generatorResult">
          <div class="text-center space-y-2 py-4">
            <div class="text-3xl">✓</div>
            <p class="text-sm font-medium text-[var(--color-text-primary)]">
              {{ generatorResult.created }} Variante{{ generatorResult.created !== 1 ? 'n' : '' }} erstellt
            </p>
            <p v-if="generatorResult.skipped > 0" class="text-xs text-[var(--color-text-tertiary)]">
              {{ generatorResult.skipped }} übersprungen (SKU bereits vorhanden)
            </p>
            <button class="pim-btn pim-btn-secondary text-xs mt-3" @click="showGenerator = false">Schließen</button>
          </div>
        </template>
      </div>

      <PimTable
        :columns="variantColumns"
        :rows="variantRows"
        :loading="variantsLoading"
        emptyText="Keine Varianten vorhanden"
        @row-click="(row) => router.push(`/products/${row.id}`)"
        @row-action="(row) => variantDeleteTarget = row"
      >
        <template #cell-status="{ value }">
          <span :class="['pim-badge', value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
            {{ value === 'active' ? 'Aktiv' : 'Entwurf' }}
          </span>
        </template>
      </PimTable>

      <PimDeleteConfirmDialog
        :open="!!variantDeleteTarget"
        title="Variante löschen?"
        message="Diese Variante wird unwiderruflich gelöscht."
        entityType="products"
        :entityId="variantDeleteTarget?.id || ''"
        :loading="variantDeleting"
        @confirm="confirmDeleteVariant"
        @cancel="variantDeleteTarget = null"
      />

      <!-- Variant Inheritance Rules (only on parent products) -->
      <div v-if="product.product_type_ref !== 'variant'" class="pt-2">
        <button
          class="text-xs text-[var(--color-accent)] hover:underline"
          @click="showInheritanceRules = !showInheritanceRules; if (!inheritanceRulesLoaded) { loadAttributeData(); loadInheritanceRules() }"
        >
          {{ showInheritanceRules ? 'Vererbungsregeln ausblenden' : 'Vererbungsregeln verwalten' }}
        </button>

        <div v-if="showInheritanceRules" class="pim-card p-4 mt-2 space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-[var(--color-text-primary)]">Vererbungsregeln</h4>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="inheritanceRulesSaving"
              @click="saveInheritanceRules"
            >
              {{ inheritanceRulesSaving ? 'Speichern…' : 'Regeln speichern' }}
            </button>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">
            Legen Sie fest, welche Attribute Varianten vom Elternprodukt erben (nicht editierbar) oder selbst überschreiben können.
          </p>

          <div v-if="inheritanceRulesLoading" class="space-y-2">
            <div v-for="i in 4" :key="i" class="pim-skeleton h-8 w-full rounded" />
          </div>
          <div v-else-if="schemaAttributes.length > 0" class="divide-y divide-[var(--color-border)]">
            <div
              v-for="attr in schemaAttributes"
              :key="attr.id"
              class="flex items-center justify-between py-2"
            >
              <span class="text-xs text-[var(--color-text-primary)]">
                {{ attr.name_de || attr.technical_name }}
                <span v-if="attr.is_variant_attribute" class="ml-1 text-[10px] text-purple-500">(Varianten-Attribut)</span>
              </span>
              <button
                :class="[
                  'text-[11px] px-2.5 py-1 rounded-full font-medium transition-colors',
                  (editedInheritanceRules[attr.id] || 'override') === 'inherit'
                    ? 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                    : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-border)]',
                ]"
                @click="toggleInheritance(attr.id)"
              >
                {{ (editedInheritanceRules[attr.id] || 'override') === 'inherit' ? 'Vererben' : 'Überschreiben' }}
              </button>
            </div>
          </div>
          <div v-else class="text-xs text-[var(--color-text-tertiary)]">
            Keine Attribute im Produkttyp-Schema gefunden.
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Media Tab ═══ -->
    <div v-else-if="activeTab === 'media' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <!-- Header with search, view toggle and add button -->
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)] shrink-0">Medien</h3>
        <span v-if="mediaItems.length > 0" class="text-[11px] text-[var(--color-text-tertiary)] shrink-0">({{ displayMediaItems.length }}<template v-if="mediaFilter || showMediaQuickLookup"> / {{ mediaItems.length }}</template>)</span>
        <div class="flex-1" />
        <!-- Quick filter -->
        <div v-if="mediaItems.length > 5" class="relative">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <input
            v-model="mediaFilter"
            type="text"
            class="pim-input text-xs pim-input-icon w-44"
            placeholder="Filtern…"
          />
        </div>
        <!-- Quick Lookup Toggle -->
        <button
          v-if="mediaItems.length > 3 && mediaViewMode === 'list'"
          :class="['pim-btn pim-btn-ghost p-1.5', showMediaQuickLookup ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)]' : '']"
          @click="showMediaQuickLookup = !showMediaQuickLookup"
          title="Quick Lookup"
        >
          <Filter class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <!-- Spaltenkonfiguration -->
        <ColumnConfigPopover
          v-if="mediaViewMode === 'list'"
          :allColumns="allMediaColumns"
          :visibleKeys="visibleMediaKeys"
          @toggle="toggleMediaColumn"
          @move="moveMediaColumn"
          @reset="resetMediaColumns"
          @reorder="visibleMediaKeys = $event"
        />
        <!-- View toggle -->
        <div class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
          <button
            class="p-1.5 transition-colors"
            :class="mediaViewMode === 'grid' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
            @click="mediaViewMode = 'grid'"
            title="Kachelansicht"
          >
            <LayoutGrid class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button
            class="p-1.5 transition-colors"
            :class="mediaViewMode === 'list' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
            @click="mediaViewMode = 'list'"
            title="Listenansicht"
          >
            <List class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
        <label v-if="selectableDisplayMediaItems.length > 0" class="inline-flex items-center gap-1.5 shrink-0 cursor-pointer text-[11px] text-[var(--color-text-tertiary)]">
          <input
            type="checkbox"
            class="w-3.5 h-3.5 accent-[var(--color-accent)]"
            :checked="allDisplayedMediaSelected"
            @change="toggleSelectAllMedia"
          />
          Alle auswählen
        </label>
        <button
          class="pim-btn pim-btn-outline text-xs"
          :disabled="selectedMediaIds.size === 0 || downloadingMediaZip"
          @click="downloadSelectedMediaZip"
        >
          <Download class="w-3.5 h-3.5" :stroke-width="2" />
          {{ downloadingMediaZip ? 'Wird gepackt…' : `Als ZIP herunterladen (${selectedMediaIds.size})` }}
        </button>
        <button class="pim-btn pim-btn-outline text-xs" @click="openMediaUpload">
          <Upload class="w-3.5 h-3.5" :stroke-width="2" /> Hochladen
        </button>
        <button class="pim-btn pim-btn-outline text-xs" @click="openMotifPicker">
          <Images class="w-3.5 h-3.5" :stroke-width="2" /> Motiv zuordnen
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="openMediaPicker">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Zuordnen
        </button>
      </div>

      <MotifPickerDialog v-model="showMotifPicker" @select="attachMotif" />

      <!-- Upload-Bereich -->
      <div v-if="showMediaUpload" class="pim-card p-4 space-y-3">
        <div class="flex items-center gap-3">
          <h4 class="text-xs font-medium text-[var(--color-text-primary)]">Medien hochladen & zuordnen</h4>
          <div class="flex-1" />
          <button class="pim-btn pim-btn-ghost text-xs" @click="showMediaUpload = false">
            <X class="w-3.5 h-3.5" :stroke-width="2" /> Schließen
          </button>
        </div>
        <div class="flex items-end gap-3 flex-wrap">
          <!-- Medientyp (Usage Type) -->
          <div class="space-y-1">
            <label class="text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium">Bildtyp</label>
            <select v-model="uploadUsageTypeId" class="pim-input text-xs w-48">
              <option v-for="ut in productTypeUsageTypes" :key="ut.id" :value="ut.id">{{ ut.name_de || ut.technical_name }}</option>
            </select>
          </div>
          <!-- Zielordner -->
          <div class="space-y-1">
            <label class="text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium">Ordner</label>
            <select v-model="uploadFolderId" class="pim-input text-xs w-48">
              <option :value="null">— Kein Ordner —</option>
              <option v-for="f in assetFolders" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <!-- Datei-Auswahl -->
          <label class="pim-btn pim-btn-primary text-xs cursor-pointer inline-flex items-center gap-1.5">
            <Upload class="w-3.5 h-3.5" :stroke-width="2" /> Dateien wählen
            <input type="file" multiple class="hidden" @change="handleMediaUpload" />
          </label>
        </div>
        <MediaUploadQueue ref="uploadQueueRef" :metadata="uploadMetadata" @file-uploaded="onFileUploaded" />
      </div>

      <!-- Loading -->
      <div v-if="mediaLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="i in 4" :key="i" class="pim-skeleton aspect-square rounded-lg" />
      </div>

      <!-- Grid view -->
      <div v-else-if="displayMediaItems.length > 0 && mediaViewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div
          v-for="(m, mIdx) in displayMediaItems"
          :key="m.id"
          class="pim-card overflow-hidden group relative"
          :class="{ 'cursor-move': canReorderMedia, 'opacity-40': mediaDragging && mediaDragIndex === mIdx }"
          :draggable="canReorderMedia"
          :title="canReorderMedia ? 'Ziehen zum Sortieren' : ''"
          @dragstart="onMediaDragStart(mIdx)"
          @dragend="onMediaDragEnd"
          @dragover.prevent
          @drop.prevent="handleMediaDrop(mIdx)"
        >
          <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden p-2 relative">
            <span v-if="m.motif_id" class="pim-badge absolute top-1 left-1 z-10 bg-[var(--color-accent)] text-white text-[9px]">Motiv</span>
            <input
              v-if="isMediaSelectable(m)"
              type="checkbox"
              class="absolute top-1 right-1 z-10 w-3.5 h-3.5 accent-[var(--color-accent)]"
              :checked="isMediaSelected(m)"
              @click.stop="toggleMediaSelection(m)"
            />
            <span v-else class="absolute top-1 right-1 z-10 p-1 rounded bg-[var(--color-bg)]/90" title="Kein Zugriff auf diesen Medientyp — Download gesperrt">
              <Lock class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            </span>
            <PdfPreview v-if="isMediaPdf(m)" :url="getMediaUrl(m)" :media-id="m.media_id || m.media?.id || m.id" :title="assignmentLabel(m)" max-height="100%" />
            <img v-else :src="getMediaUrl(m)" class="w-full h-full object-contain" loading="lazy" alt="" />
          </div>
          <div class="p-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-primary)] truncate flex-1">{{ assignmentLabel(m) }}</span>
              <button
                class="p-0.5 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-colors"
                title="In Medienverwaltung öffnen"
                @click.stop="m.motif_id ? router.push({ path: '/media-motifs', query: { search: m.motif?.title_de } }) : router.push({ path: '/media', query: { search: m.file_name || m.media?.file_name } })"
              >
                <ExternalLink class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <button class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors" @click="detachMedia(m)">
                <X class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
            <span v-if="m.usage_type" class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.usage_type?.name_de || m.usage_type?.technical_name || '' }}</span>
          </div>
        </div>
      </div>

      <!-- List view (dynamische Spalten) -->
      <div v-else-if="displayMediaItems.length > 0 && mediaViewMode === 'list'" class="pim-card overflow-hidden overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-[var(--color-border)] bg-[var(--color-bg)]">
              <th class="px-3 py-2 text-left" style="width:32px">
                <input
                  v-if="selectableDisplayMediaItems.length > 0"
                  type="checkbox"
                  class="w-3.5 h-3.5 accent-[var(--color-accent)]"
                  :checked="allDisplayedMediaSelected"
                  @change="toggleSelectAllMedia"
                />
              </th>
              <th
                v-for="col in visibleMediaColumns"
                :key="col.key"
                class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium whitespace-nowrap"
                :style="col.key === 'thumb' ? 'width:44px' : ''"
              >
                {{ col.key === 'thumb' ? '' : col.label }}
              </th>
              <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium" style="width:56px"></th>
            </tr>
            <!-- Quick Lookup Filterzeile -->
            <tr v-if="showMediaQuickLookup" class="border-b border-[var(--color-border)] bg-[var(--color-bg)]/50">
              <td></td>
              <td v-for="col in visibleMediaColumns" :key="col.key" class="px-2 py-1">
                <template v-if="col.key === 'thumb' || col.key === 'dimensions' || col.key === 'sort_order' || col.key === 'is_primary' || col.key === 'file_size'" />
                <select
                  v-else-if="col.key === 'media_type'"
                  v-model="mediaQuickLookupFilters.media_type"
                  class="pim-input text-[11px] w-full py-1 px-1.5"
                >
                  <option value="">Alle</option>
                  <option value="image">image</option>
                  <option value="document">document</option>
                  <option value="video">video</option>
                </select>
                <select
                  v-else-if="col.key === 'usage_purpose'"
                  v-model="mediaQuickLookupFilters.usage_purpose"
                  class="pim-input text-[11px] w-full py-1 px-1.5"
                >
                  <option value="">Alle</option>
                  <option value="print">Print</option>
                  <option value="web">Web</option>
                  <option value="both">Print & Web</option>
                  <option value="catalog_logo">Katalog-Logo</option>
                </select>
                <input
                  v-else
                  v-model="mediaQuickLookupFilters[col.key]"
                  type="text"
                  class="pim-input text-[11px] w-full py-1 px-1.5"
                  placeholder="Filtern…"
                />
              </td>
              <td></td>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="m in displayMediaItems"
              :key="m.id"
              class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-bg)] transition-colors"
            >
              <td class="px-3 py-1.5">
                <input
                  v-if="isMediaSelectable(m)"
                  type="checkbox"
                  class="w-3.5 h-3.5 accent-[var(--color-accent)]"
                  :checked="isMediaSelected(m)"
                  @change="toggleMediaSelection(m)"
                />
                <Lock v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" title="Kein Zugriff auf diesen Medientyp — Download gesperrt" />
              </td>
              <td v-for="col in visibleMediaColumns" :key="col.key" class="px-3 py-1.5">
                <!-- Thumbnail -->
                <template v-if="col.key === 'thumb'">
                  <div class="w-8 h-8 rounded bg-[var(--color-bg)] overflow-hidden border border-[var(--color-border)]">
                    <PdfPreview v-if="isMediaPdf(m)" :url="getMediaUrl(m)" :media-id="m.media_id || m.media?.id || m.id" :title="''" max-height="2rem" />
                    <img v-else :src="getMediaUrl(m)" class="w-full h-full object-cover" loading="lazy" alt="" />
                  </div>
                </template>
                <!-- Dateiname -->
                <template v-else-if="col.key === 'file_name'">
                  <span v-if="m.motif_id" class="pim-badge bg-[var(--color-accent)] text-white text-[9px] mr-1">Motiv</span>
                  <span class="text-[var(--color-text-primary)] font-mono text-[11px]">{{ assignmentLabel(m) }}</span>
                </template>
                <!-- Bildtyp (Usage Type) -->
                <template v-else-if="col.key === 'usage_type'">
                  <span class="text-[var(--color-text-tertiary)]">{{ m.usage_type?.name_de || m.usage_type?.technical_name || '—' }}</span>
                </template>
                <!-- MIME -->
                <template v-else-if="col.key === 'mime_type'">
                  <span class="text-[var(--color-text-tertiary)]">{{ m.mime_type || m.media?.mime_type || '—' }}</span>
                </template>
                <!-- Titel (DE) -->
                <template v-else-if="col.key === 'title'">
                  <span class="text-[var(--color-text-primary)] text-[11px]">{{ m.media?.title_de || '—' }}</span>
                </template>
                <!-- Medientyp -->
                <template v-else-if="col.key === 'media_type'">
                  <span class="text-[var(--color-text-tertiary)]">{{ m.media?.media_type || '—' }}</span>
                </template>
                <!-- Verwendungszweck -->
                <template v-else-if="col.key === 'usage_purpose'">
                  <span class="text-[var(--color-text-tertiary)]">{{ m.media?.usage_purpose || '—' }}</span>
                </template>
                <!-- Dateigröße -->
                <template v-else-if="col.key === 'file_size'">
                  <span class="text-[var(--color-text-tertiary)] tabular-nums">{{ formatFileSize(m.media?.file_size) }}</span>
                </template>
                <!-- Abmessungen -->
                <template v-else-if="col.key === 'dimensions'">
                  <span v-if="m.media?.width" class="text-[var(--color-text-tertiary)] tabular-nums">{{ m.media.width }} × {{ m.media.height }} px</span>
                  <span v-else class="text-[var(--color-text-tertiary)]">—</span>
                </template>
                <!-- Alt-Text -->
                <template v-else-if="col.key === 'alt_text'">
                  <span class="text-[var(--color-text-tertiary)] text-[11px]">{{ m.media?.alt_text_de || '—' }}</span>
                </template>
                <!-- Sortierung -->
                <template v-else-if="col.key === 'sort_order'">
                  <span class="text-[var(--color-text-tertiary)] tabular-nums">{{ m.sort_order ?? '—' }}</span>
                </template>
                <!-- Primär -->
                <template v-else-if="col.key === 'is_primary'">
                  <span v-if="m.is_primary" class="text-[var(--color-success)]">&#10003;</span>
                  <span v-else class="text-[var(--color-text-tertiary)]">—</span>
                </template>
              </td>
              <td class="px-3 py-1.5 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-colors" title="In Medienverwaltung öffnen" @click.stop="router.push({ path: '/media', query: { search: m.file_name || m.media?.file_name } })">
                    <ExternalLink class="w-3.5 h-3.5" :stroke-width="2" />
                  </button>
                  <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors" @click="detachMedia(m)">
                    <X class="w-3.5 h-3.5" :stroke-width="2" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty state -->
      <div v-else-if="!mediaLoading && mediaItems.length === 0" class="pim-card p-12 text-center">
        <Image class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Medien zugeordnet</p>
      </div>

      <!-- Filter empty state -->
      <div v-else-if="!mediaLoading && displayMediaItems.length === 0" class="pim-card p-8 text-center">
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Medien für aktive Filter gefunden</p>
      </div>

      <!-- Media picker dialog -->
      <MediaPickerDialog
        v-model="showMediaPicker"
        :usage-types="productTypeUsageTypes"
        :selected-usage-type-id="selectedUsageTypeId"
        :exclude-media-ids="assignedMediaIds"
        :allowed-extensions="selectedUsageTypeExtensions"
        @update:selected-usage-type-id="selectedUsageTypeId = $event"
        @select="attachMedia($event)"
        @select-multiple="attachMediaBulk($event)"
      />
    </div>

    <!-- ═══ Prices Tab ═══ -->
    <div v-else-if="activeTab === 'prices' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Preise</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="openPriceForm()">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neuer Preis
        </button>
      </div>

      <!-- Price creation/edit form -->
      <div v-if="showPriceForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Preistyp <span class="text-[var(--color-error)]">*</span></label>
            <PimAttributeInput type="select" v-model="priceForm.price_type_id" :options="priceTypesList.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))" />
            <p v-if="priceErrors.price_type_id" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ priceErrors.price_type_id }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Betrag <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" type="number" step="0.01" v-model="priceForm.amount" />
            <p v-if="priceErrors.amount" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ priceErrors.amount }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Währung <span class="text-[var(--color-error)]">*</span></label>
            <PimAttributeInput type="select" v-model="priceForm.currency" :options="[{ value: 'EUR', label: 'EUR' }, { value: 'USD', label: 'USD' }, { value: 'CHF', label: 'CHF' }, { value: 'GBP', label: 'GBP' }]" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Preisregion</label>
            <select class="pim-input" v-model="priceForm.price_region_id">
              <option value="">— Keine —</option>
              <option v-for="region in priceRegionsList" :key="region.id" :value="region.id">{{ region.name }} ({{ region.code }})</option>
            </select>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig ab <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" type="date" v-model="priceForm.valid_from" />
            <p v-if="priceErrors.valid_from" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ priceErrors.valid_from }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig bis</label>
            <input class="pim-input" type="date" v-model="priceForm.valid_to" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Staffel von</label>
            <input class="pim-input" type="number" v-model="priceForm.scale_from" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Staffel bis</label>
            <input class="pim-input" type="number" v-model="priceForm.scale_to" />
          </div>
        </div>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="priceSaving" @click="savePrice">
            {{ priceSaving ? 'Speichern…' : 'Speichern' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showPriceForm = false">Abbrechen</button>
        </div>
      </div>

      <PimTable
        :columns="priceColumns"
        :rows="filteredPrices"
        :loading="pricesLoading"
        :quick-lookup="prices.length > 5"
        :quick-lookup-config="priceQuickLookupConfig"
        emptyText="Keine Preise vorhanden"
        @row-click="openPriceForm"
        @row-action="(row) => priceDeleteTarget = row"
        @quick-lookup-change="priceQuickLookup = $event"
      >
        <template #cell-amount="{ value }">
          <span class="font-mono">{{ value ? Number(value).toFixed(2) : '—' }}</span>
        </template>
        <template #cell-valid_from="{ value }">
          <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
        </template>
        <template #cell-valid_to="{ value }">
          <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
        </template>
      </PimTable>

      <PimConfirmDialog
        :open="!!priceDeleteTarget"
        title="Preis löschen?"
        message="Dieser Preis wird unwiderruflich gelöscht."
        confirm-label="Löschen"
        :danger="true"
        :loading="priceDeleting"
        @confirm="confirmDeletePrice"
        @cancel="priceDeleteTarget = null"
      />
    </div>

    <!-- ═══ Relations Tab ═══ -->
    <div v-else-if="activeTab === 'relations' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <!-- Header mit Filter, View-Toggle und Button -->
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)] shrink-0">Produktbeziehungen</h3>
        <span v-if="relations.length > 0" class="text-[11px] text-[var(--color-text-tertiary)] shrink-0">({{ filteredRelations.length }}<template v-if="relationFilter"> / {{ relations.length }}</template>)</span>
        <div class="flex-1" />
        <!-- Quick filter -->
        <div v-if="relations.length > 3" class="relative">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <input
            v-model="relationFilter"
            type="text"
            class="pim-input text-xs pim-input-icon w-44"
            placeholder="Filtern…"
          />
        </div>
        <!-- Spaltenkonfiguration (nur Listenansicht) -->
        <ColumnConfigPopover
          v-if="relationViewMode === 'list'"
          :allColumns="allRelationColumns"
          :visibleKeys="visibleRelationKeys"
          @toggle="toggleRelationColumn"
          @move="moveRelationColumn"
          @reset="resetRelationColumns"
          @reorder="visibleRelationKeys = $event"
        />
        <!-- View toggle -->
        <div class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
          <button
            class="p-1.5 transition-colors"
            :class="relationViewMode === 'grid' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
            @click="relationViewMode = 'grid'"
            title="Kachelansicht"
          >
            <LayoutGrid class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button
            class="p-1.5 transition-colors"
            :class="relationViewMode === 'list' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
            @click="relationViewMode = 'list'"
            title="Listenansicht"
          >
            <List class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
        <button class="pim-btn pim-btn-primary text-xs" @click="showRelationForm = !showRelationForm">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neue Beziehung
        </button>
      </div>

      <!-- Relation creation form -->
      <div v-if="showRelationForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beziehungstyp <span class="text-[var(--color-error)]">*</span></label>
            <PimAttributeInput type="select" v-model="relationForm.relation_type_id" :options="availableRelationTypesForProduct.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))" />
            <p v-if="relationErrors.relation_type_id" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ relationErrors.relation_type_id }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Reihenfolge</label>
            <input class="pim-input" type="number" v-model.number="relationForm.sort_order" />
          </div>
          <div class="col-span-2 relative">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Zielprodukt <span class="text-[var(--color-error)]">*</span></label>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)] z-10 pointer-events-none" :stroke-width="1.75" />
              <input class="pim-input pim-input-icon" v-model="productSearch" placeholder="SKU oder Name suchen…" @input="searchProducts" />
            </div>
            <p v-if="relationErrors.target_product_id" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ relationErrors.target_product_id }}</p>
            <!-- Search results dropdown -->
            <div v-if="productSearchResults.length > 0" class="absolute z-10 w-full mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg max-h-48 overflow-y-auto">
              <div
                v-for="p in productSearchResults"
                :key="p.id"
                class="px-3 py-2 hover:bg-[var(--color-bg)] cursor-pointer flex items-center gap-2"
                @click="selectTargetProduct(p)"
              >
                <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ p.sku }}</span>
                <span class="text-xs">{{ p.name }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="relationSaving" @click="createRelation">
            {{ relationSaving ? 'Speichern…' : 'Erstellen' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showRelationForm = false">Abbrechen</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="relationsLoading" class="text-center py-8">
        <p class="text-sm text-[var(--color-text-tertiary)]">Laden…</p>
      </div>
      <div v-else-if="relations.length === 0" class="text-center py-8">
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Beziehungen vorhanden</p>
      </div>

      <!-- Grid view (Kachelansicht) -->
      <div v-else-if="relationViewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="rel in filteredRelations" :key="rel.id" class="pim-card overflow-hidden group relative">
          <div class="aspect-[4/3] bg-[var(--color-bg)] flex items-center justify-center overflow-hidden p-2">
            <img v-if="relationTargetThumbs[rel.target_product?.id]" :src="relationTargetThumbs[rel.target_product?.id]" class="w-full h-full object-contain" loading="lazy" alt="" />
            <Image v-else class="w-8 h-8 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
          </div>
          <div class="p-2 space-y-0.5">
            <div class="flex items-center gap-1">
              <span class="text-[11px] font-mono text-[var(--color-text-secondary)]">{{ rel.target_product?.sku || '—' }}</span>
              <span class="text-[10px] text-[var(--color-text-tertiary)] ml-auto">{{ rel.relation_type?.name_de || '' }}</span>
            </div>
            <span class="text-xs text-[var(--color-text-primary)] truncate block">{{ rel.target_product?.name || '—' }}</span>
            <span v-if="rel.attribute_values?.length" class="flex items-center gap-0.5 text-[10px] text-[var(--color-text-tertiary)]">
              <Tags class="w-3 h-3" :stroke-width="1.75" /> {{ rel.attribute_values.length }} Attribute
            </span>
          </div>
          <div class="flex items-center gap-1 px-2 pb-2">
            <router-link
              :to="`/products/${rel.target_product?.id}`"
              class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-colors"
              title="Produkt öffnen"
              @click.stop
            >
              <ExternalLink class="w-3.5 h-3.5" :stroke-width="1.75" />
            </router-link>
            <button
              class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-amber-500 transition-colors"
              :title="watchlistIds.has(rel.target_product?.id) ? 'Von Merkliste entfernen' : 'Zur Merkliste'"
              @click.stop="toggleTargetWatchlist(rel.target_product?.id)"
            >
              <Star class="w-3.5 h-3.5" :stroke-width="1.75" :class="watchlistIds.has(rel.target_product?.id) ? 'text-amber-500 fill-amber-500' : ''" />
            </button>
            <div class="flex-1" />
            <button
              class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors"
              title="Löschen"
              @click.stop="relationDeleteTarget = rel"
            >
              <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
            </button>
          </div>
        </div>
      </div>

      <!-- List view (Listenansicht) mit Spaltenköpfen, Quick Lookup und Sortierung -->
      <div v-else class="pim-card overflow-hidden">
        <table class="w-full text-xs">
          <thead>
            <!-- Sortierbare Spaltenköpfe (Kern- + Metadaten-Spalten dynamisch) -->
            <tr class="border-b border-[var(--color-border)] bg-[var(--color-bg)]">
              <th
                v-for="col in visibleRelationColumns"
                :key="col.key"
                class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium"
                :class="col.sortable ? 'cursor-pointer select-none hover:text-[var(--color-text-primary)] transition-colors' : ''"
                @click="col.sortable && toggleRelationSort(col.key)"
              >
                <span class="inline-flex items-center gap-1">{{ col.label }}
                  <ChevronUp v-if="col.sortable && relationSortField === col.key && relationSortOrder === 'asc'" class="w-3 h-3" :stroke-width="2" />
                  <ChevronDown v-else-if="col.sortable && relationSortField === col.key && relationSortOrder === 'desc'" class="w-3 h-3" :stroke-width="2" />
                </span>
              </th>
              <th class="px-3 py-2 text-right text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium" style="width:100px"></th>
            </tr>
            <!-- Quick Lookup Filter-Zeile -->
            <tr v-if="relations.length > 3" class="border-b border-[var(--color-border)] bg-[var(--color-surface)]">
              <td v-for="col in visibleRelationColumns" :key="col.key" class="px-2 py-1.5">
                <select v-if="col.key === 'relation_type'" class="pim-input text-xs w-full py-1 px-2" :value="relationQuickLookup['relation_type'] || ''" @change="relationQuickLookup = { ...relationQuickLookup, relation_type: $event.target.value }">
                  <option value="">— Alle —</option>
                  <option v-for="t in relationTypesList" :key="t.id" :value="t.name_de || t.technical_name">{{ t.name_de || t.technical_name }}</option>
                </select>
                <input v-else-if="col.key === 'target_sku'" type="text" class="pim-input text-xs w-full py-1 px-2" placeholder="SKU…" :value="relationQuickLookup['target_sku'] || ''" @input="relationQuickLookup = { ...relationQuickLookup, target_sku: $event.target.value }" />
                <input v-else-if="col.key === 'target_name'" type="text" class="pim-input text-xs w-full py-1 px-2" placeholder="Name…" :value="relationQuickLookup['target_name'] || ''" @input="relationQuickLookup = { ...relationQuickLookup, target_name: $event.target.value }" />
              </td>
              <td class="px-2 py-1.5"></td>
            </tr>
          </thead>
          <tbody>
            <template v-for="rel in filteredRelations" :key="rel.id">
              <tr
                class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
                @click="toggleRelationExpand(rel)"
              >
                <td v-for="col in visibleRelationColumns" :key="col.key" class="px-3 py-2.5">
                  <span v-if="col.key === 'relation_type'" class="text-[var(--color-text-secondary)]">{{ rel.relation_type?.name_de || '—' }}</span>
                  <span v-else-if="col.key === 'target_sku'" class="font-mono text-[var(--color-text-secondary)]">{{ rel.target_product?.sku || '—' }}</span>
                  <span v-else-if="col.key === 'target_name'">{{ rel.target_product?.name || '—' }}</span>
                  <span v-else-if="col.key === 'sort_order'" class="flex items-center gap-1 text-[var(--color-text-tertiary)]">
                    <span>{{ rel.sort_order ?? '—' }}</span>
                    <Tags v-if="rel.attribute_values?.length" class="w-3 h-3 ml-2" :stroke-width="1.75" :title="(rel.attribute_values.length) + ' Attribute'" />
                  </span>
                  <span v-else class="text-[var(--color-text-secondary)]">{{ getRelationMetaCell(rel, col) || '—' }}</span>
                </td>
                <td class="px-3 py-2.5 text-right">
                  <div class="flex items-center justify-end gap-0.5">
                    <router-link
                      :to="`/products/${rel.target_product?.id}`"
                      class="p-1 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] hover:bg-[var(--color-bg)] transition-colors"
                      title="Produkt öffnen"
                      @click.stop
                    >
                      <ExternalLink class="w-3.5 h-3.5" :stroke-width="1.75" />
                    </router-link>
                    <button
                      class="p-1 rounded text-[var(--color-text-tertiary)] hover:text-amber-500 hover:bg-[var(--color-bg)] transition-colors"
                      :title="watchlistIds.has(rel.target_product?.id) ? 'Von Merkliste entfernen' : 'Zur Merkliste'"
                      @click.stop="toggleTargetWatchlist(rel.target_product?.id)"
                    >
                      <Star class="w-3.5 h-3.5" :stroke-width="1.75" :class="watchlistIds.has(rel.target_product?.id) ? 'text-amber-500 fill-amber-500' : ''" />
                    </button>
                    <button
                      class="p-1 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] hover:bg-[var(--color-error-light)] transition-colors"
                      title="Löschen"
                      @click.stop="relationDeleteTarget = rel"
                    >
                      <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
                    </button>
                  </div>
                </td>
              </tr>
              <!-- Expanded: Attribute editing -->
              <tr v-if="expandedRelationId === rel.id">
                <td :colspan="visibleRelationColumns.length + 1" class="border-b border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3">
                  <div class="space-y-3">
                    <div v-if="relationAttrLoading" class="text-center py-4">
                      <p class="text-xs text-[var(--color-text-tertiary)]">Attribute laden…</p>
                    </div>
                    <template v-else>
                      <h4 class="text-[12px] font-semibold text-[var(--color-text-primary)]">Beziehungsattribute</h4>

                      <!-- Existing attribute values -->
                      <div v-if="relationAttrValues.length > 0" class="space-y-2">
                        <div v-for="(attrVal, idx) in relationAttrValues" :key="attrVal.attribute_id" class="flex items-end gap-2">
                          <div class="flex-1">
                            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">
                              {{ attrVal.attribute?.name_de || attrVal.attribute?.technical_name || 'Attribut' }}
                            </label>
                            <PimAttributeInput
                              :type="mapDataTypeToInput(attrVal.attribute?.data_type || 'String')"
                              :modelValue="attrVal[getRelationAttrValueField(attrVal)]"
                              :options="attrVal.attribute?.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || []"
                              @update:modelValue="attrVal[getRelationAttrValueField(attrVal)] = $event"
                            />
                          </div>
                          <button
                            class="pim-btn pim-btn-secondary text-xs px-2 py-1.5 mb-0.5"
                            title="Entfernen"
                            @click="removeRelationAttribute(idx)"
                          >
                            <X class="w-3 h-3" :stroke-width="2" />
                          </button>
                        </div>
                      </div>
                      <p v-else class="text-[11px] text-[var(--color-text-tertiary)]">Keine Attribute gepflegt.</p>

                      <!-- Add attribute -->
                      <div class="flex items-end gap-2">
                        <div class="flex-1">
                          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attribut hinzufügen</label>
                          <PimAttributeInput
                            type="select"
                            v-model="newRelationAttr.attribute_id"
                            :options="relationAttrList.filter(a => !relationAttrValues.some(v => v.attribute_id === a.id)).map(a => ({ value: a.id, label: a.name_de || a.technical_name }))"
                            placeholder="Attribut wählen…"
                          />
                        </div>
                        <button class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 mb-0.5" :disabled="!newRelationAttr.attribute_id" @click="addRelationAttribute">
                          <Plus class="w-3 h-3" :stroke-width="2" /> Hinzufügen
                        </button>
                      </div>

                      <!-- Save button -->
                      <div class="flex justify-end pt-1">
                        <button class="pim-btn pim-btn-primary text-xs" :disabled="relationAttrSaving" @click="saveRelationAttrValues">
                          {{ relationAttrSaving ? 'Speichern…' : 'Attribute speichern' }}
                        </button>
                      </div>
                    </template>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Filter empty state -->
      <div v-if="!relationsLoading && relations.length > 0 && filteredRelations.length === 0" class="pim-card p-8 text-center">
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Beziehungen für „{{ relationFilter }}" gefunden</p>
      </div>

      <PimConfirmDialog
        :open="!!relationDeleteTarget"
        title="Beziehung löschen?"
        message="Diese Produktbeziehung wird entfernt."
        confirm-label="Löschen"
        :danger="true"
        :loading="relationDeleting"
        @confirm="confirmDeleteRelation"
        @cancel="relationDeleteTarget = null"
      />
    </div>

    <!-- ═══ Notes Tab ═══ -->
    <div v-else-if="activeTab === 'notes' && product" class="space-y-4">
      <ProductNotesTab :product-id="product.id" @counts-updated="onNoteCountsUpdated" />
    </div>

    <!-- ═══ Virtueller Cluster Tab ═══ -->
    <div v-else-if="activeTab === 'virtual-cluster' && product" class="space-y-4" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <div class="flex items-start gap-2">
        <Sparkles class="w-4 h-4 text-[var(--color-accent)] mt-0.5 shrink-0" :stroke-width="2" />
        <div>
          <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Cluster-Vererbung</h3>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Die Mitglieder dieses Klammer-Produkts werden live aus der gewählten Quelle aufgelöst.</p>
        </div>
      </div>

      <div v-if="virtualLoading" class="text-center py-8">
        <p class="text-sm text-[var(--color-text-tertiary)]">Laden…</p>
      </div>

      <template v-else>
        <!-- Definitions-Editor -->
        <div class="pim-card p-4 space-y-3">
          <!-- Quelle wählen -->
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Quelle</label>
            <div class="flex gap-2">
              <button
                v-for="opt in [{ v: 'search_profile', l: 'Suchprofil' }, { v: 'pql', l: 'PQL-Abfrage' }, { v: 'manual', l: 'Merkliste / manuell' }]"
                :key="opt.v"
                class="pim-btn text-xs"
                :class="virtualForm.source_type === opt.v ? 'pim-btn-primary' : 'pim-btn-secondary'"
                @click="virtualForm.source_type = opt.v"
              >{{ opt.l }}</button>
            </div>
          </div>

          <!-- Suchprofil -->
          <div v-if="virtualForm.source_type === 'search_profile'">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Suchprofil</label>
            <PimAttributeInput
              type="select"
              v-model="virtualForm.search_profile_id"
              :options="virtualSearchProfiles.map(p => ({ value: p.id, label: p.name }))"
            />
          </div>

          <!-- PQL -->
          <div v-else-if="virtualForm.source_type === 'pql'">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">PQL-Abfrage</label>
            <textarea
              v-model="virtualForm.pql_query"
              rows="3"
              class="pim-input font-mono text-xs"
              placeholder="SELECT * FROM products WHERE status = 'active'"
            />
          </div>

          <!-- Manuelle Auswahl -->
          <div v-else>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)]">Produkte ({{ virtualForm.manual_product_ids.length }})</label>
              <button class="pim-btn pim-btn-secondary text-[11px]" :disabled="virtualSaving" @click="virtualFromWatchlist">
                <ClipboardList class="w-3 h-3" :stroke-width="2" /> Aus Merkliste übernehmen
              </button>
            </div>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)] z-10 pointer-events-none" :stroke-width="1.75" />
              <input class="pim-input pim-input-icon" v-model="virtualProductSearch" placeholder="Produkt suchen (SKU oder Name)…" @input="searchVirtualProducts" />
              <div v-if="virtualProductSearchResults.length > 0" class="absolute z-10 w-full mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg max-h-48 overflow-y-auto">
                <div
                  v-for="p in virtualProductSearchResults"
                  :key="p.id"
                  class="px-3 py-2 hover:bg-[var(--color-bg)] cursor-pointer flex items-center gap-2"
                  @click="addVirtualManualProduct(p)"
                >
                  <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ p.sku }}</span>
                  <span class="text-xs">{{ p.name }}</span>
                </div>
              </div>
            </div>
            <div v-if="virtualManualProducts.length" class="mt-2 flex flex-wrap gap-1.5">
              <span v-for="p in virtualManualProducts" :key="p.id" class="inline-flex items-center gap-1 text-[11px] bg-[var(--color-bg)] border border-[var(--color-border)] rounded px-2 py-1">
                <span class="font-mono text-[var(--color-text-secondary)]">{{ p.sku }}</span>
                <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeVirtualManualProduct(p.id)">
                  <X class="w-3 h-3" :stroke-width="2" />
                </button>
              </span>
            </div>
          </div>

          <!-- Gemeinsame Optionen -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beziehungstyp (optional)</label>
              <PimAttributeInput
                type="select"
                v-model="virtualForm.relation_type_id"
                :options="relationTypesList.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))"
              />
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Max. Mitglieder (optional)</label>
              <input class="pim-input" type="number" min="1" v-model.number="virtualForm.max_members" placeholder="500" />
            </div>
          </div>

          <div class="flex gap-2">
            <button class="pim-btn pim-btn-primary text-xs" :disabled="virtualSaving" @click="saveVirtualDefinition">
              <Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ virtualSaving ? 'Speichern…' : 'Speichern & Aktualisieren' }}
            </button>
          </div>
        </div>

        <!-- Vererbungsregeln (Phase 1: Attribute) -->
        <div class="pim-card p-4 space-y-3">
          <div>
            <h4 class="text-sm font-medium text-[var(--color-text-primary)]">Vererbungsregeln</h4>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">
              Attribute, die per Sync an die Mitglieder vererbt werden. Die Werte selbst pflegst du im Reiter „Attribute“ dieses Produkts.
              Ein Mitglied gehört immer zu höchstens einem Cluster — bereits einem anderen Cluster zugeordnete Mitglieder werden beim Sync übersprungen.
            </p>
          </div>

          <div v-if="virtualRulesLoading" class="text-center py-4">
            <p class="text-sm text-[var(--color-text-tertiary)]">Laden…</p>
          </div>
          <div v-else-if="virtualOwnAttributes.length === 0" class="text-center py-4">
            <p class="text-sm text-[var(--color-text-tertiary)]">Dieses Produkt hat noch keine eigenen Attributwerte. Im Reiter „Attribute“ pflegen, um sie hier vererben zu können.</p>
          </div>
          <div v-else class="pim-card overflow-hidden">
            <table class="w-full text-xs">
              <thead>
                <tr class="border-b border-[var(--color-border)] bg-[var(--color-bg)]">
                  <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium" style="width:32px"></th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium">Attribut</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium">Bei Konflikt mit lokalem Wert</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="attr in virtualOwnAttributes" :key="attr.attribute_id" class="border-b border-[var(--color-border)] last:border-0">
                  <td class="px-3 py-2">
                    <input type="checkbox" v-model="virtualRules[attr.attribute_id].enabled" />
                  </td>
                  <td class="px-3 py-2 text-[var(--color-text-primary)]">{{ attr.name }}</td>
                  <td class="px-3 py-2">
                    <select
                      class="pim-input text-xs py-1"
                      v-model="virtualRules[attr.attribute_id].conflict_mode"
                      :disabled="!virtualRules[attr.attribute_id].enabled"
                    >
                      <option value="keep_local">Lokalen Wert belassen</option>
                      <option value="force_override">Überschreiben</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex gap-2">
            <button class="pim-btn pim-btn-secondary text-xs" :disabled="virtualRulesSaving" @click="saveVirtualInheritanceRules">
              {{ virtualRulesSaving ? 'Speichern…' : 'Regeln speichern' }}
            </button>
            <button class="pim-btn pim-btn-primary text-xs" :disabled="virtualSyncing" @click="syncVirtualCluster">
              <RefreshCw class="w-3.5 h-3.5" :stroke-width="2" :class="{ 'animate-spin': virtualSyncing }" /> {{ virtualSyncing ? 'Synchronisiere…' : 'Jetzt synchronisieren' }}
            </button>
          </div>

          <!-- Sync-Report -->
          <div v-if="virtualSyncReport" class="text-[11px] bg-[var(--color-bg)] border border-[var(--color-border)] rounded-md p-3 space-y-1">
            <p class="text-[var(--color-text-primary)] font-medium">Ergebnis der letzten Synchronisierung</p>
            <p class="text-[var(--color-text-secondary)]">
              {{ virtualSyncReport.member_count }} Mitglieder verarbeitet ·
              {{ virtualSyncReport.values_created }} neu ·
              {{ virtualSyncReport.values_updated }} aktualisiert ·
              {{ virtualSyncReport.values_overridden }} überschrieben ·
              {{ virtualSyncReport.values_kept_local }} lokal belassen ·
              {{ virtualSyncReport.values_removed }} entfernt
            </p>
            <p v-if="Object.keys(virtualSyncReport.released_members || {}).length" class="text-[var(--color-text-tertiary)]">
              Cluster verlassen (Werte entfernt): {{ Object.values(virtualSyncReport.released_members).join(', ') }}
            </p>
            <div v-if="virtualSyncReport.skipped_members?.length" class="text-amber-600">
              <p class="font-medium">Übersprungen ({{ virtualSyncReport.skipped_members.length }}):</p>
              <ul class="list-disc list-inside">
                <li v-for="s in virtualSyncReport.skipped_members" :key="s.id">{{ s.sku }} — {{ s.reason }}</li>
              </ul>
            </div>
            <div v-if="virtualSyncReport.media" class="pt-1 border-t border-[var(--color-border)]">
              <p class="text-[var(--color-text-primary)] font-medium">Medien</p>
              <p class="text-[var(--color-text-secondary)]">
                {{ virtualSyncReport.media.member_count }} Mitglieder verarbeitet ·
                {{ virtualSyncReport.media.assignments_created }} neu ·
                {{ virtualSyncReport.media.assignments_updated }} aktualisiert ·
                {{ virtualSyncReport.media.assignments_overridden }} überschrieben ·
                {{ virtualSyncReport.media.assignments_kept_local }} lokal belassen ·
                {{ virtualSyncReport.media.assignments_removed }} entfernt
              </p>
              <p v-if="Object.keys(virtualSyncReport.media.released_members || {}).length" class="text-[var(--color-text-tertiary)]">
                Cluster verlassen (Medien entfernt): {{ Object.values(virtualSyncReport.media.released_members).join(', ') }}
              </p>
              <div v-if="virtualSyncReport.media.skipped_members?.length" class="text-amber-600">
                <p class="font-medium">Übersprungen ({{ virtualSyncReport.media.skipped_members.length }}):</p>
                <ul class="list-disc list-inside">
                  <li v-for="s in virtualSyncReport.media.skipped_members" :key="s.id">{{ s.sku }} — {{ s.reason }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Vererbungsregeln (Phase 2: Medien) -->
        <div class="pim-card p-4 space-y-3">
          <div>
            <h4 class="text-sm font-medium text-[var(--color-text-primary)]">Medien-Vererbungsregeln</h4>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">
              Bildtypen (Usage-Types), deren Medien-Zuordnungen per Sync an die Mitglieder vererbt werden. Die Medien selbst pflegst du im Reiter „Medien“ dieses Produkts.
              Ein Mitglied gehört bei Medien immer zu höchstens einem Cluster — bereits einem anderen Cluster zugeordnete Mitglieder werden beim Sync übersprungen.
            </p>
          </div>

          <div v-if="virtualMediaRulesLoading" class="text-center py-4">
            <p class="text-sm text-[var(--color-text-tertiary)]">Laden…</p>
          </div>
          <div v-else-if="productTypeUsageTypes.length === 0" class="text-center py-4">
            <p class="text-sm text-[var(--color-text-tertiary)]">Keine Bildtypen (Usage-Types) für diesen Produkttyp vorhanden.</p>
          </div>
          <div v-else class="pim-card overflow-hidden">
            <table class="w-full text-xs">
              <thead>
                <tr class="border-b border-[var(--color-border)] bg-[var(--color-bg)]">
                  <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium" style="width:32px"></th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium">Bildtyp</th>
                  <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium">Bei Konflikt mit lokalen Medien</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ut in productTypeUsageTypes" :key="ut.id" class="border-b border-[var(--color-border)] last:border-0">
                  <td class="px-3 py-2">
                    <input type="checkbox" v-model="virtualMediaRules[ut.id].enabled" />
                  </td>
                  <td class="px-3 py-2 text-[var(--color-text-primary)]">{{ ut.name_de || ut.technical_name }}</td>
                  <td class="px-3 py-2">
                    <select
                      class="pim-input text-xs py-1"
                      v-model="virtualMediaRules[ut.id].conflict_mode"
                      :disabled="!virtualMediaRules[ut.id].enabled"
                    >
                      <option value="keep_local">Lokale Medien belassen</option>
                      <option value="force_override">Überschreiben</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex gap-2">
            <button class="pim-btn pim-btn-secondary text-xs" :disabled="virtualMediaRulesSaving" @click="saveVirtualMediaInheritanceRules">
              {{ virtualMediaRulesSaving ? 'Speichern…' : 'Regeln speichern' }}
            </button>
            <button class="pim-btn pim-btn-primary text-xs" :disabled="virtualSyncing" @click="syncVirtualCluster">
              <RefreshCw class="w-3.5 h-3.5" :stroke-width="2" :class="{ 'animate-spin': virtualSyncing }" /> {{ virtualSyncing ? 'Synchronisiere…' : 'Jetzt synchronisieren' }}
            </button>
          </div>
        </div>

        <!-- Live-Vorschau der Mitglieder -->
        <div>
          <div class="flex items-center gap-2 mb-2">
            <h4 class="text-sm font-medium text-[var(--color-text-primary)]">Mitglieder (Live-Vorschau)</h4>
            <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ virtualMembers.length }}</span>
            <button class="ml-auto pim-btn pim-btn-secondary text-[11px]" :disabled="virtualMembersLoading" @click="loadVirtualMembers">
              <RefreshCw class="w-3 h-3" :stroke-width="2" :class="{ 'animate-spin': virtualMembersLoading }" /> Aktualisieren
            </button>
          </div>

          <div v-if="virtualMembersLoading" class="text-center py-8">
            <p class="text-sm text-[var(--color-text-tertiary)]">Laden…</p>
          </div>
          <div v-else-if="virtualMembers.length === 0" class="text-center py-8">
            <p class="text-sm text-[var(--color-text-tertiary)]">Keine Mitglieder — Quelle wählen und speichern.</p>
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <div v-for="m in virtualMembers" :key="m.id" class="pim-card overflow-hidden group relative">
              <div class="aspect-[4/3] bg-[var(--color-bg)] flex items-center justify-center overflow-hidden p-2">
                <img v-if="virtualMemberThumbs[m.id]" :src="virtualMemberThumbs[m.id]" class="w-full h-full object-contain" loading="lazy" alt="" />
                <Image v-else class="w-8 h-8 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
              </div>
              <div class="p-2 space-y-0.5">
                <span class="text-[11px] font-mono text-[var(--color-text-secondary)] block">{{ m.sku || '—' }}</span>
                <span class="text-xs text-[var(--color-text-primary)] truncate block">{{ m.name || '—' }}</span>
              </div>
              <div class="flex items-center gap-1 px-2 pb-2">
                <router-link
                  :to="`/products/${m.id}`"
                  class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-colors"
                  title="Produkt öffnen"
                  @click.stop
                >
                  <ExternalLink class="w-3.5 h-3.5" :stroke-width="1.75" />
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ═══ Output Hierarchies Tab ═══ -->
    <div v-else-if="activeTab === 'output-hierarchies' && product" class="space-y-3" :class="{ 'pointer-events-none opacity-75': isTabReadOnly }">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Ausgabehierarchie-Zuordnungen</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="toggleOutputHierarchyForm">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Zuordnung hinzufügen
        </button>
      </div>

      <!-- Add form -->
      <div v-if="showOutputHierarchyForm" class="pim-card p-4 space-y-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Ausgabehierarchie</label>
          <select class="pim-input text-xs w-full max-w-sm" :value="selectedOutputHierarchyId || ''" @change="onOutputHierarchyChange($event.target.value)">
            <option value="">— Hierarchie wählen —</option>
            <option v-for="h in outputHierarchies" :key="h.id" :value="h.id">{{ h.name_de || h.technical_name }}</option>
          </select>
        </div>

        <div v-if="selectedOutputHierarchyId">
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
            Knoten <span class="text-[var(--color-text-tertiary)] font-normal">(Mehrfachauswahl — ein übergeordneter Knoten markiert automatisch alle Kinder mit)</span>
          </label>
          <div v-if="outputHierarchyTreeLoading" class="space-y-2">
            <div v-for="i in 4" :key="i" class="pim-skeleton h-6 rounded" />
          </div>
          <OutputHierarchyNodeTree
            v-else
            v-model="selectedOutputNodeIds"
            :nodes="outputHierarchyTreeNodes"
            :disabled-ids="alreadyAssignedNodeIds"
          />
          <p class="text-[11px] text-[var(--color-text-tertiary)] mt-1">{{ selectedOutputNodeIds.length }} Knoten ausgewählt</p>
        </div>

        <p v-if="bulkAssignOutputError" class="text-xs text-[var(--color-error)]">{{ bulkAssignOutputError }}</p>

        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="!selectedOutputNodeIds.length || bulkAssigningOutputNodes" @click="bulkAssignSelectedOutputNodes">
            {{ bulkAssigningOutputNodes ? 'Ordne zu…' : `Zuordnen${selectedOutputNodeIds.length ? ` (${selectedOutputNodeIds.length})` : ''}` }}
          </button>
          <button class="pim-btn pim-btn-ghost text-xs" @click="cancelOutputHierarchyForm">Abbrechen</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="outputHierarchyLoading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="pim-skeleton h-10 rounded" />
      </div>

      <!-- Filter -->
      <div v-else-if="outputHierarchyAssignments.length > 0" class="flex items-center gap-2">
        <select v-model="outputHierarchyFilterHierarchyId" class="pim-input text-xs max-w-[220px]">
          <option value="">Alle Hierarchien</option>
          <option v-for="h in outputHierarchies" :key="h.id" :value="h.id">{{ h.name_de || h.technical_name }}</option>
        </select>
        <input v-model="outputHierarchyFilterNodeText" type="text" placeholder="Knoten filtern…" class="pim-input text-xs max-w-[220px]">
        <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ filteredOutputHierarchyAssignments.length }} von {{ outputHierarchyAssignments.length }}</span>
      </div>

      <!-- Assignment list -->
      <div v-if="!outputHierarchyLoading && filteredOutputHierarchyAssignments.length > 0" class="pim-card overflow-hidden">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px] uppercase tracking-wider">
              <th class="px-3 py-2 text-left w-6"></th>
              <th class="px-3 py-2 text-left">Hierarchie</th>
              <th class="px-3 py-2 text-left">Knoten</th>
              <th class="px-3 py-2 text-right w-12">#</th>
              <th class="px-3 py-2 text-right w-16">Aktion</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="assignment in filteredOutputHierarchyAssignments" :key="assignment.id">
              <tr
                class="border-t border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
                @click="toggleRelationshipAttrs(assignment.id)"
              >
                <td class="px-2 py-2 text-[var(--color-text-tertiary)]">
                  <component :is="expandedAssignmentId === assignment.id ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" :stroke-width="2" />
                </td>
                <td class="px-3 py-2 text-[var(--color-text-secondary)]">{{ assignment.hierarchy_node?.hierarchy?.name_de || '—' }}</td>
                <td class="px-3 py-2 font-medium text-[var(--color-text-primary)]">{{ assignment.hierarchy_node?.name_de || '—' }}</td>
                <td class="px-3 py-2 text-right font-mono text-[var(--color-text-tertiary)]">{{ assignment.sort_order ?? 0 }}</td>
                <td class="px-3 py-2 text-right">
                  <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click.stop="outputHierarchyDeleteTarget = assignment" title="Entfernen">
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                  </button>
                </td>
              </tr>
              <!-- Beziehungs-Attribute (aufklappbar) -->
              <tr v-if="expandedAssignmentId === assignment.id">
                <td :colspan="5" class="px-4 py-3 bg-[var(--color-bg)]">
                  <div v-if="relationshipAttrLoading" class="space-y-2">
                    <div v-for="i in 2" :key="i" class="pim-skeleton h-8 rounded" />
                  </div>
                  <div v-else-if="relationshipAttrs.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">
                    Keine Beziehungs-Attribute definiert. Attribute mit Scope "Beziehung" oder "Beide" in der Hierarchie-Konfiguration zuordnen.
                  </div>
                  <div v-else class="space-y-2">
                    <div v-for="attr in relationshipAttrs" :key="attr.attribute_id" class="flex items-center gap-3">
                      <label class="text-xs text-[var(--color-text-secondary)] w-36 shrink-0 truncate" :title="attr.attribute_name_de">
                        {{ attr.attribute_name_de || attr.attribute_technical_name }}
                      </label>
                      <template v-if="attr.data_type === 'Flag'">
                        <input
                          type="checkbox"
                          class="pim-checkbox"
                          :checked="!!relationshipAttrValues[attr.attribute_id]"
                          @change="relationshipAttrValues[attr.attribute_id] = $event.target.checked"
                        />
                      </template>
                      <template v-else-if="attr.data_type === 'Number' || attr.data_type === 'Float'">
                        <input
                          type="number"
                          class="pim-input text-xs flex-1"
                          :value="relationshipAttrValues[attr.attribute_id]"
                          @input="relationshipAttrValues[attr.attribute_id] = $event.target.value"
                          step="any"
                        />
                      </template>
                      <template v-else-if="attr.data_type === 'Date'">
                        <input
                          type="date"
                          class="pim-input text-xs flex-1"
                          :value="relationshipAttrValues[attr.attribute_id]"
                          @input="relationshipAttrValues[attr.attribute_id] = $event.target.value"
                        />
                      </template>
                      <template v-else>
                        <input
                          type="text"
                          class="pim-input text-xs flex-1"
                          :value="relationshipAttrValues[attr.attribute_id]"
                          @input="relationshipAttrValues[attr.attribute_id] = $event.target.value"
                          :placeholder="attr.attribute_technical_name"
                        />
                      </template>
                    </div>
                    <div class="flex justify-end pt-1">
                      <button
                        class="pim-btn pim-btn-primary text-xs"
                        :disabled="relationshipAttrSaving"
                        @click.stop="saveRelationshipAttrs"
                      >
                        <Save class="w-3 h-3" :stroke-width="2" />
                        {{ relationshipAttrSaving ? 'Speichert...' : 'Speichern' }}
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <p v-else-if="!outputHierarchyLoading && outputHierarchyAssignments.length > 0" class="text-xs text-[var(--color-text-tertiary)] py-8 text-center">
        Kein Treffer für die aktuellen Filter.
      </p>
      <p v-else-if="!outputHierarchyLoading" class="text-xs text-[var(--color-text-tertiary)] py-8 text-center">
        Keine Ausgabehierarchie-Zuordnungen. Klicken Sie auf "Zuordnung hinzufügen" um eine Hierarchie zuzuweisen.
      </p>

      <!-- Delete confirm -->
      <PimConfirmDialog
        :open="!!outputHierarchyDeleteTarget"
        title="Zuordnung entfernen?"
        message="Die Zuordnung dieses Produkts zum Ausgabehierarchie-Knoten wird entfernt."
        confirm-label="Entfernen"
        :danger="true"
        :loading="outputHierarchyDeleting"
        @confirm="confirmDeleteOutputHierarchyAssignment"
        @cancel="outputHierarchyDeleteTarget = null"
      />
    </div>

    <!-- ═══ Preview Tab (Generic) ═══ -->
    <div v-else-if="activeTab === 'preview' && product" class="space-y-3">
      <!-- Header with language switcher + export buttons -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Export &amp; Vorschau</h3>
          <div v-if="localeStore.activeDataLocales.length > 1" class="flex items-center gap-1">
            <button
              class="px-2 py-0.5 text-[11px] rounded-md font-medium transition-colors"
              :class="previewLang === null
                ? 'bg-[var(--color-accent)] text-white'
                : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
              @click="switchPreviewLang(null)"
            >Alle</button>
            <button
              v-for="loc in localeStore.activeDataLocales"
              :key="loc"
              class="px-2 py-0.5 text-[11px] rounded-md font-medium transition-colors"
              :class="previewLang === loc
                ? 'bg-[var(--color-accent)] text-white'
                : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
              @click="switchPreviewLang(loc)"
            >{{ loc.toUpperCase() }}</button>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-secondary text-xs" :disabled="excelLoading" @click="downloadExcel">
            <Download v-if="!excelLoading" class="w-3.5 h-3.5" :stroke-width="1.75" />
            <span v-else class="w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin inline-block" />
            Excel
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" :disabled="pdfLoading" @click="downloadPdf">
            <Download v-if="!pdfLoading" class="w-3.5 h-3.5" :stroke-width="1.75" />
            <span v-else class="w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin inline-block" />
            PDF
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showPdfTemplatePicker = true">
            <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
            PDF-Vorlage
          </button>
        </div>
      </div>
      <div v-if="downloadError" class="text-xs text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded">
        {{ downloadError }}
      </div>

      <!-- Loading state -->
      <div v-if="previewLoading" class="space-y-3">
        <div class="pim-card p-6"><div class="pim-skeleton h-20 w-full rounded" /></div>
        <div class="pim-card p-6"><div class="pim-skeleton h-32 w-full rounded" /></div>
        <div class="pim-card p-6"><div class="pim-skeleton h-24 w-full rounded" /></div>
      </div>

      <template v-else-if="previewData">
        <!-- Completeness Gauge -->
        <div v-if="completenessData" class="pim-card p-4">
          <div class="flex items-center gap-5">
            <div v-html="completenessData.chart_svg" class="shrink-0 w-[80px] h-[80px] [&>svg]:w-full [&>svg]:h-full" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-[var(--color-text-primary)]">
                Vollständigkeit: {{ completenessData.overall_percentage }}%
              </p>
              <p class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">
                {{ completenessData.filled_fields }} von {{ completenessData.total_fields }} Pflichtfeldern befüllt
              </p>
              <div class="flex flex-wrap gap-1.5 mt-2">
                <span
                  v-for="s in completenessData.sections"
                  :key="s.name"
                  :class="[
                    'inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-medium',
                    s.percentage >= 100 ? 'bg-green-100 text-green-700' :
                    s.percentage >= 50 ? 'bg-amber-100 text-amber-700' :
                    'bg-red-100 text-red-700'
                  ]"
                >
                  {{ s.name }}: {{ s.percentage }}%
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stammdaten (compact header card) -->
        <div class="pim-card px-5 py-4">
          <p class="text-base font-semibold text-[var(--color-text-primary)] leading-tight">
            {{ previewData.stammdaten.name || '—' }}
          </p>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-[12px] text-[var(--color-text-secondary)]">
            <span><span class="text-[var(--color-text-tertiary)]">SKU:</span> <span class="font-mono">{{ previewData.stammdaten.sku || '—' }}</span></span>
            <span v-if="!product.product_type || product.product_type.has_ean !== false"><span class="text-[var(--color-text-tertiary)]">EAN:</span> <span class="font-mono">{{ previewData.stammdaten.ean || '—' }}</span></span>
            <span :class="[
              'pim-badge text-[11px]',
              previewData.stammdaten.status === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]'
            ]">
              {{ previewData.stammdaten.status || '—' }}
            </span>
            <span><span class="text-[var(--color-text-tertiary)]">Typ:</span> {{ previewData.stammdaten.product_type?.name || '—' }}</span>
          </div>
          <p v-if="previewData.stammdaten.category_breadcrumb?.length" class="text-[11px] text-[var(--color-text-tertiary)] mt-2">
            {{ previewData.stammdaten.category_breadcrumb.map(b => b.name).join(' › ') }}
          </p>
          <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-2 text-[11px] text-[var(--color-text-tertiary)]">
            <span v-if="previewData.stammdaten.created_at">
              Erstellt: {{ new Date(previewData.stammdaten.created_at).toLocaleDateString('de-DE') }}
              <span v-if="previewData.stammdaten.created_by"> von {{ previewData.stammdaten.created_by }}</span>
            </span>
            <span v-if="previewData.stammdaten.updated_at">
              Aktualisiert: {{ new Date(previewData.stammdaten.updated_at).toLocaleDateString('de-DE') }}
              <span v-if="previewData.stammdaten.updated_by"> von {{ previewData.stammdaten.updated_by }}</span>
            </span>
          </div>
        </div>

        <!-- Attribute Sections (table layout) -->
        <PimCollectionGroup
          v-for="section in previewData.attribute_sections"
          :key="section.section_name"
          :title="section.section_name"
          :filledCount="section.attributes.filter(a => a.is_mandatory && a.display_value !== null).length"
          :totalCount="section.attributes.filter(a => a.is_mandatory).length"
          :defaultOpen="true"
        >
          <div class="pt-2">
            <!-- Table header -->
            <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-1.5 text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider border-b border-[var(--color-border)]">
              <span>Attribut</span>
              <span>Wert</span>
              <span></span>
            </div>
            <!-- Rows -->
            <template v-for="attr in section.attributes" :key="attr.attribute_id + (attr.language || '')">
              <template v-if="!attr.parent_attribute_id">
                <!-- Composite attribute -->
                <div v-if="attr.data_type === 'Composite'">
                  <!-- Vermehrbares Composite: Instanzen als nummerierte Liste -->
                  <template v-if="attr.is_multipliable && attr.multiplied_instances?.length > 0">
                    <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-2 border-b border-[var(--color-border)] items-center">
                      <span class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                        {{ attr.label }}
                        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[9px] ml-1">{{ attr.multiplied_instances.length }}×</span>
                      </span>
                      <span class="text-[12px] text-[var(--color-text-primary)]">
                        {{ attr.multiplied_instances.length }} Einträge
                      </span>
                      <span class="inline-block w-2 h-2 rounded-full mx-auto bg-[var(--color-success)]" />
                    </div>
                    <div
                      v-for="(inst, instIdx) in attr.multiplied_instances"
                      :key="instIdx"
                      class="border-b border-[var(--color-border)]"
                    >
                      <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-1 items-center bg-[var(--color-bg)]/50">
                        <span class="text-[10px] font-mono text-[var(--color-text-tertiary)] pl-4">#{{ instIdx + 1 }}</span>
                        <span class="text-[11px] text-[var(--color-text-secondary)]">{{ inst._formatted || '' }}</span>
                        <span></span>
                      </div>
                      <div
                        v-for="child in inst.children || []"
                        :key="child.attribute_id"
                        class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-1 items-center"
                      >
                        <span class="text-[11px] text-[var(--color-text-tertiary)] pl-8">{{ child.label }}</span>
                        <span class="text-[11px] text-[var(--color-text-secondary)]">{{ child.display_value || '—' }}</span>
                        <span :class="['inline-block w-1.5 h-1.5 rounded-full mx-auto', child.display_value ? 'bg-[var(--color-success)]' : 'border border-[var(--color-text-tertiary)]']" />
                      </div>
                    </div>
                  </template>
                  <!-- Einfaches Composite (nicht vermehrbar) -->
                  <template v-else>
                    <div :class="[
                      'grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-2 border-b border-[var(--color-border)] items-center',
                      !getPreviewCompositeSummary(attr, section.attributes) ? 'bg-red-50/60' : ''
                    ]">
                      <span class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                        {{ attr.label }}
                        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[9px] ml-1">Composite</span>
                      </span>
                      <span class="text-[12px] text-[var(--color-text-primary)]">
                        {{ getPreviewCompositeSummary(attr, section.attributes) || '—' }}
                      </span>
                      <span :class="['inline-block w-2 h-2 rounded-full mx-auto', getPreviewCompositeSummary(attr, section.attributes) ? 'bg-[var(--color-success)]' : 'border-2 border-[var(--color-text-tertiary)]']" />
                    </div>
                    <!-- Child attributes (inkl. Sub-Composite Kinder) -->
                    <template v-for="child in section.attributes.filter(a => a.parent_attribute_id === attr.attribute_id)" :key="child.attribute_id">
                      <div v-if="child.data_type === 'Composite'" class="border-b border-[var(--color-border)]">
                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-1.5 items-center">
                          <span class="text-[11px] text-[var(--color-text-tertiary)] pl-4 font-medium">{{ child.label }}</span>
                          <span></span>
                          <span></span>
                        </div>
                        <div
                          v-for="gc in section.attributes.filter(a => a.parent_attribute_id === child.attribute_id)"
                          :key="gc.attribute_id"
                          :class="['grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-1 items-center', !gc.display_value ? 'bg-red-50/60' : '']"
                        >
                          <span class="text-[11px] text-[var(--color-text-tertiary)] pl-8">{{ gc.label }}</span>
                          <span class="text-[11px] text-[var(--color-text-secondary)]">{{ gc.formatted_value ?? gc.display_value ?? '—' }}</span>
                          <span :class="['inline-block w-1.5 h-1.5 rounded-full mx-auto', gc.display_value ? 'bg-[var(--color-success)]' : 'border border-[var(--color-text-tertiary)]']" />
                        </div>
                      </div>
                      <div
                        v-else
                        :class="['grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-1.5 border-b border-[var(--color-border)] items-center', !child.display_value ? 'bg-red-50/60' : '']"
                      >
                        <span class="text-[11px] text-[var(--color-text-tertiary)] pl-4">{{ child.label }}</span>
                        <span class="text-[11px] text-[var(--color-text-secondary)]">
                          {{ child.formatted_value ?? child.display_value ?? '—' }}
                          <span v-if="child.unit" class="text-[var(--color-text-tertiary)]"> {{ child.unit }}</span>
                        </span>
                        <span :class="['inline-block w-1.5 h-1.5 rounded-full mx-auto', child.display_value ? 'bg-[var(--color-success)]' : 'border border-[var(--color-text-tertiary)]']" />
                      </div>
                    </template>
                  </template>
                </div>
                <!-- Normal attribute -->
                <div v-else :class="[
                  'grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_24px] gap-x-3 px-2 py-2 border-b border-[var(--color-border)] last:border-0',
                  !(attr.display_value || attr.link_data) ? 'bg-red-50/60' : '',
                  attr.link_data ? 'items-start' : 'items-center'
                ]">
                  <span class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                    {{ attr.label }}
                    <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
                    <span v-if="attr.language" class="text-[10px] text-[var(--color-text-tertiary)] ml-1">[{{ attr.language }}]</span>
                  </span>
                  <div class="text-[12px] text-[var(--color-text-primary)]">
                    <!-- Link-type attributes -->
                    <template v-if="attr.link_data">
                      <a :href="attr.link_data.url" target="_blank" rel="noopener noreferrer"
                         class="text-[var(--color-accent)] hover:underline break-all">
                        {{ attr.link_data.title || attr.link_data.url }}
                      </a>
                      <!-- Image preview -->
                      <img v-if="attr.data_type === 'ImageLink'" :src="attr.link_data.url"
                           :alt="attr.link_data.alt_text || attr.label"
                           class="mt-1.5 max-h-20 rounded border border-[var(--color-border)]" loading="lazy" />
                      <!-- PDF preview -->
                      <PdfPreview v-else-if="attr.data_type === 'PdfLink'" :url="attr.link_data.url" :title="''" max-height="12rem" />
                      <!-- Video embed -->
                      <template v-else-if="attr.data_type === 'VideoLink'">
                        <iframe v-if="getPreviewVideoEmbedUrl(attr.link_data.url)"
                                :src="getPreviewVideoEmbedUrl(attr.link_data.url)"
                                class="mt-1.5 w-full aspect-video max-h-40 rounded border border-[var(--color-border)]"
                                allowfullscreen loading="lazy" />
                        <video v-else :src="attr.link_data.url" controls
                               class="mt-1.5 w-full max-h-40 rounded border border-[var(--color-border)]" />
                      </template>
                    </template>
                    <!-- DelimitedValue: Badges -->
                    <template v-else-if="attr.data_type === 'DelimitedValue' && attr.display_value">
                      <div class="flex flex-wrap gap-1">
                        <span
                          v-for="(val, dvIdx) in attr.display_value.split(', ')"
                          :key="dvIdx"
                          class="inline-block px-1.5 py-0.5 rounded bg-[var(--color-accent)]/10 text-[var(--color-accent)] text-[11px] font-medium"
                        >{{ val }}</span>
                      </div>
                    </template>
                    <!-- JsonArtefact: Code-Block -->
                    <template v-else-if="attr.data_type === 'JsonArtefact' && attr.display_value">
                      <code class="block text-[11px] font-mono bg-[var(--color-bg)] rounded px-2 py-1 max-h-24 overflow-auto whitespace-pre-wrap break-all">{{ attr.display_value }}</code>
                    </template>
                    <!-- Multipliable: Bullet-Liste -->
                    <template v-else-if="attr.display_values?.length > 1">
                      <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(v, vi) in attr.display_values" :key="vi">{{ v }}<span v-if="attr.unit" class="text-[var(--color-text-tertiary)]"> {{ attr.unit }}</span></li>
                      </ul>
                    </template>
                    <!-- Normal value -->
                    <template v-else>
                      {{ attr.formatted_value ?? attr.display_value ?? '—' }}
                      <span v-if="attr.unit" class="text-[var(--color-text-tertiary)]"> {{ attr.unit }}</span>
                    </template>
                  </div>
                  <span :class="['inline-block w-2 h-2 rounded-full mx-auto mt-1', (attr.display_value || attr.link_data) ? 'bg-[var(--color-success)]' : 'border-2 border-[var(--color-text-tertiary)]']" />
                </div>
              </template>
            </template>
          </div>
        </PimCollectionGroup>

        <!-- Relations -->
        <PimCollectionGroup
          v-if="previewData.relations.length > 0"
          title="Beziehungen"
          :filledCount="previewData.relations.length"
          :totalCount="previewData.relations.length"
          :defaultOpen="false"
        >
          <div class="pt-3">
            <PimTable
              :columns="[
                { key: 'relation_type', label: 'Typ' },
                { key: 'target_product.sku', label: 'Ziel-SKU', mono: true },
                { key: 'target_product.name', label: 'Zielprodukt' },
                { key: 'sort_order', label: 'Reihenfolge' },
              ]"
              :rows="previewData.relations"
              emptyText="Keine Beziehungen"
            />
          </div>
        </PimCollectionGroup>

        <!-- Prices -->
        <PimCollectionGroup
          v-if="previewData.prices.length > 0"
          title="Preise"
          :filledCount="previewData.prices.length"
          :totalCount="previewData.prices.length"
          :defaultOpen="false"
        >
          <div class="pt-3">
            <PimTable
              :columns="[
                { key: 'price_type', label: 'Preistyp' },
                { key: 'amount', label: 'Betrag', align: 'right' },
                { key: 'currency', label: 'Währung' },
                { key: 'valid_from', label: 'Gültig ab' },
                { key: 'valid_to', label: 'Gültig bis' },
                { key: 'country', label: 'Land' },
              ]"
              :rows="previewData.prices"
              emptyText="Keine Preise"
            >
              <template #cell-amount="{ value }">
                <span class="font-mono">{{ value ? Number(value).toFixed(2) : '—' }}</span>
              </template>
              <template #cell-valid_from="{ value }">
                <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
              </template>
              <template #cell-valid_to="{ value }">
                <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
              </template>
            </PimTable>
          </div>
        </PimCollectionGroup>

        <!-- Media -->
        <PimCollectionGroup
          v-if="previewData.media.length > 0"
          title="Media"
          :filledCount="previewData.media.length"
          :totalCount="previewData.media.length"
          :defaultOpen="false"
        >
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 pt-3">
            <div v-for="m in previewData.media" :key="m.id" class="pim-card overflow-hidden">
              <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
                <img :src="mediaApi.fileUrl(m.file_name)" class="w-full h-full object-cover" loading="lazy" :alt="m.alt || ''" />
              </div>
              <div class="p-2">
                <span class="text-[11px] text-[var(--color-text-primary)] truncate block">{{ m.file_name || '—' }}</span>
                <div class="flex items-center gap-1 mt-0.5">
                  <span v-if="m.is_primary" class="text-[10px] text-[var(--color-accent)] font-medium">Primär</span>
                  <span v-if="m.usage_type" class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.usage_type?.name_de || m.usage_type?.technical_name || '' }}</span>
                </div>
              </div>
            </div>
          </div>
        </PimCollectionGroup>

        <!-- Variants -->
        <PimCollectionGroup
          v-if="previewData.variants.length > 0"
          title="Varianten"
          :filledCount="previewData.variants.length"
          :totalCount="previewData.variants.length"
          :defaultOpen="false"
        >
          <div class="pt-3">
            <PimTable
              :columns="previewVariantColumns"
              :rows="previewVariantRows"
              emptyText="Keine Varianten"
            >
              <template #cell-status="{ value }">
                <span :class="['pim-badge', value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
                  {{ value === 'active' ? 'Aktiv' : value || '—' }}
                </span>
              </template>
            </PimTable>
          </div>
        </PimCollectionGroup>
      </template>

      <!-- Empty state -->
      <div v-else class="pim-card p-12 text-center">
        <p class="text-sm text-[var(--color-text-tertiary)]">Vorschau konnte nicht geladen werden</p>
      </div>
    </div>

    <!-- ═══ Versions Tab ═══ -->
    <ProductVersionsTab
      v-else-if="activeTab === 'versions' && product"
      :productId="product.id"
      @reverted="reloadAfterVersionChange"
    />

    <!-- ═══ Conformance Tab ═══ -->
    <div v-else-if="activeTab === 'conformance' && product" class="space-y-4">
      <ProductConformanceTab :productId="product.id" />
    </div>

    <!-- ═══ Scheduled Actions Tab ═══ -->
    <ProductScheduledActionsTab
      v-else-if="activeTab === 'scheduled-actions' && product"
      :productId="product.id"
    />

    <!-- ═══ Workflow History Tab ═══ -->
    <div v-else-if="activeTab === 'workflow-history' && product" class="space-y-4">
      <div v-if="!workflowEnabled" class="text-center py-12 text-sm text-[var(--color-text-tertiary)]">
        Kein Workflow für dieses Produkt konfiguriert.
      </div>
      <div v-else-if="!workflowHistoryLoaded" class="text-center py-12 text-sm text-[var(--color-text-tertiary)]">
        Laden...
      </div>
      <div v-else-if="workflowHistory.length === 0" class="text-center py-12 text-sm text-[var(--color-text-tertiary)]">
        Noch keine Workflow-Aktivitäten.
      </div>
      <div v-else class="relative pl-6">
        <!-- Timeline line -->
        <div class="absolute left-2.5 top-2 bottom-2 w-px bg-[var(--color-border)]" />

        <div
          v-for="entry in workflowHistory"
          :key="entry.id"
          class="relative pb-6 last:pb-0"
        >
          <!-- Timeline dot -->
          <div
            class="absolute -left-3.5 top-1 w-3 h-3 rounded-full border-2 border-[var(--color-surface)]"
            :style="{ background: entry.workflow_status?.color || '#6b7280' }"
          />

          <div class="pim-card p-3">
            <div class="flex items-center gap-2 flex-wrap">
              <!-- Status badge -->
              <span
                v-if="entry.workflow_status"
                class="pim-badge text-[10px] px-2 py-0.5 rounded-full font-medium"
                :style="{
                  background: (entry.workflow_status.color || '#6b7280') + '20',
                  color: entry.workflow_status.color || '#6b7280',
                }"
              >
                {{ entry.workflow_status.name }}
              </span>
              <span v-else class="text-xs font-medium text-[var(--color-text-primary)]">{{ entry.title }}</span>

              <!-- Task status -->
              <span
                class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                :class="{
                  'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': entry.status === 'open',
                  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': entry.status === 'in_progress',
                  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': entry.status === 'closed',
                }"
              >
                {{ entry.status === 'open' ? 'Offen' : entry.status === 'in_progress' ? 'In Bearbeitung' : 'Abgeschlossen' }}
              </span>

              <!-- Timestamp -->
              <span class="text-[10px] text-[var(--color-text-tertiary)] ml-auto">
                {{ new Date(entry.created_at).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
              </span>
            </div>

            <!-- Details row -->
            <div class="flex items-center gap-3 mt-1.5 text-[11px] text-[var(--color-text-secondary)]">
              <span v-if="entry.created_by">
                Erstellt von: <strong>{{ entry.created_by.name }}</strong>
              </span>
              <span v-if="entry.assignee">
                Zugewiesen: <strong>{{ entry.assignee.name }}</strong>
              </span>
              <span v-if="entry.team">
                Team: <strong>{{ entry.team.name }}</strong>
              </span>
              <span v-if="entry.closed_at" class="text-[var(--color-text-tertiary)]">
                Geschlossen: {{ new Date(entry.closed_at).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
              </span>
            </div>

            <!-- Note -->
            <p v-if="entry.note" class="mt-1.5 text-xs text-[var(--color-text-secondary)] italic">
              {{ entry.note }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- PDF Template Picker Modal -->
    <PdfTemplatePickerModal
      v-model:open="showPdfTemplatePicker"
      :productIds="product ? [product.id] : []"
    />

    <!-- Master-Hierarchie-Knoten Picker -->
    <MasterHierarchyNodePickerDialog
      v-model:open="showMasterNodePicker"
      :currentNodeId="product?.master_hierarchy_node_id"
      :currentHierarchyId="product?.master_hierarchy_node?.hierarchy?.id"
      @select="onMasterNodeSelected"
    />

    <!-- Mandatory fields warning dialog -->
    <PimConfirmDialog
      :open="showMandatoryConfirm"
      title="Pflichtfelder nicht ausgefüllt"
      :message="`${mandatoryWarnings.size} Pflichtfeld(er) sind leer. Trotzdem speichern?`"
      confirm-label="Speichern"
      @confirm="confirmSaveDespiteWarnings"
      @cancel="showMandatoryConfirm = false"
    />
  </div>
</template>
