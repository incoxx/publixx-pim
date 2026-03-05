<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  Search, Filter, ChevronDown, ChevronRight, X, Star,
  Regex, AudioLines, Languages, Download, GitCompareArrows, Pencil,
} from 'lucide-vue-next'
import searchApi from '@/api/search'
import searchProfilesApi from '@/api/searchProfiles'
import watchlistApi from '@/api/watchlist'
import productsApi from '@/api/products'
import hierarchiesApi from '@/api/hierarchies'
import PimTable from '@/components/shared/PimTable.vue'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'

const router = useRouter()
const localeStore = useLocaleStore()

// --- Search Profiles ---
const searchProfiles = ref([])
const selectedProfileId = ref(null)

async function loadProfiles() {
  try {
    const { data } = await searchProfilesApi.list()
    searchProfiles.value = data.data || data
  } catch (e) { /* ignore */ }
}

async function loadProfile(id) {
  const profile = searchProfiles.value.find(p => p.id === id)
  if (!profile) return
  searchInput.value = profile.search_text || ''
  searchMode.value = profile.search_mode || 'like'
  statusFilter.value = profile.status_filter || ''
  selectedCategories.value = profile.category_ids || []
  attributeFilters.value = profile.attribute_filters || {}
  doSearch(1)
}

async function saveProfile({ name, is_shared }) {
  try {
    await searchProfilesApi.create({
      name,
      is_shared,
      search_text: searchInput.value,
      search_mode: searchMode.value,
      status_filter: statusFilter.value || null,
      category_ids: selectedCategories.value,
      attribute_filters: attributeFilters.value,
    })
    await loadProfiles()
  } catch (e) {
    error.value = 'Profil konnte nicht gespeichert werden'
  }
}

async function deleteProfile(id) {
  try {
    await searchProfilesApi.remove(id)
    selectedProfileId.value = null
    await loadProfiles()
  } catch (e) {
    error.value = 'Profil konnte nicht gelöscht werden'
  }
}

// --- State ---
const searchInput = ref('')
const searchMode = ref('like') // 'like' | 'soundex' | 'regex'
const hasSearched = ref(false)
const results = ref([])
const resultMeta = ref({ total: 0, current_page: 1, last_page: 1 })
const loading = ref(false)
const error = ref(null)

// Category selection
const hierarchies = ref([])
const hierarchyTree = ref([])
const selectedCategories = ref([])
const showCategoryPicker = ref(false)

// Attribute filters
const searchableAttributes = ref([])
const attributeFilters = ref({})
const showAttributeFilters = ref(false)
const statusFilter = ref('')

// Watchlist quick-add
const watchlistIds = ref(new Set())

// Selection & XLIFF export
const selectedProductIds = ref([])
const showXliffPanel = ref(false)
const xliffSourceLang = ref('de')
const xliffTargetLang = ref('en')
const xliffExporting = ref(false)

// Product comparison
const showCompare = ref(false)
const compareData = ref(null)
const compareLoading = ref(false)
const showDiffsOnly = ref(false)

const canCompare = computed(() => selectedProductIds.value.length === 2)

const compareRows = computed(() => {
  if (!compareData.value?.rows) return []
  if (showDiffsOnly.value) return compareData.value.rows.filter(r => r.is_different)
  return compareData.value.rows
})

const columns = [
  { key: 'sku', label: 'SKU', mono: true },
  { key: 'name', label: 'Name' },
  { key: 'product_type.name_de', label: 'Typ' },
  { key: 'status', label: 'Status' },
]

// --- Computed ---
const activeFilterCount = computed(() => {
  let count = selectedCategories.value.length
  if (statusFilter.value) count++
  for (const val of Object.values(attributeFilters.value)) {
    if (val !== '' && val !== null && val !== undefined) count++
  }
  return count
})

const flatCategoryNodes = computed(() => {
  const result = []
  function flatten(nodes, prefix = '') {
    for (const node of nodes) {
      const name = node.name_de || node.name_en || node.id
      result.push({
        id: node.id,
        label: prefix + name,
        name: name,
      })
      if (node.children?.length) {
        flatten(node.children, prefix + name + ' > ')
      }
    }
  }
  flatten(hierarchyTree.value)
  return result
})

const searchModeLabel = computed(() => ({
  like: 'LIKE (Standard)',
  soundex: 'SOUNDEX (Ähnlichkeit)',
  regex: 'REGEXP (Muster)',
}[searchMode.value]))

// --- Load data ---
onMounted(async () => {
  // Load hierarchies
  try {
    const { data } = await hierarchiesApi.list()
    hierarchies.value = data.data || data
    if (hierarchies.value.length > 0) {
      const { data: treeData } = await hierarchiesApi.getTree(hierarchies.value[0].id)
      hierarchyTree.value = treeData.data || treeData
    }
  } catch (e) {
    console.error('Failed to load hierarchies', e)
  }

  // Load searchable attributes (with value list entries!)
  try {
    const { data } = await searchApi.searchableAttributes()
    searchableAttributes.value = data.data || data
  } catch (e) {
    console.error('Failed to load searchable attributes', e)
  }

  // Load watchlist IDs for highlighting
  try {
    const { data } = await watchlistApi.productIds()
    watchlistIds.value = new Set(data.data || data)
  } catch (e) { /* ignore */ }

  // Load search profiles
  loadProfiles()
})

// --- Actions ---
function toggleCategory(categoryId) {
  const idx = selectedCategories.value.indexOf(categoryId)
  if (idx === -1) {
    selectedCategories.value.push(categoryId)
  } else {
    selectedCategories.value.splice(idx, 1)
  }
}

function isCategorySelected(id) {
  return selectedCategories.value.includes(id)
}

function clearAllFilters() {
  selectedCategories.value = []
  attributeFilters.value = {}
  statusFilter.value = ''
  searchInput.value = ''
}

async function doSearch(page = 1) {
  hasSearched.value = true
  loading.value = true
  error.value = null

  try {
    const params = {
      search: searchInput.value.trim() || undefined,
      search_mode: searchMode.value,
      page,
      per_page: 50,
    }

    if (selectedCategories.value.length > 0) {
      params.category_ids = selectedCategories.value
      params.include_descendants = true
    }

    if (statusFilter.value) {
      params.status = statusFilter.value
    }

    // Build attribute filters
    const attrFilters = []
    for (const attr of searchableAttributes.value) {
      const val = attributeFilters.value[attr.id]
      if (val === '' || val === null || val === undefined) continue

      const filter = { attribute_id: attr.id, value: val }

      // Choose operator based on data type
      if (attr.data_type === 'String') {
        filter.operator = 'like'
      } else if (attr.data_type === 'Selection' || attr.data_type === 'Dictionary') {
        filter.operator = 'eq'
      } else if (attr.data_type === 'Flag') {
        filter.operator = 'eq'
        filter.value = val === 'true' || val === true ? 1 : 0
      } else {
        filter.operator = 'eq'
      }

      attrFilters.push(filter)
    }

    if (attrFilters.length > 0) {
      params.attribute_filters = attrFilters
    }

    const { data } = await searchApi.search(params)
    results.value = data.data || []
    resultMeta.value = data.meta || { total: results.value.length, current_page: 1, last_page: 1 }
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.detail || 'Suchfehler'
    results.value = []
  } finally {
    loading.value = false
  }
}

function openProduct(row) {
  router.push(`/products/${row.id}`)
}

function getFilterInputType(dataType) {
  switch (dataType) {
    case 'Number':
    case 'Float': return 'number'
    case 'Date': return 'date'
    case 'Flag': return 'select'
    default: return 'text'
  }
}

function isOnWatchlist(productId) {
  return watchlistIds.value.has(productId)
}

async function toggleWatchlist(productId) {
  try {
    if (isOnWatchlist(productId)) {
      await watchlistApi.removeByProduct(productId)
      watchlistIds.value.delete(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    } else {
      await watchlistApi.add(productId)
      watchlistIds.value.add(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    }
  } catch (e) {
    console.error('Watchlist toggle failed', e)
  }
}

// --- Selection & XLIFF ---
function handleSelect(ids) {
  selectedProductIds.value = ids
}

async function bulkAddToWatchlist() {
  if (selectedProductIds.value.length === 0) return
  try {
    await watchlistApi.bulkAdd(selectedProductIds.value)
    const { data } = await watchlistApi.productIds()
    watchlistIds.value = new Set(data.data || data)
  } catch (e) {
    console.error('Bulk watchlist add failed', e)
  }
}

function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 200)
}

async function exportXliff() {
  xliffExporting.value = true
  try {
    const resp = await productsApi.exportXliff({
      sourceLang: xliffSourceLang.value,
      targetLang: xliffTargetLang.value,
      productIds: selectedProductIds.value,
    })
    triggerDownload(resp.data, `suchergebnisse-${xliffSourceLang.value}-${xliffTargetLang.value}.xliff`)
  } catch (e) { console.error('XLIFF export failed:', e) }
  finally { xliffExporting.value = false }
}

// --- Product Comparison ---
async function openCompare() {
  if (!canCompare.value) return
  showCompare.value = true
  compareLoading.value = true
  compareData.value = null
  try {
    const { data } = await productsApi.compare(selectedProductIds.value[0], selectedProductIds.value[1])
    compareData.value = data.data || data
  } catch (e) { console.error('Compare failed:', e) }
  finally { compareLoading.value = false }
}

function openBulkEditor() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-edit', query: { ids } })
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <!-- Search Profile Selector -->
    <ProfileSelector
      :profiles="searchProfiles"
      v-model="selectedProfileId"
      label="Suchprofil"
      @load="loadProfile"
      @save="saveProfile"
      @delete="deleteProfile"
    />

    <!-- Search header -->
    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
      <div class="relative flex-1 min-w-0">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <input
          v-model="searchInput"
          type="text"
          :placeholder="searchMode === 'regex' ? 'Regulärer Ausdruck eingeben...' : searchMode === 'soundex' ? 'Ähnlich klingend suchen...' : 'Produkte, Attribute, SKUs durchsuchen...'"
          class="pim-input pl-12 pr-4 py-3 text-base w-full"
          @keydown.enter="doSearch(1)"
          autofocus
        />
      </div>
      <button
        class="pim-btn pim-btn-secondary py-3 px-4 relative"
        @click="showAttributeFilters = !showAttributeFilters"
      >
        <Filter class="w-4 h-4" :stroke-width="1.75" />
        <span class="ml-1.5 text-sm hidden sm:inline">Filter</span>
        <span
          v-if="activeFilterCount > 0"
          class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-[var(--color-accent)] text-white text-[10px] flex items-center justify-center font-bold"
        >
          {{ activeFilterCount }}
        </span>
      </button>
      <button class="pim-btn pim-btn-primary py-3 px-6" @click="doSearch(1)">
        Suchen
      </button>
    </div>

    <!-- Search mode toggle -->
    <div class="flex flex-wrap items-center gap-2 text-xs">
      <span class="text-[var(--color-text-tertiary)]">Suchmodus:</span>
      <button
        v-for="mode in ['like', 'soundex', 'regex']"
        :key="mode"
        :class="[
          'px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors',
          searchMode === mode
            ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
            : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]',
        ]"
        @click="searchMode = mode"
      >
        <template v-if="mode === 'like'">LIKE</template>
        <template v-else-if="mode === 'soundex'">
          <AudioLines class="inline w-3 h-3 -mt-0.5 mr-0.5" :stroke-width="2" />
          SOUNDEX
        </template>
        <template v-else>
          <Regex class="inline w-3 h-3 -mt-0.5 mr-0.5" :stroke-width="2" />
          REGEXP
        </template>
      </button>
      <span class="text-[10px] text-[var(--color-text-tertiary)] ml-2 hidden sm:inline">
        {{ searchMode === 'like' ? 'Teiltext-Suche (enthält)' : searchMode === 'soundex' ? 'Ähnlich klingende Begriffe finden (Tippfehler-tolerant)' : 'Reguläre Ausdrücke für präzise Muster' }}
      </span>
    </div>

    <!-- Filter panel -->
    <transition name="slide">
      <div v-if="showAttributeFilters" class="pim-card p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Suchfilter</h3>
          <div class="flex gap-2">
            <button
              v-if="activeFilterCount > 0"
              class="text-xs text-[var(--color-accent)] hover:underline"
              @click="clearAllFilters"
            >
              Alle zurücksetzen
            </button>
            <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="showAttributeFilters = false">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>
        </div>

        <!-- Status filter -->
        <div>
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Produkt-Status</p>
          <select class="pim-input text-xs w-48" v-model="statusFilter">
            <option value="">— Alle —</option>
            <option value="active">Aktiv</option>
            <option value="draft">Entwurf</option>
            <option value="inactive">Inaktiv</option>
            <option value="discontinued">Auslaufend</option>
          </select>
        </div>

        <!-- Category filter -->
        <div>
          <button
            class="flex items-center gap-2 text-[12px] font-medium text-[var(--color-text-secondary)] mb-2 cursor-pointer"
            @click="showCategoryPicker = !showCategoryPicker"
          >
            <component :is="showCategoryPicker ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" />
            Kategorien (inkl. Unterkategorien)
            <span v-if="selectedCategories.length > 0" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5">
              {{ selectedCategories.length }}
            </span>
          </button>
          <div v-if="showCategoryPicker" class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
            <label
              v-for="cat in flatCategoryNodes"
              :key="cat.id"
              class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
            >
              <input
                type="checkbox"
                :checked="isCategorySelected(cat.id)"
                @change="toggleCategory(cat.id)"
                class="rounded border-[var(--color-border)]"
              />
              <span class="text-[var(--color-text-primary)]">{{ cat.label }}</span>
            </label>
            <p v-if="flatCategoryNodes.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-2 text-center">
              Keine Kategorien vorhanden
            </p>
          </div>
        </div>

        <!-- Attribute filters -->
        <div v-if="searchableAttributes.length > 0">
          <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Attribute</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="attr in searchableAttributes" :key="attr.id">
              <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">
                {{ attr.name_de || attr.technical_name }}
              </label>
              <template v-if="attr.data_type === 'Flag'">
                <select
                  class="pim-input text-xs"
                  :value="attributeFilters[attr.id] ?? ''"
                  @change="attributeFilters[attr.id] = $event.target.value || ''"
                >
                  <option value="">— Alle —</option>
                  <option value="true">Ja</option>
                  <option value="false">Nein</option>
                </select>
              </template>
              <template v-else-if="(attr.data_type === 'Selection' || attr.data_type === 'Dictionary') && attr.value_list?.entries?.length">
                <select
                  class="pim-input text-xs"
                  :value="attributeFilters[attr.id] ?? ''"
                  @change="attributeFilters[attr.id] = $event.target.value || ''"
                >
                  <option value="">— Alle —</option>
                  <option
                    v-for="entry in attr.value_list.entries"
                    :key="entry.id"
                    :value="entry.id"
                  >
                    {{ entry.display_value_de || entry.code }}
                  </option>
                </select>
              </template>
              <template v-else>
                <input
                  class="pim-input text-xs"
                  :type="getFilterInputType(attr.data_type)"
                  :value="attributeFilters[attr.id] ?? ''"
                  :placeholder="attr.data_type === 'Date' ? '' : 'Wert eingeben...'"
                  @input="attributeFilters[attr.id] = $event.target.value"
                />
              </template>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Error display -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ error }}</p>
    </div>

    <!-- Result count -->
    <div v-if="hasSearched && !loading && !error && results.length > 0" class="text-xs text-[var(--color-text-tertiary)]">
      {{ resultMeta.total }} Ergebnis{{ resultMeta.total !== 1 ? 'se' : '' }}
      <span v-if="searchMode === 'soundex'" class="ml-1 text-[var(--color-accent)]">(SOUNDEX)</span>
      <span v-if="searchMode === 'regex'" class="ml-1 text-[var(--color-accent)]">(REGEXP)</span>
    </div>

    <!-- Selection toolbar -->
    <div v-if="selectedProductIds.length > 0" class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs text-[var(--color-text-secondary)]">{{ selectedProductIds.length }} ausgewählt</span>
      <button class="pim-btn pim-btn-secondary text-xs" @click="bulkAddToWatchlist">
        <Star class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Zur Merkliste</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="showXliffPanel = !showXliffPanel">
        <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
        XLIFF
      </button>
      <button
        v-if="canCompare"
        class="pim-btn pim-btn-primary text-xs"
        @click="openCompare"
      >
        <GitCompareArrows class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Vergleichen</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="openBulkEditor">
        <Pencil class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Bulk bearbeiten</span>
      </button>
      <span v-if="selectedProductIds.length === 1" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Noch 1 Produkt auswählen zum Vergleichen
      </span>
    </div>

    <!-- XLIFF Export Panel -->
    <div v-if="showXliffPanel && selectedProductIds.length > 0" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Languages class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          XLIFF Export ({{ selectedProductIds.length }} Produkte)
        </h3>
        <button class="pim-btn pim-btn-ghost text-xs p-1" @click="showXliffPanel = false">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Quellsprache</label>
          <select class="pim-input text-xs w-24" v-model="xliffSourceLang">
            <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
          </select>
        </div>
        <div class="text-[var(--color-text-tertiary)] text-lg pb-1">→</div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Zielsprache</label>
          <select class="pim-input text-xs w-24" v-model="xliffTargetLang">
            <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
          </select>
        </div>
        <button
          class="pim-btn pim-btn-primary text-xs"
          :disabled="xliffExporting || xliffSourceLang === xliffTargetLang"
          @click="exportXliff"
        >
          <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ xliffExporting ? 'Export…' : 'Export' }}
        </button>
      </div>
    </div>

    <PimTable
      v-if="results.length > 0"
      :columns="columns"
      :rows="results"
      :loading="loading"
      selectable
      @row-click="openProduct"
      @select="handleSelect"
    >
      <template #cell-sku="{ row, value }">
        <div class="flex items-center gap-2">
          <button
            class="p-0.5 rounded hover:bg-[var(--color-bg)] shrink-0"
            :title="isOnWatchlist(row.id) ? 'Von Merkliste entfernen' : 'Zur Merkliste hinzufügen'"
            @click.stop="toggleWatchlist(row.id)"
          >
            <Star
              class="w-3.5 h-3.5"
              :class="isOnWatchlist(row.id) ? 'text-amber-500 fill-amber-500' : 'text-[var(--color-text-tertiary)]'"
              :stroke-width="2"
            />
          </button>
          <span class="font-mono text-xs">{{ value }}</span>
        </div>
      </template>
      <template #cell-product_type.name_de="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[10px]">
          {{ value || 'Produkt' }}
        </span>
      </template>
      <template #cell-status="{ value }">
        <span
          :class="[
            'pim-badge',
            value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' :
            value === 'draft' ? 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' :
            'bg-[var(--color-error-light)] text-[var(--color-error)]'
          ]"
        >
          {{ value === 'active' ? 'Aktiv' : value === 'draft' ? 'Entwurf' : value === 'inactive' ? 'Inaktiv' : 'Auslaufend' }}
        </span>
      </template>

      <!-- Pagination -->
      <template #pagination v-if="resultMeta.last_page > 1">
        <div class="flex flex-col sm:flex-row items-center justify-between px-4 py-3 border-t border-[var(--color-border)] gap-2">
          <span class="text-xs text-[var(--color-text-tertiary)]">
            Seite {{ resultMeta.current_page }} / {{ resultMeta.last_page }}
          </span>
          <div class="flex items-center gap-1">
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="resultMeta.current_page <= 1"
              @click="doSearch(resultMeta.current_page - 1)"
            >Zurück</button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="resultMeta.current_page >= resultMeta.last_page"
              @click="doSearch(resultMeta.current_page + 1)"
            >Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <div v-if="loading" class="pim-card p-6">
      <div class="space-y-3">
        <div v-for="i in 5" :key="i" class="pim-skeleton h-8 rounded" />
      </div>
    </div>

    <div v-else-if="hasSearched && results.length === 0 && !error" class="text-center py-12">
      <Search class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Ergebnisse gefunden</p>
      <p v-if="searchMode === 'like'" class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Tipp: Probiere den SOUNDEX-Modus für ähnlich klingende Begriffe
      </p>
    </div>

    <div v-else-if="!hasSearched" class="text-center py-16">
      <Search class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Filter konfigurieren und Suche starten</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Suche mit LIKE, SOUNDEX oder REGEXP
      </p>
    </div>

    <!-- Product Comparison Modal -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showCompare" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showCompare = false" />
          <div class="relative w-full max-w-4xl max-h-[85vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)] shrink-0">
              <div class="flex items-center gap-3">
                <GitCompareArrows class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
                <span class="text-sm font-semibold text-[var(--color-text-primary)]">Produktvergleich</span>
                <span v-if="compareData" class="text-[11px] text-[var(--color-text-tertiary)]">
                  {{ compareData.total_differences }} Unterschiede von {{ compareData.total_attributes }} Feldern
                </span>
              </div>
              <div class="flex items-center gap-2">
                <label class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-secondary)] cursor-pointer">
                  <input type="checkbox" v-model="showDiffsOnly" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                  Nur Unterschiede
                </label>
                <button class="p-1.5 rounded hover:bg-[var(--color-bg)]" @click="showCompare = false">
                  <X class="w-4 h-4" :stroke-width="2" />
                </button>
              </div>
            </div>
            <div class="flex-1 overflow-y-auto">
              <div v-if="compareLoading" class="p-8 space-y-3">
                <div v-for="i in 8" :key="i" class="pim-skeleton h-8 w-full rounded" />
              </div>
              <table v-else-if="compareData" class="w-full text-[13px]">
                <thead class="sticky top-0 z-10">
                  <tr class="bg-[var(--color-bg)] border-b border-[var(--color-border)]">
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] w-[200px]">Attribut</th>
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-accent)]">
                      {{ compareData.product_a?.sku }}
                      <span class="font-normal normal-case text-[var(--color-text-tertiary)]">{{ compareData.product_a?.name }}</span>
                    </th>
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-accent)]">
                      {{ compareData.product_b?.sku }}
                      <span class="font-normal normal-case text-[var(--color-text-tertiary)]">{{ compareData.product_b?.name }}</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, i) in compareRows"
                    :key="i"
                    :class="['border-b border-[var(--color-border)]', row.is_different ? 'bg-amber-50/50' : '']"
                  >
                    <td class="px-4 py-2 text-[12px] font-medium text-[var(--color-text-secondary)]">
                      {{ row.attribute_name }}
                      <span v-if="row.data_type && row.data_type !== 'base'" class="ml-1 text-[10px] text-[var(--color-text-tertiary)]">({{ row.data_type }})</span>
                    </td>
                    <td :class="['px-4 py-2', row.is_different ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)]']">
                      {{ row.value_a !== null && row.value_a !== '' ? row.value_a : '—' }}
                    </td>
                    <td :class="['px-4 py-2', row.is_different ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)]']">
                      {{ row.value_b !== null && row.value_b !== '' ? row.value_b : '—' }}
                    </td>
                  </tr>
                  <tr v-if="compareRows.length === 0">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-[var(--color-text-tertiary)]">
                      {{ showDiffsOnly ? 'Keine Unterschiede gefunden — Produkte sind identisch' : 'Keine Daten zum Vergleichen' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
