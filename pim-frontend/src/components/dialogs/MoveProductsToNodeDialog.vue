<script setup>
import { ref, computed, watch } from 'vue'
import { X, Search, ArrowRightLeft, Loader2, Check, AlertTriangle } from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
  hierarchyId: { type: String, default: null },
  isMasterHierarchy: { type: Boolean, default: false },
  sourceNodeId: { type: String, default: null },
  sourceAssignmentIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open', 'moved'])

const flatNodes = ref([])
const loadingTree = ref(false)
const moving = ref(false)
const error = ref('')
const success = ref('')
const searchQuery = ref('')
const selectedNodeId = ref(null)
const selectedNodeLabel = ref('')

// Attribut-Warnung (nur Master)
const attributeWarning = ref('')
const checkingAttributes = ref(false)

watch(() => props.open, async (isOpen) => {
  if (isOpen && props.hierarchyId) {
    error.value = ''
    success.value = ''
    selectedNodeId.value = null
    selectedNodeLabel.value = ''
    searchQuery.value = ''
    attributeWarning.value = ''
    await loadTree()
  }
})

async function loadTree() {
  loadingTree.value = true
  try {
    const { data } = await hierarchiesApi.getTree(props.hierarchyId)
    const tree = data.data || data
    flatNodes.value = flattenTree(tree)
  } catch (e) {
    error.value = 'Hierarchie konnte nicht geladen werden'
  } finally {
    loadingTree.value = false
  }
}

function flattenTree(nodes, depth = 0) {
  const result = []
  for (const node of nodes) {
    result.push({ ...node, _depth: depth })
    if (node.children?.length) {
      result.push(...flattenTree(node.children, depth + 1))
    }
  }
  return result
}

const filteredNodes = computed(() => {
  if (!searchQuery.value) return flatNodes.value
  const q = searchQuery.value.toLowerCase()
  return flatNodes.value.filter(n => {
    const name = (n.name_de || n.name_en || '').toLowerCase()
    return name.includes(q)
  })
})

async function selectNode(node) {
  if (node.id === props.sourceNodeId) return
  selectedNodeId.value = node.id
  selectedNodeLabel.value = node.name_de || node.name_en || node.id
  attributeWarning.value = ''

  // Attribut-Check nur bei Master-Hierarchie
  if (props.isMasterHierarchy && props.sourceNodeId) {
    checkingAttributes.value = true
    try {
      const [sourceRes, targetRes] = await Promise.all([
        hierarchiesApi.getNodeAttributes(props.sourceNodeId),
        hierarchiesApi.getNodeAttributes(node.id),
      ])
      const sourceIds = new Set((sourceRes.data.data || sourceRes.data || []).map(a => a.attribute_id || a.attribute?.id))
      const targetIds = new Set((targetRes.data.data || targetRes.data || []).map(a => a.attribute_id || a.attribute?.id))

      // Attribute die am Quellknoten sind, aber nicht am Zielknoten
      const missingAtTarget = [...sourceIds].filter(id => !targetIds.has(id))
      if (missingAtTarget.length > 0) {
        attributeWarning.value = `Der Zielknoten hat ${missingAtTarget.length} Attribut(e) weniger zugeordnet. Einige Attributwerte könnten nach dem Verschieben nicht mehr sichtbar sein.`
      }
    } catch (e) { console.warn('Attribut-Check fehlgeschlagen:', e.message) }
    finally { checkingAttributes.value = false }
  }
}

async function move() {
  if (!selectedNodeId.value || props.productIds.length === 0) return
  if (!props.isMasterHierarchy && props.sourceAssignmentIds.length === 0) {
    error.value = 'Keine Zuordnungen zum Verschieben gefunden.'
    return
  }
  moving.value = true
  error.value = ''
  success.value = ''
  try {
    if (props.isMasterHierarchy) {
      await hierarchiesApi.bulkAssignMasterProducts(selectedNodeId.value, props.productIds)
    } else {
      // Output: erst zuordnen, dann alte Zuordnungen löschen
      await hierarchiesApi.bulkAssignOutputProducts(selectedNodeId.value, props.productIds)
      const deleteErrors = []
      for (const assignmentId of props.sourceAssignmentIds) {
        try {
          await hierarchiesApi.removeOutputProductAssignment(assignmentId)
        } catch (e) {
          deleteErrors.push(assignmentId)
        }
      }
      if (deleteErrors.length > 0) {
        console.warn(`${deleteErrors.length} alte Zuordnungen konnten nicht entfernt werden`)
      }
    }
    success.value = `${props.productIds.length} Produkt(e) verschoben.`
    emit('moved')
    setTimeout(() => close(), 1200)
  } catch (e) {
    error.value = e.response?.data?.message || 'Verschieben fehlgeschlagen'
  } finally {
    moving.value = false
  }
}

function close() {
  emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[10vh]" @keydown.escape="close">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-[540px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Produkte verschieben</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ productIds.length }} Produkt{{ productIds.length !== 1 ? 'e' : '' }} in anderen Knoten verschieben</p>
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

            <!-- Attribut-Warnung -->
            <div v-if="attributeWarning" class="flex items-start gap-2 p-2.5 rounded-lg bg-[var(--color-warning,#f59e0b)]/10 border border-[var(--color-warning,#f59e0b)]/30">
              <AlertTriangle class="w-4 h-4 text-[var(--color-warning,#f59e0b)] shrink-0 mt-0.5" :stroke-width="2" />
              <p class="text-xs text-[var(--color-warning,#f59e0b)]">{{ attributeWarning }}</p>
            </div>

            <!-- Node search -->
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
              <input v-model="searchQuery" type="text" class="pim-input text-xs w-full pim-input-icon" placeholder="Knoten suchen..." />
            </div>

            <!-- Node list -->
            <div v-if="loadingTree" class="space-y-1.5 py-2">
              <div v-for="i in 5" :key="i" class="pim-skeleton h-7 rounded" />
            </div>
            <div v-else class="max-h-[320px] overflow-y-auto space-y-0.5 -mx-1 px-1">
              <div
                v-for="node in filteredNodes"
                :key="node.id"
                :class="[
                  'flex items-center gap-2 px-2 py-1.5 rounded text-xs cursor-pointer transition-colors',
                  node.id === selectedNodeId ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)] font-medium' : '',
                  node.id === sourceNodeId ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[var(--color-bg)]',
                ]"
                :style="{ paddingLeft: (node._depth * 16 + 8) + 'px' }"
                @click="selectNode(node)"
              >
                <span class="truncate">{{ node.name_de || node.name_en || node.id }}</span>
                <span v-if="node.id === sourceNodeId" class="text-[10px] text-[var(--color-text-tertiary)]">(aktuell)</span>
                <span v-if="node.product_count" class="text-[10px] text-[var(--color-text-tertiary)] ml-auto shrink-0">{{ node.product_count }}</span>
              </div>
              <p v-if="filteredNodes.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-4 text-center">Keine Knoten gefunden</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between px-5 py-3 border-t border-[var(--color-border)] bg-[var(--color-bg)]/50">
            <span v-if="selectedNodeLabel" class="text-xs text-[var(--color-text-secondary)] truncate max-w-[200px]">
              Ziel: <strong>{{ selectedNodeLabel }}</strong>
            </span>
            <span v-else />
            <div class="flex items-center gap-2">
              <button class="pim-btn pim-btn-ghost text-xs" @click="close">Abbrechen</button>
              <button
                class="pim-btn pim-btn-primary text-xs"
                :disabled="!selectedNodeId || moving || checkingAttributes"
                @click="move"
              >
                <Loader2 v-if="moving" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
                <ArrowRightLeft v-else class="w-3.5 h-3.5" :stroke-width="2" />
                {{ moving ? 'Verschiebe...' : 'Verschieben' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
