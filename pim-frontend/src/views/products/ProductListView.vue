<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProductStore } from '@/stores/products'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useFilters } from '@/composables/useFilters'
import { useLocaleStore } from '@/stores/locale'
import { Plus, Languages, Upload, Download, X } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ProductCreatePanel from '@/components/panels/ProductCreatePanel.vue'
import productsApi from '@/api/products'

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

const columns = [
  { key: 'sku', label: 'SKU', sortable: true, mono: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'product_type.name_de', label: 'Typ' },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'updated_at', label: 'Geändert', sortable: true },
]

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
    const url = URL.createObjectURL(resp.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `pim-translations-${xliffSourceLang.value}-${xliffTargetLang.value}.xliff`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 200)
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

onMounted(() => {
  store.fetchList()
  attrStore.fetchProductTypes()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('product.title') }}</h2>
      <div class="flex items-center gap-2">
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
      <div class="flex items-end gap-3">
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

    <!-- Filter bar -->
    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Produkte durchsuchen…"
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearFilters"
    />

    <!-- Table -->
    <PimTable
      :columns="columns"
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
    >
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
  </div>
</template>
