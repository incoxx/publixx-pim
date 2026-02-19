<script setup>
import { ref, onMounted } from 'vue'
import { useHierarchyStore } from '@/stores/hierarchies'
import { useI18n } from 'vue-i18n'
import { Plus, RefreshCw } from 'lucide-vue-next'
import PimTree from '@/components/shared/PimTree.vue'

const { t } = useI18n()
const store = useHierarchyStore()
const selectedHierarchyId = ref(null)

async function selectHierarchy(id) {
  selectedHierarchyId.value = id
  await store.fetchTree(id)
}

function handleSelect(node) {
  store.selectNode(node)
}

function handleToggle(nodeId) {
  store.toggleExpanded(nodeId)
}

async function handleMove(sourceId, targetId) {
  await store.moveNode(sourceId, targetId, 0)
  if (selectedHierarchyId.value) {
    await store.fetchTree(selectedHierarchyId.value)
  }
}

onMounted(async () => {
  await store.fetchHierarchies()
  if (store.hierarchies.length > 0) {
    await selectHierarchy(store.hierarchies[0].id)
  }
})
</script>

<template>
  <div class="flex gap-4 h-[calc(100vh-140px)]">
    <!-- Left: Tree -->
    <div class="w-[320px] shrink-0 pim-card flex flex-col overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ t('hierarchy.title') }}</h3>
        <button class="pim-btn pim-btn-ghost p-1">
          <Plus class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>
      <!-- Hierarchy selector -->
      <div class="px-3 py-2 border-b border-[var(--color-border)]">
        <select
          class="pim-input text-xs"
          :value="selectedHierarchyId"
          @change="selectHierarchy($event.target.value)"
        >
          <option v-for="h in store.hierarchies" :key="h.id" :value="h.id">
            {{ h.name_de || h.name }}
          </option>
        </select>
      </div>
      <!-- Tree -->
      <div class="flex-1 overflow-y-auto p-2">
        <div v-if="store.loading" class="space-y-2 p-2">
          <div v-for="i in 6" :key="i" class="pim-skeleton h-6 rounded" :style="{ width: (50 + Math.random() * 50) + '%' }" />
        </div>
        <PimTree
          v-else
          :nodes="store.tree"
          :selectedId="store.selectedNode?.id"
          :expandedIds="store.expandedNodes"
          @select="handleSelect"
          @toggle="handleToggle"
          @move="handleMove"
        />
      </div>
    </div>

    <!-- Right: Node detail -->
    <div class="flex-1 pim-card overflow-y-auto">
      <div v-if="store.selectedNode" class="p-6 space-y-4">
        <h3 class="text-base font-semibold text-[var(--color-text-primary)]">
          {{ store.selectedNode.name_de || store.selectedNode.name }}
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Code</span>
            <p class="font-mono text-xs">{{ store.selectedNode.code || '—' }}</p>
          </div>
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Produkte</span>
            <p>{{ store.selectedNode.product_count ?? 0 }}</p>
          </div>
        </div>
        <div class="border-t border-[var(--color-border)] pt-4">
          <h4 class="text-sm font-medium text-[var(--color-text-secondary)] mb-3">Zugeordnete Attribute</h4>
          <p class="text-xs text-[var(--color-text-tertiary)]">Attribute werden hier angezeigt und verwaltet.</p>
        </div>
      </div>
      <div v-else class="flex items-center justify-center h-full">
        <p class="text-sm text-[var(--color-text-tertiary)]">Knoten auswählen</p>
      </div>
    </div>
  </div>
</template>
