<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { valueLists } from '@/api/attributes'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import ValueListFormPanel from '@/components/panels/ValueListFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const search = ref('')
const deleteTarget = ref(null)
const deleting = ref(false)

const columns = [
  { key: 'technical_name', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name', sortable: true },
  { key: 'value_data_type', label: 'Datentyp' },
  { key: 'entries_count', label: 'Einträge', align: 'right' },
]

async function fetchLists() {
  loading.value = true
  try {
    const { data } = await valueLists.list({ include: 'entries', search: search.value || undefined })
    items.value = (data.data || data).map(item => ({
      ...item,
      entries_count: item.entries?.length ?? item.entries_count ?? 0,
    }))
  } finally { loading.value = false }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(ValueListFormPanel), {
    valueList: null,
    onSaved: () => fetchLists(),
  })
}

function openEditPanel(row) {
  authStore.openPanel(markRaw(ValueListFormPanel), {
    valueList: row,
    onSaved: () => fetchLists(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await valueLists.delete(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchLists()
  } finally { deleting.value = false }
}

onMounted(() => fetchLists())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Wertelisten</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel"><Plus class="w-4 h-4" :stroke-width="2" /> Neue Werteliste</button>
    </div>
    <PimFilterBar :search="search" placeholder="Wertelisten durchsuchen..." @update:search="v => { search = v; fetchLists() }" />
    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      emptyText="Keine Wertelisten"
      @row-click="openEditPanel"
      @row-action="handleRowAction"
    />
    <PimConfirmDialog
      :open="!!deleteTarget"
      title="Werteliste löschen?"
      :message="`Die Werteliste '${deleteTarget?.name_de || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
