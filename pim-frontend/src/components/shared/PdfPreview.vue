<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue'
import { FileText, ExternalLink, Loader } from 'lucide-vue-next'

const props = defineProps({
  url: { type: String, required: true },
  title: { type: String, default: '' },
  maxHeight: { type: String, default: '24rem' },
})

const canvasRef = ref(null)
const loading = ref(true)
const error = ref(false)
const pageCount = ref(0)
let pdfDoc = null

async function renderPdf() {
  if (!props.url || !canvasRef.value) return

  loading.value = true
  error.value = false

  try {
    const pdfjsLib = await import('pdfjs-dist')

    // Configure worker — use bundled worker from pdfjs-dist
    if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
      const pdfjsWorker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url')
      pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker.default
    }

    const loadingTask = pdfjsLib.getDocument({
      url: props.url,
      disableAutoFetch: true,
      disableStream: true,
    })

    pdfDoc = await loadingTask.promise
    pageCount.value = pdfDoc.numPages

    const page = await pdfDoc.getPage(1)
    const canvas = canvasRef.value
    const ctx = canvas.getContext('2d')

    // Scale to fit container width while keeping aspect ratio
    const containerWidth = canvas.parentElement?.clientWidth || 400
    const viewport = page.getViewport({ scale: 1 })
    const scale = Math.min(containerWidth / viewport.width, 2) // max 2x for retina
    const scaledViewport = page.getViewport({ scale })

    canvas.width = scaledViewport.width
    canvas.height = scaledViewport.height
    canvas.style.width = `${scaledViewport.width / scale * Math.min(scale, 1.5)}px`
    canvas.style.height = 'auto'

    await page.render({ canvasContext: ctx, viewport: scaledViewport }).promise
  } catch (e) {
    console.warn('PDF preview failed:', e.message)
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => renderPdf())

watch(() => props.url, () => renderPdf())

onBeforeUnmount(() => {
  if (pdfDoc) {
    pdfDoc.destroy()
    pdfDoc = null
  }
})
</script>

<template>
  <div class="pdf-preview">
    <!-- Title + link -->
    <a :href="url" target="_blank" rel="noopener noreferrer"
       class="inline-flex items-center gap-1.5 text-sm font-medium hover:underline"
       :class="error ? 'text-[var(--color-accent,theme(colors.primary))]' : 'text-[var(--color-accent,theme(colors.primary))]'">
      <FileText class="w-4 h-4 shrink-0" />
      {{ title || url }}
      <ExternalLink class="w-3 h-3 shrink-0 opacity-50" />
    </a>

    <!-- Loading -->
    <div v-if="loading" class="mt-2 flex items-center gap-2 text-xs text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      <Loader class="w-4 h-4 animate-spin" />
      PDF wird geladen...
    </div>

    <!-- Canvas preview -->
    <div v-show="!loading && !error" class="mt-2 overflow-hidden rounded-lg border border-[var(--color-border,theme(colors.base-300))] bg-white"
         :style="{ maxHeight }">
      <a :href="url" target="_blank" rel="noopener noreferrer" class="block cursor-pointer">
        <canvas ref="canvasRef" class="block w-full" />
      </a>
    </div>

    <!-- Page count -->
    <p v-if="!loading && !error && pageCount > 0" class="mt-1 text-[11px] text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      {{ pageCount }} {{ pageCount === 1 ? 'Seite' : 'Seiten' }}
    </p>

    <!-- Error fallback -->
    <p v-if="error" class="mt-1 text-[11px] text-[var(--color-text-tertiary,theme(colors.base-content/50))]">
      Vorschau nicht verfügbar — klicken Sie den Link um das PDF zu öffnen
    </p>
  </div>
</template>
