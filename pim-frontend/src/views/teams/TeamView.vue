<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import teamsApi from '@/api/teams'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import TeamFormPanel from '@/components/panels/TeamFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'description', label: 'Beschreibung' },
  { key: 'users_count', label: 'Mitglieder' },
]

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await teamsApi.list()
    items.value = data.data || data
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(TeamFormPanel), {
    team: null,
    onSaved: () => fetchItems(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('teams.edit')) return
  authStore.openPanel(markRaw(TeamFormPanel), {
    team: row,
    onSaved: () => fetchItems(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await teamsApi.delete(deleteTarget.value.id, { force })
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
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Teams</h2>
      <button v-if="authStore.hasPermission('teams.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neues Team
      </button>
    </div>

    <PimFilterBar
      :search="search"
      placeholder="Teams durchsuchen…"
      @update:search="v => { search = v; fetchItems() }"
    />

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="authStore.hasPermission('teams.delete')"
      emptyText="Keine Teams gefunden"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-description="{ value }">
        <span v-if="value" class="text-xs text-[var(--color-text-secondary)] truncate block max-w-[300px]">{{ value }}</span>
        <span v-else class="text-[var(--color-text-tertiary)]">—</span>
      </template>
      <template #cell-users_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Team löschen?"
      :message="`Das Team '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      entityType="teams"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
