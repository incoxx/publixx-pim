<script setup>
import { inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { Image, Heart, Menu, Globe } from 'lucide-vue-next'

const { t } = useI18n()
const store = useAssetCatalogStore()
const wishlistOpen = inject('wishlistOpen', ref(false))
const sidebarOpen = inject('sidebarOpen', ref(false))

function toggleLocale() {
  store.setLocale(store.locale === 'de' ? 'en' : 'de')
  store.fetchAssets()
  store.fetchFolders()
}
</script>

<template>
  <header class="bg-base-100 border-b border-base-300 px-4 py-3">
    <div class="flex items-center justify-between gap-4">
      <!-- Left: Mobile menu + Logo -->
      <div class="flex items-center gap-3">
        <button class="btn btn-ghost btn-sm btn-square lg:hidden" @click="sidebarOpen = true">
          <Menu class="w-5 h-5" />
        </button>
        <div class="flex items-center gap-2">
          <Image class="w-6 h-6 text-primary" />
          <span class="font-bold text-lg hidden sm:inline">{{ t('assetCatalog.title') }}</span>
        </div>
      </div>

      <!-- Right: Actions -->
      <div class="flex items-center gap-2">
        <!-- Locale toggle -->
        <button class="btn btn-ghost btn-sm gap-1" @click="toggleLocale">
          <Globe class="w-4 h-4" />
          <span class="text-xs uppercase">{{ store.locale }}</span>
        </button>

        <!-- Wishlist -->
        <button
          class="btn btn-ghost btn-sm gap-1 relative"
          @click="wishlistOpen = !wishlistOpen"
        >
          <Heart class="w-4 h-4" :class="store.wishlistCount > 0 ? 'fill-error text-error' : ''" />
          <span v-if="store.wishlistCount > 0" class="badge badge-xs badge-error absolute -top-1 -right-1">
            {{ store.wishlistCount }}
          </span>
        </button>
      </div>
    </div>
  </header>
</template>
