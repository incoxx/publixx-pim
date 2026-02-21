<script setup>
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import AssetCatalogCard from './AssetCatalogCard.vue'

defineProps({
  assets: { type: Array, required: true },
})

const emit = defineEmits(['view-detail'])
const store = useAssetCatalogStore()
</script>

<template>
  <!-- Grid mode -->
  <div
    v-if="store.viewMode === 'grid'"
    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
  >
    <AssetCatalogCard
      v-for="(asset, index) in assets"
      :key="asset.id"
      :asset="asset"
      :index="index"
      @view-detail="emit('view-detail', asset)"
    />
  </div>

  <!-- List mode -->
  <div v-else class="space-y-2">
    <div
      v-for="asset in assets"
      :key="asset.id"
      class="flex items-center gap-4 p-3 bg-base-100 rounded-xl border border-base-300 hover:shadow-md transition-shadow cursor-pointer"
      @click="emit('view-detail', asset)"
    >
      <div class="w-16 h-16 flex-none rounded-lg bg-base-200 overflow-hidden">
        <img
          v-if="asset.thumb_url"
          :src="asset.thumb_url"
          :alt="asset.title"
          class="w-full h-full object-cover"
          loading="lazy"
        />
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium truncate">{{ asset.title || asset.file_name }}</p>
        <p class="text-xs text-base-content/50">{{ asset.file_name }}</p>
        <div class="flex items-center gap-2 mt-1">
          <span class="badge badge-xs badge-ghost">{{ asset.media_type }}</span>
          <span class="badge badge-xs badge-outline">{{ asset.usage_purpose }}</span>
          <span v-if="asset.folder_name" class="text-xs text-base-content/40">{{ asset.folder_name }}</span>
        </div>
      </div>
      <div class="text-xs text-base-content/40 text-right flex-none">
        <p>{{ formatFileSize(asset.file_size) }}</p>
        <p v-if="asset.width && asset.height">{{ asset.width }} × {{ asset.height }}</p>
      </div>
    </div>
  </div>
</template>

<script>
function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}

export default {
  methods: { formatFileSize },
}
</script>
