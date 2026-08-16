<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeMetadataDefinitions } from '@/api/attributes'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import AttributeMetadataFormPanel from '@/components/panels/AttributeMetadataFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('sort_order')
const sortOrder = ref('asc')

const VALUE_TYPE_LABELS = {
  text: 'Text',
  textarea: 'Mehrzeiliger Text',
  number: 'Zahl',
  date: 'Datum',
  boolean: 'Ja/Nein',
  select: 'Auswahl',
  multiselect: 'Mehrfachauswahl',
  url: 'Link',
  email: 'E-Mail',
}

const columns = [
  { key: 'technical_name', label: 'Technischer Name', sortable: true, mono: true },
  { key: 'name_de', label: 'Name DE', sortable: true },
  { key: 'value_type', label: 'Wert-Typ' },
  { key: 'is_required', label: 'Pflicht' },
  { key: 'values_count', label: 'Attribute' },
  { key: 'sort_order', label: 'Sortierung', sortable: true },
]

async function fetchDefinitions() {
  loading.value = true
  try {
    const { data } = await attributeMetadataDefinitions.list({
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
  fetchDefinitions()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchDefinitions()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AttributeMetadataFormPanel), {
    definition: null,
    onSaved: () => fetchDefinitions(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('attribute-metadata.edit')) return
  authStore.openPanel(markRaw(AttributeMetadataFormPanel), {
    definition: row,
    onSaved: () => fetchDefinitions(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await attributeMetadataDefinitions.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchDefinitions()
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchDefinitions())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Metadaten</h2>
        <p class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">
          Herkunft und Zuständigkeit von Attributen — Datenherkunft, Dateneigentümer, Datenverbindung
        </p>
      </div>
      <button
        v-if="authStore.hasPermission('attribute-metadata.create')"
        class="pim-btn pim-btn-primary"
        @click="openCreatePanel"
      >
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neues Metadatum
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Metadaten durchsuchen…"
      @update:search="v => { search = v; meta.current_page = 1; fetchDefinitions() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="authStore.hasPermission('attribute-metadata.delete')"
      emptyText="Keine Metadaten definiert"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-value_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">
          {{ VALUE_TYPE_LABELS[value] || value }}
        </span>
      </template>

      <template #cell-is_required="{ value }">
        <span v-if="value" class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">Ja</span>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>

      <template #cell-values_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>

      <!-- Pagination -->
      <template #pagination>
        <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
          <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Metadaten</span>
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
      title="Metadatum löschen?"
      :message="`Das Metadatum '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      entityType="attribute-metadata-definitions"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
