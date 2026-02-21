<script setup>
import { ref, onMounted, markRaw, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { valueLists } from '@/api/attributes'
import { Plus, ChevronLeft, Trash2, ArrowUp, ArrowDown, Check, X } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ValueListFormPanel from '@/components/panels/ValueListFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

// Detail / Entries state
const selectedList = ref(null)
const entries = ref([])
const entriesLoading = ref(false)
const deleteEntryTarget = ref(null)
const deletingEntry = ref(false)

// Inline add
const showAddRow = ref(false)
const newEntry = ref({ technical_name: '', display_value_de: '', display_value_en: '', sort_order: 0 })

// Inline edit
const editingEntryId = ref(null)
const editForm = ref({})

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name', sortable: true },
  { key: 'value_data_type', label: 'Datentyp' },
  { key: 'entries_count', label: 'Einträge', align: 'right' },
]

async function fetchLists() {
  loading.value = true
  try {
    const { data } = await valueLists.list({ include: 'entries', search: search.value || undefined })
    items.value = (data.data || data).map(item => ({
      ...item,
      entries_count: item.entries?.length ?? item.entries_count ?? 0,
    }))
  } finally { loading.value = false }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ValueListFormPanel), {
    valueList: null,
    onSaved: () => fetchLists(),
  })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(ValueListFormPanel), {
    valueList: row,
    onSaved: () => { fetchLists(); if (selectedList.value?.id === row.id) fetchEntries() },
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await valueLists.delete(deleteTarget.value.id)
    if (selectedList.value?.id === deleteTarget.value.id) selectedList.value = null
    deleteTarget.value = null
    await fetchLists()
  } finally { deleting.value = false }
}

function selectList(row) {
  selectedList.value = row
}

async function fetchEntries() {
  if (!selectedList.value) return
  entriesLoading.value = true
  try {
    const { data } = await valueLists.getEntries(selectedList.value.id, { perPage: 200 })
    entries.value = (data.data || data).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
  } finally { entriesLoading.value = false }
}

watch(selectedList, (v) => {
  if (v) fetchEntries()
  else entries.value = []
  editingEntryId.value = null
  showAddRow.value = false
})

// Add entry
function openAddRow() {
  const maxSort = entries.value.reduce((max, e) => Math.max(max, e.sort_order || 0), 0)
  newEntry.value = { technical_name: '', display_value_de: '', display_value_en: '', sort_order: maxSort + 10 }
  showAddRow.value = true
  editingEntryId.value = null
}

async function saveNewEntry() {
  if (!newEntry.value.technical_name.trim()) return
  try {
    await valueLists.addEntry(selectedList.value.id, {
      technical_name: newEntry.value.technical_name,
      display_value_de: newEntry.value.display_value_de,
      display_value_en: newEntry.value.display_value_en,
      sort_order: newEntry.value.sort_order,
      is_active: true,
    })
    showAddRow.value = false
    await fetchEntries()
    await fetchLists()
  } catch (e) {
    console.error('Failed to add entry:', e)
  }
}

// Edit entry inline
function startEdit(entry) {
  editingEntryId.value = entry.id
  editForm.value = {
    technical_name: entry.technical_name,
    display_value_de: entry.display_value_de || '',
    display_value_en: entry.display_value_en || '',
    sort_order: entry.sort_order ?? 0,
    is_active: entry.is_active,
  }
  showAddRow.value = false
}

function cancelEdit() {
  editingEntryId.value = null
}

async function saveEdit() {
  try {
    await valueLists.updateEntry(editingEntryId.value, editForm.value)
    editingEntryId.value = null
    await fetchEntries()
    await fetchLists()
  } catch (e) {
    console.error('Failed to update entry:', e)
  }
}

// Delete entry
async function confirmDeleteEntry() {
  deletingEntry.value = true
  try {
    await valueLists.deleteEntry(deleteEntryTarget.value.id)
    deleteEntryTarget.value = null
    await fetchEntries()
    await fetchLists()
  } finally { deletingEntry.value = false }
}

// Move entry up/down
async function moveEntry(entry, direction) {
  const idx = entries.value.findIndex(e => e.id === entry.id)
  const swapIdx = direction === 'up' ? idx - 1 : idx + 1
  if (swapIdx < 0 || swapIdx >= entries.value.length) return
  const other = entries.value[swapIdx]
  try {
    await Promise.all([
      valueLists.updateEntry(entry.id, { sort_order: other.sort_order }),
      valueLists.updateEntry(other.id, { sort_order: entry.sort_order }),
    ])
    await fetchEntries()
  } catch (e) {
    console.error('Failed to reorder:', e)
  }
}

onMounted(() => fetchLists())
</script>

<template>
  <div class="flex gap-6 h-full">
    <!-- Left: List overview -->
    <div :class="['space-y-4 transition-all', selectedList ? 'w-1/3 flex-none' : 'flex-1']">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Wertelisten</h2>
        <button class="pim-btn pim-btn-primary" @click="openCreatePanel"><Plus class="w-4 h-4" :stroke-width="2" /> Neue Werteliste</button>
      </div>
      <PimFilterBar :search="search" placeholder="Wertelisten durchsuchen..." @update:search="v => { search = v; fetchLists() }" />
      <PimTable
        :columns="columns"
        :rows="items"
        :loading="loading"
        :activeRowId="selectedList?.id"
        emptyText="Keine Wertelisten"
        @row-click="selectList"
        @row-action="handleRowAction"
      />
    </div>

    <!-- Right: Entry management -->
    <div v-if="selectedList" class="flex-1 space-y-4 min-w-0">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button class="pim-btn pim-btn-ghost p-1.5" @click="selectedList = null" title="Zurück">
            <ChevronLeft class="w-4 h-4" :stroke-width="2" />
          </button>
          <div>
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ selectedList.name_de }}</h3>
            <p class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ selectedList.technical_name }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-ghost text-xs" @click="openEditPanel(selectedList)">Bearbeiten</button>
          <button class="pim-btn pim-btn-primary text-xs" @click="openAddRow"><Plus class="w-3.5 h-3.5" :stroke-width="2" /> Eintrag</button>
        </div>
      </div>

      <!-- Entries table -->
      <div class="pim-card overflow-hidden">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px] uppercase tracking-wider">
              <th class="px-3 py-2 text-left w-8">#</th>
              <th class="px-3 py-2 text-left">Schlüssel (Key)</th>
              <th class="px-3 py-2 text-left">Wert (DE)</th>
              <th class="px-3 py-2 text-left">Wert (EN)</th>
              <th class="px-3 py-2 text-center w-16">Aktiv</th>
              <th class="px-3 py-2 text-right w-28">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading skeleton -->
            <template v-if="entriesLoading">
              <tr v-for="i in 5" :key="i">
                <td colspan="6" class="px-3 py-2"><div class="pim-skeleton h-4 rounded" /></td>
              </tr>
            </template>

            <!-- Entries -->
            <template v-else>
              <tr
                v-for="(entry, idx) in entries"
                :key="entry.id"
                class="border-t border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
              >
                <template v-if="editingEntryId === entry.id">
                  <!-- Edit mode -->
                  <td class="px-3 py-1.5 text-[var(--color-text-tertiary)]">{{ idx + 1 }}</td>
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.technical_name" class="pim-input text-xs w-full font-mono" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.display_value_de" class="pim-input text-xs w-full" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.display_value_en" class="pim-input text-xs w-full" />
                  </td>
                  <td class="px-3 py-1.5 text-center">
                    <input type="checkbox" v-model="editForm.is_active" class="rounded" />
                  </td>
                  <td class="px-3 py-1.5 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <button class="p-1 rounded hover:bg-[var(--color-primary-light)] text-[var(--color-primary)]" @click="saveEdit" title="Speichern">
                        <Check class="w-3.5 h-3.5" :stroke-width="2" />
                      </button>
                      <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="cancelEdit" title="Abbrechen">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                      </button>
                    </div>
                  </td>
                </template>

                <template v-else>
                  <!-- View mode -->
                  <td class="px-3 py-1.5 text-[var(--color-text-tertiary)]">{{ idx + 1 }}</td>
                  <td class="px-3 py-1.5 font-mono text-[var(--color-text-primary)]">{{ entry.technical_name }}</td>
                  <td class="px-3 py-1.5 text-[var(--color-text-primary)]">{{ entry.display_value_de || '—' }}</td>
                  <td class="px-3 py-1.5 text-[var(--color-text-secondary)]">{{ entry.display_value_en || '—' }}</td>
                  <td class="px-3 py-1.5 text-center">
                    <span :class="['inline-block w-2 h-2 rounded-full', entry.is_active ? 'bg-green-500' : 'bg-gray-300']" />
                  </td>
                  <td class="px-3 py-1.5 text-right">
                    <div class="flex items-center justify-end gap-0.5">
                      <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="moveEntry(entry, 'up')" :disabled="idx === 0" title="Nach oben">
                        <ArrowUp class="w-3 h-3" :stroke-width="2" />
                      </button>
                      <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="moveEntry(entry, 'down')" :disabled="idx === entries.length - 1" title="Nach unten">
                        <ArrowDown class="w-3 h-3" :stroke-width="2" />
                      </button>
                      <button class="p-1 rounded hover:bg-[var(--color-primary-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-primary)]" @click="startEdit(entry)" title="Bearbeiten">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                      </button>
                      <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="deleteEntryTarget = entry" title="Löschen">
                        <Trash2 class="w-3 h-3" :stroke-width="2" />
                      </button>
                    </div>
                  </td>
                </template>
              </tr>

              <!-- Add row -->
              <tr v-if="showAddRow" class="border-t border-[var(--color-border)] bg-[var(--color-primary-light)]">
                <td class="px-3 py-1.5 text-[var(--color-text-tertiary)]">+</td>
                <td class="px-3 py-1.5">
                  <input v-model="newEntry.technical_name" class="pim-input text-xs w-full font-mono" placeholder="z.B. #ff0000 oder rot" @keyup.enter="saveNewEntry" />
                </td>
                <td class="px-3 py-1.5">
                  <input v-model="newEntry.display_value_de" class="pim-input text-xs w-full" placeholder="Anzeigename DE" @keyup.enter="saveNewEntry" />
                </td>
                <td class="px-3 py-1.5">
                  <input v-model="newEntry.display_value_en" class="pim-input text-xs w-full" placeholder="Display name EN" @keyup.enter="saveNewEntry" />
                </td>
                <td class="px-3 py-1.5 text-center">—</td>
                <td class="px-3 py-1.5 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button class="p-1 rounded hover:bg-[var(--color-primary-light)] text-[var(--color-primary)]" @click="saveNewEntry" title="Speichern">
                      <Check class="w-3.5 h-3.5" :stroke-width="2" />
                    </button>
                    <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="showAddRow = false" title="Abbrechen">
                      <X class="w-3.5 h-3.5" :stroke-width="2" />
                    </button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Empty -->
            <tr v-if="!entriesLoading && entries.length === 0 && !showAddRow">
              <td colspan="6" class="px-3 py-8 text-center text-[var(--color-text-tertiary)] text-xs">
                Noch keine Einträge. Klicken Sie auf "+ Eintrag" um den ersten Eintrag anzulegen.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm delete list -->
    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Werteliste löschen?"
      :message="`Die Werteliste '${deleteTarget?.name_de || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <!-- Confirm delete entry -->
    <PimConfirmDialog
      :open="!!deleteEntryTarget"
      title="Eintrag löschen?"
      :message="`Der Eintrag '${deleteEntryTarget?.technical_name || ''}' wird gelöscht.`"
      :loading="deletingEntry"
      @confirm="confirmDeleteEntry"
      @cancel="deleteEntryTarget = null"
    />
  </div>
</template>
