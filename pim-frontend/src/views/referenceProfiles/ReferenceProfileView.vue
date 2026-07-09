<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { referenceProfiles } from '@/api/referenceProfiles'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import ReferenceProfileFormPanel from '@/components/panels/ReferenceProfileFormPanel.vue'

const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)

const canCreate = () => authStore.hasPermission('reference-profiles.create')
const canEdit = () => authStore.hasPermission('reference-profiles.edit')
const canDelete = () => authStore.hasPermission('reference-profiles.delete')

async function fetchProfiles() {
  loading.value = true
  try {
    const { data } = await referenceProfiles.list()
    items.value = data.data || data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'rule_count', label: 'Regeln' },
  { key: 'products_count', label: 'Produkte' },
  { key: 'is_abstract', label: 'Abstrakt' },
  { key: 'is_active', label: 'Aktiv' },
]

const deleteTarget = ref(null)
const deleting = ref(false)

// Backend liefert die komplette, unpaginierte Liste (kleine Referenzdatenmenge) —
// Sortierung läuft daher clientseitig über die bereits vollständig geladenen items.
const sortField = ref('name')
const sortOrder = ref('asc')

const sortedItems = computed(() => {
  const list = [...items.value]
  list.sort((a, b) => {
    const av = a[sortField.value]
    const bv = b[sortField.value]
    if (av == null && bv == null) return 0
    if (av == null) return -1
    if (bv == null) return 1
    const cmp = String(av).localeCompare(String(bv), 'de', { numeric: true, sensitivity: 'base' })
    return sortOrder.value === 'asc' ? cmp : -cmp
  })
  return list
})

function handleSort(field, order) {
  sortField.value = field
  sortOrder.value = order
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ReferenceProfileFormPanel), { profile: null, onSaved: fetchProfiles }, '640px')
}

function openEditPanel(row) {
  if (!canEdit()) return
  authStore.openPanel(markRaw(ReferenceProfileFormPanel), { profile: row, onSaved: fetchProfiles }, '640px')
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await referenceProfiles.delete(deleteTarget.value.id)
    items.value = items.value.filter(p => p.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    // 409 = Profil noch in Verwendung
    alert(e.response?.data?.message || 'Löschen fehlgeschlagen.')
  } finally {
    deleting.value = false
  }
}

onMounted(fetchProfiles)
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Referenz-Profile</h2>
        <p class="text-xs text-[var(--color-text-tertiary)]">
          Virtuelle Referenzprodukte: Sollbild + Prüfregeln für Produktgruppen.
        </p>
      </div>
      <button v-if="canCreate()" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neues Profil
      </button>
    </div>

    <PimTable
      :columns="columns"
      :rows="sortedItems"
      :loading="loading"
      :sortField="sortField"
      :sortOrder="sortOrder"
      :showActions="canDelete()"
      emptyText="Noch keine Referenz-Profile angelegt"
      @sort="handleSort"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-rule_count="{ row }">
        {{ (row.rules || []).length }}
      </template>
      <template #cell-is_abstract="{ value }">
        <span :class="value ? 'text-[var(--color-warning)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
      <template #cell-is_active="{ value }">
        <span :class="value ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'">
          {{ value ? 'Ja' : 'Nein' }}
        </span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Referenz-Profil löschen?"
      :message="`Das Profil '${deleteTarget?.name || ''}' wird gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
