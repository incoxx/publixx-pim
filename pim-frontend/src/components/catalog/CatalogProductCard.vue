<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { Heart, Eye, Package } from 'lucide-vue-next'

const props = defineProps({
  product: { type: Object, required: true },
  index: { type: Number, default: 0 },
})

const emit = defineEmits(['view-detail'])

const { t } = useI18n()
const store = useCatalogStore()

const inWishlist = computed(() => store.isInWishlist(props.product.id))

function toggleWishlist(e) {
  e.stopPropagation()
  store.toggleWishlist(props.product.id)
}

const formattedPrice = computed(() => {
  if (!props.product.price) return null
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: 'EUR',
  }).format(props.product.price)
})

const staggerDelay = computed(() => `${Math.min(props.index * 50, 400)}ms`)
</script>

<template>
  <div
    class="card bg-base-100 shadow-sm border border-base-300 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer group catalog-card-enter"
    :style="{ animationDelay: staggerDelay }"
    @click="emit('view-detail', product)"
  >
    <!-- Image -->
    <figure class="relative overflow-hidden aspect-[4/3] bg-base-200">
      <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.name"
        class="object-contain w-full h-full p-4 group-hover:scale-105 transition-transform duration-500"
        loading="lazy"
      />
      <div v-else class="flex items-center justify-center w-full h-full">
        <Package class="w-12 h-12 text-base-content/15" />
      </div>

      <!-- Wishlist button overlay -->
      <button
        class="btn btn-circle btn-sm absolute top-2 right-2 bg-base-100/80 backdrop-blur-sm border-0 hover:bg-base-100 shadow-sm"
        :title="inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist')"
        @click="toggleWishlist($event)"
      >
        <Heart
          class="w-4 h-4 transition-all duration-300"
          :class="inWishlist ? 'fill-error text-error scale-110' : 'text-base-content/40'"
        />
      </button>

      <!-- View overlay on hover -->
      <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-4">
        <span class="btn btn-sm btn-primary gap-1 shadow-lg">
          <Eye class="w-3.5 h-3.5" />
          {{ t('catalog.productDetail') }}
        </span>
      </div>
    </figure>

    <div class="card-body p-4 gap-1">
      <!-- Category path -->
      <p
        v-if="product.category_path"
        class="text-[11px] text-base-content/40 truncate"
      >
        {{ product.category_path }}
      </p>

      <!-- Product name -->
      <h3 class="text-sm font-semibold line-clamp-2 leading-tight min-h-[2.5rem]">
        {{ product.name }}
      </h3>

      <!-- SKU -->
      <p class="text-xs text-base-content/50 font-mono">
        {{ product.sku }}
      </p>

      <!-- Price -->
      <div class="flex justify-between items-end mt-2">
        <span
          v-if="formattedPrice"
          class="text-lg font-bold text-primary"
        >
          {{ formattedPrice }}
        </span>
        <span v-else class="text-sm text-base-content/30">--</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes catalogCardEnter {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.catalog-card-enter {
  animation: catalogCardEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
}
</style>
