<script setup>
import { computed } from 'vue'
import { useStore } from '../store.js'

const props = defineProps({
  attribute: { type: String, default: null },
})

const { state, actions } = useStore()

const filterStep = computed(() => {
  if (props.attribute) {
    return state.filterValues.find(f => f.attribute_id === props.attribute)
  }
  return state.filterValues.find(f => f.widget === 'filter-dropdown')
})

const selectedValue = computed(() => {
  const key = filterStep.value?.key
  return key ? state.selections[key] : null
})

function onSelect(e) {
  const key = filterStep.value?.key
  if (key) {
    const value = e.target.value
    if (value) {
      actions.setSelection(key, value)
    } else {
      actions.clearSelection(key)
    }
  }
}
</script>

<template>
  <div class="pe-dropdown" v-if="filterStep">
    <label class="pe-dropdown__label">{{ filterStep.label }}</label>
    <select class="pe-dropdown__select" :value="selectedValue || ''" @change="onSelect">
      <option value="">— Bitte waehlen —</option>
      <option v-for="item in filterStep.values" :key="item.value" :value="item.value">
        {{ item.label }} ({{ item.count }})
      </option>
    </select>
  </div>
</template>
