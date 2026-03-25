<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { Search, FileText, Loader2, ChevronDown, ChevronRight, BookOpen, Package, Globe, Calendar, HardDrive } from 'lucide-vue-next'
import pdfApi from '@/api/pdf'

const emit = defineEmits(['select-result'])

const { t } = useI18n()

const query = ref('')
const groups = ref([])
const loading = ref(false)
const totalFound = ref(0)
const currentPage = ref(1)
const lastPageDocCount = ref(0)
const perPage = 20
const expandedGroups = ref(new Set())

const hasMore = computed(() => lastPageDocCount.value >= perPage)

function sanitizeSnippet(html) {
  if (!html) return ''
  return html.replace(/<[^>]*>/g, (tag) => {
    if (/^<mark>$/i.test(tag)) return tag
    if (/^<\/mark>$/i.test(tag)) return tag
    return ''
  })
}

function toggleGroup(pdfDocumentId) {
  if (expandedGroups.value.has(pdfDocumentId)) {
    expandedGroups.value.delete(pdfDocumentId)
  } else {
    expandedGroups.value.add(pdfDocumentId)
  }
}

function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function performSearch(append = false) {
  const q = query.value.trim()
  if (q.length < 2) {
    groups.value = []
    totalFound.value = 0
    lastPageDocCount.value = 0
    return
  }

  loading.value = true
  try {
    const { data } = await pdfApi.search({
      q,
      perPage,
      page: currentPage.value,
    })
    const newGroups = (data.groups || []).map(group => ({
      ...group,
      hits: (group.hits || []).map(hit => ({
        ...hit,
        snippet: sanitizeSnippet(hit.snippet),
      })),
    }))
    groups.value = append ? [...groups.value, ...newGroups] : newGroups
    totalFound.value = data.found || 0
    lastPageDocCount.value = newGroups.length

    if (!append && newGroups.length > 0) {
      expandedGroups.value = new Set([newGroups[0].pdf_document_id])
    }
  } catch (e) {
    console.error('PDF search failed:', e)
    if (!append) {
      groups.value = []
      totalFound.value = 0
      lastPageDocCount.value = 0
    } else {
      currentPage.value--
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
  if (hasMore.value && !loading.value) {
    currentPage.value++
    performSearch(true)
  }
}

function selectResult(group, hit) {
  emit('select-result', {
    pdfDocumentId: group.pdf_document_id,
    mediaId: group.media_id,
    pageNumber: hit.page_number,
    title: group.title,
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
        aria-label="PDF-Inhalte durchsuchen"
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
      {{ t('pdf.inDocuments', 'in') }} {{ groups.length }} {{ t('pdf.documents', 'Dokumenten') }}
    </p>

    <!-- Gruppierte Ergebnisliste -->
    <div v-if="groups.length > 0" class="flex flex-col gap-2">
      <div
        v-for="group in groups"
        :key="group.pdf_document_id"
        class="border border-base-300 rounded-lg overflow-hidden"
      >
        <!-- Dokument-Header -->
        <button
          class="flex items-center gap-3 p-3 w-full text-left hover:bg-base-200 transition-colors"
          @click="toggleGroup(group.pdf_document_id)"
        >
          <component
            :is="expandedGroups.has(group.pdf_document_id) ? ChevronDown : ChevronRight"
            class="w-4 h-4 text-base-content/40 shrink-0"
          />
          <BookOpen class="w-5 h-5 text-primary shrink-0" />
          <div class="flex flex-col gap-0.5 min-w-0 flex-1">
            <span class="text-sm font-medium truncate">{{ group.title || group.file_name }}</span>
            <span v-if="group.title && group.file_name" class="text-xs text-base-content/40 truncate">
              {{ group.file_name }}
            </span>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <span class="badge badge-primary badge-xs">
              {{ group.total_hits }} {{ t('pdf.hits', 'Treffer') }}
            </span>
            <span v-if="group.page_count" class="badge badge-ghost badge-xs">
              {{ group.page_count }} {{ t('pdf.pages', 'Seiten') }}
            </span>
          </div>
        </button>

        <!-- Aufklappbarer Bereich: Metadaten + Produkte + Snippets -->
        <div v-if="expandedGroups.has(group.pdf_document_id)" class="border-t border-base-300">

          <!-- Metadaten + Produkt-Referenzen -->
          <div v-if="group.metadata || (group.products && group.products.length > 0)" class="px-4 py-2.5 bg-base-200/30 border-b border-base-200 space-y-2">

            <!-- Metadaten-Zeile -->
            <div v-if="group.metadata" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-base-content/50">
              <span v-if="group.metadata.file_size" class="flex items-center gap-1">
                <HardDrive class="w-3 h-3" />
                {{ formatFileSize(group.metadata.file_size) }}
              </span>
              <span v-if="group.metadata.usage_purpose && group.metadata.usage_purpose !== 'both'" class="flex items-center gap-1">
                <Globe class="w-3 h-3" />
                {{ group.metadata.usage_purpose }}
              </span>
              <span v-if="group.metadata.created_at" class="flex items-center gap-1">
                <Calendar class="w-3 h-3" />
                {{ formatDate(group.metadata.created_at) }}
              </span>
              <span v-if="group.metadata.description" class="text-base-content/40 truncate max-w-xs">
                {{ group.metadata.description }}
              </span>
            </div>

            <!-- Produkt-Referenzen -->
            <div v-if="group.products && group.products.length > 0" class="flex items-start gap-2">
              <Package class="w-3.5 h-3.5 text-base-content/40 mt-0.5 shrink-0" />
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="product in group.products.slice(0, 5)"
                  :key="product.id"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-base-300/50 text-[10px] text-base-content/60"
                  :title="product.name"
                >
                  {{ product.sku }}
                  <span v-if="product.name !== product.sku" class="text-base-content/40 truncate max-w-[120px]">
                    {{ product.name }}
                  </span>
                </span>
                <span
                  v-if="group.products.length > 5"
                  class="inline-flex items-center px-1.5 py-0.5 text-[10px] text-base-content/40"
                >
                  +{{ group.products.length - 5 }} {{ t('pdf.moreProducts', 'weitere') }}
                </span>
              </div>
            </div>
          </div>

          <!-- Seiten-Snippets -->
          <button
            v-for="hit in group.hits"
            :key="`${group.pdf_document_id}-${hit.page_number}`"
            class="flex items-start gap-3 p-3 pl-11 hover:bg-base-200 transition-colors text-left w-full border-b border-base-200 last:border-b-0"
            @click="selectResult(group, hit)"
          >
            <FileText class="w-4 h-4 text-base-content/30 mt-0.5 shrink-0" />
            <div class="flex flex-col gap-1 min-w-0">
              <span class="text-xs font-medium text-base-content/60">
                {{ t('pdf.page', 'Seite') }} {{ hit.page_number }}
              </span>
              <p
                v-if="hit.snippet"
                class="text-xs text-base-content/60 line-clamp-2"
                v-html="hit.snippet"
              />
            </div>
          </button>

          <!-- Hinweis auf weitere Treffer -->
          <div
            v-if="group.total_hits > group.hits.length"
            class="px-3 py-2 pl-11 text-xs text-base-content/40 bg-base-200/50"
          >
            + {{ group.total_hits - group.hits.length }} {{ t('pdf.moreHits', 'weitere Treffer in diesem Dokument') }}
          </div>
        </div>
      </div>
    </div>

    <!-- Leer-Zustand -->
    <div
      v-else-if="query.trim().length >= 2 && !loading && groups.length === 0"
      class="flex flex-col items-center py-8 gap-2"
    >
      <Search class="w-8 h-8 text-base-content/15" />
      <p class="text-sm text-base-content/40">{{ t('pdf.noResults', 'Keine Treffer gefunden') }}</p>
    </div>

    <!-- Mehr laden -->
    <button
      v-if="groups.length > 0 && hasMore"
      class="btn btn-ghost btn-sm mx-auto"
      :disabled="loading"
      @click="loadMore"
    >
      {{ t('pdf.loadMore', 'Mehr laden') }}
    </button>
  </div>
</template>
