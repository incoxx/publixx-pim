<script setup>
import { ref } from 'vue'
import { Search } from 'lucide-vue-next'
import { usePql } from '@/composables/usePql'
import PimTable from '@/components/shared/PimTable.vue'

const pql = usePql()
const searchInput = ref('')
const columns = [
  { key: 'sku', label: 'SKU', mono: true },
  { key: 'name_de', label: 'Name' },
  { key: 'status', label: 'Status' },
]
async function doSearch() {
  if (!searchInput.value.trim()) return
  await pql.execute(`WHERE name CONTAINS "${searchInput.value}" OR sku CONTAINS "${searchInput.value}"`)
}
</script>

<template>
  <div class="space-y-4 max-w-3xl mx-auto">
    <div class="relative">
      <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <input v-model="searchInput" type="text" placeholder="Produkte, Attribute, SKUs durchsuchen..." class="pim-input pl-12 pr-4 py-3 text-base" @keydown.enter="doSearch" autofocus />
    </div>
    <PimTable v-if="pql.results.value.length > 0" :columns="columns" :rows="pql.results.value" :loading="pql.loading.value" />
    <div v-else-if="!pql.loading.value" class="text-center py-16">
      <Search class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Suchbegriff eingeben und Enter druecken</p>
    </div>
  </div>
</template>
