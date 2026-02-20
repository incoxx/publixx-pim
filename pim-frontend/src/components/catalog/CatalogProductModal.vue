<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { X, Heart } from 'lucide-vue-next'
import CatalogImageGallery from './CatalogImageGallery.vue'

const props = defineProps({
  productId: { type: String, default: null },
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const { t } = useI18n()
const store = useCatalogStore()

watch(
  () => props.productId,
  (id) => {
    if (id) store.fetchProduct(id)
  },
)

const product = computed(() => store.currentProduct)
const inWishlist = computed(() =>
  product.value ? store.isInWishlist(product.value.id) : false,
)

function formatPrice(price) {
  if (!price?.amount) return '--'
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: price.currency || 'EUR',
  }).format(price.amount)
}
</script>

<template>
  <dialog class="modal" :class="{ 'modal-open': open }">
    <div class="modal-box max-w-4xl w-11/12 p-0 overflow-hidden">
      <!-- Close button -->
      <button
        class="btn btn-sm btn-circle btn-ghost absolute right-3 top-3 z-10"
        @click="emit('close')"
      >
        <X class="w-4 h-4" />
      </button>

      <!-- Loading -->
      <div v-if="store.productLoading" class="flex justify-center py-20">
        <span class="loading loading-spinner loading-lg text-primary"></span>
      </div>

      <!-- Content -->
      <div v-else-if="product" class="grid grid-cols-1 md:grid-cols-2">
        <!-- Left: Image gallery -->
        <div class="p-6 bg-base-200/50">
          <CatalogImageGallery :media="product.media || []" />
        </div>

        <!-- Right: Product info -->
        <div class="p-6 space-y-4 overflow-y-auto max-h-[80vh]">
          <!-- Breadcrumb -->
          <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
            <ul>
              <li v-for="crumb in product.category_breadcrumb" :key="crumb.id">
                <span class="text-base-content/50">{{ crumb.name }}</span>
              </li>
            </ul>
          </div>

          <!-- Name -->
          <h2 class="text-xl font-bold text-base-content leading-tight">
            {{ product.name }}
          </h2>

          <!-- SKU / EAN -->
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
            <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
            <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
          </div>

          <!-- Price -->
          <div v-if="product.prices?.length" class="text-2xl font-bold text-primary">
            {{ formatPrice(product.prices[0]) }}
          </div>

          <!-- Description -->
          <div v-if="product.description" class="text-sm text-base-content/70 leading-relaxed">
            <h4 class="font-semibold text-base-content mb-1">{{ t('catalog.description') }}</h4>
            <p>{{ product.description }}</p>
          </div>

          <!-- Actions -->
          <div class="pt-4 border-t border-base-300">
            <button
              class="btn gap-2 w-full"
              :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'"
              @click="store.toggleWishlist(product.id)"
            >
              <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" />
              {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
            </button>
          </div>
        </div>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40" @click="emit('close')">
      <button>close</button>
    </form>
  </dialog>
</template>
