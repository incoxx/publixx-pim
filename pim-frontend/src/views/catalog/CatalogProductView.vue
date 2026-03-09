<script setup>
import { onMounted, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { ArrowLeft, Heart, Package, Braces } from 'lucide-vue-next'
import CatalogImageGallery from '@/components/catalog/CatalogImageGallery.vue'
import { formatCompositeSummary } from '@/utils/formatting'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const store = useCatalogStore()

const product = computed(() => store.currentProduct)
const inWishlist = computed(() =>
  product.value ? store.isInWishlist(product.value.id) : false,
)
const layout = computed(() => store.themeSettings.detail_layout || 'classic')

const activeTab = ref('overview')
const tabs = computed(() => {
  const list = [{ id: 'overview', label: t('catalog.description') || 'Übersicht' }]
  if (product.value?.attributes?.length) {
    list.push({ id: 'attributes', label: t('catalog.attributes') || 'Merkmale' })
  }
  if (product.value?.variants?.length) {
    list.push({ id: 'variants', label: 'Varianten' })
  }
  return list
})

const parentAttributes = computed(() => {
  if (!product.value?.attributes) return []
  return product.value.attributes.filter(a => !a.parent_attribute_id)
})

function formatPrice(price) {
  if (!price?.amount) return '--'
  return new Intl.NumberFormat(store.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency',
    currency: price.currency || 'EUR',
  }).format(price.amount)
}

function getCatalogCompositeSummary(compositeAttr) {
  if (!product.value?.attributes) return null
  return formatCompositeSummary({
    compositeFormat: compositeAttr.composite_format,
    children: product.value.attributes.filter(a => a.parent_attribute_id === compositeAttr.attribute_id),
    getValue: c => c.value,
  })
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

    <!-- ════ CLASSIC LAYOUT ════ -->
    <div v-else-if="product && layout === 'classic'" class="card bg-base-100 shadow-sm border border-base-300">
      <div class="grid grid-cols-1 md:grid-cols-2">
        <div class="p-6 bg-base-200/30 rounded-tl-2xl">
          <CatalogImageGallery :media="product.media || []" />
        </div>
        <div class="p-6 space-y-5">
          <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
            <ul>
              <li v-for="crumb in product.category_breadcrumb" :key="crumb.id">
                <span class="text-base-content/50">{{ crumb.name }}</span>
              </li>
            </ul>
          </div>
          <h1 class="font-bold text-base-content leading-tight" :style="{ fontSize: 'var(--catalog-heading-size, 1.75rem)' }">
            {{ product.name }}
          </h1>
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
            <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
            <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
          </div>
          <div v-if="product.prices?.length" class="text-3xl font-bold text-primary">
            {{ formatPrice(product.prices[0]) }}
          </div>
          <div v-if="product.description" class="text-sm text-base-content/70 leading-relaxed">
            <h3 class="font-semibold text-base-content mb-2">{{ t('catalog.description') }}</h3>
            <p>{{ product.description }}</p>
          </div>
          <div v-if="parentAttributes.length" class="text-sm">
            <h3 class="font-semibold text-base-content mb-2">{{ t('catalog.attributes') }}</h3>
            <table class="table table-xs table-zebra w-full">
              <tbody>
                <template v-for="(attr, idx) in parentAttributes" :key="idx">
                  <tr>
                    <td class="text-base-content/60 font-medium w-2/5 align-top whitespace-nowrap">{{ attr.label }}</td>
                    <td class="text-base-content">
                      <template v-if="attr.data_type === 'Composite'">
                        {{ getCatalogCompositeSummary(attr) || '—' }}
                      </template>
                      <template v-else>
                        {{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span>
                      </template>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div v-if="product.variants?.length" class="text-sm">
            <h3 class="font-semibold text-base-content mb-2">Varianten</h3>
            <div class="overflow-x-auto">
              <table class="table table-xs table-zebra w-full">
                <thead>
                  <tr>
                    <th class="text-base-content/60">SKU</th>
                    <th class="text-base-content/60">Name</th>
                    <th class="text-base-content/60">Status</th>
                    <th v-for="va in (product.variants[0]?.variant_attributes || [])" :key="va.label" class="text-base-content/60">{{ va.label }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="variant in product.variants" :key="variant.id">
                    <td class="font-mono text-base-content/70">{{ variant.sku }}</td>
                    <td>{{ variant.name }}</td>
                    <td><span :class="['badge badge-sm', variant.status === 'active' ? 'badge-success' : 'badge-ghost']">{{ variant.status === 'active' ? 'Aktiv' : variant.status }}</span></td>
                    <td v-for="(va, idx) in (variant.variant_attributes || [])" :key="idx">{{ va.value || '—' }}<span v-if="va.unit" class="text-base-content/50 ml-1">{{ va.unit }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="pt-4 border-t border-base-300 flex gap-2">
            <button class="btn gap-2" :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'" @click="store.toggleWishlist(product.id)">
              <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" />
              {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
            </button>
            <a :href="store.productJsonUrl(product.id)" target="_blank" class="btn btn-ghost btn-outline gap-1" title="JSON"><Braces class="w-4 h-4" /> JSON</a>
          </div>
        </div>
      </div>
    </div>

    <!-- ════ TABS LAYOUT ════ -->
    <div v-else-if="product && layout === 'tabs'" class="card bg-base-100 shadow-sm border border-base-300">
      <div class="grid grid-cols-1 md:grid-cols-2">
        <div class="p-6 bg-base-200/30 rounded-tl-2xl">
          <CatalogImageGallery :media="product.media || []" />
        </div>
        <div class="p-6 flex flex-col">
          <!-- Header -->
          <div class="space-y-3 mb-4">
            <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
              <ul><li v-for="crumb in product.category_breadcrumb" :key="crumb.id"><span class="text-base-content/50">{{ crumb.name }}</span></li></ul>
            </div>
            <h1 class="font-bold text-base-content leading-tight" :style="{ fontSize: 'var(--catalog-heading-size, 1.75rem)' }">{{ product.name }}</h1>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
              <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
              <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
            </div>
            <div v-if="product.prices?.length" class="text-3xl font-bold text-primary">{{ formatPrice(product.prices[0]) }}</div>
          </div>
          <!-- Tab bar -->
          <div class="tabs tabs-bordered mb-4">
            <button v-for="tab in tabs" :key="tab.id" class="tab" :class="{ 'tab-active': activeTab === tab.id }" @click="activeTab = tab.id">{{ tab.label }}</button>
          </div>
          <!-- Tab content -->
          <div class="flex-1">
            <div v-if="activeTab === 'overview'" class="text-sm text-base-content/70 leading-relaxed">
              <p v-if="product.description">{{ product.description }}</p>
              <p v-else class="text-base-content/40 italic">Keine Beschreibung vorhanden.</p>
            </div>
            <div v-if="activeTab === 'attributes'" class="text-sm">
              <table class="table table-xs table-zebra w-full">
                <tbody>
                  <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                    <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                    <td class="text-base-content">
                      <template v-if="attr.data_type === 'Composite'">{{ getCatalogCompositeSummary(attr) || '—' }}</template>
                      <template v-else>{{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span></template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="activeTab === 'variants'" class="text-sm overflow-x-auto">
              <table class="table table-xs table-zebra w-full">
                <thead>
                  <tr>
                    <th class="text-base-content/60">SKU</th>
                    <th class="text-base-content/60">Name</th>
                    <th class="text-base-content/60">Status</th>
                    <th v-for="va in (product.variants[0]?.variant_attributes || [])" :key="va.label" class="text-base-content/60">{{ va.label }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="variant in product.variants" :key="variant.id">
                    <td class="font-mono text-base-content/70">{{ variant.sku }}</td>
                    <td>{{ variant.name }}</td>
                    <td><span :class="['badge badge-sm', variant.status === 'active' ? 'badge-success' : 'badge-ghost']">{{ variant.status === 'active' ? 'Aktiv' : variant.status }}</span></td>
                    <td v-for="(va, idx) in (variant.variant_attributes || [])" :key="idx">{{ va.value || '—' }}<span v-if="va.unit" class="text-base-content/50 ml-1">{{ va.unit }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Actions -->
          <div class="pt-4 mt-4 border-t border-base-300 flex gap-2">
            <button class="btn gap-2" :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'" @click="store.toggleWishlist(product.id)">
              <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" /> {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
            </button>
            <a :href="store.productJsonUrl(product.id)" target="_blank" class="btn btn-ghost btn-outline gap-1" title="JSON"><Braces class="w-4 h-4" /> JSON</a>
          </div>
        </div>
      </div>
    </div>

    <!-- ════ HERO LAYOUT ════ -->
    <div v-else-if="product && layout === 'hero'" class="card bg-base-100 shadow-sm border border-base-300 overflow-hidden">
      <!-- Hero image -->
      <div class="bg-base-200/30 p-6">
        <div class="max-w-lg mx-auto">
          <CatalogImageGallery :media="product.media || []" />
        </div>
      </div>
      <!-- Content -->
      <div class="p-6 space-y-5">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div class="space-y-2 flex-1">
            <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
              <ul><li v-for="crumb in product.category_breadcrumb" :key="crumb.id"><span class="text-base-content/50">{{ crumb.name }}</span></li></ul>
            </div>
            <h1 class="font-bold text-base-content leading-tight" :style="{ fontSize: 'var(--catalog-heading-size, 1.75rem)' }">{{ product.name }}</h1>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
              <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
              <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
            </div>
          </div>
          <div v-if="product.prices?.length" class="text-3xl font-bold text-primary whitespace-nowrap">{{ formatPrice(product.prices[0]) }}</div>
        </div>
        <div v-if="product.description" class="bg-base-200/30 rounded-xl p-4">
          <h3 class="font-semibold text-base-content mb-2 text-sm">{{ t('catalog.description') }}</h3>
          <p class="text-sm text-base-content/70 leading-relaxed">{{ product.description }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-if="parentAttributes.length" class="bg-base-200/30 rounded-xl p-4">
            <h3 class="font-semibold text-base-content mb-3 text-sm">{{ t('catalog.attributes') }}</h3>
            <table class="table table-xs table-zebra w-full">
              <tbody>
                <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                  <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                  <td class="text-base-content">
                    <template v-if="attr.data_type === 'Composite'">{{ getCatalogCompositeSummary(attr) || '—' }}</template>
                    <template v-else>{{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span></template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="product.variants?.length" class="bg-base-200/30 rounded-xl p-4">
            <h3 class="font-semibold text-base-content mb-3 text-sm">Varianten</h3>
            <div class="overflow-x-auto">
              <table class="table table-xs table-zebra w-full">
                <thead>
                  <tr>
                    <th class="text-base-content/60">SKU</th>
                    <th class="text-base-content/60">Name</th>
                    <th v-for="va in (product.variants[0]?.variant_attributes || [])" :key="va.label" class="text-base-content/60">{{ va.label }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="variant in product.variants" :key="variant.id">
                    <td class="font-mono text-base-content/70">{{ variant.sku }}</td>
                    <td>{{ variant.name }}</td>
                    <td v-for="(va, idx) in (variant.variant_attributes || [])" :key="idx">{{ va.value || '—' }}<span v-if="va.unit" class="text-base-content/50 ml-1">{{ va.unit }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="pt-4 border-t border-base-300 flex gap-2">
          <button class="btn gap-2" :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'" @click="store.toggleWishlist(product.id)">
            <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" /> {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
          </button>
          <a :href="store.productJsonUrl(product.id)" target="_blank" class="btn btn-ghost btn-outline gap-1" title="JSON"><Braces class="w-4 h-4" /> JSON</a>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="alert alert-error">
      <span>{{ store.error }}</span>
    </div>
  </div>
</template>
