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

  // Verlauf-Stack
  const history = ref([])

  // ─── Result-Cache pro Tab ──────────────────────────
  // Key: `${tab}:${query}:${filterHash}` → { items, hasMore, counts }
  const resultCache = new Map()
  const MAX_CACHE = 20

  function cacheKey(tab, term) {
    const f = filters.value
    return `${tab}:${term}:${f.category_id || ''}:${f.attribute_id || ''}:${f.media_id || ''}`
  }

  function cacheGet(tab, term) {
    return resultCache.get(cacheKey(tab, term))
  }

  function cacheSet(tab, term, data) {
    const key = cacheKey(tab, term)
    if (resultCache.size >= MAX_CACHE) {
      // Ältesten Eintrag löschen
      const first = resultCache.keys().next().value
      resultCache.delete(first)
    }
    resultCache.set(key, data)
  }

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

  // ─── Request Management ────────────────────────────
  let debounceTimer = null
  let abortController = null
  let requestId = 0

  // ─── Kern-Suche ────────────────────────────────────

  async function search(newQuery) {
    if (newQuery !== undefined) {
      query.value = newQuery
    }
    selectedIndex.value = -1
    if (debounceTimer) clearTimeout(debounceTimer)

    const term = query.value.trim()
    if (!term && !hasActiveFilter.value) {
      counts.value = { products: 0, media: 0, hierarchies: 0, attributes: 0 }
      results.value = []
      loading.value = false
      return
    }

    // Cache-Hit? Sofort anzeigen!
    const cached = cacheGet(activeTab.value, term)
    if (cached) {
      results.value = cached.items
      hasMore.value = cached.hasMore
      if (cached.counts) counts.value = { ...counts.value, ...cached.counts }
      loading.value = false
      // Trotzdem frische Daten im Hintergrund holen (stale-while-revalidate)
      fetchFresh(term, false)
      return
    }

    loading.value = true
    debounceTimer = setTimeout(() => fetchFresh(term, true), 120)
  }

  async function fetchFresh(term, showLoading) {
    if (abortController) abortController.abort()
    abortController = new AbortController()
    const currentRequestId = ++requestId

    try {
      const params = buildParams({ q: term, type: activeTab.value, limit: 20 })
      const { data } = await quickSearchApi.search(params, abortController.signal)
      if (currentRequestId !== requestId) return

      results.value = data.results || []
      hasMore.value = data.has_more ?? false
      if (data.counts) counts.value = { ...counts.value, ...data.counts }

      // Cache speichern
      cacheSet(activeTab.value, term, {
        items: results.value,
        hasMore: hasMore.value,
        counts: data.counts,
      })

      // Prefetch: benachbarte Tabs im Hintergrund vorladen
      prefetchAdjacentTabs(term)
    } catch (err) {
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
      console.error('Schnellsuche fehlgeschlagen:', err)
    } finally {
      if (currentRequestId === requestId) loading.value = false
    }
  }

  // ─── Prefetch benachbarter Tabs ────────────────────
  const tabOrder = ['products', 'media', 'hierarchies', 'attributes']

  function prefetchAdjacentTabs(term) {
    if (!term) return
    // Tabs mit Treffern vorladen (max 2)
    let prefetched = 0
    for (const tab of tabOrder) {
      if (tab === activeTab.value) continue
      if ((counts.value[tab] ?? 0) === 0) continue
      if (cacheGet(tab, term)) continue
      if (prefetched >= 2) break
      prefetched++

      // Fire-and-forget: keine UI-Auswirkung
      const params = buildParams({ q: term, type: tab, limit: 20 })
      quickSearchApi.search(params).then(({ data }) => {
        cacheSet(tab, term, {
          items: data.results || [],
          hasMore: data.has_more ?? false,
          counts: data.counts,
        })
      }).catch(() => {})
    }
  }

  // ─── Tab-Wechsel ───────────────────────────────────

  function switchTab(tab) {
    if (activeTab.value === tab) return
    activeTab.value = tab
    selectedIndex.value = -1

    const term = query.value.trim()
    if (!term && !hasActiveFilter.value) {
      results.value = []
      return
    }

    // Cache-Hit? Sofort anzeigen — kein Flackern!
    const cached = cacheGet(tab, term)
    if (cached) {
      results.value = cached.items
      hasMore.value = cached.hasMore
      if (cached.counts) counts.value = { ...counts.value, ...cached.counts }
      loading.value = false
      return
    }

    // Kein Cache → fetchen (sofort, kein Debounce)
    results.value = []
    executeImmediateSearch()
  }

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
      if (data.counts) counts.value = { ...counts.value, ...data.counts }

      cacheSet(activeTab.value, term, {
        items: results.value,
        hasMore: hasMore.value,
        counts: data.counts,
      })
    } catch (err) {
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
      console.error('Schnellsuche fehlgeschlagen:', err)
    } finally {
      if (currentRequestId === requestId) loading.value = false
    }
  }

  // ─── Infinite Scroll ──────────────────────────────

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

      // Cache aktualisieren
      cacheSet(activeTab.value, term, {
        items: results.value,
        hasMore: hasMore.value,
        counts: null, // Counts nicht überschreiben
      })
    } catch (err) {
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') return
      console.error('Nachladen fehlgeschlagen:', err)
    } finally {
      if (currentRequestId === requestId) loadingMore.value = false
    }
  }

  // ─── Drill-Down & History ──────────────────────────

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

    if (tab) activeTab.value = tab
    resultCache.clear() // Filter geändert → Cache invalidieren
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
    resultCache.clear()
    results.value = []
    search()
  }

  function clearFilters() {
    filters.value = { category_id: null, attribute_id: null, media_id: null }
    resultCache.clear()
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
    resultCache.clear()
  }

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
