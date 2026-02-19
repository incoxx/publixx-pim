<script setup>
import { ref, onMounted } from 'vue'
import { valueLists } from '@/api/attributes'
import { Plus } from 'lucide-vue-next'
import PimTable from '@/components/shared/PimTable.vue'
import PimFilterBar from '@/components/shared/PimFilterBar.vue'

const items = ref([])
const loading = ref(false)
const search = ref('')
const columns = [
  { key: 'code', label: 'Code', sortable: true, mono: true },
  { key: 'name_de', label: 'Name', sortable: true },
  { key: 'entries_count', label: 'Eintraege', align: 'right' },
]
async function fetchLists() {
  loading.value = true
  try {
    const { data } = await valueLists.list({ include: 'entries', search: search.value || undefined })
    items.value = data.data || data
  } finally { loading.value = false }
}
onMounted(() => fetchLists())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Wertelisten</h2>
      <button class="pim-btn pim-btn-primary"><Plus class="w-4 h-4" :stroke-width="2" /> Neue Werteliste</button>
    </div>
    <PimFilterBar :search="search" placeholder="Wertelisten durchsuchen..." @update:search="v => { search = v; fetchLists() }" />
    <PimTable :columns="columns" :rows="items" :loading="loading" emptyText="Keine Wertelisten" />
  </div>
</template>
