<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { Plus, Shield, Pencil } from 'lucide-vue-next'
import { roles } from '@/api/users'
import { useAuthStore } from '@/stores/auth'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import RoleFormPanel from '@/components/panels/RoleFormPanel.vue'
import RoleBulkPermissionDialog from '@/components/roles/RoleBulkPermissionDialog.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const selectedIds = ref([])
const bulkDialogOpen = ref(false)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'permissions_count', label: 'Berechtigungen' },
  { key: 'users_count', label: 'Benutzer' },
  { key: 'created_at', label: 'Erstellt', sortable: true },
]

async function fetchRoles() {
  loading.value = true
  try {
    const { data } = await roles.list({ include: 'permissions' })
    items.value = (data.data || data).map(r => ({
      ...r,
      permissions_count: r.permissions?.length ?? 0,
    }))
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(RoleFormPanel), {
    role: null,
    onSaved: () => fetchRoles(),
  })
}

function openEditPanel(row) {
  if (!authStore.hasPermission('roles.edit')) return
  authStore.openPanel(markRaw(RoleFormPanel), {
    role: row,
    onSaved: () => fetchRoles(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await roles.delete(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchRoles()
  } catch (e) {
    if (e.response?.status === 409) {
      alert(e.response.data?.detail || 'Rolle wird noch verwendet.')
    }
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

function openBulkPermissions() {
  bulkDialogOpen.value = true
}

function onBulkUpdated() {
  bulkDialogOpen.value = false
  selectedIds.value = []
  fetchRoles()
}

onMounted(fetchRoles)
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Rollen</h2>
      <div class="flex items-center gap-2">
        <button
          v-if="selectedIds.length > 0 && authStore.hasPermission('roles.edit')"
          class="pim-btn pim-btn-secondary"
          @click="openBulkPermissions"
        >
          <Pencil class="w-4 h-4" :stroke-width="2" /> Berechtigungen bearbeiten ({{ selectedIds.length }})
        </button>
        <button
          v-if="authStore.hasPermission('roles.create')"
          class="pim-btn pim-btn-primary"
          @click="openCreatePanel"
        >
          <Plus class="w-4 h-4" :stroke-width="2" /> Neue Rolle
        </button>
      </div>
    </div>

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="authStore.hasPermission('roles.delete')"
      :selectable="authStore.hasPermission('roles.edit')"
      v-model:selectedIds="selectedIds"
      emptyText="Keine Rollen"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-name="{ row }">
        <span class="flex items-center gap-2">
          <Shield class="w-3.5 h-3.5 text-[var(--color-accent)]" :stroke-width="2" />
          <span class="font-medium">{{ row.name }}</span>
        </span>
      </template>
      <template #cell-permissions_count="{ value }">
        <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-secondary)]">
          {{ value }} Berechtigungen
        </span>
      </template>
      <template #cell-users_count="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ value ?? 0 }} Benutzer</span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ value ? new Date(value).toLocaleDateString('de-DE') : '' }}</span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Rolle löschen?"
      :message="`Die Rolle '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht. Rollen mit zugewiesenen Benutzern können nicht gelöscht werden.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />

    <RoleBulkPermissionDialog
      :open="bulkDialogOpen"
      :selectedRoleIds="selectedIds"
      @close="bulkDialogOpen = false"
      @updated="onBulkUpdated"
    />
  </div>
</template>
