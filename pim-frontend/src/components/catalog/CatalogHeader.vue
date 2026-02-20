<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { Search, Heart, Menu, X } from 'lucide-vue-next'
import CatalogLocaleSwitch from './CatalogLocaleSwitch.vue'

const { t } = useI18n()
const store = useCatalogStore()
const searchInput = ref(store.search)
let debounceTimer = null

function onSearchInput(value) {
  searchInput.value = value
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    store.setSearch(value)
    store.fetchProducts()
  }, 300)
}

function onSearchClear() {
  searchInput.value = ''
  store.setSearch('')
  store.fetchProducts()
}

function openWishlist() {
  document.getElementById('catalog-wishlist-drawer').checked = true
}
</script>

<template>
  <div class="navbar bg-base-100 shadow-sm border-b border-base-300 sticky top-0 z-30 px-4 gap-2">
    <!-- Mobile hamburger -->
    <div class="flex-none lg:hidden">
      <label for="catalog-sidebar-drawer" class="btn btn-square btn-ghost btn-sm">
        <Menu class="w-5 h-5" />
      </label>
    </div>

    <!-- Logo -->
    <div class="flex-none">
      <a href="/preview" class="btn btn-ghost text-lg font-bold text-primary normal-case gap-1 px-2">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <span class="hidden sm:inline">Produktkatalog</span>
      </a>
    </div>

    <!-- Search -->
    <div class="flex-1 px-2 lg:px-8">
      <label class="input input-bordered input-sm w-full max-w-xl mx-auto flex items-center gap-2">
        <Search class="w-4 h-4 opacity-40" />
        <input
          type="text"
          class="grow bg-transparent text-sm"
          :placeholder="t('catalog.search')"
          :value="searchInput"
          @input="onSearchInput($event.target.value)"
          @keydown.escape="onSearchClear"
        />
        <button
          v-if="searchInput"
          class="btn btn-ghost btn-xs btn-circle"
          @click="onSearchClear"
        >
          <X class="w-3 h-3" />
        </button>
        <span v-if="store.loading" class="loading loading-spinner loading-xs text-primary"></span>
      </label>
    </div>

    <!-- Actions -->
    <div class="flex-none flex items-center gap-1">
      <CatalogLocaleSwitch />
      <button
        class="btn btn-ghost btn-sm btn-circle indicator"
        :title="t('catalog.wishlist')"
        @click="openWishlist"
      >
        <Heart class="w-5 h-5" />
        <span
          v-if="store.wishlistCount > 0"
          class="badge badge-sm badge-secondary indicator-item font-mono text-[10px] px-1"
        >
          {{ store.wishlistCount }}
        </span>
      </button>
    </div>
  </div>
</template>
