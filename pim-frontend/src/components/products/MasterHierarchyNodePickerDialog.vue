<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { X, Search, FolderTree, Loader2, Check } from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'
import { useLocalizedName } from '@/composables/useLocalizedName'

const props = defineProps({
  open: { type: Boolean, default: false },
  currentNodeId: { type: String, default: null },
  currentHierarchyId: { type: String, default: null },
})

const { t } = useI18n()
const { localizedName } = useLocalizedName()
const emit = defineEmits(['update:open', 'select'])

const hierarchies = ref([])
const selectedHierarchyId = ref('')
const flatNodes = ref([])
const loading = ref(false)
const loadingTree = ref(false)
const error = ref('')
const searchQuery = ref('')
const selectedNodeId = ref(null)
const selectedNode = ref(null)
const expandedNodes = ref(new Set())

watch(() => props.open, async (isOpen) => {
  if (!isOpen) return
  error.value = ''
  selectedHierarchyId.value = ''
  selectedNodeId.value = props.currentNodeId || null
  selectedNode.value = null
  flatNodes.value = []
  searchQuery.value = ''
  expandedNodes.value = new Set()
  await loadHierarchies()

  if (props.currentHierarchyId) {
    selectedHierarchyId.value = props.currentHierarchyId
  } else if (hierarchies.value.length === 1) {
    selectedHierarchyId.value = hierarchies.value[0].id
  }
})

watch(selectedHierarchyId, async (id) => {
  if (!id) { flatNodes.value = []; return }
  await loadTree(id)
})

async function loadHierarchies() {
  loading.value = true
  try {
    const { data } = await hierarchiesApi.list({ perPage: 100 })
    hierarchies.value = data.data || data || []
  } catch {
    error.value = t('Hierarchien konnten nicht geladen werden')
  } finally {
    loading.value = false
  }
}

function flattenTree(nodes, depth = 0) {
  const result = []
  for (const node of nodes) {
    result.push({ ...node, depth })
    if (node.children?.length) {
      result.push(...flattenTree(node.children, depth + 1))
    }
  }
  return result
}

function findAndExpandAncestors(nodes, targetId, ancestors = []) {
  for (const node of nodes) {
    if (node.id === targetId) {
      ancestors.forEach(id => expandedNodes.value.add(id))
      return true
    }
    if (node.children?.length && findAndExpandAncestors(node.children, targetId, [...ancestors, node.id])) {
      return true
    }
  }
  return false
}

async function loadTree(hierarchyId) {
  loadingTree.value = true
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    const raw = data.data || data || []
    expandedNodes.value = new Set(raw.map(n => n.id))
    if (selectedNodeId.value) {
      findAndExpandAncestors(raw, selectedNodeId.value)
    }
    flatNodes.value = flattenTree(raw)
  } catch {
    error.value = t('Baum konnte nicht geladen werden')
  } finally {
    loadingTree.value = false
  }
}

function toggleExpand(nodeId) {
  const s = new Set(expandedNodes.value)
  if (s.has(nodeId)) s.delete(nodeId)
  else s.add(nodeId)
  expandedNodes.value = s
}

const visibleNodes = computed(() => {
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    return flatNodes.value.filter(n => {
      const name = (n.name_de || n.name_en || n.technical_name || '').toLowerCase()
      return name.includes(q)
    })
  }

  const result = []
  const visibleDepths = [true]
  for (const node of flatNodes.value) {
    if (node.depth === 0 || visibleDepths[node.depth]) {
      result.push(node)
      visibleDepths[node.depth + 1] = expandedNodes.value.has(node.id)
    }
  }
  return result
})

function selectNode(node) {
  selectedNodeId.value = node.id
  selectedNode.value = node
}

function hasChildren(node) {
  return node.children && node.children.length > 0
}

function confirm() {
  if (!selectedNodeId.value || !selectedNode.value) return
  const hierarchy = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
  emit('select', {
    id: selectedNode.value.id,
    name_de: selectedNode.value.name_de,
    name_en: selectedNode.value.name_en,
    hierarchy_id: selectedHierarchyId.value,
    hierarchy: hierarchy ? { id: hierarchy.id, name_de: hierarchy.name_de, name_en: hierarchy.name_en } : null,
  })
  close()
}

function close() {
  emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[10vh]"
        @keydown.escape="close"
      >
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-[540px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Master-Hierarchie-Knoten auswählen</h3>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <div class="px-5 py-4 space-y-3">
            <div v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</div>

            <!-- Hierarchy selection -->
            <div>
              <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Hierarchie</label>
              <select v-model="selectedHierarchyId" class="pim-input text-sm w-full">
                <option value="">— Hierarchie wählen —</option>
                <option v-for="h in hierarchies" :key="h.id" :value="h.id">
                  {{ localizedName(h) || h.technical_name }}
                </option>
              </select>
            </div>

            <!-- Node tree -->
            <template v-if="selectedHierarchyId">
              <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                <input
                  v-model="searchQuery"
                  type="text"
                  class="pim-input text-xs w-full pim-input-icon"
                  placeholder="Knoten suchen..."
                />
              </div>

              <div class="max-h-[40vh] overflow-y-auto border border-[var(--color-border)] rounded-lg">
                <div v-if="loadingTree" class="flex items-center justify-center py-8 text-[var(--color-text-tertiary)]">
                  <Loader2 class="w-5 h-5 animate-spin" />
                </div>

                <div v-else-if="visibleNodes.length === 0" class="text-sm text-[var(--color-text-tertiary)] text-center py-8">
                  Keine Knoten gefunden.
                </div>

                <div v-else class="p-1">
                  <button
                    v-for="node in visibleNodes"
                    :key="node.id"
                    type="button"
                    class="w-full flex items-center gap-1 px-2 py-1.5 rounded text-left text-xs transition-colors"
                    :class="selectedNodeId === node.id
                      ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)] font-medium'
                      : 'hover:bg-[var(--color-bg)] text-[var(--color-text-primary)]'"
                    :style="{ paddingLeft: (node.depth * 20 + 8) + 'px' }"
                    @click="selectNode(node)"
                  >
                    <span
                      v-if="hasChildren(node)"
                      class="w-4 h-4 flex items-center justify-center shrink-0 rounded hover:bg-[var(--color-border)]"
                      @click.stop="toggleExpand(node.id)"
                    >
                      <svg v-if="expandedNodes.has(node.id)" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                      <svg v-else class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </span>
                    <span v-else class="w-4 shrink-0" />
                    <FolderTree class="w-3.5 h-3.5 shrink-0 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                    <span class="truncate">{{ localizedName(node) || node.technical_name }}</span>
                  </button>
                </div>
              </div>

              <div v-if="selectedNode" class="text-xs text-[var(--color-text-secondary)] flex items-center gap-1">
                <Check class="w-3.5 h-3.5 text-[var(--color-accent)]" />
                <strong>{{ localizedName(selectedNode) || selectedNode.id }}</strong>
              </div>
            </template>
          </div>

          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!selectedNodeId"
              @click="confirm"
            >
              <FolderTree class="w-3.5 h-3.5" :stroke-width="1.75" />
              Übernehmen
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
