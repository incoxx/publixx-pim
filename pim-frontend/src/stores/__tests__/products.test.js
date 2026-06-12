import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useProductStore } from '../products'

// Mock the products API
vi.mock('@/api/products', () => ({
  default: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
    saveAttributeValues: vi.fn(),
  },
}))

import productsApi from '@/api/products'

describe('Product Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    store = useProductStore()
  })

  describe('initial state', () => {
    it('starts with empty items and default meta', () => {
      expect(store.items).toEqual([])
      expect(store.current).toBeNull()
      expect(store.meta).toEqual({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
      expect(store.sort).toEqual({ field: 'updated_at', order: 'desc' })
    })

    it('isEmpty is true when no items and not loading', () => {
      expect(store.isEmpty).toBe(true)
    })

    it('isEmpty is false while loading', () => {
      store.loading = true
      expect(store.isEmpty).toBe(false)
    })
  })

  describe('fetchList', () => {
    it('loads items and meta from the API', async () => {
      productsApi.list.mockResolvedValue({
        data: {
          data: [{ id: 'p1' }, { id: 'p2' }],
          meta: { current_page: 2, last_page: 5, total: 230, per_page: 50 },
        },
      })

      await store.fetchList()

      expect(store.items).toHaveLength(2)
      expect(store.meta.total).toBe(230)
      expect(store.loading).toBe(false)
      expect(store.error).toBeNull()
    })

    it('passes pagination, sort and search to the API', async () => {
      productsApi.list.mockResolvedValue({ data: { data: [] } })
      store.setSearch('bohrer')
      store.setSort('name', 'asc')
      store.setPage(3)

      await store.fetchList()

      expect(productsApi.list).toHaveBeenCalledWith(expect.objectContaining({
        page: 3,
        perPage: 50,
        sort: 'name',
        order: 'asc',
        search: 'bohrer',
      }))
    })

    it('omits search when empty', async () => {
      productsApi.list.mockResolvedValue({ data: { data: [] } })

      await store.fetchList()

      expect(productsApi.list).toHaveBeenCalledWith(
        expect.objectContaining({ search: undefined }),
      )
    })

    it('sets error message from API problem title', async () => {
      productsApi.list.mockRejectedValue({ response: { data: { title: 'Serverfehler' } } })

      await store.fetchList()

      expect(store.error).toBe('Serverfehler')
      expect(store.loading).toBe(false)
    })

    it('falls back to generic error message', async () => {
      productsApi.list.mockRejectedValue(new Error('Network'))

      await store.fetchList()

      expect(store.error).toBe('Fehler beim Laden')
    })
  })

  describe('fetchOne', () => {
    it('sets current product from wrapped response', async () => {
      productsApi.get.mockResolvedValue({ data: { data: { id: 'p1', sku: 'SKU-1' } } })

      await store.fetchOne('p1')

      expect(store.current).toEqual({ id: 'p1', sku: 'SKU-1' })
    })

    it('sets error on failure', async () => {
      productsApi.get.mockRejectedValue({ response: { data: { title: 'Nicht gefunden' } } })

      await store.fetchOne('missing')

      expect(store.error).toBe('Nicht gefunden')
      expect(store.current).toBeNull()
    })
  })

  describe('create', () => {
    it('returns the created product', async () => {
      productsApi.create.mockResolvedValue({ data: { data: { id: 'new-1' } } })

      const result = await store.create({ sku: 'NEW' })

      expect(result).toEqual({ id: 'new-1' })
      expect(productsApi.create).toHaveBeenCalledWith({ sku: 'NEW' })
    })
  })

  describe('update', () => {
    it('optimistically merges into current when ids match', async () => {
      store.current = { id: 'p1', sku: 'OLD', name: 'Alt' }
      productsApi.update.mockResolvedValue({ data: { data: { id: 'p1', sku: 'NEU' } } })

      await store.update('p1', { sku: 'NEU' })

      expect(store.current.sku).toBe('NEU')
      expect(store.current.name).toBe('Alt')
    })

    it('does not touch current when ids differ', async () => {
      store.current = { id: 'p2', sku: 'OTHER' }
      productsApi.update.mockResolvedValue({ data: { data: { id: 'p1', sku: 'NEU' } } })

      await store.update('p1', { sku: 'NEU' })

      expect(store.current.sku).toBe('OTHER')
    })
  })

  describe('remove', () => {
    it('removes item from list and clears current', async () => {
      store.items = [{ id: 'p1' }, { id: 'p2' }]
      store.current = { id: 'p1' }
      productsApi.delete.mockResolvedValue({})

      await store.remove('p1')

      expect(store.items).toEqual([{ id: 'p2' }])
      expect(store.current).toBeNull()
      expect(productsApi.delete).toHaveBeenCalledWith('p1', { force: false })
    })

    it('passes force flag', async () => {
      productsApi.delete.mockResolvedValue({})

      await store.remove('p1', { force: true })

      expect(productsApi.delete).toHaveBeenCalledWith('p1', { force: true })
    })
  })

  describe('pagination helpers', () => {
    it('setSearch resets to page 1', () => {
      store.meta.current_page = 4
      store.setSearch('akku')

      expect(store.search).toBe('akku')
      expect(store.meta.current_page).toBe(1)
    })

    it('setFilters resets to page 1', () => {
      store.meta.current_page = 4
      store.setFilters({ status: 'active' })

      expect(store.filters).toEqual({ status: 'active' })
      expect(store.meta.current_page).toBe(1)
    })

    it('setPage updates current page', () => {
      store.setPage(7)
      expect(store.meta.current_page).toBe(7)
    })
  })

  describe('$reset', () => {
    it('restores initial state', () => {
      store.items = [{ id: 'p1' }]
      store.current = { id: 'p1' }
      store.error = 'Fehler'
      store.meta.current_page = 9

      store.$reset()

      expect(store.items).toEqual([])
      expect(store.current).toBeNull()
      expect(store.error).toBeNull()
      expect(store.meta.current_page).toBe(1)
    })
  })
})
