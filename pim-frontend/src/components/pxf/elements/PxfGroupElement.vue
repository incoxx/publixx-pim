<script setup>
import { computed, defineAsyncComponent } from 'vue'
import { resolveBinding, checkVisibility } from '../PxfDataResolver.js'

const PxfPage = defineAsyncComponent(() => import('../PxfPage.vue'))

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
})

const iter = computed(() => props.element.iterator || {})
const items = computed(() => {
  const source = iter.value.source || props.element.bind
  const val = resolveBinding(props.data, source) || []
  const arr = Array.isArray(val) ? val : []
  return arr.slice(0, iter.value.maxItems || 10)
})

const direction = computed(() => iter.value.direction || 'horizontal')
const gap = computed(() => iter.value.gap || 10)
const itemSize = computed(() => props.element.itemSize || { w: 200, h: 200 })

const containerStyle = computed(() => ({
  display: 'flex',
  flexDirection: direction.value === 'vertical' ? 'column' : 'row',
  flexWrap: direction.value === 'grid' ? 'wrap' : 'nowrap',
  gap: gap.value + 'px',
  width: '100%',
  height: '100%',
  overflow: 'hidden',
}))
</script>

<template>
  <div :style="containerStyle">
    <div
      v-for="(item, i) in items"
      :key="i"
      :style="{
        width: itemSize.w + 'px',
        height: itemSize.h + 'px',
        position: 'relative',
        flexShrink: 0,
      }"
    >
      <!-- Render children with item data scope -->
      <template v-for="child in (element.children || [])" :key="child.id">
        <component
          v-if="checkVisibility(item, child.visibility)"
          :is="child.type === 'text' || child.type === 'fixedText' ? 'PxfTextElement' : 'div'"
          :element="child"
          :data="item"
          :config="config"
          :style="{
            position: 'absolute',
            left: (child.x || 0) + 'px',
            top: (child.y || 0) + 'px',
            width: (child.width || child.w || 100) + 'px',
            height: (child.height || child.h || 40) + 'px',
          }"
        />
      </template>
    </div>
  </div>
</template>
