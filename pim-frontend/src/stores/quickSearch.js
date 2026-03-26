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

  // ─── Debounce ──────────────────────────────────────
  let debounceTimer = null

  // ─── Actions ───────────────────────────────────────

  async function search(newQuery) {
    if (newQuery !== undefined) {
      query.value = newQuery
    }
    selectedIndex.value = -1

    // Debounce abbrechen wenn noch aktiv
    if (debounceTimer) clearTimeout(debounceTimer)

    const term = query.value.trim()
    if (!term && !hasActiveFilter.value) {
      counts.value = { products: 0, media: 0, hierarchies: 0, attributes: 0 }
      results.value = []
      return
    }

    loading.value = true

    debounceTimer = setTimeout(async () => {
      try {
        const params = { q: term, type: activeTab.value, limit: 20 }
        if (filters.value.category_id) params.category_id = filters.value.category_id
        if (filters.value.attribute_id) params.attribute_id = filters.value.attribute_id
        if (filters.value.media_id) params.media_id = filters.value.media_id

        const { data } = await quickSearchApi.search(params)
        results.value = data.results || []

        // Counts aktualisieren — bei type-spezifischer Suche nur diesen Typ
        if (data.counts) {
          if (data.counts[activeTab.value] !== undefined) {
            counts.value = { ...counts.value, [activeTab.value]: data.counts[activeTab.value] }
          } else {
            counts.value = data.counts
          }
        }

        // Auch andere Tabs zählen (Hintergrund-Request ohne type)
        if (term || hasActiveFilter.value) {
          fetchAllCounts(term)
        }
      } catch (err) {
        console.error('Schnellsuche fehlgeschlagen:', err)
      } finally {
        loading.value = false
      }
    }, 300)
  }

  async function fetchAllCounts(term) {
    try {
      const params = { q: term, limit: 0 }
      if (filters.value.category_id) params.category_id = filters.value.category_id
      if (filters.value.attribute_id) params.attribute_id = filters.value.attribute_id
      if (filters.value.media_id) params.media_id = filters.value.media_id

      const { data } = await quickSearchApi.search(params)
      if (data.counts) {
        counts.value = data.counts
      }
    } catch {
      // Counts-Fehler ignorieren
    }
  }

  function switchTab(tab) {
    if (activeTab.value === tab) return
    activeTab.value = tab
    selectedIndex.value = -1
    results.value = []
    search()
  }

  function drillDown({ tab, filter, label }) {
    // Aktuellen Zustand in Verlauf speichern
    history.value.push({
      query: query.value,
      tab: activeTab.value,
      filters: { ...filters.value },
      label,
    })

    // Neuen Filter + Tab setzen
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
    // Alles nach diesem Index entfernen
    history.value = history.value.slice(0, index)

    // Zustand wiederherstellen
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
    query.value = ''
    activeTab.value = 'products'
    counts.value = { products: 0, media: 0, hierarchies: 0, attributes: 0 }
    results.value = []
    loading.value = false
    selectedIndex.value = -1
    filters.value = { category_id: null, attribute_id: null, media_id: null }
    history.value = []
    if (debounceTimer) clearTimeout(debounceTimer)
  }

  return {
    // State
    query,
    activeTab,
    counts,
    results,
    loading,
    selectedIndex,
    filters,
    history,
    // Computed
    hasQuery,
    hasActiveFilter,
    activeFilterLabel,
    // Actions
    search,
    switchTab,
    drillDown,
    jumpToHistory,
    clearFilters,
    clear,
  }
})
