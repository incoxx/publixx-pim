<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { Search, FileText, Loader2 } from 'lucide-vue-next'
import pdfApi from '@/api/pdf'

const emit = defineEmits(['select-result'])

const { t } = useI18n()

const query = ref('')
const results = ref([])
const loading = ref(false)
const totalFound = ref(0)
const currentPage = ref(1)
const perPage = 20

function sanitizeSnippet(html) {
  if (!html) return ''
  // Only allow <mark> tags for search highlighting, strip everything else
  return html
    .replace(/<(?!\/?(mark)\b)[^>]*>/gi, '')
    .replace(/<\/(?!mark\b)[^>]*>/gi, '')
}

async function performSearch(append = false) {
  const q = query.value.trim()
  if (q.length < 2) {
    results.value = []
    totalFound.value = 0
    return
  }

  loading.value = true
  try {
    const { data } = await pdfApi.search({
      q,
      perPage,
      page: currentPage.value,
    })
    const hits = (data.hits || []).map(hit => ({
      ...hit,
      snippet: sanitizeSnippet(hit.snippet),
    }))
    results.value = append ? [...results.value, ...hits] : hits
    totalFound.value = data.found || 0
  } catch (e) {
    console.error('PDF search failed:', e)
    if (!append) {
      results.value = []
      totalFound.value = 0
    } else {
      currentPage.value-- // Rollback on pagination error
    }
  } finally {
    loading.value = false
  }
}

const debouncedSearch = useDebounceFn(() => {
  currentPage.value = 1
  performSearch()
}, 300)

watch(query, () => {
  debouncedSearch()
})

function loadMore() {
  if (results.value.length < totalFound.value && !loading.value) {
    currentPage.value++
    performSearch(true)
  }
}

function selectResult(hit) {
  emit('select-result', {
    pdfDocumentId: hit.pdf_document_id,
    mediaId: hit.media_id,
    pageNumber: hit.page_number,
    title: hit.title,
  })
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Suchfeld -->
    <div class="relative">
      <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
      <input
        v-model="query"
        type="text"
        :placeholder="t('pdf.searchPlaceholder', 'PDF-Inhalte durchsuchen…')"
        class="input input-bordered w-full pl-10 input-sm"
      />
      <Loader2
        v-if="loading"
        class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-base-content/40"
      />
    </div>

    <!-- Ergebnisanzahl -->
    <p v-if="query.trim().length >= 2 && !loading" class="text-xs text-base-content/50">
      {{ totalFound }} {{ t('pdf.resultsFound', 'Treffer') }}
    </p>

    <!-- Ergebnisliste -->
    <div v-if="results.length > 0" class="flex flex-col gap-1">
      <button
        v-for="(hit, index) in results"
        :key="`${hit.pdf_document_id}-${hit.page_number}`"
        class="flex items-start gap-3 p-3 rounded-lg hover:bg-base-200 transition-colors text-left w-full"
        @click="selectResult(hit)"
      >
        <FileText class="w-5 h-5 text-primary mt-0.5 shrink-0" />
        <div class="flex flex-col gap-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium truncate">{{ hit.title }}</span>
            <span class="badge badge-ghost badge-xs shrink-0">
              {{ t('pdf.page', 'Seite') }} {{ hit.page_number }}
            </span>
          </div>
          <p
            v-if="hit.snippet"
            class="text-xs text-base-content/60 line-clamp-2"
            v-html="hit.snippet"
          />
        </div>
      </button>
    </div>

    <!-- Leer-Zustand -->
    <div
      v-else-if="query.trim().length >= 2 && !loading && results.length === 0"
      class="flex flex-col items-center py-8 gap-2"
    >
      <Search class="w-8 h-8 text-base-content/15" />
      <p class="text-sm text-base-content/40">{{ t('pdf.noResults', 'Keine Treffer gefunden') }}</p>
    </div>

    <!-- Mehr laden -->
    <button
      v-if="results.length > 0 && results.length < totalFound"
      class="btn btn-ghost btn-sm mx-auto"
      :disabled="loading"
      @click="loadMore"
    >
      {{ t('pdf.loadMore', 'Mehr laden') }}
    </button>
  </div>
</template>
