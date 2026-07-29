<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Plus, Search, Trash2, ArrowRightLeft, FolderTree, X, ListFilter, Package } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import BulkAssignHierarchyNodeDialog from '@/components/dialogs/BulkAssignHierarchyNodeDialog.vue'
import MoveProductsToNodeDialog from '@/components/dialogs/MoveProductsToNodeDialog.vue'
import productsApi from '@/api/products'
import hierarchiesApi from '@/api/hierarchies'

const { t } = useI18n()

const props = defineProps({
  nodeId: { type: String, required: true },
  isMasterHierarchy: { type: Boolean, default: false },
  hierarchyId: { type: String, default: null },
  hasEditPermission: { type: Boolean, default: false },
})

const emit = defineEmits(['feedback', 'loaded'])
const router = useRouter()

// ─── Daten ──────────────────────────────────────────
const nodeProducts = ref([])
const outputProductAssignments = ref([])
const nodeProductsLoading = ref(false)

// Normalisierte Rows für PimTable
const tableRows = computed(() => {
  if (props.isMasterHierarchy) {
    return nodeProducts.value
  }
  return outputProductAssignments.value.map(a => ({
    ...(a.product || {}),
    _assignmentId: a.id,
    _sortOrder: a.sort_order,
  }))
})

async function loadNodeProducts(nodeId) {
  if (!nodeId) return
  nodeProductsLoading.value = true
  try {
    if (props.isMasterHierarchy) {
      const { data } = await productsApi.list({
        filters: { master_hierarchy_node_id: nodeId },
        perPage: 500,
      })
      nodeProducts.value = data.data || data
    } else {
      const { data } = await hierarchiesApi.getOutputProducts(nodeId, { perPage: 500 })
      outputProductAssignments.value = data.data || data
    }
    emit('loaded', { count: tableRows.value.length })
  } catch (e) {
    console.error('Failed to load products:', e)
  } finally {
    nodeProductsLoading.value = false
  }
}

watch(() => props.nodeId, (id) => {
  selectedProductIds.value = []
  if (pimTableRef.value?.clearSelection) pimTableRef.value.clearSelection()
  loadNodeProducts(id)
})

// ─── PimTable ───────────────────────────────────────
const pimTableRef = ref(null)

const columns = [
  { key: 'sku', label: t('SKU'), sortable: true, mono: true },
  { key: 'name', label: t('Name'), sortable: true },
  { key: 'status', label: t('Status'), sortable: true },
]

// ─── Quick Lookup ───────────────────────────────────
const showQuickLookup = ref(false)
const quickLookupFilters = ref({})

const quickLookupConfig = computed(() => ({
  sku: { type: 'text', placeholder: t('SKU...') },
  name: { type: 'text', placeholder: t('Name...') },
  status: {
    type: 'select',
    options: [
      { value: 'active', label: t('Aktiv') },
      { value: 'draft', label: t('Entwurf') },
      { value: 'inactive', label: t('Inaktiv') },
      { value: 'discontinued', label: t('Ausgelaufen') },
    ],
  },
}))

const filteredProducts = computed(() => {
  const filters = quickLookupFilters.value
  const active = Object.entries(filters).filter(([, v]) => v !== '' && v != null)
  if (active.length === 0) return tableRows.value
  return tableRows.value.filter(row => {
    return active.every(([key, val]) => {
      const cell = row[key]
      if (cell == null) return false
      const cfg = quickLookupConfig.value[key]
      if (cfg?.type === 'select') return String(cell) === String(val)
      return String(cell).toLowerCase().includes(String(val).toLowerCase())
    })
  })
})

function onQuickLookupChange(values) {
  quickLookupFilters.value = values
}

// ─── Selection ──────────────────────────────────────
const selectedProductIds = ref([])

function onProductsSelected(ids) {
  selectedProductIds.value = ids
}

// ─── Produkt-Suche & Zuordnung ─────────────────────
const showProductSearch = ref(false)
const productSearchQuery = ref('')
const productSearchResults = ref([])
let productSearchTimer = null

function searchProducts() {
  clearTimeout(productSearchTimer)
  productSearchTimer = setTimeout(async () => {
    if (!productSearchQuery.value.trim()) {
      productSearchResults.value = []
      return
    }
    try {
      const { data } = await productsApi.list({
        search: productSearchQuery.value,
        perPage: 10,
      })
      const existingIds = new Set(tableRows.value.map(r => r.id))
      productSearchResults.value = (data.data || data).filter(p => !existingIds.has(p.id))
    } catch (e) {
      console.error('Product search failed:', e)
    }
  }, 300)
}

onUnmounted(() => clearTimeout(productSearchTimer))

async function assignProductToNode(product) {
  try {
    if (props.isMasterHierarchy) {
      await hierarchiesApi.assignMasterProduct(props.nodeId, { product_id: product.id })
    } else {
      await hierarchiesApi.assignOutputProduct(props.nodeId, { product_id: product.id })
    }
    showProductSearch.value = false
    productSearchQuery.value = ''
    productSearchResults.value = []
    await loadNodeProducts(props.nodeId)
    emit('feedback', t('Produkt zugeordnet'))
  } catch (e) {
    emit('feedback', e.response?.data?.message || t('Fehler beim Zuordnen'), 'error')
  }
}

// ─── Delete (nur Output) ────────────────────────────
const deleteTarget = ref(null)
const deleting = ref(false)

async function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await hierarchiesApi.removeOutputProductAssignment(deleteTarget.value._assignmentId)
    deleteTarget.value = null
    await loadNodeProducts(props.nodeId)
    emit('feedback', t('Zuordnung entfernt'))
  } catch (e) {
    emit('feedback', e.response?.data?.message || t('Fehler beim Entfernen'), 'error')
  } finally {
    deleting.value = false
  }
}

// ─── Verschieben ────────────────────────────────────
const showMoveDialog = ref(false)

// Assignment-IDs für die selektierten Produkte (Output-Modus)
const selectedAssignmentIds = computed(() => {
  if (props.isMasterHierarchy) return []
  const idSet = new Set(selectedProductIds.value)
  return outputProductAssignments.value
    .filter(a => a.product && idSet.has(a.product.id))
    .map(a => a.id)
})

async function onProductsMoved() {
  showMoveDialog.value = false
  selectedProductIds.value = []
  if (pimTableRef.value?.clearSelection) pimTableRef.value.clearSelection()
  await loadNodeProducts(props.nodeId)
  emit('feedback', t('Produkte verschoben'))
}

// ─── Output Hierarchie zuordnen ─────────────────────
const showAssignOutputHierarchy = ref(false)

async function onBulkAssigned() {
  showAssignOutputHierarchy.value = false
  selectedProductIds.value = []
  if (pimTableRef.value?.clearSelection) pimTableRef.value.clearSelection()
  emit('feedback', t('Produkte zugeordnet'))
}

// Initialer Ladeaufruf — erst hier, NACH allen const-Deklarationen (TDZ vermeiden)
onMounted(() => loadNodeProducts(props.nodeId))
</script>

<template>
  <div class="border-t border-[var(--color-border)] pt-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3">
      <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">
        <Package class="w-3.5 h-3.5 inline-block mr-1 -mt-0.5" :stroke-width="1.75" />
        Zugeordnete Produkte
        <span v-if="tableRows.length > 0" class="text-[11px] text-[var(--color-text-tertiary)] ml-1">({{ tableRows.length }})</span>
      </h4>
      <div class="flex items-center gap-1.5">
        <button
          :class="['pim-btn pim-btn-ghost p-1', showQuickLookup ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)]' : '']"
          @click="showQuickLookup = !showQuickLookup"
          title="Quick Lookup"
        >
          <ListFilter class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
        <button v-if="hasEditPermission" class="pim-btn pim-btn-secondary text-xs" @click="showProductSearch = !showProductSearch">
          <Plus class="w-3 h-3" :stroke-width="2" /> Produkt zuordnen
        </button>
      </div>
    </div>

    <!-- Product search -->
    <div v-if="showProductSearch" class="mb-3 p-3 bg-[var(--color-bg)] rounded-lg space-y-2">
      <div class="relative">
        <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <input
          v-model="productSearchQuery"
          class="pim-input text-xs w-full pim-input-icon"
          placeholder="Produkt suchen (SKU, Name)..."
          @input="searchProducts"
          @keyup.escape="showProductSearch = false"
        />
      </div>
      <div v-if="productSearchResults.length > 0" class="max-h-48 overflow-y-auto space-y-1">
        <div
          v-for="prod in productSearchResults"
          :key="prod.id"
          class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-[var(--color-surface)] cursor-pointer"
          @click="assignProductToNode(prod)"
        >
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ prod.sku }}</span>
            <span class="text-xs">{{ prod.name || '—' }}</span>
          </div>
          <Plus class="w-3.5 h-3.5 text-[var(--color-primary)]" :stroke-width="2" />
        </div>
      </div>
      <p v-else-if="productSearchQuery.length > 0 && productSearchResults.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Produkte gefunden</p>
    </div>

    <!-- Bulk action toolbar -->
    <div v-if="selectedProductIds.length > 0" class="flex flex-wrap items-center gap-2 mb-2 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ selectedProductIds.length }} ausgewählt</span>
      <button v-if="hasEditPermission" class="pim-btn pim-btn-secondary text-xs" @click="showMoveDialog = true">
        <ArrowRightLeft class="w-3.5 h-3.5" :stroke-width="1.75" />
        Verschieben
      </button>
      <button v-if="hasEditPermission" class="pim-btn pim-btn-secondary text-xs" @click="showAssignOutputHierarchy = true">
        <FolderTree class="w-3.5 h-3.5" :stroke-width="1.75" />
        Output Hierarchie zuordnen
      </button>
      <button class="pim-btn pim-btn-ghost text-xs" @click="selectedProductIds = []; pimTableRef?.clearSelection()">
        <X class="w-3.5 h-3.5" :stroke-width="1.75" />
        Aufheben
      </button>
    </div>

    <!-- Product table -->
    <PimTable
      ref="pimTableRef"
      :columns="columns"
      :rows="filteredProducts"
      :loading="nodeProductsLoading"
      selectable
      :showActions="hasEditPermission && !isMasterHierarchy"
      :quickLookup="showQuickLookup"
      :quickLookupConfig="quickLookupConfig"
      :empty-text="t('Keine Produkte zugeordnet')"
      @select="onProductsSelected"
      @row-click="(row) => router.push(`/products/${row.id}`)"
      @quick-lookup-change="onQuickLookupChange"
    >
      <template #cell-sku="{ value }">
        <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ value }}</span>
      </template>
      <template #cell-status="{ value }">
        <span :class="['pim-badge text-[10px]', value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
          {{ value || '—' }}
        </span>
      </template>
      <template #actions="{ row }">
        <button
          class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-all"
          @click.stop="deleteTarget = row"
          title="Zuordnung entfernen"
        >
          <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </template>
    </PimTable>

    <!-- Delete confirmation (Output only) -->
    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Zuordnung entfernen?"
      :message="t('Produkt \'{sku}\' ({name}) wird aus diesem Knoten entfernt.', { sku: deleteTarget?.sku || '', name: deleteTarget?.name || '' })"
      :loading="deleting"
      entityType="output-hierarchy-product-assignments"
      :entityId="deleteTarget?._assignmentId"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />

    <!-- Move dialog -->
    <MoveProductsToNodeDialog
      v-model:open="showMoveDialog"
      :productIds="selectedProductIds"
      :hierarchyId="hierarchyId"
      :isMasterHierarchy="isMasterHierarchy"
      :sourceNodeId="nodeId"
      :sourceAssignmentIds="selectedAssignmentIds"
      @moved="onProductsMoved"
    />

    <!-- Output hierarchy assign dialog -->
    <BulkAssignHierarchyNodeDialog
      v-model:open="showAssignOutputHierarchy"
      :productIds="selectedProductIds"
      @assigned="onBulkAssigned"
    />
  </div>
</template>
