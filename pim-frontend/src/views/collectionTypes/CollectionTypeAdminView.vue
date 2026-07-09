<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { collectionTypes } from '@/api/collections'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import CollectionTypeFormPanel from '@/components/panels/CollectionTypeFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)
const deleteError = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('sort_order')
const sortOrder = ref('asc')

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name DE', sortable: true },
  { key: 'name_en', label: 'Name EN', sortable: true },
  { key: 'requires_organization', label: 'Empfänger nötig' },
  { key: 'requires_snapshot', label: 'Einfrieren nötig' },
  { key: 'sort_order', label: 'Sortierung', sortable: true },
  { key: 'is_active', label: 'Aktiv' },
]

async function fetchTypes() {
  loading.value = true
  try {
    const { data } = await collectionTypes.list({
      search: search.value,
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
  fetchTypes()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchTypes()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(CollectionTypeFormPanel), {
    collectionType: null,
    onSaved: () => fetchTypes(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('collection-types.edit')) return
  authStore.openPanel(markRaw(CollectionTypeFormPanel), {
    collectionType: row,
    onSaved: () => fetchTypes(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
  deleteError.value = ''
}

async function confirmDelete({ force }) {
  deleting.value = true
  deleteError.value = ''
  try {
    await collectionTypes.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchTypes()
  } catch (e) {
    deleteError.value = e.response?.data?.detail || e.response?.data?.message || 'Löschen fehlgeschlagen'
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchTypes())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Collection-Typen</h2>
      <button v-if="authStore.hasPermission('collection-types.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neuer Typ
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Collection-Typen durchsuchen…"
      @update:search="v => { search = v; meta.current_page = 1; fetchTypes() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('collection-types.delete')"
      emptyText="Keine Collection-Typen gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-requires_organization="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-requires_snapshot="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_active="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Collection-Typen</span>
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
      title="Collection-Typ löschen?"
      :message="`Der Collection-Typ '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      :error="deleteError"
      entityType="collection-types"
      :entityId="deleteTarget?.id"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null; deleteError = ''"
    />
  </div>
</template>
