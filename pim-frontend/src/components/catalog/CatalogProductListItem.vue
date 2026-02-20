<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { Heart, Package } from 'lucide-vue-next'

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

const staggerDelay = computed(() => `${Math.min(props.index * 30, 300)}ms`)
</script>

<template>
  <div
    class="card card-side bg-base-100 shadow-sm border border-base-300 hover:shadow-md transition-all duration-200 cursor-pointer group catalog-list-enter"
    :style="{ animationDelay: staggerDelay }"
    @click="emit('view-detail', product)"
  >
    <!-- Image -->
    <figure class="w-24 sm:w-32 flex-none bg-base-200 overflow-hidden">
      <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.name"
        class="object-contain w-full h-full p-2"
        loading="lazy"
      />
      <div v-else class="flex items-center justify-center w-full h-full">
        <Package class="w-8 h-8 text-base-content/15" />
      </div>
    </figure>

    <div class="card-body p-3 sm:p-4 gap-1">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
          <p v-if="product.category_path" class="text-[11px] text-base-content/40 truncate">
            {{ product.category_path }}
          </p>
          <h3 class="text-sm font-semibold line-clamp-1">{{ product.name }}</h3>
          <p class="text-xs text-base-content/50 font-mono mt-0.5">{{ product.sku }}</p>
          <p v-if="product.description" class="text-xs text-base-content/60 line-clamp-1 mt-1 hidden sm:block">
            {{ product.description }}
          </p>
        </div>

        <div class="flex items-center gap-2 flex-none">
          <span v-if="formattedPrice" class="text-base font-bold text-primary whitespace-nowrap">
            {{ formattedPrice }}
          </span>
          <button
            class="btn btn-ghost btn-sm btn-circle flex-none"
            :title="inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist')"
            @click="toggleWishlist($event)"
          >
            <Heart
              class="w-4 h-4 transition-all duration-300"
              :class="inWishlist ? 'fill-error text-error' : 'text-base-content/30'"
            />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes catalogListEnter {
  from {
    opacity: 0;
    transform: translateX(-8px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.catalog-list-enter {
  animation: catalogListEnter 0.3s cubic-bezier(0.16, 1, 0.3, 1) both;
}
</style>
