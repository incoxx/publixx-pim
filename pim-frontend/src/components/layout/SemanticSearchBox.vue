<script setup>
// Semantische Schnellsuche in der Topbar (nur sichtbar wenn Meilisearch aktiv).
// Eigenständiger State (lokales Debounce + Abort), damit Tippen hier nicht
// den Suchzustand der Schnellsuche-Seite überschreibt.
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useQuickSearchStore } from '@/stores/quickSearch'
import semanticSearchApi from '@/api/semanticSearch'
import { Sparkles, Package, Filter, X } from 'lucide-vue-next'

const router = useRouter()
const store = useQuickSearchStore()

const query = ref('')
const hits = ref([])
const constraints = ref([])
const total = ref(0)
const loading = ref(false)
const open = ref(false)
const selectedIndex = ref(-1)
const containerRef = ref(null)
const inputRef = ref(null)

let debounceTimer = null
let abortController = null

const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'

onMounted(() => {
  store.checkSemanticHealth()
  document.addEventListener('click', onClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside)
  if (debounceTimer) clearTimeout(debounceTimer)
  if (abortController) abortController.abort()
})

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    open.value = false
  }
}

function onInput(e) {
  query.value = e.target.value
  selectedIndex.value = -1
  if (debounceTimer) clearTimeout(debounceTimer)

  const term = query.value.trim()
  if (!term) {
    hits.value = []
    constraints.value = []
    total.value = 0
    open.value = false
    return
  }

  loading.value = true
  open.value = true
  debounceTimer = setTimeout(() => fetchResults(term), 250)
}

async function fetchResults(term) {
  if (abortController) abortController.abort()
  abortController = new AbortController()

  try {
    const { data } = await semanticSearchApi.search(
      { q: term, limit: 8 },
      abortController.signal,
    )
    hits.value = data.hits ?? []
    constraints.value = data.applied_constraints ?? []
    total.value = data.total ?? 0
  } catch (err) {
    if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
    console.error('Semantische Suche fehlgeschlagen:', err)
    hits.value = []
  } finally {
    loading.value = false
  }
}

function onKeyDown(e) {
  if (e.key === 'Escape') {
    open.value = false
    inputRef.value?.blur()
    return
  }
  if (!open.value || hits.value.length === 0) {
    if (e.key === 'Enter') openInQuickSearch()
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, hits.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (selectedIndex.value >= 0) {
      openProduct(hits.value[selectedIndex.value])
    } else {
      openInQuickSearch()
    }
  }
}

function openProduct(hit) {
  if (!hit?.id) return
  open.value = false
  router.push(`/products/${hit.id}`)
}

// Übergibt die Anfrage an die Schnellsuche-Seite (Tab „Semantisch")
function openInQuickSearch() {
  const term = query.value.trim()
  if (!term) return
  open.value = false
  store.activeTab = 'semantic'
  store.semanticSearch(term)
  router.push('/quick-search')
}

function clearQuery() {
  query.value = ''
  hits.value = []
  constraints.value = []
  total.value = 0
  open.value = false
  inputRef.value?.focus()
}

function constraintLabel(c) {
  const attrs = (c.attributes ?? []).join(', ')
  const op = c.operator === 'range'
    ? `${c.min} – ${c.max}`
    : (c.operator === 'le' ? '≤' : c.operator === 'ge' ? '≥' : '=') + ' ' + c.value
  return `${attrs} ${op}${c.unit ? ' ' + c.unit : ''}`
}
</script>

<template>
  <div v-if="store.semanticEnabled" ref="containerRef" class="relative hidden md:block">
    <!-- Eingabefeld -->
    <div class="relative">
      <Sparkles class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 opacity-60 pointer-events-none" :stroke-width="1.75" />
      <input
        ref="inputRef"
        type="text"
        :value="query"
        @input="onInput"
        @keydown="onKeyDown"
        @focus="query.trim() && (open = true)"
        class="w-44 lg:w-64 h-8 pl-8 pr-7 text-xs rounded-md border bg-white/5 placeholder:opacity-50 focus:outline-none focus:bg-white/10 transition-colors"
        :style="{ color: 'inherit', borderColor: 'var(--pim-toolbar-border)' }"
        placeholder="Semantische Suche..."
        autocomplete="off"
        spellcheck="false"
      />
      <button
        v-if="query"
        class="absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded hover:bg-white/10"
        style="color: inherit"
        @click="clearQuery"
      >
        <X class="w-3 h-3" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Dropdown -->
    <div
      v-if="open"
      class="absolute right-0 top-full mt-1.5 w-[26rem] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-xl overflow-hidden z-50"
    >
      <!-- Constraint-Chips -->
      <div
        v-if="constraints.length > 0"
        class="flex flex-wrap gap-1 px-3 pt-2.5 pb-1"
      >
        <span
          v-for="(c, i) in constraints"
          :key="i"
          class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-[color-mix(in_srgb,var(--color-success,#22c55e)_15%,transparent)] text-[color-mix(in_srgb,var(--color-success,#16a34a)_90%,black)]"
          :title="c.source_text"
        >
          <Filter class="w-2.5 h-2.5" :stroke-width="2" />
          {{ constraintLabel(c) }}
        </span>
      </div>

      <!-- Ladeindikator -->
      <div v-if="loading" class="flex items-center gap-2 px-3 py-4 text-xs text-[var(--color-text-tertiary)]">
        <div class="w-3 h-3 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
        Suche läuft...
      </div>

      <!-- Treffer -->
      <div v-else-if="hits.length > 0" class="max-h-96 overflow-y-auto py-1">
        <button
          v-for="(hit, idx) in hits"
          :key="hit.id"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-[var(--color-bg)] transition-colors"
          :class="{ 'bg-[var(--color-bg)]': selectedIndex === idx }"
          @click="openProduct(hit)"
          @mouseenter="selectedIndex = idx"
        >
          <div class="shrink-0 w-8 h-8 rounded bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
            <img
              v-if="hit.primary_image"
              :src="`${apiBase}/media/file/${hit.primary_image}`"
              class="w-full h-full object-cover"
              loading="lazy"
              @error="$event.target.classList.add('hidden')"
            />
            <Package v-else class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs font-medium text-[var(--color-text-primary)] truncate">
              {{ hit.name_de || hit.name_en || hit.sku }}
            </div>
            <div class="text-[10px] text-[var(--color-text-tertiary)] truncate">
              {{ [hit.sku, hit.hierarchy_path].filter(Boolean).join(' · ') }}
            </div>
          </div>
          <div class="shrink-0 flex flex-col items-end gap-0.5">
            <span v-if="hit.list_price != null" class="text-[11px] font-semibold text-[var(--color-text-primary)]">
              {{ hit.list_price.toLocaleString('de-DE', { minimumFractionDigits: 2 }) }} €
            </span>
            <span v-if="hit.score != null" class="text-[9px] text-[var(--color-text-tertiary)] tabular-nums">
              {{ Math.round(hit.score * 100) }}%
            </span>
          </div>
        </button>
      </div>

      <!-- Keine Treffer -->
      <div v-else class="px-3 py-4 text-xs text-[var(--color-text-tertiary)] text-center">
        Keine Treffer gefunden
      </div>

      <!-- Footer: alle Ergebnisse -->
      <button
        v-if="!loading && total > 0"
        class="w-full px-3 py-2 text-[11px] text-[var(--color-accent)] border-t border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors text-left"
        @click="openInQuickSearch"
      >
        Alle {{ total }} Ergebnisse in der Schnellsuche anzeigen →
      </button>
    </div>
  </div>
</template>
