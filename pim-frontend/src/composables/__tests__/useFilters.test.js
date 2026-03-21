import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock @vueuse/core before importing the composable
vi.mock('@vueuse/core', () => ({
  useDebounceFn: (fn) => fn, // Execute immediately for tests
}))

import { useFilters } from '../useFilters'

describe('useFilters', () => {
  let fetchFn
  let filters

  beforeEach(() => {
    fetchFn = vi.fn()
    filters = useFilters(fetchFn)
  })

  describe('initial state', () => {
    it('starts with empty search', () => {
      expect(filters.search.value).toBe('')
    })

    it('starts with empty filters', () => {
      expect(filters.filters.value).toEqual({})
    })

    it('starts with empty activeFilters', () => {
      expect(filters.activeFilters.value).toEqual([])
    })
  })

  describe('setSearch', () => {
    it('updates search value', () => {
      filters.setSearch('test query')
      expect(filters.search.value).toBe('test query')
    })

    it('triggers fetch', () => {
      filters.setSearch('test')
      expect(fetchFn).toHaveBeenCalled()
    })
  })

  describe('addFilter', () => {
    it('adds filter to filters object', () => {
      filters.addFilter('status', 'active', 'Status: Active')
      expect(filters.filters.value).toEqual({ status: 'active' })
    })

    it('adds entry to activeFilters', () => {
      filters.addFilter('status', 'active', 'Status: Active')
      expect(filters.activeFilters.value).toEqual([
        { key: 'status', value: 'active', label: 'Status: Active' },
      ])
    })

    it('generates label from key:value when not provided', () => {
      filters.addFilter('status', 'active')
      expect(filters.activeFilters.value[0].label).toBe('status: active')
    })

    it('triggers fetch immediately', () => {
      filters.addFilter('status', 'active')
      expect(fetchFn).toHaveBeenCalledTimes(1)
    })
  })

  describe('removeFilter', () => {
    it('removes filter from filters object', () => {
      filters.addFilter('status', 'active')
      filters.removeFilter('status')
      expect(filters.filters.value).toEqual({})
    })

    it('removes entry from activeFilters', () => {
      filters.addFilter('status', 'active')
      filters.removeFilter('status')
      expect(filters.activeFilters.value).toEqual([])
    })

    it('triggers fetch', () => {
      filters.addFilter('status', 'active')
      fetchFn.mockClear()

      filters.removeFilter('status')
      expect(fetchFn).toHaveBeenCalledTimes(1)
    })
  })

  describe('clearFilters', () => {
    it('resets all state', () => {
      filters.addFilter('status', 'active')
      filters.setSearch('test')

      filters.clearFilters()

      expect(filters.filters.value).toEqual({})
      expect(filters.activeFilters.value).toEqual([])
      expect(filters.search.value).toBe('')
    })

    it('triggers fetch', () => {
      fetchFn.mockClear()
      filters.clearFilters()
      expect(fetchFn).toHaveBeenCalledTimes(1)
    })
  })
})
