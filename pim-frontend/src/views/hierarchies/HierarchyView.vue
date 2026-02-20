<script setup>
import { ref, watch, onMounted, markRaw } from 'vue'
import { useHierarchyStore } from '@/stores/hierarchies'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Plus, Edit3, Trash2, FolderPlus, Package } from 'lucide-vue-next'
import hierarchiesApi from '@/api/hierarchies'
import productsApi from '@/api/products'
import PimTree from '@/components/shared/PimTree.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import HierarchyFormPanel from '@/components/panels/HierarchyFormPanel.vue'
import HierarchyNodeFormPanel from '@/components/panels/HierarchyNodeFormPanel.vue'

const { t } = useI18n()
const router = useRouter()
const store = useHierarchyStore()
const attrStore = useAttributeStore()
const authStore = useAuthStore()
const selectedHierarchyId = ref(null)

// Node attributes
const nodeAttributes = ref([])
const nodeAttrsLoading = ref(false)
const showAttrPicker = ref(false)

// Node products
const nodeProducts = ref([])
const nodeProductsLoading = ref(false)

// Delete state
const deleteNodeTarget = ref(null)
const nodeDeleting = ref(false)

// Context menu
const contextMenu = ref({ show: false, x: 0, y: 0, node: null })

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

// ─── Hierarchy CRUD ──────────────────────────────────
function openCreateHierarchy() {
  authStore.openPanel(markRaw(HierarchyFormPanel), {
    hierarchy: null,
    onSaved: async (newHierarchy) => {
      await store.fetchHierarchies()
      if (newHierarchy?.id) await selectHierarchy(newHierarchy.id)
    },
  })
}

// ─── Node CRUD ───────────────────────────────────────
function createChildNode(parentNode) {
  contextMenu.value.show = false
  authStore.openPanel(markRaw(HierarchyNodeFormPanel), {
    node: null,
    hierarchyId: selectedHierarchyId.value,
    parentNodeId: parentNode?.id || null,
  })
}

function editNode(node) {
  contextMenu.value.show = false
  authStore.openPanel(markRaw(HierarchyNodeFormPanel), {
    node: node,
    hierarchyId: selectedHierarchyId.value,
  })
}

function requestDeleteNode(node) {
  contextMenu.value.show = false
  deleteNodeTarget.value = node
}

async function confirmDeleteNode() {
  nodeDeleting.value = true
  try {
    await store.deleteNode(deleteNodeTarget.value.id)
    if (store.selectedNode?.id === deleteNodeTarget.value?.id) {
      store.selectNode(null)
    }
    deleteNodeTarget.value = null
    await store.fetchTree(selectedHierarchyId.value)
  } finally { nodeDeleting.value = false }
}

// ─── Node Attribute Assignments ──────────────────────
async function loadNodeAttributes(nodeId) {
  if (!nodeId) return
  nodeAttrsLoading.value = true
  try {
    const { data } = await hierarchiesApi.getNodeAttributes(nodeId)
    nodeAttributes.value = data.data || data
  } catch { nodeAttributes.value = [] }
  finally { nodeAttrsLoading.value = false }
}

async function assignAttribute(attr) {
  if (!store.selectedNode) return
  try {
    await hierarchiesApi.assignNodeAttribute(store.selectedNode.id, {
      attribute_id: attr.id,
      access_product: 'editable',
      access_hierarchy: 'visible',
      access_variant: 'editable',
    })
    showAttrPicker.value = false
    await loadNodeAttributes(store.selectedNode.id)
  } catch { /* silently fail */ }
}

async function removeNodeAttribute(assignment) {
  try {
    await hierarchiesApi.removeNodeAttributeAssignment(assignment.id)
    await loadNodeAttributes(store.selectedNode.id)
  } catch { /* silently fail */ }
}

// ─── Node Products ──────────────────────────────────
async function loadNodeProducts(nodeId) {
  if (!nodeId) return
  nodeProductsLoading.value = true
  try {
    const { data } = await productsApi.list({ filters: { master_hierarchy_node_id: nodeId }, perPage: 20 })
    nodeProducts.value = data.data || data
  } catch { nodeProducts.value = [] }
  finally { nodeProductsLoading.value = false }
}

// Watch node selection to load attributes and products
watch(() => store.selectedNode, (node) => {
  if (node) {
    loadNodeAttributes(node.id)
    loadNodeProducts(node.id)
  } else {
    nodeAttributes.value = []
    nodeProducts.value = []
  }
})

onMounted(async () => {
  await store.fetchHierarchies()
  attrStore.fetchAttributes()
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
        <button class="pim-btn pim-btn-ghost p-1" @click="openCreateHierarchy">
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
      <!-- Action bar for adding root node -->
      <div class="px-3 py-2 border-b border-[var(--color-border)]">
        <button class="pim-btn pim-btn-secondary text-xs w-full" @click="createChildNode(null)">
          <FolderPlus class="w-3.5 h-3.5" :stroke-width="2" /> Knoten erstellen
        </button>
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
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-[var(--color-text-primary)]">
            {{ store.selectedNode.name_de || store.selectedNode.name }}
          </h3>
          <div class="flex items-center gap-1">
            <button class="pim-btn pim-btn-ghost p-1.5" title="Unterknoten erstellen" @click="createChildNode(store.selectedNode)">
              <FolderPlus class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost p-1.5" title="Knoten bearbeiten" @click="editNode(store.selectedNode)">
              <Edit3 class="w-4 h-4" :stroke-width="1.75" />
            </button>
            <button class="pim-btn pim-btn-ghost p-1.5 text-[var(--color-error)]" title="Knoten löschen" @click="requestDeleteNode(store.selectedNode)">
              <Trash2 class="w-4 h-4" :stroke-width="1.75" />
            </button>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Code</span>
            <p class="font-mono text-xs">{{ store.selectedNode.code || '—' }}</p>
          </div>
          <div>
            <span class="text-[12px] text-[var(--color-text-tertiary)]">Produkte</span>
            <p>{{ nodeProducts.length || store.selectedNode.product_count || 0 }}</p>
          </div>
        </div>

        <!-- Assigned Products -->
        <div class="border-t border-[var(--color-border)] pt-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">
              <Package class="w-3.5 h-3.5 inline-block mr-1 -mt-0.5" :stroke-width="1.75" />
              Zugeordnete Produkte
            </h4>
          </div>
          <div v-if="nodeProductsLoading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="pim-skeleton h-8 rounded" />
          </div>
          <div v-else-if="nodeProducts.length > 0" class="space-y-1">
            <div
              v-for="prod in nodeProducts"
              :key="prod.id"
              class="flex items-center justify-between px-3 py-2 rounded-lg bg-[var(--color-bg)] cursor-pointer hover:bg-[var(--color-surface)] transition-colors"
              @click="router.push(`/products/${prod.id}`)"
            >
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ prod.sku }}</span>
                <span class="text-xs font-medium">{{ prod.name || '—' }}</span>
              </div>
              <span :class="['pim-badge text-[10px]', prod.status === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
                {{ prod.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Produkte zugeordnet. Weisen Sie Produkte über die Produktdetailseite zu.</p>
        </div>

        <!-- Assigned Attributes -->
        <div class="border-t border-[var(--color-border)] pt-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-medium text-[var(--color-text-secondary)]">Zugeordnete Attribute</h4>
            <button class="pim-btn pim-btn-secondary text-xs" @click="showAttrPicker = !showAttrPicker">
              <Plus class="w-3 h-3" :stroke-width="2" /> Attribut zuordnen
            </button>
          </div>

          <!-- Attribute picker -->
          <div v-if="showAttrPicker" class="mb-3 p-3 bg-[var(--color-bg)] rounded-lg max-h-48 overflow-y-auto space-y-1">
            <div
              v-for="attr in attrStore.items"
              :key="attr.id"
              class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-[var(--color-surface)] cursor-pointer"
              @click="assignAttribute(attr)"
            >
              <span class="text-xs">{{ attr.name_de || attr.technical_name }}</span>
              <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ attr.data_type }}</span>
            </div>
            <p v-if="attrStore.items.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute verfügbar</p>
          </div>

          <div v-if="nodeAttrsLoading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="pim-skeleton h-8 rounded" />
          </div>
          <div v-else-if="nodeAttributes.length > 0" class="space-y-1">
            <div
              v-for="assignment in nodeAttributes"
              :key="assignment.id"
              class="flex items-center justify-between px-3 py-2 rounded-lg bg-[var(--color-bg)] group"
            >
              <div class="flex items-center gap-2">
                <span class="text-xs font-medium">{{ assignment.attribute?.name_de || assignment.attribute?.technical_name || '—' }}</span>
                <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ assignment.attribute?.data_type }}</span>
              </div>
              <button
                class="opacity-0 group-hover:opacity-100 p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-all"
                @click="removeNodeAttribute(assignment)"
              >
                <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
          </div>
          <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute zugeordnet</p>
        </div>
      </div>
      <div v-else class="flex items-center justify-center h-full">
        <p class="text-sm text-[var(--color-text-tertiary)]">Knoten auswählen</p>
      </div>
    </div>

    <PimConfirmDialog
      :open="!!deleteNodeTarget"
      title="Knoten löschen?"
      :message="`Der Knoten '${deleteNodeTarget?.name_de || ''}' und alle Unterknoten werden unwiderruflich gelöscht.`"
      :loading="nodeDeleting"
      @confirm="confirmDeleteNode"
      @cancel="deleteNodeTarget = null"
    />
  </div>
</template>
