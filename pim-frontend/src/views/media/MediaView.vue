<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Upload, Image, Grid, List, Trash2, FolderOpen, Folder, Search, Edit3, X, ChevronRight, ChevronDown, Plus } from 'lucide-vue-next'
import mediaApi from '@/api/media'
import hierarchiesApi from '@/api/hierarchies'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'

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

// Folders
const folders = ref([])
const assetHierarchyId = ref(null)
const foldersLoading = ref(false)
const expandedFolders = ref(new Set())
const newFolderName = ref('')
const showNewFolder = ref(false)
const newFolderParent = ref(null)

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
  // On thumbnail error, try the original file URL
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
  try {
    await hierarchiesApi.deleteNode(deleteFolderTarget.value.id)
    deleteFolderTarget.value = null
    if (selectedFolderId.value === deleteFolderTarget.value?.id) {
      selectedFolderId.value = null
    }
    await fetchFolders()
    await fetchMedia()
  } catch (e) {
    console.error('Failed to delete folder:', e)
  } finally {
    deletingFolder.value = false
  }
}

function openDetail(item) {
  detailItem.value = item
  detailOpen.value = true
}

function closeDetail() {
  detailOpen.value = false
  detailItem.value = null
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

function selectFolder(id) {
  selectedFolderId.value = selectedFolderId.value === id ? null : id
}

function toggleExpand(id) {
  if (expandedFolders.value.has(id)) expandedFolders.value.delete(id)
  else expandedFolders.value.add(id)
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

let debounceTimer = null
watch(searchTerm, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchMedia(), 300)
})
onUnmounted(() => clearTimeout(debounceTimer))
watch(usagePurposeFilter, () => fetchMedia())
watch(selectedFolderId, () => fetchMedia())

onMounted(() => {
  fetchMedia()
  fetchFolders()
})
</script>

<template>
  <div class="flex gap-4 h-full">
    <!-- Folder Sidebar -->
    <div class="w-56 flex-none space-y-2">
      <div class="flex items-center justify-between">
        <h3 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">Ordner</h3>
        <button
          v-if="assetHierarchyId"
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
        @click="selectFolder(null)"
      >
        <FolderOpen class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span>Alle Medien</span>
      </button>

      <!-- Folder tree -->
      <div v-for="node in folders" :key="node.id" class="space-y-0.5">
        <MediaFolderItem
          :node="node"
          :depth="0"
          :selected-id="selectedFolderId"
          :expanded="expandedFolders"
          @select="selectFolder"
          @toggle="toggleExpand"
          @delete="n => deleteFolderTarget = n"
          @add-sub="id => { showNewFolder = true; newFolderParent = id }"
        />
      </div>
    </div>

    <!-- Main content -->
    <div class="flex-1 space-y-4" @dragover.prevent @drop="handleDrop">
      <!-- Header -->
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Medien</h2>
        <div class="flex items-center gap-2">
          <!-- Search -->
          <div class="relative">
            <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
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
          <input type="file" accept="image/*,application/pdf,.doc,.docx,.xlsx" multiple class="hidden" id="media-upload" @change="handleUpload" />
          <label for="media-upload" class="pim-btn pim-btn-primary text-sm cursor-pointer"><Upload class="w-4 h-4" :stroke-width="2" /> Hochladen</label>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <div v-for="i in 10" :key="i" class="pim-skeleton aspect-square rounded-lg" />
      </div>

      <!-- Grid -->
      <div v-else-if="items.length > 0 && viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <div v-for="item in items" :key="item.id" class="pim-card overflow-hidden group cursor-pointer hover:shadow-md transition-shadow relative" @click="openDetail(item)">
          <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
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
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-center gap-3 p-2 pim-card cursor-pointer hover:shadow-sm transition-shadow"
          @click="openDetail(item)"
        >
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

        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs flex-1" @click="saveDetail">Speichern</button>
          <button class="pim-btn pim-btn-ghost text-xs" @click="deleteTarget = detailItem; closeDetail()">
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </Transition>

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

<script>
// Recursive folder item
const MediaFolderItem = {
  name: 'MediaFolderItem',
  props: { node: Object, depth: Number, selectedId: String, expanded: Object },
  emits: ['select', 'toggle', 'delete', 'add-sub'],
  setup(props, { emit }) {
    const hasChildren = props.node.children && props.node.children.length > 0
    return { hasChildren, emit }
  },
  template: `
    <div>
      <div
        class="group flex items-center gap-0.5 pr-1 rounded text-xs transition-colors"
        :class="selectedId === node.id ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]' : 'hover:bg-[var(--color-bg)]'"
      >
        <button
          class="flex-1 flex items-center gap-1.5 py-1 min-w-0"
          :style="{ paddingLeft: (depth * 12 + 8) + 'px' }"
          @click="emit('select', node.id)"
        >
          <button v-if="hasChildren" class="flex-none w-3 h-3" @click.stop="emit('toggle', node.id)">
            <svg v-if="expanded.has(node.id)" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            <svg v-else class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
          </button>
          <span v-else class="w-3"></span>
          <svg class="w-3.5 h-3.5 flex-none text-[var(--color-text-tertiary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <span class="truncate">{{ node.name_de || node.name }}</span>
        </button>
        <button
          class="opacity-0 group-hover:opacity-100 flex-none p-0.5 rounded hover:bg-[var(--color-primary-light)]"
          title="Unterordner erstellen"
          @click.stop="emit('add-sub', node.id)"
        >
          <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        </button>
        <button
          class="opacity-0 group-hover:opacity-100 flex-none p-0.5 rounded hover:bg-[var(--color-error-light)] hover:text-[var(--color-error)]"
          title="Ordner löschen"
          @click.stop="emit('delete', node)"
        >
          <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </button>
      </div>
      <div v-if="hasChildren && expanded.has(node.id)">
        <MediaFolderItem
          v-for="child in node.children"
          :key="child.id"
          :node="child"
          :depth="depth + 1"
          :selected-id="selectedId"
          :expanded="expanded"
          @select="(id) => emit('select', id)"
          @toggle="(id) => emit('toggle', id)"
          @delete="(n) => emit('delete', n)"
          @add-sub="(id) => emit('add-sub', id)"
        />
      </div>
    </div>
  `,
}

export default {
  components: { MediaFolderItem },
}
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from,
.slide-leave-to { opacity: 0; transform: translateX(20px); }
</style>
