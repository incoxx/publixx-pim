<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Filter, ChevronDown, ChevronRight, X } from 'lucide-vue-next'
import { usePql } from '@/composables/usePql'
import attributesApiDefault from '@/api/attributes'
import hierarchiesApi from '@/api/hierarchies'
import PimTable from '@/components/shared/PimTable.vue'

const router = useRouter()
const pql = usePql()

// --- State ---
const searchInput = ref('')
const hasSearched = ref(false)

// Category selection
const hierarchies = ref([])
const hierarchyTree = ref([])
const selectedCategories = ref([])
const showCategoryPicker = ref(false)

// Attribute filters
const searchableAttributes = ref([])
const attributeFilters = ref({})
const showAttributeFilters = ref(false)

const columns = [
  { key: 'sku', label: 'SKU', mono: true },
  { key: 'name', label: 'Name' },
  { key: 'status', label: 'Status' },
]

// --- Computed ---
const activeFilterCount = computed(() => {
  let count = selectedCategories.value.length
  for (const val of Object.values(attributeFilters.value)) {
    if (val !== '' && val !== null && val !== undefined) count++
  }
  return count
})

const flatCategoryNodes = computed(() => {
  const result = []
  function flatten(nodes, prefix = '') {
    for (const node of nodes) {
      result.push({
        id: node.id,
        label: prefix + node.name_de || node.name_en || node.id,
        name: node.name_de || node.name_en || node.id,
      })
      if (node.children?.length) {
        flatten(node.children, prefix + (node.name_de || node.name_en || node.id) + ' > ')
      }
    }
  }
  flatten(hierarchyTree.value)
  return result
})

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
  } catch { /* silently fail */ }

  // Load searchable attributes (non-internal)
  try {
    const { data } = await attributesApiDefault.listSearchable()
    searchableAttributes.value = data.data || data
  } catch { /* silently fail */ }
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
  searchInput.value = ''
}

async function doSearch() {
  hasSearched.value = true

  const conditions = []

  // Text search
  if (searchInput.value.trim()) {
    const term = searchInput.value.replace(/"/g, '\\"')
    conditions.push(`(name LIKE "%${term}%" OR sku LIKE "%${term}%")`)
  }

  // Category filter
  if (selectedCategories.value.length > 0) {
    const catIds = selectedCategories.value.map(id => `"${id}"`).join(', ')
    conditions.push(`category_id IN (${catIds})`)
  }

  // Attribute filters
  for (const attr of searchableAttributes.value) {
    const val = attributeFilters.value[attr.id]
    if (val === '' || val === null || val === undefined) continue

    const techName = attr.technical_name
    if (attr.data_type === 'String') {
      const escaped = String(val).replace(/"/g, '\\"')
      conditions.push(`${techName} LIKE "%${escaped}%"`)
    } else if (attr.data_type === 'Number' || attr.data_type === 'Float') {
      conditions.push(`${techName} = ${val}`)
    } else if (attr.data_type === 'Flag') {
      conditions.push(`${techName} = ${val === true || val === 'true' ? 'true' : 'false'}`)
    } else if (attr.data_type === 'Selection' || attr.data_type === 'Dictionary') {
      const escaped = String(val).replace(/"/g, '\\"')
      conditions.push(`${techName} = "${escaped}"`)
    } else if (attr.data_type === 'Date') {
      conditions.push(`${techName} = "${val}"`)
    }
  }

  const pqlQuery = conditions.length > 0
    ? 'WHERE ' + conditions.join(' AND ')
    : ''

  await pql.execute(pqlQuery)
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
          placeholder="Produkte, Attribute, SKUs durchsuchen..."
          class="pim-input pl-12 pr-4 py-3 text-base w-full"
          @keydown.enter="doSearch"
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
      <button class="pim-btn pim-btn-primary py-3 px-6" @click="doSearch">
        Suchen
      </button>
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

        <!-- Category filter -->
        <div>
          <button
            class="flex items-center gap-2 text-[12px] font-medium text-[var(--color-text-secondary)] mb-2 cursor-pointer"
            @click="showCategoryPicker = !showCategoryPicker"
          >
            <component :is="showCategoryPicker ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" />
            Kategorien
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
              <template v-else-if="attr.data_type === 'Selection' || attr.data_type === 'Dictionary'">
                <select
                  class="pim-input text-xs"
                  :value="attributeFilters[attr.id] ?? ''"
                  @change="attributeFilters[attr.id] = $event.target.value || ''"
                >
                  <option value="">— Alle —</option>
                  <option
                    v-for="entry in (attr.value_list?.entries || [])"
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
    <div v-if="pql.error.value" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ pql.error.value }}</p>
    </div>

    <!-- Result count -->
    <div v-if="hasSearched && !pql.loading.value && !pql.error.value && pql.results.value.length > 0" class="text-xs text-[var(--color-text-tertiary)]">
      {{ pql.count.value }} Ergebnis{{ pql.count.value !== 1 ? 'se' : '' }}
    </div>

    <PimTable
      v-if="pql.results.value.length > 0"
      :columns="columns"
      :rows="pql.results.value"
      :loading="pql.loading.value"
      @row-click="openProduct"
    >
      <template #cell-status="{ value }">
        <span
          :class="[
            'pim-badge',
            value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' :
            value === 'draft' ? 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' :
            'bg-[var(--color-error-light)] text-[var(--color-error)]'
          ]"
        >
          {{ value === 'active' ? 'Aktiv' : value === 'draft' ? 'Entwurf' : 'Inaktiv' }}
        </span>
      </template>
    </PimTable>

    <div v-if="pql.loading.value" class="pim-card p-6">
      <div class="space-y-3">
        <div v-for="i in 5" :key="i" class="pim-skeleton h-8 rounded" />
      </div>
    </div>

    <div v-else-if="hasSearched && pql.results.value.length === 0 && !pql.error.value" class="text-center py-12">
      <Search class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Ergebnisse gefunden</p>
    </div>

    <div v-else-if="!hasSearched" class="text-center py-16">
      <Search class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Filter konfigurieren und Suche starten</p>
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
