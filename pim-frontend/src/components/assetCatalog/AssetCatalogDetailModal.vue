<script setup>
import { watch, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetCatalogStore } from '@/stores/assetCatalog'
import { X, Download, Heart, Image, FileText, Info } from 'lucide-vue-next'

const props = defineProps({
  assetId: String,
  open: Boolean,
})

const emit = defineEmits(['close'])
const { t } = useI18n()
const store = useAssetCatalogStore()

watch(() => props.assetId, (id) => {
  if (id) store.fetchAsset(id)
})

function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
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
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>

        <div class="relative bg-base-100 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-base-300">
            <h2 class="text-lg font-semibold truncate">
              {{ store.currentAsset?.title || store.currentAsset?.file_name || t('assetCatalog.assetDetail') }}
            </h2>
            <div class="flex items-center gap-2">
              <button class="btn btn-ghost btn-sm" @click="toggleWishlist">
                <Heart
                  class="w-4 h-4"
                  :class="store.currentAsset && store.isInWishlist(store.currentAsset.id) ? 'fill-error text-error' : ''"
                />
              </button>
              <button class="btn btn-primary btn-sm gap-1" @click="downloadAsset">
                <Download class="w-4 h-4" />
                {{ t('assetCatalog.download') }}
              </button>
              <button class="btn btn-ghost btn-sm btn-circle" @click="emit('close')">
                <X class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="store.assetLoading" class="flex-1 flex items-center justify-center py-24">
            <span class="loading loading-spinner loading-lg text-primary"></span>
          </div>

          <!-- Content -->
          <div v-else-if="store.currentAsset" class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
              <!-- Left: Preview -->
              <div class="bg-base-200 flex items-center justify-center min-h-[300px] p-4">
                <img
                  v-if="store.currentAsset.media_type === 'image' && store.currentAsset.preview_url"
                  :src="store.currentAsset.preview_url"
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
                    <span class="text-xs font-medium text-base-content/50">{{ t('catalog.description') }}</span>
                    <p class="text-sm text-base-content/70">{{ store.currentAsset.description }}</p>
                  </div>
                  <div v-if="store.currentAsset.folder_name">
                    <span class="text-xs font-medium text-base-content/50">{{ t('assetCatalog.folders') }}</span>
                    <p class="text-sm">{{ store.currentAsset.folder_name }}</p>
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
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity 0.3s ease; }
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }
</style>
