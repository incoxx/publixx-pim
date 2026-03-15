<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import workflowStatusesApi from '@/api/workflowStatuses'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import WorkflowStatusFormPanel from '@/components/panels/WorkflowStatusFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const columns = [
  { key: 'color', label: 'Farbe', sortable: false },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'description', label: 'Beschreibung' },
  { key: 'sort_order', label: 'Reihenfolge', sortable: true },
]

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await workflowStatusesApi.list()
    items.value = data.data || data
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(WorkflowStatusFormPanel), {
    workflowStatus: null,
    onSaved: () => fetchItems(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('workflow-statuses.edit')) return
  authStore.openPanel(markRaw(WorkflowStatusFormPanel), {
    workflowStatus: row,
    onSaved: () => fetchItems(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await workflowStatusesApi.delete(deleteTarget.value.id, { force })
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
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Workflow-Status</h2>
      <button v-if="authStore.hasPermission('workflow-statuses.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neuer Status
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Workflow-Status durchsuchen…"
      @update:search="v => { search = v; fetchItems() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="authStore.hasPermission('workflow-statuses.delete')"
      emptyText="Keine Workflow-Status gefunden"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-color="{ value }">
        <span class="inline-block w-5 h-5 rounded-full border border-[var(--color-border)]" :style="{ backgroundColor: value }"></span>
      </template>
      <template #cell-description="{ value }">
        <span v-if="value" class="text-xs text-[var(--color-text-secondary)] truncate block max-w-[300px]">{{ value }}</span>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Workflow-Status löschen?"
      :message="`Der Status '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      entityType="workflow-statuses"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
