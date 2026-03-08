<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Upload, Image, Grid, List, Trash2, FolderOpen, FolderPlus, Search, X, Plus, MoveRight, CheckSquare } from 'lucide-vue-next'
import mediaApi from '@/api/media'
import hierarchiesApi from '@/api/hierarchies'
import { useAuthStore } from '@/stores/auth'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import PimTree from '@/components/shared/PimTree.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'

const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)
const viewMode = ref('grid')
const deleteTarget = ref(null)
const deleting = ref(false)
const searchTerm = ref('')
const selectedFolderId = ref(null)
const usagePurposeFilter = ref('')
const detailItem = ref(null)
const detailOpen = ref(false)

// Selection & move
const selectedIds = ref(new Set())
const showMoveDialog = ref(false)
const moveFolderId = ref(null)
const moving = ref(false)

// Folders
const folders = ref([])
const assetHierarchyId = ref(null)
const foldersLoading = ref(false)
const expandedFolders = ref(new Set())
const newFolderName = ref('')
const showNewFolder = ref(false)
const newFolderParent = ref(null)

// Folder context menu
const contextMenu = ref({ show: false, x: 0, y: 0, node: null })

// Asset attributes (from hierarchy node)
const assetAttributes = ref([])
const assetAttributeValues = ref({})
const assetAttrsLoading = ref(false)

// Build filter options
const filterOptions = computed(() => {
  const opts = { perPage: 50, sort: 'created_at', order: 'desc' }
  const filters = {}
  if (selectedFolderId.value) filters.asset_folder_id = selectedFolderId.value
  if (usagePurposeFilter.value) filters.usage_purpose = usagePurposeFilter.value
  if (Object.keys(filters).length) opts.filters = filters
  if (searchTerm.value) opts.search = searchTerm.value
  return opts
})

async function fetchMedia() {
  loading.value = true
  try {
    const { data } = await mediaApi.list(filterOptions.value)
    items.value = data.data || data
  } finally {
    loading.value = false
  }
}

async function fetchFolders() {
  foldersLoading.value = true
  try {
    const { data } = await hierarchiesApi.list({ filters: { hierarchy_type: 'asset' } })
    const hierarchies = data.data || data
    if (hierarchies.length > 0) {
      assetHierarchyId.value = hierarchies[0].id
      const treeRes = await hierarchiesApi.getTree(hierarchies[0].id)
      folders.value = treeRes.data.data || treeRes.data || []
    }
  } catch (e) {
    console.error('Failed to load asset folders:', e)
  } finally {
    foldersLoading.value = false
  }
}

async function handleUpload(e) {
  for (const file of e.target.files) {
    const metadata = {}
    if (selectedFolderId.value) metadata.asset_folder_id = selectedFolderId.value
    await mediaApi.upload(file, metadata)
  }
  e.target.value = ''
  await fetchMedia()
}

function handleDrop(e) {
  e.preventDefault()
  const files = e.dataTransfer?.files
  if (files?.length) {
    const fakeEvent = { target: { files, value: '' } }
    handleUpload(fakeEvent)
  }
}

function getImageUrl(item) {
  if (item.thumb_url) return item.thumb_url
  if (item.file_name) return mediaApi.fileUrl(item.file_name)
  return item.url || ''
}

function handleImgError(e) {
  const img = e.target
  const originalSrc = img.dataset.fallback
  if (originalSrc && img.src !== originalSrc) {
    img.dataset.fallback = ''
    img.src = originalSrc
  }
}

// Folder deletion
const deleteFolderTarget = ref(null)
const deletingFolder = ref(false)

async function confirmDeleteFolder() {
  if (!deleteFolderTarget.value) return
  deletingFolder.value = true
  const folderId = deleteFolderTarget.value.id
  try {
    await hierarchiesApi.deleteNode(folderId)
    if (selectedFolderId.value === folderId) {
      selectedFolderId.value = null
    }
    deleteFolderTarget.value = null
    await fetchFolders()
    await fetchMedia()
  } catch (e) {
    console.error('Failed to delete folder:', e)
  } finally {
    deletingFolder.value = false
  }
}

// ─── PimTree event handlers ─────────────────────────
function handleTreeSelect(node) {
  selectedFolderId.value = selectedFolderId.value === node.id ? null : node.id
}

function handleTreeToggle(nodeId) {
  if (expandedFolders.value.has(nodeId)) expandedFolders.value.delete(nodeId)
  else expandedFolders.value.add(nodeId)
}

function handleTreeContextMenu(event, node) {
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

function handleDocClick() {
  if (contextMenu.value.show) closeContextMenu()
}

// ─── Asset attribute helpers ────────────────────────
function mapDataTypeToInput(dataType) {
  return {
    String: 'text', Number: 'number', Float: 'decimal', Date: 'date',
    Flag: 'boolean', Selection: 'select', Dictionary: 'dictionary', RichText: 'richtext',
  }[dataType] || 'text'
}

function resolveValueFromEntry(entry, attribute) {
  if (!entry) return null
  switch (attribute.data_type) {
    case 'Number': case 'Float': return entry.value_number
    case 'Date': return entry.value_date
    case 'Flag': return entry.value_flag
    case 'Selection': case 'Dictionary': return entry.value_selection_id || entry.value_string
    default: return entry.value_string
  }
}

async function loadAssetAttributes(mediaItem) {
  assetAttributes.value = []
  assetAttributeValues.value = {}
  if (!mediaItem?.asset_folder_id) return

  assetAttrsLoading.value = true
  try {
    // Load attribute definitions from hierarchy node and current values in parallel
    const [attrsRes, valsRes] = await Promise.all([
      hierarchiesApi.getNodeAttributes(mediaItem.asset_folder_id),
      mediaApi.getAttributeValues(mediaItem.id),
    ])
    const assignments = attrsRes.data.data || attrsRes.data || []
    assetAttributes.value = assignments

    // Build values map from existing attribute values
    const values = valsRes.data.data || valsRes.data || []
    const valMap = {}
    for (const val of values) {
      const attr = assignments.find(a => (a.attribute?.id || a.attribute_id) === val.attribute_id)
      if (attr) {
        valMap[val.attribute_id] = resolveValueFromEntry(val, attr.attribute || {})
      }
    }
    assetAttributeValues.value = valMap
  } catch (e) {
    console.error('Failed to load asset attributes:', e)
  } finally {
    assetAttrsLoading.value = false
  }
}

async function saveAssetAttributeValues() {
  if (!detailItem.value || assetAttributes.value.length === 0) return

  const valuesArray = []
  for (const assignment of assetAttributes.value) {
    const attrId = assignment.attribute?.id || assignment.attribute_id
    const value = assetAttributeValues.value[attrId]
    if (value !== undefined && value !== null && value !== '') {
      valuesArray.push({
        attribute_id: attrId,
        value,
      })
    }
  }

  if (valuesArray.length > 0) {
    await mediaApi.updateAttributeValues(detailItem.value.id, valuesArray)
  }
}

function openDetail(item) {
  detailItem.value = item
  detailOpen.value = true
  loadAssetAttributes(item)
}

function closeDetail() {
  detailOpen.value = false
  detailItem.value = null
  assetAttributes.value = []
  assetAttributeValues.value = {}
}

async function saveDetail() {
  if (!detailItem.value) return
  await mediaApi.update(detailItem.value.id, {
    title_de: detailItem.value.title_de,
    title_en: detailItem.value.title_en,
    description_de: detailItem.value.description_de,
    alt_text_de: detailItem.value.alt_text_de,
    usage_purpose: detailItem.value.usage_purpose,
    asset_folder_id: detailItem.value.asset_folder_id,
  })
  await saveAssetAttributeValues()
  closeDetail()
  await fetchMedia()
}

async function confirmDelete() {
  deleting.value = true
  try {
    await mediaApi.delete(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchMedia()
  } finally { deleting.value = false }
}

async function createFolder() {
  if (!newFolderName.value.trim() || !assetHierarchyId.value) return
  try {
    await hierarchiesApi.createNode(assetHierarchyId.value, {
      name_de: newFolderName.value.trim(),
      parent_node_id: newFolderParent.value || null,
    })
    newFolderName.value = ''
    showNewFolder.value = false
    newFolderParent.value = null
    await fetchFolders()
  } catch (e) {
    console.error('Failed to create folder:', e)
  }
}

// ─── Selection & Move helpers ────────────────────────
const allSelected = computed(() => items.value.length > 0 && items.value.every(i => selectedIds.value.has(i.id)))

function toggleSelect(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
  // trigger reactivity
  selectedIds.value = new Set(selectedIds.value)
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(items.value.map(i => i.id))
  }
}

function clearSelection() {
  selectedIds.value = new Set()
}

function flattenFolders(nodes, depth = 0) {
  const result = []
  for (const node of nodes) {
    result.push({ id: node.id, name_de: node.name_de, depth })
    if (node.children?.length) {
      result.push(...flattenFolders(node.children, depth + 1))
    }
  }
  return result
}

const flatFolderList = computed(() => flattenFolders(folders.value))

async function moveSelectedToFolder() {
  if (selectedIds.value.size === 0) return
  moving.value = true
  try {
    await mediaApi.bulkMove([...selectedIds.value], moveFolderId.value)
    showMoveDialog.value = false
    moveFolderId.value = null
    clearSelection()
    await fetchMedia()
  } catch (e) {
    console.error('Failed to move media:', e)
  } finally {
    moving.value = false
  }
}

function contextCreateSubfolder() {
  if (!contextMenu.value.node) return
  showNewFolder.value = true
  newFolderParent.value = contextMenu.value.node.id
  closeContextMenu()
}

function contextDeleteFolder() {
  if (!contextMenu.value.node) return
  deleteFolderTarget.value = contextMenu.value.node
  closeContextMenu()
}

let debounceTimer = null
watch(searchTerm, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchMedia(), 300)
})
onUnmounted(() => {
  clearTimeout(debounceTimer)
  document.removeEventListener('click', handleDocClick, true)
})
watch(usagePurposeFilter, () => { clearSelection(); fetchMedia() })
watch(selectedFolderId, () => { clearSelection(); fetchMedia() })

onMounted(() => {
  fetchMedia()
  fetchFolders()
  document.addEventListener('click', handleDocClick, true)
})
</script>

<template>
  <div class="flex gap-4 h-full">
    <!-- Folder Sidebar -->
    <div class="w-56 flex-none pim-card p-3 space-y-2 self-start">
      <div class="flex items-center justify-between">
        <h3 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">Ordner</h3>
        <button
          v-if="assetHierarchyId && authStore.hasPermission('media.create')"
          class="pim-btn pim-btn-ghost p-0.5"
          @click="showNewFolder = !showNewFolder; newFolderParent = null"
          title="Neuer Ordner"
        >
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>

      <!-- New folder input -->
      <div v-if="showNewFolder" class="flex gap-1">
        <input
          v-model="newFolderName"
          class="pim-input text-xs flex-1"
          placeholder="Ordnername…"
          @keyup.enter="createFolder"
        />
        <button class="pim-btn pim-btn-primary pim-btn-xs" @click="createFolder">OK</button>
      </div>

      <!-- All items -->
      <button
        class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-xs transition-colors"
        :class="!selectedFolderId ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]' : 'hover:bg-[var(--color-bg)]'"
        @click="selectedFolderId = null"
      >
        <FolderOpen class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span>Alle Medien</span>
      </button>

      <!-- Folder tree (PimTree) -->
      <div v-if="foldersLoading" class="space-y-1 px-1">
        <div v-for="i in 4" :key="i" class="pim-skeleton h-5 rounded" :style="{ width: (50 + Math.random() * 40) + '%' }" />
      </div>
      <PimTree
        v-else-if="folders.length > 0"
        :nodes="folders"
        :selectedId="selectedFolderId"
        :expandedIds="expandedFolders"
        :draggable="false"
        @select="handleTreeSelect"
        @toggle="handleTreeToggle"
        @context-menu="handleTreeContextMenu"
      />
    </div>

    <!-- Context Menu -->
    <Teleport to="body">
      <div
        v-if="contextMenu.show && authStore.hasPermission('media.create')"
        class="fixed z-50 min-w-[170px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 text-[13px]"
        :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
        @click.stop
      >
        <button class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2" @click="contextCreateSubfolder">
          <FolderPlus class="w-3.5 h-3.5" :stroke-width="1.75" /> Unterordner erstellen
        </button>
        <hr class="my-1 border-[var(--color-border)]" />
        <button class="w-full text-left px-3 py-1.5 hover:bg-[var(--color-bg)] flex items-center gap-2 text-[var(--color-error)]" @click="contextDeleteFolder">
          <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" /> Löschen
        </button>
      </div>
    </Teleport>

    <!-- Main content -->
    <div class="flex-1 space-y-4" @dragover.prevent @drop="handleDrop">
      <!-- Header -->
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Medien</h2>
        <div class="flex items-center gap-2">
          <!-- Search -->
          <div class="relative">
            <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)] z-10 pointer-events-none" />
            <input
              v-model="searchTerm"
              class="pim-input text-xs pl-7 w-48"
              placeholder="Suchen…"
            />
          </div>

          <!-- Usage filter -->
          <select v-model="usagePurposeFilter" class="pim-select text-xs">
            <option value="">Alle</option>
            <option value="print">Print</option>
            <option value="web">Web</option>
            <option value="both">Print & Web</option>
          </select>

          <button :class="['pim-btn pim-btn-ghost p-1.5', viewMode==='grid'?'bg-[var(--color-bg)]':'']" @click="viewMode='grid'"><Grid class="w-4 h-4" :stroke-width="1.75" /></button>
          <button :class="['pim-btn pim-btn-ghost p-1.5', viewMode==='list'?'bg-[var(--color-bg)]':'']" @click="viewMode='list'"><List class="w-4 h-4" :stroke-width="1.75" /></button>
          <template v-if="authStore.hasPermission('media.create')">
            <input type="file" accept="image/*,application/pdf,.doc,.docx,.xlsx" multiple class="hidden" id="media-upload" @change="handleUpload" />
            <label for="media-upload" class="pim-btn pim-btn-primary text-sm cursor-pointer"><Upload class="w-4 h-4" :stroke-width="2" /> Hochladen</label>
          </template>
        </div>
      </div>

      <!-- Selection toolbar -->
      <Transition name="slide-down">
        <div v-if="selectedIds.size > 0" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)] text-sm">
          <CheckSquare class="w-4 h-4 text-[var(--color-text-secondary)]" :stroke-width="2" />
          <span class="text-[var(--color-text-primary)] font-medium">{{ selectedIds.size }} ausgewählt</span>
          <button
            v-if="authStore.hasPermission('media.edit')"
            class="pim-btn pim-btn-primary pim-btn-sm flex items-center gap-1.5"
            @click="showMoveDialog = true"
          >
            <MoveRight class="w-3.5 h-3.5" :stroke-width="2" />
            In Ordner verschieben
          </button>
          <button class="pim-btn pim-btn-ghost pim-btn-sm ml-auto" @click="clearSelection">
            <X class="w-3.5 h-3.5" :stroke-width="2" />
            Auswahl aufheben
          </button>
        </div>
      </Transition>

      <!-- Loading -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <div v-for="i in 10" :key="i" class="pim-skeleton aspect-square rounded-lg" />
      </div>

      <!-- Grid -->
      <div v-else-if="items.length > 0 && viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <div v-for="item in items" :key="item.id" class="pim-card overflow-hidden group cursor-pointer hover:shadow-md transition-shadow relative" @click="openDetail(item)">
          <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden relative">
            <input
              type="checkbox"
              :checked="selectedIds.has(item.id)"
              class="absolute top-2 left-2 z-10 w-4 h-4 accent-[var(--color-primary)] cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity"
              :class="{ '!opacity-100': selectedIds.has(item.id) }"
              @click.stop="toggleSelect(item.id)"
            />
            <img v-if="item.media_type === 'image'" :src="getImageUrl(item)" :data-fallback="item.url || mediaApi.fileUrl(item.file_name)" class="w-full h-full object-cover" loading="lazy" alt="" @error="handleImgError" />
            <Image v-else class="w-8 h-8 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
          </div>
          <div class="p-2 flex items-center justify-between">
            <div class="flex-1 min-w-0">
              <p class="text-[11px] text-[var(--color-text-primary)] truncate">{{ item.title_de || item.file_name || '—' }}</p>
              <div class="flex items-center gap-1 mt-0.5">
                <span class="text-[9px] px-1 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">{{ item.media_type }}</span>
                <span v-if="item.usage_purpose && item.usage_purpose !== 'both'" class="text-[9px] px-1 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">{{ item.usage_purpose }}</span>
              </div>
            </div>
            <button
              v-if="authStore.hasPermission('media.delete')"
              class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-all shrink-0"
              @click.stop="deleteTarget = item"
            >
              <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
          </div>
        </div>
      </div>

      <!-- List view -->
      <div v-else-if="items.length > 0 && viewMode === 'list'" class="space-y-1">
        <!-- Select-all header -->
        <div class="flex items-center gap-3 px-2 py-1.5 text-[10px] font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
          <input
            type="checkbox"
            :checked="allSelected"
            class="w-4 h-4 flex-none accent-[var(--color-primary)] cursor-pointer"
            @change="toggleSelectAll"
          />
          <span>Alle auswählen</span>
        </div>
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-center gap-3 p-2 pim-card cursor-pointer hover:shadow-sm transition-shadow"
          :class="{ 'ring-1 ring-[var(--color-border)] bg-[var(--color-bg)]': selectedIds.has(item.id) }"
          @click="openDetail(item)"
        >
          <input
            type="checkbox"
            :checked="selectedIds.has(item.id)"
            class="w-4 h-4 flex-none accent-[var(--color-primary)] cursor-pointer"
            @click.stop="toggleSelect(item.id)"
          />
          <div class="w-10 h-10 flex-none rounded bg-[var(--color-bg)] overflow-hidden flex items-center justify-center">
            <img v-if="item.media_type === 'image'" :src="getImageUrl(item)" :data-fallback="item.url || mediaApi.fileUrl(item.file_name)" class="w-full h-full object-cover" loading="lazy" alt="" @error="handleImgError" />
            <Image v-else class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs text-[var(--color-text-primary)] truncate">{{ item.title_de || item.file_name }}</p>
            <p class="text-[10px] text-[var(--color-text-tertiary)]">{{ item.file_name }}</p>
          </div>
          <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ item.media_type }}</span>
          <span v-if="item.usage_purpose" class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">{{ item.usage_purpose }}</span>
          <button
            v-if="authStore.hasPermission('media.delete')"
            class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-all"
            @click.stop="deleteTarget = item"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </div>

      <!-- Empty -->
      <div v-else class="pim-card p-12 text-center">
        <Image class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Medien vorhanden</p>
        <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Dateien hierhin ziehen oder "Hochladen" klicken</p>
      </div>
    </div>

    <!-- Detail Slide-over -->
    <Transition name="slide">
      <div v-if="detailOpen && detailItem" class="w-80 flex-none border-l border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-4 overflow-y-auto">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Details</h3>
          <button class="pim-btn pim-btn-ghost p-1" @click="closeDetail"><X class="w-4 h-4" /></button>
        </div>

        <!-- Preview -->
        <div class="aspect-square rounded-lg bg-[var(--color-bg)] overflow-hidden flex items-center justify-center">
          <img v-if="detailItem.media_type === 'image'" :src="getImageUrl(detailItem)" class="w-full h-full object-contain" />
          <Image v-else class="w-12 h-12 text-[var(--color-text-tertiary)]" />
        </div>

        <!-- Editable fields -->
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Dateiname</label>
            <p class="text-xs font-mono text-[var(--color-text-primary)]">{{ detailItem.file_name }}</p>
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Titel (DE)</label>
            <input v-model="detailItem.title_de" class="pim-input text-xs w-full" />
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Titel (EN)</label>
            <input v-model="detailItem.title_en" class="pim-input text-xs w-full" />
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Beschreibung</label>
            <textarea v-model="detailItem.description_de" rows="2" class="pim-input text-xs w-full"></textarea>
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Alt-Text</label>
            <input v-model="detailItem.alt_text_de" class="pim-input text-xs w-full" />
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Verwendungszweck</label>
            <select v-model="detailItem.usage_purpose" class="pim-select text-xs w-full">
              <option value="both">Print & Web</option>
              <option value="print">Print</option>
              <option value="web">Web</option>
            </select>
          </div>
          <div v-if="detailItem.width && detailItem.height">
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Abmessungen</label>
            <p class="text-xs text-[var(--color-text-primary)]">{{ detailItem.width }} × {{ detailItem.height }} px</p>
          </div>
        </div>

        <!-- Asset Attributes (from hierarchy node) -->
        <div v-if="assetAttrsLoading" class="border-t border-[var(--color-border)] pt-3 space-y-2">
          <div v-for="i in 2" :key="i" class="pim-skeleton h-12 rounded" />
        </div>
        <div v-else-if="assetAttributes.length > 0" class="border-t border-[var(--color-border)] pt-3 space-y-3">
          <h4 class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">Attribute</h4>
          <div v-for="assignment in assetAttributes" :key="assignment.id">
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">
              {{ assignment.attribute?.name_de || assignment.attribute?.technical_name }}
            </label>
            <PimAttributeInput
              :type="mapDataTypeToInput(assignment.attribute?.data_type)"
              :modelValue="assetAttributeValues[assignment.attribute?.id || assignment.attribute_id]"
              :options="(assignment.attribute?.value_list?.entries || []).map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code }))"
              @update:modelValue="assetAttributeValues[assignment.attribute?.id || assignment.attribute_id] = $event"
            />
          </div>
        </div>

        <div class="flex gap-2">
          <button v-if="authStore.hasPermission('media.edit')" class="pim-btn pim-btn-primary text-xs flex-1" @click="saveDetail">Speichern</button>
          <button v-if="authStore.hasPermission('media.delete')" class="pim-btn pim-btn-ghost text-xs" @click="deleteTarget = detailItem; closeDetail()">
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </Transition>

    <!-- Move to folder dialog -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showMoveDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showMoveDialog = false">
          <div class="bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-sm p-5 space-y-4">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
              {{ selectedIds.size }} Medium{{ selectedIds.size > 1 ? ' Assets' : '' }} verschieben
            </h3>
            <div>
              <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Zielordner</label>
              <select v-model="moveFolderId" class="pim-select text-xs w-full">
                <option :value="null">— Kein Ordner (Stammverzeichnis) —</option>
                <option v-for="f in flatFolderList" :key="f.id" :value="f.id">
                  {{ '  '.repeat(f.depth) }}{{ f.depth > 0 ? '└ ' : '' }}{{ f.name_de }}
                </option>
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button class="pim-btn pim-btn-ghost text-xs" @click="showMoveDialog = false">Abbrechen</button>
              <button class="pim-btn pim-btn-primary text-xs flex items-center gap-1.5" :disabled="moving" @click="moveSelectedToFolder">
                <MoveRight v-if="!moving" class="w-3.5 h-3.5" :stroke-width="2" />
                {{ moving ? 'Verschiebe…' : 'Verschieben' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Medium löschen?"
      :message="`Die Datei '${deleteTarget?.file_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
    <PimConfirmDialog
      :open="!!deleteFolderTarget"
      title="Ordner löschen?"
      :message="`Der Ordner '${deleteFolderTarget?.name_de || ''}' und alle Unterordner werden gelöscht. Medien bleiben erhalten.`"
      :loading="deletingFolder"
      @confirm="confirmDeleteFolder"
      @cancel="deleteFolderTarget = null"
    />
  </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from,
.slide-leave-to { opacity: 0; transform: translateX(20px); }

.slide-down-enter-active,
.slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from,
.slide-down-leave-to { opacity: 0; transform: translateY(-8px); }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
