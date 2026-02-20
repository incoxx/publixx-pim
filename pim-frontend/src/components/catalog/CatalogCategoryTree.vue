<script setup>
import { ref } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import { ChevronRight, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  level: { type: Number, default: 0 },
})

const store = useCatalogStore()
const expandedIds = ref(new Set())

function toggle(nodeId) {
  const next = new Set(expandedIds.value)
  if (next.has(nodeId)) {
    next.delete(nodeId)
  } else {
    next.add(nodeId)
  }
  expandedIds.value = next
}

function selectCategory(node) {
  store.setCategory(node.id, node.name)
  store.fetchProducts()
}
</script>

<template>
  <ul :class="level === 0 ? '' : 'ml-3 border-l border-base-300 pl-1'">
    <li v-for="node in nodes" :key="node.id" class="my-0.5">
      <div
        class="flex items-center gap-1 py-1.5 px-2 rounded-lg cursor-pointer transition-all duration-200 text-sm group"
        :class="
          store.selectedCategoryId === node.id
            ? 'bg-primary/10 text-primary font-medium'
            : 'hover:bg-base-200 text-base-content'
        "
        @click="selectCategory(node)"
      >
        <!-- Expand/collapse -->
        <button
          v-if="node.children && node.children.length > 0"
          class="btn btn-ghost btn-xs btn-circle flex-none opacity-50 group-hover:opacity-100"
          @click.stop="toggle(node.id)"
        >
          <ChevronDown v-if="expandedIds.has(node.id)" class="w-3.5 h-3.5" />
          <ChevronRight v-else class="w-3.5 h-3.5" />
        </button>
        <span v-else class="w-6 flex-none" />

        <span class="flex-1 truncate">{{ node.name }}</span>

        <span
          v-if="node.product_count > 0"
          class="badge badge-xs badge-ghost font-mono text-[10px] flex-none"
        >
          {{ node.product_count }}
        </span>
      </div>

      <!-- Recursive children -->
      <Transition name="tree-expand">
        <CatalogCategoryTree
          v-if="node.children && node.children.length > 0 && expandedIds.has(node.id)"
          :nodes="node.children"
          :level="level + 1"
        />
      </Transition>
    </li>
  </ul>
</template>

<style scoped>
.tree-expand-enter-active,
.tree-expand-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}
.tree-expand-enter-from,
.tree-expand-leave-to {
  opacity: 0;
  max-height: 0;
}
.tree-expand-enter-to,
.tree-expand-leave-from {
  opacity: 1;
  max-height: 800px;
}
</style>
