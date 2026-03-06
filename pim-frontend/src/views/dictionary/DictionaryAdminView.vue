<script setup>
import { ref, onMounted, markRaw, computed } from 'vue'
import { useDictionaryStore } from '@/stores/dictionary'
import { useAuthStore } from '@/stores/auth'
import { useFilters } from '@/composables/useFilters'
import { Plus, Filter, X } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import DictionaryEntryFormPanel from '@/components/panels/DictionaryEntryFormPanel.vue'

const store = useDictionaryStore()
const authStore = useAuthStore()

// ─── Filters ─────────────────────────────────────────
const filterOpen = ref(false)
const activeFilterEntries = ref({})

const { search, activeFilters, setSearch, removeFilter, clearFilters } = useFilters(() => {
  loadWithFilters()
})

function loadWithFilters() {
  const opts = { search: search.value }
  if (Object.keys(activeFilterEntries.value).length > 0) {
    opts.filters = { ...activeFilterEntries.value }
  }
  store.fetchEntries(opts)
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

const statusOptions = [
  { value: '', label: 'Alle' },
  { value: 'active', label: 'Aktiv' },
  { value: 'inactive', label: 'Inaktiv' },
]

// ─── Columns ─────────────────────────────────────────
const columns = [
  { key: 'category', label: 'Kategorie', sortable: true },
  { key: 'short_text_de', label: 'Kurztext (DE)', sortable: true },
  { key: 'short_text_en', label: 'Kurztext (EN)', sortable: true },
  { key: 'long_text_de', label: 'Langtext (DE)' },
  { key: 'status', label: 'Status' },
]

const deleteTarget = ref(null)
const deleting = ref(false)

function handleSort(field, order) {
  store.fetchEntries({ sort: field, order, filters: activeFilterEntries.value })
}

function openCreatePanel() {
  authStore.openPanel(markRaw(DictionaryEntryFormPanel), { entry: null })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('attributes.edit')) return
  authStore.openPanel(markRaw(DictionaryEntryFormPanel), { entry: row })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await store.deleteEntry(deleteTarget.value.id)
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  store.fetchEntries()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Wörterbuch</h2>
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
          Neuer Eintrag
        </button>
      </div>
    </div>

    <PimFilterBar
      :search="search"
      :activeFilters="activeFilters"
      placeholder="Wörterbuch durchsuchen…"
      @update:search="setSearch"
      @remove-filter="removeFilter"
      @clear-all="clearAllFilters"
    />

    <!-- Filter Panel -->
    <div v-if="filterOpen" class="pim-card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          <Filter class="inline w-4 h-4 -mt-0.5 mr-1" :stroke-width="1.75" />
          Einträge filtern
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
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Status</label>
          <select class="pim-input text-xs" :value="activeFilterEntries.status || ''" @change="setFilter('status', $event.target.value)">
            <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Kategorie</label>
          <input
            type="text"
            class="pim-input text-xs"
            :value="activeFilterEntries.category || ''"
            placeholder="z.B. Verpackung"
            @input="setFilter('category', $event.target.value)"
          />
        </div>
      </div>
    </div>

    <PimTable
      :columns="columns"
      :rows="store.items"
      :loading="store.loading"
      selectable
      :showActions="authStore.hasPermission('attributes.delete')"
      emptyText="Keine Wörterbucheinträge gefunden"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-category="{ value }">
        <span v-if="value" class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value }}</span>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>
      <template #cell-long_text_de="{ value }">
        <span class="text-[var(--color-text-tertiary)] text-xs truncate max-w-[300px] block">{{ value || '—' }}</span>
      </template>
      <template #cell-status="{ value }">
        <span :class="value === 'active' ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value === 'active' ? 'Aktiv' : 'Inaktiv' }}
        </span>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Wörterbucheintrag löschen?"
      :message="`Der Eintrag '${deleteTarget?.short_text_de || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
