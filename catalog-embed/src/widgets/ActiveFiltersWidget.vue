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
    // Resolve display values for ValueList facets (IDs → names)
    let displayVal = val
    if (facet?.values?.length) {
      const selectedIds = val.split(',').filter(Boolean).map(v => decodeURIComponent(v))
      const names = selectedIds.map(id => {
        const v = facet.values.find(fv => String(fv.value_id) === id)
        return v ? v.value : id
      })
      displayVal = names.join(', ')
    }
    items.push({ type: 'filter', attrId, label: `${label}: ${displayVal}` })
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
