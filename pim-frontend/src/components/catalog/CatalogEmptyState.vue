<script setup>
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { SearchX } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()

function clearFilters() {
  store.setSearch('')
  store.clearCategory()
  store.fetchProducts()
}
</script>

<template>
  <div class="flex flex-col items-center justify-center py-20 text-center">
    <div class="bg-base-300/50 rounded-full p-6 mb-6">
      <SearchX class="w-12 h-12 text-base-content/25" />
    </div>
    <h3 class="text-lg font-semibold text-base-content/70 mb-2">
      {{ t('catalog.noResults') }}
    </h3>
    <p class="text-sm text-base-content/40 max-w-sm mb-6">
      {{ t('catalog.noResultsHint') }}
    </p>
    <button
      v-if="store.search || store.selectedCategoryId"
      class="btn btn-primary btn-sm"
      @click="clearFilters"
    >
      {{ t('catalog.allCategories') }}
    </button>
  </div>
</template>
