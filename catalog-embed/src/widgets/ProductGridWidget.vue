<script setup>
import { onMounted, watch } from 'vue'
import { useStore } from '../store.js'
import { resolveMediaUrl } from '../api.js'
import { icons } from '../icons.js'

const { state, actions, getters } = useStore()

onMounted(() => {
  if (state.products.length === 0 && !state.loading) {
    actions.fetchProducts()
  }
})

// Re-fetch when locale changes
watch(() => state.locale, () => actions.fetchProducts())

function formatPrice(price, currency) {
  if (!price) return null
  return new Intl.NumberFormat(state.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: currency || 'EUR',
  }).format(price)
}

function viewDetail(product) {
  actions.openDetail(product.id)
}

function onWishlist(e, productId) {
  e.stopPropagation()
  actions.toggleWishlist(productId)
}
</script>

<template>
  <div class="pxc-product-grid">
    <!-- Category Assets -->
    <div v-if="state.categoryAssets.length > 0 && !getters.searchActive.value" class="pxc-category-assets">
      <h3 class="pxc-category-assets__title">
        {{ state.selectedCategoryName ? state.selectedCategoryName + ' — ' : '' }}Medien
      </h3>
      <div class="pxc-category-assets__scroll">
        <a
          v-for="asset in state.categoryAssets"
          :key="asset.id"
          :href="resolveMediaUrl(asset.original_url)"
          target="_blank"
          class="pxc-category-assets__item"
          :title="asset.title || asset.file_name"
        >
          <img
            v-if="asset.thumb_url"
            :src="resolveMediaUrl(asset.thumb_url)"
            :alt="asset.alt_text || asset.title || asset.file_name"
            loading="lazy"
          />
          <span v-else style="font-size:10px;opacity:0.3;text-transform:uppercase">{{ (asset.mime_type || '').split('/')[1] }}</span>
        </a>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="state.loading && state.products.length === 0" class="pxc-product-grid__loading">
      <div v-for="i in 8" :key="i" class="pxc-skeleton pxc-skeleton--card"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="getters.isEmpty.value" class="pxc-product-grid__empty">
      <span v-html="icons.package" style="width:48px;height:48px;opacity:0.2"></span>
      <p>Keine Produkte gefunden</p>
    </div>

    <!-- Grid -->
    <div v-else class="pxc-product-grid__grid" :class="state.viewMode === 'list' ? 'pxc-product-grid__grid--list' : ''">
      <div
        v-for="product in state.products"
        :key="product.id"
        class="pxc-product-card"
        @click="viewDetail(product)"
      >
        <!-- Image -->
        <div class="pxc-product-card__image">
          <img
            v-if="product.image_url"
            :src="product.image_url"
            :alt="product.name"
            loading="lazy"
          />
          <div v-else class="pxc-product-card__no-image">
            <span v-html="icons.package"></span>
          </div>

          <!-- Wishlist button -->
          <button
            class="pxc-product-card__wishlist"
            @click="onWishlist($event, product.id)"
            :title="getters.isInWishlist(product.id) ? 'Von Merkliste entfernen' : 'Zur Merkliste'"
          >
            <span v-html="getters.isInWishlist(product.id) ? icons.heartFilled : icons.heart"
                  :class="{ 'pxc-text-accent': getters.isInWishlist(product.id) }"></span>
          </button>
        </div>

        <!-- Body -->
        <div class="pxc-product-card__body">
          <p v-if="product.category_path" class="pxc-product-card__category">{{ product.category_path }}</p>
          <h3 class="pxc-product-card__name">{{ (product.primary_attribute_value && product.primary_attribute_value.trim()) || product.name || product.sku || '–' }}</h3>
          <p v-if="product.sku" class="pxc-product-card__sku">{{ product.sku }}</p>

          <!-- Card attributes -->
          <div v-if="product.card_attributes?.length" class="pxc-product-card__attrs">
            <span v-for="(attr, idx) in product.card_attributes.slice(0, 3)" :key="idx">{{ attr.value }}</span>
          </div>

          <!-- Price -->
          <div v-if="product.price" class="pxc-product-card__price">
            {{ formatPrice(product.price, product.currency) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Loading overlay for pagination -->
    <div v-if="state.loading && state.products.length > 0" class="pxc-product-grid__overlay">
      <span v-html="icons.loader" style="width:32px;height:32px"></span>
    </div>
  </div>
</template>
