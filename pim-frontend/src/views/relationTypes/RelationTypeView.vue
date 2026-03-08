<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useFilters } from '@/composables/useFilters'
import { relationTypes } from '@/api/prices'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import RelationTypeFormPanel from '@/components/panels/RelationTypeFormPanel.vue'

const { t } = useI18n()
const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)

async function fetchRelationTypes(options = {}) {
  loading.value = true
  try {
    const { data } = await relationTypes.list()
    items.value = data.data || data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  fetchRelationTypes({ search: search.value })
})

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'name_en', label: 'Name (EN)', sortable: true },
  { key: 'is_bidirectional', label: 'Bidirektional' },
]

const deleteTarget = ref(null)
const deleting = ref(false)

function handleSort(field, order) {
  fetchRelationTypes({ sort: field, order })
}

function openCreatePanel() {
  authStore.openPanel(markRaw(RelationTypeFormPanel), { relationType: null, onSaved: fetchRelationTypes })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(RelationTypeFormPanel), { relationType: row, onSaved: fetchRelationTypes })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await relationTypes.delete(deleteTarget.value.id)
    items.value = items.value.filter(rt => rt.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchRelationTypes()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('relationType.title') }}</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        {{ t('relationType.newRelationType') }}
      </button>
    </div>

    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Beziehungstypen durchsuchen..."
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearFilters"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      selectable
      showActions
      emptyText="Keine Beziehungstypen gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-is_bidirectional="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Beziehungstyp löschen?"
      :message="`Der Beziehungstyp '${deleteTarget?.name_de || deleteTarget?.technical_name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
