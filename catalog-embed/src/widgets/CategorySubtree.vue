<script setup>
import { icons } from '../icons.js'
import { useStore } from '../store.js'

defineProps({
  nodes: { type: Array, required: true },
  level: { type: Number, required: true },
  expandedNodes: { type: Object, required: true },
  selectedCategoryId: { type: String, default: null },
})

const emit = defineEmits(['toggle', 'select'])

const { state } = useStore()
const hasFilters = () => Object.keys(state.activeFilters).length > 0
function dynamicCount(node) {
  if (!hasFilters() || !state.categoryCounts || Object.keys(state.categoryCounts).length === 0) {
    return node.product_count
  }
  return state.categoryCounts[node.id] ?? 0
}
function isEmpty(node) {
  return hasFilters() && Object.keys(state.categoryCounts).length > 0 && dynamicCount(node) === 0
}
</script>

<template>
  <template v-for="node in nodes" :key="node.id">
    <div class="pxc-categories__node" :style="{ paddingLeft: (level * 16) + 'px' }">
      <div class="pxc-categories__row">
        <button
          v-if="node.children && node.children.length"
          class="pxc-categories__toggle"
          @click="emit('toggle', node.id)"
          v-html="expandedNodes[node.id] ? icons.chevronDown : icons.chevronRight"
        ></button>
        <span v-else class="pxc-categories__toggle-space"></span>
        <button
          class="pxc-categories__item"
          :class="{
            'pxc-categories__item--active': selectedCategoryId === node.id,
            'pxc-categories__item--empty': isEmpty(node),
          }"
          @click="emit('select', node)"
        >
          {{ node.name }}
          <span v-if="dynamicCount(node)" class="pxc-categories__count">{{ dynamicCount(node) }}</span>
        </button>
      </div>
    </div>
    <template v-if="expandedNodes[node.id] && node.children && node.children.length">
      <category-subtree
        :nodes="node.children"
        :level="level + 1"
        :expanded-nodes="expandedNodes"
        :selected-category-id="selectedCategoryId"
        @toggle="emit('toggle', $event)"
        @select="emit('select', $event)"
      />
    </template>
  </template>
</template>
