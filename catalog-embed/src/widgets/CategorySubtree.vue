<script setup>
import { icons } from '../icons.js'

defineProps({
  nodes: { type: Array, required: true },
  level: { type: Number, required: true },
  expandedNodes: { type: Object, required: true },
  selectedCategoryId: { type: String, default: null },
})

const emit = defineEmits(['toggle', 'select'])
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
          :class="{ 'pxc-categories__item--active': selectedCategoryId === node.id }"
          @click="emit('select', node)"
        >
          {{ node.name }}
          <span v-if="node.product_count" class="pxc-categories__count">{{ node.product_count }}</span>
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
