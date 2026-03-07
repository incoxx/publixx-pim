<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import {
  ArrowLeft, Plus, Trash2, Search, Check, AlertTriangle,
  X, Package, Settings, Link2, FolderTree, Image,
} from 'lucide-vue-next'
import bulkUpdateApi from '@/api/bulkUpdate'
import attributesApi from '@/api/attributes'
import productsApi from '@/api/products'
import mediaApi from '@/api/media'
import hierarchiesApi from '@/api/hierarchies'
import { relationTypes } from '@/api/prices'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import PimTree from '@/components/shared/PimTree.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'

const route = useRoute()
const router = useRouter()

// ── Product IDs from query ─────────────────────────
const productIds = computed(() => {
  const ids = route.query.ids
  if (!ids) return []
  return ids.split(',').filter(Boolean)
})

// ── Tabs ────────────────────────────────────────────
const tabs = [
  { key: 'attributes', label: 'Attribute', icon: Settings },
  { key: 'relations', label: 'Beziehungen', icon: Link2 },
  { key: 'output_hierarchy', label: 'Ausgabehierarchie', icon: FolderTree },
  { key: 'status', label: 'Status', icon: Package },
  { key: 'master_hierarchy', label: 'Master-Hierarchie', icon: FolderTree },
  { key: 'media', label: 'Medien', icon: Image },
]
const activeTab = ref('attributes')

// ── Feedback ────────────────────────────────────────
const actionFeedback = ref(null)
let feedbackTimer = null
function showFeedback(msg, type = 'success') {
  actionFeedback.value = { msg, type }
  clearTimeout(feedbackTimer)
  feedbackTimer = setTimeout(() => { actionFeedback.value = null }, 4000)
}

// ── Tab 1: Attributes ───────────────────────────────
const attrOperations = ref([]) // [{attribute, value, mode, language}]
const attrPickerOpen = ref(false)
const attrPickerSearch = ref('')
const attrPickerItems = ref([])
const attrPickerLoading = ref(false)
const attrPickerMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const attrPickerWarning = ref(null)

async function fetchAttrPickerItems(page = 1) {
  attrPickerLoading.value = true
  attrPickerWarning.value = null
  try {
    const usedIds = new Set(attrOperations.value.map(o => o.attribute.id))

    if (productIds.value.length > 0) {
      // Use hierarchy-aware common attributes
      const { data } = await bulkUpdateApi.commonAttributes({
        productIds: productIds.value,
        search: attrPickerSearch.value || undefined,
        excludeIds: usedIds.size > 0 ? [...usedIds] : undefined,
      })
      const items = data.data || data
      attrPickerItems.value = items
      attrPickerMeta.value = { current_page: 1, last_page: 1, total: items.length }
      if (data.warning) {
        attrPickerWarning.value = data.warning
      }
    } else {
      // Fallback: no product IDs, load all attributes
      const { data } = await attributesApi.list({
        search: attrPickerSearch.value || undefined,
        page,
        perPage: 20,
        sort: 'name_de',
        order: 'asc',
      })
      const items = data.data || data
      attrPickerItems.value = items.filter(a => !usedIds.has(a.id))
      attrPickerMeta.value = data.meta || { current_page: page, last_page: 1, total: items.length }
    }
  } catch {
    attrPickerItems.value = []
  } finally {
    attrPickerLoading.value = false
  }
}

const debouncedAttrSearch = useDebounceFn(() => fetchAttrPickerItems(1), 300)

function openAttrPicker() {
  attrPickerOpen.value = true
  attrPickerSearch.value = ''
  fetchAttrPickerItems(1)
}

function addAttribute(attr) {
  attrOperations.value.push({
    attribute: attr,
    value: null,
    mode: 'overwrite',
    language: attr.is_translatable ? 'de' : null,
  })
  // Remove from picker list
  attrPickerItems.value = attrPickerItems.value.filter(a => a.id !== attr.id)
}

function removeAttrOp(index) {
  attrOperations.value.splice(index, 1)
}

function attrInputType(dataType) {
  return {
    String: 'text', Number: 'number', Float: 'decimal', Date: 'date',
    Flag: 'boolean', Selection: 'select', Dictionary: 'dictionary', RichText: 'richtext',
  }[dataType] || 'text'
}

function attrInputOptions(attr) {
  if (!attr.value_list?.entries) return []
  return attr.value_list.entries.map(e => ({
    value: e.id,
    label: e.display_value_de || e.code,
  }))
}

// ── Tab 2: Relations ────────────────────────────────
const relationOps = ref([]) // [{relationType, targetProduct, action, search, results, loading}]
const relationTypesList = ref([])

async function loadRelationTypes() {
  try {
    const { data } = await relationTypes.list()
    relationTypesList.value = data.data || data
  } catch { relationTypesList.value = [] }
}

function searchRelProduct(index) {
  const op = relationOps.value[index]
  if (!op.search?.trim()) { op.results = []; return }
  op.loading = true
  productsApi.list({ search: op.search, perPage: 10 })
    .then(({ data }) => { op.results = data.data || data })
    .catch(() => { op.results = [] })
    .finally(() => { op.loading = false })
}

const debouncedRelProductSearch = useDebounceFn(searchRelProduct, 300)

function addRelationOp() {
  relationOps.value.push({
    relationType: relationTypesList.value[0] || null,
    targetProduct: null,
    action: 'add',
    search: '',
    results: [],
    loading: false,
  })
}

function selectRelProduct(product, index) {
  relationOps.value[index].targetProduct = product
  relationOps.value[index].search = ''
  relationOps.value[index].results = []
}

function removeRelOp(index) {
  relationOps.value.splice(index, 1)
}

// ── Tab 3: Output Hierarchy ─────────────────────────
const hierarchyOps = ref([]) // [{hierarchyId, node, action}]
const hierarchiesList = ref([])
const hierarchyTrees = ref({}) // hierarchyId -> tree nodes
const hierarchyTreeLoading = ref(false)

let allHierarchiesCache = null

async function loadAllHierarchies() {
  if (allHierarchiesCache) return allHierarchiesCache
  const { data } = await hierarchiesApi.list()
  allHierarchiesCache = data.data || data
  return allHierarchiesCache
}

async function loadHierarchies() {
  try {
    const all = await loadAllHierarchies()
    hierarchiesList.value = all.filter(h => h.hierarchy_type !== 'master')
  } catch { hierarchiesList.value = [] }
}

async function loadHierarchyTree(hierarchyId) {
  if (hierarchyTrees.value[hierarchyId]) return
  hierarchyTreeLoading.value = true
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    hierarchyTrees.value[hierarchyId] = data.data || data
  } catch { hierarchyTrees.value[hierarchyId] = [] }
  finally { hierarchyTreeLoading.value = false }
}

function addHierarchyOp() {
  const h = hierarchiesList.value[0]
  if (!h) return
  hierarchyOps.value.push({ hierarchyId: h.id, node: null, action: 'assign' })
  loadHierarchyTree(h.id)
}

function onHierarchyChange(index, hierarchyId) {
  hierarchyOps.value[index].hierarchyId = hierarchyId
  hierarchyOps.value[index].node = null
  loadHierarchyTree(hierarchyId)
}

function selectHierarchyNode(index, node) {
  hierarchyOps.value[index].node = node
}

function removeHierarchyOp(index) {
  hierarchyOps.value.splice(index, 1)
}

// ── Tab 4: Status ───────────────────────────────────
const statusOperation = ref(null)
const statusOptions = [
  { value: 'draft', label: 'Entwurf' },
  { value: 'active', label: 'Aktiv' },
  { value: 'inactive', label: 'Inaktiv' },
  { value: 'discontinued', label: 'Abgekündigt' },
]

// ── Tab 5: Master Hierarchy ─────────────────────────
const masterHierarchyNodeId = ref(null)
const masterHierarchyChanged = ref(false) // true when user actively configured this tab
const masterHierarchy = ref(null) // the master hierarchy object
const masterTree = ref([])
const masterTreeLoading = ref(false)
const masterSelectedNode = ref(null)
const masterExpandedNodes = ref(new Set())

async function loadMasterHierarchy() {
  try {
    const all = await loadAllHierarchies()
    masterHierarchy.value = all.find(h => h.hierarchy_type === 'master') || null
    if (masterHierarchy.value) {
      masterTreeLoading.value = true
      const { data: treeData } = await hierarchiesApi.getTree(masterHierarchy.value.id)
      masterTree.value = treeData.data || treeData
      masterTreeLoading.value = false
    }
  } catch {
    masterHierarchy.value = null
    masterTree.value = []
    masterTreeLoading.value = false
  }
}

function selectMasterNode(node) {
  masterSelectedNode.value = node
  masterHierarchyNodeId.value = node?.id || null
  masterHierarchyChanged.value = true
}

function clearMasterHierarchy() {
  masterSelectedNode.value = null
  masterHierarchyNodeId.value = null
  masterHierarchyChanged.value = true
}

function toggleMasterExpanded(nodeId) {
  if (masterExpandedNodes.value.has(nodeId)) {
    masterExpandedNodes.value.delete(nodeId)
  } else {
    masterExpandedNodes.value.add(nodeId)
  }
  masterExpandedNodes.value = new Set(masterExpandedNodes.value)
}

// ── Tab 6: Media ────────────────────────────────────
const mediaOps = ref([]) // [{media, usageType, action, search, results, loading}]
const usageTypesList = ref([])

async function loadUsageTypes() {
  try {
    const { data } = await mediaUsageTypes.list()
    usageTypesList.value = data.data || data
  } catch { usageTypesList.value = [] }
}

function searchMediaForRow(index) {
  const op = mediaOps.value[index]
  if (!op.search?.trim()) { op.results = []; return }
  op.loading = true
  mediaApi.list({ search: op.search, perPage: 10 })
    .then(({ data }) => { op.results = data.data || data })
    .catch(() => { op.results = [] })
    .finally(() => { op.loading = false })
}

const debouncedMediaSearch = useDebounceFn(searchMediaForRow, 300)

function addMediaOp() {
  mediaOps.value.push({
    media: null,
    usageType: usageTypesList.value[0] || null,
    action: 'assign',
    search: '',
    results: [],
    loading: false,
  })
}

function selectMedia(medium, index) {
  mediaOps.value[index].media = medium
  mediaOps.value[index].search = ''
  mediaOps.value[index].results = []
}

function removeMediaOp(index) {
  mediaOps.value.splice(index, 1)
}

// ── Operations Summary ──────────────────────────────
const operationCount = computed(() => {
  let count = 0
  if (attrOperations.value.length > 0) count += attrOperations.value.length
  if (relationOps.value.filter(r => r.targetProduct).length > 0) count += relationOps.value.filter(r => r.targetProduct).length
  if (hierarchyOps.value.filter(h => h.node).length > 0) count += hierarchyOps.value.filter(h => h.node).length
  if (statusOperation.value) count++
  if (masterHierarchyChanged.value) count++
  if (mediaOps.value.filter(m => m.media).length > 0) count += mediaOps.value.filter(m => m.media).length
  return count
})

function buildOperations() {
  const ops = {}

  if (attrOperations.value.length > 0) {
    ops.attributes = attrOperations.value.map(o => ({
      attribute_id: o.attribute.id,
      value: o.mode === 'clear' ? null : o.value,
      language: o.language,
      mode: o.mode,
    }))
  }

  const validRelations = relationOps.value.filter(r => r.targetProduct && r.relationType)
  if (validRelations.length > 0) {
    ops.relations = validRelations.map(r => ({
      relation_type_id: r.relationType.id,
      target_product_id: r.targetProduct.id,
      action: r.action,
    }))
  }

  const validHierarchy = hierarchyOps.value.filter(h => h.node)
  if (validHierarchy.length > 0) {
    ops.output_hierarchy = validHierarchy.map(h => ({
      hierarchy_node_id: h.node.id,
      action: h.action,
    }))
  }

  if (statusOperation.value) {
    ops.status = statusOperation.value
  }

  if (masterHierarchyChanged.value) {
    ops.master_hierarchy_node_id = masterHierarchyNodeId.value
  }

  const validMedia = mediaOps.value.filter(m => m.media)
  if (validMedia.length > 0) {
    ops.media = validMedia.map(m => ({
      media_id: m.media.id,
      usage_type_id: m.usageType?.id || null,
      action: m.action,
    }))
  }

  return ops
}

// ── Preview & Execute ───────────────────────────────
const previewResult = ref(null)
const showPreview = ref(false)
const previewing = ref(false)
const executing = ref(false)
const executeResult = ref(null)
const showConfirm = ref(false)

async function runPreview() {
  if (operationCount.value === 0) {
    showFeedback('Keine Operationen konfiguriert', 'error')
    return
  }
  previewing.value = true
  previewResult.value = null
  try {
    const { data } = await bulkUpdateApi.preview({
      productIds: productIds.value,
      operations: buildOperations(),
    })
    previewResult.value = data.summary
    showPreview.value = true
  } catch (e) {
    showFeedback(e.response?.data?.message || 'Fehler bei der Vorschau', 'error')
  } finally {
    previewing.value = false
  }
}

function requestExecute() {
  showConfirm.value = true
}

async function confirmExecute() {
  showConfirm.value = false
  executing.value = true
  try {
    const { data } = await bulkUpdateApi.execute({
      productIds: productIds.value,
      operations: buildOperations(),
    })
    executeResult.value = data.results
    showPreview.value = false
    showFeedback(`Massenaktualisierung abgeschlossen`)
  } catch (e) {
    showFeedback(e.response?.data?.message || 'Fehler bei der Ausführung', 'error')
  } finally {
    executing.value = false
  }
}

function goBack() {
  router.push('/products')
}

// ── Init ────────────────────────────────────────────
onMounted(() => {
  if (productIds.value.length === 0) {
    router.push('/products')
    return
  }
  loadRelationTypes()
  loadHierarchies()
  loadMasterHierarchy()
  loadUsageTypes()
})
</script>

<template>
  <div class="max-w-5xl mx-auto space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button class="pim-btn pim-btn-ghost p-1.5" @click="goBack" title="Zurück">
          <ArrowLeft class="w-4 h-4" :stroke-width="1.75" />
        </button>
        <div>
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Massendatenpflege</h2>
          <p class="text-xs text-[var(--color-text-tertiary)]">{{ productIds.length }} Produkte ausgewählt</p>
        </div>
      </div>
    </div>

    <!-- Execute Result -->
    <div v-if="executeResult" class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-2 text-[var(--color-success)]">
        <Check class="w-5 h-5" :stroke-width="2" />
        <h3 class="text-base font-semibold">Massenaktualisierung abgeschlossen</h3>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
        <div v-if="executeResult.attributes" class="p-3 bg-[var(--color-bg)] rounded-lg">
          <p class="text-xs text-[var(--color-text-tertiary)]">Attribute</p>
          <p class="font-medium">{{ executeResult.attributes.updated }} aktualisiert, {{ executeResult.attributes.skipped }} übersprungen</p>
        </div>
        <div v-if="executeResult.relations" class="p-3 bg-[var(--color-bg)] rounded-lg">
          <p class="text-xs text-[var(--color-text-tertiary)]">Beziehungen</p>
          <p class="font-medium">{{ executeResult.relations.added }} hinzugefügt, {{ executeResult.relations.removed }} entfernt</p>
        </div>
        <div v-if="executeResult.output_hierarchy" class="p-3 bg-[var(--color-bg)] rounded-lg">
          <p class="text-xs text-[var(--color-text-tertiary)]">Ausgabehierarchie</p>
          <p class="font-medium">{{ executeResult.output_hierarchy.assigned }} zugeordnet, {{ executeResult.output_hierarchy.removed }} entfernt</p>
        </div>
        <div v-if="executeResult.status" class="p-3 bg-[var(--color-bg)] rounded-lg">
          <p class="text-xs text-[var(--color-text-tertiary)]">Status</p>
          <p class="font-medium">{{ executeResult.status.would_change }} geändert</p>
        </div>
        <div v-if="executeResult.master_hierarchy" class="p-3 bg-[var(--color-bg)] rounded-lg">
          <p class="text-xs text-[var(--color-text-tertiary)]">Master-Hierarchie</p>
          <p class="font-medium">{{ executeResult.master_hierarchy.would_change }} geändert</p>
        </div>
        <div v-if="executeResult.media" class="p-3 bg-[var(--color-bg)] rounded-lg">
          <p class="text-xs text-[var(--color-text-tertiary)]">Medien</p>
          <p class="font-medium">{{ executeResult.media.assigned }} zugeordnet, {{ executeResult.media.removed }} entfernt</p>
        </div>
      </div>
      <button class="pim-btn pim-btn-primary text-sm" @click="goBack">Zurück zur Produktliste</button>
    </div>

    <!-- Main Content (not shown after execution) -->
    <template v-if="!executeResult">
      <!-- Tab nav -->
      <div class="pim-card">
        <nav class="flex border-b border-[var(--color-border)] overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :class="[
              'flex items-center gap-1.5 px-4 py-2.5 text-xs font-medium whitespace-nowrap transition-colors border-b-2 -mb-px',
              activeTab === tab.key
                ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
                : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]',
            ]"
            @click="activeTab = tab.key"
          >
            <component :is="tab.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ tab.label }}
            <!-- Badge for configured ops -->
            <span
              v-if="tab.key === 'attributes' && attrOperations.length > 0"
              class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--color-accent)] text-white"
            >{{ attrOperations.length }}</span>
            <span
              v-if="tab.key === 'relations' && relationOps.filter(r => r.targetProduct).length > 0"
              class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--color-accent)] text-white"
            >{{ relationOps.filter(r => r.targetProduct).length }}</span>
            <span
              v-if="tab.key === 'output_hierarchy' && hierarchyOps.filter(h => h.node).length > 0"
              class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--color-accent)] text-white"
            >{{ hierarchyOps.filter(h => h.node).length }}</span>
            <span
              v-if="tab.key === 'status' && statusOperation"
              class="ml-1 w-2 h-2 rounded-full bg-[var(--color-accent)]"
            />
            <span
              v-if="tab.key === 'master_hierarchy' && masterHierarchyChanged"
              class="ml-1 w-2 h-2 rounded-full bg-[var(--color-accent)]"
            />
            <span
              v-if="tab.key === 'media' && mediaOps.filter(m => m.media).length > 0"
              class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-[var(--color-accent)] text-white"
            >{{ mediaOps.filter(m => m.media).length }}</span>
          </button>
        </nav>

        <div class="p-5">
          <!-- ═══ Tab: Attribute ═══ -->
          <div v-if="activeTab === 'attributes'" class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-[var(--color-text-secondary)]">Attributwerte für alle markierten Produkte setzen</p>
              <button class="pim-btn pim-btn-secondary text-xs" @click="openAttrPicker">
                <Plus class="w-3 h-3" :stroke-width="2" /> Attribut hinzufügen
              </button>
            </div>

            <!-- Attribute picker -->
            <div v-if="attrPickerOpen" class="p-3 bg-[var(--color-bg)] rounded-lg space-y-2">
              <div class="flex items-center justify-between">
                <div class="relative flex-1 max-w-sm">
                  <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                  <input
                    v-model="attrPickerSearch"
                    class="pim-input text-xs w-full pl-8"
                    placeholder="Attribut suchen…"
                    @input="debouncedAttrSearch"
                  />
                </div>
                <button class="pim-btn pim-btn-ghost p-1" @click="attrPickerOpen = false">
                  <X class="w-4 h-4" :stroke-width="1.75" />
                </button>
              </div>
              <div v-if="attrPickerLoading" class="space-y-1">
                <div v-for="i in 4" :key="i" class="pim-skeleton h-7 rounded" />
              </div>
              <div v-else-if="attrPickerItems.length > 0" class="max-h-48 overflow-y-auto space-y-1">
                <div
                  v-for="attr in attrPickerItems"
                  :key="attr.id"
                  class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-[var(--color-surface)] cursor-pointer"
                  @click="addAttribute(attr)"
                >
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-medium truncate">{{ attr.name_de || attr.technical_name }}</span>
                    <span v-if="attr.technical_name" class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ attr.technical_name }}</span>
                  </div>
                  <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0 ml-2">{{ attr.data_type }}</span>
                </div>
              </div>
              <div v-else class="space-y-1">
                <p class="text-xs text-[var(--color-text-tertiary)]">Keine gemeinsamen Attribute gefunden</p>
                <p v-if="attrPickerWarning" class="text-xs text-[var(--color-warning,#b45309)]">{{ attrPickerWarning }}</p>
              </div>
              <div v-if="attrPickerMeta.last_page > 1" class="flex items-center justify-between pt-1 border-t border-[var(--color-border)]">
                <span class="text-[11px] text-[var(--color-text-tertiary)]">Seite {{ attrPickerMeta.current_page }} / {{ attrPickerMeta.last_page }}</span>
                <div class="flex gap-1">
                  <button class="pim-btn pim-btn-ghost text-xs px-2 disabled:opacity-30" :disabled="attrPickerMeta.current_page <= 1" @click="fetchAttrPickerItems(attrPickerMeta.current_page - 1)">Zurück</button>
                  <button class="pim-btn pim-btn-ghost text-xs px-2 disabled:opacity-30" :disabled="attrPickerMeta.current_page >= attrPickerMeta.last_page" @click="fetchAttrPickerItems(attrPickerMeta.current_page + 1)">Weiter</button>
                </div>
              </div>
            </div>

            <!-- Configured attribute operations -->
            <div v-if="attrOperations.length > 0" class="space-y-2">
              <div
                v-for="(op, idx) in attrOperations"
                :key="op.attribute.id"
                class="p-3 bg-[var(--color-bg)] rounded-lg space-y-2"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-medium">{{ op.attribute.name_de || op.attribute.technical_name }}</span>
                    <span class="pim-badge text-[10px]">{{ op.attribute.data_type }}</span>
                    <span v-if="op.attribute.is_translatable" class="pim-badge text-[10px] bg-blue-50 text-blue-600">übersetzbar</span>
                  </div>
                  <button class="pim-btn pim-btn-ghost p-1 text-[var(--color-error)]" @click="removeAttrOp(idx)">
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
                  </button>
                </div>
                <div class="flex items-start gap-3 flex-wrap">
                  <!-- Mode -->
                  <div class="min-w-[160px]">
                    <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Modus</label>
                    <select v-model="op.mode" class="pim-input text-xs w-full">
                      <option value="overwrite">Überschreiben</option>
                      <option value="fill_empty">Leere füllen</option>
                      <option value="clear">Werte löschen</option>
                    </select>
                  </div>
                  <!-- Language -->
                  <div v-if="op.attribute.is_translatable" class="min-w-[100px]">
                    <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Sprache</label>
                    <select v-model="op.language" class="pim-input text-xs w-full">
                      <option value="de">Deutsch</option>
                      <option value="en">Englisch</option>
                    </select>
                  </div>
                  <!-- Value input (hidden for clear mode) -->
                  <div v-if="op.mode !== 'clear'" class="flex-1 min-w-[200px]">
                    <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Wert</label>
                    <PimAttributeInput
                      :type="attrInputType(op.attribute.data_type)"
                      :modelValue="op.value"
                      :options="attrInputOptions(op.attribute)"
                      placeholder="Wert eingeben…"
                      @update:modelValue="op.value = $event"
                    />
                  </div>
                  <div v-else class="flex-1 min-w-[200px] flex items-end">
                    <p class="text-xs text-[var(--color-text-tertiary)] italic pb-2">Werte werden gelöscht</p>
                  </div>
                </div>
              </div>
            </div>
            <p v-else-if="!attrPickerOpen" class="text-xs text-[var(--color-text-tertiary)]">Klicken Sie auf "Attribut hinzufügen", um Attribute auszuwählen.</p>
          </div>

          <!-- ═══ Tab: Beziehungen ═══ -->
          <div v-else-if="activeTab === 'relations'" class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-[var(--color-text-secondary)]">Produktbeziehungen für alle markierten Produkte verwalten</p>
              <button class="pim-btn pim-btn-secondary text-xs" @click="addRelationOp">
                <Plus class="w-3 h-3" :stroke-width="2" /> Beziehung hinzufügen
              </button>
            </div>

            <div v-if="relationOps.length > 0" class="space-y-2">
              <div
                v-for="(op, idx) in relationOps"
                :key="idx"
                class="p-3 bg-[var(--color-bg)] rounded-lg space-y-2"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3 flex-wrap flex-1">
                    <!-- Relation type -->
                    <div class="min-w-[160px]">
                      <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Beziehungsart</label>
                      <select v-model="op.relationType" class="pim-input text-xs w-full">
                        <option v-for="rt in relationTypesList" :key="rt.id" :value="rt">{{ rt.name_de || rt.technical_name }}</option>
                      </select>
                    </div>
                    <!-- Action -->
                    <div class="min-w-[120px]">
                      <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Aktion</label>
                      <select v-model="op.action" class="pim-input text-xs w-full">
                        <option value="add">Hinzufügen</option>
                        <option value="remove">Entfernen</option>
                      </select>
                    </div>
                  </div>
                  <button class="pim-btn pim-btn-ghost p-1 text-[var(--color-error)] shrink-0 self-start" @click="removeRelOp(idx)">
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
                  </button>
                </div>
                <!-- Target product search -->
                <div>
                  <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Zielprodukt</label>
                  <div v-if="op.targetProduct" class="flex items-center gap-2 px-2 py-1.5 bg-[var(--color-surface)] rounded">
                    <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ op.targetProduct.sku }}</span>
                    <span class="text-xs">{{ op.targetProduct.name || '—' }}</span>
                    <button class="pim-btn pim-btn-ghost p-0.5 ml-auto" @click="op.targetProduct = null">
                      <X class="w-3 h-3" :stroke-width="2" />
                    </button>
                  </div>
                  <div v-else>
                    <div class="relative">
                      <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                      <input
                        v-model="op.search"
                        class="pim-input text-xs w-full pl-8"
                        placeholder="Produkt suchen (SKU, Name)…"
                        @input="debouncedRelProductSearch(idx)"
                      />
                    </div>
                    <div v-if="op.results?.length > 0" class="max-h-32 overflow-y-auto mt-1 space-y-0.5">
                      <div
                        v-for="p in op.results"
                        :key="p.id"
                        class="flex items-center gap-2 px-2 py-1 rounded hover:bg-[var(--color-surface)] cursor-pointer text-xs"
                        @click="selectRelProduct(p, idx)"
                      >
                        <span class="font-mono text-[var(--color-text-secondary)]">{{ p.sku }}</span>
                        <span>{{ p.name || '—' }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-[var(--color-text-tertiary)]">Klicken Sie auf "Beziehung hinzufügen", um Produktbeziehungen zu konfigurieren.</p>
          </div>

          <!-- ═══ Tab: Ausgabehierarchie ═══ -->
          <div v-else-if="activeTab === 'output_hierarchy'" class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-[var(--color-text-secondary)]">Ausgabehierarchie-Zuordnungen verwalten</p>
              <button class="pim-btn pim-btn-secondary text-xs" @click="addHierarchyOp" :disabled="hierarchiesList.length === 0">
                <Plus class="w-3 h-3" :stroke-width="2" /> Zuordnung hinzufügen
              </button>
            </div>

            <div v-if="hierarchyOps.length > 0" class="space-y-2">
              <div
                v-for="(op, idx) in hierarchyOps"
                :key="idx"
                class="p-3 bg-[var(--color-bg)] rounded-lg space-y-2"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3 flex-wrap">
                    <div class="min-w-[160px]">
                      <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Hierarchie</label>
                      <select :value="op.hierarchyId" class="pim-input text-xs w-full" @change="onHierarchyChange(idx, $event.target.value)">
                        <option v-for="h in hierarchiesList" :key="h.id" :value="h.id">{{ h.name_de || h.name }}</option>
                      </select>
                    </div>
                    <div class="min-w-[120px]">
                      <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Aktion</label>
                      <select v-model="op.action" class="pim-input text-xs w-full">
                        <option value="assign">Zuordnen</option>
                        <option value="remove">Entfernen</option>
                      </select>
                    </div>
                  </div>
                  <button class="pim-btn pim-btn-ghost p-1 text-[var(--color-error)] shrink-0 self-start" @click="removeHierarchyOp(idx)">
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
                  </button>
                </div>
                <!-- Node selector -->
                <div>
                  <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Knoten</label>
                  <div v-if="op.node" class="flex items-center gap-2 px-2 py-1.5 bg-[var(--color-surface)] rounded text-xs">
                    <FolderTree class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                    <span class="font-medium">{{ op.node.name_de || op.node.name }}</span>
                    <button class="pim-btn pim-btn-ghost p-0.5 ml-auto" @click="op.node = null">
                      <X class="w-3 h-3" :stroke-width="2" />
                    </button>
                  </div>
                  <div v-else-if="hierarchyTrees[op.hierarchyId]" class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-1">
                    <PimTree
                      :nodes="hierarchyTrees[op.hierarchyId]"
                      :selectedId="null"
                      :expandedIds="new Set()"
                      @select="(node) => selectHierarchyNode(idx, node)"
                    />
                  </div>
                  <div v-else-if="hierarchyTreeLoading" class="space-y-1">
                    <div v-for="i in 3" :key="i" class="pim-skeleton h-6 rounded" />
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-[var(--color-text-tertiary)]">Klicken Sie auf "Zuordnung hinzufügen", um Hierarchie-Knoten zu konfigurieren.</p>
          </div>

          <!-- ═══ Tab: Status ═══ -->
          <div v-else-if="activeTab === 'status'" class="space-y-3">
            <p class="text-sm text-[var(--color-text-secondary)]">Status für alle markierten Produkte ändern</p>
            <div class="max-w-xs">
              <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Neuer Status</label>
              <select v-model="statusOperation" class="pim-input text-xs w-full">
                <option :value="null">— Nicht ändern —</option>
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <!-- ═══ Tab: Master-Hierarchie ═══ -->
          <div v-else-if="activeTab === 'master_hierarchy'" class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-[var(--color-text-secondary)]">Master-Hierarchie-Knoten für alle markierten Produkte zuordnen</p>
              <button v-if="!masterHierarchyChanged || masterHierarchyNodeId" class="pim-btn pim-btn-secondary text-xs" @click="clearMasterHierarchy">
                <Trash2 class="w-3 h-3" :stroke-width="2" /> Zuordnung entfernen
              </button>
            </div>
            <div v-if="masterHierarchyChanged && !masterHierarchyNodeId" class="flex items-center gap-2 p-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
              <AlertTriangle class="w-3.5 h-3.5 shrink-0" :stroke-width="1.75" />
              <span>Master-Hierarchie-Zuordnung wird für alle ausgewählten Produkte entfernt.</span>
              <button class="pim-btn pim-btn-ghost p-0.5 ml-auto" @click="masterHierarchyChanged = false">
                <X class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
            <div v-if="masterSelectedNode" class="flex items-center gap-2 p-2 bg-[var(--color-bg)] rounded-lg text-xs">
              <FolderTree class="w-3.5 h-3.5 text-[var(--color-accent)]" :stroke-width="1.75" />
              <span class="font-medium">{{ masterSelectedNode.name_de || masterSelectedNode.name }}</span>
              <button class="pim-btn pim-btn-ghost p-0.5 ml-auto" @click="selectMasterNode(null)">
                <X class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
            <div v-if="masterTreeLoading" class="space-y-1">
              <div v-for="i in 4" :key="i" class="pim-skeleton h-6 rounded" />
            </div>
            <div v-else-if="masterTree.length > 0" class="max-h-64 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2">
              <PimTree
                :nodes="masterTree"
                :selectedId="masterHierarchyNodeId"
                :expandedIds="masterExpandedNodes"
                @select="selectMasterNode"
                @toggle="toggleMasterExpanded"
              />
            </div>
            <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Master-Hierarchie gefunden.</p>
          </div>

          <!-- ═══ Tab: Medien ═══ -->
          <div v-else-if="activeTab === 'media'" class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-[var(--color-text-secondary)]">Medien für alle markierten Produkte verwalten</p>
              <button class="pim-btn pim-btn-secondary text-xs" @click="addMediaOp">
                <Plus class="w-3 h-3" :stroke-width="2" /> Medium hinzufügen
              </button>
            </div>

            <div v-if="mediaOps.length > 0" class="space-y-2">
              <div
                v-for="(op, idx) in mediaOps"
                :key="idx"
                class="p-3 bg-[var(--color-bg)] rounded-lg space-y-2"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3 flex-wrap">
                    <div v-if="usageTypesList.length > 0" class="min-w-[160px]">
                      <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Bildtyp</label>
                      <select v-model="op.usageType" class="pim-input text-xs w-full">
                        <option :value="null">— Kein Typ —</option>
                        <option v-for="ut in usageTypesList" :key="ut.id" :value="ut">{{ ut.name_de || ut.name || ut.technical_name }}</option>
                      </select>
                    </div>
                    <div class="min-w-[120px]">
                      <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Aktion</label>
                      <select v-model="op.action" class="pim-input text-xs w-full">
                        <option value="assign">Zuordnen</option>
                        <option value="remove">Entfernen</option>
                      </select>
                    </div>
                  </div>
                  <button class="pim-btn pim-btn-ghost p-1 text-[var(--color-error)] shrink-0 self-start" @click="removeMediaOp(idx)">
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
                  </button>
                </div>
                <!-- Media search -->
                <div>
                  <label class="text-[11px] text-[var(--color-text-tertiary)] block mb-1">Medium</label>
                  <div v-if="op.media" class="flex items-center gap-2 px-2 py-1.5 bg-[var(--color-surface)] rounded text-xs">
                    <Image class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                    <span class="font-medium">{{ op.media.original_filename || op.media.filename || op.media.id }}</span>
                    <button class="pim-btn pim-btn-ghost p-0.5 ml-auto" @click="op.media = null">
                      <X class="w-3 h-3" :stroke-width="2" />
                    </button>
                  </div>
                  <div v-else>
                    <div class="relative">
                      <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                      <input
                        v-model="op.search"
                        class="pim-input text-xs w-full pl-8"
                        placeholder="Medium suchen (Dateiname)…"
                        @input="debouncedMediaSearch(idx)"
                      />
                    </div>
                    <div v-if="op.results?.length > 0" class="max-h-32 overflow-y-auto mt-1 space-y-0.5">
                      <div
                        v-for="m in op.results"
                        :key="m.id"
                        class="flex items-center gap-2 px-2 py-1 rounded hover:bg-[var(--color-surface)] cursor-pointer text-xs"
                        @click="selectMedia(m, idx)"
                      >
                        <Image class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                        <span>{{ m.original_filename || m.filename || m.id }}</span>
                        <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.mime_type }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-[var(--color-text-tertiary)]">Klicken Sie auf "Medium hinzufügen", um Medien zuzuordnen oder zu entfernen.</p>
          </div>
        </div>
      </div>

      <!-- Footer: Summary + Preview Button -->
      <div class="pim-card p-4 flex items-center justify-between">
        <div class="text-xs text-[var(--color-text-secondary)]">
          <template v-if="operationCount > 0">
            {{ operationCount }} Operation(en) konfiguriert für {{ productIds.length }} Produkte
          </template>
          <template v-else>
            Noch keine Operationen konfiguriert
          </template>
        </div>
        <button
          class="pim-btn pim-btn-primary text-sm"
          :disabled="operationCount === 0 || previewing"
          @click="runPreview"
        >
          <template v-if="previewing">Vorschau wird erstellt…</template>
          <template v-else>Vorschau & Prüfen</template>
        </button>
      </div>

      <!-- Preview Modal -->
      <Teleport to="body">
        <div v-if="showPreview && previewResult" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showPreview = false">
          <div class="bg-[var(--color-surface)] rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--color-border)]">
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Vorschau der Änderungen</h3>
              <button class="pim-btn pim-btn-ghost p-1" @click="showPreview = false">
                <X class="w-4 h-4" :stroke-width="2" />
              </button>
            </div>
            <div class="p-5 space-y-3 overflow-y-auto flex-1">
              <p class="text-xs text-[var(--color-text-tertiary)]">{{ previewResult.total_products }} Produkte betroffen</p>

              <!-- Attributes preview -->
              <div v-if="previewResult.attributes" class="p-3 bg-[var(--color-bg)] rounded-lg space-y-1.5">
                <h4 class="text-xs font-medium">Attribute</h4>
                <div v-for="detail in previewResult.attributes.details" :key="detail.attribute_name" class="flex items-center justify-between text-xs">
                  <span>{{ detail.attribute_name }} <span class="text-[var(--color-text-tertiary)]">({{ {overwrite:'Überschreiben',fill_empty:'Leere füllen',clear:'Löschen'}[detail.mode] }})</span></span>
                  <span>{{ detail.would_update }} ändern, {{ detail.would_skip }} überspringen</span>
                </div>
              </div>

              <!-- Relations preview -->
              <div v-if="previewResult.relations" class="p-3 bg-[var(--color-bg)] rounded-lg text-xs space-y-1">
                <h4 class="font-medium">Beziehungen</h4>
                <p v-if="previewResult.relations.added > 0">{{ previewResult.relations.added }} hinzufügen</p>
                <p v-if="previewResult.relations.removed > 0">{{ previewResult.relations.removed }} entfernen</p>
                <p v-if="previewResult.relations.alreadyExists > 0" class="text-[var(--color-text-tertiary)]">{{ previewResult.relations.alreadyExists }} bereits vorhanden</p>
              </div>

              <!-- Output hierarchy preview -->
              <div v-if="previewResult.output_hierarchy" class="p-3 bg-[var(--color-bg)] rounded-lg text-xs space-y-1">
                <h4 class="font-medium">Ausgabehierarchie</h4>
                <p v-if="previewResult.output_hierarchy.assigned > 0">{{ previewResult.output_hierarchy.assigned }} zuordnen</p>
                <p v-if="previewResult.output_hierarchy.removed > 0">{{ previewResult.output_hierarchy.removed }} entfernen</p>
                <p v-if="previewResult.output_hierarchy.alreadyAssigned > 0" class="text-[var(--color-text-tertiary)]">{{ previewResult.output_hierarchy.alreadyAssigned }} bereits zugeordnet</p>
              </div>

              <!-- Status preview -->
              <div v-if="previewResult.status" class="p-3 bg-[var(--color-bg)] rounded-lg text-xs space-y-1">
                <h4 class="font-medium">Status</h4>
                <p>{{ previewResult.status.would_change }} ändern, {{ previewResult.status.already_target }} bereits im Zielstatus</p>
              </div>

              <!-- Master hierarchy preview -->
              <div v-if="previewResult.master_hierarchy" class="p-3 bg-[var(--color-bg)] rounded-lg text-xs space-y-1">
                <h4 class="font-medium">Master-Hierarchie</h4>
                <p>{{ previewResult.master_hierarchy.would_change }} umhängen, {{ previewResult.master_hierarchy.already_target }} bereits zugeordnet</p>
              </div>

              <!-- Media preview -->
              <div v-if="previewResult.media" class="p-3 bg-[var(--color-bg)] rounded-lg text-xs space-y-1">
                <h4 class="font-medium">Medien</h4>
                <p v-if="previewResult.media.assigned > 0">{{ previewResult.media.assigned }} zuordnen</p>
                <p v-if="previewResult.media.removed > 0">{{ previewResult.media.removed }} entfernen</p>
                <p v-if="previewResult.media.alreadyAssigned > 0" class="text-[var(--color-text-tertiary)]">{{ previewResult.media.alreadyAssigned }} bereits zugeordnet</p>
              </div>

              <!-- Warning for destructive ops -->
              <div v-if="previewResult.attributes?.details?.some(d => d.mode === 'clear') || previewResult.relations?.removed > 0 || previewResult.output_hierarchy?.removed > 0 || (previewResult.master_hierarchy && masterHierarchyChanged && !masterHierarchyNodeId) || previewResult.media?.removed > 0"
                class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800"
              >
                <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" :stroke-width="1.75" />
                <p>Einige Operationen sind destruktiv (Werte löschen, Zuordnungen entfernen). Diese Aktion kann nicht rückgängig gemacht werden.</p>
              </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-[var(--color-border)]">
              <button class="pim-btn pim-btn-secondary text-xs" @click="showPreview = false">Abbrechen</button>
              <button class="pim-btn pim-btn-primary text-xs" :disabled="executing" @click="requestExecute">
                <template v-if="executing">Wird ausgeführt…</template>
                <template v-else>Ausführen</template>
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </template>

    <!-- Confirm Dialog -->
    <PimConfirmDialog
      :open="showConfirm"
      title="Massenaktualisierung ausführen?"
      :message="`${operationCount} Operation(en) werden auf ${productIds.length} Produkte angewendet. Diese Aktion kann nicht rückgängig gemacht werden.`"
      confirmLabel="Ausführen"
      :danger="true"
      :loading="executing"
      @confirm="confirmExecute"
      @cancel="showConfirm = false"
    />

    <!-- Toast -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="actionFeedback"
          :class="[
            'fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] px-4 py-2 rounded-lg shadow-lg text-sm font-medium',
            actionFeedback.type === 'error' ? 'bg-[var(--color-error)] text-white' : 'bg-[var(--color-success)] text-white',
          ]"
        >
          {{ actionFeedback.msg }}
        </div>
      </transition>
    </Teleport>
  </div>
</template>
