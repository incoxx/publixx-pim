<script setup>
import { ref, onMounted, markRaw, computed } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { useFilters } from '@/composables/useFilters'
import { Plus, Filter, X } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import AttributeFormPanel from '@/components/panels/AttributeFormPanel.vue'

const { t } = useI18n()
const store = useAttributeStore()
const authStore = useAuthStore()

// ─── Filters ─────────────────────────────────────────
const filterOpen = ref(false)
const activeFilterEntries = ref({})

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  loadWithFilters()
})

function loadWithFilters() {
  const opts = { search: search.value, include: 'attributeType,valueList,unitGroup,children,attributeViews' }
  if (Object.keys(activeFilterEntries.value).length > 0) {
    opts.filters = { ...activeFilterEntries.value }
  }
  store.fetchAttributes(opts)
}

function setFilter(key, value) {
  if (value === '' || value === null || value === undefined) {
    delete activeFilterEntries.value[key]
  } else {
    activeFilterEntries.value[key] = value
  }
  loadWithFilters()
}

function clearAllFilters() {
  activeFilterEntries.value = {}
  clearFilters()
  loadWithFilters()
}

const filterCount = computed(() => Object.keys(activeFilterEntries.value).length)

const boolFilterOptions = [
  { value: '', label: 'Alle' },
  { value: '1', label: 'Ja' },
  { value: '0', label: 'Nein' },
]

const dataTypeOptions = [
  { value: '', label: 'Alle' },
  { value: 'String', label: 'String' },
  { value: 'Number', label: 'Number' },
  { value: 'Float', label: 'Float' },
  { value: 'Date', label: 'Date' },
  { value: 'Flag', label: 'Flag' },
  { value: 'Selection', label: 'Selection' },
  { value: 'Dictionary', label: 'Dictionary' },
  { value: 'Collection', label: 'Collection' },
  { value: 'Composite', label: 'Composite' },
]

const attributeTypeOptions = computed(() => [
  { value: '', label: 'Alle Gruppen' },
  ...store.types.map(t => ({ value: t.id, label: t.name_de || t.technical_name })),
])

// ─── Columns ─────────────────────────────────────────
const columns = [
  { key: 'technical_name', label: 'Techn. Name', sortable: true, mono: true },
  { key: 'name_de', label: 'Name (DE)', sortable: true },
  { key: 'data_type', label: 'Datentyp', sortable: true },
  { key: 'attribute_type.name_de', label: 'Gruppe' },
  { key: '_views', label: 'Sichten' },
  { key: 'is_translatable', label: 'Übersetzbar' },
  { key: 'is_multipliable', label: 'Multipliziert' },
  { key: 'is_searchable', label: 'Suchbar' },
  { key: 'is_mandatory', label: 'Pflicht' },
  { key: 'is_unique', label: 'Einzigartig' },
  { key: 'is_inheritable', label: 'Vererbbar' },
  { key: 'is_variant_attribute', label: 'Varianten-Attr.' },
  { key: 'is_internal', label: 'Intern' },
  { key: 'description_de', label: 'Beschreibung' },
]

const deleteTarget = ref(null)
const deleting = ref(false)

function handleSort(field, order) {
  store.fetchAttributes({ sort: field, order, filters: activeFilterEntries.value, include: 'attributeType,valueList,unitGroup,children,attributeViews' })
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AttributeFormPanel), { attribute: null })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('attributes.edit')) return
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
  store.fetchAttributes({ include: 'attributeType,valueList,unitGroup,children,attributeViews' })
  store.fetchTypes()
  store.fetchValueLists()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('attribute.title') }}</h2>
      <div class="flex items-center gap-2">
        <button
          :class="[
            'pim-btn text-xs gap-1',
            filterCount > 0 ? 'pim-btn-primary' : 'pim-btn-secondary',
          ]"
          @click="filterOpen = !filterOpen"
        >
          <Filter class="w-3.5 h-3.5" :stroke-width="1.75" />
          Filter
          <span v-if="filterCount > 0" class="ml-0.5 bg-white/20 text-white px-1.5 py-0 rounded-full text-[10px]">{{ filterCount }}</span>
        </button>
        <button v-if="authStore.hasPermission('attributes.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
          <Plus class="w-4 h-4" :stroke-width="2" />
          {{ t('attribute.newAttribute') }}
        </button>
      </div>
    </div>

    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Attribute durchsuchen…"
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearAllFilters"
    />

    <!-- Filter Panel -->
    <div v-if="filterOpen" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Filter class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          Attribute filtern
        </h3>
        <div class="flex items-center gap-2">
          <button v-if="filterCount > 0" class="text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] underline" @click="clearAllFilters">
            Alle zurücksetzen
          </button>
          <button class="pim-btn pim-btn-ghost text-xs p-1" @click="filterOpen = false">
            <X class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Datentyp</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.data_type || ''" @change="setFilter('data_type', $event.target.value)">
            <option v-for="o in dataTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attributgruppe</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.attribute_type_id || ''" @change="setFilter('attribute_type_id', $event.target.value)">
            <option v-for="o in attributeTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Übersetzbar</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.is_translatable ?? ''" @change="setFilter('is_translatable', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Pflichtfeld</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.is_mandatory ?? ''" @change="setFilter('is_mandatory', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Suchbar</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.is_searchable ?? ''" @change="setFilter('is_searchable', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Vererbbar</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.is_inheritable ?? ''" @change="setFilter('is_inheritable', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Varianten-Attribut</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.is_variant_attribute ?? ''" @change="setFilter('is_variant_attribute', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Intern</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.is_internal ?? ''" @change="setFilter('is_internal', $event.target.value)">
            <option v-for="o in boolFilterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
      </div>
    </div>

    <PimTable
      :columns="columns"
      :rows="store.items"
      :loading="store.loading"
      selectable
      :showActions="authStore.hasPermission('attributes.delete')"
      emptyText="Keine Attribute gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-data_type="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value }}</span>
      </template>
      <template #cell-_views="{ row }">
        <div class="flex flex-wrap gap-0.5">
          <span v-for="v in (row.attribute_views || [])" :key="v.id" class="pim-badge bg-[var(--color-primary-light)] text-[var(--color-primary)] text-[10px]">{{ v.name_de || v.technical_name }}</span>
          <span v-if="!row.attribute_views?.length" class="text-[var(--color-text-tertiary)]">—</span>
        </div>
      </template>
      <template #cell-is_translatable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_multipliable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_searchable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_mandatory="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_unique="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_inheritable="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_variant_attribute="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-is_internal="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-description_de="{ value }">
        <span class="text-[var(--color-text-tertiary)] text-xs truncate max-w-[200px] block">{{ value || '—' }}</span>
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
