<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import workflowsApi from '@/api/workflows'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import WorkflowFormPanel from '@/components/panels/WorkflowFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'description', label: 'Beschreibung' },
  { key: 'is_active', label: 'Aktiv', sortable: true },
  { key: 'statuses_count', label: 'Status' },
]

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await workflowsApi.list({ include: 'statuses,startStatus,endStatus' })
    items.value = (data.data || data).map(w => ({
      ...w,
      statuses_count: w.statuses?.length ?? 0,
    }))
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(WorkflowFormPanel), {
    workflow: null,
    onSaved: () => fetchItems(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('workflows.edit')) return
  authStore.openPanel(markRaw(WorkflowFormPanel), {
    workflow: row,
    onSaved: () => fetchItems(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await workflowsApi.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchItems()
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchItems())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Workflows</h2>
      <button v-if="authStore.hasPermission('workflows.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neuer Workflow
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Workflows durchsuchen…"
      @update:search="v => { search = v; fetchItems() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="authStore.hasPermission('workflows.delete')"
      emptyText="Keine Workflows gefunden"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-is_active="{ value }">
        <span :class="value ? 'text-green-600' : 'text-[var(--color-text-tertiary)]'">{{ value ? 'Ja' : 'Nein' }}</span>
      </template>
      <template #cell-description="{ value }">
        <span v-if="value" class="text-xs text-[var(--color-text-secondary)] truncate block max-w-[300px]">{{ value }}</span>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Workflow löschen?"
      :message="`Der Workflow '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      entityType="workflows"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
