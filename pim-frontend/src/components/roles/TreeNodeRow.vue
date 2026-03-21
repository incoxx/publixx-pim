<script setup>
import { computed } from 'vue'
import { ChevronDown, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
  selectedMap: { type: Object, default: () => ({}) },
  expandedNodes: { type: Object, default: () => new Set() },
})

const emit = defineEmits(['toggle-node', 'toggle-expand'])

const hasChildren = computed(() => props.node.children && props.node.children.length > 0)
const isExpanded = computed(() => props.expandedNodes.has('n:' + props.node.id))
const isSelected = computed(() => !!props.selectedMap[props.node.id])
const indent = computed(() => `${(props.depth + 1) * 16 + 8}px`)
</script>

<template>
  <div>
    <div
      class="flex items-center gap-1.5 px-2 py-1 hover:bg-[var(--color-bg-hover)]"
      :style="{ paddingLeft: indent }"
    >
      <button
        v-if="hasChildren"
        class="p-0.5 cursor-pointer"
        @click="emit('toggle-expand', node.id)"
      >
        <component
          :is="isExpanded ? ChevronDown : ChevronRight"
          class="w-3 h-3 text-[var(--color-text-tertiary)]"
        />
      </button>
      <span v-else class="w-4" />

      <input
        type="checkbox"
        :checked="isSelected"
        class="cursor-pointer"
        @change="emit('toggle-node', node)"
      />

      <span
        class="text-xs cursor-pointer"
        :class="isSelected ? 'text-[var(--color-accent)] font-medium' : 'text-[var(--color-text-primary)]'"
        @click="emit('toggle-node', node)"
      >
        {{ node.name_de || node.id }}
      </span>
    </div>

    <template v-if="hasChildren && isExpanded">
      <TreeNodeRow
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :selected-map="selectedMap"
        :expanded-nodes="expandedNodes"
        @toggle-node="emit('toggle-node', $event)"
        @toggle-expand="emit('toggle-expand', $event)"
      />
    </template>
  </div>
</template>

<script>
export default {
  name: 'TreeNodeRow',
}
</script>
