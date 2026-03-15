<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import projectsApi from '@/api/projects'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import ProjectFormPanel from '@/components/panels/ProjectFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const columns = [
  { key: 'name', label: 'Projekt', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'start_date', label: 'Start', sortable: true },
  { key: 'end_date', label: 'Ende', sortable: true },
  { key: 'teams_count', label: 'Teams' },
  { key: 'products_count', label: 'Produkte' },
]

const statusLabels = {
  planning: 'Planung',
  active: 'Aktiv',
  completed: 'Abgeschlossen',
  on_hold: 'Pausiert',
}

const statusColors = {
  planning: 'text-blue-600 bg-blue-50',
  active: 'text-green-600 bg-green-50',
  completed: 'text-gray-600 bg-gray-100',
  on_hold: 'text-amber-600 bg-amber-50',
}

async function fetchItems() {
  loading.value = true
  try {
    const options = {}
    if (statusFilter.value) {
      options.filter = { status: statusFilter.value }
    }
    const { data } = await projectsApi.list(options)
    items.value = data.data || data
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ProjectFormPanel), {
    project: null,
    onSaved: () => fetchItems(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('projects.edit')) return
  authStore.openPanel(markRaw(ProjectFormPanel), {
    project: row,
    onSaved: () => fetchItems(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await projectsApi.delete(deleteTarget.value.id, { force })
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
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Projekte</h2>
      <button v-if="authStore.hasPermission('projects.create')" class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" />
        Neues Projekt
      </button>
    </div>

    <div class="flex items-center gap-3">
      <PimFilterBar
        :search="search"
        placeholder="Projekte durchsuchen…"
        class="flex-1"
        @update:search="v => { search = v; fetchItems() }"
      />
      <select v-model="statusFilter" class="pim-input text-xs w-40" @change="fetchItems">
        <option value="">Alle Status</option>
        <option value="planning">Planung</option>
        <option value="active">Aktiv</option>
        <option value="completed">Abgeschlossen</option>
        <option value="on_hold">Pausiert</option>
      </select>
    </div>

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="authStore.hasPermission('projects.delete')"
      emptyText="Keine Projekte gefunden"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-status="{ value }">
        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColors[value] || '']">
          {{ statusLabels[value] || value }}
        </span>
      </template>
      <template #cell-teams_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>
      <template #cell-products_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">{{ value ?? 0 }}</span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Projekt löschen?"
      :message="`Das Projekt '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      entityType="projects"
      :entityId="deleteTarget?.id"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
