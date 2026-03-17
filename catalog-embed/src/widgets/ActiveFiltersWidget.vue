<script setup>
/**
 * Shows active filters as removable chips/tags.
 * data-catalog="active-filters"
 */
import { computed } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions, getters } = useStore()

const chips = computed(() => {
  const items = []
  // Category
  if (state.selectedCategoryName) {
    items.push({ type: 'category', label: state.selectedCategoryName })
  }
  // Search
  if (state.search) {
    items.push({ type: 'search', label: `"${state.search}"` })
  }
  // Facet filters
  for (const [attrId, val] of Object.entries(state.activeFilters)) {
    const facet = state.facets.find(f => String(f.attribute_id) === String(attrId))
    const label = facet ? facet.label : `Filter ${attrId}`
    items.push({ type: 'filter', attrId, label: `${label}: ${val}` })
  }
  return items
})

function remove(chip) {
  if (chip.type === 'category') {
    actions.clearCategory()
  } else if (chip.type === 'search') {
    actions.setSearch('')
  } else if (chip.type === 'filter') {
    actions.clearFilter(chip.attrId)
  }
  actions.fetchProducts()
}

function clearAll() {
  actions.setSearch('')
  actions.clearCategory()
  actions.clearAllFilters()
  actions.fetchProducts()
}
</script>

<template>
  <div v-if="chips.length > 0" class="pxc-active-filters">
    <span
      v-for="(chip, idx) in chips"
      :key="idx"
      class="pxc-active-filters__chip"
    >
      {{ chip.label }}
      <button @click="remove(chip)" v-html="icons.x"></button>
    </span>
    <button v-if="chips.length > 1" class="pxc-active-filters__clear" @click="clearAll">
      Alle löschen
    </button>
  </div>
</template>
