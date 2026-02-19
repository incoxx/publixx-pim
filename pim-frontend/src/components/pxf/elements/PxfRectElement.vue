<script setup>
import { computed } from 'vue'

const props = defineProps({
  element: { type: Object, required: true },
})

const style = computed(() => {
  const s = props.element.style || {}
  return {
    width: '100%',
    height: '100%',
    backgroundColor: s.fillColor || 'transparent',
    border: s.strokeWidth ? `${s.strokeWidth}px solid ${s.strokeColor || '#000'}` : 'none',
    borderRadius: (s.radius || 0) + 'px',
    boxSizing: 'border-box',
  }
})
</script>

<template>
  <div :style="style" />
</template>
