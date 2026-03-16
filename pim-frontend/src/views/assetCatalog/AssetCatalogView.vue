<script setup>
import { onMounted, ref } from 'vue'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import AssetCatalogToolbar from '@/components/assetCatalog/AssetCatalogToolbar.vue'
import AssetCatalogGrid from '@/components/assetCatalog/AssetCatalogGrid.vue'
import AssetCatalogPagination from '@/components/assetCatalog/AssetCatalogPagination.vue'
import AssetCatalogDetailModal from '@/components/assetCatalog/AssetCatalogDetailModal.vue'
import PdfSearch from '@/components/assetCatalog/PdfSearch.vue'
import PdfViewer from '@/components/assetCatalog/PdfViewer.vue'
import { Image, FileText, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const store = useAssetCatalogStore()

const modalOpen = ref(false)
const modalAssetId = ref(null)
const searchMode = ref('metadata') // 'metadata' | 'content'
const pdfViewerDocId = ref(null)
const pdfViewerPage = ref(1)

function openDetail(asset) {
  modalAssetId.value = asset.id
  modalOpen.value = true
}

function closeDetail() {
  modalOpen.value = false
  modalAssetId.value = null
}

function onPdfSearchResult(result) {
  pdfViewerDocId.value = result.pdfDocumentId
  pdfViewerPage.value = result.pageNumber
}

function closePdfViewer() {
  pdfViewerDocId.value = null
  pdfViewerPage.value = 1
}

onMounted(() => {
  store.fetchAssets()
})
</script>

<template>
  <div>
    <AssetCatalogToolbar>
      <template #search-toggle>
        <div class="join">
          <button
            class="join-item btn btn-xs"
            :class="searchMode === 'metadata' ? 'btn-primary' : 'btn-ghost'"
            @click="searchMode = 'metadata'"
          >
            <Image class="w-3 h-3 mr-1" />
            {{ t('assetCatalog.searchMetadata', 'Metadaten') }}
          </button>
          <button
            class="join-item btn btn-xs"
            :class="searchMode === 'content' ? 'btn-primary' : 'btn-ghost'"
            @click="searchMode = 'content'"
          >
            <FileText class="w-3 h-3 mr-1" />
            {{ t('assetCatalog.searchContent', 'Inhalte') }}
          </button>
        </div>
      </template>
    </AssetCatalogToolbar>

    <!-- PDF Content Search Mode -->
    <template v-if="searchMode === 'content'">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-4">
        <!-- Left: Search -->
        <div class="bg-base-100 rounded-xl border border-base-300 p-4">
          <PdfSearch @select-result="onPdfSearchResult" />
        </div>

        <!-- Right: PDF Viewer (wenn Ergebnis ausgewählt) -->
        <div v-if="pdfViewerDocId" class="bg-base-100 rounded-xl border border-base-300 p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold">PDF-Vorschau</h3>
            <button class="btn btn-ghost btn-xs" @click="closePdfViewer"><X class="w-4 h-4" /></button>
          </div>
          <PdfViewer :pdf-id="pdfViewerDocId" :initial-page="pdfViewerPage" />
        </div>
        <div v-else class="hidden lg:flex flex-col items-center justify-center py-24 gap-3 text-base-content/30">
          <FileText class="w-16 h-16" />
          <p class="text-sm">{{ t('pdf.selectResult', 'Wähle ein Suchergebnis, um das PDF anzuzeigen.') }}</p>
        </div>
      </div>
    </template>

    <!-- Standard Metadata Search Mode -->
    <template v-else>
      <!-- Loading -->
      <div v-if="store.loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mt-4">
        <div v-for="i in 12" :key="i" class="animate-pulse">
          <div class="bg-base-300 rounded-xl aspect-square"></div>
          <div class="mt-2 h-3 bg-base-300 rounded w-3/4"></div>
          <div class="mt-1 h-2 bg-base-300 rounded w-1/2"></div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="store.isEmpty" class="flex flex-col items-center justify-center py-24 text-center">
        <Image class="w-16 h-16 text-base-content/15 mb-4" />
        <h3 class="text-lg font-semibold text-base-content/50 mb-1">{{ t('assetCatalog.noResults') }}</h3>
        <p class="text-sm text-base-content/30">{{ t('assetCatalog.noResultsHint') }}</p>
      </div>

      <!-- Grid -->
      <template v-else>
        <AssetCatalogGrid :assets="store.assets" @view-detail="openDetail" />
      </template>

      <AssetCatalogPagination />
    </template>

    <AssetCatalogDetailModal
      :asset-id="modalAssetId"
      :open="modalOpen"
      @close="closeDetail"
    />
  </div>
</template>
