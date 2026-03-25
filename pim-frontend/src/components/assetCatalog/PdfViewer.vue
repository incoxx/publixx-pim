<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIntersectionObserver } from '@vueuse/core'
import { Download, ChevronLeft, ChevronRight, Loader2, AlertCircle } from 'lucide-vue-next'
import pdfApi from '@/api/pdf'

const props = defineProps({
  pdfId: { type: String, required: true },
  initialPage: {
    type: Number,
    default: 1,
    validator: (val) => Number.isInteger(val) && val >= 1,
  },
})

const { t } = useI18n()

const document = ref(null)
const pages = ref([])
const status = ref('loading')
const errorMessage = ref('')
const currentPage = ref(props.initialPage)
const loadedPages = ref(new Set())
const pageRefs = ref([])
const scrollContainer = ref(null)
const observerCleanups = ref([])

let pollInterval = null
let pollErrorCount = 0
const MAX_POLL_ERRORS = 5

async function loadDocument() {
  try {
    const { data } = await pdfApi.getDocument(props.pdfId)
    document.value = data
    status.value = data.status

    if (data.status === 'ready') {
      await loadPages()
    } else if (data.status === 'processing' || data.status === 'pending') {
      startPolling()
    } else if (data.status === 'error') {
      errorMessage.value = data.error_message || t('pdf.processingError', 'Verarbeitung fehlgeschlagen')
    }
  } catch (e) {
    status.value = 'error'
    errorMessage.value = e.response?.data?.detail || t('pdf.loadError', 'PDF konnte nicht geladen werden')
  }
}

async function loadPages() {
  try {
    const { data } = await pdfApi.getPages(props.pdfId)
    pages.value = data.pages || []
    if (pages.value.length > 0) {
      loadedPages.value.add(1)
    }
    await nextTick()
    setupLazyLoading()
    scrollToPage(props.initialPage)
  } catch (e) {
    console.error('Failed to load pages:', e)
  }
}

function setupLazyLoading() {
  // Cleanup previous observers
  cleanupObservers()

  pageRefs.value.forEach((el, index) => {
    if (!el || index === 0) return
    const pageNumber = index + 1
    const { stop } = useIntersectionObserver(el, ([{ isIntersecting }]) => {
      if (isIntersecting) {
        loadedPages.value.add(pageNumber)
        stop()
      }
    }, { rootMargin: '200px' })
    observerCleanups.value.push(stop)
  })
}

function cleanupObservers() {
  observerCleanups.value.forEach(stop => stop())
  observerCleanups.value = []
}

function scrollToPage(pageNumber) {
  if (pageNumber <= 1) return
  loadedPages.value.add(pageNumber)
  nextTick(() => {
    const el = pageRefs.value[pageNumber - 1]
    const container = scrollContainer.value
    if (el && container) {
      // Innerhalb des Scroll-Containers scrollen
      container.scrollTo({
        top: el.offsetTop - container.offsetTop,
        behavior: 'smooth',
      })
    }
  })
}

function startPolling() {
  if (pollInterval) return
  pollErrorCount = 0
  pollInterval = setInterval(async () => {
    try {
      const { data } = await pdfApi.getStatus(props.pdfId)
      status.value = data.status
      pollErrorCount = 0
      if (document.value) {
        document.value.page_count = data.page_count
      }
      if (data.status === 'ready') {
        stopPolling()
        await loadPages()
      } else if (data.status === 'error') {
        stopPolling()
        errorMessage.value = data.error_message || t('pdf.processingError', 'Verarbeitung fehlgeschlagen')
      }
    } catch (e) {
      pollErrorCount++
      if (pollErrorCount >= MAX_POLL_ERRORS) {
        stopPolling()
        status.value = 'error'
        errorMessage.value = t('pdf.pollingError', 'Statusabfrage fehlgeschlagen')
      }
    }
  }, 3000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

function goToPage(n) {
  if (n >= 1 && n <= pages.value.length) {
    currentPage.value = n
    scrollToPage(n)
  }
}

const pageCountDisplay = computed(() => {
  if (status.value === 'ready' && document.value?.page_count) {
    return document.value.page_count
  }
  return pages.value.length || 0
})

onMounted(() => {
  loadDocument()
})

onUnmounted(() => {
  stopPolling()
  cleanupObservers()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Toolbar -->
    <div class="flex items-center justify-between bg-base-200 rounded-lg px-4 py-2">
      <div class="flex items-center gap-2 text-sm">
        <span v-if="document" class="font-medium truncate max-w-xs">
          {{ document.title }}
        </span>
      </div>

      <div class="flex items-center gap-2">
        <!-- Seitennavigation -->
        <template v-if="status === 'ready' && pages.length > 1">
          <button
            class="btn btn-ghost btn-xs"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="text-sm tabular-nums">
            {{ currentPage }} / {{ pageCountDisplay }}
          </span>
          <button
            class="btn btn-ghost btn-xs"
            :disabled="currentPage >= pageCountDisplay"
            @click="goToPage(currentPage + 1)"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </template>

        <!-- Download -->
        <a
          v-if="document?.original_url"
          :href="document.original_url"
          target="_blank"
          class="btn btn-ghost btn-xs gap-1"
        >
          <Download class="w-4 h-4" />
          {{ t('pdf.download', 'Download') }}
        </a>
      </div>
    </div>

    <!-- Processing state -->
    <div
      v-if="status === 'loading' || status === 'pending' || status === 'processing'"
      class="flex flex-col items-center justify-center py-16 gap-4"
    >
      <Loader2 class="w-10 h-10 animate-spin text-primary" />
      <p class="text-sm text-base-content/60">
        {{ status === 'loading'
          ? t('pdf.loading', 'Lade PDF…')
          : t('pdf.processingWait', 'PDF wird verarbeitet. Bitte warten…')
        }}
      </p>
      <p v-if="document?.page_count" class="text-xs text-base-content/40">
        {{ document.page_count }} {{ t('pdf.pages', 'Seiten') }}
      </p>
    </div>

    <!-- Error state -->
    <div
      v-else-if="status === 'error'"
      class="flex flex-col items-center justify-center py-16 gap-4"
    >
      <AlertCircle class="w-10 h-10 text-error" />
      <p class="text-sm text-error">{{ errorMessage }}</p>
    </div>

    <!-- Pages (scrollbarer Container) -->
    <div v-else-if="status === 'ready'" ref="scrollContainer" class="overflow-y-auto flex flex-col gap-2" style="max-height: calc(100vh - 180px);">
      <div
        v-for="(page, index) in pages"
        :key="page.page_number"
        :ref="el => { if (el) pageRefs[index] = el }"
        class="relative bg-white rounded-lg shadow-sm border border-base-300 overflow-hidden shrink-0"
      >
        <!-- Seitennummer -->
        <div class="absolute top-2 right-2 badge badge-ghost badge-xs z-10">
          {{ page.page_number }}
        </div>

        <!-- Bild: geladen -->
        <img
          v-if="loadedPages.has(page.page_number)"
          :src="page.image_url"
          :alt="`${t('pdf.page', 'Seite')} ${page.page_number}`"
          class="w-full h-auto"
          loading="lazy"
        />

        <!-- Placeholder -->
        <div
          v-else
          class="w-full aspect-[210/297] bg-base-200 flex items-center justify-center"
        >
          <Loader2 class="w-6 h-6 animate-spin text-base-content/20" />
        </div>
      </div>
    </div>
  </div>
</template>
