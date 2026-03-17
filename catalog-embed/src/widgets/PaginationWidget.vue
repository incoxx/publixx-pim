<script setup>
import { computed } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()

const pages = computed(() => {
  const { current_page, last_page } = state.meta
  if (last_page <= 1) return []
  const items = []
  const max = 5
  let start = Math.max(1, current_page - Math.floor(max / 2))
  let end = Math.min(last_page, start + max - 1)
  start = Math.max(1, end - max + 1)
  if (start > 1) { items.push(1); if (start > 2) items.push('...') }
  for (let i = start; i <= end; i++) items.push(i)
  if (end < last_page) { if (end < last_page - 1) items.push('...'); items.push(last_page) }
  return items
})

const showing = computed(() => {
  const { current_page, per_page, total } = state.meta
  return {
    from: (current_page - 1) * per_page + 1,
    to: Math.min(current_page * per_page, total),
  }
})

function goTo(page) {
  if (typeof page !== 'number') return
  actions.setPage(page)
  actions.fetchProducts()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div v-if="state.meta.last_page > 1" class="pxc-pagination">
    <p class="pxc-pagination__info">
      {{ showing.from }}–{{ showing.to }} von {{ state.meta.total }}
    </p>
    <div class="pxc-pagination__buttons">
      <button :disabled="state.meta.current_page <= 1" @click="goTo(state.meta.current_page - 1)">
        <span v-html="icons.chevronLeft"></span>
      </button>
      <template v-for="(page, idx) in pages" :key="idx">
        <button v-if="page === '...'" disabled class="pxc-pagination__dots">...</button>
        <button
          v-else
          :class="{ 'pxc-pagination__active': page === state.meta.current_page }"
          @click="goTo(page)"
        >{{ page }}</button>
      </template>
      <button :disabled="state.meta.current_page >= state.meta.last_page" @click="goTo(state.meta.current_page + 1)">
        <span v-html="icons.chevronRight"></span>
      </button>
    </div>
  </div>
</template>
