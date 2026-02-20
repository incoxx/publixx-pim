<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()

const pages = computed(() => {
  const { current_page, last_page } = store.meta
  if (last_page <= 1) return []

  const items = []
  const maxVisible = 5
  let start = Math.max(1, current_page - Math.floor(maxVisible / 2))
  let end = Math.min(last_page, start + maxVisible - 1)
  start = Math.max(1, end - maxVisible + 1)

  if (start > 1) {
    items.push(1)
    if (start > 2) items.push('...')
  }

  for (let i = start; i <= end; i++) {
    items.push(i)
  }

  if (end < last_page) {
    if (end < last_page - 1) items.push('...')
    items.push(last_page)
  }

  return items
})

const showing = computed(() => {
  const { current_page, per_page, total } = store.meta
  const from = (current_page - 1) * per_page + 1
  const to = Math.min(current_page * per_page, total)
  return { from, to }
})

function goToPage(page) {
  if (typeof page !== 'number') return
  store.setPage(page)
  store.fetchProducts()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div
    v-if="store.meta.last_page > 1"
    class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-8"
  >
    <!-- Showing info -->
    <p class="text-sm text-base-content/50">
      {{ t('catalog.showing') }}
      <span class="font-medium text-base-content">{{ showing.from }}–{{ showing.to }}</span>
      {{ t('catalog.of') }}
      <span class="font-medium text-base-content">{{ store.meta.total }}</span>
    </p>

    <!-- Page buttons -->
    <div class="join">
      <button
        class="join-item btn btn-sm"
        :disabled="store.meta.current_page <= 1"
        @click="goToPage(store.meta.current_page - 1)"
      >
        <ChevronLeft class="w-4 h-4" />
      </button>

      <template v-for="(page, idx) in pages" :key="idx">
        <button
          v-if="page === '...'"
          class="join-item btn btn-sm btn-disabled"
        >
          ...
        </button>
        <button
          v-else
          class="join-item btn btn-sm"
          :class="page === store.meta.current_page ? 'btn-primary' : ''"
          @click="goToPage(page)"
        >
          {{ page }}
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
