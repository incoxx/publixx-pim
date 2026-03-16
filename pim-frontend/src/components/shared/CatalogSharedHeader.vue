<script setup>
import { ref, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { Search, Heart, Menu, X } from 'lucide-vue-next'

const props = defineProps({
  themeSettings: { type: Object, default: () => ({}) },
  search: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  wishlistCount: { type: Number, default: 0 },
  homeUrl: { type: String, default: '/preview' },
  locale: { type: String, default: 'de' },
  searchPlaceholder: { type: String, default: '' },
})

const emit = defineEmits(['search', 'clear-search', 'switch-locale'])

const { t } = useI18n()
const wishlistOpen = inject('wishlistOpen')
const sidebarOpen = inject('sidebarOpen')

const searchInput = ref(props.search)
let debounceTimer = null

function onSearchInput(value) {
  searchInput.value = value
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    emit('search', value)
  }, 300)
}

function onSearchClear() {
  searchInput.value = ''
  emit('clear-search')
}
</script>

<template>
  <div
    class="navbar bg-base-100 shadow-sm border-b border-base-300 sticky top-0 z-30 px-4 gap-2"
    :style="{
      backgroundColor: themeSettings.color_header_bg || undefined,
      color: themeSettings.color_header_text || undefined,
    }"
  >
    <!-- Mobile hamburger -->
    <div class="flex-none lg:hidden">
      <button class="btn btn-square btn-ghost btn-sm" @click="sidebarOpen = true">
        <Menu class="w-5 h-5" />
      </button>
    </div>

    <!-- Logo -->
    <div class="flex-none">
      <a :href="homeUrl" class="btn btn-ghost text-lg font-bold text-primary normal-case gap-1 px-2">
        <img
          v-if="themeSettings.logo_url"
          :src="themeSettings.logo_url"
          alt="Logo"
          class="h-8 w-auto max-w-[160px] object-contain"
        />
        <svg v-else class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <span class="hidden sm:inline">{{ themeSettings.catalog_title || 'Produktkatalog' }}</span>
      </a>
    </div>

    <!-- Search -->
    <div class="flex-1 px-2 lg:px-8">
      <label class="input input-bordered input-sm w-full max-w-xl mx-auto flex items-center gap-2">
        <Search class="w-4 h-4 opacity-40" />
        <input
          type="text"
          class="grow bg-transparent text-sm"
          :placeholder="searchPlaceholder || t('catalog.search')"
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
        <span v-if="loading" class="loading loading-spinner loading-xs text-primary"></span>
      </label>
    </div>

    <!-- Actions -->
    <div class="flex-none flex items-center gap-1">
      <!-- Locale switch -->
      <div class="join">
        <button
          class="join-item btn btn-xs"
          :class="locale === 'de' ? 'btn-primary' : 'btn-ghost'"
          @click="emit('switch-locale', 'de')"
        >
          DE
        </button>
        <button
          class="join-item btn btn-xs"
          :class="locale === 'en' ? 'btn-primary' : 'btn-ghost'"
          @click="emit('switch-locale', 'en')"
        >
          EN
        </button>
      </div>
      <button
        class="btn btn-ghost btn-sm btn-circle indicator"
        :title="t('catalog.wishlist')"
        @click="wishlistOpen = true"
      >
        <Heart class="w-5 h-5" />
        <span
          v-if="wishlistCount > 0"
          class="badge badge-sm badge-secondary indicator-item font-mono text-[10px] px-1"
        >
          {{ wishlistCount }}
        </span>
      </button>
    </div>
  </div>
</template>
