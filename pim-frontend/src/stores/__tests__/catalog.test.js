import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { nextTick } from 'vue'

// Mock the catalog API (resolveMediaUrl as pass-through)
vi.mock('@/api/catalog', () => ({
  default: {
    getProducts: vi.fn(),
    getProduct: vi.fn(),
    getCategories: vi.fn(),
    getSettings: vi.fn(),
    getFacets: vi.fn(),
    getCategoryAssets: vi.fn(),
  },
  resolveMediaUrl: vi.fn((path) => path),
}))

import catalogApi from '@/api/catalog'
import { useCatalogStore } from '../catalog'

describe('Catalog Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    window.history.replaceState({}, '', '/')
    setActivePinia(createPinia())
    store = useCatalogStore()
  })

  describe('fetchProducts', () => {
    it('loads products and reads pagination from headers', async () => {
      catalogApi.getProducts.mockResolvedValue({
        data: [{ id: 'p1', image_url: '/img/1.jpg' }],
        headers: {
          'x-current-page': '2',
          'x-last-page': '4',
          'x-per-page': '24',
          'x-total-count': '96',
        },
      })

      await store.fetchProducts()

      expect(store.products).toHaveLength(1)
      expect(store.meta).toEqual({ current_page: 2, last_page: 4, per_page: 24, total: 96 })
      expect(store.loading).toBe(false)
    })

    it('search overrides category and facet filters', async () => {
      catalogApi.getProducts.mockResolvedValue({ data: [], headers: {} })
      store.setCategory('cat-1', 'Werkzeuge')
      store.setFilter('attr-1', 'rot')
      store.setSearch('bohrer')

      await store.fetchProducts()

      const [opts] = catalogApi.getProducts.mock.calls[0]
      expect(opts.search).toBe('bohrer')
      expect(opts.category).toBeUndefined()
      expect(opts.filters).toBeUndefined()
    })

    it('sends category and filters when not searching', async () => {
      catalogApi.getProducts.mockResolvedValue({ data: [], headers: {} })
      store.setCategory('cat-1')
      store.setFilter('attr-1', 'rot')

      await store.fetchProducts()

      const [opts] = catalogApi.getProducts.mock.calls[0]
      expect(opts.category).toBe('cat-1')
      expect(opts.filters).toEqual({ 'attr-1': 'rot' })
    })

    it('clears products and sets error on failure', async () => {
      store.products = [{ id: 'old' }]
      catalogApi.getProducts.mockRejectedValue({ response: { data: { title: 'Kaputt' } } })

      await store.fetchProducts()

      expect(store.products).toEqual([])
      expect(store.error).toBe('Kaputt')
    })
  })

  describe('fetchProduct', () => {
    it('sets currentProduct', async () => {
      catalogApi.getProduct.mockResolvedValue({
        data: { data: { id: 'p1', media: [{ url: '/m.jpg' }] } },
      })

      await store.fetchProduct('p1')

      expect(store.currentProduct.id).toBe('p1')
    })

    it('clears currentProduct on error', async () => {
      store.currentProduct = { id: 'old' }
      catalogApi.getProduct.mockRejectedValue(new Error('404'))

      await store.fetchProduct('missing')

      expect(store.currentProduct).toBeNull()
      expect(store.error).toBe('Produkt nicht gefunden')
    })
  })

  describe('wishlist', () => {
    it('toggleWishlist adds and removes products', () => {
      store.toggleWishlist('p1')
      expect(store.isInWishlist('p1')).toBe(true)
      expect(store.wishlistCount).toBe(1)

      store.toggleWishlist('p1')
      expect(store.isInWishlist('p1')).toBe(false)
      expect(store.wishlistCount).toBe(0)
    })

    it('persists wishlist to localStorage', async () => {
      store.toggleWishlist('p1')
      await nextTick()

      expect(JSON.parse(localStorage.getItem('pim_catalog_wishlist'))).toEqual(['p1'])
    })

    it('restores wishlist from localStorage on creation', () => {
      localStorage.setItem('pim_catalog_wishlist', JSON.stringify(['a', 'b']))
      setActivePinia(createPinia())
      const fresh = useCatalogStore()

      expect(fresh.wishlistIds).toEqual(['a', 'b'])
    })

    it('clearWishlist empties the list', () => {
      store.toggleWishlist('p1')
      store.clearWishlist()
      expect(store.wishlistCount).toBe(0)
    })

    it('importWishlistFromUrl merges ids without duplicates and cleans URL', () => {
      store.toggleWishlist('p1')
      window.history.replaceState({}, '', '/?wishlist=p1,p2,p3')

      store.importWishlistFromUrl()

      expect(store.wishlistIds).toEqual(['p1', 'p2', 'p3'])
      expect(window.location.search).toBe('')
    })

    it('importWishlistFromUrl does nothing without the param', () => {
      store.importWishlistFromUrl()
      expect(store.wishlistIds).toEqual([])
    })

    it('wishlistProducts merges loaded grid products with the fetched cache in wishlist order', async () => {
      // p1 liegt auf der aktuellen Grid-Seite, p2 nicht → muss nachgeladen werden
      catalogApi.getProducts.mockResolvedValueOnce({
        data: [{ id: 'p1', image_url: '/img/1.jpg' }],
        headers: {},
      })
      await store.fetchProducts()

      store.toggleWishlist('p2')
      store.toggleWishlist('p1')

      catalogApi.getProducts.mockResolvedValueOnce({
        data: [{ id: 'p2', name: 'Zwei', image_url: '/img/2.jpg' }],
        headers: {},
      })
      await store.fetchWishlistProducts()

      // Nur die fehlende ID (p2) wird angefragt
      const [opts] = catalogApi.getProducts.mock.calls[1]
      expect(opts.ids).toEqual(['p2'])

      // Reihenfolge folgt der Merkliste (p2, p1), beide sind aufgelöst
      expect(store.wishlistProducts.map((p) => p.id)).toEqual(['p2', 'p1'])
      expect(store.wishlistUnresolvedCount).toBe(0)
    })

    it('wishlistUnresolvedCount counts ids that could not be resolved', async () => {
      store.toggleWishlist('missing')

      catalogApi.getProducts.mockResolvedValueOnce({ data: [], headers: {} })
      await store.fetchWishlistProducts()

      expect(store.wishlistProducts).toEqual([])
      expect(store.wishlistUnresolvedCount).toBe(1)
    })
  })

  describe('facet filters', () => {
    it('setFilter and clearFilter reset to page 1', () => {
      store.meta.current_page = 3
      store.setFilter('attr-1', '10:20')

      expect(store.activeFilters['attr-1']).toBe('10:20')
      expect(store.activeFilterCount).toBe(1)
      expect(store.meta.current_page).toBe(1)

      store.meta.current_page = 3
      store.clearFilter('attr-1')
      expect(store.activeFilterCount).toBe(0)
      expect(store.meta.current_page).toBe(1)
    })

    it('clearAllFilters removes every filter', () => {
      store.setFilter('a', '1')
      store.setFilter('b', '2')

      store.clearAllFilters()

      expect(store.activeFilterCount).toBe(0)
    })
  })

  describe('navigation state', () => {
    it('setSearch resets page', () => {
      store.meta.current_page = 5
      store.setSearch('akku')
      expect(store.search).toBe('akku')
      expect(store.meta.current_page).toBe(1)
      expect(store.searchActive).toBe(true)
    })

    it('setCategory and clearCategory manage selection', () => {
      store.setCategory('cat-1', 'Werkzeuge')
      expect(store.selectedCategoryId).toBe('cat-1')
      expect(store.selectedCategoryName).toBe('Werkzeuge')

      store.clearCategory()
      expect(store.selectedCategoryId).toBeNull()
      expect(store.selectedCategoryName).toBeNull()
    })

    it('setViewMode persists to localStorage', () => {
      store.setViewMode('list')
      expect(store.viewMode).toBe('list')
      expect(localStorage.getItem('catalog_view_mode')).toBe('list')
    })

    it('setLocale persists to localStorage', () => {
      store.setLocale('en')
      expect(store.locale).toBe('en')
      expect(localStorage.getItem('catalog_locale')).toBe('en')
    })
  })

  describe('theme settings', () => {
    it('merges server settings into defaults', async () => {
      catalogApi.getSettings.mockResolvedValue({
        data: { data: { catalog_title: 'Mein Katalog', color_primary: '#000000' } },
      })

      await store.fetchThemeSettings()

      expect(store.themeSettings.catalog_title).toBe('Mein Katalog')
      expect(store.themeSettings.color_primary).toBe('#000000')
      // Defaults bleiben erhalten
      expect(store.themeSettings.catalog_access_mode).toBe('public')
      expect(store._themeLoaded).toBe(true)
    })

    it('applies default_locale only when user has no explicit choice', async () => {
      catalogApi.getSettings.mockResolvedValue({
        data: { data: { default_locale: 'fr' } },
      })

      await store.fetchThemeSettings()
      expect(store.locale).toBe('fr')
    })

    it('keeps user locale choice over default_locale', async () => {
      localStorage.setItem('catalog_locale', 'en')
      setActivePinia(createPinia())
      const fresh = useCatalogStore()
      catalogApi.getSettings.mockResolvedValue({
        data: { data: { default_locale: 'fr' } },
      })

      await fresh.fetchThemeSettings()
      expect(fresh.locale).toBe('en')
    })
  })
})
