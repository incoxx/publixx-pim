<script setup>
import { ref, onMounted, markRaw, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { comparisonOperatorGroups, comparisonOperators } from '@/api/comparisonOperators'
import { Plus, ChevronLeft, Trash2, Check } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import ComparisonOperatorGroupFormPanel from '@/components/panels/ComparisonOperatorGroupFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const sortField = ref('name_de')
const sortOrder = ref('asc')

// Detail state
const selectedGroup = ref(null)
const operatorList = ref([])
const operatorsLoading = ref(false)
const deleteOperatorTarget = ref(null)
const deletingOperator = ref(false)

// Inline add
const showAddRow = ref(false)
const newOperator = ref({ technical_name: '', symbol: '', description_de: '' })

// Error feedback
const operatorError = ref(null)

// Inline edit
const editingOperatorId = ref(null)
const editForm = ref({})

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name', sortable: true },
  { key: 'operators_count', label: 'Operatoren', align: 'right' },
]

async function fetchGroups() {
  loading.value = true
  try {
    const { data } = await comparisonOperatorGroups.list({
      include: 'operators',
      search: search.value || undefined,
      sort: sortField.value,
      order: sortOrder.value,
      perPage: meta.value.per_page,
      page: meta.value.current_page,
    })
    items.value = (data.data || data).map(item => ({
      ...item,
      operators_count: item.operators?.length ?? 0,
    }))
    if (data.meta) meta.value = data.meta
  } finally { loading.value = false }
}

function handleSort(field, order) {
  sortField.value = field
  sortOrder.value = order
  meta.value.current_page = 1
  fetchGroups()
}

function handlePageChange(page) {
  if (page < 1 || page > meta.value.last_page) return
  meta.value.current_page = page
  fetchGroups()
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ComparisonOperatorGroupFormPanel), {
    group: null,
    onSaved: () => fetchGroups(),
  })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(ComparisonOperatorGroupFormPanel), {
    group: row,
    onSaved: () => { fetchGroups(); if (selectedGroup.value?.id === row.id) fetchOperators() },
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force } = {}) {
  deleting.value = true
  try {
    await comparisonOperatorGroups.delete(deleteTarget.value.id, { force })
    if (selectedGroup.value?.id === deleteTarget.value.id) selectedGroup.value = null
    deleteTarget.value = null
    await fetchGroups()
  } finally { deleting.value = false }
}

function selectGroup(row) {
  selectedGroup.value = row
}

async function fetchOperators() {
  if (!selectedGroup.value) return
  operatorsLoading.value = true
  try {
    const { data } = await comparisonOperators.list(selectedGroup.value.id)
    operatorList.value = data.data || data
  } finally { operatorsLoading.value = false }
}

watch(selectedGroup, (v) => {
  if (v) {
    fetchOperators()
  } else {
    operatorList.value = []
  }
  editingOperatorId.value = null
  showAddRow.value = false
})

// Add operator
function openAddRow() {
  newOperator.value = { technical_name: '', symbol: '', description_de: '' }
  showAddRow.value = true
  editingOperatorId.value = null
}

async function saveNewOperator() {
  if (!newOperator.value.technical_name.trim() || !newOperator.value.symbol.trim()) return
  operatorError.value = null
  try {
    await comparisonOperators.create(selectedGroup.value.id, newOperator.value)
    showAddRow.value = false
    await fetchOperators()
    await fetchGroups()
  } catch (e) {
    const msg = e.response?.data?.message || e.response?.data?.errors?.technical_name?.[0]
    operatorError.value = msg || 'Operator konnte nicht erstellt werden'
  }
}

// Edit operator inline
function startEdit(op) {
  editingOperatorId.value = op.id
  editForm.value = {
    technical_name: op.technical_name,
    symbol: op.symbol || '',
    description_de: op.description_de || '',
  }
  showAddRow.value = false
}

function cancelEdit() {
  editingOperatorId.value = null
}

async function saveEdit() {
  operatorError.value = null
  try {
    await comparisonOperators.update(editingOperatorId.value, editForm.value)
    editingOperatorId.value = null
    await fetchOperators()
  } catch (e) {
    const msg = e.response?.data?.message || e.response?.data?.errors?.technical_name?.[0]
    operatorError.value = msg || 'Operator konnte nicht aktualisiert werden'
  }
}

// Delete operator
async function confirmDeleteOperator({ force } = {}) {
  deletingOperator.value = true
  try {
    await comparisonOperators.delete(deleteOperatorTarget.value.id, { force })
    deleteOperatorTarget.value = null
    await fetchOperators()
    await fetchGroups()
  } finally { deletingOperator.value = false }
}

onMounted(() => fetchGroups())
</script>

<template>
  <div class="flex gap-6 h-full">
    <!-- Left: Group list -->
    <div :class="['space-y-4 transition-all', selectedGroup ? 'w-1/3 flex-none' : 'flex-1']">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Vergleichsoperator-Gruppen</h2>
        <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
          <Plus class="w-4 h-4" :stroke-width="2" /> Neue Gruppe
        </button>
      </div>
      <PimFilterBar :search="search" placeholder="Gruppen durchsuchen..." @update:search="v => { search = v; meta.current_page = 1; fetchGroups() }" />
      <PimTable
        :columns="columns"
        :rows="items"
        :loading="loading"
        :sortField="sortField"
        :sortOrder="sortOrder"
        :activeRowId="selectedGroup?.id"
        showActions
        emptyText="Keine Vergleichsoperator-Gruppen"
        @sort="handleSort"
        @row-click="selectGroup"
        @row-action="handleRowAction"
      >
        <template #pagination>
          <div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
            <span class="text-xs text-[var(--color-text-tertiary)]">{{ meta.total }} Gruppen</span>
            <div class="flex items-center gap-1">
              <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page <= 1" @click="handlePageChange(meta.current_page - 1)">Zurück</button>
              <span class="text-xs text-[var(--color-text-secondary)] px-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
              <button class="pim-btn pim-btn-ghost text-xs" :disabled="meta.current_page >= meta.last_page" @click="handlePageChange(meta.current_page + 1)">Weiter</button>
            </div>
          </div>
        </template>
      </PimTable>
    </div>

    <!-- Right: Operator management -->
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
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Operator
          </button>
        </div>
      </div>

      <!-- Error -->
      <div v-if="operatorError" class="flex items-center justify-between gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
        <p class="text-xs">{{ operatorError }}</p>
        <button class="text-xs hover:underline" @click="operatorError = null">Schließen</button>
      </div>

      <!-- Operators table -->
      <div class="pim-card overflow-hidden">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px] uppercase tracking-wider">
              <th class="px-3 py-2 text-left">Technischer Name</th>
              <th class="px-3 py-2 text-center w-16">Symbol</th>
              <th class="px-3 py-2 text-left">Beschreibung</th>
              <th class="px-3 py-2 text-right w-28">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading -->
            <template v-if="operatorsLoading">
              <tr v-for="i in 3" :key="i">
                <td colspan="4" class="px-3 py-2"><div class="pim-skeleton h-4 rounded" /></td>
              </tr>
            </template>

            <template v-else>
              <tr
                v-for="op in operatorList"
                :key="op.id"
                class="border-t border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
              >
                <template v-if="editingOperatorId === op.id">
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.technical_name" class="pim-input text-xs w-full font-mono" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.symbol" class="pim-input text-xs w-full text-center" />
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="editForm.description_de" class="pim-input text-xs w-full" />
                  </td>
                  <td class="px-3 py-1.5 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <button class="pim-btn pim-btn-primary !text-xs !py-1 !px-2.5" @click="saveEdit">
                        <Check class="w-3 h-3" :stroke-width="2.5" /> Speichern
                      </button>
                      <button class="pim-btn pim-btn-ghost !text-xs !py-1 !px-2" @click="cancelEdit">
                        Abbrechen
                      </button>
                    </div>
                  </td>
                </template>

                <template v-else>
                  <td class="px-3 py-1.5 font-mono text-[var(--color-text-primary)]">{{ op.technical_name }}</td>
                  <td class="px-3 py-1.5 text-center text-base font-medium text-[var(--color-text-primary)]">{{ op.symbol }}</td>
                  <td class="px-3 py-1.5 text-[var(--color-text-secondary)]">{{ op.description_de || '—' }}</td>
                  <td class="px-3 py-1.5 text-right">
                    <div class="flex items-center justify-end gap-0.5">
                      <button class="p-1 rounded hover:bg-[var(--color-primary-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-primary)]" @click="startEdit(op)" title="Bearbeiten">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
                      </button>
                      <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="deleteOperatorTarget = op" title="Löschen">
                        <Trash2 class="w-3 h-3" :stroke-width="2" />
                      </button>
                    </div>
                  </td>
                </template>
              </tr>

              <!-- Add row -->
              <tr v-if="showAddRow" class="border-t-2 border-[var(--color-primary)] bg-[var(--color-primary)]/10">
                <td class="px-3 py-2">
                  <input v-model="newOperator.technical_name" class="pim-input text-xs w-full font-mono" placeholder="z.B. gte" @keyup.enter="saveNewOperator" @keyup.escape="showAddRow = false" />
                </td>
                <td class="px-3 py-2">
                  <input v-model="newOperator.symbol" class="pim-input text-xs w-full text-center" placeholder="z.B. ≥" @keyup.enter="saveNewOperator" @keyup.escape="showAddRow = false" />
                </td>
                <td class="px-3 py-2">
                  <input v-model="newOperator.description_de" class="pim-input text-xs w-full" placeholder="z.B. größer gleich" @keyup.enter="saveNewOperator" @keyup.escape="showAddRow = false" />
                </td>
                <td class="px-3 py-2 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button class="pim-btn pim-btn-primary !text-xs !py-1 !px-2.5" @click="saveNewOperator">
                      <Check class="w-3 h-3" :stroke-width="2.5" /> Speichern
                    </button>
                    <button class="pim-btn pim-btn-ghost !text-xs !py-1 !px-2" @click="showAddRow = false">
                      Abbrechen
                    </button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Empty -->
            <tr v-if="!operatorsLoading && operatorList.length === 0 && !showAddRow">
              <td colspan="4" class="px-3 py-8 text-center text-[var(--color-text-tertiary)] text-xs">
                Noch keine Operatoren. Klicken Sie auf "+ Operator" um den ersten Operator anzulegen.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm delete group -->
    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Vergleichsoperator-Gruppe löschen?"
      :message="`Die Gruppe '${deleteTarget?.name_de || ''}' und alle zugehörigen Operatoren werden gelöscht.`"
      :loading="deleting"
      entityType="comparison-operator-groups"
      :entityId="deleteTarget?.id"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <!-- Confirm delete operator -->
    <PimDeleteConfirmDialog
      :open="!!deleteOperatorTarget"
      title="Operator löschen?"
      :message="`Der Operator '${deleteOperatorTarget?.symbol || ''}' (${deleteOperatorTarget?.technical_name || ''}) wird gelöscht.`"
      :loading="deletingOperator"
      entityType="comparison-operators"
      :entityId="deleteOperatorTarget?.id"
      @confirm="confirmDeleteOperator"
      @cancel="deleteOperatorTarget = null"
    />
  </div>
</template>
