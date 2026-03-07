<script setup>
import { computed } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const store = useAttributeStore()

const emit = defineEmits(['page-change'])

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
  emit('page-change', page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div
    v-if="store.meta.last_page > 1"
    class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-[var(--color-border)]"
  >
    <p class="text-xs text-[var(--color-text-tertiary)]">
      <span class="font-medium text-[var(--color-text-secondary)]">{{ showing.from }}–{{ showing.to }}</span>
      von
      <span class="font-medium text-[var(--color-text-secondary)]">{{ store.meta.total }}</span>
      Attributen
    </p>

    <div class="flex items-center gap-1">
      <button
        class="pim-btn pim-btn-ghost text-xs p-1.5"
        :disabled="store.meta.current_page <= 1"
        @click="goToPage(store.meta.current_page - 1)"
      >
        <ChevronLeft class="w-3.5 h-3.5" />
      </button>

      <template v-for="(page, idx) in pages" :key="idx">
        <span v-if="page === '...'" class="px-1.5 text-xs text-[var(--color-text-tertiary)]">...</span>
        <button
          v-else
          class="min-w-[28px] h-7 text-xs rounded transition-colors"
          :class="page === store.meta.current_page
            ? 'bg-[var(--color-accent)] text-white font-medium'
            : 'hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]'"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
      </template>

      <button
        class="pim-btn pim-btn-ghost text-xs p-1.5"
        :disabled="store.meta.current_page >= store.meta.last_page"
        @click="goToPage(store.meta.current_page + 1)"
      >
        <ChevronRight class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
