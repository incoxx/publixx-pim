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
  return state.filterValues.find(f => f.widget === 'filter-cards')
})

const selectedValue = computed(() => {
  const key = filterStep.value?.key
  return key ? state.selections[key] : null
})

function select(value) {
  const key = filterStep.value?.key
  if (key) actions.setSelection(key, value)
}
</script>

<template>
  <div class="pe-cards" v-if="filterStep">
    <h3 class="pe-cards__title">{{ filterStep.label }}</h3>
    <div class="pe-cards__grid">
      <button
        v-for="item in filterStep.values"
        :key="item.value"
        class="pe-cards__card"
        :class="{ 'pe-cards__card--active': selectedValue === item.value }"
        @click="select(item.value)"
      >
        <span class="pe-cards__label">{{ item.label }}</span>
        <span class="pe-cards__count">{{ item.count }} Produkte</span>
      </button>
    </div>
  </div>
</template>
