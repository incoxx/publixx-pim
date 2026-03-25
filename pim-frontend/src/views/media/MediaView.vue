<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Upload, Image, Grid, List, Trash2, FolderOpen, FolderPlus, Search, X, Plus, MoveRight, CheckSquare, Link, FileSpreadsheet, FileText, Wand2, Loader2, ChevronLeft, ChevronRight, Download, Copy, History } from 'lucide-vue-next'
import mediaApi from '@/api/media'
import { mediaUsageTypes as mediaUsageTypesApi } from '@/api/mediaUsageTypes'
import hierarchiesApi from '@/api/hierarchies'
import { useAuthStore } from '@/stores/auth'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import PimTree from '@/components/shared/PimTree.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import PdfPreview from '@/components/shared/PdfPreview.vue'
import MediaUploadQueue from '@/components/media/MediaUploadQueue.vue'
import MediaProcessingStatus from '@/components/media/MediaProcessingStatus.vue'

const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)
const sidebarOpen = ref(false)
const viewMode = ref('grid')
const deleteTarget = ref(null)
const deleting = ref(false)
const searchTerm = ref('')
const selectedFolderId = ref(null)
const usagePurposeFilter = ref('')
const detailItem = ref(null)
const detailOpen = ref(false)

// Pagination
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

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
  const opts = { perPage: 50, sort: 'created_at', order: 'desc', page: currentPage.value }
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
    if (data.meta) {
      currentPage.value = data.meta.current_page || 1
      totalPages.value = data.meta.last_page || 1
      totalItems.value = data.meta.total || 0
    }
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
  fetchMedia()
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

const uploading = ref(false)
const uploadError = ref('')
const uploadQueueRef = ref(null)
const processingStatusRef = ref(null)

// Bulk-Delete
const showBulkDeleteConfirm = ref(false)
const bulkDeleting = ref(false)

// Revisions im Detail-Panel
const detailRevisions = ref([])
const detailRevisionsLoading = ref(false)
const detailTab = ref('info')

async function handleUpload(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return
  if (e.target?.value !== undefined) e.target.value = ''

  // Dateien an Upload-Queue delegieren
  if (uploadQueueRef.value) {
    uploadQueueRef.value.addFiles(files)
  }
}

function handleDrop(e) {
  e.preventDefault()
  const files = e.dataTransfer?.files
  if (files?.length && uploadQueueRef.value) {
    uploadQueueRef.value.addFiles(files)
  }
}

function onUploadCompleted() {
  fetchMedia()
  // Hintergrund-Status aktualisieren (PDF-Jobs etc.)
  processingStatusRef.value?.refresh()
}

async function bulkDeleteSelected() {
  if (selectedIds.value.size === 0) return
  bulkDeleting.value = true
  try {
    await mediaApi.bulkDelete([...selectedIds.value])
    showBulkDeleteConfirm.value = false
    clearSelection()
    await fetchMedia()
  } catch (err) {
    uploadError.value = err.response?.data?.message || err.message || 'Löschen fehlgeschlagen'
  } finally {
    bulkDeleting.value = false
  }
}

async function loadRevisions(mediaId) {
  detailRevisions.value = []
  detailRevisionsLoading.value = true
  try {
    const { data } = await mediaApi.revisions(mediaId)
    detailRevisions.value = data.data || data || []
  } catch (e) {
    console.error('Failed to load revisions:', e)
  } finally {
    detailRevisionsLoading.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function getImageUrl(item) {
  if (item.thumb_url) return item.thumb_url
  if (item.file_name) return mediaApi.fileUrl(item.file_name)
  return item.url || ''
}

function isItemPdf(item) {
  const mime = item.mime_type || ''
  if (mime.includes('pdf')) return true
  return (item.file_name || '').toLowerCase().endsWith('.pdf')
}

function getFileUrl(item) {
  if (item.file_name) return mediaApi.fileUrl(item.file_name)
  return item.url || ''
}

const copiedUrl = ref(false)
async function copyAssetUrl(item) {
  const url = new URL(getFileUrl(item), window.location.origin).href
  try {
    await navigator.clipboard.writeText(url)
    copiedUrl.value = true
    setTimeout(() => { copiedUrl.value = false }, 2000)
  } catch {
    // Fallback
    const ta = document.createElement('textarea')
    ta.value = url
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    copiedUrl.value = true
    setTimeout(() => { copiedUrl.value = false }, 2000)
  }
}

function handleImgError(e) {
  const img = e.target
  const originalSrc = img.dataset.fallback
  if (originalSrc && img.src !== originalSrc) {
    img.dataset.fallback = ''
    img.src = originalSrc
  } else {
    // Alle Fallbacks fehlgeschlagen → Placeholder anzeigen
    img.style.display = 'none'
    const placeholder = img.parentElement?.querySelector('.img-fallback')
    if (placeholder) placeholder.style.display = 'flex'
  }
}

// Folder deletion
const deleteFolderTarget = ref(null)
const deletingFolder = ref(false)

async function confirmDeleteFolder({ force } = {}) {
  if (!deleteFolderTarget.value) return
  deletingFolder.value = true
  const folderId = deleteFolderTarget.value.id
  try {
    await hierarchiesApi.deleteNode(folderId, { force })
    if (selectedFolderId.value === folderId) {
      selectedFolderId.value = null
    }
    deleteFolderTarget.value = null
    await fetchFolders()
    await fetchMedia()
  } catch (e) {
    console.error('Failed to delete folder:', e)
    uploadError.value = e.response?.data?.title || e.response?.data?.message || 'Ordner konnte nicht gelöscht werden'
  } finally {
    deletingFolder.value = false
  }
}

// ─── PimTree event handlers ─────────────────────────
function handleTreeSelect(node) {
  selectedFolderId.value = selectedFolderId.value === node.id ? null : node.id
  sidebarOpen.value = false
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
    Hyperlink: 'hyperlink', ImageLink: 'imagelink', PdfLink: 'pdflink', VideoLink: 'videolink',
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
  copiedUrl.value = false
  saveError.value = ''
  detailTab.value = 'info'
  loadAssetAttributes(item)
  if (item.revision_count > 0) {
    loadRevisions(item.id)
  } else {
    detailRevisions.value = []
  }
}

function closeDetail() {
  detailOpen.value = false
  detailItem.value = null
  assetAttributes.value = []
  assetAttributeValues.value = {}
}

const saveError = ref('')

async function saveDetail() {
  if (!detailItem.value) return
  saveError.value = ''
  try {
    await mediaApi.update(detailItem.value.id, {
      title_de: detailItem.value.title_de,
      title_en: detailItem.value.title_en,
      description_de: detailItem.value.description_de,
      description_en: detailItem.value.description_en,
      alt_text_de: detailItem.value.alt_text_de,
      usage_purpose: detailItem.value.usage_purpose,
      asset_folder_id: detailItem.value.asset_folder_id,
      media_type: detailItem.value.media_type,
    })
    await saveAssetAttributeValues()
    closeDetail()
    await fetchMedia()
  } catch (err) {
    saveError.value = err.response?.data?.message || err.message || 'Speichern fehlgeschlagen'
  }
}

const deleteError = ref('')

async function confirmDelete({ force } = {}) {
  deleting.value = true
  deleteError.value = ''
  try {
    await mediaApi.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchMedia()
  } catch (err) {
    deleteError.value = err.response?.data?.message || err.message || 'Löschen fehlgeschlagen'
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
    uploadError.value = e.response?.data?.message || 'Ordner konnte nicht erstellt werden'
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

// ─── Usage Types ────────────────────────
const usageTypes = ref([])
async function fetchUsageTypes() {
  try {
    const { data } = await mediaUsageTypesApi.list()
    usageTypes.value = data.data || data || []
  } catch (e) { console.warn('Failed to load usage types:', e.message) }
}

// ─── URL Import ────────────────────────
const showUrlImport = ref(false)
const urlImportForm = ref({ url: '', usage_type_id: null, usage_purpose: 'both' })
const urlImporting = ref(false)
const urlImportError = ref(null)

async function importFromUrl() {
  if (!urlImportForm.value.url) return
  urlImporting.value = true
  urlImportError.value = null
  try {
    await mediaApi.importFromUrl(urlImportForm.value.url, {
      usage_type_id: urlImportForm.value.usage_type_id || undefined,
      usage_purpose: urlImportForm.value.usage_purpose,
      asset_folder_id: selectedFolderId.value || undefined,
    })
    showUrlImport.value = false
    urlImportForm.value = { url: '', usage_type_id: null, usage_purpose: 'both' }
    await fetchMedia()
  } catch (e) {
    urlImportError.value = e.response?.data?.message || e.message
  } finally { urlImporting.value = false }
}

// ─── Bulk URL Import ────────────────────────
const showBulkImport = ref(false)
const bulkImportFile = ref(null)
const bulkImportForm = ref({ usage_type_id: null, usage_purpose: 'both' })
const bulkImporting = ref(false)
const bulkImportResult = ref(null)
const bulkImportError = ref(null)

function handleBulkFile(e) {
  bulkImportFile.value = e.target.files?.[0] || null
}

async function executeBulkImport() {
  if (!bulkImportFile.value) return
  bulkImporting.value = true
  bulkImportResult.value = null
  bulkImportError.value = null
  try {
    const { data } = await mediaApi.bulkImportFromUrls(bulkImportFile.value, {
      usage_type_id: bulkImportForm.value.usage_type_id || undefined,
      usage_purpose: bulkImportForm.value.usage_purpose,
      asset_folder_id: selectedFolderId.value || undefined,
    })
    bulkImportResult.value = data
    await fetchMedia()
  } catch (e) {
    bulkImportError.value = e.response?.data?.message || e.message
  } finally { bulkImporting.value = false }
}

function closeBulkImport() {
  showBulkImport.value = false
  bulkImportFile.value = null
  bulkImportResult.value = null
  bulkImportError.value = null
}

// ─── Auto-Match ────────────────────────
const showAutoMatch = ref(false)
const autoMatchForm = ref({ pattern: '/^(.+?)(?:_\\d+)?$/', usage_type_id: null, dry_run: true })
const autoMatching = ref(false)
const autoMatchResult = ref(null)
const autoMatchError = ref(null)

async function executeAutoMatch() {
  if (!autoMatchForm.value.pattern) return
  autoMatching.value = true
  autoMatchResult.value = null
  autoMatchError.value = null
  try {
    const { data } = await mediaApi.autoMatch(autoMatchForm.value.pattern, {
      usage_type_id: autoMatchForm.value.usage_type_id || undefined,
      dry_run: autoMatchForm.value.dry_run,
    })
    autoMatchResult.value = data
    if (!autoMatchForm.value.dry_run && data.matched > 0) {
      await fetchMedia()
    }
  } catch (e) {
    autoMatchError.value = e.response?.data?.message || e.message
  } finally { autoMatching.value = false }
}

function closeAutoMatch() {
  showAutoMatch.value = false
  autoMatchResult.value = null
  autoMatchError.value = null
  autoMatchForm.value = { pattern: '/^(.+?)(?:_\\d+)?$/', usage_type_id: null, dry_run: true }
}

let debounceTimer = null
watch(searchTerm, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { currentPage.value = 1; fetchMedia() }, 300)
})
onUnmounted(() => {
  clearTimeout(debounceTimer)
  document.removeEventListener('click', handleDocClick, true)
})
watch(usagePurposeFilter, () => { clearSelection(); currentPage.value = 1; fetchMedia() })
watch(selectedFolderId, () => { clearSelection(); currentPage.value = 1; fetchMedia() })

onMounted(() => {
  fetchMedia()
  fetchFolders()
  fetchUsageTypes()
  document.addEventListener('click', handleDocClick, true)
})
</script>

<template>
  <div class="flex gap-4 h-full relative">
    <!-- Mobile sidebar toggle -->
    <button class="lg:hidden fixed bottom-4 left-4 z-40 pim-btn pim-btn-primary rounded-full w-12 h-12 shadow-lg flex items-center justify-center" @click="sidebarOpen = !sidebarOpen">
      <FolderOpen class="w-5 h-5" />
    </button>

    <!-- Sidebar backdrop (mobile) -->
    <div v-if="sidebarOpen" class="lg:hidden fixed inset-0 z-40 bg-black/40" @click="sidebarOpen = false" />

    <!-- Folder Sidebar -->
    <div
      class="w-56 flex-none pim-card p-3 space-y-2 self-start transition-transform duration-200
             max-lg:fixed max-lg:inset-y-0 max-lg:left-0 max-lg:z-50 max-lg:w-64 max-lg:rounded-none max-lg:shadow-xl max-lg:overflow-y-auto"
      :class="sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full'"
    >
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
        :class="!selectedFolderId ? 'bg-[var(--color-bg)] text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
        @click="selectedFolderId = null; sidebarOpen = false"
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
      <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-3">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Medien</h2>
        <div class="flex flex-wrap items-center gap-2">
          <!-- Search -->
          <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)] z-10 pointer-events-none" />
            <input
              v-model="searchTerm"
              class="pim-input text-xs pim-input-icon w-36 sm:w-48"
              placeholder="Suchen…"
            />
          </div>

          <!-- Usage filter -->
          <select v-model="usagePurposeFilter" class="pim-select text-xs max-sm:hidden">
            <option value="">Alle</option>
            <option value="print">Print</option>
            <option value="web">Web</option>
            <option value="both">Print & Web</option>
          </select>

          <button :class="['pim-btn pim-btn-ghost p-1.5', viewMode==='grid'?'bg-[var(--color-bg)]':'']" @click="viewMode='grid'"><Grid class="w-4 h-4" :stroke-width="1.75" /></button>
          <button :class="['pim-btn pim-btn-ghost p-1.5', viewMode==='list'?'bg-[var(--color-bg)]':'']" @click="viewMode='list'"><List class="w-4 h-4" :stroke-width="1.75" /></button>
          <template v-if="authStore.hasPermission('media.create')">
            <button class="pim-btn pim-btn-ghost text-xs max-sm:hidden" @click="showAutoMatch = true" title="Auto-Match: Dateinamen → SKU">
              <Wand2 class="w-4 h-4" :stroke-width="2" /> <span class="max-md:hidden">Auto-Match</span>
            </button>
            <button class="pim-btn pim-btn-ghost text-xs max-sm:hidden" @click="showBulkImport = true" title="Bulk-Import über Excel">
              <FileSpreadsheet class="w-4 h-4" :stroke-width="2" /> <span class="max-md:hidden">Bulk-Import</span>
            </button>
            <button class="pim-btn pim-btn-ghost text-xs max-sm:hidden" @click="showUrlImport = true" title="Import über URL">
              <Link class="w-4 h-4" :stroke-width="2" /> <span class="max-md:hidden">URL-Import</span>
            </button>
            <input type="file" accept="image/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv" multiple class="hidden" id="media-upload" @change="handleUpload" />
            <label for="media-upload" class="pim-btn pim-btn-primary text-sm cursor-pointer" :class="{ 'opacity-50 pointer-events-none': uploading }">
              <Loader2 v-if="uploading" class="w-4 h-4 animate-spin" :stroke-width="2" />
              <Upload v-else class="w-4 h-4" :stroke-width="2" />
              <span class="max-sm:hidden">{{ uploading ? 'Wird hochgeladen...' : 'Hochladen' }}</span>
            </label>
          </template>
        </div>
      </div>

      <!-- Upload error -->
      <div v-if="uploadError" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-error-light,#fef2f2)] border border-[var(--color-error,#ef4444)]/20 text-sm text-[var(--color-error,#ef4444)]">
        <span class="flex-1">{{ uploadError }}</span>
        <button class="p-0.5 hover:opacity-70" @click="uploadError = ''"><X class="w-4 h-4" /></button>
      </div>

      <!-- Hintergrund-Verarbeitungsstatus (PDF-Jobs etc.) -->
      <MediaProcessingStatus ref="processingStatusRef" />

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
          <button
            v-if="authStore.hasPermission('media.delete')"
            class="pim-btn pim-btn-sm flex items-center gap-1.5 text-[var(--color-error)] border-[var(--color-error)]/30 hover:bg-[var(--color-error)]/10"
            @click="showBulkDeleteConfirm = true"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
            {{ selectedIds.size }} löschen
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
            <template v-if="item.media_type === 'image'">
              <img :src="getImageUrl(item)" :data-fallback="item.url || mediaApi.fileUrl(item.file_name)" class="w-full h-full object-cover" loading="lazy" alt="" @error="handleImgError" />
              <div class="img-fallback items-center justify-center absolute inset-0 bg-[var(--color-bg)]" style="display: none">
                <Image class="w-8 h-8 text-[var(--color-text-tertiary)]/30" :stroke-width="1.5" />
              </div>
            </template>
            <template v-else-if="isItemPdf(item)">
              <img v-if="item.pdf_preview_url" :src="item.pdf_preview_url" class="w-full h-full object-contain" loading="lazy" alt="PDF" @error="(e) => { e.target.style.display = 'none'; e.target.nextElementSibling.style.display = 'flex' }" />
              <div :class="['flex-col items-center gap-1 text-[var(--color-error)]/60', item.pdf_preview_url ? 'absolute inset-0 bg-[var(--color-bg)] justify-center' : 'flex']" :style="item.pdf_preview_url ? 'display: none' : ''">
                <FileText class="w-10 h-10" :stroke-width="1.25" />
                <span class="text-[9px] text-[var(--color-text-tertiary)]">PDF</span>
              </div>
            </template>
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
            <FileText v-else-if="isItemPdf(item)" class="w-5 h-5 text-[var(--color-error)]/60" :stroke-width="1.5" />
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

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between mt-4 px-1">
        <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ totalItems }} Medien</span>
        <div class="flex items-center gap-1">
          <button
            class="p-1.5 rounded hover:bg-[var(--color-bg)] disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <ChevronLeft class="w-4 h-4" :stroke-width="2" />
          </button>
          <template v-for="p in totalPages" :key="p">
            <button
              v-if="p === 1 || p === totalPages || (p >= currentPage - 2 && p <= currentPage + 2)"
              class="min-w-[28px] h-7 rounded text-xs transition-colors"
              :class="p === currentPage ? 'bg-[var(--color-accent)] text-white font-medium' : 'hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]'"
              @click="goToPage(p)"
            >{{ p }}</button>
            <span v-else-if="p === currentPage - 3 || p === currentPage + 3" class="text-xs text-[var(--color-text-tertiary)] px-1">…</span>
          </template>
          <button
            class="p-1.5 rounded hover:bg-[var(--color-bg)] disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
            :disabled="currentPage >= totalPages"
            @click="goToPage(currentPage + 1)"
          >
            <ChevronRight class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>
      </div>
    </div>

    <!-- Detail backdrop (mobile) -->
    <div v-if="detailOpen && detailItem" class="lg:hidden fixed inset-0 z-40 bg-black/40" @click="closeDetail" />

    <!-- Detail Slide-over -->
    <Transition name="slide">
      <div v-if="detailOpen && detailItem" class="w-80 flex-none border-l border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-4 overflow-y-auto
               max-lg:fixed max-lg:inset-y-0 max-lg:right-0 max-lg:z-50 max-lg:w-[85vw] max-lg:max-w-sm max-lg:shadow-xl max-lg:border-l">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Details</h3>
          <button class="pim-btn pim-btn-ghost p-1" @click="closeDetail"><X class="w-4 h-4" /></button>
        </div>

        <!-- Preview -->
        <div class="aspect-square rounded-lg bg-[var(--color-bg)] overflow-hidden flex items-center justify-center">
          <img v-if="detailItem.media_type === 'image'" :src="getImageUrl(detailItem)" class="w-full h-full object-contain" />
          <PdfPreview v-else-if="isItemPdf(detailItem)" :url="getFileUrl(detailItem)" :media-id="detailItem.id" :title="detailItem.file_name || 'PDF'" max-height="100%" />
          <Image v-else class="w-12 h-12 text-[var(--color-text-tertiary)]" />
        </div>

        <!-- Download / Copy URL -->
        <div class="flex gap-2">
          <a :href="getFileUrl(detailItem)" :download="detailItem.file_name" class="pim-btn pim-btn-ghost text-xs flex-1 justify-center">
            <Download class="w-3.5 h-3.5" :stroke-width="2" /> Download
          </a>
          <button class="pim-btn pim-btn-ghost text-xs flex-1 justify-center" @click="copyAssetUrl(detailItem)">
            <Copy v-if="!copiedUrl" class="w-3.5 h-3.5" :stroke-width="2" /> {{ copiedUrl ? 'Kopiert!' : 'URL kopieren' }}
          </button>
        </div>

        <!-- Editable fields -->
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Dateiname</label>
            <p class="text-xs font-mono text-[var(--color-text-primary)]">{{ detailItem.file_name }}</p>
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Medientyp</label>
            <select v-model="detailItem.media_type" class="pim-select text-xs w-full">
              <option value="image">image</option>
              <option value="document">document</option>
              <option value="video">video</option>
              <option value="PDF">PDF</option>
              <option value="other">other</option>
            </select>
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
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Beschreibung (DE)</label>
            <textarea v-model="detailItem.description_de" rows="2" class="pim-input text-xs w-full"></textarea>
          </div>
          <div>
            <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Beschreibung (EN)</label>
            <textarea v-model="detailItem.description_en" rows="2" class="pim-input text-xs w-full"></textarea>
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

        <!-- Upload-Info -->
        <div v-if="detailItem.last_uploaded_at" class="border-t border-[var(--color-border)] pt-3">
          <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase">Letzter Upload</label>
          <p class="text-xs text-[var(--color-text-primary)]">{{ formatDate(detailItem.last_uploaded_at) }}</p>
        </div>

        <!-- Revisions-Historie -->
        <div v-if="detailRevisions.length > 0" class="border-t border-[var(--color-border)] pt-3 space-y-2">
          <div class="flex items-center gap-1.5">
            <History class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            <h4 class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">
              Dateiversionen ({{ detailRevisions.length }})
            </h4>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="rev in detailRevisions"
              :key="rev.id"
              class="flex items-center justify-between gap-2 px-2 py-1.5 rounded bg-[var(--color-bg)] text-[10px]"
            >
              <div class="min-w-0">
                <p class="text-[var(--color-text-primary)] font-medium">
                  Rev. {{ rev.revision_number }} — {{ formatDate(rev.replaced_at) }}
                </p>
                <p class="text-[var(--color-text-tertiary)] truncate">
                  {{ rev.replaced_by || 'System' }}
                  <template v-if="rev.file_size"> · {{ (rev.file_size / 1024).toFixed(0) }} KB</template>
                </p>
              </div>
              <a
                :href="rev.download_url"
                class="shrink-0 p-1 rounded hover:bg-[var(--color-surface)] text-[var(--color-text-tertiary)]"
                title="Download"
              >
                <Download class="w-3 h-3" />
              </a>
            </div>
          </div>
        </div>
        <div v-else-if="detailRevisionsLoading" class="border-t border-[var(--color-border)] pt-3">
          <div class="pim-skeleton h-8 rounded" />
        </div>

        <p v-if="saveError" class="text-[11px] text-[var(--color-error,#ef4444)]">{{ saveError }}</p>
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
              {{ selectedIds.size }} {{ selectedIds.size === 1 ? 'Medium' : 'Medien' }} verschieben
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

    <!-- URL Import Dialog -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showUrlImport" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showUrlImport = false">
          <div class="bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
              <Link class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
              Bild über URL importieren
            </h3>
            <div class="space-y-3">
              <div>
                <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Bild-URL</label>
                <input v-model="urlImportForm.url" class="pim-input text-xs w-full" placeholder="https://example.com/bild.jpg" @keyup.enter="importFromUrl" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Bildtyp</label>
                  <select v-model="urlImportForm.usage_type_id" class="pim-select text-xs w-full">
                    <option :value="null">— Kein Typ —</option>
                    <option v-for="ut in usageTypes" :key="ut.id" :value="ut.id">{{ ut.name_de || ut.technical_name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Verwendung</label>
                  <select v-model="urlImportForm.usage_purpose" class="pim-select text-xs w-full">
                    <option value="both">Print & Web</option>
                    <option value="print">Print</option>
                    <option value="web">Web</option>
                  </select>
                </div>
              </div>
            </div>
            <div v-if="urlImportError" class="text-xs text-[var(--color-error)] bg-red-50 dark:bg-red-950/30 rounded p-2">{{ urlImportError }}</div>
            <div class="flex justify-end gap-2">
              <button class="pim-btn pim-btn-ghost text-xs" @click="showUrlImport = false">Abbrechen</button>
              <button class="pim-btn pim-btn-primary text-xs flex items-center gap-1.5" :disabled="urlImporting || !urlImportForm.url" @click="importFromUrl">
                <Loader2 v-if="urlImporting" class="w-3.5 h-3.5 animate-spin" />
                <Link v-else class="w-3.5 h-3.5" :stroke-width="2" />
                {{ urlImporting ? 'Importiere…' : 'Importieren' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Bulk Import Dialog -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showBulkImport" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeBulkImport">
          <div class="bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-lg p-5 space-y-4">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
              <FileSpreadsheet class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
              Bulk-Import über Excel
            </h3>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">
              Excel-Datei (.xlsx) mit einer Spalte <strong>"url"</strong> hochladen. Alle Bilder werden heruntergeladen und importiert.
            </p>
            <div class="space-y-3">
              <div>
                <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Excel-Datei</label>
                <input type="file" accept=".xlsx,.xls,.csv" class="pim-input text-xs w-full" @change="handleBulkFile" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Bildtyp</label>
                  <select v-model="bulkImportForm.usage_type_id" class="pim-select text-xs w-full">
                    <option :value="null">— Kein Typ —</option>
                    <option v-for="ut in usageTypes" :key="ut.id" :value="ut.id">{{ ut.name_de || ut.technical_name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Verwendung</label>
                  <select v-model="bulkImportForm.usage_purpose" class="pim-select text-xs w-full">
                    <option value="both">Print & Web</option>
                    <option value="print">Print</option>
                    <option value="web">Web</option>
                  </select>
                </div>
              </div>
            </div>
            <!-- Result -->
            <div v-if="bulkImportResult" class="text-xs rounded p-3 space-y-1" :class="bulkImportResult.failed > 0 ? 'bg-yellow-50 dark:bg-yellow-950/30 border border-yellow-200' : 'bg-green-50 dark:bg-green-950/30 border border-green-200'">
              <p class="font-medium">{{ bulkImportResult.imported }} importiert, {{ bulkImportResult.failed }} fehlgeschlagen</p>
              <div v-if="bulkImportResult.errors?.length" class="mt-1 space-y-0.5 max-h-32 overflow-y-auto">
                <p v-for="(err, i) in bulkImportResult.errors" :key="i" class="text-[var(--color-error)]">{{ err }}</p>
              </div>
            </div>
            <div v-if="bulkImportError" class="text-xs text-[var(--color-error)] bg-red-50 dark:bg-red-950/30 rounded p-2">{{ bulkImportError }}</div>
            <div class="flex justify-end gap-2">
              <button class="pim-btn pim-btn-ghost text-xs" @click="closeBulkImport">Schließen</button>
              <button class="pim-btn pim-btn-primary text-xs flex items-center gap-1.5" :disabled="bulkImporting || !bulkImportFile" @click="executeBulkImport">
                <Loader2 v-if="bulkImporting" class="w-3.5 h-3.5 animate-spin" />
                <FileSpreadsheet v-else class="w-3.5 h-3.5" :stroke-width="2" />
                {{ bulkImporting ? 'Importiere…' : 'Importieren' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Auto-Match Dialog -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showAutoMatch" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeAutoMatch">
          <div class="bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-xl p-5 space-y-4">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
              <Wand2 class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
              Auto-Match: Dateinamen → SKU
            </h3>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">
              Ordnet Medien automatisch Produkten zu, wenn der Dateiname per Regex zur SKU passt.
              Die erste Capture-Group <code>(…)</code> wird als SKU verwendet.
            </p>
            <div class="space-y-3">
              <div>
                <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Regex-Muster</label>
                <input v-model="autoMatchForm.pattern" class="pim-input text-xs w-full font-mono" placeholder="/^(.+?)(?:_\d+)?$/" />
                <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">Beispiel: <code>/^(.+?)(?:_\d+)?$/</code> extrahiert "ABC123" aus "ABC123_1.jpg"</p>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-[10px] font-medium text-[var(--color-text-secondary)] uppercase block mb-1">Bildtyp</label>
                  <select v-model="autoMatchForm.usage_type_id" class="pim-select text-xs w-full">
                    <option :value="null">— Kein Typ —</option>
                    <option v-for="ut in usageTypes" :key="ut.id" :value="ut.id">{{ ut.name_de || ut.technical_name }}</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <label class="flex items-center gap-2 text-xs cursor-pointer">
                    <input type="checkbox" v-model="autoMatchForm.dry_run" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                    Nur Vorschau (Dry Run)
                  </label>
                </div>
              </div>
            </div>
            <!-- Result -->
            <div v-if="autoMatchResult" class="text-xs rounded p-3 space-y-2 bg-[var(--color-bg)] border border-[var(--color-border)] max-h-64 overflow-y-auto">
              <div class="flex gap-4 font-medium">
                <span class="text-green-600">{{ autoMatchResult.matched }} Treffer</span>
                <span class="text-yellow-600">{{ autoMatchResult.no_match }} ohne SKU</span>
                <span class="text-[var(--color-text-tertiary)]">{{ autoMatchResult.total_media }} Medien gesamt</span>
                <span v-if="autoMatchResult.dry_run" class="text-blue-500 ml-auto">Vorschau</span>
                <span v-else class="text-green-600 ml-auto">Ausgeführt</span>
              </div>
              <div v-if="autoMatchResult.matches?.length" class="space-y-0.5 mt-2">
                <p class="font-medium text-[var(--color-text-secondary)]">Zuordnungen:</p>
                <div v-for="m in autoMatchResult.matches" :key="m.media_id" class="flex gap-2">
                  <span class="text-[var(--color-text-tertiary)] truncate flex-1">{{ m.file_name }}</span>
                  <span class="text-[var(--color-text-secondary)]">→</span>
                  <span class="font-mono text-[var(--color-accent)]">{{ m.sku }}</span>
                </div>
              </div>
              <div v-if="autoMatchResult.unmatched?.length" class="space-y-0.5 mt-2">
                <p class="font-medium text-yellow-600">Nicht zugeordnet:</p>
                <div v-for="u in autoMatchResult.unmatched.slice(0, 10)" :key="u.file_name" class="flex gap-2">
                  <span class="text-[var(--color-text-tertiary)] truncate flex-1">{{ u.file_name }}</span>
                  <span class="text-yellow-600 text-[10px]">{{ u.extracted_sku }} — {{ u.reason }}</span>
                </div>
              </div>
            </div>
            <div v-if="autoMatchError" class="text-xs text-[var(--color-error)] bg-red-50 dark:bg-red-950/30 rounded p-2">{{ autoMatchError }}</div>
            <div class="flex justify-end gap-2">
              <button class="pim-btn pim-btn-ghost text-xs" @click="closeAutoMatch">Schließen</button>
              <button class="pim-btn pim-btn-primary text-xs flex items-center gap-1.5" :disabled="autoMatching || !autoMatchForm.pattern" @click="executeAutoMatch">
                <Loader2 v-if="autoMatching" class="w-3.5 h-3.5 animate-spin" />
                <Wand2 v-else class="w-3.5 h-3.5" :stroke-width="2" />
                {{ autoMatching ? 'Matching…' : (autoMatchForm.dry_run ? 'Vorschau' : 'Ausführen') }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Medium löschen?"
      :message="`Die Datei '${deleteTarget?.file_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      entityType="media"
      :entityId="deleteTarget?.id"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
    <PimDeleteConfirmDialog
      :open="!!deleteFolderTarget"
      title="Ordner löschen?"
      :message="`Der Ordner '${deleteFolderTarget?.name_de || ''}' und alle Unterordner werden gelöscht. Medien bleiben erhalten.`"
      :loading="deletingFolder"
      entityType="hierarchy-nodes"
      :entityId="deleteFolderTarget?.id"
      @confirm="confirmDeleteFolder"
      @cancel="deleteFolderTarget = null"
    />

    <!-- Upload Queue -->
    <MediaUploadQueue
      ref="uploadQueueRef"
      :metadata="selectedFolderId ? { asset_folder_id: selectedFolderId } : {}"
      @completed="onUploadCompleted"
    />

    <!-- Bulk Delete Confirm -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showBulkDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50" @click="showBulkDeleteConfirm = false" />
          <div class="relative bg-[var(--color-surface)] rounded-xl shadow-2xl p-6 max-w-sm w-full space-y-4">
            <h3 class="text-lg font-semibold text-[var(--color-text-primary)]">Medien löschen</h3>
            <p class="text-sm text-[var(--color-text-secondary)]">
              {{ selectedIds.size }} {{ selectedIds.size === 1 ? 'Medium' : 'Medien' }} endgültig löschen?
              Dieser Vorgang kann nicht rückgängig gemacht werden.
            </p>
            <div class="flex justify-end gap-2">
              <button class="pim-btn pim-btn-ghost pim-btn-sm" @click="showBulkDeleteConfirm = false" :disabled="bulkDeleting">
                Abbrechen
              </button>
              <button
                class="pim-btn pim-btn-sm text-white bg-[var(--color-error)] hover:bg-[var(--color-error)]/80"
                @click="bulkDeleteSelected"
                :disabled="bulkDeleting"
              >
                <Loader2 v-if="bulkDeleting" class="w-4 h-4 animate-spin" />
                <Trash2 v-else class="w-4 h-4" />
                Löschen
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
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
