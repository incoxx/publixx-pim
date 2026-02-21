<script setup>
import { onMounted, ref } from 'vue'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import AssetCatalogToolbar from '@/components/assetCatalog/AssetCatalogToolbar.vue'
import AssetCatalogGrid from '@/components/assetCatalog/AssetCatalogGrid.vue'
import AssetCatalogPagination from '@/components/assetCatalog/AssetCatalogPagination.vue'
import AssetCatalogDetailModal from '@/components/assetCatalog/AssetCatalogDetailModal.vue'
import { Image } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const store = useAssetCatalogStore()

const modalOpen = ref(false)
const modalAssetId = ref(null)

function openDetail(asset) {
  modalAssetId.value = asset.id
  modalOpen.value = true
}

function closeDetail() {
  modalOpen.value = false
  modalAssetId.value = null
}

onMounted(() => {
  store.fetchAssets()
})
</script>

<template>
  <div>
    <AssetCatalogToolbar />

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

    <AssetCatalogDetailModal
      :asset-id="modalAssetId"
      :open="modalOpen"
      @close="closeDetail"
    />
  </div>
</template>
