<script setup>
import { ref, computed } from 'vue'
import { Package, ChevronLeft, ChevronRight, FileText } from 'lucide-vue-next'
import PdfPreview from '@/components/shared/PdfPreview.vue'

const props = defineProps({
  media: { type: Array, default: () => [] },
})

const selectedIndex = ref(0)

function isPdf(m) {
  return m.media_type === 'document' && (m.mime_type || '').includes('pdf')
}

const galleryItems = computed(() =>
  props.media.filter((m) => m.media_type === 'image' || !m.media_type || isPdf(m))
)
const current = computed(() => galleryItems.value[selectedIndex.value])

function prev() {
  selectedIndex.value = selectedIndex.value > 0 ? selectedIndex.value - 1 : galleryItems.value.length - 1
}

function next() {
  selectedIndex.value = selectedIndex.value < galleryItems.value.length - 1 ? selectedIndex.value + 1 : 0
}
</script>

<template>
  <div>
    <!-- Main display -->
    <div class="relative aspect-square bg-base-200 rounded-xl overflow-hidden mb-3">
      <Transition name="img-fade" mode="out-in">
        <!-- PDF -->
        <div v-if="current && isPdf(current)" :key="current.url + '-pdf'" class="w-full h-full flex items-center justify-center p-4">
          <PdfPreview :url="current.url" :title="current.file_name || current.alt || 'PDF'" max-height="100%" />
        </div>
        <!-- Image -->
        <img
          v-else-if="current"
          :key="current.url"
          :src="current.url"
          :alt="current.alt || ''"
          class="object-contain w-full h-full p-4"
        />
        <div v-else class="flex items-center justify-center w-full h-full">
          <Package class="w-16 h-16 text-base-content/10" />
        </div>
      </Transition>

      <!-- Nav arrows -->
      <template v-if="galleryItems.length > 1">
        <button
          class="btn btn-circle btn-sm btn-ghost bg-base-100/70 backdrop-blur-sm absolute left-2 top-1/2 -translate-y-1/2"
          @click="prev"
        >
          <ChevronLeft class="w-4 h-4" />
        </button>
        <button
          class="btn btn-circle btn-sm btn-ghost bg-base-100/70 backdrop-blur-sm absolute right-2 top-1/2 -translate-y-1/2"
          @click="next"
        >
          <ChevronRight class="w-4 h-4" />
        </button>
      </template>
    </div>

    <!-- Thumbnails -->
    <div v-if="galleryItems.length > 1" class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="(item, idx) in galleryItems"
        :key="item.url"
        class="flex-none w-16 h-16 rounded-lg overflow-hidden border-2 transition-all duration-200"
        :class="idx === selectedIndex ? 'border-primary shadow-sm' : 'border-transparent opacity-60 hover:opacity-100'"
        @click="selectedIndex = idx"
      >
        <!-- PDF thumbnail -->
        <div v-if="isPdf(item)" class="flex items-center justify-center w-full h-full bg-base-200">
          <FileText class="w-6 h-6 text-error/60" />
        </div>
        <!-- Image thumbnail -->
        <img v-else :src="item.url" :alt="item.alt || ''" class="object-contain w-full h-full p-1" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.img-fade-enter-active,
.img-fade-leave-active {
  transition: opacity 0.2s ease;
}
.img-fade-enter-from,
.img-fade-leave-to {
  opacity: 0;
}
</style>
