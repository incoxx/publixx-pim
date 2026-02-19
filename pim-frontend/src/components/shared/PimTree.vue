<script setup>
import { ref, computed } from 'vue'
import { ChevronRight, ChevronDown, FolderOpen, Folder, MoreHorizontal, Plus } from 'lucide-vue-next'

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  selectedId: { type: String, default: null },
  expandedIds: { type: Set, default: () => new Set() },
  level: { type: Number, default: 0 },
  draggable: { type: Boolean, default: true },
})

const emit = defineEmits(['select', 'toggle', 'create', 'delete', 'move', 'context-menu'])

function isExpanded(node) {
  return props.expandedIds.has(node.id)
}

function hasChildren(node) {
  return node.children && node.children.length > 0
}

function handleSelect(node) {
  emit('select', node)
}

function handleToggle(node) {
  emit('toggle', node.id)
}

function handleDragStart(e, node) {
  if (!props.draggable) return
  e.dataTransfer.setData('text/plain', JSON.stringify({ nodeId: node.id }))
  e.dataTransfer.effectAllowed = 'move'
}

function handleDrop(e, targetNode) {
  e.preventDefault()
  try {
    const data = JSON.parse(e.dataTransfer.getData('text/plain'))
    if (data.nodeId !== targetNode.id) {
      emit('move', data.nodeId, targetNode.id)
    }
  } catch { /* ignore */ }
}

function handleDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'
}
</script>

<template>
  <ul :class="level === 0 ? 'space-y-0.5' : ''">
    <li
      v-for="node in nodes"
      :key="node.id"
    >
      <div
        :class="[
          'group flex items-center gap-1 px-2 py-[5px] rounded-md cursor-pointer transition-colors text-[13px]',
          selectedId === node.id
            ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)]'
            : 'text-[var(--color-text-primary)] hover:bg-[var(--color-bg)]',
        ]"
        :style="{ paddingLeft: (level * 20 + 8) + 'px' }"
        :draggable="draggable"
        @click="handleSelect(node)"
        @dragstart="handleDragStart($event, node)"
        @drop="handleDrop($event, node)"
        @dragover="handleDragOver"
      >
        <!-- Expand/collapse -->
        <button
          v-if="hasChildren(node)"
          class="p-0.5 rounded hover:bg-[var(--color-border)] shrink-0"
          @click.stop="handleToggle(node)"
        >
          <ChevronDown v-if="isExpanded(node)" class="w-3.5 h-3.5" :stroke-width="2" />
          <ChevronRight v-else class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <span v-else class="w-5 shrink-0" />

        <!-- Icon -->
        <FolderOpen
          v-if="isExpanded(node) && hasChildren(node)"
          class="w-4 h-4 shrink-0 text-[var(--color-accent)]"
          :stroke-width="1.75"
        />
        <Folder
          v-else
          class="w-4 h-4 shrink-0 text-[var(--color-text-tertiary)]"
          :stroke-width="1.75"
        />

        <!-- Label -->
        <span class="truncate flex-1">{{ node.name_de || node.name || node.label }}</span>

        <!-- Count badge -->
        <span
          v-if="node.product_count != null"
          class="text-[10px] text-[var(--color-text-tertiary)] bg-[var(--color-bg)] px-1.5 rounded-full"
        >
          {{ node.product_count }}
        </span>

        <!-- Context actions -->
        <button
          class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-[var(--color-border)] transition-opacity shrink-0"
          @click.stop="$emit('context-menu', $event, node)"
        >
          <MoreHorizontal class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
        </button>
      </div>

      <!-- Children (recursive) -->
      <transition name="fade">
        <PimTree
          v-if="hasChildren(node) && isExpanded(node)"
          :nodes="node.children"
          :selectedId="selectedId"
          :expandedIds="expandedIds"
          :level="level + 1"
          :draggable="draggable"
          @select="$emit('select', $event)"
          @toggle="$emit('toggle', $event)"
          @create="$emit('create', $event)"
          @delete="$emit('delete', $event)"
          @move="(s, t) => $emit('move', s, t)"
          @context-menu="(e, n) => $emit('context-menu', e, n)"
        />
      </transition>
    </li>
  </ul>
</template>
