<script setup>
import { ref, computed, watch } from 'vue'
import { X, Search, LayoutGrid, List, ChevronLeft, ChevronRight, Image, FileText, Filter, FolderOpen } from 'lucide-vue-next'
import mediaApi from '@/api/media'
import hierarchiesApi from '@/api/hierarchies'
import ColumnConfigPopover from '@/components/shared/ColumnConfigPopover.vue'
import PimTree from '@/components/shared/PimTree.vue'
import { useColumnConfig } from '@/composables/useColumnConfig'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  usageTypes: { type: Array, default: () => [] },
  selectedUsageTypeId: { type: String, default: null },
  excludeMediaIds: { type: Array, default: () => [] },
  allowedExtensions: { type: Array, default: null },
})

const emit = defineEmits(['update:modelValue', 'select', 'select-multiple', 'update:selectedUsageTypeId'])

// ─── Spalten-Konfiguration ───────────────────────────
const defaultPickerColumns = [
  { key: 'thumb',     label: 'Bild',       sortable: false },
  { key: 'file_name', label: 'Dateiname',  sortable: true, mono: true },
  { key: 'mime_type', label: 'MIME',        sortable: true },
  { key: 'file_size', label: 'Dateigröße', sortable: true },
]
const extraPickerColumns = [
  { key: 'title',         label: 'Titel (DE)',       sortable: true },
  { key: 'media_type',    label: 'Medientyp',        sortable: true },
  { key: 'usage_purpose', label: 'Verwendungszweck', sortable: true },
  { key: 'dimensions',    label: 'Abmessungen',      sortable: false },
  { key: 'alt_text',      label: 'Alt-Text',         sortable: false },
]
const {
  allColumns, visibleColumns, visibleKeys,
  toggleColumn, moveColumn, resetColumns,
} = useColumnConfig('columns:media-picker', defaultPickerColumns, extraPickerColumns)

// ─── Medien laden ────────────────────────────────────
const searchQuery = ref('')
const viewMode = ref('grid')
const loading = ref(false)
const media = ref([])
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = 24
let searchTimer = null

// Spalten-Key → Backend-Filterfeld. file_name/mime_type/title/alt_text werden per
// Präfix-Suche (LIKE 'wert%'), media_type/usage_purpose per Exakt-Match gefiltert
// (siehe MediaController::ALLOWED_FILTERS bzw. ALLOWED_PREFIX_FILTERS).
const QUICK_LOOKUP_FIELD_MAP = {
  file_name: 'file_name',
  mime_type: 'mime_type',
  title: 'title_de',
  media_type: 'media_type',
  usage_purpose: 'usage_purpose',
  alt_text: 'alt_text_de',
}

async function loadMedia(page = 1) {
  loading.value = true
  try {
    const params = { perPage, page }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    const filters = {}
    if (props.allowedExtensions && props.allowedExtensions.length > 0) {
      filters.extensions = props.allowedExtensions.join(',')
    }
    if (selectedFolderId.value) {
      filters.asset_folder_id = selectedFolderId.value
    }
    if (showQuickLookup.value) {
      for (const [colKey, value] of Object.entries(quickLookupFilters.value)) {
        if (value === '' || value == null) continue
        const field = QUICK_LOOKUP_FIELD_MAP[colKey]
        if (!field) continue
        filters[field] = value
      }
    }
    if (Object.keys(filters).length) params.filters = filters
    const { data } = await mediaApi.list(params)
    const items = data.data || data
    media.value = items
    currentPage.value = data.meta?.current_page || page
    totalPages.value = data.meta?.last_page || 1
    totalItems.value = data.meta?.total || items.length
  } catch (e) {
    console.error('Failed to load media:', e.message)
  } finally {
    loading.value = false
  }
}

// ─── Ordner-Sidebar ──────────────────────────────────
const folders = ref([])
const selectedFolderId = ref(null)
const expandedFolders = ref(new Set())
const foldersLoading = ref(false)

async function loadFolders() {
  if (folders.value.length > 0) return
  foldersLoading.value = true
  try {
    const { data } = await hierarchiesApi.list({ filters: { hierarchy_type: 'asset' } })
    const hierarchies = data.data || data
    if (hierarchies.length > 0) {
      const treeRes = await hierarchiesApi.getTree(hierarchies[0].id)
      folders.value = treeRes.data.data || treeRes.data || []
    }
  } catch (e) { console.error('Failed to load folders:', e) }
  finally { foldersLoading.value = false }
}

function handleTreeSelect(node) {
  selectedFolderId.value = selectedFolderId.value === node.id ? null : node.id
}

function handleTreeToggle(nodeId) {
  const s = new Set(expandedFolders.value)
  s.has(nodeId) ? s.delete(nodeId) : s.add(nodeId)
  expandedFolders.value = s
}

watch(selectedFolderId, () => {
  currentPage.value = 1
  clearSelection()
  loadMedia(1)
})

// ─── Multi-Select ────────────────────────────────────
const selectedIds = ref(new Set())

const allSelected = computed(() =>
  filteredMedia.value.length > 0 &&
  filteredMedia.value.every(m => selectedIds.value.has(m.id))
)

function toggleSelect(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
  selectedIds.value = new Set(selectedIds.value)
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(filteredMedia.value.map(m => m.id))
  }
}

function clearSelection() {
  selectedIds.value = new Set()
}

function assignSelected() {
  const items = media.value.filter(m => selectedIds.value.has(m.id))
  emit('select-multiple', items)
  clearSelection()
}

function handleItemClick(item) {
  if (selectedIds.value.size > 0) {
    toggleSelect(item.id)
  } else {
    emit('select', item)
  }
}

// ─── Quick Lookup — filtert serverseitig über die komplette Treffermenge ────
const showQuickLookup = ref(false)
const quickLookupFilters = ref({})

watch(showQuickLookup, (val) => {
  if (!val) quickLookupFilters.value = {}
})

let quickLookupDebounce = null
watch(quickLookupFilters, () => {
  clearTimeout(quickLookupDebounce)
  quickLookupDebounce = setTimeout(() => {
    currentPage.value = 1
    clearSelection()
    loadMedia(1)
  }, 300)
}, { deep: true })

// ─── Filter & Display ────────────────────────────────
// Quick Lookup läuft serverseitig (siehe loadMedia); hier bleibt nur der
// clientseitige Ausschluss bereits zugeordneter Medien (excludeMediaIds).
const filteredMedia = computed(() => {
  if (!props.excludeMediaIds.length) return media.value
  return media.value.filter(m => !props.excludeMediaIds.includes(m.id))
})

// ─── Watchers ────────────────────────────────────────
watch(() => props.modelValue, (open) => {
  if (open) {
    searchQuery.value = ''
    currentPage.value = 1
    clearSelection()
    showQuickLookup.value = false
    quickLookupFilters.value = {}
    loadMedia(1)
    loadFolders()
  }
})

watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    clearSelection()
    loadMedia(1)
  }, 300)
})

watch(() => props.allowedExtensions, () => {
  if (props.modelValue) {
    currentPage.value = 1
    clearSelection()
    loadMedia(1)
  }
})

// ─── Helpers ─────────────────────────────────────────
function close() { emit('update:modelValue', false) }

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  clearSelection()
  loadMedia(page)
}

function isImage(item) { return item.media_type === 'image' }

function getThumbUrl(item) {
  if (item.file_name && isImage(item)) return mediaApi.thumbUrl(item.id, 200, 200)
  return ''
}

function formatFileSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function getExtension(fileName) {
  if (!fileName) return ''
  return fileName.split('.').pop()?.toUpperCase() || ''
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />
        <div class="relative z-10 w-full max-w-[1100px] max-h-[85vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">

          <!-- Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
            <span class="text-sm font-semibold text-[var(--color-text-primary)]">Medien auswählen</span>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <!-- Toolbar -->
          <div class="flex items-center gap-2 px-4 py-2 border-b border-[var(--color-border)] bg-[var(--color-bg)]">
            <div class="relative flex-1">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
              <input v-model="searchQuery" type="text" class="pim-input text-xs pim-input-icon w-full" placeholder="Medien durchsuchen…" />
            </div>
            <select
              v-if="usageTypes.length > 0"
              :value="selectedUsageTypeId"
              @change="emit('update:selectedUsageTypeId', $event.target.value || null)"
              class="pim-select text-xs min-w-[140px]"
            >
              <option v-for="ut in usageTypes" :key="ut.id" :value="ut.id">{{ ut.name_de || ut.technical_name }}</option>
            </select>
            <!-- Quick Lookup Toggle (nur list) -->
            <button
              v-if="viewMode === 'list'"
              :class="['pim-btn pim-btn-ghost p-1.5', showQuickLookup ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)]' : '']"
              @click="showQuickLookup = !showQuickLookup"
              title="Quick Lookup"
            >
              <Filter class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <!-- Spaltenkonfiguration (nur list) -->
            <ColumnConfigPopover
              v-if="viewMode === 'list'"
              :allColumns="allColumns"
              :visibleKeys="visibleKeys"
              @toggle="toggleColumn"
              @move="moveColumn"
              @reset="resetColumns"
            />
            <!-- View toggle -->
            <div class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
              <button
                class="p-1.5 transition-colors"
                :class="viewMode === 'grid' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
                @click="viewMode = 'grid'"
                title="Kachelansicht"
              >
                <LayoutGrid class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <button
                class="p-1.5 transition-colors"
                :class="viewMode === 'list' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
                @click="viewMode = 'list'"
                title="Listenansicht"
              >
                <List class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- Body: Sidebar + Content -->
          <div class="flex flex-1 overflow-hidden min-h-0">
            <!-- Folder Sidebar -->
            <div class="w-48 flex-none border-r border-[var(--color-border)] overflow-y-auto p-3 space-y-2">
              <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-tertiary)]">Ordner</h4>
              <button
                class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-xs transition-colors"
                :class="!selectedFolderId ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)] font-medium' : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
                @click="selectedFolderId = null"
              >
                <FolderOpen class="w-3.5 h-3.5" :stroke-width="2" />
                Alle Medien
              </button>
              <div v-if="foldersLoading" class="space-y-1.5">
                <div v-for="i in 4" :key="i" class="pim-skeleton h-6 rounded" />
              </div>
              <PimTree
                v-else-if="folders.length > 0"
                :nodes="folders"
                :selectedId="selectedFolderId"
                :expandedIds="expandedFolders"
                :draggable="false"
                @select="handleTreeSelect"
                @toggle="handleTreeToggle"
              />
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-4">
              <!-- Loading -->
              <div v-if="loading" :class="viewMode === 'grid' ? 'grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2' : 'space-y-1'">
                <div v-for="i in 12" :key="i" :class="viewMode === 'grid' ? 'pim-skeleton aspect-square rounded' : 'pim-skeleton h-10 rounded'" />
              </div>

              <!-- Empty state -->
              <div v-else-if="filteredMedia.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                <Image class="w-10 h-10 mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
                <p class="text-sm text-[var(--color-text-tertiary)]">
                  {{ searchQuery ? 'Keine Medien gefunden für „' + searchQuery + '"' : 'Keine Medien vorhanden' }}
                </p>
              </div>

              <!-- Grid view -->
              <div v-else-if="viewMode === 'grid'" class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2">
                <div
                  v-for="m in filteredMedia"
                  :key="m.id"
                  class="group relative aspect-square bg-[var(--color-bg)] rounded-lg overflow-hidden cursor-pointer border border-[var(--color-border)] hover:ring-2 hover:ring-[var(--color-accent)] hover:border-[var(--color-accent)] transition-all"
                  :class="{ 'ring-2 ring-[var(--color-accent)] border-[var(--color-accent)]': selectedIds.has(m.id) }"
                  @click="handleItemClick(m)"
                >
                  <!-- Multi-Select Checkbox -->
                  <input
                    type="checkbox"
                    :checked="selectedIds.has(m.id)"
                    class="absolute top-2 left-2 z-20 w-4 h-4 accent-[var(--color-accent)] cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity"
                    :class="{ '!opacity-100': selectedIds.has(m.id) }"
                    @click.stop="toggleSelect(m.id)"
                  />
                  <img
                    v-if="isImage(m)"
                    :src="getThumbUrl(m)"
                    class="w-full h-full object-cover"
                    loading="lazy"
                    alt=""
                  />
                  <div v-else class="w-full h-full flex flex-col items-center justify-center gap-1">
                    <FileText class="w-8 h-8 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
                    <span class="text-[9px] text-[var(--color-text-tertiary)] font-medium">{{ getExtension(m.file_name) }}</span>
                  </div>
                  <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="text-[10px] text-white truncate block">{{ m.file_name }}</span>
                  </div>
                </div>
              </div>

              <!-- List view (dynamische Spalten) -->
              <div v-else class="overflow-x-auto">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="border-b border-[var(--color-border)] bg-[var(--color-bg)]">
                      <th class="px-2 py-2 text-left" style="width:32px">
                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="w-3.5 h-3.5 accent-[var(--color-accent)] cursor-pointer" />
                      </th>
                      <th
                        v-for="col in visibleColumns"
                        :key="col.key"
                        class="px-3 py-2 text-left text-[10px] uppercase tracking-wider text-[var(--color-text-tertiary)] font-medium whitespace-nowrap"
                        :style="col.key === 'thumb' ? 'width:44px' : ''"
                      >
                        {{ col.key === 'thumb' ? '' : col.label }}
                      </th>
                    </tr>
                    <!-- Quick Lookup Filterzeile -->
                    <tr v-if="showQuickLookup" class="border-b border-[var(--color-border)] bg-[var(--color-bg)]/50">
                      <td></td>
                      <td v-for="col in visibleColumns" :key="col.key" class="px-2 py-1">
                        <template v-if="col.key === 'thumb' || col.key === 'dimensions' || col.key === 'file_size'" />
                        <select
                          v-else-if="col.key === 'media_type'"
                          v-model="quickLookupFilters.media_type"
                          class="pim-input text-[11px] w-full py-1 px-1.5"
                        >
                          <option value="">Alle</option>
                          <option value="image">image</option>
                          <option value="document">document</option>
                          <option value="video">video</option>
                        </select>
                        <select
                          v-else-if="col.key === 'usage_purpose'"
                          v-model="quickLookupFilters.usage_purpose"
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
                          v-model="quickLookupFilters[col.key]"
                          type="text"
                          class="pim-input text-[11px] w-full py-1 px-1.5"
                          placeholder="Filtern…"
                        />
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="m in filteredMedia"
                      :key="m.id"
                      class="border-b border-[var(--color-border)] last:border-0 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
                      :class="{ 'bg-[var(--color-accent)]/5': selectedIds.has(m.id) }"
                      @click="handleItemClick(m)"
                    >
                      <td class="px-2 py-1.5" @click.stop>
                        <input type="checkbox" :checked="selectedIds.has(m.id)" @change="toggleSelect(m.id)" class="w-3.5 h-3.5 accent-[var(--color-accent)] cursor-pointer" />
                      </td>
                      <td v-for="col in visibleColumns" :key="col.key" class="px-3 py-1.5">
                        <template v-if="col.key === 'thumb'">
                          <div class="w-8 h-8 rounded bg-[var(--color-bg)] overflow-hidden border border-[var(--color-border)] flex items-center justify-center">
                            <img v-if="isImage(m)" :src="getThumbUrl(m)" class="w-full h-full object-cover" loading="lazy" alt="" />
                            <FileText v-else class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
                          </div>
                        </template>
                        <template v-else-if="col.key === 'file_name'">
                          <span class="text-[var(--color-text-primary)] font-mono text-[11px]">{{ m.file_name || '—' }}</span>
                        </template>
                        <template v-else-if="col.key === 'mime_type'">
                          <span class="text-[var(--color-text-tertiary)]">{{ m.mime_type || '—' }}</span>
                        </template>
                        <template v-else-if="col.key === 'file_size'">
                          <span class="text-[var(--color-text-tertiary)] tabular-nums">{{ formatFileSize(m.file_size) }}</span>
                        </template>
                        <template v-else-if="col.key === 'title'">
                          <span class="text-[var(--color-text-primary)] text-[11px]">{{ m.title_de || '—' }}</span>
                        </template>
                        <template v-else-if="col.key === 'media_type'">
                          <span class="text-[var(--color-text-tertiary)]">{{ m.media_type || '—' }}</span>
                        </template>
                        <template v-else-if="col.key === 'usage_purpose'">
                          <span class="text-[var(--color-text-tertiary)]">{{ m.usage_purpose || '—' }}</span>
                        </template>
                        <template v-else-if="col.key === 'dimensions'">
                          <span v-if="m.width" class="text-[var(--color-text-tertiary)] tabular-nums">{{ m.width }} × {{ m.height }} px</span>
                          <span v-else class="text-[var(--color-text-tertiary)]">—</span>
                        </template>
                        <template v-else-if="col.key === 'alt_text'">
                          <span class="text-[var(--color-text-tertiary)] text-[11px]">{{ m.alt_text_de || '—' }}</span>
                        </template>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between px-4 py-2 border-t border-[var(--color-border)] bg-[var(--color-bg)]">
            <div class="flex items-center gap-3">
              <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ totalItems }} Medien</span>
              <div v-if="totalPages > 1" class="flex items-center gap-1">
                <button class="p-1 rounded hover:bg-[var(--color-surface)] disabled:opacity-30 disabled:cursor-not-allowed transition-colors" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                  <ChevronLeft class="w-3.5 h-3.5" :stroke-width="2" />
                </button>
                <span class="text-[11px] text-[var(--color-text-secondary)] px-2">{{ currentPage }} / {{ totalPages }}</span>
                <button class="p-1 rounded hover:bg-[var(--color-surface)] disabled:opacity-30 disabled:cursor-not-allowed transition-colors" :disabled="currentPage >= totalPages" @click="goToPage(currentPage + 1)">
                  <ChevronRight class="w-3.5 h-3.5" :stroke-width="2" />
                </button>
              </div>
            </div>
            <!-- Selection actions -->
            <div v-if="selectedIds.size > 0" class="flex items-center gap-2">
              <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ selectedIds.size }} ausgewählt</span>
              <button class="pim-btn pim-btn-ghost text-xs py-1 px-2" @click="clearSelection">Aufheben</button>
              <button class="pim-btn pim-btn-primary text-xs" @click="assignSelected">{{ selectedIds.size }} zuordnen</button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
