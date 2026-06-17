<script setup>
import { onMounted, computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeft, Heart, Package, Braces, FileDown } from 'lucide-vue-next'
import CatalogImageGallery from '@/components/catalog/CatalogImageGallery.vue'
import CatalogProductDescription from '@/components/catalog/CatalogProductDescription.vue'
import AttributeLiveEditPopover from '@/components/catalog/AttributeLiveEditPopover.vue'
import PdfPreview from '@/components/shared/PdfPreview.vue'
import { formatCompositeSummary } from '@/utils/formatting'
import catalogApi from '@/api/catalog'
import { triggerDownload } from '@/utils/download'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const store = useCatalogStore()
const authStore = useAuthStore()

// Live-Edit: Inline-Bearbeitung von Beschreibungs-Attributen (nur mit Schreibrecht)
const canLiveEdit = computed(() => authStore.hasPermission('products.edit'))
const liveEdit = ref({ open: false, attributeId: null, label: '' })

function openLiveEdit(attr) {
  liveEdit.value = { open: true, attributeId: attr.attribute_id, label: attr.label }
}

function closeLiveEdit() {
  liveEdit.value = { ...liveEdit.value, open: false }
}

async function onLiveEditSaved() {
  closeLiveEdit()
  if (product.value?.id) {
    await store.fetchProduct(product.value.id)
  }
}

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
  if (linkAttributes.value.length) {
    list.push({ id: 'media-links', label: 'Medien & Links' })
  }
  if (product.value?.variants?.length) {
    list.push({ id: 'variants', label: 'Varianten' })
  }
  if (relations.value.length) {
    list.push({ id: 'relations', label: 'Beziehungen' })
  }
  return list
})

const relations = computed(() => product.value?.relations || [])

const relationsByType = computed(() => {
  const groups = {}
  for (const rel of relations.value) {
    const type = rel.relation_type || 'Sonstige'
    if (!groups[type]) groups[type] = []
    groups[type].push(rel)
  }
  return groups
})

const LINK_DATA_TYPES = ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']

const parentAttributes = computed(() => {
  if (!product.value?.attributes) return []
  return product.value.attributes.filter(a => !a.parent_attribute_id && !LINK_DATA_TYPES.includes(a.data_type))
})

const linkAttributes = computed(() => {
  if (!product.value?.attributes) return []
  return product.value.attributes.filter(a => LINK_DATA_TYPES.includes(a.data_type) && a.link_data)
})

const groupedLinks = computed(() => {
  const groups = { ImageLink: [], VideoLink: [], PdfLink: [], Hyperlink: [] }
  for (const attr of linkAttributes.value) {
    if (groups[attr.data_type]) groups[attr.data_type].push(attr)
  }
  return groups
})

const linkGroupLabels = { ImageLink: 'Bilder', VideoLink: 'Videos', PdfLink: 'PDFs', Hyperlink: 'Links' }

const pdfDisplayMode = computed(() => store.themeSettings.pdf_display_mode || 'link')

function getVideoEmbedUrl(url) {
  if (!url) return null
  const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([\w-]+)/)
  if (yt) return `https://www.youtube.com/embed/${yt[1]}`
  const vim = url.match(/vimeo\.com\/(\d+)/)
  if (vim) return `https://player.vimeo.com/video/${vim[1]}`
  return null
}

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

const pdfLoading = ref(false)

async function downloadPdf() {
  if (!product.value) return
  pdfLoading.value = true
  try {
    const resp = await catalogApi.downloadProductPdf(product.value.id, { lang: store.locale })
    triggerDownload(resp.data, `${product.value.sku || 'product'}.pdf`)
  } catch (e) {
    console.error('PDF download failed:', e)
  } finally {
    pdfLoading.value = false
  }
}

function goBack() {
  router.push({ name: 'catalog' })
}

function goToProduct(productId) {
  router.push({ name: 'catalog-product', params: { id: productId } })
}

onMounted(() => {
  store.fetchProduct(route.params.id)
})

// Re-fetch when navigating between related products
watch(() => route.params.id, (newId) => {
  if (newId) {
    activeTab.value = 'overview'
    store.fetchProduct(newId)
  }
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
          <div v-if="product.description_attributes?.length || product.description">
            <h3 class="font-semibold text-base-content mb-2">{{ t('catalog.description') }}</h3>
            <CatalogProductDescription :description="product.description" :description-attributes="product.description_attributes" :editable="canLiveEdit" @edit="openLiveEdit" />
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
                      <template v-else-if="attr.data_type === 'DelimitedValue' && attr.value">
                        <div class="flex flex-wrap gap-1">
                          <span v-for="(dv, dvI) in String(attr.value).split(attr.delimiter || '|').map(v => v.trim()).filter(Boolean)" :key="dvI"
                                class="badge badge-sm badge-primary badge-outline">{{ dv }}</span>
                        </div>
                      </template>
                      <template v-else-if="attr.data_type === 'JsonArtefact' && attr.value">
                        <code class="block text-xs bg-base-200 rounded px-2 py-1 max-h-24 overflow-auto whitespace-pre-wrap break-all">{{ attr.value }}</code>
                      </template>
                      <template v-else-if="attr.values?.length > 1">
                        <ul class="list-disc pl-4 space-y-0.5">
                          <li v-for="(v, vi) in attr.values" :key="vi">{{ v }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span></li>
                        </ul>
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
            <button v-if="store.themeSettings.catalog_pdf_enabled" class="btn btn-ghost btn-outline gap-1" :disabled="pdfLoading" @click="downloadPdf" title="PDF"><FileDown class="w-4 h-4" /> {{ pdfLoading ? '...' : 'PDF' }}</button>
          </div>
        </div>
      </div>
      <!-- Relations tab (classic layout) -->
      <div v-if="relations.length" class="border-t border-base-300 p-6">
        <h3 class="font-semibold text-base-content mb-3 text-sm">Beziehungen</h3>
        <div class="text-sm">
          <div v-for="(rels, typeName) in relationsByType" :key="typeName" class="mb-3 last:mb-0">
            <h4 class="text-xs font-semibold text-base-content/60 mb-1">{{ typeName }}</h4>
            <table class="table table-xs table-zebra w-full">
              <thead><tr><th class="text-base-content/60">SKU</th><th class="text-base-content/60">Name</th></tr></thead>
              <tbody>
                <tr v-for="rel in rels" :key="rel.target_product_id" class="cursor-pointer hover:bg-primary/5" @click="goToProduct(rel.target_product_id)">
                  <td class="font-mono text-base-content/70">{{ rel.sku }}</td>
                  <td class="link link-primary">{{ rel.name }}</td>
                </tr>
              </tbody>
            </table>
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
            <div v-if="activeTab === 'overview'">
              <CatalogProductDescription :description="product.description" :description-attributes="product.description_attributes" :editable="canLiveEdit" @edit="openLiveEdit" />
            </div>
            <div v-if="activeTab === 'attributes'" class="text-sm">
              <table class="table table-xs table-zebra w-full">
                <tbody>
                  <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                    <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                    <td class="text-base-content">
                      <template v-if="attr.data_type === 'Composite'">{{ getCatalogCompositeSummary(attr) || '—' }}</template>
                      <template v-else-if="attr.values?.length > 1">
                        <ul class="list-disc pl-4 space-y-0.5">
                          <li v-for="(v, vi) in attr.values" :key="vi">{{ v }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span></li>
                        </ul>
                      </template>
                      <template v-else>{{ attr.value }}<span v-if="attr.unit" class="text-base-content/50 ml-1">{{ attr.unit }}</span></template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="activeTab === 'media-links'" class="space-y-4">
              <template v-for="(items, dtype) in groupedLinks" :key="dtype">
                <div v-if="items.length">
                  <h4 class="font-semibold text-sm text-base-content mb-2">{{ linkGroupLabels[dtype] }}</h4>
                  <!-- Images -->
                  <div v-if="dtype === 'ImageLink'" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <a v-for="(attr, idx) in items" :key="idx" :href="attr.link_data.url" target="_blank" rel="noopener noreferrer" class="group block rounded-lg overflow-hidden border border-base-300 hover:border-primary transition-colors">
                      <img :src="attr.link_data.url" :alt="attr.link_data.alt_text || attr.link_data.title || attr.label" class="w-full h-32 object-cover" loading="lazy" />
                      <div class="p-2 text-xs text-base-content/70">{{ attr.link_data.title || attr.label }}</div>
                    </a>
                  </div>
                  <!-- PDFs -->
                  <div v-else-if="dtype === 'PdfLink'" class="space-y-3">
                    <div v-for="(attr, idx) in items" :key="idx" class="rounded-lg border border-base-300 p-3">
                      <PdfPreview v-if="pdfDisplayMode === 'embedded'" :url="attr.link_data.url" :title="attr.link_data.title || attr.link_data.url" max-height="24rem" />
                      <a v-else :href="attr.link_data.url" :target="attr.link_data.target || '_blank'" rel="noopener noreferrer" class="link link-primary text-sm font-medium">{{ attr.link_data.title || attr.link_data.url }}</a>
                    </div>
                  </div>
                  <!-- Videos -->
                  <div v-else-if="dtype === 'VideoLink'" class="space-y-3">
                    <div v-for="(attr, idx) in items" :key="idx" class="rounded-lg border border-base-300 p-3">
                      <p class="text-sm font-medium text-base-content mb-2">{{ attr.link_data.title || attr.label }}</p>
                      <iframe v-if="getVideoEmbedUrl(attr.link_data.url)" :src="getVideoEmbedUrl(attr.link_data.url)" class="w-full aspect-video rounded" allowfullscreen loading="lazy"></iframe>
                      <video v-else :src="attr.link_data.url" controls class="w-full rounded"></video>
                      <div v-if="attr.link_data.width && attr.link_data.height" class="text-xs text-base-content/50 mt-1">{{ attr.link_data.width }} × {{ attr.link_data.height }} px</div>
                    </div>
                  </div>
                  <!-- Hyperlinks -->
                  <div v-else class="space-y-2">
                    <div v-for="(attr, idx) in items" :key="idx" class="flex items-center gap-2 text-sm">
                      <a :href="attr.link_data.url" :target="attr.link_data.target || '_blank'" rel="noopener noreferrer" class="link link-primary">{{ attr.link_data.title || attr.link_data.url }}</a>
                      <span class="text-xs text-base-content/40">{{ attr.label }}</span>
                    </div>
                  </div>
                </div>
              </template>
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
            <div v-if="activeTab === 'relations'" class="text-sm">
              <div v-for="(rels, typeName) in relationsByType" :key="typeName" class="mb-3">
                <h4 class="text-xs font-semibold text-base-content/60 mb-1">{{ typeName }}</h4>
                <table class="table table-xs table-zebra w-full">
                  <thead><tr><th class="text-base-content/60">SKU</th><th class="text-base-content/60">Name</th></tr></thead>
                  <tbody>
                    <tr v-for="rel in rels" :key="rel.target_product_id" class="cursor-pointer hover:bg-primary/5" @click="goToProduct(rel.target_product_id)">
                      <td class="font-mono text-base-content/70">{{ rel.sku }}</td>
                      <td class="link link-primary">{{ rel.name }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Actions -->
          <div class="pt-4 mt-4 border-t border-base-300 flex gap-2">
            <button class="btn gap-2" :class="inWishlist ? 'btn-outline btn-primary' : 'btn-primary'" @click="store.toggleWishlist(product.id)">
              <Heart class="w-4 h-4" :class="{ 'fill-current': inWishlist }" /> {{ inWishlist ? t('catalog.removeFromWishlist') : t('catalog.addToWishlist') }}
            </button>
            <a :href="store.productJsonUrl(product.id)" target="_blank" class="btn btn-ghost btn-outline gap-1" title="JSON"><Braces class="w-4 h-4" /> JSON</a>
            <button v-if="store.themeSettings.catalog_pdf_enabled" class="btn btn-ghost btn-outline gap-1" :disabled="pdfLoading" @click="downloadPdf" title="PDF"><FileDown class="w-4 h-4" /> {{ pdfLoading ? '...' : 'PDF' }}</button>
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
        <div v-if="product.description_attributes?.length || product.description" class="bg-base-200/30 rounded-xl p-4">
          <h3 v-if="!product.description_attributes?.length" class="font-semibold text-base-content mb-2 text-sm">{{ t('catalog.description') }}</h3>
          <CatalogProductDescription :description="product.description" :description-attributes="product.description_attributes" :editable="canLiveEdit" @edit="openLiveEdit" />
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
          <div v-if="linkAttributes.length" class="bg-base-200/30 rounded-xl p-4">
            <h3 class="font-semibold text-base-content mb-3 text-sm">Medien & Links</h3>
            <div class="space-y-3">
              <template v-for="(items, dtype) in groupedLinks" :key="dtype">
                <div v-if="items.length">
                  <h4 class="text-xs font-semibold text-base-content/60 mb-1.5">{{ linkGroupLabels[dtype] }}</h4>
                  <!-- Images -->
                  <div v-if="dtype === 'ImageLink'" class="flex flex-wrap gap-2">
                    <a v-for="(attr, idx) in items" :key="idx" :href="attr.link_data.url" target="_blank" rel="noopener noreferrer" class="block w-20 h-20 rounded border border-base-300 overflow-hidden hover:border-primary transition-colors">
                      <img :src="attr.link_data.url" :alt="attr.link_data.alt_text || attr.label" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                  </div>
                  <!-- PDFs -->
                  <div v-else-if="dtype === 'PdfLink'" class="space-y-2">
                    <div v-for="(attr, idx) in items" :key="idx">
                      <PdfPreview v-if="pdfDisplayMode === 'embedded'" :url="attr.link_data.url" :title="attr.link_data.title || attr.link_data.url" max-height="16rem" />
                      <a v-else :href="attr.link_data.url" :target="attr.link_data.target || '_blank'" rel="noopener noreferrer" class="link link-primary text-sm">{{ attr.link_data.title || attr.link_data.url }}</a>
                    </div>
                  </div>
                  <!-- Videos -->
                  <div v-else-if="dtype === 'VideoLink'" class="space-y-2">
                    <div v-for="(attr, idx) in items" :key="idx">
                      <p class="text-sm font-medium text-base-content mb-1">{{ attr.link_data.title || attr.label }}</p>
                      <iframe v-if="getVideoEmbedUrl(attr.link_data.url)" :src="getVideoEmbedUrl(attr.link_data.url)" class="w-full aspect-video rounded" allowfullscreen loading="lazy"></iframe>
                      <video v-else :src="attr.link_data.url" controls class="w-full rounded"></video>
                    </div>
                  </div>
                  <!-- Hyperlinks -->
                  <div v-else class="space-y-1">
                    <a v-for="(attr, idx) in items" :key="idx" :href="attr.link_data.url" :target="attr.link_data.target || '_blank'" rel="noopener noreferrer" class="block link link-primary text-sm">{{ attr.link_data.title || attr.link_data.url }}</a>
                  </div>
                </div>
              </template>
            </div>
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
        <!-- Relations section (hero layout) -->
        <div v-if="relations.length" class="bg-base-200/30 rounded-xl p-4">
          <h3 class="font-semibold text-base-content mb-3 text-sm">Beziehungen</h3>
          <div class="text-sm">
            <div v-for="(rels, typeName) in relationsByType" :key="typeName" class="mb-3 last:mb-0">
              <h4 class="text-xs font-semibold text-base-content/60 mb-1">{{ typeName }}</h4>
              <table class="table table-xs table-zebra w-full">
                <thead><tr><th class="text-base-content/60">SKU</th><th class="text-base-content/60">Name</th></tr></thead>
                <tbody>
                  <tr v-for="rel in rels" :key="rel.target_product_id" class="cursor-pointer hover:bg-primary/5" @click="goToProduct(rel.target_product_id)">
                    <td class="font-mono text-base-content/70">{{ rel.sku }}</td>
                    <td class="link link-primary">{{ rel.name }}</td>
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
          <button v-if="store.themeSettings.catalog_pdf_enabled" class="btn btn-ghost btn-outline gap-1" :disabled="pdfLoading" @click="downloadPdf" title="PDF"><FileDown class="w-4 h-4" /> {{ pdfLoading ? '...' : 'PDF' }}</button>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="alert alert-error">
      <span>{{ store.error }}</span>
    </div>

    <!-- Live-Edit Layer für Beschreibungs-Attribute -->
    <AttributeLiveEditPopover
      v-if="canLiveEdit && product"
      :open="liveEdit.open"
      :product-id="product.id"
      :attribute-id="liveEdit.attributeId"
      :label="liveEdit.label"
      :language="store.locale"
      @close="closeLiveEdit"
      @saved="onLiveEditSaved"
    />
  </div>
</template>
