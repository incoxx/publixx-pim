import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock both search APIs
vi.mock('@/api/quickSearch', () => ({
  default: { search: vi.fn() },
}))
vi.mock('@/api/semanticSearch', () => ({
  default: { search: vi.fn(), health: vi.fn() },
}))

import quickSearchApi from '@/api/quickSearch'
import semanticSearchApi from '@/api/semanticSearch'
import { useQuickSearchStore } from '../quickSearch'

function mockSearchResponse(results = [], counts = null, hasMore = false) {
  quickSearchApi.search.mockResolvedValue({
    data: { results, counts, has_more: hasMore },
  })
}

describe('QuickSearch Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    localStorage.clear()
    setActivePinia(createPinia())
    store = useQuickSearchStore()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('search', () => {
    it('resets results when query is empty', async () => {
      store.results = [{ id: 'x' }]
      store.counts = { products: 5, media: 0, hierarchies: 0, attributes: 0 }

      await store.search('')

      expect(store.results).toEqual([])
      expect(store.counts.products).toBe(0)
      expect(quickSearchApi.search).not.toHaveBeenCalled()
    })

    it('debounces and fetches results', async () => {
      mockSearchResponse([{ id: 'p1' }], { products: 1 }, false)

      store.search('bohrer')
      expect(store.loading).toBe(true)
      expect(quickSearchApi.search).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(120)

      expect(quickSearchApi.search).toHaveBeenCalled()
      expect(store.results).toEqual([{ id: 'p1' }])
      expect(store.counts.products).toBe(1)
      expect(store.loading).toBe(false)
    })

    it('sends query, tab type and language as params', async () => {
      mockSearchResponse([])

      store.search('akku')
      await vi.advanceTimersByTimeAsync(120)

      const [params] = quickSearchApi.search.mock.calls[0]
      expect(params.q).toBe('akku')
      expect(params.type).toBe('products')
      expect(params.limit).toBe(20)
      expect(params.lang).toBe('de')
    })

    it('serves cached results immediately on repeated search (stale-while-revalidate)', async () => {
      mockSearchResponse([{ id: 'p1' }], { products: 1 })
      store.search('bohrer')
      await vi.advanceTimersByTimeAsync(120)
      quickSearchApi.search.mockClear()

      // Same query again — cache hit, no loading state
      await store.search('bohrer')

      expect(store.results).toEqual([{ id: 'p1' }])
      expect(store.loading).toBe(false)
      // Background revalidation still fires
      expect(quickSearchApi.search).toHaveBeenCalledTimes(1)
    })

    it('ignores canceled requests silently', async () => {
      const err = new Error('canceled')
      err.code = 'ERR_CANCELED'
      quickSearchApi.search.mockRejectedValue(err)

      store.search('bohrer')
      await vi.advanceTimersByTimeAsync(120)

      expect(store.results).toEqual([])
    })
  })

  describe('switchTab', () => {
    it('does nothing when switching to the active tab', () => {
      store.switchTab('products')
      expect(quickSearchApi.search).not.toHaveBeenCalled()
    })

    it('fetches immediately (no debounce) on tab switch with a query', async () => {
      mockSearchResponse([{ id: 'm1' }], { media: 1 })
      store.query = 'bohrer'

      store.switchTab('media')
      await vi.runAllTimersAsync()

      expect(store.activeTab).toBe('media')
      expect(store.results).toEqual([{ id: 'm1' }])
    })

    it('serves cached results on tab switch without a new request', async () => {
      mockSearchResponse([{ id: 'p1' }], { products: 1, media: 0 })
      store.search('bohrer')
      await vi.advanceTimersByTimeAsync(120)

      mockSearchResponse([{ id: 'm1' }], { media: 1 })
      store.switchTab('media')
      await vi.runAllTimersAsync()
      quickSearchApi.search.mockClear()

      // Back to products → cache hit
      store.switchTab('products')

      expect(store.results).toEqual([{ id: 'p1' }])
      expect(quickSearchApi.search).not.toHaveBeenCalled()
    })

    it('clears results when switching tabs without a query', () => {
      store.results = [{ id: 'x' }]

      store.switchTab('media')

      expect(store.results).toEqual([])
      expect(quickSearchApi.search).not.toHaveBeenCalled()
    })
  })

  describe('loadMore', () => {
    it('appends results and updates hasMore', async () => {
      mockSearchResponse([{ id: 'p1' }], { products: 40 }, true)
      store.search('bohrer')
      await vi.advanceTimersByTimeAsync(120)

      mockSearchResponse([{ id: 'p2' }], null, false)
      await store.loadMore()

      expect(store.results).toEqual([{ id: 'p1' }, { id: 'p2' }])
      expect(store.hasMore).toBe(false)

      const [params] = quickSearchApi.search.mock.calls.at(-1)
      expect(params.offset).toBe(1)
    })

    it('does nothing when hasMore is false', async () => {
      store.hasMore = false
      await store.loadMore()
      expect(quickSearchApi.search).not.toHaveBeenCalled()
    })
  })

  describe('drill-down and history', () => {
    it('drillDown pushes history entry and applies filter', async () => {
      mockSearchResponse([])
      store.query = 'bohrer'

      store.drillDown({ tab: 'products', filter: { category_id: 'cat-1' }, label: 'Kategorie X' })
      await vi.runAllTimersAsync()

      expect(store.history).toHaveLength(1)
      expect(store.history[0].label).toBe('Kategorie X')
      expect(store.filters.category_id).toBe('cat-1')
      expect(store.hasActiveFilter).toBeTruthy()
      expect(store.activeFilterLabel).toBe('Kategorie-Filter aktiv')
    })

    it('drillDown filter is passed as request param', async () => {
      mockSearchResponse([])
      store.query = 'bohrer'

      store.drillDown({ tab: 'products', filter: { category_id: 'cat-1' }, label: 'Kat' })
      await vi.runAllTimersAsync()

      const [params] = quickSearchApi.search.mock.calls.at(-1)
      expect(params.category_id).toBe('cat-1')
    })

    it('jumpToHistory restores previous state and truncates history', async () => {
      mockSearchResponse([])
      store.query = 'bohrer'
      store.drillDown({ tab: 'products', filter: { category_id: 'cat-1' }, label: 'A' })
      await vi.runAllTimersAsync()
      store.drillDown({ tab: 'media', filter: { media_id: 'm-1' }, label: 'B' })
      await vi.runAllTimersAsync()
      expect(store.history).toHaveLength(2)

      store.jumpToHistory(0)
      await vi.runAllTimersAsync()

      expect(store.history).toHaveLength(0)
      expect(store.filters.category_id).toBeNull()
      expect(store.activeTab).toBe('products')
    })

    it('jumpToHistory ignores out-of-range indices', () => {
      store.jumpToHistory(5)
      expect(quickSearchApi.search).not.toHaveBeenCalled()
    })
  })

  describe('clear', () => {
    it('resets the complete store state', async () => {
      mockSearchResponse([{ id: 'p1' }], { products: 1 })
      store.search('bohrer')
      await vi.advanceTimersByTimeAsync(120)
      store.filters.category_id = 'cat-1'
      store.history.push({ query: 'x' })

      store.clear()

      expect(store.query).toBe('')
      expect(store.results).toEqual([])
      expect(store.counts.products).toBe(0)
      expect(store.filters.category_id).toBeNull()
      expect(store.history).toEqual([])
      expect(store.activeTab).toBe('products')
      expect(store.hasQuery).toBeFalsy()
    })
  })

  describe('semantic search', () => {
    it('checkSemanticHealth enables semantic search when healthy', async () => {
      semanticSearchApi.health.mockResolvedValue({ data: { enabled: true, healthy: true } })

      await store.checkSemanticHealth()

      expect(store.semanticEnabled).toBe(true)
      expect(store.semanticHealthChecked).toBe(true)
    })

    it('checkSemanticHealth disables on error', async () => {
      semanticSearchApi.health.mockRejectedValue(new Error('down'))

      await store.checkSemanticHealth()

      expect(store.semanticEnabled).toBe(false)
      expect(store.semanticHealthChecked).toBe(true)
    })

    it('only checks health once', async () => {
      semanticSearchApi.health.mockResolvedValue({ data: { enabled: true, healthy: true } })

      await store.checkSemanticHealth()
      await store.checkSemanticHealth()

      expect(semanticSearchApi.health).toHaveBeenCalledTimes(1)
    })

    it('semanticSearch debounces and stores hits and constraints', async () => {
      semanticSearchApi.search.mockResolvedValue({
        data: {
          hits: [{ id: 'p1' }],
          applied_constraints: [{ attribute: 'farbe', value: 'rot' }],
          unresolved_constraints: [],
          total: 1,
          mode: 'hybrid',
        },
      })

      store.semanticSearch('rote bohrer')
      expect(store.semanticLoading).toBe(true)
      await vi.advanceTimersByTimeAsync(200)

      expect(store.semanticResults).toEqual([{ id: 'p1' }])
      expect(store.semanticConstraints).toHaveLength(1)
      expect(store.semanticTotal).toBe(1)
      expect(store.semanticMode).toBe('hybrid')
      expect(store.semanticLoading).toBe(false)
    })

    it('semanticSearch with empty query resets semantic state', () => {
      store.semanticResults = [{ id: 'x' }]
      store.semanticTotal = 1

      store.semanticSearch('')

      expect(store.semanticResults).toEqual([])
      expect(store.semanticTotal).toBe(0)
      expect(semanticSearchApi.search).not.toHaveBeenCalled()
    })
  })
})
