<script setup>
import { ref, onMounted, markRaw, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { unitGroups, units } from '@/api/units'
import { Plus, ChevronLeft, Trash2, Check, X } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import UnitGroupFormPanel from '@/components/panels/UnitGroupFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

// Detail state
const selectedGroup = ref(null)
const unitList = ref([])
const unitsLoading = ref(false)
const deleteUnitTarget = ref(null)
const deletingUnit = ref(false)

// Inline add
const showAddRow = ref(false)
const newUnit = ref({ technical_name: '', abbreviation: '', conversion_factor: 1, is_base_unit: false })

// Error feedback
const unitError = ref(null)

// Inline edit
const editingUnitId = ref(null)
const editForm = ref({})

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name', sortable: true },
  { key: 'units_count', label: 'Einheiten', align: 'right' },
]

async function fetchGroups() {
  loading.value = true
  try {
    const { data } = await unitGroups.list({ include: 'units', search: search.value || undefined })
    items.value = (data.data || data).map(item => ({
      ...item,
      units_count: item.units?.length ?? 0,
    }))
  } finally { loading.value = false }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(UnitGroupFormPanel), {
    unitGroup: null,
    onSaved: () => fetchGroups(),
  })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(UnitGroupFormPanel), {
    unitGroup: row,
    onSaved: () => { fetchGroups(); if (selectedGroup.value?.id === row.id) fetchUnits() },
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await unitGroups.delete(deleteTarget.value.id)
    if (selectedGroup.value?.id === deleteTarget.value.id) selectedGroup.value = null
    deleteTarget.value = null
    await fetchGroups()
  } finally { deleting.value = false }
}

function selectGroup(row) {
  selectedGroup.value = row
}

async function fetchUnits() {
  if (!selectedGroup.value) return
  unitsLoading.value = true
  try {
    const { data } = await units.list(selectedGroup.value.id)
    unitList.value = (data.data || data).sort((a, b) => (b.is_base_unit ? 1 : 0) - (a.is_base_unit ? 1 : 0))
  } finally { unitsLoading.value = false }
}

watch(selectedGroup, (v) => {
  if (v) {
    fetchUnits()
  } else {
    unitList.value = []
  }
  editingUnitId.value = null
  showAddRow.value = false
})

// Add unit
function openAddRow() {
  newUnit.value = { technical_name: '', abbreviation: '', conversion_factor: 1, is_base_unit: false }
  showAddRow.value = true
  editingUnitId.value = null
}

async function saveNewUnit() {
  if (!newUnit.value.technical_name.trim()) return
  unitError.value = null
  try {
    await units.create(selectedGroup.value.id, newUnit.value)
    showAddRow.value = false
    await fetchUnits()
    await fetchGroups()
  } catch (e) {
    const msg = e.response?.data?.message || e.response?.data?.errors?.technical_name?.[0]
    unitError.value = msg || 'Einheit konnte nicht erstellt werden'
  }
}

// Edit unit inline
function startEdit(unit) {
  editingUnitId.value = unit.id
  editForm.value = {
    technical_name: unit.technical_name,
    abbreviation: unit.abbreviation || '',
    conversion_factor: unit.conversion_factor ?? 1,
    is_base_unit: unit.is_base_unit,
  }
  showAddRow.value = false
}

function cancelEdit() {
  editingUnitId.value = null
}

async function saveEdit() {
  unitError.value = null
  try {
    await units.update(editingUnitId.value, editForm.value)
    editingUnitId.value = null
    await fetchUnits()
  } catch (e) {
    const msg = e.response?.data?.message || e.response?.data?.errors?.technical_name?.[0]
    unitError.value = msg || 'Einheit konnte nicht aktualisiert werden'
  }
}

// Delete unit
async function confirmDeleteUnit() {
  deletingUnit.value = true
  try {
    await units.delete(deleteUnitTarget.value.id)
    deleteUnitTarget.value = null
    await fetchUnits()
    await fetchGroups()
  } finally { deletingUnit.value = false }
}

onMounted(() => fetchGroups())
</script>

<template>
  <div class="flex gap-6 h-full">
    <!-- Left: Group list -->
    <div :class="['space-y-4 transition-all', selectedGroup ? 'w-1/3 flex-none' : 'flex-1']">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Einheitengruppen</h2>
        <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
          <Plus class="w-4 h-4" :stroke-width="2" /> Neue Gruppe
        </button>
      </div>
      <PimFilterBar :search="search" placeholder="Einheitengruppen durchsuchen..." @update:search="v => { search = v; fetchGroups() }" />
      <PimTable
        :columns="columns"
        :rows="items"
        :loading="loading"
        :activeRowId="selectedGroup?.id"
        showActions
        emptyText="Keine Einheitengruppen"
        @row-click="selectGroup"
        @row-action="handleRowAction"
      />
    </div>

    <!-- Right: Unit management -->
    <div v-if="selectedGroup" class="flex-1 space-y-4 min-w-0">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button class="pim-btn pim-btn-ghost p-1.5" @click="selectedGroup = null" title="Zurück">
            <ChevronLeft class="w-4 h-4" :stroke-width="2" />
          </button>
          <div>
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ selectedGroup.name_de }}</h3>
            <p class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ selectedGroup.technical_name }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-ghost text-xs" @click="openEditPanel(selectedGroup)">Bearbeiten</button>
          <button class="pim-btn pim-btn-primary text-xs" @click="openAddRow">
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Einheit
          </button>
        </div>
      </div>

      <!-- Error -->
      <div v-if="unitError" class="flex items-center justify-between gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
        <p class="text-xs">{{ unitError }}</p>
        <button class="text-xs hover:underline" @click="unitError = null">Schließen</button>
      </div>

      <!-- Units table -->
      <div class="pim-card overflow-hidden">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px] uppercase tracking-wider">
              <th class="px-3 py-2 text-left">Technischer Name</th>
              <th class="px-3 py-2 text-left">Abkürzung</th>
              <th class="px-3 py-2 text-right">Umrechnungsfaktor</th>
              <th class="px-3 py-2 text-center w-20">Basis</th>
              <th class="px-3 py-2 text-right w-28">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading -->
            <template v-if="unitsLoading">
              <tr v-for="i in 3" :key="i">
                <td colspan="5" class="px-3 py-2"><div class="pim-skeleton h-4 rounded" /></td>
              </tr>
            </template>

            <template v-else>
              <tr
                v-for="unit in unitList"
                :key="unit.id"
                class="border-t border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
              >
                <template v-if="editingUnitId === unit.id">
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.technical_name" class="pim-input text-xs w-full font-mono" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.abbreviation" class="pim-input text-xs w-full" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model.number="editForm.conversion_factor" type="number" step="any" class="pim-input text-xs w-full text-right" />
                  </td>
                  <td class="px-3 py-1.5 text-center">
                    <input type="checkbox" v-model="editForm.is_base_unit" class="rounded" />
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
                  <td class="px-3 py-1.5 font-mono text-[var(--color-text-primary)]">{{ unit.technical_name }}</td>
                  <td class="px-3 py-1.5 text-[var(--color-text-primary)]">{{ unit.abbreviation || '—' }}</td>
                  <td class="px-3 py-1.5 text-right text-[var(--color-text-secondary)]">{{ unit.conversion_factor }}</td>
                  <td class="px-3 py-1.5 text-center">
                    <span :class="['inline-block w-2 h-2 rounded-full', unit.is_base_unit ? 'bg-green-500' : 'bg-gray-300']" />
                  </td>
                  <td class="px-3 py-1.5 text-right">
                    <div class="flex items-center justify-end gap-0.5">
                      <button class="p-1 rounded hover:bg-[var(--color-primary-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-primary)]" @click="startEdit(unit)" title="Bearbeiten">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                      </button>
                      <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="deleteUnitTarget = unit" title="Löschen">
                        <Trash2 class="w-3 h-3" :stroke-width="2" />
                      </button>
                    </div>
                  </td>
                </template>
              </tr>

              <!-- Add row -->
              <tr v-if="showAddRow" class="border-t border-[var(--color-border)] bg-[var(--color-primary-light)]">
                <td class="px-3 py-1.5">
                  <input v-model="newUnit.technical_name" class="pim-input text-xs w-full font-mono" placeholder="z.B. kilogramm" @keyup.enter="saveNewUnit" />
                </td>
                <td class="px-3 py-1.5">
                  <input v-model="newUnit.abbreviation" class="pim-input text-xs w-full" placeholder="z.B. kg" @keyup.enter="saveNewUnit" />
                </td>
                <td class="px-3 py-1.5">
                  <input v-model.number="newUnit.conversion_factor" type="number" step="any" class="pim-input text-xs w-full text-right" @keyup.enter="saveNewUnit" />
                </td>
                <td class="px-3 py-1.5 text-center">
                  <input type="checkbox" v-model="newUnit.is_base_unit" class="rounded" />
                </td>
                <td class="px-3 py-1.5 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button class="p-1 rounded hover:bg-[var(--color-primary-light)] text-[var(--color-primary)]" @click="saveNewUnit" title="Speichern">
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
            <tr v-if="!unitsLoading && unitList.length === 0 && !showAddRow">
              <td colspan="5" class="px-3 py-8 text-center text-[var(--color-text-tertiary)] text-xs">
                Noch keine Einheiten. Klicken Sie auf "+ Einheit" um die erste Einheit anzulegen.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm delete group -->
    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Einheitengruppe löschen?"
      :message="`Die Einheitengruppe '${deleteTarget?.name_de || ''}' und alle zugehörigen Einheiten werden gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <!-- Confirm delete unit -->
    <PimConfirmDialog
      :open="!!deleteUnitTarget"
      title="Einheit löschen?"
      :message="`Die Einheit '${deleteUnitTarget?.technical_name || ''}' wird gelöscht.`"
      :loading="deletingUnit"
      @confirm="confirmDeleteUnit"
      @cancel="deleteUnitTarget = null"
    />
  </div>
</template>
