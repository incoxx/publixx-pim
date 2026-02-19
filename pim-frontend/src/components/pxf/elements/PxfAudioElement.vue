<script setup>
import { computed } from 'vue'
import { resolveBinding, resolveAssetUrl } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
})

const ac = computed(() => props.element.audioConfig || {})

const src = computed(() => {
  const val = resolveBinding(props.data, ac.value.srcBind) || ac.value.src || ''
  return resolveAssetUrl(val, props.config.assetBase)
})
</script>

<template>
  <div class="w-full h-full flex items-center justify-center p-2">
    <audio
      v-if="src"
      :src="src"
      :autoplay="ac.autoplay"
      :loop="ac.loop"
      controls
      class="w-full"
    />
    <span v-else class="text-xs text-gray-400">Kein Audio</span>
  </div>
</template>
