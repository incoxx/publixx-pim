<script setup>
import { ref, onMounted, onBeforeUnmount, markRaw, watch, defineAsyncComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useCollectionsStore } from '@/stores/collections'
import attributesApi from '@/api/attributes'
import { collections as collectionsApi, collectionItems as collectionItemsApi, collectionRenders as collectionRendersApi } from '@/api/collections'
import { triggerDownload, blobErrorMessage } from '@/utils/download'
import productsApi from '@/api/products'
import { useRouter } from 'vue-router'
import { Plus, ChevronLeft, Trash2, GripVertical, Upload, Eye, EyeOff, Download, Loader2, Star } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import AddressForm from '@/components/shared/AddressForm.vue'
import EntityPickerDialog from '@/components/shared/EntityPickerDialog.vue'
import CollectionFormPanel from '@/components/panels/CollectionFormPanel.vue'
import CollectionMatchQueue from '@/components/collections/CollectionMatchQueue.vue'
import CollectionShareLinksPanel from '@/components/collections/CollectionShareLinksPanel.vue'
import AddFromWatchlistDialog from '@/components/dialogs/AddFromWatchlistDialog.vue'

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
const toastStore = useToastStore()
const store = useCollectionsStore()
const router = useRouter()
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const selected = ref(null)
const showProductPicker = ref(false)
const showWatchlistPicker = ref(false)
const expandedItemId = ref(null)

// ─── Rendering: Vorschau + Export (sync/async), PdfTemplate-Kopfbereich siehe Backend ───
const previewing = ref(false)
const exportingFormat = ref(null) // Format, das gerade synchron exportiert wird
const asyncJobFormat = ref(null) // Format, dessen Async-Job gerade laeuft (Polling)
let pollHandle = null

const exportFormatLabels = { pdf: 'PDF', xlsx: 'Excel', opentrans: 'openTRANS' }

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
  addressDraft.value = { ...(selected.value.address || {}) }
  expandedItemId.value = null
  // itemAttrDefs haengt vom Collection-Typ ab (default_item_attribute_groups) -- Cache aus
  // toggleItemAttributes() beim Wechsel auf eine andere Collection ungueltig machen.
  itemAttrDefs.value = []
  await store.fetchItems(row.id)
  await loadCollectionAttributes()
}

// ─── Adresse (fuer ein Angebot) ───
const addressDraft = ref({})
const savingAddress = ref(false)

async function saveAddress() {
  savingAddress.value = true
  try {
    const { data } = await collectionsApi.update(selected.value.id, { address: addressDraft.value })
    selected.value.address = (data.data || data).address
    toastStore.showToast('Adresse gespeichert', 'success')
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Adresse konnte nicht gespeichert werden', 'error')
  } finally {
    savingAddress.value = false
  }
}

// ─── Collection-level attributes (Zahlungsbedingungen, Kopftext, ...) ───
const collectionAttrDefs = ref([])
const collectionAttrValues = ref({})

async function loadCollectionAttributes() {
  // Auf die dem Collection-Typ zugeordnete Attributsicht beschraenkt (default_attribute_groups).
  // Bewusst KEIN Fallback auf "alle Attribute" ohne zugeordnete Sicht -- CollectionRenderService::
  // resolveGroupDisplayValues() rendert bei leerer Sicht ebenfalls nichts. Ein Fallback wuerde
  // hier Werte editierbar machen, die im Export nie erscheinen.
  const groups = selected.value.collection_type?.default_attribute_groups
  if (!groups?.length) {
    collectionAttrDefs.value = []
    collectionAttrValues.value = {}
    return
  }
  const { data: defs } = await attributesApi.list({
    filters: { is_internal: 0, attribute_view: groups.join(',') },
    perPage: 100,
  })
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
// Pro Position abwaehlbare Attribute der zugeordneten Attributsicht (z.B. "Farbe" bei einer
// Position ohne Farbvariante) -- gilt nur fuer das aktuell aufgeklappte Item, aus
// item.hidden_attribute_ids initialisiert, siehe CollectionRenderService::resolveGroupDisplayValues().
const itemHiddenAttrIds = ref(new Set())

async function toggleItemAttributes(item) {
  if (expandedItemId.value === item.id) {
    expandedItemId.value = null
    return
  }
  expandedItemId.value = item.id
  itemHiddenAttrIds.value = new Set(item.hidden_attribute_ids || [])
  if (!itemAttrDefs.value.length) {
    // Auf die dem Collection-Typ zugeordnete Attributsicht beschraenkt (default_item_attribute_groups)
    // -- siehe loadCollectionAttributes()/CollectionRenderService::resolveGroupDisplayValues().
    const groups = selected.value.collection_type?.default_item_attribute_groups
    let defs = []
    if (groups?.length) {
      const { data } = await attributesApi.list({
        filters: { is_internal: 0, attribute_view: groups.join(',') },
        perPage: 100,
      })
      defs = data.data
    }
    // Rabatt-Attribut braucht immer ein Eingabefeld, auch wenn es (noch) kein Mitglied der
    // zugeordneten Gruppe ist -- ohne UI-Zugriff waere der Rabatt sonst nicht pflegbar.
    const discountTechnicalName = selected.value.collection_type?.default_discount_attribute
    if (discountTechnicalName && !defs.some((a) => a.technical_name === discountTechnicalName)) {
      const { data } = await attributesApi.list({ search: discountTechnicalName, perPage: 5 })
      const match = (data.data || []).find((a) => a.technical_name === discountTechnicalName)
      if (match) defs = [...defs, match]
    }
    itemAttrDefs.value = defs
  }
  const { data: vals } = await collectionItemsApi.getAttributeValues(selected.value.id, item.id)
  const map = {}
  for (const v of vals.data) {
    // Selection-Werte werden hier als reines Text-Eingabefeld dargestellt (keine Selectbox) --
    // gleiche Feld-Prioritaet wie CollectionRenderService::resolveGroupDisplayValues(), damit
    // Editor und gerendertes Dokument denselben Wert zeigen.
    map[v.attribute_id] = v.value_string
      ?? v.value_number
      ?? v.value_date
      ?? (v.value_flag !== null && v.value_flag !== undefined ? (v.value_flag ? 'Ja' : 'Nein') : null)
      ?? v.value_list_entry?.display_value_de
      ?? v.value_selection_id
  }
  itemAttrValues.value = map
}

function toggleAttributeHidden(attributeId) {
  const next = new Set(itemHiddenAttrIds.value)
  if (next.has(attributeId)) next.delete(attributeId)
  else next.add(attributeId)
  itemHiddenAttrIds.value = next
}

async function saveItemAttributes(item) {
  const values = itemAttrDefs.value
    .filter((a) => !itemHiddenAttrIds.value.has(a.id))
    .filter((a) => itemAttrValues.value[a.id] !== undefined && itemAttrValues.value[a.id] !== '')
    .map((a) => ({
      attribute_id: a.id,
      value: itemAttrValues.value[a.id],
      language: a.is_translatable ? (selected.value.language || 'de') : undefined,
    }))
  if (values.length) {
    await collectionItemsApi.saveAttributeValues(selected.value.id, item.id, values)
  }
  await store.updateItem(selected.value.id, item.id, { hidden_attribute_ids: [...itemHiddenAttrIds.value] })
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

// ─── Freitextpositionen: Name/Preis sind bei Positionen ohne product_id die einzige Quelle
// (EnrichmentService leitet dort nichts von einem Produkt ab), daher direkt im snapshot editierbar.
async function updateFreetextName(item, name) {
  await store.updateItem(selected.value.id, item.id, { snapshot: { ...(item.snapshot || {}), name } })
}

async function updateFreetextPrice(item, rawAmount) {
  const snapshot = { ...(item.snapshot || {}) }
  const amount = rawAmount === '' ? null : parseFloat(rawAmount)
  if (amount === null || Number.isNaN(amount)) {
    delete snapshot.resolved_price
  } else {
    snapshot.resolved_price = { amount, currency: selected.value.currency || 'EUR' }
  }
  await store.updateItem(selected.value.id, item.id, { snapshot })
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

// ─── Rendering ───
async function previewCollection() {
  previewing.value = true
  try {
    const { data } = await collectionRendersApi.preview(selected.value.id, { format: 'pdf' })
    const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    toastStore.showToast(await blobErrorMessage(e), 'error')
  } finally {
    previewing.value = false
  }
}

async function exportSync(format) {
  exportingFormat.value = format
  try {
    const { data } = await collectionRendersApi.execute(selected.value.id, { format })
    triggerDownload(new Blob([data]), `${selected.value.name || 'collection'}.${format}`)
  } catch (e) {
    toastStore.showToast(await blobErrorMessage(e), 'error')
  } finally {
    exportingFormat.value = null
  }
}

async function exportAsync(format) {
  asyncJobFormat.value = format
  try {
    const { data } = await collectionRendersApi.executeAsync(selected.value.id, { format })
    pollRenderJob(data.data.id, format)
  } catch (e) {
    toastStore.showToast(e.response?.data?.message || 'Render-Job konnte nicht gestartet werden', 'error')
    asyncJobFormat.value = null
  }
}

function pollRenderJob(jobId, format) {
  clearInterval(pollHandle)
  pollHandle = setInterval(async () => {
    const { data } = await collectionRendersApi.jobStatus(jobId)
    const job = data.data
    if (job.last_status === 'completed') {
      clearInterval(pollHandle)
      asyncJobFormat.value = null
      const { data: fileData } = await collectionRendersApi.jobDownload(jobId)
      triggerDownload(new Blob([fileData]), `${selected.value.name || 'collection'}.${format}`)
      toastStore.showToast('Render abgeschlossen', 'success')
    } else if (job.last_status === 'failed') {
      clearInterval(pollHandle)
      asyncJobFormat.value = null
      toastStore.showToast(job.last_error || 'Render-Job fehlgeschlagen', 'error')
    }
  }, 2000)
}

onBeforeUnmount(() => clearInterval(pollHandle))

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
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-ghost" @click="router.push('/collections/import')">
            <Upload class="w-4 h-4" :stroke-width="2" /> Import
          </button>
          <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
            <Plus class="w-4 h-4" :stroke-width="2" /> Neue Collection
          </button>
        </div>
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
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-ghost text-xs" data-testid="collection-preview" :disabled="previewing" @click="previewCollection">
            <Loader2 v-if="previewing" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
            <Eye v-else class="w-3.5 h-3.5" :stroke-width="2" />
            Vorschau
          </button>
          <button class="pim-btn pim-btn-ghost text-xs" @click="openEditPanel(selected)">Bearbeiten</button>
        </div>
      </div>

      <!-- Export: PDF/XLSX (allowed_export_formats gefiltert, Sync-Download oder Hintergrund-Job) -->
      <div v-if="selected.collection_type?.allowed_export_formats?.length" class="pim-card p-3 flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-medium text-[var(--color-text-secondary)] mr-1">Export:</span>
        <template v-for="format in selected.collection_type.allowed_export_formats" :key="format">
          <div v-if="format === 'pdf' || format === 'xlsx'" class="flex items-center gap-1">
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :data-testid="`collection-export-sync-${format}`"
              :disabled="exportingFormat === format || asyncJobFormat === format"
              @click="exportSync(format)"
              :title="`${exportFormatLabels[format]} herunterladen`"
            >
              <Loader2 v-if="exportingFormat === format" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
              <Download v-else class="w-3.5 h-3.5" :stroke-width="2" />
              {{ exportFormatLabels[format] || format }}
            </button>
            <button
              class="pim-btn pim-btn-ghost !text-[10px] !px-1.5"
              :data-testid="`collection-export-async-${format}`"
              :disabled="asyncJobFormat === format"
              @click="exportAsync(format)"
              title="Im Hintergrund erstellen"
            >
              {{ asyncJobFormat === format ? 'läuft…' : 'im Hintergrund' }}
            </button>
          </div>
          <span
            v-else
            class="pim-btn pim-btn-ghost text-xs opacity-50 cursor-not-allowed"
            title="Kommt in Phase 5"
          >
            {{ exportFormatLabels[format] || format }}
          </span>
        </template>
      </div>

      <CollectionMatchQueue :collection-id="selected.id" @resolved="store.fetchItems(selected.id)" />

      <CollectionShareLinksPanel :collection-id="selected.id" />

      <!-- Adresse (fuer ein Angebot) -- pro Collection eingefroren, unabhaengig von der
           Organisation-Live-Adresse, siehe CollectionRenderService::resolveAddress() -->
      <div class="pim-card p-4 space-y-3">
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Adresse</h4>
        <AddressForm v-model="addressDraft" />
        <button class="pim-btn pim-btn-secondary text-xs" data-testid="save-address" :disabled="savingAddress" @click="saveAddress">
          {{ savingAddress ? 'Speichere...' : 'Speichern' }}
        </button>
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
            <button class="pim-btn pim-btn-ghost text-xs" data-testid="add-from-watchlist-trigger" @click="showWatchlistPicker = true">
              <Star class="w-3.5 h-3.5" :stroke-width="2" /> Aus Merkliste
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
                <template v-if="item.product_id">
                  <p class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ item.product?.name }}</p>
                  <p v-if="item.product?.sku" class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ item.product.sku }}</p>
                </template>
                <input
                  v-else
                  type="text"
                  class="pim-input text-xs w-full"
                  placeholder="Freitext-Bezeichnung eingeben…"
                  :value="item.snapshot?.name || ''"
                  data-testid="freetext-name"
                  @change="updateFreetextName(item, $event.target.value)"
                />
              </div>
              <input
                v-if="!item.product_id"
                type="number"
                class="pim-input text-xs !w-24 text-right shrink-0"
                placeholder="Preis"
                :value="item.snapshot?.resolved_price?.amount ?? ''"
                min="0"
                step="0.01"
                data-testid="freetext-price"
                @change="updateFreetextPrice(item, $event.target.value)"
              />
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
              <p v-if="!itemAttrDefs.length" class="text-[11px] text-[var(--color-text-tertiary)] italic">
                Dem Collection-Typ ist keine Positions-Attributsicht zugeordnet.
              </p>
              <div v-for="attr in itemAttrDefs" :key="attr.id" class="flex items-start gap-2">
                <div class="flex-1 min-w-0">
                  <label
                    class="block text-[11px] font-medium mb-1"
                    :class="itemHiddenAttrIds.has(attr.id) ? 'text-[var(--color-text-tertiary)] line-through' : 'text-[var(--color-text-secondary)]'"
                  >{{ attr.name_de }}</label>
                  <PimAttributeInput
                    type="text"
                    :modelValue="itemAttrValues[attr.id]"
                    :disabled="itemHiddenAttrIds.has(attr.id)"
                    @update:modelValue="v => itemAttrValues[attr.id] = v"
                  />
                </div>
                <button
                  class="pim-btn pim-btn-ghost !p-1.5 mt-4 shrink-0"
                  :title="itemHiddenAttrIds.has(attr.id) ? 'Für diese Position einblenden' : 'Für diese Position abwählen'"
                  :data-testid="`toggle-attr-${attr.technical_name}`"
                  @click="toggleAttributeHidden(attr.id)"
                >
                  <component :is="itemHiddenAttrIds.has(attr.id) ? EyeOff : Eye" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                </button>
              </div>
              <button v-if="itemAttrDefs.length" class="pim-btn pim-btn-secondary text-xs" @click="saveItemAttributes(item)">Speichern</button>
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

    <AddFromWatchlistDialog
      v-if="selected"
      v-model:open="showWatchlistPicker"
      :collection-id="selected.id"
      :existing-product-ids="store.currentItems.map(i => i.product_id).filter(Boolean)"
      @added="store.fetchItems(selected.id)"
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
