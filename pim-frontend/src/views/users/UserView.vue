<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { Plus, Shield } from 'lucide-vue-next'
import usersApi, { roles } from '@/api/users'
import { useAuthStore } from '@/stores/auth'
import PimTable from '@/components/shared/PimTable.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import UserFormPanel from '@/components/panels/UserFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const rolesList = ref([])
const loading = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'email', label: 'E-Mail', sortable: true, mono: true },
  { key: 'roles', label: 'Rolle' },
  { key: 'created_at', label: 'Erstellt', sortable: true },
]

async function fetchUsers() {
  loading.value = true
  try { const { data } = await usersApi.list(); items.value = data.data || data }
  finally { loading.value = false }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(UserFormPanel), {
    user: null,
    rolesList: rolesList.value,
    onSaved: () => fetchUsers(),
  })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(UserFormPanel), {
    user: row,
    rolesList: rolesList.value,
    onSaved: () => fetchUsers(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await usersApi.delete(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchUsers()
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  fetchUsers()
  try {
    const { data } = await roles.list()
    rolesList.value = data.data || data
  } catch { /* roles might not load if no permission */ }
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Benutzer</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neuer Benutzer
      </button>
    </div>
    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      emptyText="Keine Benutzer"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    >
      <template #cell-roles="{ row }">
        <span class="pim-badge bg-[var(--color-info-light)] text-[var(--color-info)]">
          <Shield class="w-3 h-3" :stroke-width="2" /> {{ row.roles?.[0]?.name || 'Keine' }}
        </span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ value ? new Date(value).toLocaleDateString('de-DE') : '' }}</span>
      </template>
    </PimTable>

    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Benutzer löschen?"
      :message="`Der Benutzer '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
