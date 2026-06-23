<script setup>
import { watch, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { useCatalogStore } from '@/stores/catalog'
import { X, Download, Heart, Image, FileText, Info, Package, ChevronLeft, ChevronRight, FolderTree } from 'lucide-vue-next'
import { formatFileSize } from '@/utils/formatting'
import CatalogProductModal from '@/components/catalog/CatalogProductModal.vue'
import PdfStatusBadge from '@/components/assetCatalog/PdfStatusBadge.vue'
import PdfViewer from '@/components/assetCatalog/PdfViewer.vue'
import pdfApi from '@/api/pdf'

const props = defineProps({
  assetId: String,
  open: Boolean,
})

const emit = defineEmits(['close'])
const { t } = useI18n()
const store = useAssetCatalogStore()
const catalogStore = useCatalogStore()

const activeTab = ref('details')
const productModalOpen = ref(false)
const productModalId = ref(null)
const pdfDocumentId = ref(null)

const isPdf = computed(() => {
  const asset = store.currentAsset
  if (!asset) return false
  return asset.mime_type === 'application/pdf'
    || asset.file_name?.toLowerCase().endsWith('.pdf')
})

watch(() => props.assetId, (id) => {
  if (id) {
    store.fetchAsset(id)
    // Produkt-Verwendung direkt laden, damit der Zähler im Details-Tab sofort verfügbar ist
    store.fetchAssetProducts(id)
    activeTab.value = 'details'
    pdfDocumentId.value = null
  }
})

// PDF-Dokument laden wenn ein PDF-Asset angezeigt wird
let pdfFetchMediaId = null
watch(isPdf, async (val) => {
  if (val && store.currentAsset) {
    const mediaId = store.currentAsset.id
    pdfFetchMediaId = mediaId
    try {
      const { data } = await pdfApi.getByMedia(mediaId)
      // Guard: nur setzen wenn noch dasselbe Asset angezeigt wird
      if (pdfFetchMediaId === mediaId) {
        pdfDocumentId.value = data.id
      }
    } catch {
      if (pdfFetchMediaId === mediaId) {
        pdfDocumentId.value = null
      }
    }
  } else {
    pdfDocumentId.value = null
  }
}, { immediate: true })

const assetNodes = ref([])
const assetNodesLoading = ref(false)

watch(activeTab, (tab) => {
  if (tab === 'usedBy' && props.assetId) {
    store.fetchAssetProducts(props.assetId)
  }
  if (tab === 'categories' && props.assetId) {
    loadAssetNodes(props.assetId)
  }
})

async function loadAssetNodes(assetId) {
  assetNodesLoading.value = true
  try {
    const { default: assetCatalogApi } = await import('@/api/assetCatalog')
    const { data } = await assetCatalogApi.getAssetNodes(assetId, { lang: store.locale })
    assetNodes.value = data.data || data
  } catch {
    assetNodes.value = []
  } finally {
    assetNodesLoading.value = false
  }
}

function usageLabel(purpose) {
  const map = { print: t('assetCatalog.usagePrint'), web: t('assetCatalog.usageWeb'), both: t('assetCatalog.usageBoth') }
  return map[purpose] || purpose
}

function downloadAsset() {
  if (!store.currentAsset?.original_url) return
  const link = document.createElement('a')
  link.href = store.currentAsset.original_url
  link.download = store.currentAsset.file_name
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  link.remove()
}

function toggleWishlist() {
  if (store.currentAsset) {
    store.toggleWishlist(store.currentAsset.id)
  }
}

function openProduct(product) {
  productModalId.value = product.id
  productModalOpen.value = true
  // Ensure catalog store has theme settings for product modal
  if (!catalogStore._themeLoaded) {
    catalogStore.themeSettings = { ...catalogStore.themeSettings, ...store.themeSettings }
  }
}

function closeProductModal() {
  productModalOpen.value = false
  productModalId.value = null
}

function goToProductPage(page) {
  if (props.assetId) {
    store.fetchAssetProducts(props.assetId, page)
  }
}

const productPages = computed(() => {
  const meta = store.assetProductsMeta
  const pages = []
  for (let i = 1; i <= meta.last_page; i++) {
    if (i === 1 || i === meta.last_page || Math.abs(i - meta.current_page) <= 1) {
      pages.push(i)
    } else if (pages[pages.length - 1] !== '...') {
      pages.push('...')
    }
  }
  return pages
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        data-theme="pim-catalog"
        style="--color-base-100:#ffffff;--color-base-200:#f8fafc;--color-base-300:#e5e7eb;--color-base-content:#111827;--color-primary:#1B3A5C;--color-primary-content:#ffffff;--color-secondary:#2E75B6;--color-secondary-content:#ffffff;--color-accent:#0D9488;--color-accent-content:#ffffff;--color-neutral:#1f2937;--color-neutral-content:#f9fafb;--color-info:#2563EB;--color-info-content:#ffffff;--color-success:#059669;--color-success-content:#ffffff;--color-warning:#D97706;--color-warning-content:#ffffff;--color-error:#DC2626;--color-error-content:#ffffff;"
      >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>

        <div class="relative z-10 bg-base-100 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-3 md:p-4 border-b border-base-300 gap-2">
            <h2 class="text-sm md:text-lg font-semibold truncate min-w-0">
              {{ store.currentAsset?.title || store.currentAsset?.file_name || t('assetCatalog.assetDetail') }}
            </h2>
            <div class="flex items-center gap-1 md:gap-2 shrink-0">
              <button class="btn btn-ghost btn-sm btn-circle md:btn-square" @click="toggleWishlist">
                <Heart
                  class="w-4 h-4"
                  :class="store.currentAsset && store.isInWishlist(store.currentAsset.id) ? 'fill-error text-error' : ''"
                />
              </button>
              <button class="btn btn-primary btn-sm gap-1" @click="downloadAsset">
                <Download class="w-4 h-4" />
                <span class="hidden sm:inline">{{ t('assetCatalog.download') }}</span>
              </button>
              <button class="btn btn-ghost btn-sm btn-circle" @click="emit('close')">
                <X class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Tabs -->
          <div class="tabs tabs-bordered px-4 shrink-0">
            <button
              class="tab"
              :class="{ 'tab-active': activeTab === 'details' }"
              @click="activeTab = 'details'"
            >
              {{ t('assetCatalog.details') }}
            </button>
            <button
              v-if="isPdf"
              class="tab"
              :class="{ 'tab-active': activeTab === 'pdf' }"
              @click="activeTab = 'pdf'"
            >
              PDF
              <PdfStatusBadge v-if="store.currentAsset" :media-id="store.currentAsset.id" class="ml-1" />
            </button>
            <button
              class="tab"
              :class="{ 'tab-active': activeTab === 'usedBy' }"
              @click="activeTab = 'usedBy'"
            >
              {{ t('assetCatalog.usedBy') }}
              <span v-if="store.assetProductsMeta.total > 0" class="badge badge-xs badge-primary ml-1">
                {{ store.assetProductsMeta.total }}
              </span>
            </button>
            <button
              v-if="store.currentAsset?.hierarchy_nodes?.length"
              class="tab"
              :class="{ 'tab-active': activeTab === 'categories' }"
              @click="activeTab = 'categories'"
            >
              Kategorien
              <span class="badge badge-xs badge-secondary ml-1">
                {{ store.currentAsset.hierarchy_nodes.length }}
              </span>
            </button>
          </div>

          <!-- Loading -->
          <div v-if="store.assetLoading" class="flex-1 flex items-center justify-center py-24">
            <span class="loading loading-spinner loading-lg text-primary"></span>
          </div>

          <!-- Tab Content -->
          <div v-else-if="store.currentAsset" class="flex-1 overflow-y-auto">
            <!-- ═══ Details Tab ═══ -->
            <div v-if="activeTab === 'details'" class="grid grid-cols-1 md:grid-cols-2 gap-0">
              <!-- Left: Preview -->
              <div class="bg-base-200 flex items-center justify-center min-h-[300px] p-4">
                <img
                  v-if="(store.currentAsset.media_type === 'image' && store.currentAsset.preview_url) || store.currentAsset.pdf_preview_url"
                  :src="store.currentAsset.pdf_preview_url || store.currentAsset.preview_url"
                  :alt="store.currentAsset.title"
                  class="max-w-full max-h-[500px] object-contain rounded-lg shadow-lg"
                />
                <div v-else class="flex flex-col items-center gap-3 text-base-content/20">
                  <FileText v-if="store.currentAsset.media_type === 'document'" class="w-24 h-24" />
                  <Image v-else class="w-24 h-24" />
                  <span class="text-sm uppercase">{{ store.currentAsset.mime_type?.split('/')[1] }}</span>
                </div>
              </div>

              <!-- Right: Info + Metadata -->
              <div class="p-6 space-y-4">
                <!-- Verwendungsnachweis (kompakte Übersicht, springt in die Detail-Tabs) -->
                <div class="rounded-lg border border-base-300 bg-base-200/40 p-3">
                  <div class="flex items-center gap-2 mb-2">
                    <Package class="w-4 h-4 text-base-content/50" />
                    <span class="text-sm font-semibold">{{ t('assetCatalog.usageProof') }}</span>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button
                      class="btn btn-xs btn-outline gap-1"
                      :disabled="store.assetProductsMeta.total === 0"
                      @click="activeTab = 'usedBy'"
                    >
                      <Package class="w-3 h-3" />
                      {{ store.assetProductsMeta.total }} {{ t('assetCatalog.usageProducts') }}
                    </button>
                    <button
                      class="btn btn-xs btn-outline gap-1"
                      :disabled="!store.currentAsset.hierarchy_nodes?.length"
                      @click="activeTab = 'categories'"
                    >
                      <FolderTree class="w-3 h-3" />
                      {{ store.currentAsset.hierarchy_nodes?.length || 0 }} {{ t('assetCatalog.usageCategories') }}
                    </button>
                  </div>
                  <p
                    v-if="store.assetProductsMeta.total === 0 && !store.currentAsset.hierarchy_nodes?.length"
                    class="text-xs text-base-content/40 mt-2"
                  >
                    {{ t('assetCatalog.usageNone') }}
                  </p>
                </div>

                <!-- Basic info -->
                <div class="space-y-3">
                  <div>
                    <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.fileName') }}</span>
                    <p class="text-sm font-mono">{{ store.currentAsset.file_name }}</p>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.fileSize') }}</span>
                      <p class="text-sm">{{ formatFileSize(store.currentAsset.file_size) }}</p>
                    </div>
                    <div v-if="store.currentAsset.width && store.currentAsset.height">
                      <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.dimensions') }}</span>
                      <p class="text-sm">{{ store.currentAsset.width }} × {{ store.currentAsset.height }} px</p>
                    </div>
                  </div>
                  <div>
                    <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.usagePurpose') }}</span>
                    <p class="text-sm">
                      <span class="badge badge-sm badge-outline">{{ usageLabel(store.currentAsset.usage_purpose) }}</span>
                    </p>
                  </div>
                  <div v-if="store.currentAsset.description">
                    <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.description') }}</span>
                    <p class="text-sm text-base-content/70">{{ store.currentAsset.description }}</p>
                  </div>
                  <div v-if="store.currentAsset.folder_name">
                    <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.folders') }}</span>
                    <p class="text-sm">{{ store.currentAsset.folder_name }}</p>
                  </div>
                  <div v-if="store.currentAsset.hierarchy_nodes?.length">
                    <span class="text-xs font-medium text-base-content/50">Kategorien</span>
                    <div class="flex flex-wrap gap-1 mt-0.5">
                      <span
                        v-for="hn in store.currentAsset.hierarchy_nodes"
                        :key="hn.node_id"
                        class="badge badge-sm badge-secondary badge-outline"
                        :title="hn.hierarchy_name"
                      >
                        {{ hn.node_name }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Metadata from EAV -->
                <div v-if="store.currentAsset.metadata && store.currentAsset.metadata.length > 0">
                  <div class="flex items-center gap-2 mb-2">
                    <Info class="w-4 h-4 text-base-content/50" />
                    <span class="text-sm font-semibold">{{ t('assetCatalog.metadata') }}</span>
                  </div>
                  <div class="space-y-2">
                    <div
                      v-for="meta in store.currentAsset.metadata"
                      :key="meta.attribute_id"
                      class="flex justify-between items-start py-1.5 border-b border-base-200 last:border-0"
                    >
                      <span class="text-xs text-base-content/60">{{ meta.attribute_name }}</span>
                      <span class="text-xs font-medium text-right">
                        {{ meta.value }}{{ meta.unit ? ' ' + meta.unit : '' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ═══ PDF Tab ═══ -->
            <div v-if="activeTab === 'pdf'" class="p-4 md:p-6">
              <PdfViewer
                v-if="pdfDocumentId"
                :pdf-id="pdfDocumentId"
              />
              <div v-else class="flex flex-col items-center py-16 gap-2">
                <FileText class="w-12 h-12 text-base-content/15" />
                <p class="text-sm text-base-content/40">{{ t('pdf.notProcessed', 'PDF wird verarbeitet oder nicht verfügbar.') }}</p>
              </div>
            </div>

            <!-- ═══ Kategorien Tab ═══ -->
            <div v-if="activeTab === 'categories'" class="p-4 md:p-6">
              <div v-if="assetNodesLoading" class="flex justify-center py-12">
                <span class="loading loading-spinner loading-lg text-primary"></span>
              </div>
              <div v-else-if="assetNodes.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <FolderTree class="w-12 h-12 text-base-content/15 mb-3" />
                <p class="text-sm text-base-content/50">Keine Kategorie-Zuordnungen</p>
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="node in assetNodes"
                  :key="node.node_id"
                  class="bg-base-200 rounded-lg p-3"
                >
                  <div class="flex items-center gap-2 mb-1">
                    <FolderTree class="w-4 h-4 text-secondary shrink-0" />
                    <span class="text-sm font-semibold">{{ node.node_name }}</span>
                    <span class="badge badge-xs badge-outline">{{ node.hierarchy_name }}</span>
                  </div>
                  <div class="text-xs text-base-content/50 ml-6">
                    {{ node.path_string }}
                  </div>
                </div>
              </div>
            </div>

            <!-- ═══ Verwendet von Tab ═══ -->
            <div v-if="activeTab === 'usedBy'" class="p-4 md:p-6">
              <!-- Loading -->
              <div v-if="store.assetProductsLoading" class="flex justify-center py-12">
                <span class="loading loading-spinner loading-lg text-primary"></span>
              </div>

              <!-- Empty state -->
              <div v-else-if="store.assetProducts.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <Package class="w-12 h-12 text-base-content/15 mb-3" />
                <p class="text-sm text-base-content/50">{{ t('assetCatalog.noProducts') }}</p>
              </div>

              <!-- Product table -->
              <div v-else>
                <div class="overflow-x-auto">
                  <table class="table table-sm w-full">
                    <thead>
                      <tr>
                        <th class="w-16"></th>
                        <th>{{ t('assetCatalog.productName') }}</th>
                        <th>SKU</th>
                        <th class="hidden sm:table-cell">EAN</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="product in store.assetProducts"
                        :key="product.id"
                        class="hover cursor-pointer"
                        @click="openProduct(product)"
                      >
                        <td>
                          <div class="w-10 h-10 rounded bg-base-200 flex items-center justify-center overflow-hidden">
                            <img
                              v-if="product.image_url"
                              :src="product.image_url"
                              :alt="product.name"
                              class="w-full h-full object-contain"
                              loading="lazy"
                            />
                            <Package v-else class="w-5 h-5 text-base-content/15" />
                          </div>
                        </td>
                        <td class="font-medium">{{ product.name }}</td>
                        <td class="font-mono text-base-content/60 text-xs">{{ product.sku }}</td>
                        <td class="hidden sm:table-cell font-mono text-base-content/60 text-xs">{{ product.ean || '–' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination -->
                <div v-if="store.assetProductsMeta.last_page > 1" class="flex justify-center mt-4">
                  <div class="join">
                    <button
                      class="join-item btn btn-sm"
                      :disabled="store.assetProductsMeta.current_page <= 1"
                      @click="goToProductPage(store.assetProductsMeta.current_page - 1)"
                    >
                      <ChevronLeft class="w-4 h-4" />
                    </button>
                    <template v-for="page in productPages" :key="page">
                      <button
                        v-if="page !== '...'"
                        class="join-item btn btn-sm"
                        :class="{ 'btn-active': page === store.assetProductsMeta.current_page }"
                        @click="goToProductPage(page)"
                      >
                        {{ page }}
                      </button>
                      <button v-else class="join-item btn btn-sm btn-disabled">...</button>
                    </template>
                    <button
                      class="join-item btn btn-sm"
                      :disabled="store.assetProductsMeta.current_page >= store.assetProductsMeta.last_page"
                      @click="goToProductPage(store.assetProductsMeta.current_page + 1)"
                    >
                      <ChevronRight class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Product detail modal (opened from "Verwendet von" tab) -->
  <CatalogProductModal
    :product-id="productModalId"
    :open="productModalOpen"
    @close="closeProductModal"
  />
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity 0.3s ease; }
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }
</style>
