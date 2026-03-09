<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { X, Heart, Braces } from 'lucide-vue-next'
import CatalogImageGallery from './CatalogImageGallery.vue'
import { formatCompositeSummary } from '@/utils/formatting'

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
    if (id) {
      store.fetchProduct(id)
      activeTab.value = 'overview'
    }
  },
)

const product = computed(() => store.currentProduct)
const inWishlist = computed(() =>
  product.value ? store.isInWishlist(product.value.id) : false,
)

const layout = computed(() => store.themeSettings.detail_layout || 'classic')

const modalSizeClass = computed(() => {
  const map = { '4xl': 'max-w-4xl', '5xl': 'max-w-5xl', '6xl': 'max-w-6xl', '7xl': 'max-w-7xl', 'full': 'max-w-full' }
  return map[store.themeSettings.popup_max_width] || 'max-w-4xl'
})

// Tabs layout state
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

// Separate parent-only attributes
const parentAttributes = computed(() => {
  if (!product.value?.attributes) return []
  return product.value.attributes.filter(a =>
    !a.parent_attribute_id || !product.value.attributes.some(
      p => p.data_type === 'Composite' && p.attribute_id === a.parent_attribute_id
    )
  )
})

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
    <div :class="['modal-box w-11/12 p-0 overflow-hidden', modalSizeClass]">
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

      <!-- ════ CLASSIC LAYOUT ════ -->
      <template v-else-if="product && layout === 'classic'">
        <div class="grid grid-cols-1 md:grid-cols-2 max-h-[85vh] overflow-y-auto">
          <!-- Left: Image gallery -->
          <div class="p-4 md:p-6 bg-base-200/50">
            <CatalogImageGallery :media="product.media || []" />
          </div>

          <!-- Right: Product info -->
          <div class="p-4 md:p-6 space-y-4 md:overflow-y-auto md:max-h-[80vh]">
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

            <!-- Attributes -->
            <div v-if="parentAttributes.length" class="text-sm">
              <h4 class="font-semibold text-base-content mb-2">{{ t('catalog.attributes') }}</h4>
              <table class="table table-xs table-zebra w-full">
                <tbody>
                  <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                    <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                    <td class="text-base-content">
                      <template v-if="attr.data_type === 'Composite'">
                        {{ formatCompositeSummary({
                          compositeFormat: attr.composite_format,
                          children: product.attributes.filter(a => a.parent_attribute_id === attr.attribute_id),
                          getValue: c => c.value,
                        }) || '—' }}
                      </template>
                      <template v-else>
                        {{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span>
                      </template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Variants -->
            <div v-if="product.variants?.length" class="text-sm">
              <h4 class="font-semibold text-base-content mb-2">Varianten</h4>
              <div class="overflow-x-auto">
                <table class="table table-xs table-zebra w-full">
                  <thead>
                    <tr>
                      <th class="text-base-content/60">SKU</th>
                      <th class="text-base-content/60">Name</th>
                      <th class="text-base-content/60">Status</th>
                      <th
                        v-for="va in (product.variants[0]?.variant_attributes || [])"
                        :key="va.label"
                        class="text-base-content/60"
                      >
                        {{ va.label }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="variant in product.variants" :key="variant.id">
                      <td class="font-mono text-base-content/70">{{ variant.sku }}</td>
                      <td>{{ variant.name }}</td>
                      <td>
                        <span :class="['badge badge-sm', variant.status === 'active' ? 'badge-success' : 'badge-ghost']">
                          {{ variant.status === 'active' ? 'Aktiv' : variant.status }}
                        </span>
                      </td>
                      <td v-for="(va, idx) in (variant.variant_attributes || [])" :key="idx">
                        {{ va.value || '—' }}<span v-if="va.unit" class="text-base-content/50 ml-1">{{ va.unit }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Actions -->
            <div class="pt-4 border-t border-base-300 flex gap-2">
              <button
                class="btn gap-2 flex-1"
                :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'"
                @click="store.toggleWishlist(product.id)"
              >
                <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" />
                {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
              </button>
              <a
                :href="store.productJsonUrl(product.id)"
                target="_blank"
                class="btn btn-ghost btn-outline gap-1"
                title="JSON"
              >
                <Braces class="w-4 h-4" />
                JSON
              </a>
            </div>
          </div>
        </div>
      </template>

      <!-- ════ TABS LAYOUT ════ -->
      <template v-else-if="product && layout === 'tabs'">
        <div class="grid grid-cols-1 md:grid-cols-2 max-h-[85vh] overflow-y-auto">
          <!-- Left: Image gallery -->
          <div class="p-4 md:p-6 bg-base-200/50">
            <CatalogImageGallery :media="product.media || []" />
          </div>

          <!-- Right: Tabbed content -->
          <div class="p-4 md:p-6 flex flex-col md:max-h-[80vh]">
            <!-- Header info (always visible) -->
            <div class="space-y-3 mb-4">
              <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
                <ul>
                  <li v-for="crumb in product.category_breadcrumb" :key="crumb.id">
                    <span class="text-base-content/50">{{ crumb.name }}</span>
                  </li>
                </ul>
              </div>
              <h2 class="text-xl font-bold text-base-content leading-tight">{{ product.name }}</h2>
              <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
                <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
                <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
              </div>
              <div v-if="product.prices?.length" class="text-2xl font-bold text-primary">
                {{ formatPrice(product.prices[0]) }}
              </div>
            </div>

            <!-- Tab bar -->
            <div class="tabs tabs-bordered mb-4 shrink-0">
              <button
                v-for="tab in tabs"
                :key="tab.id"
                class="tab"
                :class="{ 'tab-active': activeTab === tab.id }"
                @click="activeTab = tab.id"
              >
                {{ tab.label }}
              </button>
            </div>

            <!-- Tab content (scrollable) -->
            <div class="flex-1 overflow-y-auto min-h-0">
              <!-- Overview tab -->
              <div v-if="activeTab === 'overview'" class="text-sm text-base-content/70 leading-relaxed">
                <p v-if="product.description">{{ product.description }}</p>
                <p v-else class="text-base-content/40 italic">Keine Beschreibung vorhanden.</p>
              </div>

              <!-- Attributes tab -->
              <div v-if="activeTab === 'attributes'" class="text-sm">
                <table class="table table-xs table-zebra w-full">
                  <tbody>
                    <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                      <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                      <td class="text-base-content">
                        <template v-if="attr.data_type === 'Composite'">
                          {{ formatCompositeSummary({
                            compositeFormat: attr.composite_format,
                            children: product.attributes.filter(a => a.parent_attribute_id === attr.attribute_id),
                            getValue: c => c.value,
                          }) || '—' }}
                        </template>
                        <template v-else>
                          {{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span>
                        </template>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Variants tab -->
              <div v-if="activeTab === 'variants'" class="text-sm">
                <div class="overflow-x-auto">
                  <table class="table table-xs table-zebra w-full">
                    <thead>
                      <tr>
                        <th class="text-base-content/60">SKU</th>
                        <th class="text-base-content/60">Name</th>
                        <th class="text-base-content/60">Status</th>
                        <th
                          v-for="va in (product.variants[0]?.variant_attributes || [])"
                          :key="va.label"
                          class="text-base-content/60"
                        >
                          {{ va.label }}
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="variant in product.variants" :key="variant.id">
                        <td class="font-mono text-base-content/70">{{ variant.sku }}</td>
                        <td>{{ variant.name }}</td>
                        <td>
                          <span :class="['badge badge-sm', variant.status === 'active' ? 'badge-success' : 'badge-ghost']">
                            {{ variant.status === 'active' ? 'Aktiv' : variant.status }}
                          </span>
                        </td>
                        <td v-for="(va, idx) in (variant.variant_attributes || [])" :key="idx">
                          {{ va.value || '—' }}<span v-if="va.unit" class="text-base-content/50 ml-1">{{ va.unit }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="pt-4 mt-4 border-t border-base-300 flex gap-2 shrink-0">
              <button
                class="btn gap-2 flex-1"
                :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'"
                @click="store.toggleWishlist(product.id)"
              >
                <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" />
                {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
              </button>
              <a
                :href="store.productJsonUrl(product.id)"
                target="_blank"
                class="btn btn-ghost btn-outline gap-1"
                title="JSON"
              >
                <Braces class="w-4 h-4" />
                JSON
              </a>
            </div>
          </div>
        </div>
      </template>

      <!-- ════ HERO LAYOUT ════ -->
      <template v-else-if="product && layout === 'hero'">
        <div class="max-h-[85vh] overflow-y-auto">
          <!-- Hero image (full-width) -->
          <div class="bg-base-200/50 p-6 pb-4">
            <div class="max-w-md mx-auto">
              <CatalogImageGallery :media="product.media || []" />
            </div>
          </div>

          <!-- Content -->
          <div class="p-4 md:p-6 space-y-5">
            <!-- Header row -->
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
              <div class="space-y-2 flex-1">
                <div v-if="product.category_breadcrumb?.length" class="breadcrumbs text-xs">
                  <ul>
                    <li v-for="crumb in product.category_breadcrumb" :key="crumb.id">
                      <span class="text-base-content/50">{{ crumb.name }}</span>
                    </li>
                  </ul>
                </div>
                <h2 class="text-2xl font-bold text-base-content leading-tight">{{ product.name }}</h2>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/50">
                  <span>{{ t('catalog.sku') }}: <span class="font-mono text-base-content/70">{{ product.sku }}</span></span>
                  <span v-if="product.ean">{{ t('catalog.ean') }}: <span class="font-mono text-base-content/70">{{ product.ean }}</span></span>
                </div>
              </div>
              <div v-if="product.prices?.length" class="text-3xl font-bold text-primary whitespace-nowrap">
                {{ formatPrice(product.prices[0]) }}
              </div>
            </div>

            <!-- Description card -->
            <div v-if="product.description" class="bg-base-200/30 rounded-xl p-4">
              <h4 class="font-semibold text-base-content mb-2 text-sm">{{ t('catalog.description') }}</h4>
              <p class="text-sm text-base-content/70 leading-relaxed">{{ product.description }}</p>
            </div>

            <!-- Attributes & Variants side-by-side on desktop -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Attributes -->
              <div v-if="parentAttributes.length" class="bg-base-200/30 rounded-xl p-4">
                <h4 class="font-semibold text-base-content mb-3 text-sm">{{ t('catalog.attributes') }}</h4>
                <table class="table table-xs table-zebra w-full">
                  <tbody>
                    <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                      <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                      <td class="text-base-content">
                        <template v-if="attr.data_type === 'Composite'">
                          {{ formatCompositeSummary({
                            compositeFormat: attr.composite_format,
                            children: product.attributes.filter(a => a.parent_attribute_id === attr.attribute_id),
                            getValue: c => c.value,
                          }) || '—' }}
                        </template>
                        <template v-else>
                          {{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span>
                        </template>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Variants -->
              <div v-if="product.variants?.length" class="bg-base-200/30 rounded-xl p-4">
                <h4 class="font-semibold text-base-content mb-3 text-sm">Varianten</h4>
                <div class="overflow-x-auto">
                  <table class="table table-xs table-zebra w-full">
                    <thead>
                      <tr>
                        <th class="text-base-content/60">SKU</th>
                        <th class="text-base-content/60">Name</th>
                        <th
                          v-for="va in (product.variants[0]?.variant_attributes || [])"
                          :key="va.label"
                          class="text-base-content/60"
                        >
                          {{ va.label }}
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="variant in product.variants" :key="variant.id">
                        <td class="font-mono text-base-content/70">{{ variant.sku }}</td>
                        <td>{{ variant.name }}</td>
                        <td v-for="(va, idx) in (variant.variant_attributes || [])" :key="idx">
                          {{ va.value || '—' }}<span v-if="va.unit" class="text-base-content/50 ml-1">{{ va.unit }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="pt-4 border-t border-base-300 flex gap-2">
              <button
                class="btn gap-2 flex-1"
                :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'"
                @click="store.toggleWishlist(product.id)"
              >
                <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" />
                {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
              </button>
              <a
                :href="store.productJsonUrl(product.id)"
                target="_blank"
                class="btn btn-ghost btn-outline gap-1"
                title="JSON"
              >
                <Braces class="w-4 h-4" />
                JSON
              </a>
            </div>
          </div>
        </div>
      </template>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40" @click="emit('close')">
      <button>close</button>
    </form>
  </dialog>
</template>
