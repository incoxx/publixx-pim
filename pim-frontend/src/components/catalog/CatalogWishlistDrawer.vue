<script setup>
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { Heart, Trash2, X, Package } from 'lucide-vue-next'

const { t } = useI18n()
const store = useCatalogStore()
const wishlistOpen = inject('wishlistOpen')

// Find product data from loaded products
const wishlistProducts = computed(() => {
  return store.products.filter((p) => store.isInWishlist(p.id))
})

// IDs that are in wishlist but not loaded — show just the IDs
const unloadedIds = computed(() => {
  const loadedIds = new Set(store.products.map((p) => p.id))
  return store.wishlistIds.filter((id) => !loadedIds.has(id))
})

function closeDrawer() {
  wishlistOpen.value = false
}

function formatPrice(price) {
  if (!price) return null
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: 'EUR',
  }).format(price)
}
</script>

<template>
  <div class="bg-base-100 w-80 min-h-full border-l border-base-300 flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-base-300 flex items-center justify-between">
      <h2 class="font-semibold text-sm flex items-center gap-2">
        <Heart class="w-4 h-4 text-error" />
        {{ t('catalog.wishlist') }}
        <span v-if="store.wishlistCount > 0" class="badge badge-sm badge-ghost">
          {{ store.wishlistCount }}
        </span>
      </h2>
      <button class="btn btn-ghost btn-sm btn-circle" @click="closeDrawer">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Items -->
    <div class="flex-1 overflow-y-auto">
      <!-- Empty state -->
      <div
        v-if="store.wishlistCount === 0"
        class="flex flex-col items-center justify-center py-16 px-6 text-center"
      >
        <Heart class="w-10 h-10 text-base-content/15 mb-4" />
        <p class="text-sm font-medium text-base-content/50 mb-1">
          {{ t('catalog.wishlistEmpty') }}
        </p>
        <p class="text-xs text-base-content/30">
          {{ t('catalog.wishlistEmptyHint') }}
        </p>
      </div>

      <!-- Products -->
      <div v-else class="p-2 space-y-2">
        <div
          v-for="product in wishlistProducts"
          :key="product.id"
          class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200 transition-colors"
        >
          <div class="w-12 h-12 flex-none rounded-lg bg-base-200 overflow-hidden">
            <img
              v-if="product.image_url"
              :src="product.image_url"
              :alt="product.name"
              class="object-contain w-full h-full p-1"
            />
            <div v-else class="flex items-center justify-center w-full h-full">
              <Package class="w-5 h-5 text-base-content/15" />
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-medium line-clamp-1">{{ product.name }}</p>
            <p class="text-[10px] text-base-content/50 font-mono">{{ product.sku }}</p>
            <p v-if="product.price" class="text-xs font-bold text-primary">
              {{ formatPrice(product.price) }}
            </p>
          </div>
          <button
            class="btn btn-ghost btn-xs btn-circle flex-none text-base-content/30 hover:text-error"
            @click="store.toggleWishlist(product.id)"
          >
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Unloaded items (just show count) -->
        <div
          v-if="unloadedIds.length > 0"
          class="text-center text-xs text-base-content/40 py-2"
        >
          + {{ unloadedIds.length }} weitere Produkte
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div v-if="store.wishlistCount > 0" class="p-3 border-t border-base-300">
      <button
        class="btn btn-outline btn-error btn-sm w-full gap-1"
        @click="store.clearWishlist()"
      >
        <Trash2 class="w-3.5 h-3.5" />
        {{ t('catalog.clearWishlist') }}
      </button>
    </div>
  </div>
</template>
