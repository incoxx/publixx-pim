<script setup>
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { ArrowLeft, Heart, Package } from 'lucide-vue-next'
import CatalogImageGallery from '@/components/catalog/CatalogImageGallery.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const store = useCatalogStore()

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

function goBack() {
  router.push({ name: 'catalog' })
}

onMounted(() => {
  store.fetchProduct(route.params.id)
})
</script>

<template>
  <div>
    <!-- Back link -->
    <button class="btn btn-ghost btn-sm gap-1 mb-4" @click="goBack">
      <ArrowLeft class="w-4 h-4" />
      {{ t('catalog.backToCatalog') }}
    </button>

    <!-- Loading -->
    <div v-if="store.productLoading" class="flex justify-center py-20">
      <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    <!-- Product detail -->
    <div v-else-if="product" class="card bg-base-100 shadow-sm border border-base-300">
      <div class="grid grid-cols-1 md:grid-cols-2">
        <!-- Left: Image gallery -->
        <div class="p-6 bg-base-200/30 rounded-tl-2xl">
          <CatalogImageGallery :media="product.media || []" />
        </div>

        <!-- Right: Product info -->
        <div class="p-6 space-y-5">
          <!-- Breadcrumb -->
          <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
            <ul>
              <li v-for="crumb in product.category_breadcrumb" :key="crumb.id">
                <span class="text-base-content/50">{{ crumb.name }}</span>
              </li>
            </ul>
          </div>

          <!-- Name -->
          <h1 class="text-2xl font-bold text-base-content leading-tight">
            {{ product.name }}
          </h1>

          <!-- SKU / EAN -->
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
            <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
            <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
          </div>

          <!-- Price -->
          <div v-if="product.prices?.length" class="text-3xl font-bold text-primary">
            {{ formatPrice(product.prices[0]) }}
          </div>

          <!-- Description -->
          <div v-if="product.description" class="text-sm text-base-content/70 leading-relaxed">
            <h3 class="font-semibold text-base-content mb-2">{{ t('catalog.description') }}</h3>
            <p>{{ product.description }}</p>
          </div>

          <!-- Actions -->
          <div class="pt-4 border-t border-base-300">
            <button
              class="btn gap-2"
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

    <!-- Error -->
    <div v-else-if="store.error" class="alert alert-error">
      <span>{{ store.error }}</span>
    </div>
  </div>
</template>
