<script setup>
import { computed } from 'vue'
import { resolveBinding, resolveAssetUrl } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
})

const src = computed(() => {
  const val = resolveBinding(props.data, props.element.bind) || ''
  const uc = props.element.urlConfig || {}
  let url = val
  if (uc.prefix) url = uc.prefix + url
  if (uc.suffix) url = url + uc.suffix
  if (uc.useAssetBase) url = resolveAssetUrl(url, props.config.assetBase)
  return url
})

const imgStyle = computed(() => {
  const is = props.element.imageStyle || {}
  const filters = []
  if (is.grayscale) filters.push('grayscale(1)')
  if (is.sepia) filters.push('sepia(1)')
  if (is.brightness != null && is.brightness !== 100) filters.push(`brightness(${is.brightness / 100})`)
  if (is.contrast != null && is.contrast !== 100) filters.push(`contrast(${is.contrast / 100})`)
  if (is.saturate != null && is.saturate !== 100) filters.push(`saturate(${is.saturate / 100})`)
  if (is.blur) filters.push(`blur(${is.blur}px)`)

  return {
    objectFit: is.objectFit || 'cover',
    objectPosition: is.objectPosition || 'center',
    width: '100%',
    height: '100%',
    opacity: is.opacity ?? 1,
    filter: filters.length ? filters.join(' ') : undefined,
  }
})

const containerStyle = computed(() => {
  const s = props.element.style || {}
  return {
    padding: (s.padding || 0) + 'px',
    backgroundColor: s.bg || 'transparent',
    width: '100%',
    height: '100%',
    boxSizing: 'border-box',
    overflow: 'hidden',
  }
})
</script>

<template>
  <div :style="containerStyle">
    <img
      v-if="src"
      :src="src"
      :style="imgStyle"
      loading="lazy"
      alt=""
    />
    <div
      v-else
      class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400 text-xs"
    >
      Kein Bild
    </div>
  </div>
</template>
