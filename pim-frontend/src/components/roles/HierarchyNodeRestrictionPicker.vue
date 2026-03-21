<script setup>
import { ref, computed, onMounted } from 'vue'
import { ChevronDown, ChevronRight, X, Lock } from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'
import TreeNodeRow from './TreeNodeRow.vue'

const props = defineProps({
  selectedRestrictions: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:restrictions'])

const expanded = ref(false)
const hierarchies = ref([])
const trees = ref({})
const loading = ref(false)
const expandedNodes = ref(new Set())

const accessLevelLabels = {
  read: 'Lesen',
  write: 'Schreiben',
  delete: 'Löschen',
}

const isRestricted = computed(() => props.selectedRestrictions.length > 0)
const selectedMap = computed(() => {
  const map = {}
  for (const r of props.selectedRestrictions) {
    map[r.id] = r
  }
  return map
})

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await hierarchiesApi.list({ perPage: 100 })
    const result = data.data || data
    hierarchies.value = Array.isArray(result) ? result : (result.data || [])
  } catch {
    // silent
  } finally {
    loading.value = false
  }
})

async function loadTree(hierarchyId) {
  if (trees.value[hierarchyId]) return
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    trees.value[hierarchyId] = data.data || []
  } catch {
    trees.value[hierarchyId] = []
  }
}

function toggleHierarchy(hierarchyId) {
  const key = 'h:' + hierarchyId
  if (expandedNodes.value.has(key)) {
    expandedNodes.value.delete(key)
  } else {
    expandedNodes.value.add(key)
    loadTree(hierarchyId)
  }
  expandedNodes.value = new Set(expandedNodes.value)
}

function toggleNodeExpand(nodeId) {
  const key = 'n:' + nodeId
  if (expandedNodes.value.has(key)) {
    expandedNodes.value.delete(key)
  } else {
    expandedNodes.value.add(key)
  }
  expandedNodes.value = new Set(expandedNodes.value)
}

function toggleNode(node) {
  if (selectedMap.value[node.id]) {
    emit('update:restrictions', props.selectedRestrictions.filter(r => r.id !== node.id))
  } else {
    emit('update:restrictions', [
      ...props.selectedRestrictions,
      { id: node.id, access_level: 'read', name: node.name_de || node.id },
    ])
  }
}

function updateAccessLevel(nodeId, level) {
  emit('update:restrictions', props.selectedRestrictions.map(r =>
    r.id === nodeId ? { ...r, access_level: level } : r
  ))
}

function clearAll() {
  emit('update:restrictions', [])
}
</script>

<template>
  <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
    <!-- Header -->
    <button
      class="w-full flex items-center gap-2 px-3 py-2 text-left cursor-pointer hover:bg-[var(--color-bg-hover)] transition-colors"
      @click="expanded = !expanded"
    >
      <component :is="expanded ? ChevronDown : ChevronRight" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
      <Lock v-if="isRestricted" class="w-3 h-3 text-[var(--color-warning)]" />
      <span class="text-xs font-medium text-[var(--color-text-primary)]">Hierarchie-Knoten</span>
      <span v-if="isRestricted" class="text-[10px] text-[var(--color-warning)] ml-auto">
        {{ selectedRestrictions.length }} Knoten
      </span>
      <span v-else class="text-[10px] text-[var(--color-text-tertiary)] ml-auto">Vollzugriff</span>
    </button>

    <!-- Content -->
    <div v-if="expanded" class="border-t border-[var(--color-border)] px-3 py-2 space-y-2">
      <!-- Selected nodes summary -->
      <div v-if="selectedRestrictions.length > 0" class="space-y-1 mb-2">
        <div
          v-for="restriction in selectedRestrictions"
          :key="restriction.id"
          class="flex items-center gap-2 py-1 px-2 rounded bg-[var(--color-bg-hover)]"
        >
          <span class="text-xs text-[var(--color-text-primary)] flex-1 truncate">{{ restriction.name }}</span>
          <select
            :value="restriction.access_level"
            class="pim-input text-[11px] py-0.5 px-1.5 min-w-0"
            @change="updateAccessLevel(restriction.id, $event.target.value)"
          >
            <option value="read">{{ accessLevelLabels.read }}</option>
            <option value="write">{{ accessLevelLabels.write }}</option>
            <option value="delete">{{ accessLevelLabels.delete }}</option>
          </select>
          <button
            class="p-0.5 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] cursor-pointer"
            @click="emit('update:restrictions', selectedRestrictions.filter(r => r.id !== restriction.id))"
          >
            <X class="w-3 h-3" />
          </button>
        </div>
        <button
          class="text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] cursor-pointer"
          @click="clearAll"
        >
          Alle entfernen
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-[11px] text-[var(--color-text-tertiary)]">Hierarchien werden geladen...</div>

      <!-- Hierarchy tree browser -->
      <div v-else class="max-h-60 overflow-y-auto border border-[var(--color-border)] rounded">
        <div v-for="hierarchy in hierarchies" :key="hierarchy.id">
          <button
            class="w-full flex items-center gap-1.5 px-2 py-1.5 text-left cursor-pointer hover:bg-[var(--color-bg-hover)] border-b border-[var(--color-border)]"
            @click="toggleHierarchy(hierarchy.id)"
          >
            <component
              :is="expandedNodes.has('h:' + hierarchy.id) ? ChevronDown : ChevronRight"
              class="w-3 h-3 text-[var(--color-text-tertiary)]"
            />
            <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ hierarchy.name_de }}</span>
            <span class="text-[10px] text-[var(--color-text-tertiary)] ml-auto">{{ hierarchy.hierarchy_type }}</span>
          </button>

          <div v-if="expandedNodes.has('h:' + hierarchy.id) && trees[hierarchy.id]">
            <TreeNodeRow
              v-for="node in trees[hierarchy.id]"
              :key="node.id"
              :node="node"
              :depth="0"
              :selected-map="selectedMap"
              :expanded-nodes="expandedNodes"
              @toggle-node="toggleNode"
              @toggle-expand="toggleNodeExpand"
            />
          </div>
        </div>
        <div v-if="hierarchies.length === 0 && !loading" class="px-2 py-1.5 text-[11px] text-[var(--color-text-tertiary)]">
          Keine Hierarchien vorhanden
        </div>
      </div>

      <p v-if="!loading && selectedRestrictions.length === 0" class="text-[10px] text-[var(--color-text-tertiary)]">
        Keine Einschränkungen — Rolle hat Vollzugriff auf alle Knoten und deren Kinder.
      </p>
    </div>
  </div>
</template>
