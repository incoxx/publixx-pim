<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { X, Search, LayoutGrid, List, ChevronLeft, ChevronRight, Image } from 'lucide-vue-next'
import mediaApi from '@/api/media'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  usageTypes: { type: Array, default: () => [] },
  selectedUsageTypeId: { type: String, default: null },
  excludeMediaIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'select', 'update:selectedUsageTypeId'])

const searchQuery = ref('')
const viewMode = ref('grid') // 'grid' | 'list'
const loading = ref(false)
const media = ref([])
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = 24

let searchTimer = null

async function loadMedia(page = 1) {
  loading.value = true
  try {
    const params = { perPage, page }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }
    params.filters = { media_type: 'image' }
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

const filteredMedia = computed(() => {
  if (!props.excludeMediaIds.length) return media.value
  return media.value.filter(m => !props.excludeMediaIds.includes(m.id))
})

watch(() => props.modelValue, (open) => {
  if (open) {
    searchQuery.value = ''
    currentPage.value = 1
    loadMedia(1)
  }
})

watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadMedia(1)
  }, 300)
})

function close() {
  emit('update:modelValue', false)
}

function selectMedia(item) {
  emit('select', item)
}

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  loadMedia(page)
}

function getThumbUrl(item) {
  if (item.file_name) return mediaApi.thumbUrl(item.id, 200, 200)
  return ''
}

function formatFileSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />
        <div class="relative w-full max-w-[800px] max-h-[85vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
            <span class="text-sm font-semibold text-[var(--color-text-primary)]">Medium auswählen</span>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <!-- Toolbar -->
          <div class="flex items-center gap-2 px-4 py-2 border-b border-[var(--color-border)] bg-[var(--color-bg)]">
            <!-- Search -->
            <div class="relative flex-1">
              <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
              <input
                v-model="searchQuery"
                type="text"
                class="pim-input text-xs pl-8 w-full"
                placeholder="Medien durchsuchen…"
              />
            </div>

            <!-- Usage type selector -->
            <select
              v-if="usageTypes.length > 0"
              :value="selectedUsageTypeId"
              @change="emit('update:selectedUsageTypeId', $event.target.value || null)"
              class="pim-select text-xs min-w-[140px]"
            >
              <option v-for="ut in usageTypes" :key="ut.id" :value="ut.id">
                {{ ut.name_de || ut.technical_name }}
              </option>
            </select>

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

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-4">
            <!-- Loading -->
            <div v-if="loading" :class="viewMode === 'grid' ? 'grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2' : 'space-y-1'">
              <div v-for="i in 12" :key="i" :class="viewMode === 'grid' ? 'pim-skeleton aspect-square rounded' : 'pim-skeleton h-12 rounded'" />
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
                @click="selectMedia(m)"
              >
                <img
                  :src="getThumbUrl(m)"
                  class="w-full h-full object-cover"
                  loading="lazy"
                  alt=""
                />
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                  <span class="text-[10px] text-white truncate block">{{ m.file_name }}</span>
                </div>
              </div>
            </div>

            <!-- List view -->
            <div v-else class="space-y-0.5">
              <div
                v-for="m in filteredMedia"
                :key="m.id"
                class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-[var(--color-bg)] border border-transparent hover:border-[var(--color-border)] transition-all"
                @click="selectMedia(m)"
              >
                <div class="w-10 h-10 bg-[var(--color-bg)] rounded overflow-hidden flex-shrink-0 border border-[var(--color-border)]">
                  <img :src="getThumbUrl(m)" class="w-full h-full object-cover" loading="lazy" alt="" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ m.file_name }}</p>
                  <p class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.mime_type }} · {{ formatFileSize(m.file_size) }}{{ m.width ? ` · ${m.width}×${m.height}` : '' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer / Pagination -->
          <div v-if="totalPages > 1 || totalItems > 0" class="flex items-center justify-between px-4 py-2 border-t border-[var(--color-border)] bg-[var(--color-bg)]">
            <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ totalItems }} Medien</span>
            <div v-if="totalPages > 1" class="flex items-center gap-1">
              <button
                class="p-1 rounded hover:bg-[var(--color-surface)] disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :disabled="currentPage <= 1"
                @click="goToPage(currentPage - 1)"
              >
                <ChevronLeft class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <span class="text-[11px] text-[var(--color-text-secondary)] px-2">
                {{ currentPage }} / {{ totalPages }}
              </span>
              <button
                class="p-1 rounded hover:bg-[var(--color-surface)] disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :disabled="currentPage >= totalPages"
                @click="goToPage(currentPage + 1)"
              >
                <ChevronRight class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
