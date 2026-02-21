<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const { t } = useI18n()
const store = useAssetCatalogStore()

const showPagination = computed(() => store.meta.last_page > 1)

const from = computed(() => {
  if (store.meta.total === 0) return 0
  return (store.meta.current_page - 1) * store.meta.per_page + 1
})

const to = computed(() => {
  return Math.min(store.meta.current_page * store.meta.per_page, store.meta.total)
})

function goToPage(page) {
  if (page < 1 || page > store.meta.last_page) return
  store.setPage(page)
  store.fetchAssets()
}
</script>

<template>
  <div v-if="showPagination || store.meta.total > 0" class="flex items-center justify-between mt-6 text-sm">
    <p class="text-base-content/50">
      {{ t('assetCatalog.showing') }} {{ from }}–{{ to }}
      {{ t('assetCatalog.of') }} {{ store.meta.total }}
      {{ t('assetCatalog.results') }}
    </p>

    <div v-if="showPagination" class="join">
      <button
        class="join-item btn btn-sm"
        :disabled="store.meta.current_page <= 1"
        @click="goToPage(store.meta.current_page - 1)"
      >
        <ChevronLeft class="w-4 h-4" />
      </button>

      <template v-for="page in store.meta.last_page" :key="page">
        <button
          v-if="page === 1 || page === store.meta.last_page || Math.abs(page - store.meta.current_page) <= 1"
          class="join-item btn btn-sm"
          :class="page === store.meta.current_page ? 'btn-active' : ''"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
        <button
          v-else-if="page === 2 && store.meta.current_page > 3"
          class="join-item btn btn-sm btn-disabled"
        >
          ...
        </button>
        <button
          v-else-if="page === store.meta.last_page - 1 && store.meta.current_page < store.meta.last_page - 2"
          class="join-item btn btn-sm btn-disabled"
        >
          ...
        </button>
      </template>

      <button
        class="join-item btn btn-sm"
        :disabled="store.meta.current_page >= store.meta.last_page"
        @click="goToPage(store.meta.current_page + 1)"
      >
        <ChevronRight class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
