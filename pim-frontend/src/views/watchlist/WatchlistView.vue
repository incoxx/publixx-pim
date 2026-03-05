<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  Star, Trash2, Download, FileSpreadsheet, FileText,
  Languages, Archive, X, GitCompareArrows, Pencil,
} from 'lucide-vue-next'
import watchlistApi from '@/api/watchlist'
import productsApi from '@/api/products'
import PimTable from '@/components/shared/PimTable.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'

const router = useRouter()
const localeStore = useLocaleStore()

const items = ref([])
const loading = ref(false)
const error = ref(null)

// Export state
const showExportPanel = ref(false)
const exporting = ref(null) // 'excel' | 'pdf' | 'pdf-zip' | 'xliff' | null
const xliffSourceLang = ref('de')
const xliffTargetLang = ref('en')

// Delete state
const deleteTarget = ref(null)
const deleting = ref(false)

// Selection & comparison
const selectedIds = ref([])
const showCompare = ref(false)
const compareData = ref(null)
const compareLoading = ref(false)
const showDiffsOnly = ref(false)

const canCompare = computed(() => {
  // Need exactly 2 selected, and they must map to product_ids
  const productIds = selectedProductIds.value
  return productIds.length === 2
})

const selectedProductIds = computed(() => {
  return selectedIds.value
    .map(id => items.value.find(i => i.id === id)?.product_id)
    .filter(Boolean)
})

const compareRows = computed(() => {
  if (!compareData.value?.rows) return []
  if (showDiffsOnly.value) return compareData.value.rows.filter(r => r.is_different)
  return compareData.value.rows
})

const columns = [
  { key: 'product.sku', label: 'SKU', mono: true },
  { key: 'product.name', label: 'Name' },
  { key: 'product.status', label: 'Status' },
  { key: 'product.product_type.name_de', label: 'Typ' },
  { key: 'created_at', label: 'Hinzugefügt' },
]

const tableRows = computed(() =>
  items.value.map(item => ({
    ...item,
    id: item.id,
    _productId: item.product_id,
  }))
)

async function loadWatchlist() {
  loading.value = true
  error.value = null
  try {
    const { data } = await watchlistApi.list()
    items.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der Merkliste'
  } finally {
    loading.value = false
  }
}

function openProduct(row) {
  router.push(`/products/${row.product_id}`)
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await watchlistApi.remove(deleteTarget.value.id)
    items.value = items.value.filter(i => i.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 200)
}

async function exportExcel() {
  exporting.value = 'excel'
  try {
    const resp = await watchlistApi.exportExcel()
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0,10)}.xlsx`)
  } catch (e) { console.error('Excel export failed', e) }
  finally { exporting.value = null }
}

async function exportPdf() {
  exporting.value = 'pdf'
  try {
    const resp = await watchlistApi.exportPdf()
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0,10)}.pdf`)
  } catch (e) { console.error('PDF export failed', e) }
  finally { exporting.value = null }
}

async function exportPdfZip() {
  exporting.value = 'pdf-zip'
  try {
    const resp = await watchlistApi.exportPdfZip()
    triggerDownload(resp.data, `merkliste-${new Date().toISOString().slice(0,10)}.zip`)
  } catch (e) { console.error('PDF-ZIP export failed', e) }
  finally { exporting.value = null }
}

async function exportXliff() {
  exporting.value = 'xliff'
  try {
    const resp = await watchlistApi.exportXliff(xliffSourceLang.value, xliffTargetLang.value)
    triggerDownload(resp.data, `merkliste-${xliffSourceLang.value}-${xliffTargetLang.value}.xliff`)
  } catch (e) { console.error('XLIFF export failed', e) }
  finally { exporting.value = null }
}

// --- Selection & Comparison ---
function handleSelect(ids) {
  selectedIds.value = ids
}

async function openCompare() {
  if (!canCompare.value) return
  const pids = selectedProductIds.value
  showCompare.value = true
  compareLoading.value = true
  compareData.value = null
  try {
    const { data } = await productsApi.compare(pids[0], pids[1])
    compareData.value = data.data || data
  } catch (e) { console.error('Compare failed:', e) }
  finally { compareLoading.value = false }
}

function openBulkEditor() {
  const ids = selectedProductIds.value.join(',')
  router.push({ path: '/products/bulk-edit', query: { ids } })
}

onMounted(loadWatchlist)
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
          <Star class="w-4 h-4 text-amber-500 fill-amber-500" :stroke-width="2" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Merkliste</h2>
          <p class="text-xs text-[var(--color-text-tertiary)]">{{ items.length }} Produkt{{ items.length !== 1 ? 'e' : '' }} gespeichert</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="items.length > 0"
          class="pim-btn pim-btn-secondary text-xs"
          @click="showExportPanel = !showExportPanel"
        >
          <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
          Export
        </button>
      </div>
    </div>

    <!-- Export Panel -->
    <div v-if="showExportPanel && items.length > 0" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Download class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          Merkliste exportieren
        </h3>
        <button class="pim-btn pim-btn-ghost text-xs p-1" @click="showExportPanel = false">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <!-- Excel -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="exportExcel"
        >
          <FileSpreadsheet class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ exporting === 'excel' ? 'Export…' : 'Excel' }}
        </button>

        <!-- PDF: All in one -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="exportPdf"
        >
          <FileText class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ exporting === 'pdf' ? 'Export…' : 'PDF (Gesamt)' }}
        </button>

        <!-- PDF: Per SKU as ZIP -->
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="exporting !== null"
          @click="exportPdfZip"
        >
          <Archive class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ exporting === 'pdf-zip' ? 'Export…' : 'PDF pro SKU (ZIP)' }}
        </button>

        <div class="border-l border-[var(--color-border)] h-8 hidden sm:block" />

        <!-- XLIFF -->
        <div class="flex flex-wrap items-end gap-2">
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Quellsprache</label>
            <select class="pim-input text-xs w-24" v-model="xliffSourceLang">
              <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
            </select>
          </div>
          <div class="text-[var(--color-text-tertiary)] text-lg pb-1">→</div>
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Zielsprache</label>
            <select class="pim-input text-xs w-24" v-model="xliffTargetLang">
              <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
            </select>
          </div>
          <button
            class="pim-btn pim-btn-secondary text-xs"
            :disabled="exporting !== null || xliffSourceLang === xliffTargetLang"
            @click="exportXliff"
          >
            <Languages class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ exporting === 'xliff' ? 'Export…' : 'XLIFF' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Selection toolbar -->
    <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 py-2 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
      <span class="text-xs text-[var(--color-text-secondary)]">{{ selectedIds.length }} ausgewählt</span>
      <button
        v-if="canCompare"
        class="pim-btn pim-btn-primary text-xs"
        @click="openCompare"
      >
        <GitCompareArrows class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Vergleichen</span>
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="openBulkEditor">
        <Pencil class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Bulk bearbeiten</span>
      </button>
      <span v-if="selectedIds.length === 1" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Noch 1 Produkt auswählen zum Vergleichen
      </span>
      <span v-else-if="selectedIds.length > 2" class="text-[11px] text-[var(--color-text-tertiary)] hidden sm:inline">
        Max. 2 Produkte zum Vergleichen
      </span>
    </div>

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ error }}</p>
    </div>

    <!-- Table -->
    <PimTable
      :columns="columns"
      :rows="tableRows"
      :loading="loading"
      selectable
      showActions
      emptyText="Keine Produkte auf der Merkliste"
      @row-click="openProduct"
      @row-action="handleRowAction"
      @select="handleSelect"
    >
      <template #cell-product.sku="{ row, value }">
        <div class="flex items-center gap-2">
          <Star class="w-3.5 h-3.5 text-amber-500 fill-amber-500 shrink-0" :stroke-width="2" />
          <span class="font-mono text-xs">{{ value || '—' }}</span>
        </div>
      </template>
      <template #cell-product.status="{ value }">
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
      <template #cell-created_at="{ value }">
        <span class="text-[var(--color-text-tertiary)] text-xs">
          {{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}
        </span>
      </template>
    </PimTable>

    <!-- Empty state -->
    <div v-if="!loading && items.length === 0 && !error" class="text-center py-16">
      <Star class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Noch keine Produkte auf der Merkliste</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Füge Produkte über die Suche oder die Produktliste hinzu
      </p>
    </div>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Von Merkliste entfernen?"
      :message="`'${deleteTarget?.product?.name || deleteTarget?.product?.sku || ''}' wird von der Merkliste entfernt.`"
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
                    :class="['border-b border-[var(--color-border)]', row.is_different ? 'bg-amber-50/50' : '']"
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
