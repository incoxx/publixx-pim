<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { FileText, ExternalLink, Loader, AlertCircle } from 'lucide-vue-next'
import pdfApi from '@/api/pdf'

const props = defineProps({
  url: { type: String, required: true },
  mediaId: { type: String, default: null },
  title: { type: String, default: '' },
  maxHeight: { type: String, default: '24rem' },
})

const loading = ref(true)
const error = ref(false)
const pageCount = ref(0)
const previewImageUrl = ref(null)
const pdfStatus = ref(null)

async function loadPreview() {
  loading.value = true
  error.value = false
  previewImageUrl.value = null
  pdfStatus.value = null
  pageCount.value = 0

  if (props.mediaId) {
    try {
      const { data } = await pdfApi.getByMedia(props.mediaId)
      const doc = data.data || data
      pdfStatus.value = doc.status
      pageCount.value = doc.page_count || 0

      if (doc.status === 'ready' && doc.id) {
        // Load page 1 image URL
        const pagesRes = await pdfApi.getPages(doc.id)
        const pages = pagesRes.data?.data?.pages || pagesRes.data?.pages || []
        if (pages.length > 0) {
          previewImageUrl.value = pages[0].image_url
        }
      }
    } catch (e) {
      // 404 = no PdfDocument exists for this media → show fallback
      if (e.response?.status !== 404) {
        console.warn('PdfPreview: Failed to load pipeline preview:', e.message)
      }
    }
  }

  loading.value = false
}

const showFallback = computed(() => !loading.value && !previewImageUrl.value)

onMounted(() => loadPreview())

watch(() => props.mediaId, () => loadPreview())
watch(() => props.url, () => {
  if (!props.mediaId) loadPreview()
})
</script>

<template>
  <div class="pdf-preview">
    <!-- Title + link -->
    <a v-if="title" :href="url" target="_blank" rel="noopener noreferrer"
       class="inline-flex items-center gap-1.5 text-sm font-medium hover:underline text-[var(--color-accent,theme(colors.primary))]">
      <FileText class="w-4 h-4 shrink-0" />
      {{ title }}
      <ExternalLink class="w-3 h-3 shrink-0 opacity-50" />
    </a>

    <!-- Loading -->
    <div v-if="loading" class="mt-2 flex items-center gap-2 text-xs text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      <Loader class="w-4 h-4 animate-spin" />
      PDF wird geladen...
    </div>

    <!-- Pipeline WebP preview image -->
    <div v-if="!loading && previewImageUrl" class="mt-2 overflow-hidden rounded-lg border border-[var(--color-border,theme(colors.base-300))] bg-white"
         :style="{ maxHeight }">
      <a :href="url" target="_blank" rel="noopener noreferrer" class="block cursor-pointer">
        <img :src="previewImageUrl" alt="PDF Vorschau" class="block w-full h-auto" loading="lazy" />
      </a>
    </div>

    <!-- Fallback: PDF icon with status info -->
    <div v-if="showFallback" class="mt-2 rounded-lg border border-[var(--color-border,theme(colors.base-300))] bg-[var(--color-bg,theme(colors.base-200))] p-4">
      <a :href="url" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center gap-2 text-center no-underline">
        <FileText class="w-8 h-8 text-[var(--color-text-tertiary,theme(colors.base-content/40))]" />
        <span v-if="pdfStatus === 'processing'" class="text-[11px] text-[var(--color-text-tertiary)]">
          <Loader class="w-3 h-3 animate-spin inline mr-1" />Wird verarbeitet...
        </span>
        <span v-else-if="pdfStatus === 'pending'" class="text-[11px] text-[var(--color-text-tertiary)]">
          Vorschau wird erstellt...
        </span>
        <span v-else-if="pdfStatus === 'error'" class="text-[11px] text-[var(--color-error,red)]">
          <AlertCircle class="w-3 h-3 inline mr-0.5" />Vorschau fehlgeschlagen
        </span>
        <span v-else class="text-[11px] text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
          PDF öffnen
        </span>
      </a>
    </div>

    <!-- Page count -->
    <p v-if="!loading && pageCount > 0" class="mt-1 text-[11px] text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      {{ pageCount }} {{ pageCount === 1 ? 'Seite' : 'Seiten' }}
    </p>
  </div>
</template>
