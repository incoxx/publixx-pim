<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProductStore } from '@/stores/products'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useFilters } from '@/composables/useFilters'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ProductCreatePanel from '@/components/panels/ProductCreatePanel.vue'

const { t } = useI18n()
const router = useRouter()
const store = useProductStore()
const attrStore = useAttributeStore()
const authStore = useAuthStore()

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
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        {{ t('product.newProduct') }}
      </button>
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
