<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProductStore } from '@/stores/products'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useFilters } from '@/composables/useFilters'
import { useLocaleStore } from '@/stores/locale'
import { Plus, Languages, Upload, Download, X, GitCompareArrows, Star, Pencil, FileSpreadsheet } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import ColumnConfigPopover from '@/components/shared/ColumnConfigPopover.vue'
import { useColumnConfig } from '@/composables/useColumnConfig'
import { triggerDownload } from '@/utils/download'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ProductCreatePanel from '@/components/panels/ProductCreatePanel.vue'
import productsApi from '@/api/products'
import watchlistApi from '@/api/watchlist'

const { t } = useI18n()
const router = useRouter()
const store = useProductStore()
const attrStore = useAttributeStore()
const authStore = useAuthStore()
const localeStore = useLocaleStore()

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  store.setSearch(search.value)
  store.fetchList()
})

const defaultColumns = [
  { key: 'sku', label: 'SKU', sortable: true, mono: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'product_type.name_de', label: 'Typ' },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'updated_at', label: 'Geändert', sortable: true },
]

const extraColumns = [
  { key: 'ean', label: 'EAN', mono: true },
  { key: 'created_at', label: 'Erstellt', sortable: true },
]

const { visibleColumns, allColumns, visibleKeys, isColumnVisible, toggleColumn, moveColumn, resetColumns } = useColumnConfig('columns:products', defaultColumns, extraColumns)

// Excel export
const excelExporting = ref(false)

async function exportExcel() {
  excelExporting.value = true
  exportError.value = null
  try {
    const params = {
      columns: visibleKeys.value,
      search: search.value || undefined,
      language: 'de',
    }
    const resp = await productsApi.exportExcel(params)
    triggerDownload(resp.data, `produkte-${new Date().toISOString().slice(0, 10)}.xlsx`)
  } catch (e) {
    exportError.value = 'Excel-Export fehlgeschlagen'
    console.error('Excel export failed:', e)
  } finally { excelExporting.value = false }
}

const exportError = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)

function handleSort(field, order) {
  store.setSort(field, order)
  store.fetchList()
}

function openProduct(row) {
  router.push(`/products/${row.id}`)
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ProductCreatePanel), {
    productTypes: attrStore.prodTypes,
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await store.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await store.fetchList()
  } finally {
    deleting.value = false
  }
}

// ─── Product Comparison ──────────────────────────────
const selectedProductIds = ref([])
const showCompare = ref(false)
const compareData = ref(null)
const compareLoading = ref(false)
const showDiffsOnly = ref(false)

const canCompare = computed(() => selectedProductIds.value.length === 2)

function handleSelect(ids) {
  selectedProductIds.value = ids
}

const compareRows = computed(() => {
  if (!compareData.value?.rows) return []
  if (showDiffsOnly.value) return compareData.value.rows.filter(r => r.is_different)
  return compareData.value.rows
})

async function openCompare() {
  if (!canCompare.value) return
  showCompare.value = true
  compareLoading.value = true
  compareData.value = null
  try {
    const { data } = await productsApi.compare(selectedProductIds.value[0], selectedProductIds.value[1])
    compareData.value = data.data || data
  } catch (e) { console.error('Compare failed:', e) }
  finally { compareLoading.value = false }
}

// ─── XLIFF Translation Export/Import ─────────────────
const showXliffPanel = ref(false)
const xliffSourceLang = ref('de')
const xliffTargetLang = ref('en')
const xliffExporting = ref(false)
const xliffImporting = ref(false)
const xliffImportResult = ref(null)

async function exportXliff() {
  xliffExporting.value = true
  try {
    const resp = await productsApi.exportXliff({
      sourceLang: xliffSourceLang.value,
      targetLang: xliffTargetLang.value,
    })
    triggerDownload(resp.data, `pim-translations-${xliffSourceLang.value}-${xliffTargetLang.value}.xliff`)
  } catch (e) { console.error('XLIFF export failed:', e) }
  finally { xliffExporting.value = false }
}

async function importXliff(event) {
  const file = event.target.files?.[0]
  if (!file) return
  xliffImporting.value = true
  xliffImportResult.value = null
  try {
    const { data } = await productsApi.importXliff(file)
    xliffImportResult.value = data
    store.fetchList()
  } catch (e) {
    xliffImportResult.value = { message: 'Import fehlgeschlagen', errors: [e.message] }
  } finally {
    xliffImporting.value = false
    event.target.value = ''
  }
}

// ─── Watchlist (Merkliste) ────────────────────────────
const watchlistIds = ref(new Set())

async function loadWatchlistIds() {
  try {
    const { data } = await watchlistApi.productIds()
    watchlistIds.value = new Set(data.data || data)
  } catch (e) { /* ignore */ }
}

function isOnWatchlist(productId) {
  return watchlistIds.value.has(productId)
}

async function toggleWatchlist(productId) {
  try {
    if (isOnWatchlist(productId)) {
      await watchlistApi.removeByProduct(productId)
      watchlistIds.value.delete(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    } else {
      await watchlistApi.add(productId)
      watchlistIds.value.add(productId)
      watchlistIds.value = new Set(watchlistIds.value)
    }
  } catch (e) {
    console.error('Watchlist toggle failed', e)
  }
}

async function bulkAddToWatchlist() {
  if (selectedProductIds.value.length === 0) return
  try {
    await watchlistApi.bulkAdd(selectedProductIds.value)
    await loadWatchlistIds()
  } catch (e) {
    console.error('Bulk watchlist add failed', e)
  }
}

function openBulkEditor() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-edit', query: { ids } })
}

onMounted(() => {
  store.fetchList()
  attrStore.fetchProductTypes()
  loadWatchlistIds()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('product.title') }}</h2>
      <div class="flex items-center gap-2">
        <ColumnConfigPopover
          :allColumns="allColumns"
          :visibleKeys="visibleKeys"
          @toggle="toggleColumn"
          @move="moveColumn"
          @reset="resetColumns"
        />
        <button class="pim-btn pim-btn-secondary text-xs" :disabled="excelExporting" @click="exportExcel">
          <FileSpreadsheet class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ excelExporting ? 'Export...' : 'Excel' }}
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showXliffPanel = !showXliffPanel">
          <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
          XLIFF
        </button>
        <button v-if="authStore.hasPermission('products.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
          <Plus class="w-4 h-4" :stroke-width="2" />
          {{ t('product.newProduct') }}
        </button>
      </div>
    </div>

    <!-- XLIFF Translation Panel -->
    <div v-if="showXliffPanel" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Languages class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          XLIFF {{ t('product.translationExportImport') }}
        </h3>
        <button class="pim-btn pim-btn-ghost text-xs p-1" @click="showXliffPanel = false">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">{{ t('product.sourceLang') }}</label>
          <select class="pim-input text-xs w-28" v-model="xliffSourceLang">
            <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
          </select>
        </div>
        <div class="text-[var(--color-text-tertiary)] text-lg pb-1">→</div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">{{ t('product.targetLang') }}</label>
          <select class="pim-input text-xs w-28" v-model="xliffTargetLang">
            <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
          </select>
        </div>
        <button class="pim-btn pim-btn-primary text-xs" :disabled="xliffExporting || xliffSourceLang === xliffTargetLang" @click="exportXliff">
          <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ xliffExporting ? 'Export…' : 'Export' }}
        </button>
        <div class="border-l border-[var(--color-border)] h-8" />
        <label class="pim-btn pim-btn-secondary text-xs cursor-pointer" :class="{ 'opacity-50 pointer-events-none': xliffImporting }">
          <Upload class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ xliffImporting ? 'Import…' : 'XLIFF Import' }}
          <input type="file" accept=".xliff,.xlf,.xml" class="hidden" @change="importXliff" />
        </label>
      </div>
      <div v-if="xliffImportResult" class="text-xs p-2 rounded" :class="xliffImportResult.errors?.length ? 'bg-yellow-50 text-yellow-800' : 'bg-green-50 text-green-800'">
        <p class="font-medium">{{ xliffImportResult.message }}</p>
        <p v-if="xliffImportResult.imported !== undefined">{{ xliffImportResult.imported }} {{ t('product.xliffImported') }}, {{ xliffImportResult.skipped }} {{ t('product.xliffSkipped') }}</p>
        <ul v-if="xliffImportResult.errors?.length" class="mt-1 list-disc list-inside">
          <li v-for="(err, i) in xliffImportResult.errors" :key="i">{{ err }}</li>
        </ul>
      </div>
    </div>

    <!-- Export error -->
    <div v-if="exportError" class="flex items-center justify-between gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ exportError }}</p>
      <button class="text-xs hover:underline" @click="exportError = null">Schließen</button>
    </div>

    <!-- Filter bar -->
    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Produkte durchsuchen…"
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearFilters"
    />

    <!-- Selection toolbar -->
    <div v-if="selectedProductIds.length > 0" class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs text-[var(--color-text-secondary)]">{{ selectedProductIds.length }} ausgewählt</span>
      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="bulkAddToWatchlist"
      >
        <Star class="w-3.5 h-3.5" :stroke-width="1.75" />
        Zur Merkliste
      </button>
      <button
        v-if="canCompare"
        class="pim-btn pim-btn-primary text-xs"
        @click="openCompare"
      >
        <GitCompareArrows class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Produkte vergleichen</span>
        <span class="sm:hidden">Vergleichen</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="openBulkEditor">
        <Pencil class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Bulk bearbeiten</span>
      </button>
      <span v-if="!canCompare && selectedProductIds.length === 1" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Noch 1 Produkt auswählen zum Vergleichen
      </span>
    </div>

    <!-- Table -->
    <PimTable
      :columns="visibleColumns"
      :rows="store.items"
      :loading="store.loading"
      :sortField="store.sort.field"
      :sortOrder="store.sort.order"
      selectable
      :showActions="authStore.hasPermission('products.delete')"
      emptyText="Keine Produkte gefunden"
      @sort="handleSort"
      @row-click="openProduct"
      @row-action="handleRowAction"
      @select="handleSelect"
    >
      <template #cell-sku="{ row, value }">
        <div class="flex items-center gap-2">
          <button
            class="p-0.5 rounded hover:bg-[var(--color-bg)] shrink-0"
            :title="isOnWatchlist(row.id) ? 'Von Merkliste entfernen' : 'Zur Merkliste hinzufügen'"
            @click.stop="toggleWatchlist(row.id)"
          >
            <Star
              class="w-3.5 h-3.5"
              :class="isOnWatchlist(row.id) ? 'text-amber-500 fill-amber-500' : 'text-[var(--color-text-tertiary)]'"
              :stroke-width="2"
            />
          </button>
          <span class="font-mono text-xs">{{ value }}</span>
        </div>
      </template>
      <template #cell-status="{ value }">
        <span
          :class="[
            'pim-badge',
            value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' :
            value === 'draft' ? 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' :
            'bg-[var(--color-error-light)] text-[var(--color-error)]'
          ]"
        >
          {{ value === 'active' ? 'Aktiv' : value === 'draft' ? 'Entwurf' : value === 'inactive' ? 'Inaktiv' : 'Auslaufend' }}
        </span>
      </template>

      <template #cell-updated_at="{ value }">
        <span class="text-[var(--color-text-tertiary)] text-xs">
          {{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}
        </span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">
            {{ store.meta.total }} Produkte
          </span>
          <div class="flex items-center gap-1">
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="store.meta.current_page <= 1"
              @click="store.setPage(store.meta.current_page - 1); store.fetchList()"
            >Zurück</button>
            <span class="text-xs text-[var(--color-text-secondary)] px-2">
              {{ store.meta.current_page }} / {{ store.meta.last_page }}
            </span>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="store.meta.current_page >= store.meta.last_page"
              @click="store.setPage(store.meta.current_page + 1); store.fetchList()"
            >Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Produkt löschen?"
      :message="`Das Produkt '${deleteTarget?.name || deleteTarget?.sku || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <!-- Product Comparison Modal -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showCompare" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showCompare = false" />
          <div class="relative w-full max-w-6xl max-h-[90vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)] shrink-0">
              <div class="flex items-center gap-3">
                <GitCompareArrows class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
                <span class="text-sm font-semibold text-[var(--color-text-primary)]">Produktvergleich</span>
                <span v-if="compareData" class="text-[11px] text-[var(--color-text-tertiary)]">
                  {{ compareData.total_differences }} Unterschiede von {{ compareData.total_attributes }} Feldern
                </span>
              </div>
              <div class="flex items-center gap-2">
                <label class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-secondary)] cursor-pointer">
                  <input type="checkbox" v-model="showDiffsOnly" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                  Nur Unterschiede
                </label>
                <button class="p-1.5 rounded hover:bg-[var(--color-bg)]" @click="showCompare = false">
                  <X class="w-4 h-4" :stroke-width="2" />
                </button>
              </div>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto">
              <div v-if="compareLoading" class="p-8 space-y-3">
                <div v-for="i in 8" :key="i" class="pim-skeleton h-8 w-full rounded" />
              </div>
              <table v-else-if="compareData" class="w-full text-[13px]">
                <thead class="sticky top-0 z-10">
                  <tr class="bg-[var(--color-bg)] border-b border-[var(--color-border)]">
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] w-[200px]">Attribut</th>
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-accent)]">
                      {{ compareData.product_a?.sku }}
                      <span class="font-normal normal-case text-[var(--color-text-tertiary)]">{{ compareData.product_a?.name }}</span>
                    </th>
                    <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-accent)]">
                      {{ compareData.product_b?.sku }}
                      <span class="font-normal normal-case text-[var(--color-text-tertiary)]">{{ compareData.product_b?.name }}</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, i) in compareRows"
                    :key="i"
                    :class="[
                      'border-b border-[var(--color-border)]',
                      row.is_different ? 'bg-amber-50/50' : '',
                    ]"
                  >
                    <td class="px-4 py-2 text-[12px] font-medium text-[var(--color-text-secondary)]">
                      {{ row.attribute_name }}
                      <span v-if="row.data_type && row.data_type !== 'base'" class="ml-1 text-[10px] text-[var(--color-text-tertiary)]">({{ row.data_type }})</span>
                    </td>
                    <td :class="['px-4 py-2', row.is_different ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)]']">
                      {{ row.value_a !== null && row.value_a !== '' ? row.value_a : '—' }}
                    </td>
                    <td :class="['px-4 py-2', row.is_different ? 'text-[var(--color-text-primary)] font-medium' : 'text-[var(--color-text-secondary)]']">
                      {{ row.value_b !== null && row.value_b !== '' ? row.value_b : '—' }}
                    </td>
                  </tr>
                  <tr v-if="compareRows.length === 0">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-[var(--color-text-tertiary)]">
                      {{ showDiffsOnly ? 'Keine Unterschiede gefunden — Produkte sind identisch' : 'Keine Daten zum Vergleichen' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>
