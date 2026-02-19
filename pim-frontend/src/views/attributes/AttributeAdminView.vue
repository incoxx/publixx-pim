<script setup>
import { onMounted } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { useI18n } from 'vue-i18n'
import { useFilters } from '@/composables/useFilters'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'

const { t } = useI18n()
const store = useAttributeStore()

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  store.fetchAttributes({ search: search.value })
})

const columns = [
  { key: 'code', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'data_type', label: 'Datentyp', sortable: true },
  { key: 'is_required', label: 'Pflicht' },
  { key: 'is_searchable', label: 'Suchbar' },
  { key: 'attribute_type.name_de', label: 'Gruppe' },
]

function handleSort(field, order) {
  store.fetchAttributes({ sort: field, order })
}

onMounted(() => {
  store.fetchAttributes()
  store.fetchTypes()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('attribute.title') }}</h2>
      <button class="pim-btn pim-btn-primary">
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
  </div>
</template>
