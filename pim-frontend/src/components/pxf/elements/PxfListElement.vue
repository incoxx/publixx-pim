<script setup>
import { computed } from 'vue'
import { resolveBinding } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
})

const items = computed(() => {
  const val = resolveBinding(props.data, props.element.bind) || []
  return Array.isArray(val) ? val : []
})

const ls = computed(() => props.element.listStyle || {})
const s = computed(() => props.element.style || {})

const containerStyle = computed(() => ({
  padding: (s.value.padding || ls.value.padding || 8) + 'px',
  fontSize: (s.value.fontSize || 12) + 'px',
  color: s.value.textColor || '#111111',
  width: '100%',
  height: '100%',
  boxSizing: 'border-box',
  overflow: 'hidden',
}))

const bullet = computed(() => {
  const type = ls.value.bulletType || 'disc'
  if (type === 'custom') return ls.value.customBullet || '•'
  if (type === 'disc') return '•'
  if (type === 'circle') return '○'
  if (type === 'square') return '■'
  return ''
})
</script>

<template>
  <div :style="containerStyle">
    <div
      v-for="(item, i) in items"
      :key="i"
      class="flex"
      :style="{ marginBottom: (ls.itemSpacing || 4) + 'px' }"
    >
      <span
        v-if="ls.bulletType !== 'none'"
        :style="{
          color: ls.bulletColor || s.textColor || '#111111',
          marginRight: (ls.bulletTextGap || 8) + 'px',
          marginLeft: (ls.bulletIndent || 0) + 'px',
          flexShrink: 0,
        }"
      >{{ bullet }}</span>
      <span>{{ typeof item === 'string' ? item : item.label || item.value || JSON.stringify(item) }}</span>
    </div>
  </div>
</template>
