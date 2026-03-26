import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import quickSearchApi from '@/api/quickSearch'

export const useQuickSearchStore = defineStore('quickSearch', () => {
  // ─── State ─────────────────────────────────────────
  const query = ref('')
  const activeTab = ref('products')
  const counts = ref({ products: 0, media: 0, hierarchies: 0, attributes: 0 })
  const results = ref([])
  const loading = ref(false)
  const loadingMore = ref(false)
  const hasMore = ref(false)
  const selectedIndex = ref(-1)

  // Drill-Down-Filter
  const filters = ref({ category_id: null, attribute_id: null, media_id: null })

  // Verlauf-Stack für Breadcrumbs
  const history = ref([])

  // ─── Computed ──────────────────────────────────────
  const hasQuery = computed(() => query.value.trim() !== '' || hasActiveFilter.value)
  const hasActiveFilter = computed(() =>
    filters.value.category_id || filters.value.attribute_id || filters.value.media_id
  )
  const activeFilterLabel = computed(() => {
    if (filters.value.category_id) return 'Kategorie-Filter aktiv'
    if (filters.value.attribute_id) return 'Attribut-Filter aktiv'
    if (filters.value.media_id) return 'Medien-Filter aktiv'
    return null
  })

  // ─── Request-Cancellation ─────────────────────────
  let debounceTimer = null
  let abortController = null
  let requestId = 0 // Race-Condition-Schutz

  // ─── Actions ───────────────────────────────────────

  async function search(newQuery) {
    if (newQuery !== undefined) {
      query.value = newQuery
    }
    selectedIndex.value = -1

    // Laufenden Debounce abbrechen
    if (debounceTimer) clearTimeout(debounceTimer)

    const term = query.value.trim()
    if (!term && !hasActiveFilter.value) {
      counts.value = { products: 0, media: 0, hierarchies: 0, attributes: 0 }
      results.value = []
      loading.value = false
      return
    }

    loading.value = true

    debounceTimer = setTimeout(async () => { // 150ms Debounce
      // Vorherigen Request abbrechen
      if (abortController) abortController.abort()
      abortController = new AbortController()

      const currentRequestId = ++requestId

      try {
        // 1 Request: Ergebnisse + Counts für alle Tabs (Backend liefert beides)
        const params = buildParams({ q: term, type: activeTab.value, limit: 20 })
        const { data } = await quickSearchApi.search(params, abortController.signal)

        if (currentRequestId !== requestId) return

        results.value = data.results || []
        hasMore.value = data.has_more ?? false
        if (data.counts) {
          counts.value = { ...counts.value, ...data.counts }
        }
      } catch (err) {
        if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
        console.error('Schnellsuche fehlgeschlagen:', err)
      } finally {
        if (currentRequestId === requestId) {
          loading.value = false
        }
      }
    }, 150)
  }

  function switchTab(tab) {
    if (activeTab.value === tab) return
    activeTab.value = tab
    selectedIndex.value = -1
    results.value = []
    // Sofort suchen (kein Debounce beim Tab-Wechsel)
    executeImmediateSearch()
  }

  /** Tab-Wechsel: nur Ergebnisse für neuen Tab laden (Counts bleiben). */
  async function executeImmediateSearch() {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (abortController) abortController.abort()
    abortController = new AbortController()

    const currentRequestId = ++requestId
    const term = query.value.trim()

    if (!term && !hasActiveFilter.value) {
      results.value = []
      loading.value = false
      return
    }

    loading.value = true
    try {
      const params = buildParams({ q: term, type: activeTab.value, limit: 20 })
      const { data } = await quickSearchApi.search(params, abortController.signal)
      if (currentRequestId !== requestId) return

      results.value = data.results || []
      hasMore.value = data.has_more ?? false
      if (data.counts?.[activeTab.value] !== undefined) {
        counts.value = { ...counts.value, [activeTab.value]: data.counts[activeTab.value] }
      }
    } catch (err) {
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
      console.error('Schnellsuche fehlgeschlagen:', err)
    } finally {
      if (currentRequestId === requestId) {
        loading.value = false
      }
    }
  }

  /** Infinite Scroll: nächste Seite nachladen und an results anhängen. */
  async function loadMore() {
    if (loadingMore.value || !hasMore.value) return

    loadingMore.value = true
    const currentRequestId = ++requestId

    try {
      const term = query.value.trim()
      const params = buildParams({
        q: term,
        type: activeTab.value,
        limit: 20,
        offset: results.value.length,
      })
      const { data } = await quickSearchApi.search(params)
      if (currentRequestId !== requestId) return

      const newItems = data.results || []
      results.value = [...results.value, ...newItems]
      hasMore.value = data.has_more ?? false
    } catch (err) {
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
      console.error('Nachladen fehlgeschlagen:', err)
    } finally {
      if (currentRequestId === requestId) {
        loadingMore.value = false
      }
    }
  }

  function drillDown({ tab, filter, label }) {
    history.value.push({
      query: query.value,
      tab: activeTab.value,
      filters: { ...filters.value },
      label,
    })

    if (filter.category_id !== undefined) filters.value.category_id = filter.category_id
    if (filter.attribute_id !== undefined) filters.value.attribute_id = filter.attribute_id
    if (filter.media_id !== undefined) filters.value.media_id = filter.media_id

    if (tab) {
      activeTab.value = tab
    }

    results.value = []
    search()
  }

  function jumpToHistory(index) {
    if (index < 0 || index >= history.value.length) return

    const entry = history.value[index]
    history.value = history.value.slice(0, index)

    query.value = entry.query
    activeTab.value = entry.tab
    filters.value = { ...entry.filters }

    results.value = []
    search()
  }

  function clearFilters() {
    filters.value = { category_id: null, attribute_id: null, media_id: null }
  }

  function clear() {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (abortController) abortController.abort()
    query.value = ''
    activeTab.value = 'products'
    counts.value = { products: 0, media: 0, hierarchies: 0, attributes: 0 }
    results.value = []
    loading.value = false
    loadingMore.value = false
    hasMore.value = false
    selectedIndex.value = -1
    filters.value = { category_id: null, attribute_id: null, media_id: null }
    history.value = []
  }

  /** Filter-Parameter zusammenbauen */
  function buildParams(base) {
    const params = { ...base }
    if (filters.value.category_id) params.category_id = filters.value.category_id
    if (filters.value.attribute_id) params.attribute_id = filters.value.attribute_id
    if (filters.value.media_id) params.media_id = filters.value.media_id
    return params
  }

  return {
    query, activeTab, counts, results, loading, loadingMore, hasMore, selectedIndex, filters, history,
    hasQuery, hasActiveFilter, activeFilterLabel,
    search, switchTab, loadMore, drillDown, jumpToHistory, clearFilters, clear,
  }
})
