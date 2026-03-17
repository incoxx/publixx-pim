<script setup>
import { ref, onMounted, onBeforeUnmount, markRaw, computed } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useFilters } from '@/composables/useFilters'
import { Plus, Filter, X, Pencil, ListFilter, Copy, Trash2, MoreHorizontal, CheckCheck, Search, ChevronDown, ChevronRight } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import AttributeFormPanel from '@/components/panels/AttributeFormPanel.vue'
import AttributePagination from '@/components/attributes/AttributePagination.vue'
import AttributeBulkUpdateDialog from '@/components/attributes/AttributeBulkUpdateDialog.vue'
import attributesApi from '@/api/attributes'
import hierarchiesApi from '@/api/hierarchies'

const { t } = useI18n()
const store = useAttributeStore()
const authStore = useAuthStore()

// ─── Filters ─────────────────────────────────────────
const filterOpen = ref(false)
const activeFilterEntries = ref({})

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  store.setPage(1)
  loadWithFilters()
})

async function loadWithFilters() {
  const opts = { search: search.value, include: 'attributeType,valueList,unitGroup,children,attributeViews' }
  if (Object.keys(activeFilterEntries.value).length > 0) {
    opts.filters = { ...activeFilterEntries.value }
  }
  await store.fetchAttributes(opts)
}

// pendingFilterEntries holds in-progress filter selections (not yet applied)
const pendingFilterEntries = ref({})

function setFilter(key, value) {
  if (value === '' || value === null || value === undefined) {
    delete pendingFilterEntries.value[key]
  } else {
    pendingFilterEntries.value[key] = value
  }
}

function applyFilterPanel() {
  activeFilterEntries.value = { ...pendingFilterEntries.value }
  store.setPage(1)
  loadWithFilters()
}

function clearAllFilters() {
  activeFilterEntries.value = {}
  pendingFilterEntries.value = {}
  selectedHierarchyId.value = ''
  hierarchyNodes.value = []
  clearFilters()
  store.setPage(1)
  loadWithFilters()
}

const filterCount = computed(() => Object.keys(activeFilterEntries.value).length)
const pendingFilterCount = computed(() => Object.keys(pendingFilterEntries.value).length)

const boolFilterOptions = [
  { value: '', label: 'Alle' },
  { value: '1', label: 'Ja' },
  { value: '0', label: 'Nein' },
]

const dataTypeOptions = [
  { value: '', label: 'Alle' },
  { value: 'String', label: 'String' },
  { value: 'Number', label: 'Number' },
  { value: 'Float', label: 'Float' },
  { value: 'Date', label: 'Date' },
  { value: 'Flag', label: 'Flag' },
  { value: 'Selection', label: 'Selection' },
  { value: 'Dictionary', label: 'Dictionary' },
  { value: 'Composite', label: 'Composite' },
  { value: 'RichText', label: 'RichText' },
]

const statusOptions = [
  { value: 'active', label: 'Aktiv' },
  { value: 'inactive', label: 'Inaktiv' },
]

const attributeTypeOptions = computed(() => [
  { value: '', label: 'Alle Gruppen' },
  ...store.types.map(t => ({ value: t.id, label: t.name_de || t.technical_name })),
])

// ─── Quick Lookup ────────────────────────────────────
const showQuickLookup = ref(false)
const quickLookupFilters = ref({})

const quickLookupConfig = computed(() => ({
  technical_name: { type: 'text', placeholder: 'Techn. Name...' },
  name_de: { type: 'text', placeholder: 'Name...' },
  data_type: { type: 'select', options: dataTypeOptions.filter(o => o.value !== '') },
  'attribute_type.name_de': {
    type: 'select',
    options: store.types.map(t => ({ value: t.name_de || t.technical_name, label: t.name_de || t.technical_name })),
  },
}))

function getCellValueForFilter(row, colKey) {
  const keys = colKey.split('.')
  let val = row
  for (const k of keys) val = val?.[k]
  return val
}

const filteredItems = computed(() => {
  if (!showQuickLookup.value) return store.items
  const filters = quickLookupFilters.value
  const activeFiltersArr = Object.entries(filters).filter(([, v]) => v !== '' && v != null)
  if (activeFiltersArr.length === 0) return store.items

  return store.items.filter(row => {
    return activeFiltersArr.every(([colKey, filterVal]) => {
      const cellVal = getCellValueForFilter(row, colKey)
      if (cellVal == null || cellVal === '—') return false
      const config = quickLookupConfig.value[colKey]
      if (config?.type === 'select') {
        return String(cellVal) === String(filterVal)
      }
      return String(cellVal).toLowerCase().startsWith(String(filterVal).toLowerCase())
    })
  })
})

function onQuickLookupChange(values) {
  quickLookupFilters.value = values
}

// ─── Hierarchy data for filter ──────────────────────
const hierarchies = ref([])
const hierarchyNodes = ref([])
const selectedHierarchyId = ref('')
const showHierarchyPicker = ref(false)

async function fetchHierarchies() {
  try {
    const { data } = await hierarchiesApi.list()
    hierarchies.value = data.data || data
    // Auto-select first hierarchy if only one exists
    if (hierarchies.value.length === 1) {
      selectedHierarchyId.value = hierarchies.value[0].id
      fetchHierarchyNodes(hierarchies.value[0].id)
    }
  } catch (e) {
    console.error('Failed to fetch hierarchies', e)
  }
}

async function fetchHierarchyNodes(hierarchyId) {
  if (!hierarchyId) {
    hierarchyNodes.value = []
    return
  }
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    hierarchyNodes.value = flattenTree(data.data || data)
  } catch (e) {
    console.error('Failed to fetch hierarchy nodes', e)
  }
}

function flattenTree(nodes, depth = 0) {
  const result = []
  for (const node of nodes) {
    result.push({ ...node, _depth: depth })
    if (node.children?.length) {
      result.push(...flattenTree(node.children, depth + 1))
    }
  }
  return result
}

function onHierarchyChange(hierarchyId) {
  selectedHierarchyId.value = hierarchyId
  if (!hierarchyId) {
    hierarchyNodes.value = []
    delete pendingFilterEntries.value.hierarchy_node_id
  } else {
    fetchHierarchyNodes(hierarchyId)
  }
}

function onHierarchyNodeChange(nodeId) {
  setFilter('hierarchy_node_id', nodeId)
}

// ─── Selection & Bulk Update ─────────────────────────
const selectedIds = ref([])
const bulkDialogOpen = ref(false)
const pimTableRef = ref(null)

// Select-all across pages
const allPagesSelected = ref(false)
const selectingAll = ref(false)

// Bulk delete
const bulkDeleting = ref(false)
const showConfirmBulkDelete = ref(false)

async function selectAllPages() {
  selectingAll.value = true
  try {
    const params = { search: search.value }
    if (Object.keys(activeFilterEntries.value).length > 0) {
      params.filter = { ...activeFilterEntries.value }
    }
    const { data } = await attributesApi.allIds(params)
    const ids = data.ids || []
    if (pimTableRef.value?.setSelectedIds) {
      pimTableRef.value.setSelectedIds(ids)
    }
    selectedIds.value = ids
    allPagesSelected.value = true
  } catch (e) {
    console.error('Failed to select all', e)
  } finally {
    selectingAll.value = false
  }
}

function clearAllSelection() {
  if (pimTableRef.value?.clearSelection) {
    pimTableRef.value.clearSelection()
  }
  selectedIds.value = []
  allPagesSelected.value = false
}

const bulkDeleteError = ref(null)

async function bulkDeleteAttributes() {
  showConfirmBulkDelete.value = false
  bulkDeleteError.value = null
  bulkDeleting.value = true
  // Snapshot IDs before any reactive changes
  const idsToDelete = [...selectedIds.value]
  try {
    await attributesApi.bulkDelete(idsToDelete)
    selectedIds.value = []
    allPagesSelected.value = false
    if (pimTableRef.value?.clearSelection) {
      pimTableRef.value.clearSelection()
    }
    await loadWithFilters()
  } catch (e) {
    console.error('Bulk delete failed', e)
    bulkDeleteError.value = e.response?.data?.message || e.message || 'Löschen fehlgeschlagen'
  } finally {
    bulkDeleting.value = false
  }
}

function handleSelect(ids) {
  selectedIds.value = ids
  if (allPagesSelected.value) allPagesSelected.value = false
}

function openBulkUpdate() {
  bulkDialogOpen.value = true
}

function onBulkUpdated() {
  bulkDialogOpen.value = false
  selectedIds.value = []
  loadWithFilters()
}

// ─── Columns ─────────────────────────────────────────
const columns = [
  { key: 'technical_name', label: 'Techn. Name', sortable: true, mono: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'data_type', label: 'Datentyp', sortable: true },
  { key: '_children', label: 'Kind-Attribute' },
  { key: 'attribute_type.name_de', label: 'Gruppe' },
  { key: '_views', label: 'Sichten' },
  { key: 'is_translatable', label: 'Übersetzbar' },
  { key: 'is_multipliable', label: 'Multipliziert' },
  { key: 'is_searchable', label: 'Suchbar' },
  { key: 'is_mandatory', label: 'Pflicht' },
  { key: 'is_unique', label: 'Einzigartig' },
  { key: 'is_inheritable', label: 'Vererbbar' },
  { key: 'is_variant_attribute', label: 'Varianten-Attr.' },
  { key: 'is_internal', label: 'Intern' },
  { key: 'description_de', label: 'Beschreibung' },
]

const deleteTarget = ref(null)
const deleting = ref(false)
const actionMenuRow = ref(null)
const copying = ref(false)

function handleSort(field, order) {
  store.fetchAttributes({ sort: field, order, filters: activeFilterEntries.value, include: 'attributeType,valueList,unitGroup,children,attributeViews' })
}

function handlePageChange() {
  loadWithFilters()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AttributeFormPanel), { attribute: null })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('attributes.edit')) return
  authStore.openPanel(markRaw(AttributeFormPanel), { attribute: row })
}

function handleRowAction(row) {
  actionMenuRow.value = actionMenuRow.value?.id === row.id ? null : row
}

function closeActionMenu() {
  actionMenuRow.value = null
}

async function handleCopy(row) {
  actionMenuRow.value = null
  copying.value = true
  try {
    await store.copyAttribute(row.id)
    loadWithFilters()
  } finally {
    copying.value = false
  }
}

function handleDelete(row) {
  actionMenuRow.value = null
  deleteTarget.value = row
}

async function confirmDelete({ force } = {}) {
  deleting.value = true
  try {
    await store.deleteAttribute(deleteTarget.value.id, { force })
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

function onClickOutside(e) {
  if (actionMenuRow.value && !e.target.closest('.relative')) {
    actionMenuRow.value = null
  }
}

onMounted(() => {
  store.fetchAttributes({ include: 'attributeType,valueList,unitGroup,children,attributeViews' })
  store.fetchTypes()
  store.fetchValueLists()
  fetchHierarchies()
  document.addEventListener('click', onClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside)
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('attribute.title') }}</h2>
      <div class="flex items-center gap-2">
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :class="showQuickLookup ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : ''"
          @click="showQuickLookup = !showQuickLookup"
          title="Quick Lookup"
        >
          <ListFilter class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Quick Lookup</span>
        </button>
        <button
          :class="[
            'pim-btn text-xs gap-1',
            filterCount > 0 ? 'pim-btn-primary' : 'pim-btn-secondary',
          ]"
          @click="filterOpen = !filterOpen"
        >
          <Filter class="w-3.5 h-3.5" :stroke-width="1.75" />
          Filter
          <span v-if="filterCount > 0" class="ml-0.5 bg-white/20 text-white px-1.5 py-0 rounded-full text-[10px]">{{ filterCount }}</span>
        </button>
        <button v-if="authStore.hasPermission('attributes.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
          <Plus class="w-4 h-4" :stroke-width="2" />
          {{ t('attribute.newAttribute') }}
        </button>
      </div>
    </div>

    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Attribute durchsuchen…"
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearAllFilters"
    />

    <!-- Selection toolbar -->
    <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs text-[var(--color-text-secondary)]">{{ selectedIds.length }} ausgewählt</span>

      <button
        v-if="!allPagesSelected && store.meta.total > store.meta.per_page"
        class="pim-btn pim-btn-secondary text-xs"
        :disabled="selectingAll"
        @click="selectAllPages"
      >
        <CheckCheck class="w-3.5 h-3.5" :stroke-width="1.75" />
        {{ selectingAll ? 'Lade...' : `Alle ${store.meta.total} Attribute auswählen` }}
      </button>

      <button
        v-if="authStore.hasPermission('attributes.edit')"
        class="pim-btn pim-btn-primary text-xs"
        @click="openBulkUpdate"
      >
        <Pencil class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Bulk bearbeiten</span>
        <span class="sm:hidden">Bulk</span>
      </button>

      <button
        v-if="authStore.userRole === 'Admin'"
        class="pim-btn text-xs bg-[var(--color-error)] text-white hover:bg-[var(--color-error)]/90"
        :disabled="bulkDeleting"
        @click="showConfirmBulkDelete = true"
      >
        <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
        {{ bulkDeleting ? 'Lösche...' : 'Löschen' }}
      </button>

      <button class="pim-btn pim-btn-ghost text-xs" @click="clearAllSelection">
        <X class="w-3.5 h-3.5" :stroke-width="1.75" />
        Auswahl aufheben
      </button>
    </div>

    <!-- Bulk Delete Confirmation -->
    <div v-if="showConfirmBulkDelete" class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg flex flex-wrap items-center gap-3">
      <span class="text-xs text-red-700 font-medium">{{ selectedIds.length }} Attribute unwiderruflich löschen?</span>
      <button
        class="pim-btn text-xs bg-[var(--color-error)] text-white hover:bg-[var(--color-error)]/90"
        :disabled="bulkDeleting"
        @click="bulkDeleteAttributes"
      >
        {{ bulkDeleting ? 'Lösche...' : 'Ja, endgültig löschen' }}
      </button>
      <button class="pim-btn pim-btn-ghost text-xs" @click="showConfirmBulkDelete = false">Abbrechen</button>
    </div>

    <!-- Bulk deleting indicator -->
    <div v-if="bulkDeleting" class="px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-2">
      <svg class="animate-spin w-3.5 h-3.5 text-amber-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
      <span class="text-xs text-amber-700 font-medium">Attribute werden gelöscht…</span>
    </div>

    <!-- Bulk delete error -->
    <div v-if="bulkDeleteError" class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2">
      <span class="text-xs text-red-700 font-medium">Fehler beim Löschen: {{ bulkDeleteError }}</span>
      <button class="pim-btn pim-btn-ghost text-xs" @click="bulkDeleteError = null">
        <X class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>

    <!-- Filter Panel -->
    <div v-if="filterOpen" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Filter class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          Attribute filtern
        </h3>
        <div class="flex items-center gap-2">
          <button v-if="filterCount > 0" class="text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] underline" @click="clearAllFilters">
            Alle zurücksetzen
          </button>
          <button class="pim-btn pim-btn-ghost text-xs p-1" @click="filterOpen = false">
            <X class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Datentyp</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.data_type || ''" @change="setFilter('data_type', $event.target.value)">
            <option v-for="o in dataTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attributgruppe</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.attribute_type_id || ''" @change="setFilter('attribute_type_id', $event.target.value)">
            <option v-for="o in attributeTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Übersetzbar</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.is_translatable ?? ''" @change="setFilter('is_translatable', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Pflichtfeld</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.is_mandatory ?? ''" @change="setFilter('is_mandatory', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Suchbar</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.is_searchable ?? ''" @change="setFilter('is_searchable', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Vererbbar</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.is_inheritable ?? ''" @change="setFilter('is_inheritable', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Varianten-Attribut</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.is_variant_attribute ?? ''" @change="setFilter('is_variant_attribute', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Intern</label>
          <select class="pim-input text-xs" :value="pendingFilterEntries.is_internal ?? ''" @change="setFilter('is_internal', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
      </div>

      <!-- Hierarchy usage filter -->
      <div class="border-t border-[var(--color-border)] pt-3 mt-1">
        <button
          class="flex items-center gap-2 text-[12px] font-medium text-[var(--color-text-secondary)] mb-2 cursor-pointer"
          @click="showHierarchyPicker = !showHierarchyPicker"
        >
          <component :is="showHierarchyPicker ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" />
          Verwendung in Hierarchie (inkl. Unterknoten)
          <span v-if="pendingFilterEntries.hierarchy_node_id" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5">1</span>
        </button>
        <div v-if="showHierarchyPicker" class="space-y-2">
          <div v-if="hierarchies.length > 1" class="flex items-center gap-2">
            <label class="text-[11px] font-medium text-[var(--color-text-tertiary)]">Hierarchie:</label>
            <select
              class="pim-input text-xs flex-1"
              :value="selectedHierarchyId"
              @change="onHierarchyChange($event.target.value)"
            >
              <option value="">— Alle —</option>
              <option v-for="h in hierarchies" :key="h.id" :value="h.id">{{ h.name_de || h.name_en || h.technical_name }}</option>
            </select>
          </div>
          <div v-if="hierarchyNodes.length > 0" class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
            <label
              v-for="n in hierarchyNodes"
              :key="n.id"
              class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
              :style="{ paddingLeft: (n._depth * 16 + 8) + 'px' }"
            >
              <input
                type="radio"
                name="hierarchy_node_filter"
                :checked="pendingFilterEntries.hierarchy_node_id === n.id"
                @change="onHierarchyNodeChange(n.id)"
                class="rounded border-[var(--color-border)]"
              />
              <span class="text-[var(--color-text-primary)]">{{ n.name_de || n.name_en || n.id }}</span>
            </label>
            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs text-[var(--color-text-tertiary)]">
              <input
                type="radio"
                name="hierarchy_node_filter"
                :checked="!pendingFilterEntries.hierarchy_node_id"
                @change="onHierarchyNodeChange('')"
                class="rounded border-[var(--color-border)]"
              />
              Alle Knoten
            </label>
          </div>
          <p v-else-if="selectedHierarchyId" class="text-xs text-[var(--color-text-tertiary)] py-2">Keine Knoten vorhanden</p>
          <p v-else class="text-xs text-[var(--color-text-tertiary)] py-2">Hierarchie auswählen, um Knoten zu filtern</p>
        </div>
      </div>

      <!-- Search button -->
      <div class="flex items-center justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
        <button v-if="pendingFilterCount > 0 || filterCount > 0" class="pim-btn pim-btn-ghost text-xs" @click="clearAllFilters">
          Alle zurücksetzen
        </button>
        <button class="pim-btn pim-btn-primary py-2 px-5" @click="applyFilterPanel">
          <Search class="w-3.5 h-3.5" :stroke-width="1.75" />
          Suchen
        </button>
      </div>
    </div>

    <PimTable
      ref="pimTableRef"
      :columns="columns"
      :rows="filteredItems"
      :loading="store.loading"
      selectable
      :showActions="authStore.hasPermission('attributes.create') || authStore.hasPermission('attributes.delete')"
      emptyText="Keine Attribute gefunden"
      :quickLookup="showQuickLookup"
      :quickLookupConfig="quickLookupConfig"
      @sort="handleSort"
      @row-click="openEditPanel"
      @select="handleSelect"
      @quick-lookup-change="onQuickLookupChange"
    >
      <template #cell-data_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value }}</span>
      </template>
      <template #cell-_children="{ row }">
        <div v-if="row.data_type === 'Composite' && row.children?.length" class="flex flex-wrap gap-0.5">
          <span v-for="c in row.children" :key="c.id" class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px]">{{ c.name_de || c.technical_name }}</span>
        </div>
        <span v-else-if="row.parent_attribute_id" class="text-[10px] text-[var(--color-text-tertiary)]">→ Kind von Composite</span>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>
      <template #cell-_views="{ row }">
        <div class="flex flex-wrap gap-0.5">
          <span v-for="v in (row.attribute_views || [])" :key="v.id" class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px]">{{ v.name_de || v.technical_name }}</span>
          <span v-if="!row.attribute_views?.length" class="text-[var(--color-text-tertiary)]">—</span>
        </div>
      </template>
      <template #cell-is_translatable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_multipliable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_searchable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_mandatory="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_unique="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_inheritable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_variant_attribute="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_internal="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-description_de="{ value }">
        <span class="text-[var(--color-text-tertiary)] text-xs truncate max-w-[200px] block">{{ value || '—' }}</span>
      </template>

      <template #actions="{ row }">
        <div class="relative">
          <button
            class="p-1 rounded hover:bg-[var(--color-border)] transition-all opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
            @click="handleRowAction(row)"
          >
            <MoreHorizontal class="w-4 h-4 text-[var(--color-text-tertiary)]" />
          </button>
          <div
            v-if="actionMenuRow?.id === row.id"
            class="absolute right-0 top-full mt-1 z-50 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 min-w-[160px]"
          >
            <button
              v-if="authStore.hasPermission('attributes.create')"
              class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-[var(--color-text-primary)] hover:bg-[var(--color-bg)] transition-colors"
              :disabled="copying"
              @click="handleCopy(row)"
            >
              <Copy class="w-3.5 h-3.5" :stroke-width="1.75" />
              Kopieren
            </button>
            <button
              v-if="authStore.hasPermission('attributes.delete')"
              class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-red-600 hover:bg-[var(--color-bg)] transition-colors"
              @click="handleDelete(row)"
            >
              <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
              Löschen
            </button>
          </div>
        </div>
      </template>

      <template #pagination>
        <AttributePagination @page-change="handlePageChange" />
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Attribut löschen?"
      :message="`Das Attribut '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      entityType="attributes"
      :entityId="deleteTarget?.id"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <AttributeBulkUpdateDialog
      :open="bulkDialogOpen"
      :selectedIds="selectedIds"
      @close="bulkDialogOpen = false"
      @updated="onBulkUpdated"
    />
  </div>
</template>
