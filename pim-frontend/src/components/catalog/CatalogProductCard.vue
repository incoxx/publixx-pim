<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCatalogStore } from '@/stores/catalog'
import { useCatalogEditAccess } from '@/composables/useCatalogEditAccess'
import { Heart, Eye, Package, FileDown, Pencil } from 'lucide-vue-next'
import catalogApi from '@/api/catalog'
import { triggerDownload } from '@/utils/download'

const props = defineProps({
  product: { type: Object, required: true },
  index: { type: Number, default: 0 },
})

const emit = defineEmits(['view-detail'])

const { t } = useI18n()
const router = useRouter()
const store = useCatalogStore()

// "Zum Editor" nur in der internen Vorschau (/preview) für angemeldete Bearbeiter
const canEdit = useCatalogEditAccess()

function goToEditor(e) {
  e.stopPropagation()
  router.push({ name: 'product-detail', params: { id: props.product.id } })
}

const inWishlist = computed(() => store.isInWishlist(props.product.id))

const showCategory = computed(() => store.themeSettings.card_show_category !== false)
const showSku = computed(() => store.themeSettings.card_show_sku === true)
const showPrice = computed(() => store.themeSettings.card_show_price !== false)
const imageRatio = computed(() => store.themeSettings.card_image_ratio || '4/3')

const pdfLoading = ref(false)

function toggleWishlist(e) {
  e.stopPropagation()
  store.toggleWishlist(props.product.id)
}

async function downloadPdf(e) {
  e.stopPropagation()
  if (pdfLoading.value) return
  pdfLoading.value = true
  try {
    const resp = await catalogApi.downloadProductPdf(props.product.id, { lang: store.locale })
    triggerDownload(resp.data, `${props.product.sku || 'product'}.pdf`)
  } catch (err) {
    console.error('PDF download failed:', err)
  } finally {
    pdfLoading.value = false
  }
}

const formattedPrice = computed(() => {
  if (!props.product.price) return null
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: props.product.currency || 'EUR',
  }).format(props.product.price)
})

const imgBroken = ref(false)
function onImgError() { imgBroken.value = true }

const staggerDelay = computed(() => `${Math.min(props.index * 50, 400)}ms`)
</script>

<template>
  <div
    class="card bg-base-100 shadow-sm border border-base-300 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer group catalog-card-enter"
    :style="{ animationDelay: staggerDelay }"
    @click="emit('view-detail', product)"
  >
    <!-- Image -->
    <figure class="relative overflow-hidden bg-base-200" :style="{ aspectRatio: imageRatio }">
      <img
        v-if="product.image_url && !imgBroken"
        :src="product.image_url"
        :alt="product.name"
        class="object-contain w-full h-full p-4 group-hover:scale-105 transition-transform duration-500"
        loading="lazy"
        @error="onImgError"
      />
      <div v-else class="flex items-center justify-center w-full h-full">
        <Package class="w-12 h-12 text-base-content/15" />
      </div>

      <!-- PDF download button overlay -->
      <button
        v-if="store.themeSettings.catalog_pdf_enabled"
        class="btn btn-circle btn-sm absolute top-2 left-2 bg-base-100/80 backdrop-blur-sm border-0 hover:bg-base-100 shadow-sm"
        title="PDF"
        @click="downloadPdf($event)"
      >
        <FileDown
          class="w-4 h-4 transition-all duration-300"
          :class="pdfLoading ? 'animate-pulse text-primary' : 'text-base-content/40'"
        />
      </button>

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

      <!-- Zum Editor (nur angemeldete Bearbeiter) -->
      <button
        v-if="canEdit"
        class="btn btn-circle btn-sm absolute bottom-2 right-2 bg-base-100/80 backdrop-blur-sm border-0 hover:bg-base-100 shadow-sm"
        title="Zum Editor"
        @click="goToEditor($event)"
      >
        <Pencil class="w-4 h-4 text-base-content/50" />
      </button>
    </figure>

    <div class="card-body p-4 gap-1">
      <!-- Category path -->
      <p
        v-if="showCategory && product.category_path"
        class="text-[11px] text-base-content/40 truncate"
      >
        {{ product.category_path }}
      </p>

      <!-- Product name / primary attribute -->
      <h3 class="text-sm font-semibold line-clamp-2 leading-tight min-h-[2.5rem]">
        {{ product.primary_attribute_value || product.name || product.sku || '–' }}
      </h3>

      <!-- SKU -->
      <p v-if="showSku" class="text-[11px] text-base-content/50 font-mono truncate">
        {{ product.sku }}
      </p>

      <!-- Match sources (search hit badges) -->
      <div v-if="product.match_sources?.length" class="flex flex-wrap gap-1 mt-1">
        <span
          v-for="(source, i) in product.match_sources.slice(0, 3)"
          :key="i"
          class="badge badge-xs"
          :class="{
            'badge-primary': source.type === 'name',
            'badge-secondary': source.type === 'sku' || source.type === 'ean',
            'badge-accent': source.type === 'attribute',
            'badge-info': source.type === 'media' || source.type === 'description',
            'badge-warning': source.type === 'phonetic',
          }"
        >
          {{ source.label }}
        </span>
      </div>

      <!-- Card attributes -->
      <div v-if="product.card_attributes?.length" class="mt-1 space-y-0.5">
        <div
          v-for="(attr, idx) in product.card_attributes.slice(0, 3)"
          :key="idx"
          class="text-[11px] text-base-content/60 truncate"
        >
          {{ attr.value }}
        </div>
      </div>

      <!-- Price -->
      <div v-if="showPrice" class="flex justify-between items-end mt-2">
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
