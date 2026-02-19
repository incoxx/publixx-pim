<script setup>
import { computed, defineAsyncComponent } from 'vue'
import { resolveBinding } from '../PxfDataResolver.js'

const PxfPage = defineAsyncComponent(() => import('../PxfPage.vue'))

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
})

const soc = computed(() => props.element.smartObjectConfig || {})

const scopedData = computed(() => {
  if (soc.value.dataPath) {
    return resolveBinding(props.data, soc.value.dataPath) || {}
  }
  return props.data
})

const elements = computed(() => props.element.children || props.element.elements || [])
</script>

<template>
  <div class="w-full h-full relative overflow-hidden">
    <PxfPage
      :elements="elements"
      :data="scopedData"
      :config="config"
      :width="parseInt(element.width || element.w || 200)"
      :height="parseInt(element.height || element.h || 200)"
      :zoom="1"
    />
  </div>
</template>
