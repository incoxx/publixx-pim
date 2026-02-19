<script setup>
import { ref, onMounted } from 'vue'
import { Plus, Shield } from 'lucide-vue-next'
import usersApi from '@/api/users'
import PimTable from '@/components/shared/PimTable.vue'

const items = ref([])
const loading = ref(false)
const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'email', label: 'E-Mail', sortable: true, mono: true },
  { key: 'role.name', label: 'Rolle' },
  { key: 'created_at', label: 'Erstellt', sortable: true },
]
async function fetchUsers() {
  loading.value = true
  try { const { data } = await usersApi.list({ include: 'role' }); items.value = data.data || data }
  finally { loading.value = false }
}
onMounted(() => fetchUsers())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Benutzer</h2>
      <button class="pim-btn pim-btn-primary"><Plus class="w-4 h-4" :stroke-width="2" /> Neuer Benutzer</button>
    </div>
    <PimTable :columns="columns" :rows="items" :loading="loading" emptyText="Keine Benutzer">
      <template #cell-role.name="{ value }">
        <span class="pim-badge bg-[var(--color-info-light)] text-[var(--color-info)]"><Shield class="w-3 h-3" :stroke-width="2" /> {{ value || 'Keine' }}</span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ value ? new Date(value).toLocaleDateString('de-DE') : '' }}</span>
      </template>
    </PimTable>
  </div>
</template>
