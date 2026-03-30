<script setup>
import { useStore } from '../store.js'

const props = defineProps({
  label: { type: String, default: null },
})

const { state, getters, actions } = useStore()

function onClick() {
  actions.submit()
}
</script>

<template>
  <button
    class="pe-submit"
    :class="{ 'pe-submit--disabled': !getters.isComplete.value }"
    :disabled="!getters.isComplete.value"
    @click="onClick"
  >
    {{ getters.isComplete.value ? (props.label || 'Weiter') : 'Bitte Auswahl treffen' }}
    <svg v-if="getters.isComplete.value" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
  </button>
</template>
