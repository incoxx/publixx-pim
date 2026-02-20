<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useFilters } from '@/composables/useFilters'
import { productTypes } from '@/api/attributes'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ProductTypeFormPanel from '@/components/panels/ProductTypeFormPanel.vue'

const { t } = useI18n()
const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)

async function fetchProductTypes(options = {}) {
  loading.value = true
  try {
    const { data } = await productTypes.list()
    items.value = data.data || data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  fetchProductTypes({ search: search.value })
})

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'has_variants', label: 'Varianten' },
  { key: 'has_ean', label: 'EAN' },
  { key: 'has_prices', label: 'Preise' },
  { key: 'has_media', label: 'Medien' },
]

const deleteTarget = ref(null)
const deleting = ref(false)

function handleSort(field, order) {
  fetchProductTypes({ sort: field, order })
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ProductTypeFormPanel), { productType: null, onSaved: fetchProductTypes })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(ProductTypeFormPanel), { productType: row, onSaved: fetchProductTypes })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await productTypes.delete(deleteTarget.value.id)
    items.value = items.value.filter(pt => pt.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchProductTypes()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('productType.title') }}</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        {{ t('productType.newProductType') }}
      </button>
    </div>

    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Produkttypen durchsuchen..."
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearFilters"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      selectable
      emptyText="Keine Produkttypen gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-has_variants="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
      <template #cell-has_ean="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
      <template #cell-has_prices="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
      <template #cell-has_media="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Produkttyp löschen?"
      :message="`Der Produkttyp '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
