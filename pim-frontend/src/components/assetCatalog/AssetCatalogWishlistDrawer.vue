<script setup>
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { Heart, Trash2, X, Image, Download } from 'lucide-vue-next'

const { t } = useI18n()
const store = useAssetCatalogStore()
const wishlistOpen = inject('wishlistOpen')

const wishlistAssets = computed(() => {
  return store.assets.filter((a) => store.isInWishlist(a.id))
})

const unloadedIds = computed(() => {
  const loadedIds = new Set(store.assets.map((a) => a.id))
  return store.wishlistIds.filter((id) => !loadedIds.has(id))
})

function closeDrawer() {
  wishlistOpen.value = false
}

function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}

async function downloadAll() {
  await store.downloadWishlist()
}
</script>

<template>
  <div class="bg-base-100 w-80 min-h-full border-l border-base-300 flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-base-300 flex items-center justify-between">
      <h2 class="font-semibold text-sm flex items-center gap-2">
        <Heart class="w-4 h-4 text-error" />
        {{ t('assetCatalog.wishlist') }}
        <span v-if="store.wishlistCount > 0" class="badge badge-sm badge-ghost">
          {{ store.wishlistCount }}
        </span>
      </h2>
      <button class="btn btn-ghost btn-sm btn-circle" @click="closeDrawer">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Items -->
    <div class="flex-1 overflow-y-auto">
      <div
        v-if="store.wishlistCount === 0"
        class="flex flex-col items-center justify-center py-16 px-6 text-center"
      >
        <Heart class="w-10 h-10 text-base-content/15 mb-4" />
        <p class="text-sm font-medium text-base-content/50 mb-1">{{ t('assetCatalog.wishlistEmpty') }}</p>
        <p class="text-xs text-base-content/30">{{ t('assetCatalog.wishlistEmptyHint') }}</p>
      </div>

      <div v-else class="p-2 space-y-2">
        <div
          v-for="asset in wishlistAssets"
          :key="asset.id"
          class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200 transition-colors"
        >
          <div class="w-12 h-12 flex-none rounded-lg bg-base-200 overflow-hidden">
            <img
              v-if="asset.thumb_url && asset.media_type === 'image'"
              :src="asset.thumb_url"
              :alt="asset.title"
              class="object-cover w-full h-full"
            />
            <div v-else class="flex items-center justify-center w-full h-full">
              <Image class="w-5 h-5 text-base-content/15" />
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-medium line-clamp-1">{{ asset.title || asset.file_name }}</p>
            <p class="text-[10px] text-base-content/50">{{ formatFileSize(asset.file_size) }}</p>
          </div>
          <button
            class="btn btn-ghost btn-xs btn-circle flex-none text-base-content/30 hover:text-error"
            @click="store.toggleWishlist(asset.id)"
          >
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>

        <div
          v-if="unloadedIds.length > 0"
          class="text-center text-xs text-base-content/40 py-2"
        >
          + {{ unloadedIds.length }} weitere Assets
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div v-if="store.wishlistCount > 0" class="p-3 border-t border-base-300 space-y-2">
      <button
        class="btn btn-primary btn-sm w-full gap-1"
        @click="downloadAll"
      >
        <Download class="w-3.5 h-3.5" />
        {{ t('assetCatalog.downloadZip') }}
      </button>
      <button
        class="btn btn-outline btn-error btn-sm w-full gap-1"
        @click="store.clearWishlist()"
      >
        <Trash2 class="w-3.5 h-3.5" />
        {{ t('assetCatalog.clearWishlist') }}
      </button>
    </div>
  </div>
</template>
