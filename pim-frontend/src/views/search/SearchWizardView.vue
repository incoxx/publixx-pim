<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Filter, ChevronDown, ChevronRight, X, Star, Regex, AudioLines } from 'lucide-vue-next'
import searchApi from '@/api/search'
import watchlistApi from '@/api/watchlist'
import hierarchiesApi from '@/api/hierarchies'
import PimTable from '@/components/shared/PimTable.vue'

const router = useRouter()

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
      watchlistIds.value = new Set(watchlistIds.value) // trigger reactivity
    } else {
      await watchlistApi.add(productId)
      watchlistIds.value.add(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    }
  } catch (e) {
    console.error('Watchlist toggle failed', e)
  }
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <!-- Search header -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1">
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
        <span class="ml-1.5 text-sm">Filter</span>
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
    <div class="flex items-center gap-2 text-xs">
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
      <span class="text-[10px] text-[var(--color-text-tertiary)] ml-2">
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

    <PimTable
      v-if="results.length > 0"
      :columns="columns"
      :rows="results"
      :loading="loading"
      @row-click="openProduct"
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
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
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
