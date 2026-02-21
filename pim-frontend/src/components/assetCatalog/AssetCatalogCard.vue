<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { Heart, Eye, Image, FileText } from 'lucide-vue-next'
import { formatFileSize } from '@/utils/formatting'

const props = defineProps({
  asset: { type: Object, required: true },
  index: { type: Number, default: 0 },
})

const emit = defineEmits(['view-detail'])

const { t } = useI18n()
const store = useAssetCatalogStore()

const inWishlist = computed(() => store.isInWishlist(props.asset.id))
const isImage = computed(() => props.asset.media_type === 'image')

function toggleWishlist(e) {
  e.stopPropagation()
  store.toggleWishlist(props.asset.id)
}

const staggerDelay = computed(() => `${Math.min(props.index * 40, 400)}ms`)
</script>

<template>
  <div
    class="card bg-base-100 shadow-sm border border-base-300 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer group asset-card-enter"
    :style="{ animationDelay: staggerDelay }"
    @click="emit('view-detail', asset)"
  >
    <!-- Image / Preview -->
    <figure class="relative overflow-hidden aspect-square bg-base-200">
      <img
        v-if="isImage && asset.thumb_url"
        :src="asset.thumb_url"
        :alt="asset.title || asset.file_name"
        class="object-contain w-full h-full p-2 group-hover:scale-105 transition-transform duration-500"
        loading="lazy"
      />
      <div v-else class="flex flex-col items-center justify-center w-full h-full gap-2">
        <FileText v-if="asset.media_type === 'document'" class="w-12 h-12 text-base-content/15" />
        <Image v-else class="w-12 h-12 text-base-content/15" />
        <span class="text-[10px] text-base-content/30 uppercase">{{ asset.mime_type?.split('/')[1] }}</span>
      </div>

      <!-- Usage badge -->
      <span
        v-if="asset.usage_purpose && asset.usage_purpose !== 'both'"
        class="absolute top-2 left-2 badge badge-xs"
        :class="asset.usage_purpose === 'print' ? 'badge-info' : 'badge-success'"
      >
        {{ asset.usage_purpose }}
      </span>

      <!-- Wishlist button -->
      <button
        class="btn btn-circle btn-sm absolute top-2 right-2 bg-base-100/80 backdrop-blur-sm border-0 hover:bg-base-100 shadow-sm"
        :title="inWishlist ? t('assetCatalog.removeFromWishlist') : t('assetCatalog.addToWishlist')"
        @click="toggleWishlist($event)"
      >
        <Heart
          class="w-4 h-4 transition-all duration-300"
          :class="inWishlist ? 'fill-error text-error scale-110' : 'text-base-content/40'"
        />
      </button>

      <!-- View overlay -->
      <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-4">
        <span class="btn btn-sm btn-primary gap-1 shadow-lg">
          <Eye class="w-3.5 h-3.5" />
          {{ t('assetCatalog.assetDetail') }}
        </span>
      </div>
    </figure>

    <div class="card-body p-3 gap-0.5">
      <h3 class="text-xs font-semibold line-clamp-1">{{ asset.title || asset.file_name }}</h3>
      <p class="text-[10px] text-base-content/50 truncate">{{ asset.file_name }}</p>
      <div class="flex items-center justify-between mt-1">
        <span class="text-[10px] text-base-content/40">{{ formatFileSize(asset.file_size) }}</span>
        <span v-if="asset.width && asset.height" class="text-[10px] text-base-content/40">
          {{ asset.width }}×{{ asset.height }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes assetCardEnter {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
.asset-card-enter {
  animation: assetCardEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
}
</style>
