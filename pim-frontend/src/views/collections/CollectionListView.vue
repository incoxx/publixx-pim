<script setup>
import { ref, onMounted, markRaw, watch, defineAsyncComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCollectionsStore } from '@/stores/collections'
import attributesApi from '@/api/attributes'
import { collections as collectionsApi, collectionItems as collectionItemsApi } from '@/api/collections'
import productsApi from '@/api/products'
import { Plus, ChevronLeft, Trash2, GripVertical } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import EntityPickerDialog from '@/components/shared/EntityPickerDialog.vue'
import CollectionFormPanel from '@/components/panels/CollectionFormPanel.vue'

const VueDraggable = defineAsyncComponent(() => import('vue-draggable-plus').then((m) => m.VueDraggable))

// @see ProductDetailView.vue::mapDataTypeToInput -- gleiche Zuordnung Attribute.data_type -> PimAttributeInput type
function mapDataTypeToInput(attr) {
  if (attr.data_type === 'String' && attr.textarea_rows) return 'textarea'
  const map = {
    String: 'text', Number: 'number', Float: 'decimal', Date: 'date',
    Flag: 'boolean', Selection: 'select', MultiSelection: 'multicombobox', Dictionary: 'dictionary',
  }
  return map[attr.data_type] || 'text'
}

const authStore = useAuthStore()
const store = useCollectionsStore()
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const selected = ref(null)
const showProductPicker = ref(false)
const expandedItemId = ref(null)

const statusLabels = {
  draft: 'Entwurf', open: 'Offen', frozen: 'Eingefroren', sent: 'Versendet', archived: 'Archiviert',
}

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'type', label: 'Typ', render: (row) => row.collection_type?.name_de || '—' },
  { key: 'status', label: 'Status', render: (row) => statusLabels[row.status] || row.status },
  { key: 'items_count', label: 'Positionen', align: 'right', render: (row) => row.items_count ?? '—' },
]

async function fetchList() {
  await store.fetchCollections({ search: search.value || undefined })
}

function openCreatePanel() {
  authStore.openPanel(markRaw(CollectionFormPanel), { collection: null, onSaved: () => fetchList() })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(CollectionFormPanel), {
    collection: row,
    onSaved: () => { fetchList(); if (selected.value?.id === row.id) selectCollection(row) },
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await store.deleteCollection(deleteTarget.value.id)
    if (selected.value?.id === deleteTarget.value.id) selected.value = null
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

async function selectCollection(row) {
  const { data } = await collectionsApi.get(row.id, { include: 'collectionType,organization' })
  selected.value = data.data
  expandedItemId.value = null
  await store.fetchItems(row.id)
  await loadCollectionAttributes()
}

// ─── Collection-level attributes (Zahlungsbedingungen, Kopftext, ...) ───
const collectionAttrDefs = ref([])
const collectionAttrValues = ref({})

async function loadCollectionAttributes() {
  const { data: defs } = await attributesApi.list({ filters: { applies_to: 'collection' }, perPage: 100 })
  collectionAttrDefs.value = defs.data
  const { data: vals } = await collectionsApi.getAttributeValues(selected.value.id)
  const map = {}
  for (const v of vals.data) {
    map[v.attribute_id] = v.value_string ?? v.value_number ?? v.value_date ?? v.value_flag ?? v.value_selection_id
  }
  collectionAttrValues.value = map
}

async function saveCollectionAttributes() {
  const values = collectionAttrDefs.value
    .filter((a) => collectionAttrValues.value[a.id] !== undefined && collectionAttrValues.value[a.id] !== '')
    .map((a) => ({
      attribute_id: a.id,
      value: collectionAttrValues.value[a.id],
      language: a.is_translatable ? (selected.value.language || 'de') : undefined,
    }))
  if (!values.length) return
  await collectionsApi.saveAttributeValues(selected.value.id, values)
}

// ─── Item-level attributes (Rabatt, Positionstext, ...) ───
const itemAttrDefs = ref([])
const itemAttrValues = ref({})

async function toggleItemAttributes(item) {
  if (expandedItemId.value === item.id) {
    expandedItemId.value = null
    return
  }
  expandedItemId.value = item.id
  if (!itemAttrDefs.value.length) {
    const { data: defs } = await attributesApi.list({ filters: { applies_to: 'collection_item' }, perPage: 100 })
    itemAttrDefs.value = defs.data
  }
  const { data: vals } = await collectionItemsApi.getAttributeValues(selected.value.id, item.id)
  const map = {}
  for (const v of vals.data) {
    map[v.attribute_id] = v.value_string ?? v.value_number ?? v.value_date ?? v.value_flag ?? v.value_selection_id
  }
  itemAttrValues.value = map
}

async function saveItemAttributes(item) {
  const values = itemAttrDefs.value
    .filter((a) => itemAttrValues.value[a.id] !== undefined && itemAttrValues.value[a.id] !== '')
    .map((a) => ({
      attribute_id: a.id,
      value: itemAttrValues.value[a.id],
      language: a.is_translatable ? (selected.value.language || 'de') : undefined,
    }))
  if (values.length) {
    await collectionItemsApi.saveAttributeValues(selected.value.id, item.id, values)
  }
  expandedItemId.value = null
}

// ─── Items: add / remove / quantity / reorder ───
async function pickProduct(picked) {
  const product = picked[0]
  if (!product) return
  await store.addItem(selected.value.id, { product_id: product.id, quantity: 1 })
}

async function addFreetextItem() {
  await store.addItem(selected.value.id, { quantity: 1 })
}

async function removeItem(item) {
  await store.removeItem(selected.value.id, item.id)
}

async function updateQuantity(item, quantity) {
  if (quantity === item.quantity) return
  await store.updateItem(selected.value.id, item.id, { quantity })
}

async function onReorder() {
  const ids = store.currentItems.map((i) => i.id)
  await store.reorderItems(selected.value.id, ids)
}

function productFetcher(query, page) {
  return productsApi.list({ search: query || undefined, page, perPage: 20 }).then(({ data }) => ({
    items: data.data,
    meta: data.meta,
  }))
}

watch(search, () => fetchList())
onMounted(() => {
  fetchList()
  store.fetchTypes()
})
</script>

<template>
  <div class="flex gap-6 h-full">
    <!-- Left: Collection list -->
    <div :class="['space-y-4 transition-all', selected ? 'w-1/3 flex-none' : 'flex-1']">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Collections</h2>
        <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
          <Plus class="w-4 h-4" :stroke-width="2" /> Neue Collection
        </button>
      </div>
      <PimFilterBar :search="search" placeholder="Collections durchsuchen..." @update:search="v => search = v" />
      <PimTable
        :columns="columns"
        :rows="store.items"
        :loading="store.loading"
        :activeRowId="selected?.id"
        showActions
        emptyText="Keine Collections"
        @row-click="selectCollection"
        @row-action="handleRowAction"
      />
    </div>

    <!-- Right: Collection detail -->
    <div v-if="selected" class="flex-1 space-y-4 min-w-0">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button class="pim-btn pim-btn-ghost p-1.5" @click="selected = null" title="Zurück">
            <ChevronLeft class="w-4 h-4" :stroke-width="2" />
          </button>
          <div>
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ selected.name }}</h3>
            <p class="text-[10px] text-[var(--color-text-tertiary)]">
              {{ selected.collection_type?.name_de }} · {{ statusLabels[selected.status] || selected.status }}
            </p>
          </div>
        </div>
        <button class="pim-btn pim-btn-ghost text-xs" @click="openEditPanel(selected)">Bearbeiten</button>
      </div>

      <!-- Collection-level attributes -->
      <div v-if="collectionAttrDefs.length" class="pim-card p-4 space-y-3">
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Kopfdaten</h4>
        <div v-for="attr in collectionAttrDefs" :key="attr.id">
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">{{ attr.name_de }}</label>
          <PimAttributeInput
            :type="mapDataTypeToInput(attr)"
            :modelValue="collectionAttrValues[attr.id]"
            @update:modelValue="v => collectionAttrValues[attr.id] = v"
          />
        </div>
        <button class="pim-btn pim-btn-secondary text-xs" @click="saveCollectionAttributes">Speichern</button>
      </div>

      <!-- Items -->
      <div class="pim-card overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Positionen</h4>
          <div class="flex items-center gap-2">
            <button class="pim-btn pim-btn-ghost text-xs" @click="addFreetextItem">
              <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Freitext
            </button>
            <button class="pim-btn pim-btn-primary text-xs" @click="showProductPicker = true">
              <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Produkt hinzufügen
            </button>
          </div>
        </div>

        <div v-if="store.currentItemsLoading" class="p-4 space-y-2">
          <div v-for="i in 3" :key="i" class="pim-skeleton h-10 rounded" />
        </div>
        <p v-else-if="!store.currentItems.length" class="px-4 py-8 text-center text-xs text-[var(--color-text-tertiary)]">
          Noch keine Positionen.
        </p>

        <VueDraggable
          v-else
          v-model="store.currentItems"
          handle=".drag-handle"
          ghost-class="opacity-30"
          class="divide-y divide-[var(--color-border)]"
          @end="onReorder"
        >
          <div v-for="item in store.currentItems" :key="item.id">
            <div class="flex items-center gap-3 px-4 py-2.5">
              <GripVertical class="drag-handle w-3.5 h-3.5 text-[var(--color-text-tertiary)] cursor-grab shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-[var(--color-text-primary)] truncate">
                  {{ item.product?.name || item.snapshot?.name || 'Freitextposition' }}
                </p>
                <p v-if="item.product?.sku" class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ item.product.sku }}</p>
              </div>
              <input
                type="number"
                class="pim-input text-xs !w-20 text-right shrink-0"
                :value="item.quantity"
                min="0"
                step="any"
                @change="updateQuantity(item, parseFloat($event.target.value))"
              />
              <button class="pim-btn pim-btn-ghost !text-xs !py-1 !px-2 shrink-0" @click="toggleItemAttributes(item)">Attribute</button>
              <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] shrink-0" @click="removeItem(item)" title="Entfernen">
                <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
            <div v-if="expandedItemId === item.id" class="px-4 pb-3 pl-10 space-y-2 bg-[var(--color-bg)]">
              <div v-for="attr in itemAttrDefs" :key="attr.id">
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">{{ attr.name_de }}</label>
                <PimAttributeInput
                  :type="mapDataTypeToInput(attr)"
                  :modelValue="itemAttrValues[attr.id]"
                  @update:modelValue="v => itemAttrValues[attr.id] = v"
                />
              </div>
              <button class="pim-btn pim-btn-secondary text-xs" @click="saveItemAttributes(item)">Speichern</button>
            </div>
          </div>
        </VueDraggable>
      </div>
    </div>

    <EntityPickerDialog
      v-model="showProductPicker"
      title="Produkt hinzufügen"
      :fetcher="productFetcher"
      :labelFn="p => p.name"
      :sublabelFn="p => p.sku"
      @confirm="pickProduct"
    />

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Collection löschen?"
      :message="`Die Collection '${deleteTarget?.name || ''}' und alle Positionen werden gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
