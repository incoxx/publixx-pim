<script setup>
import { ref, computed, watch, onMounted, onUnmounted, markRaw, nextTick, defineAsyncComponent } from 'vue'
import { useDebounceFn, onClickOutside } from '@vueuse/core'
import { useHierarchyStore } from '@/stores/hierarchies'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import {
  Plus, Edit3, Trash2, FolderPlus, Package, Search,
  Copy, ChevronUp, ChevronDown, Settings, GripVertical, X, Save, Image, Layers, Tag,
} from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'
import attributesApi from '@/api/attributes'
import mediaApi from '@/api/media'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import PimTree from '@/components/shared/PimTree.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import { useLocalizedName } from '@/composables/useLocalizedName'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import HierarchyFormPanel from '@/components/panels/HierarchyFormPanel.vue'
import HierarchyNodeFormPanel from '@/components/panels/HierarchyNodeFormPanel.vue'
import MediaPickerDialog from '@/components/shared/MediaPickerDialog.vue'
import PdfPreview from '@/components/shared/PdfPreview.vue'
import HierarchyNodeProducts from '@/components/hierarchies/HierarchyNodeProducts.vue'
// vue-draggable-plus lazy laden um zirkuläre Initialisierung zu vermeiden
const VueDraggable = defineAsyncComponent(() =>
  import('vue-draggable-plus').then(m => m.VueDraggable)
)

const { t } = useI18n()
const { localizedName } = useLocalizedName()
const router = useRouter()
const _route = useRoute()
const store = useHierarchyStore()
const authStore = useAuthStore()
const selectedHierarchyId = ref(null)

// Node attributes
const nodeAttributes = ref([])
const nodeAttrsLoading = ref(false)
const showAttrPicker = ref(false)

// Attribute picker (server-side search + pagination)
const attrPickerSearch = ref('')
const attrPickerItems = ref([])
const attrPickerLoading = ref(false)
const attrPickerMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })

// Assigned attributes search (client-side filter)
const nodeAttrSearch = ref('')

// Node-assigned attribute values (EAV on nodes)
const nodeAttrValues = ref({})
const nodeAttrValuesSaving = ref(false)

// Tab-Steuerung für Produkte/Medien/Attribute
const activeNodeTab = ref('products')
const nodeProductCount = ref(0)

// Editierbare Sortier-Nummer für Attribute
const editingSortIdx = ref(null)
const editingSortValue = ref('')

// Node media assignments (explizite Zuordnung ueber hierarchy_node_media_assignments)
const nodeMediaItems = ref([])
const nodeMediaLoading = ref(false)
const showMediaPicker = ref(false)
const availableUsageTypes = ref([])

// Ordner-Inhalt (nur Asset-Hierarchien): Medien mit media.asset_folder_id = dieser
// Knoten. Das ist eine von der expliziten Zuordnung oben komplett unabhaengige
// Beziehung (siehe MediaView.vue) — Dateien, die im Media-Modul in diesen Ordner
// hochgeladen wurden, tauchen sonst hier nie auf, obwohl es "derselbe Knoten" ist.
const folderMediaItems = ref([])
const folderMediaLoading = ref(false)

async function loadFolderMedia(nodeId) {
  if (!nodeId || store.currentHierarchy?.hierarchy_type !== 'asset') {
    folderMediaItems.value = []
    return
  }
  folderMediaLoading.value = true
  try {
    const { data } = await mediaApi.list({ filters: { asset_folder_id: nodeId }, perPage: 100 })
    folderMediaItems.value = data.data || data
  } catch {
    folderMediaItems.value = []
  } finally {
    folderMediaLoading.value = false
  }
}

async function loadNodeMedia(nodeId) {
  nodeMediaLoading.value = true
  try {
    const { data } = await hierarchiesApi.getNodeMedia(nodeId, { perPage: 100 })
    const items = data.data || data
    nodeMediaItems.value = items.map(a => ({
      id: a.id,
      media_id: a.media_id || a.media?.id,
      file_name: a.media?.file_name || a.file_name,
      mime_type: a.media?.mime_type || a.mime_type,
      usage_type: a.usage_type,
      sort_order: a.sort_order,
      is_primary: a.is_primary,
      thumb_url: a.media?.thumb_url,
      file_url: a.media?.file_url || a.media?.url,
    }))
  } catch {
    nodeMediaItems.value = []
  } finally {
    nodeMediaLoading.value = false
  }
}

async function loadUsageTypes() {
  if (availableUsageTypes.value.length > 0) return
  try {
    const { data } = await mediaUsageTypes.list()
    availableUsageTypes.value = data.data || data
  } catch { /* ignore */ }
}

function openMediaPicker() {
  loadUsageTypes()
  showMediaPicker.value = true
}

async function onMediaSelected(media, usageTypeId) {
  if (!store.selectedNode) return
  try {
    await hierarchiesApi.assignNodeMedia(store.selectedNode.id, {
      media_id: media.id,
      usage_type_id: usageTypeId || null,
      sort_order: nodeMediaItems.value.length,
    })
    showMediaPicker.value = false
    await loadNodeMedia(store.selectedNode.id)
    showFeedback(t('Medium zugeordnet'))
  } catch (e) {
    showFeedback(e.response?.data?.message || t('Fehler beim Zuordnen'), 'error')
  }
}

async function onMediaSelectedBulk(mediaItems) {
  if (!store.selectedNode) return
  try {
    for (let i = 0; i < mediaItems.length; i++) {
      await hierarchiesApi.assignNodeMedia(store.selectedNode.id, {
        media_id: mediaItems[i].id,
        sort_order: nodeMediaItems.value.length + i,
      })
    }
    showMediaPicker.value = false
    await loadNodeMedia(store.selectedNode.id)
    showFeedback(t('{count} Medien zugeordnet', { count: mediaItems.length }))
  } catch (e) {
    showFeedback(e.response?.data?.message || t('Fehler beim Zuordnen'), 'error')
    await loadNodeMedia(store.selectedNode.id)
  }
}

async function detachNodeMedia(item) {
  if (!store.selectedNode) return
  try {
    await hierarchiesApi.removeNodeMedia(item.id)
    nodeMediaItems.value = nodeMediaItems.value.filter(m => m.id !== item.id)
    showFeedback(t('Zuordnung entfernt'))
  } catch (e) {
    showFeedback(e.response?.data?.message || t('Fehler beim Entfernen'), 'error')
  }
}

function getNodeMediaUrl(item) {
  return item.thumb_url || item.file_url || ''
}

function isNodeMediaPdf(item) {
  return item.mime_type === 'application/pdf' || (item.file_name && item.file_name.toLowerCase().endsWith('.pdf'))
}

// Hierarchy-level attributes (values editable per node)
const hierarchyLevelAttrs = ref([])
const hierarchyAttrValues = ref({})
const hierarchyAttrSaving = ref(false)
const hierarchyAttrsLoaded = ref(null) // track which hierarchy was loaded

// Tree panel resize
const treePanelWidth = ref(320)
const isTreeResizing = ref(false)
const TREE_MIN_WIDTH = 240
const TREE_MAX_WIDTH = 600

function startTreeResize(e) {
  e.preventDefault()
  isTreeResizing.value = true
  const startX = e.clientX
  const startWidth = treePanelWidth.value

  function onMove(ev) {
    treePanelWidth.value = Math.min(TREE_MAX_WIDTH, Math.max(TREE_MIN_WIDTH, startWidth + (ev.clientX - startX)))
  }

  function onUp() {
    isTreeResizing.value = false
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }

  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}

// Node search
const nodeSearchQuery = ref('')
const nodeSearchResults = ref([])
const nodeSearching = ref(false)
const nodeSearchRef = ref(null)
const highlightedNodeIds = ref(new Set())
let nodeSearchTimer = null

onClickOutside(nodeSearchRef, () => {
  nodeSearchResults.value = []
})

function searchNodes() {
  clearTimeout(nodeSearchTimer)
  if (nodeSearchQuery.value.length < 2) {
    nodeSearchResults.value = []
    highlightedNodeIds.value = new Set()
    return
  }
  nodeSearchTimer = setTimeout(async () => {
    nodeSearching.value = true
    try {
      const { data } = await hierarchiesApi.searchNodes({
        search: nodeSearchQuery.value,
        filters: { hierarchy_id: selectedHierarchyId.value },
        include_descendants: false,
        perPage: 20,
      })
      const results = data.data || data
      // Build breadcrumb path labels from the in-memory tree
      for (const result of results) {
        if (result.path) {
          const ids = result.path.split('/').filter(Boolean)
          // Exclude the node itself from the breadcrumb
          const ancestorIds = ids.slice(0, -1)
          const labels = ancestorIds.map(id => {
            const n = findNodeInTree(store.tree, id)
            return n ? (localizedName(n) || n.name || '') : ''
          }).filter(Boolean)
          result.breadcrumb = labels.length > 0 ? labels.join(' › ') : null
        }
      }
      nodeSearchResults.value = results
      // Auto-expand ancestor paths and highlight all matching nodes
      const newHighlighted = new Set()
      for (const result of results) {
        newHighlighted.add(result.id)
        if (result.path) {
          const ids = result.path.split('/').filter(Boolean)
          for (const id of ids) {
            store.expandedNodes.add(id)
          }
        }
        if (result.parent_node_id) {
          store.expandedNodes.add(result.parent_node_id)
        }
      }
      highlightedNodeIds.value = newHighlighted
    } catch {
      nodeSearchResults.value = []
    } finally {
      nodeSearching.value = false
    }
  }, 300)
}

function navigateToNode(node) {
  // Extract ancestor IDs from materialized path and expand them
  if (node.path) {
    const ids = node.path.split('/').filter(Boolean)
    for (const id of ids) {
      if (!store.expandedNodes.has(id)) {
        store.expandedNodes.add(id)
      }
    }
  }
  // Also expand the node's parent
  if (node.parent_node_id && !store.expandedNodes.has(node.parent_node_id)) {
    store.expandedNodes.add(node.parent_node_id)
  }
  // Find the full node object in the tree for selection
  const fullNode = findNodeInTree(store.tree, node.id)
  store.selectNode(fullNode || node)
  // Clear search and highlights
  nodeSearchQuery.value = ''
  nodeSearchResults.value = []
  highlightedNodeIds.value = new Set()
}

function findNodeInTree(nodes, id) {
  for (const n of nodes) {
    if (n.id === id) return n
    if (n.children?.length) {
      const found = findNodeInTree(n.children, id)
      if (found) return found
    }
  }
  return null
}

// Delete state
const deleteNodeTarget = ref(null)
const nodeDeleting = ref(false)

// Hierarchy delete state
const deleteHierarchyTarget = ref(null)
const hierarchyDeleting = ref(false)

// Context menu
const contextMenu = ref({ show: false, x: 0, y: 0, node: null })

// Action feedback
const actionFeedback = ref(null)
let feedbackTimer = null

function showFeedback(msg, type = 'success') {
  actionFeedback.value = { msg, type }
  clearTimeout(feedbackTimer)
  feedbackTimer = setTimeout(() => { actionFeedback.value = null }, 3000)
}

async function selectHierarchy(id) {
  selectedHierarchyId.value = id
  store.selectNode(null)
  hierarchyAttrsLoaded.value = null // force reload for new hierarchy
  await store.fetchTree(id)
}

function handleSelect(node) {
  store.selectNode(node)
}

function handleToggle(nodeId) {
  store.toggleExpanded(nodeId)
}

async function handleMove(sourceId, targetId) {
  try {
    await store.moveNode(sourceId, targetId, 0)
    if (selectedHierarchyId.value) {
      await store.fetchTree(selectedHierarchyId.value)
    }
    showFeedback(t('Knoten verschoben'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Verschieben'), 'error')
  }
}

// ─── Context Menu ────────────────────────────────────
function handleContextMenu(event, node) {
  const rect = event.currentTarget?.getBoundingClientRect() || { left: event.clientX, bottom: event.clientY }
  contextMenu.value = {
    show: true,
    x: rect.left || event.clientX,
    y: (rect.bottom || event.clientY) + 4,
    node,
  }
}

function closeContextMenu() {
  contextMenu.value.show = false
}

function handleDocClick(e) {
  if (contextMenu.value.show) {
    closeContextMenu()
  }
}

onMounted(() => document.addEventListener('click', handleDocClick, true))
onUnmounted(() => {
  document.removeEventListener('click', handleDocClick, true)
  clearTimeout(nodeSearchTimer)
})

// ─── Hierarchy CRUD ──────────────────────────────────
function openCreateHierarchy() {
  authStore.openPanel(markRaw(HierarchyFormPanel), {
    hierarchy: null,
    onSaved: async (newHierarchy) => {
      await store.fetchHierarchies()
      if (newHierarchy?.id) await selectHierarchy(newHierarchy.id)
      showFeedback(t('Hierarchie erstellt'))
    },
  })
}

function openEditHierarchy() {
  if (!store.currentHierarchy) return
  authStore.openPanel(markRaw(HierarchyFormPanel), {
    hierarchy: store.currentHierarchy,
    onSaved: async () => {
      await store.fetchHierarchies()
      if (selectedHierarchyId.value) await store.fetchTree(selectedHierarchyId.value)
      showFeedback(t('Hierarchie aktualisiert'))
    },
  })
}

function requestDeleteHierarchy() {
  if (!store.currentHierarchy) return
  if (store.currentHierarchy.hierarchy_type === 'master') {
    showFeedback(t('Die Master-Hierarchie kann nicht gelöscht werden.'), 'error')
    return
  }
  deleteHierarchyTarget.value = store.currentHierarchy
}

async function confirmDeleteHierarchy({ force } = {}) {
  hierarchyDeleting.value = true
  try {
    await store.deleteHierarchy(deleteHierarchyTarget.value.id, { force })
    deleteHierarchyTarget.value = null
    store.selectNode(null)
    await store.fetchHierarchies()
    if (store.hierarchies.length > 0) {
      await selectHierarchy(store.hierarchies[0].id)
    } else {
      selectedHierarchyId.value = null
      store.tree.splice(0)
    }
    showFeedback(t('Hierarchie gelöscht'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Löschen der Hierarchie'), 'error')
  } finally {
    hierarchyDeleting.value = false
  }
}

// ─── Node CRUD ───────────────────────────────────────
function createChildNode(parentNode) {
  closeContextMenu()
  if (!selectedHierarchyId.value) return
  authStore.openPanel(markRaw(HierarchyNodeFormPanel), {
    node: null,
    hierarchyId: selectedHierarchyId.value,
    parentNodeId: parentNode?.id || null,
  })
}

function editNode(node) {
  closeContextMenu()
  if (!selectedHierarchyId.value) return
  authStore.openPanel(markRaw(HierarchyNodeFormPanel), {
    node,
    hierarchyId: selectedHierarchyId.value,
  })
}

function requestDeleteNode(node) {
  closeContextMenu()
  deleteNodeTarget.value = node
}

async function confirmDeleteNode({ force } = {}) {
  nodeDeleting.value = true
  try {
    await store.deleteNode(deleteNodeTarget.value.id, { force })
    if (store.selectedNode?.id === deleteNodeTarget.value?.id) {
      store.selectNode(null)
    }
    deleteNodeTarget.value = null
    await store.fetchTree(selectedHierarchyId.value)
    showFeedback(t('Knoten gelöscht'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Löschen'), 'error')
  } finally {
    nodeDeleting.value = false
  }
}

// ─── Node Duplicate ──────────────────────────────────
async function duplicateNode(node) {
  closeContextMenu()
  try {
    await store.duplicateNode(node.id)
    await store.fetchTree(selectedHierarchyId.value)
    showFeedback(t('Knoten dupliziert'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Duplizieren'), 'error')
  }
}

// ─── Node Reorder ────────────────────────────────────
function findSiblings(nodeId, nodes) {
  for (const n of nodes) {
    if (n.children && n.children.some(c => c.id === nodeId)) {
      return n.children
    }
    if (n.children) {
      const found = findSiblings(nodeId, n.children)
      if (found) return found
    }
  }
  return null
}

async function moveNodeUp(node) {
  closeContextMenu()
  const siblings = findSiblings(node.id, store.tree) || store.tree
  const idx = siblings.findIndex(n => n.id === node.id)
  if (idx <= 0) return
  try {
    await store.updateNode(node.id, { sort_order: (siblings[idx - 1].sort_order ?? idx - 1) - 1 })
    await store.fetchTree(selectedHierarchyId.value)
    showFeedback(t('Knoten nach oben verschoben'))
  } catch (e) {
    showFeedback(t('Fehler beim Verschieben'), 'error')
  }
}

async function moveNodeDown(node) {
  closeContextMenu()
  const siblings = findSiblings(node.id, store.tree) || store.tree
  const idx = siblings.findIndex(n => n.id === node.id)
  if (idx < 0 || idx >= siblings.length - 1) return
  try {
    await store.updateNode(node.id, { sort_order: (siblings[idx + 1].sort_order ?? idx + 1) + 1 })
    await store.fetchTree(selectedHierarchyId.value)
    showFeedback(t('Knoten nach unten verschoben'))
  } catch (e) {
    showFeedback(t('Fehler beim Verschieben'), 'error')
  }
}

// ─── Node Attribute Assignments ──────────────────────
async function loadNodeAttributes(nodeId) {
  if (!nodeId) return
  nodeAttrsLoading.value = true
  try {
    const { data } = await hierarchiesApi.getNodeAttributes(nodeId, { inherited: true, perPage: 500 })
    nodeAttributes.value = data.data || data
  } catch { nodeAttributes.value = [] }
  finally { nodeAttrsLoading.value = false }
}

async function assignAttribute(attr) {
  if (!store.selectedNode) return
  try {
    await hierarchiesApi.assignNodeAttribute(store.selectedNode.id, {
      attribute_id: attr.id,
      access_product: 'editable',
      access_hierarchy: 'visible',
      access_variant: 'editable',
    })
    await loadNodeAttributes(store.selectedNode.id)
    // Refresh picker to exclude newly assigned attribute
    await fetchPickerAttributes(attrPickerMeta.value.current_page)
    showFeedback(t('Attribut zugeordnet'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Zuordnen'), 'error')
  }
}

async function removeNodeAttribute(assignment) {
  try {
    await hierarchiesApi.removeNodeAttributeAssignment(assignment.id)
    await loadNodeAttributes(store.selectedNode.id)
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Entfernen'), 'error')
  }
}

// Facette-Kennzeichnung für die Asset-Facettensuche — nur für Asset-Hierarchien relevant
// (Produkt-Hierarchien nutzen weiterhin das theme-weite facet_attribute_ids-Setting).
async function toggleNodeFacet(assignment) {
  const nextValue = !assignment.is_facet
  try {
    await hierarchiesApi.updateNodeAttributeAssignment(assignment.id, { is_facet: nextValue })
    assignment.is_facet = nextValue
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Umschalten der Facette'), 'error')
  }
}

async function moveAttributeUp(index) {
  if (index <= 0) return
  const list = [...nodeAttributes.value]
  ;[list[index - 1], list[index]] = [list[index], list[index - 1]]
  nodeAttributes.value = list
  await persistAttributeOrder()
}

async function moveAttributeDown(index) {
  if (index >= nodeAttributes.value.length - 1) return
  const list = [...nodeAttributes.value]
  ;[list[index], list[index + 1]] = [list[index + 1], list[index]]
  nodeAttributes.value = list
  await persistAttributeOrder()
}

async function persistAttributeOrder() {
  try {
    const items = nodeAttributes.value.map((a, i) => ({
      id: a.id,
      collection_sort: 0,
      attribute_sort: i,
    }))
    await hierarchiesApi.bulkSortAssignments({ items })
  } catch (e) {
    showFeedback(t('Fehler beim Speichern der Reihenfolge'), 'error')
    if (store.selectedNode) await loadNodeAttributes(store.selectedNode.id)
  }
}

async function toggleRequired(assignment) {
  const newValue = !assignment.is_required
  try {
    await hierarchiesApi.updateNodeAttributeAssignment(assignment.id, { is_required: newValue })
    assignment.is_required = newValue
  } catch (e) {
    showFeedback(t('Fehler beim Aktualisieren'), 'error')
  }
}

// ─── Node Attribute Values ───────────────────────────
async function loadNodeAttrValues(nodeId) {
  if (!nodeId) { nodeAttrValues.value = {}; return }
  try {
    const { data } = await hierarchiesApi.getNodeAttributeValues(nodeId, { perPage: 500 })
    const vals = data.data || data
    const map = {}
    for (const v of vals) {
      const attrId = v.attribute_id || v.attribute?.id
      map[attrId] = v.value_string ?? v.value_number ?? v.value_date ?? v.value_flag ?? v.value_selection_id ?? null
    }
    nodeAttrValues.value = map
  } catch {
    nodeAttrValues.value = {}
  }
}

async function saveNodeAttrValues() {
  if (!store.selectedNode || nodeAttributes.value.length === 0) return
  nodeAttrValuesSaving.value = true
  try {
    const values = []
    for (const assignment of nodeAttributes.value) {
      const attr = assignment.attribute
      if (!attr) continue
      if (assignment.access_hierarchy !== 'editable') continue
      const val = nodeAttrValues.value[attr.id]
      if (val === undefined || val === null || val === '') continue
      const entry = { attribute_id: attr.id }
      switch (attr.data_type) {
        case 'Number': case 'Float':
          entry.value_number = Number(val)
          break
        case 'Date':
          entry.value_date = val
          break
        case 'Flag':
          entry.value_flag = !!val
          break
        case 'Selection': case 'Dictionary':
          entry.value_string = String(val)
          entry.value_selection_id = val
          break
        default:
          entry.value_string = String(val)
      }
      values.push(entry)
    }
    if (values.length === 0) {
      showFeedback(t('Keine Werte zum Speichern'))
      return
    }
    await hierarchiesApi.updateNodeAttributeValues(store.selectedNode.id, values)
    showFeedback(t('Attributwerte gespeichert'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Speichern'), 'error')
  } finally {
    nodeAttrValuesSaving.value = false
  }
}

// ─── Attribute Picker (search + pagination) ─────────
async function fetchPickerAttributes(page = 1) {
  attrPickerLoading.value = true
  try {
    const assignedIds = new Set(nodeAttributes.value.map(a => a.attribute?.id || a.attribute_id))
    const { data } = await attributesApi.list({
      search: attrPickerSearch.value || undefined,
      page,
      perPage: 20,
      sort: 'name_de',
      order: 'asc',
    })
    const items = data.data || data
    // Hide child attributes (parent_attribute_id set) and already assigned
    attrPickerItems.value = items.filter(a => !assignedIds.has(a.id) && !a.parent_attribute_id)
    attrPickerMeta.value = data.meta || { current_page: page, last_page: 1, total: items.length, per_page: 20 }
  } catch {
    attrPickerItems.value = []
  } finally {
    attrPickerLoading.value = false
  }
}

const debouncedPickerSearch = useDebounceFn(() => {
  fetchPickerAttributes(1)
}, 300)

function onAttrPickerSearchInput() {
  attrPickerMeta.value.current_page = 1
  debouncedPickerSearch()
}

function attrPickerPrevPage() {
  if (attrPickerMeta.value.current_page > 1) {
    fetchPickerAttributes(attrPickerMeta.value.current_page - 1)
  }
}

function attrPickerNextPage() {
  if (attrPickerMeta.value.current_page < attrPickerMeta.value.last_page) {
    fetchPickerAttributes(attrPickerMeta.value.current_page + 1)
  }
}

// Computed: filter assigned attributes client-side (hide composite children)
const filteredNodeAttributes = computed(() => {
  // Hide child attributes of composites (they're managed via their parent)
  const compositeIds = new Set(
    nodeAttributes.value
      .filter(a => a.attribute?.data_type === 'Composite')
      .map(a => a.attribute?.id || a.attribute_id)
  )
  let attrs = nodeAttributes.value.filter(a => {
    const parentId = a.attribute?.parent_attribute_id
    return !parentId || !compositeIds.has(parentId)
  })

  if (nodeAttrSearch.value.trim()) {
    const q = nodeAttrSearch.value.toLowerCase()
    attrs = attrs.filter(a => {
      const name = (a.attribute?.name_de || a.attribute?.technical_name || '').toLowerCase()
      const tech = (a.attribute?.technical_name || '').toLowerCase()
      return name.includes(q) || tech.includes(q)
    })
  }
  return attrs
})

// ─── Editierbare Sortier-Nummer für Attribute ──────
const editingSortAssignmentId = ref(null)

function startEditSort(assignment, idx) {
  editingSortAssignmentId.value = assignment.id
  editingSortIdx.value = idx
  editingSortValue.value = String(idx + 1)
}

function applySortValue() {
  const assignmentId = editingSortAssignmentId.value
  editingSortIdx.value = null
  editingSortAssignmentId.value = null
  if (!assignmentId) return

  const list = [...nodeAttributes.value]
  const fromIdx = list.findIndex(a => a.id === assignmentId)
  if (fromIdx === -1) return

  const targetIdx = Math.max(0, Math.min(list.length - 1, parseInt(editingSortValue.value) - 1))
  if (isNaN(targetIdx) || targetIdx === fromIdx) return

  const [item] = list.splice(fromIdx, 1)
  list.splice(targetIdx, 0, item)
  nodeAttributes.value = list
  persistAttributeOrder()
}

// ─── Hierarchy-level Attribute Helpers ───────────────
function mapDataTypeToInput(dataType) {
  return {
    String: 'text', Number: 'number', Float: 'decimal', Date: 'date',
    Flag: 'boolean', Selection: 'select', Dictionary: 'dictionary', RichText: 'richtext',
    Hyperlink: 'hyperlink', ImageLink: 'imagelink', PdfLink: 'pdflink', VideoLink: 'videolink',
  }[dataType] || 'text'
}

async function loadHierarchyLevelAttrs(hierarchyId) {
  if (!hierarchyId || hierarchyAttrsLoaded.value === hierarchyId) return
  try {
    const { data } = await hierarchiesApi.getHierarchyAttributes(hierarchyId)
    hierarchyLevelAttrs.value = (data.data || data).map(a => a.attribute || a)
    hierarchyAttrsLoaded.value = hierarchyId
  } catch {
    hierarchyLevelAttrs.value = []
  }
}

async function loadHierarchyAttrValues(nodeId) {
  if (!nodeId || hierarchyLevelAttrs.value.length === 0) {
    hierarchyAttrValues.value = {}
    return
  }
  try {
    const { data } = await hierarchiesApi.getNodeAttributeValues(nodeId, { perPage: 500 })
    const vals = data.data || data
    const map = {}
    for (const v of vals) {
      const attrId = v.attribute_id || v.attribute?.id
      // Pick whichever typed value is set
      const value = v.value_string ?? v.value_number ?? v.value_date ?? v.value_flag ?? v.value_selection_id ?? null
      map[attrId] = value
    }
    hierarchyAttrValues.value = map
  } catch {
    hierarchyAttrValues.value = {}
  }
}

async function saveHierarchyAttrValues() {
  if (!store.selectedNode) return
  hierarchyAttrSaving.value = true
  try {
    const values = []
    for (const attr of hierarchyLevelAttrs.value) {
      const val = hierarchyAttrValues.value[attr.id]
      if (val === undefined || val === null || val === '') continue
      const entry = { attribute_id: attr.id }
      // Map value to the correct typed column
      switch (attr.data_type) {
        case 'Number': case 'Float':
          entry.value_number = Number(val)
          break
        case 'Date':
          entry.value_date = val
          break
        case 'Flag':
          entry.value_flag = !!val
          break
        case 'Selection': case 'Dictionary':
          entry.value_string = val
          entry.value_selection_id = val
          break
        default:
          entry.value_string = String(val)
      }
      values.push(entry)
    }
    if (values.length === 0) {
      showFeedback(t('Keine Werte zum Speichern'))
      hierarchyAttrSaving.value = false
      return
    }
    await hierarchiesApi.updateNodeAttributeValues(store.selectedNode.id, values)
    showFeedback(t('Hierarchie-Attributwerte gespeichert'))
  } catch (e) {
    showFeedback(e.response?.data?.title || t('Fehler beim Speichern'), 'error')
  } finally {
    hierarchyAttrSaving.value = false
  }
}

// When attr picker opens, fetch first page
watch(showAttrPicker, (open) => {
  if (open) {
    attrPickerSearch.value = ''
    fetchPickerAttributes(1)
  }
})

// Watch node selection to load attributes, media, etc.
// Produkte werden von HierarchyNodeProducts-Komponente selbst geladen (watch nodeId)
watch(() => store.selectedNode, async (node) => {
  activeNodeTab.value = 'products'
  if (node) {
    loadNodeAttributes(node.id)
    loadNodeAttrValues(node.id)
    loadNodeMedia(node.id)
    loadFolderMedia(node.id)
    await loadHierarchyLevelAttrs(selectedHierarchyId.value)
    loadHierarchyAttrValues(node.id)
  } else {
    nodeAttributes.value = []
    hierarchyAttrValues.value = {}
    nodeAttrValues.value = {}
    nodeMediaItems.value = []
    folderMediaItems.value = []
  }
  showAttrPicker.value = false
  showMediaPicker.value = false
  nodeAttrSearch.value = ''
})

onMounted(async () => {
  await store.fetchHierarchies()
  if (store.hierarchies.length > 0) {
    await selectHierarchy(store.hierarchies[0].id)
  }
  // Schnellsuche: Query-Parameter auslesen und Knotensuche triggern
  if (_route.query.search) {
    nodeSearchQuery.value = _route.query.search
    searchNodes()
  }
})
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-4 h-auto lg:h-[calc(100vh-140px)]" data-testid="hierarchy-view">
    <!-- Left: Tree -->
    <div
      class="w-full shrink-0 pim-card flex flex-col overflow-hidden max-h-[50vh] lg:max-h-none relative"
      :style="{ width: treePanelWidth + 'px', maxWidth: '100%' }"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ t('hierarchy.title') }}</h3>
        <button v-if="authStore.hasPermission('hierarchies.create')" class="pim-btn pim-btn-ghost p-1" title="Neue Hierarchie" @click="openCreateHierarchy">
          <Plus class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>
      <!-- Hierarchy selector + edit/delete -->
      <div class="px-3 py-2 border-b border-[var(--color-border)] flex items-center gap-1">
        <select
          class="pim-input text-xs flex-1"
          :value="selectedHierarchyId"
          @change="selectHierarchy($event.target.value)"
        >
          <option v-for="h in store.hierarchies" :key="h.id" :value="h.id">
            {{ localizedName(h) || h.name }} ({{ h.hierarchy_type }})
          </option>
        </select>
        <button v-if="authStore.hasPermission('hierarchies.edit')" class="pim-btn pim-btn-ghost p-1 shrink-0" title="Hierarchie bearbeiten" @click="openEditHierarchy">
          <Edit3 class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
        <button
          v-if="authStore.hasPermission('hierarchies.delete')"
          class="pim-btn pim-btn-ghost p-1 shrink-0"
          :class="store.currentHierarchy?.hierarchy_type === 'master' ? 'opacity-30 cursor-not-allowed' : 'text-[var(--color-error)]'"
          :title="store.currentHierarchy?.hierarchy_type === 'master' ? t('Master-Hierarchie kann nicht gelöscht werden') : t('Hierarchie löschen')"
          @click="requestDeleteHierarchy"
        >
          <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
      </div>
      <!-- Action bar for adding root node -->
      <div v-if="authStore.hasPermission('hierarchy-nodes.create')" class="px-3 py-2 border-b border-[var(--color-border)]">
        <button class="pim-btn pim-btn-secondary text-xs w-full" @click="createChildNode(null)" data-testid="btn-new-node">
          <FolderPlus class="w-3.5 h-3.5" :stroke-width="2" /> Knoten erstellen
        </button>
      </div>
      <!-- Node search -->
      <div ref="nodeSearchRef" class="px-3 py-2 border-b border-[var(--color-border)] relative">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <input
            v-model="nodeSearchQuery"
            type="text"
            class="pim-input text-xs w-full pim-input-icon"
            placeholder="Knoten suchen..."
            @input="searchNodes"
          />
          <button v-if="nodeSearchQuery" class="absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded hover:bg-[var(--color-bg)]" @click="nodeSearchQuery = ''; nodeSearchResults.length = 0; highlightedNodeIds = new Set()">
            <X class="w-3 h-3 text-[var(--color-text-tertiary)]" />
          </button>
        </div>
        <!-- Search results dropdown -->
        <div v-if="nodeSearchResults.length > 0" class="absolute left-0 right-0 z-20 mx-3 mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg max-h-60 overflow-y-auto">
          <div
            v-for="result in nodeSearchResults"
            :key="result.id"
            class="px-3 py-2 text-xs cursor-pointer hover:bg-[var(--color-bg)] border-b border-[var(--color-border)] last:border-b-0"
            @click="navigateToNode(result)"
          >
            <div class="font-medium text-[var(--color-text-primary)]">{{ localizedName(result) || result.name }}</div>
            <div v-if="result.breadcrumb" class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5 truncate">{{ result.breadcrumb }}</div>
          </div>
        </div>
        <div v-else-if="nodeSearchQuery.length >= 2 && !nodeSearching && nodeSearchResults.length === 0" class="absolute left-0 right-0 z-20 mx-3 mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg">
          <div class="px-3 py-3 text-xs text-center text-[var(--color-text-tertiary)]">Keine Knoten gefunden.</div>
        </div>
      </div>

      <!-- Tree -->
      <div class="flex-1 overflow-y-auto p-2 relative">
        <div v-if="store.loading" class="space-y-2 p-2">
          <div v-for="i in 6" :key="i" class="pim-skeleton h-6 rounded" :style="{ width: (50 + Math.random() * 50) + '%' }" />
        </div>
        <PimTree
          v-else
          :nodes="store.tree"
          :selectedId="store.selectedNode?.id"
          :expandedIds="store.expandedNodes"
          :highlightedIds="highlightedNodeIds"
          @select="handleSelect"
          @toggle="handleToggle"
          @move="handleMove"
          @context-menu="handleContextMenu"
        />
        <div v-if="!store.loading && store.error" class="text-xs text-center text-[var(--color-error)] py-4 px-2">
          <p class="font-semibold">Fehler beim Laden des Baums:</p>
          <p>{{ store.error }}</p>
        </div>
        <p v-else-if="!store.loading && store.tree.length === 0" class="text-xs text-center text-[var(--color-text-tertiary)] py-8">
          Keine Knoten vorhanden.
        </p>
      </div>
      <!-- Resize handle (desktop only) -->
      <div
        class="absolute top-0 right-0 w-[5px] h-full cursor-col-resize group z-10 items-center justify-center hidden lg:flex"
        @mousedown="startTreeResize"
      >
        <div
          class="w-[2px] h-full transition-colors"
          :class="isTreeResizing ? 'bg-[var(--color-accent)]' : 'bg-transparent group-hover:bg-[var(--color-accent)]/40'"
        />
      </div>
    </div>

    <!-- Context Menu Dropdown -->
    <Teleport to="body">
      <div
        v-if="contextMenu.show && authStore.hasPermission('hierarchy-nodes.edit')"
        class="fixed z-50 min-w-[180px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 text-[13px]"
        :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
        @click.stop
      >
        <button v-if="authStore.hasPermission('hierarchy-nodes.create')" class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2" @click="createChildNode(contextMenu.node)">
          <FolderPlus class="w-3.5 h-3.5" :stroke-width="1.75" /> Unterknoten erstellen
        </button>
        <button class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2" @click="editNode(contextMenu.node)">
          <Edit3 class="w-3.5 h-3.5" :stroke-width="1.75" /> Bearbeiten
        </button>
        <button class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2" @click="duplicateNode(contextMenu.node)">
          <Copy class="w-3.5 h-3.5" :stroke-width="1.75" /> Duplizieren
        </button>
        <hr class="my-1 border-[var(--color-border)]" />
        <button class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2" @click="moveNodeUp(contextMenu.node)">
          <ChevronUp class="w-3.5 h-3.5" :stroke-width="1.75" /> Nach oben
        </button>
        <button class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2" @click="moveNodeDown(contextMenu.node)">
          <ChevronDown class="w-3.5 h-3.5" :stroke-width="1.75" /> Nach unten
        </button>
        <hr v-if="authStore.hasPermission('hierarchy-nodes.delete')" class="my-1 border-[var(--color-border)]" />
        <button v-if="authStore.hasPermission('hierarchy-nodes.delete')" class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2 text-[var(--color-error)]" @click="requestDeleteNode(contextMenu.node)">
          <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" /> Löschen
        </button>
      </div>
    </Teleport>

    <!-- Right: Node detail -->
    <div class="flex-1 pim-card overflow-y-auto">
      <div v-if="store.selectedNode" class="p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-[var(--color-text-primary)]">
            {{ localizedName(store.selectedNode) || store.selectedNode.name }}
          </h3>
          <div v-if="authStore.hasPermission('hierarchy-nodes.edit')" class="flex items-center gap-1">
            <button v-if="authStore.hasPermission('hierarchy-nodes.create')" class="pim-btn pim-btn-ghost p-1.5" title="Unterknoten erstellen" @click="createChildNode(store.selectedNode)">
              <FolderPlus class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost p-1.5" title="Knoten duplizieren" @click="duplicateNode(store.selectedNode)">
              <Copy class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost p-1.5" title="Knoten bearbeiten" @click="editNode(store.selectedNode)">
              <Edit3 class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost p-1.5" title="Nach oben" @click="moveNodeUp(store.selectedNode)">
              <ChevronUp class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost p-1.5" title="Nach unten" @click="moveNodeDown(store.selectedNode)">
              <ChevronDown class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button v-if="authStore.hasPermission('hierarchy-nodes.delete')" class="pim-btn pim-btn-ghost p-1.5 text-[var(--color-error)]" title="Knoten löschen" @click="requestDeleteNode(store.selectedNode)">
              <Trash2 class="w-4 h-4" :stroke-width="1.75" />
            </button>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Ebene</span>
            <p class="font-mono text-xs">{{ store.selectedNode.depth ?? '—' }}</p>
          </div>
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Reihenfolge</span>
            <p class="font-mono text-xs">{{ store.selectedNode.sort_order ?? 0 }}</p>
          </div>
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Produkte</span>
            <p>{{ nodeProductCount || store.selectedNode.product_count || 0 }}</p>
          </div>
        </div>

        <!-- Tabs: Produkte / Medien / Attribute -->
        <div class="border-t border-[var(--color-border)] pt-3">
          <div class="flex items-center gap-0 border-b border-[var(--color-border)] mb-4">
            <button
              v-for="tab in [
                { key: 'products', label: 'Produkte', icon: Package },
                { key: 'media', label: 'Medien', icon: Image },
                { key: 'attributes', label: 'Attribute', icon: Layers },
              ]"
              :key="tab.key"
              :class="[
                'flex items-center gap-1.5 px-3 py-2 text-xs font-medium transition-colors border-b-2 -mb-px',
                activeNodeTab === tab.key
                  ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
                  : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]',
              ]"
              @click="activeNodeTab = tab.key"
            >
              <component :is="tab.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
              {{ tab.label }}
            </button>
          </div>

          <!-- Tab: Produkte -->
          <HierarchyNodeProducts
            v-if="activeNodeTab === 'products'"
            :nodeId="store.selectedNode.id"
            :isMasterHierarchy="store.isMasterHierarchy"
            :hierarchyId="selectedHierarchyId"
            :hasEditPermission="authStore.hasPermission('hierarchies.edit')"
            @feedback="showFeedback"
            @loaded="({ count }) => nodeProductCount = count"
          />

          <!-- Tab: Medien -->
          <div v-if="activeNodeTab === 'media'">
            <!-- Ordner-Inhalt (nur Asset-Hierarchien): Medien mit asset_folder_id = dieser Knoten,
                 unabhaengig von expliziten Zuordnungen weiter unten -->
            <template v-if="store.currentHierarchy?.hierarchy_type === 'asset'">
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">
                  {{ t('Medien in diesem Ordner') }}
                  <span v-if="folderMediaItems.length > 0" class="text-[11px] text-[var(--color-text-tertiary)] ml-1">({{ folderMediaItems.length }})</span>
                </h4>
              </div>

              <div v-if="folderMediaLoading" class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                <div v-for="i in 3" :key="i" class="pim-skeleton aspect-square rounded-lg" />
              </div>
              <div v-else-if="folderMediaItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                <div v-for="m in folderMediaItems" :key="m.id" class="pim-card overflow-hidden group relative">
                  <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
                    <img :src="m.thumb_url || m.file_url" class="w-full h-full object-cover" loading="lazy" alt="" />
                  </div>
                  <div class="p-1.5">
                    <span class="text-[10px] text-[var(--color-text-primary)] truncate block">{{ m.file_name || '—' }}</span>
                  </div>
                </div>
              </div>
              <p v-else class="text-xs text-[var(--color-text-tertiary)] mb-4">{{ t('Keine Medien in diesem Ordner') }}</p>
            </template>

            <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">
              {{ t('Zugeordnete Medien') }}
              <span v-if="nodeMediaItems.length > 0" class="text-[11px] text-[var(--color-text-tertiary)] ml-1">({{ nodeMediaItems.length }})</span>
            </h4>
            <button v-if="authStore.hasPermission('hierarchies.edit')" class="pim-btn pim-btn-secondary text-xs" @click="openMediaPicker">
              <Plus class="w-3 h-3" :stroke-width="2" /> Zuordnen
            </button>
          </div>

          <div v-if="nodeMediaLoading" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div v-for="i in 3" :key="i" class="pim-skeleton aspect-square rounded-lg" />
          </div>

          <div v-else-if="nodeMediaItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div v-for="m in nodeMediaItems" :key="m.id" class="pim-card overflow-hidden group relative">
              <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
                <PdfPreview v-if="isNodeMediaPdf(m)" :url="getNodeMediaUrl(m)" :media-id="m.media_id || m.id" :title="m.file_name || ''" max-height="100%" />
                <img v-else :src="getNodeMediaUrl(m)" class="w-full h-full object-cover" loading="lazy" alt="" />
              </div>
              <div class="p-1.5">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] text-[var(--color-text-primary)] truncate flex-1">{{ m.file_name || '—' }}</span>
                  <button
                    v-if="authStore.hasPermission('hierarchies.edit')"
                    class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors"
                    @click="detachNodeMedia(m)"
                  >
                    <X class="w-3 h-3" :stroke-width="2" />
                  </button>
                </div>
                <span v-if="m.usage_type" class="text-[9px] text-[var(--color-text-tertiary)]">{{ localizedName(m.usage_type) || '' }}</span>
              </div>
            </div>
          </div>

            <p v-else class="text-xs text-[var(--color-text-tertiary)]">{{ t('Keine Medien zugeordnet') }}</p>

            <!-- Hierarchy-level Attribute Values -->
            <div v-if="hierarchyLevelAttrs.length > 0" class="border-t border-[var(--color-border)] pt-4 mt-4">
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">Hierarchie-Attribute</h4>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="hierarchyAttrSaving"
              @click="saveHierarchyAttrValues"
            >
              <Save class="w-3 h-3" :stroke-width="2" />
              {{ hierarchyAttrSaving ? t('Speichern…') : t('Speichern') }}
            </button>
          </div>
          <div class="space-y-3">
            <div v-for="attr in hierarchyLevelAttrs" :key="attr.id">
              <label class="block text-[12px] text-[var(--color-text-secondary)] mb-1">
                {{ localizedName(attr) || attr.technical_name }}
                <span class="text-[10px] text-[var(--color-text-tertiary)] ml-1">{{ attr.data_type }}</span>
              </label>
              <PimAttributeInput
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="hierarchyAttrValues[attr.id] ?? null"
                @update:modelValue="hierarchyAttrValues[attr.id] = $event"
                :options="attr.value_list?.entries || []"
                size="sm"
              />
            </div>
          </div>
          </div>
          </div><!-- /Tab: Medien -->

          <!-- Tab: Attribute -->
          <div v-if="activeNodeTab === 'attributes'">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">Zugeordnete Attribute</h4>
            <button v-if="authStore.hasPermission('hierarchies.edit')" class="pim-btn pim-btn-secondary text-xs" @click="showAttrPicker = !showAttrPicker">
              <Plus class="w-3 h-3" :stroke-width="2" /> Attribut zuordnen
            </button>
          </div>

          <!-- Attribute picker (searchable + paginated) -->
          <div v-if="showAttrPicker" class="mb-3 p-3 bg-[var(--color-bg)] rounded-lg space-y-2">
            <div class="relative">
              <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
              <input
                v-model="attrPickerSearch"
                class="pim-input text-xs w-full pim-input-icon"
                placeholder="Attribut suchen (Name, Technischer Name)…"
                @input="onAttrPickerSearchInput"
                @keyup.escape="showAttrPicker = false"
              />
            </div>
            <div v-if="attrPickerLoading" class="space-y-1">
              <div v-for="i in 4" :key="i" class="pim-skeleton h-7 rounded" />
            </div>
            <div v-else-if="attrPickerItems.length > 0" class="max-h-64 overflow-y-auto space-y-1">
              <div
                v-for="attr in attrPickerItems"
                :key="attr.id"
                class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-[var(--color-surface)] cursor-pointer"
                @click="assignAttribute(attr)"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="text-xs font-medium truncate">{{ localizedName(attr) || attr.technical_name }}</span>
                  <span v-if="attr.name_de && attr.technical_name" class="text-[10px] text-[var(--color-text-tertiary)] font-mono truncate">{{ attr.technical_name }}</span>
                </div>
                <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0 ml-2">{{ attr.data_type }}</span>
              </div>
            </div>
            <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute gefunden</p>
            <!-- Pagination -->
            <div v-if="attrPickerMeta.last_page > 1" class="flex items-center justify-between pt-1 border-t border-[var(--color-border)]">
              <span class="text-[11px] text-[var(--color-text-tertiary)]">
                Seite {{ attrPickerMeta.current_page }} von {{ attrPickerMeta.last_page }}
                ({{ attrPickerMeta.total }} gesamt)
              </span>
              <div class="flex items-center gap-1">
                <button
                  class="pim-btn pim-btn-ghost p-1 text-xs disabled:opacity-30"
                  :disabled="attrPickerMeta.current_page <= 1"
                  @click="attrPickerPrevPage"
                >
                  <ChevronUp class="w-3.5 h-3.5 -rotate-90" :stroke-width="2" />
                </button>
                <button
                  class="pim-btn pim-btn-ghost p-1 text-xs disabled:opacity-30"
                  :disabled="attrPickerMeta.current_page >= attrPickerMeta.last_page"
                  @click="attrPickerNextPage"
                >
                  <ChevronDown class="w-3.5 h-3.5 -rotate-90" :stroke-width="2" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="nodeAttrsLoading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="pim-skeleton h-8 rounded" />
          </div>
          <template v-else-if="nodeAttributes.length > 0">
            <!-- Search within assigned attributes (shown when >10) -->
            <div v-if="nodeAttributes.length > 10" class="mb-2">
              <div class="relative">
                <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                <input
                  v-model="nodeAttrSearch"
                  class="pim-input text-xs w-full pim-input-icon"
                  placeholder="Zugeordnete Attribute filtern…"
                />
              </div>
              <p v-if="nodeAttrSearch && filteredNodeAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] mt-1">Keine Treffer</p>
            </div>
            <VueDraggable
              v-model="nodeAttributes"
              handle=".drag-handle"
              :disabled="!authStore.hasPermission('hierarchies.edit') || !!nodeAttrSearch"
              ghost-class="opacity-30"
              class="space-y-1"
              @end="persistAttributeOrder"
            >
              <div
                v-for="(assignment, idx) in filteredNodeAttributes"
                :key="assignment.id"
                :class="['flex items-center justify-between px-3 py-2 rounded-lg group', assignment.is_inherited ? 'bg-[var(--color-bg)] opacity-75 border border-dashed border-[var(--color-border)]' : 'bg-[var(--color-bg)]']"
              >
                <div class="flex items-center gap-2 flex-wrap">
                  <GripVertical v-if="!assignment.is_inherited" class="drag-handle w-3 h-3 text-[var(--color-text-tertiary)] opacity-40 cursor-grab active:cursor-grabbing" />
                  <input
                    v-if="editingSortAssignmentId === assignment.id && !assignment.is_inherited"
                    v-model="editingSortValue"
                    type="number" min="1" :max="nodeAttributes.length"
                    class="w-8 h-5 text-[10px] font-mono text-center pim-input py-0 px-0.5"
                    @keyup.enter="applySortValue()"
                    @keyup.escape="editingSortAssignmentId = null; editingSortIdx = null"
                    @blur="applySortValue()"
                    autofocus
                  />
                  <span
                    v-else
                    class="text-[10px] text-[var(--color-text-tertiary)] font-mono w-5 text-center rounded px-0.5"
                    :class="!assignment.is_inherited ? 'cursor-pointer hover:bg-[var(--color-surface)]' : ''"
                    :title="!assignment.is_inherited ? t('Klick = Nummer ändern') : ''"
                    @click="!assignment.is_inherited && startEditSort(assignment, idx)"
                  >{{ idx + 1 }}</span>
                  <span class="text-xs font-medium">{{ localizedName(assignment.attribute) || assignment.attribute?.technical_name || '—' }}</span>
                  <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ assignment.attribute?.data_type }}</span>
                  <span v-if="assignment.collection_name" class="text-[10px] bg-[var(--color-surface)] text-[var(--color-text-secondary)] px-1.5 py-0.5 rounded">{{ assignment.collection_name }}</span>
                  <span v-if="assignment.is_inherited" class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium" :title="assignment.inherited_from_node_name ? t('Vererbt von: {name}', { name: assignment.inherited_from_node_name }) : t('Vererbt vom übergeordneten Knoten')">
                    {{ assignment.inherited_from_node_name ? t('vererbt von {name}', { name: assignment.inherited_from_node_name }) : t('vererbt') }}
                  </span>
                  <span v-if="assignment.attribute?.data_type === 'Composite'" class="text-[10px] text-[var(--color-accent)]">
                    ({{ nodeAttributes.filter(a => a.attribute?.parent_attribute_id === (assignment.attribute?.id || assignment.attribute_id)).length }} Felder)
                  </span>
                  <template v-if="!assignment.is_inherited">
                    <label v-if="authStore.hasPermission('hierarchies.edit')" class="flex items-center gap-1 ml-2 cursor-pointer" @click.stop>
                      <input
                        type="checkbox"
                        :checked="assignment.is_required || assignment.attribute?.is_mandatory"
                        :disabled="assignment.attribute?.is_mandatory"
                        class="w-3 h-3 rounded border-[var(--color-border)] text-[var(--color-error)] accent-[var(--color-error)] disabled:opacity-60"
                        @change="toggleRequired(assignment)"
                      />
                      <span class="text-[10px] text-[var(--color-text-tertiary)]">Pflicht</span>
                      <span v-if="assignment.attribute?.is_mandatory" class="text-[10px] text-[var(--color-text-tertiary)]">(global)</span>
                    </label>
                    <span v-else-if="assignment.is_required || assignment.attribute?.is_mandatory" class="text-[10px] text-[var(--color-error)] font-medium ml-2">Pflicht</span>
                  </template>
                  <span v-else-if="assignment.is_required || assignment.attribute?.is_mandatory" class="text-[10px] text-[var(--color-error)] font-medium ml-2">Pflicht</span>
                </div>
                <div v-if="!assignment.is_inherited" class="flex items-center gap-0.5">
                  <template v-if="authStore.hasPermission('hierarchies.edit')">
                    <button
                      v-if="store.currentHierarchy?.hierarchy_type === 'asset'"
                      class="flex items-center gap-1 px-1.5 py-0.5 mr-1 text-[10px] font-medium rounded-full transition-colors cursor-pointer"
                      :class="assignment.is_facet
                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'
                        : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)]'"
                      @click="toggleNodeFacet(assignment)"
                      title="Als Facette in der Asset-Facettensuche verwenden (klicken zum Umschalten)"
                    >
                      <Tag class="w-3 h-3" :stroke-width="2" />
                      Facette
                    </button>
                    <button
                      class="p-0.5 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] hover:bg-[var(--color-bg-secondary)] transition-all disabled:opacity-20 disabled:cursor-default"
                      :disabled="idx === 0"
                      @click="moveAttributeUp(idx)"
                      title="Nach oben"
                    >
                      <ChevronUp class="w-3.5 h-3.5" :stroke-width="2" />
                    </button>
                    <button
                      class="p-0.5 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] hover:bg-[var(--color-bg-secondary)] transition-all disabled:opacity-20 disabled:cursor-default"
                      :disabled="idx === nodeAttributes.length - 1"
                      @click="moveAttributeDown(idx)"
                      title="Nach unten"
                    >
                      <ChevronDown class="w-3.5 h-3.5" :stroke-width="2" />
                    </button>
                    <button
                      class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-all ml-1"
                      @click="removeNodeAttribute(assignment)"
                      title="Entfernen"
                    >
                      <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                    </button>
                  </template>
                </div>
              </div>
            </VueDraggable>
            <p v-if="nodeAttributes.length > 0" class="text-[11px] text-[var(--color-text-tertiary)] mt-1">
              {{ nodeAttributes.filter(a => !a.is_inherited).length }} direkt zugeordnet<template v-if="nodeAttributes.some(a => a.is_inherited)">, {{ nodeAttributes.filter(a => a.is_inherited).length }} vererbt</template>
            </p>
          </template>
          <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute zugeordnet</p>

          <!-- Node Attribute Values (only for access_hierarchy = 'editable') -->
          <div v-if="nodeAttributes.some(a => a.access_hierarchy === 'editable')" class="border-t border-[var(--color-border)] pt-4 mt-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">Attributwerte</h4>
            <button
              v-if="authStore.hasPermission('hierarchies.edit')"
              class="pim-btn pim-btn-primary text-xs"
              :disabled="nodeAttrValuesSaving"
              @click="saveNodeAttrValues"
            >
              <Save class="w-3 h-3" :stroke-width="2" />
              {{ nodeAttrValuesSaving ? t('Speichern…') : t('Speichern') }}
            </button>
          </div>
          <div class="space-y-3">
            <template v-for="assignment in filteredNodeAttributes" :key="'val-' + assignment.id">
              <div v-if="assignment.attribute?.data_type !== 'Composite' && assignment.access_hierarchy === 'editable'">
                <label class="block text-[12px] text-[var(--color-text-secondary)] mb-1">
                  {{ localizedName(assignment.attribute) || assignment.attribute?.technical_name }}
                  <span class="text-[10px] text-[var(--color-text-tertiary)] ml-1">{{ assignment.attribute?.data_type }}</span>
                </label>
                <PimAttributeInput
                  :type="mapDataTypeToInput(assignment.attribute?.data_type)"
                  :modelValue="nodeAttrValues[assignment.attribute?.id] ?? null"
                  @update:modelValue="nodeAttrValues[assignment.attribute?.id] = $event"
                  :options="(assignment.attribute?.value_list?.entries || []).map(e => ({ value: e.id, label: localizedName(e, 'display_value') || e.code }))"
                  :disabled="!authStore.hasPermission('hierarchies.edit')"
                  size="sm"
                />
              </div>
            </template>
          </div>
        </div>
          </div><!-- /Tab: Attribute -->
        </div><!-- /Tabs Container -->
        </div><!-- /Node Sections -->
      <div v-else class="flex items-center justify-center h-full">
        <p class="text-sm text-[var(--color-text-tertiary)]">Knoten auswählen</p>
      </div>
    </div>

    <!-- Delete Node Confirm -->
    <PimDeleteConfirmDialog
      :open="!!deleteNodeTarget"
      title="Knoten löschen?"
      :message="t('Der Knoten \'{name}\' und alle Unterknoten werden unwiderruflich gelöscht.', { name: localizedName(deleteNodeTarget) || '' })"
      :loading="nodeDeleting"
      entityType="hierarchy-nodes"
      :entityId="deleteNodeTarget?.id"
      @confirm="confirmDeleteNode"
      @cancel="deleteNodeTarget = null"
    />

    <!-- Delete Hierarchy Confirm -->
    <PimDeleteConfirmDialog
      :open="!!deleteHierarchyTarget"
      title="Hierarchie löschen?"
      :message="t('Die Hierarchie \'{name}\' und alle zugehörigen Knoten werden unwiderruflich gelöscht.', { name: localizedName(deleteHierarchyTarget) || '' })"
      :loading="hierarchyDeleting"
      entityType="hierarchies"
      :entityId="deleteHierarchyTarget?.id"
      @confirm="confirmDeleteHierarchy"
      @cancel="deleteHierarchyTarget = null"
    />

    <!-- Media Picker Dialog -->
    <MediaPickerDialog
      v-model="showMediaPicker"
      :usageTypes="availableUsageTypes"
      :excludeMediaIds="nodeMediaItems.map(m => m.media_id)"
      @select="onMediaSelected"
      @select-multiple="onMediaSelectedBulk"
    />

    <!-- Action Feedback Toast -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="actionFeedback"
          :class="[
            'fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] px-4 py-2 rounded-lg shadow-lg text-sm font-medium',
            actionFeedback.type === 'error'
              ? 'bg-[var(--color-error)] text-white'
              : 'bg-[var(--color-success)] text-white',
          ]"
        >
          {{ actionFeedback.msg }}
        </div>
      </transition>
    </Teleport>
  </div>
</template>
