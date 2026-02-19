<script setup>
import { computed } from 'vue'

const props = defineProps({
  element: { type: Object, required: true },
})

const isVertical = computed(() => (props.element.style?.lineOrientation || 'horizontal') === 'vertical')

const style = computed(() => {
  const s = props.element.style || {}
  const color = s.lineColor || '#111111'
  const width = s.lineWidth || 1
  if (isVertical.value) {
    return {
      width: width + 'px',
      height: '100%',
      backgroundColor: color,
      margin: '0 auto',
    }
  }
  return {
    width: '100%',
    height: width + 'px',
    backgroundColor: color,
    margin: 'auto 0',
  }
})
</script>

<template>
  <div class="flex items-center justify-center w-full h-full">
    <div :style="style" />
  </div>
</template>
