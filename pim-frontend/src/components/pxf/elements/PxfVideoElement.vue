<script setup>
import { computed } from 'vue'
import { resolveBinding, resolveAssetUrl } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
})

const vc = computed(() => props.element.videoConfig || {})

const src = computed(() => {
  const val = resolveBinding(props.data, vc.value.srcBind) || vc.value.src || ''
  return resolveAssetUrl(val, props.config.assetBase)
})
</script>

<template>
  <video
    v-if="src"
    :src="src"
    :autoplay="vc.autoplay"
    :muted="vc.muted ?? true"
    :controls="vc.controls ?? true"
    :style="{ objectFit: vc.objectFit || 'contain', width: '100%', height: '100%' }"
  />
  <div v-else class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400 text-xs">
    Kein Video
  </div>
</template>
