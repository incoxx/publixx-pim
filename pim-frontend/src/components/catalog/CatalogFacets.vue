<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { ChevronDown, ChevronRight, Search, X } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()

// Track collapsed state per facet
const collapsed = ref({})
// Track "show more" state per facet
const expanded = ref({})
// Track search text per facet
const searchText = ref({})

const VISIBLE_COUNT = 5

onMounted(() => {
  store.fetchFacets()
})

// Re-fetch facets when locale changes
watch(() => store.locale, () => {
  store.fetchFacets()
})

function toggle(attributeId) {
  collapsed.value[attributeId] = !collapsed.value[attributeId]
}

function isCollapsed(attributeId) {
  return !!collapsed.value[attributeId]
}

function toggleExpand(attributeId) {
  expanded.value[attributeId] = !expanded.value[attributeId]
}

function isExpanded(attributeId) {
  return !!expanded.value[attributeId]
}

// ValueList / Text: comma-separated selected values
function getSelectedValues(attributeId) {
  const raw = store.activeFilters[attributeId]
  if (!raw) return []
  return String(raw).split(',').filter(Boolean)
}

function toggleValueSelection(attributeId, valueId) {
  const current = getSelectedValues(attributeId)
  const idx = current.indexOf(String(valueId))
  if (idx === -1) {
    current.push(String(valueId))
  } else {
    current.splice(idx, 1)
  }
  if (current.length === 0) {
    store.clearFilter(attributeId)
  } else {
    store.setFilter(attributeId, current.join(','))
  }
  store.fetchProducts()
}

function isValueSelected(attributeId, valueId) {
  return getSelectedValues(attributeId).includes(String(valueId))
}

// Boolean
function toggleBoolean(attributeId) {
  const current = store.activeFilters[attributeId]
  if (current === '1') {
    store.clearFilter(attributeId)
  } else {
    store.setFilter(attributeId, '1')
  }
  store.fetchProducts()
}

// Range (Decimal / Integer)
function getRangeValue(attributeId) {
  const raw = store.activeFilters[attributeId]
  if (!raw) return { min: '', max: '' }
  const parts = String(raw).split(':')
  return { min: parts[0] || '', max: parts[1] || '' }
}

function setRangeMin(attributeId, val) {
  const range = getRangeValue(attributeId)
  range.min = val
  applyRange(attributeId, range)
}

function setRangeMax(attributeId, val) {
  const range = getRangeValue(attributeId)
  range.max = val
  applyRange(attributeId, range)
}

function applyRange(attributeId, range) {
  if (!range.min && !range.max) {
    store.clearFilter(attributeId)
  } else {
    store.setFilter(attributeId, `${range.min}:${range.max}`)
  }
  store.fetchProducts()
}

// Filtered values for search
function filteredValues(facet) {
  const values = facet.values || []
  const q = (searchText.value[facet.attribute_id] || '').toLowerCase()
  if (!q) return values
  return values.filter(v => v.value.toLowerCase().includes(q))
}

// Visible values (respecting show more / search)
function visibleValues(facet) {
  const filtered = filteredValues(facet)
  if (isExpanded(facet.attribute_id)) return filtered
  return filtered.slice(0, VISIBLE_COUNT)
}

function hiddenCount(facet) {
  return filteredValues(facet).length - VISIBLE_COUNT
}

// Active filter count for a specific facet
function facetFilterCount(attributeId) {
  return getSelectedValues(attributeId).length
}
</script>

<template>
  <div v-if="store.facets.length" class="py-2">
    <!-- Header with reset -->
    <div class="px-4 pb-2 flex items-center justify-between">
      <h3 class="font-semibold text-sm text-base-content/80">
        {{ t('catalog.filters') }}
      </h3>
      <button
        v-if="store.activeFilterCount > 0"
        class="text-xs text-primary hover:underline"
        @click="store.clearAllFilters(); store.fetchProducts()"
      >
        {{ t('catalog.clearAllFilters') }}
      </button>
    </div>

    <!-- Facets -->
    <div class="space-y-1">
      <div
        v-for="facet in store.facets"
        :key="facet.attribute_id"
        class="border-t border-base-300"
      >
        <!-- Section header -->
        <button
          class="w-full flex items-center gap-2 px-4 py-3 text-sm font-medium text-base-content hover:bg-base-200/50 transition-colors min-h-[44px]"
          @click="toggle(facet.attribute_id)"
        >
          <component
            :is="isCollapsed(facet.attribute_id) ? ChevronRight : ChevronDown"
            class="w-3.5 h-3.5 text-base-content/50 shrink-0"
          />
          <span class="flex-1 text-left truncate">{{ facet.label }}</span>
          <span
            v-if="facetFilterCount(facet.attribute_id) > 0"
            class="badge badge-xs badge-primary"
          >
            {{ facetFilterCount(facet.attribute_id) }}
          </span>
        </button>

        <!-- Section body -->
        <div v-show="!isCollapsed(facet.attribute_id)" class="px-4 pb-3">

          <!-- ValueList / Text: checkboxes -->
          <template v-if="facet.data_type === 'ValueList' || facet.data_type === 'Text'">
            <!-- Search (when > 8 values) -->
            <div v-if="(facet.values || []).length > 8" class="relative mb-2">
              <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-base-content/40" />
              <input
                v-model="searchText[facet.attribute_id]"
                type="text"
                :placeholder="t('catalog.searchValues')"
                class="input input-xs input-bordered w-full pl-8 text-xs"
              />
            </div>

            <div class="space-y-0.5">
              <label
                v-for="val in visibleValues(facet)"
                :key="val.value_id || val.value"
                class="flex items-center gap-2 py-1.5 px-1 rounded hover:bg-base-200/50 cursor-pointer min-h-[44px] sm:min-h-0"
              >
                <input
                  type="checkbox"
                  class="checkbox checkbox-xs checkbox-primary rounded"
                  :checked="isValueSelected(facet.attribute_id, val.value_id || val.value)"
                  @change="toggleValueSelection(facet.attribute_id, val.value_id || val.value)"
                />
                <span class="flex-1 text-xs text-base-content truncate">{{ val.value }}</span>
                <span class="text-[10px] text-base-content/40 tabular-nums">{{ val.count }}</span>
              </label>
            </div>

            <!-- Show more / less -->
            <button
              v-if="hiddenCount(facet) > 0 && !isExpanded(facet.attribute_id)"
              class="text-xs text-primary hover:underline mt-1.5 pl-1"
              @click="toggleExpand(facet.attribute_id)"
            >
              {{ t('catalog.showMore') }} (+{{ hiddenCount(facet) }})
            </button>
            <button
              v-else-if="isExpanded(facet.attribute_id) && (facet.values || []).length > VISIBLE_COUNT"
              class="text-xs text-primary hover:underline mt-1.5 pl-1"
              @click="toggleExpand(facet.attribute_id)"
            >
              {{ t('catalog.showLess') }}
            </button>
          </template>

          <!-- Boolean: toggle -->
          <template v-else-if="facet.data_type === 'Boolean'">
            <label class="flex items-center gap-3 py-1 cursor-pointer min-h-[44px] sm:min-h-0">
              <input
                type="checkbox"
                class="toggle toggle-sm toggle-primary"
                :checked="store.activeFilters[facet.attribute_id] === '1'"
                @change="toggleBoolean(facet.attribute_id)"
              />
              <span class="text-xs text-base-content">{{ facet.label }}</span>
            </label>
          </template>

          <!-- Decimal / Integer: range inputs -->
          <template v-else-if="facet.data_type === 'Decimal' || facet.data_type === 'Integer'">
            <div class="flex items-center gap-2">
              <div class="flex-1">
                <label class="text-[10px] text-base-content/50 mb-0.5 block">{{ t('catalog.from') }}</label>
                <input
                  type="number"
                  class="input input-xs input-bordered w-full text-xs"
                  :placeholder="facet.min != null ? String(facet.min) : ''"
                  :value="getRangeValue(facet.attribute_id).min"
                  @change="setRangeMin(facet.attribute_id, $event.target.value)"
                />
              </div>
              <span class="text-base-content/30 mt-4">–</span>
              <div class="flex-1">
                <label class="text-[10px] text-base-content/50 mb-0.5 block">{{ t('catalog.to') }}</label>
                <input
                  type="number"
                  class="input input-xs input-bordered w-full text-xs"
                  :placeholder="facet.max != null ? String(facet.max) : ''"
                  :value="getRangeValue(facet.attribute_id).max"
                  @change="setRangeMax(facet.attribute_id, $event.target.value)"
                />
              </div>
              <span v-if="facet.unit" class="text-[10px] text-base-content/50 mt-4">{{ facet.unit }}</span>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
