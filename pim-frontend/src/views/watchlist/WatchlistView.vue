<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  Star, Trash2, Download, FileSpreadsheet, FileText,
  Languages, Archive, X,
} from 'lucide-vue-next'
import watchlistApi from '@/api/watchlist'
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

onMounted(loadWatchlist)
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
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

        <div class="border-l border-[var(--color-border)] h-8" />

        <!-- XLIFF -->
        <div class="flex items-end gap-2">
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

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <p class="text-xs">{{ error }}</p>
    </div>

    <!-- Table -->
    <PimTable
      :columns="columns"
      :rows="tableRows"
      :loading="loading"
      showActions
      emptyText="Keine Produkte auf der Merkliste"
      @row-click="openProduct"
      @row-action="handleRowAction"
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
  </div>
</template>
