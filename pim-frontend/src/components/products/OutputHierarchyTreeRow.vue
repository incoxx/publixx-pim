<script setup>
import { computed } from 'vue'
import { ChevronDown, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
  nodeState: { type: Function, required: true },
  disabledSet: { type: Object, required: true },
  expandedIds: { type: Object, required: true },
  matchesFilter: { type: Function, required: true },
  forceExpanded: { type: Function, required: true },
  nodeLabel: { type: Function, required: true },
})

const emit = defineEmits(['toggle-node', 'toggle-expand'])

const hasChildren = computed(() => props.node.children && props.node.children.length > 0)
const isExpanded = computed(() => props.expandedIds.has(props.node.id) || props.forceExpanded(props.node))
const state = computed(() => props.nodeState(props.node))
// Nur einzelne, bereits zugeordnete Blattknoten sind nicht (ab-)wählbar — bei
// zusammengesetzten Knoten bleibt das Toggle sinnvoll (wählt noch offene Kinder).
const isDisabled = computed(() => !hasChildren.value && props.disabledSet.has(props.node.id))
const visibleChildren = computed(() => (props.node.children || []).filter(props.matchesFilter))
const indent = computed(() => `${props.depth * 16 + 8}px`)
</script>

<template>
  <div>
    <div
      class="flex items-center gap-1.5 px-2 py-1 hover:bg-[var(--color-bg-hover)]"
      :style="{ paddingLeft: indent }"
    >
      <button
        v-if="hasChildren"
        type="button"
        class="p-0.5 cursor-pointer shrink-0"
        @click="emit('toggle-expand', node.id)"
      >
        <component :is="isExpanded ? ChevronDown : ChevronRight" class="w-3 h-3 text-[var(--color-text-tertiary)]" />
      </button>
      <span v-else class="w-4 shrink-0" />

      <input
        type="checkbox"
        :checked="state === 'checked'"
        :indeterminate="state === 'indeterminate'"
        :disabled="isDisabled"
        class="cursor-pointer disabled:cursor-not-allowed"
        @change="emit('toggle-node', node)"
      >

      <span
        class="text-xs cursor-pointer truncate"
        :class="isDisabled ? 'text-[var(--color-text-tertiary)]' : 'text-[var(--color-text-primary)]'"
        @click="!isDisabled && emit('toggle-node', node)"
      >{{ nodeLabel(node) }}</span>

      <span v-if="isDisabled" class="text-[10px] text-[var(--color-text-tertiary)] shrink-0">bereits zugeordnet</span>
    </div>

    <template v-if="hasChildren && isExpanded">
      <OutputHierarchyTreeRow
        v-for="child in visibleChildren"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :node-state="nodeState"
        :disabled-set="disabledSet"
        :expanded-ids="expandedIds"
        :matches-filter="matchesFilter"
        :force-expanded="forceExpanded"
        :node-label="nodeLabel"
        @toggle-node="emit('toggle-node', $event)"
        @toggle-expand="emit('toggle-expand', $event)"
      />
    </template>
  </div>
</template>

<script>
export default {
  name: 'OutputHierarchyTreeRow',
}
</script>
