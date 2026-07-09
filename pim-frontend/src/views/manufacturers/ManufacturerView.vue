<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import manufacturersApi from '@/api/manufacturers'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import ManufacturerFormPanel from '@/components/panels/ManufacturerFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('name')
const sortOrder = ref('asc')

const columns = [
  { key: 'name', label: 'Firma', sortable: true },
  { key: 'city', label: 'Stadt', sortable: true },
  { key: 'country', label: 'Land', sortable: true },
  { key: 'email', label: 'E-Mail' },
  { key: 'website', label: 'Webseite' },
  { key: 'products_count', label: 'Produkte' },
]

async function fetchManufacturers() {
  loading.value = true
  try {
    const { data } = await manufacturersApi.list({
      include: 'products_count',
      search: search.value || undefined,
      sort: sortField.value,
      order: sortOrder.value,
      perPage: meta.value.per_page,
      page: meta.value.current_page,
    })
    items.value = data.data || data
    if (data.meta) meta.value = data.meta
  } finally {
    loading.value = false
  }
}

function handleSort(field, order) {
  sortField.value = field
  sortOrder.value = order
  meta.value.current_page = 1
  fetchManufacturers()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchManufacturers()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ManufacturerFormPanel), {
    manufacturer: null,
    onSaved: () => fetchManufacturers(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('manufacturers.edit')) return
  authStore.openPanel(markRaw(ManufacturerFormPanel), {
    manufacturer: row,
    onSaved: () => fetchManufacturers(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await manufacturersApi.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchManufacturers()
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchManufacturers())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Hersteller</h2>
      <button v-if="authStore.hasPermission('manufacturers.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neuer Hersteller
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Hersteller durchsuchen…"
      @update:search="v => { search = v; meta.current_page = 1; fetchManufacturers() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('manufacturers.delete')"
      emptyText="Keine Hersteller gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-products_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>
      <template #cell-website="{ value }">
        <a v-if="value" :href="value.startsWith('http') ? value : 'https://' + value" target="_blank" rel="noopener" class="text-[var(--color-accent)] hover:underline text-xs truncate block max-w-[200px]">{{ value }}</a>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>
      <template #cell-email="{ value }">
        <a v-if="value" :href="'mailto:' + value" class="text-[var(--color-accent)] hover:underline text-xs">{{ value }}</a>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Hersteller</span>
          <div class="flex items-center gap-1">
            <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page <= 1" @click="handlePageChange(meta.current_page - 1)">Zurück</button>
            <span class="text-xs text-[var(--color-text-secondary)] px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page >= meta.last_page" @click="handlePageChange(meta.current_page + 1)">Weiter</button>
          </div>
        </div>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Hersteller löschen?"
      :message="`Der Hersteller '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      entityType="manufacturers"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
