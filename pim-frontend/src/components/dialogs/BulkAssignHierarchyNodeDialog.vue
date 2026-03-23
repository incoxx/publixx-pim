<script setup>
import { ref, computed, watch } from 'vue'
import { X, Search, FolderTree, Loader2, Check } from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'assigned'])

const hierarchies = ref([])
const selectedHierarchyId = ref('')
const flatNodes = ref([])
const loading = ref(false)
const loadingTree = ref(false)
const assigning = ref(false)
const error = ref('')
const success = ref('')
const searchQuery = ref('')
const selectedNodeId = ref(null)
const selectedNodeLabel = ref('')
const expandedNodes = ref(new Set())
const isMaster = ref(false)

watch(() => props.open, async (isOpen) => {
  if (isOpen) {
    error.value = ''
    success.value = ''
    selectedHierarchyId.value = ''
    selectedNodeId.value = null
    selectedNodeLabel.value = ''
    flatNodes.value = []
    searchQuery.value = ''
    expandedNodes.value = new Set()
    isMaster.value = false
    await loadHierarchies()
  }
})

watch(selectedHierarchyId, async (id) => {
  if (!id) { flatNodes.value = []; return }
  const h = hierarchies.value.find(h => h.id === id)
  isMaster.value = h?.hierarchy_type === 'master'
  selectedNodeId.value = null
  selectedNodeLabel.value = ''
  await loadTree(id)
})

async function loadHierarchies() {
  loading.value = true
  try {
    const { data } = await hierarchiesApi.list({ perPage: 100 })
    hierarchies.value = data.data || data || []
  } catch {
    error.value = 'Hierarchien konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

function flattenTree(nodes, depth = 0, parentExpanded = true) {
  const result = []
  for (const node of nodes) {
    result.push({ ...node, depth, visible: parentExpanded })
    if (node.children && node.children.length > 0) {
      const isExpanded = expandedNodes.value.has(node.id)
      result.push(...flattenTree(node.children, depth + 1, parentExpanded && isExpanded))
    }
  }
  return result
}

async function loadTree(hierarchyId) {
  loadingTree.value = true
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    const raw = data.data || data || []
    // Auto-expand root
    expandedNodes.value = new Set(raw.map(n => n.id))
    flatNodes.value = flattenTree(raw)
  } catch {
    error.value = 'Baum konnte nicht geladen werden'
  } finally {
    loadingTree.value = false
  }
}

function toggleExpand(nodeId) {
  const s = new Set(expandedNodes.value)
  if (s.has(nodeId)) s.delete(nodeId)
  else s.add(nodeId)
  expandedNodes.value = s
  // Re-flatten won't help since we stored flat. Instead recompute visibility.
}

const visibleNodes = computed(() => {
  if (!searchQuery.value) {
    // Show based on expanded state
    const visible = []
    const hiddenParents = new Set()
    for (const node of flatNodes.value) {
      if (node.depth === 0) {
        visible.push(node)
      } else {
        // Check all ancestors are expanded
        let show = true
        // Simple: check if any ancestor depth < this depth is collapsed
        // We can check by tracking a "hidden depth" approach
        let parentCollapsed = false
        for (const parent of visible) {
          // not efficient but good enough for trees
        }
        // Simpler approach: just use expanded set
        visible.push({ ...node, hidden: hiddenParents.has(node.depth) })
      }
    }
    // Actually let's just filter properly
    const result = []
    const visibleDepths = [true] // depth 0 is always visible
    for (const node of flatNodes.value) {
      if (node.depth === 0 || visibleDepths[node.depth]) {
        result.push(node)
        // Children are visible if this node is expanded
        visibleDepths[node.depth + 1] = expandedNodes.value.has(node.id)
      }
    }
    return result
  }

  // Search mode: show all matching nodes
  const q = searchQuery.value.toLowerCase()
  return flatNodes.value.filter(n => {
    const name = (n.name_de || n.name_en || n.technical_name || '').toLowerCase()
    return name.includes(q)
  })
})

function selectNode(node) {
  selectedNodeId.value = node.id
  selectedNodeLabel.value = node.name_de || node.name_en || node.technical_name || node.id
}

function hasChildren(node) {
  return node.children && node.children.length > 0
}

async function assign() {
  if (!selectedNodeId.value || props.productIds.length === 0) return
  assigning.value = true
  error.value = ''
  success.value = ''
  try {
    let data
    if (isMaster.value) {
      const res = await hierarchiesApi.bulkAssignMasterProducts(selectedNodeId.value, props.productIds)
      data = res.data
    } else {
      const res = await hierarchiesApi.bulkAssignOutputProducts(selectedNodeId.value, props.productIds)
      data = res.data
    }
    success.value = data.message || `${props.productIds.length} Produkt(e) zugeordnet.`
    emit('assigned')
    setTimeout(() => close(), 1500)
  } catch (e) {
    error.value = e.response?.data?.message || 'Zuordnung fehlgeschlagen'
  } finally {
    assigning.value = false
  }
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
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <!-- Panel -->
        <div class="relative z-10 w-full max-w-[540px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Hierarchie-Knoten zuordnen</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ productIds.length }} Produkt{{ productIds.length !== 1 ? 'e' : '' }} ausgewählt</p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="close">
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-5 py-4 space-y-3">
            <div v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</div>
            <div v-if="success" class="text-xs text-[var(--color-success)] flex items-center gap-1.5">
              <Check class="w-3.5 h-3.5" :stroke-width="2" />
              {{ success }}
            </div>

            <!-- Hierarchy selection -->
            <div>
              <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Hierarchie</label>
              <select v-model="selectedHierarchyId" class="pim-input text-sm w-full">
                <option value="">— Hierarchie wählen —</option>
                <option v-for="h in hierarchies" :key="h.id" :value="h.id">
                  {{ h.name_de || h.technical_name }}
                  ({{ h.hierarchy_type === 'master' ? 'Master' : 'Ausgabe' }})
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
                    <span class="truncate">{{ node.name_de || node.name_en || node.technical_name }}</span>
                    <span v-if="node.products_count != null" class="text-[10px] text-[var(--color-text-tertiary)] ml-auto shrink-0">
                      {{ node.products_count }}
                    </span>
                  </button>
                </div>
              </div>

              <!-- Selected indicator -->
              <div v-if="selectedNodeLabel" class="text-xs text-[var(--color-text-secondary)] flex items-center gap-1">
                <Check class="w-3.5 h-3.5 text-[var(--color-accent)]" />
                <strong>{{ selectedNodeLabel }}</strong>
                <span class="text-[var(--color-text-tertiary)]">({{ isMaster ? 'Master' : 'Ausgabe' }})</span>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!selectedNodeId || assigning"
              @click="assign"
            >
              <Loader2 v-if="assigning" class="w-3.5 h-3.5 animate-spin" />
              <FolderTree v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              {{ assigning ? 'Zuordnen...' : 'Zuordnen' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
