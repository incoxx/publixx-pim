<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Search, AlertCircle } from 'lucide-vue-next'
import { usePql } from '@/composables/usePql'
import PimTable from '@/components/shared/PimTable.vue'

const router = useRouter()
const pql = usePql()
const searchInput = ref('')
const hasSearched = ref(false)
const columns = [
  { key: 'sku', label: 'SKU', mono: true },
  { key: 'name_de', label: 'Name' },
  { key: 'status', label: 'Status' },
]

async function doSearch() {
  if (!searchInput.value.trim()) return
  hasSearched.value = true
  const term = searchInput.value.replace(/"/g, '\\"')
  await pql.execute(`WHERE name LIKE "%${term}%" OR sku LIKE "%${term}%"`)
}

function openProduct(row) {
  router.push(`/products/${row.id}`)
}
</script>

<template>
  <div class="space-y-4 max-w-3xl mx-auto">
    <div class="relative">
      <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <input v-model="searchInput" type="text" placeholder="Produkte, Attribute, SKUs durchsuchen..." class="pim-input pl-12 pr-4 py-3 text-base" @keydown.enter="doSearch" autofocus />
    </div>

    <!-- Error display -->
    <div v-if="pql.error.value" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <AlertCircle class="w-4 h-4 shrink-0" :stroke-width="2" />
      <p class="text-xs">{{ pql.error.value }}</p>
    </div>

    <!-- Result count -->
    <div v-if="hasSearched && !pql.loading.value && !pql.error.value && pql.results.value.length > 0" class="text-xs text-[var(--color-text-tertiary)]">
      {{ pql.count.value }} Ergebnis{{ pql.count.value !== 1 ? 'se' : '' }}
    </div>

    <PimTable
      v-if="pql.results.value.length > 0"
      :columns="columns"
      :rows="pql.results.value"
      :loading="pql.loading.value"
      @row-click="openProduct"
    >
      <template #cell-status="{ value }">
        <span
          :class="[
            'pim-badge',
            value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' :
            value === 'draft' ? 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' :
            'bg-[var(--color-error-light)] text-[var(--color-error)]'
          ]"
        >
          {{ value === 'active' ? 'Aktiv' : value === 'draft' ? 'Entwurf' : 'Inaktiv' }}
        </span>
      </template>
    </PimTable>

    <div v-if="pql.loading.value" class="pim-card p-6">
      <div class="space-y-3">
        <div v-for="i in 5" :key="i" class="pim-skeleton h-8 rounded" />
      </div>
    </div>

    <div v-else-if="hasSearched && pql.results.value.length === 0 && !pql.error.value" class="text-center py-12">
      <Search class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Ergebnisse gefunden</p>
    </div>

    <div v-else-if="!hasSearched" class="text-center py-16">
      <Search class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Suchbegriff eingeben und Enter drücken</p>
    </div>
  </div>
</template>
