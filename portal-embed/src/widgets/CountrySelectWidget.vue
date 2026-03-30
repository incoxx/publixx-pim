<script setup>
import { ref, computed } from 'vue'
import { useStore } from '../store.js'

const { state, actions } = useStore()
const searchTerm = ref('')

const filterStep = computed(() =>
  state.filterValues.find(f => f.widget === 'country-select') || state.filterValues[0]
)

const filteredValues = computed(() => {
  const values = filterStep.value?.values || []
  const term = searchTerm.value.toLowerCase()
  if (!term) return values
  return values.filter(v =>
    v.value.toLowerCase().includes(term) || v.label.toLowerCase().includes(term)
  )
})

const selectedValue = computed(() => {
  const key = filterStep.value?.key
  return key ? state.selections[key] : null
})

function countryFlag(code) {
  if (!code || code.length !== 2) return ''
  return String.fromCodePoint(
    ...[...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65)
  )
}

function select(value) {
  const key = filterStep.value?.key
  if (key) actions.setSelection(key, value)
}
</script>

<template>
  <div class="pe-country" v-if="filterStep">
    <h3 class="pe-country__title">{{ filterStep.label }}</h3>

    <div class="pe-country__search">
      <input v-model="searchTerm" type="text" placeholder="Land suchen..." class="pe-country__input" />
    </div>

    <div class="pe-country__list">
      <button
        v-for="item in filteredValues"
        :key="item.value"
        class="pe-country__item"
        :class="{ 'pe-country__item--active': selectedValue === item.value }"
        @click="select(item.value)"
      >
        <span class="pe-country__flag">{{ countryFlag(item.value) }}</span>
        <span class="pe-country__name">{{ item.label }}</span>
        <span class="pe-country__count">{{ item.count }}</span>
      </button>
    </div>
  </div>
</template>
