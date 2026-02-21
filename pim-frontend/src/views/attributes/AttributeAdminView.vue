<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useFilters } from '@/composables/useFilters'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import AttributeFormPanel from '@/components/panels/AttributeFormPanel.vue'

const { t } = useI18n()
const store = useAttributeStore()
const authStore = useAuthStore()

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  store.fetchAttributes({ search: search.value })
})

const columns = [
  { key: 'code', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'data_type', label: 'Datentyp', sortable: true },
  { key: 'value_list.name_de', label: 'Werteliste' },
  { key: 'is_required', label: 'Pflicht' },
  { key: 'is_searchable', label: 'Suchbar' },
  { key: 'attribute_type.name_de', label: 'Gruppe' },
]

const deleteTarget = ref(null)
const deleting = ref(false)

function handleSort(field, order) {
  store.fetchAttributes({ sort: field, order })
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AttributeFormPanel), { attribute: null })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(AttributeFormPanel), { attribute: row })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await store.deleteAttribute(deleteTarget.value.id)
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  store.fetchAttributes()
  store.fetchTypes()
  store.fetchValueLists()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('attribute.title') }}</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        {{ t('attribute.newAttribute') }}
      </button>
    </div>

    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Attribute durchsuchen…"
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearFilters"
    />

    <PimTable
      :columns="columns"
      :rows="store.items"
      :loading="store.loading"
      selectable
      emptyText="Keine Attribute gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-data_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value }}</span>
      </template>
      <template #cell-is_required="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
      <template #cell-is_searchable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Attribut löschen?"
      :message="`Das Attribut '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
