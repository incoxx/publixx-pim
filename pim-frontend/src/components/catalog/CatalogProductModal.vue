<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCatalogStore } from '@/stores/catalog'
import { useCatalogEditAccess } from '@/composables/useCatalogEditAccess'
import { X, Heart, FileDown, Pencil } from 'lucide-vue-next'
import CatalogImageGallery from './CatalogImageGallery.vue'
import CatalogProductDescription from './CatalogProductDescription.vue'
import AttributeLiveEditPopover from './AttributeLiveEditPopover.vue'
import PdfPreview from '@/components/shared/PdfPreview.vue'
import { formatCompositeSummary } from '@/utils/formatting'
import catalogApi from '@/api/catalog'
import { triggerDownload } from '@/utils/download'

const props = defineProps({
  productId: { type: String, default: null },
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const { t } = useI18n()
const router = useRouter()
const store = useCatalogStore()

// Live-Edit + "Zum Editor": nur in der internen Vorschau (/preview) mit Schreibrecht
const canLiveEdit = useCatalogEditAccess()

function goToEditor() {
  const id = props.productId || product.value?.id
  if (id) {
    emit('close')
    router.push({ name: 'product-detail', params: { id } })
  }
}
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
  if (linkAttributes.value.length || pdfMedia.value.length) {
    list.push({ id: 'media-links', label: 'Medien & Links' })
  }
  if (product.value?.variants?.length) {
    list.push({ id: 'variants', label: 'Varianten' })
  }
  return list
})

const LINK_DATA_TYPES = ['Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink']

// Separate parent-only attributes (excluding link types)
const parentAttributes = computed(() => {
  if (!product.value?.attributes) return []
  return product.value.attributes.filter(a =>
    !LINK_DATA_TYPES.includes(a.data_type) &&
    (!a.parent_attribute_id || !product.value.attributes.some(
      p => p.data_type === 'Composite' && p.attribute_id === a.parent_attribute_id
    ))
  )
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

function isPdfMedia(m) {
  const mt = (m.media_type || '').toLowerCase()
  return mt === 'pdf' || (m.mime_type || '').includes('pdf')
}

const pdfMedia = computed(() => {
  if (!product.value?.media) return []
  return product.value.media.filter(m => isPdfMedia(m))
})

const pdfDisplayMode = computed(() => store.themeSettings.pdf_display_mode || 'link')

function getVideoEmbedUrl(url) {
  if (!url) return null
  const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([\w-]+)/)
  if (yt) return `https://www.youtube.com/embed/${yt[1]}`
  const vim = url.match(/vimeo\.com\/(\d+)/)
  if (vim) return `https://player.vimeo.com/video/${vim[1]}`
  return null
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

async function switchLang(loc) {
  store.setLocale(loc)
  if (product.value?.id) {
    await store.fetchProduct(product.value.id)
  }
}

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
    <div :class="['modal-box catalog-popup-bg w-11/12 p-0 overflow-hidden', modalSizeClass]">
      <!-- Zum Editor (nur interne Vorschau /preview mit Schreibrecht) -->
      <button
        v-if="canLiveEdit"
        class="btn btn-sm btn-primary gap-1 absolute right-14 top-3 z-10"
        title="Im Produkteditor öffnen"
        @click="goToEditor"
      >
        <Pencil class="w-3.5 h-3.5" />
        Zum Editor
      </button>

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

            <!-- Prices -->
            <div v-if="product.prices?.length" class="flex flex-wrap gap-4">
              <div v-for="(price, idx) in product.prices" :key="idx">
                <span v-if="price.type_name" class="text-xs font-medium text-base-content/50 uppercase block">{{ price.type_name }}</span>
                <span class="text-2xl font-bold text-primary">{{ formatPrice(price) }}</span>
              </div>
            </div>

            <!-- Description -->
            <div v-if="product.description_attributes?.length || product.description">
              <h4 class="font-semibold text-base-content mb-1">{{ t('catalog.description') }}</h4>
              <CatalogProductDescription :description="product.description" :description-attributes="product.description_attributes" :editable="canLiveEdit" @edit="openLiveEdit" />
            </div>

            <!-- Attributes -->
            <div v-if="parentAttributes.length" class="text-sm">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold text-base-content">{{ t('catalog.attributes') }}</h4>
                <div class="flex gap-1">
                  <button @click="switchLang('de')" class="btn btn-xs" :class="store.locale === 'de' ? 'btn-primary' : 'btn-ghost'">DE</button>
                  <button @click="switchLang('en')" class="btn btn-xs" :class="store.locale === 'en' ? 'btn-primary' : 'btn-ghost'">EN</button>
                </div>
              </div>
              <table class="table table-xs table-zebra w-full">
                <tbody>
                  <tr v-for="(attr, idx) in parentAttributes" :key="idx">
                    <td class="text-base-content/60 font-medium w-2/5 align-top">{{ attr.label }}</td>
                    <td class="text-base-content">
                      <template v-if="attr.data_type === 'Composite'">
                        <template v-if="attr.value && attr.value.includes('\n')">
                          <div v-for="(line, li) in attr.value.split('\n')" :key="li">{{ line }}</div>
                        </template>
                        <template v-else>
                          {{ attr.value || formatCompositeSummary({
                            compositeFormat: attr.composite_format,
                            children: product.attributes.filter(a => a.parent_attribute_id === attr.attribute_id),
                            getValue: c => c.value,
                          }) || '—' }}
                        </template>
                      </template>
                      <template v-else-if="attr.link_data">
                        <a :href="attr.link_data.url" :target="attr.link_data.target || '_blank'" class="link link-primary text-sm" rel="noopener noreferrer">{{ attr.link_data.title || attr.link_data.url }}</a>
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
                </tbody>
              </table>
            </div>

            <!-- Media & Links -->
            <div v-if="linkAttributes.length || pdfMedia.length" class="text-sm">
              <h4 class="font-semibold text-base-content mb-2">Medien & Links</h4>
              <div class="space-y-3">
                <!-- Product PDF media files -->
                <div v-if="pdfMedia.length">
                  <h5 class="text-xs font-semibold text-base-content/60 mb-1">Dokumente</h5>
                  <div class="space-y-3">
                    <div v-for="(m, idx) in pdfMedia" :key="'pdf-' + idx" class="border border-base-300 rounded-lg overflow-hidden">
                      <PdfPreview :url="m.url" :media-id="m.id || m.media_id" :title="m.file_name || m.alt || 'PDF'" max-height="16rem" />
                      <div class="px-3 py-2 bg-base-200/50">
                        <p class="text-xs font-medium text-base-content">{{ m.file_name || 'PDF' }}</p>
                        <p v-if="m.description" class="text-xs text-base-content/60 mt-0.5">{{ m.description }}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Link attributes -->
                <template v-for="(items, dtype) in groupedLinks" :key="dtype">
                  <div v-if="items.length">
                    <h5 class="text-xs font-semibold text-base-content/60 mb-1">{{ linkGroupLabels[dtype] }}</h5>
                    <!-- Images -->
                    <div v-if="dtype === 'ImageLink'" class="flex flex-wrap gap-2">
                      <a v-for="(attr, idx) in items" :key="idx" :href="attr.link_data.url" target="_blank" rel="noopener noreferrer" class="block w-16 h-16 rounded border border-base-300 overflow-hidden hover:border-primary">
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
                    <div v-else-if="dtype === 'VideoLink'" class="space-y-3">
                      <div v-for="(attr, idx) in items" :key="idx">
                        <p class="text-sm font-medium text-base-content mb-1">{{ attr.link_data.title || attr.label }}</p>
                        <iframe v-if="getVideoEmbedUrl(attr.link_data.url)" :src="getVideoEmbedUrl(attr.link_data.url)" class="w-full aspect-video rounded border border-base-300" allowfullscreen loading="lazy"></iframe>
                        <video v-else :src="attr.link_data.url" controls class="w-full rounded border border-base-300"></video>
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
<button v-if="store.themeSettings.catalog_pdf_enabled" class="btn btn-ghost btn-outline gap-1" :disabled="pdfLoading" @click="downloadPdf" title="PDF">
                <FileDown class="w-4 h-4" /> {{ pdfLoading ? '...' : 'PDF' }}
              </button>
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
              <div v-if="activeTab === 'overview'">
                <CatalogProductDescription :description="product.description" :description-attributes="product.description_attributes" :editable="canLiveEdit" @edit="openLiveEdit" />
              </div>

              <!-- Attributes tab -->
              <div v-if="activeTab === 'attributes'" class="text-sm">
                <div class="flex justify-end mb-2">
                  <div class="flex gap-1">
                    <button @click="switchLang('de')" class="btn btn-xs" :class="store.locale === 'de' ? 'btn-primary' : 'btn-ghost'">DE</button>
                    <button @click="switchLang('en')" class="btn btn-xs" :class="store.locale === 'en' ? 'btn-primary' : 'btn-ghost'">EN</button>
                  </div>
                </div>
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

              <!-- Media & Links tab -->
              <div v-if="activeTab === 'media-links'" class="space-y-4">
                <!-- Product PDF media files -->
                <div v-if="pdfMedia.length">
                  <h4 class="font-semibold text-sm text-base-content mb-2">Dokumente</h4>
                  <div class="space-y-3">
                    <div v-for="(m, idx) in pdfMedia" :key="'pdf-' + idx" class="rounded-lg border border-base-300 overflow-hidden">
                      <PdfPreview :url="m.url" :media-id="m.id || m.media_id" :title="m.file_name || m.alt || 'PDF'" max-height="24rem" />
                      <div class="px-3 py-2 bg-base-200/50">
                        <p class="text-sm font-medium text-base-content">{{ m.file_name || 'PDF' }}</p>
                        <p v-if="m.description" class="text-xs text-base-content/60 mt-0.5">{{ m.description }}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Link attributes -->
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
<button v-if="store.themeSettings.catalog_pdf_enabled" class="btn btn-ghost btn-outline gap-1" :disabled="pdfLoading" @click="downloadPdf" title="PDF">
                <FileDown class="w-4 h-4" /> {{ pdfLoading ? '...' : 'PDF' }}
              </button>
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
            <div v-if="product.description_attributes?.length || product.description" class="bg-base-200/30 rounded-xl p-4">
              <h4 v-if="!product.description_attributes?.length" class="font-semibold text-base-content mb-2 text-sm">{{ t('catalog.description') }}</h4>
              <CatalogProductDescription :description="product.description" :description-attributes="product.description_attributes" :editable="canLiveEdit" @edit="openLiveEdit" />
            </div>

            <!-- Attributes & Variants side-by-side on desktop -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Attributes -->
              <div v-if="parentAttributes.length" class="bg-base-200/30 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="font-semibold text-base-content text-sm">{{ t('catalog.attributes') }}</h4>
                  <div class="flex gap-1">
                    <button @click="switchLang('de')" class="btn btn-xs" :class="store.locale === 'de' ? 'btn-primary' : 'btn-ghost'">DE</button>
                    <button @click="switchLang('en')" class="btn btn-xs" :class="store.locale === 'en' ? 'btn-primary' : 'btn-ghost'">EN</button>
                  </div>
                </div>
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

              <!-- Media & Links -->
              <div v-if="linkAttributes.length || pdfMedia.length" class="bg-base-200/30 rounded-xl p-4">
                <h4 class="font-semibold text-base-content mb-3 text-sm">Medien & Links</h4>
                <div class="space-y-3">
                  <!-- Product PDF media files -->
                  <div v-if="pdfMedia.length">
                    <h5 class="text-xs font-semibold text-base-content/60 mb-1.5">Dokumente</h5>
                    <div class="space-y-3">
                      <div v-for="(m, idx) in pdfMedia" :key="'pdf-' + idx" class="border border-base-300 rounded-lg overflow-hidden">
                        <PdfPreview :url="m.url" :media-id="m.id || m.media_id" :title="m.file_name || m.alt || 'PDF'" max-height="16rem" />
                        <div class="px-3 py-2 bg-base-200/50">
                          <p class="text-xs font-medium text-base-content">{{ m.file_name || 'PDF' }}</p>
                          <p v-if="m.description" class="text-xs text-base-content/60 mt-0.5">{{ m.description }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Link attributes -->
                  <template v-for="(items, dtype) in groupedLinks" :key="dtype">
                    <div v-if="items.length">
                      <h5 class="text-xs font-semibold text-base-content/60 mb-1.5">{{ linkGroupLabels[dtype] }}</h5>
                      <!-- Images -->
                      <div v-if="dtype === 'ImageLink'" class="flex flex-wrap gap-2">
                        <a v-for="(attr, idx) in items" :key="idx" :href="attr.link_data.url" target="_blank" rel="noopener noreferrer" class="block w-20 h-20 rounded border border-base-300 overflow-hidden hover:border-primary">
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
<button v-if="store.themeSettings.catalog_pdf_enabled" class="btn btn-ghost btn-outline gap-1" :disabled="pdfLoading" @click="downloadPdf" title="PDF">
                <FileDown class="w-4 h-4" /> {{ pdfLoading ? '...' : 'PDF' }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40" @click="emit('close')">
      <button>close</button>
    </form>

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
  </dialog>
</template>
