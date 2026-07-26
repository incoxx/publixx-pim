import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock the asset catalog API
vi.mock('@/api/assetCatalog', () => ({
  default: {
    getAssets: vi.fn(),
    getAsset: vi.fn(),
    getFolders: vi.fn(),
    getFacets: vi.fn(),
    getUsageTypes: vi.fn(),
    getAssetProducts: vi.fn(),
    downloadZip: vi.fn(),
  },
}))

// Der Store importiert zusätzlich den Produkt-Katalog-Client (Theme-Settings)
vi.mock('@/api/catalog', () => ({
  default: { getSettings: vi.fn() },
  resolveMediaUrl: vi.fn((path) => path),
}))

import assetCatalogApi from '@/api/assetCatalog'
import { useAssetCatalogStore } from '../assetCatalog'

describe('Asset Catalog Store — wishlist', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    window.history.replaceState({}, '', '/')
    setActivePinia(createPinia())
    store = useAssetCatalogStore()
  })

  it('toggleWishlist adds and removes assets', () => {
    store.toggleWishlist('a1')
    expect(store.isInWishlist('a1')).toBe(true)
    expect(store.wishlistCount).toBe(1)

    store.toggleWishlist('a1')
    expect(store.isInWishlist('a1')).toBe(false)
    expect(store.wishlistCount).toBe(0)
  })

  it('wishlistAssets merges loaded grid assets with the fetched cache in wishlist order', async () => {
    // a1 liegt auf der aktuellen Grid-Seite, a2 nicht → muss nachgeladen werden
    assetCatalogApi.getAssets.mockResolvedValueOnce({
      data: { data: [{ id: 'a1', title: 'Eins' }], meta: {} },
    })
    await store.fetchAssets()

    store.toggleWishlist('a2')
    store.toggleWishlist('a1')

    assetCatalogApi.getAssets.mockResolvedValueOnce({
      data: { data: [{ id: 'a2', title: 'Zwei' }], meta: {} },
    })
    await store.fetchWishlistAssets()

    // Nur die fehlende ID (a2) wird angefragt
    const [opts] = assetCatalogApi.getAssets.mock.calls[1]
    expect(opts.ids).toEqual(['a2'])

    // Reihenfolge folgt der Merkliste (a2, a1), beide sind aufgelöst
    expect(store.wishlistAssets.map((a) => a.id)).toEqual(['a2', 'a1'])
    expect(store.wishlistUnresolvedCount).toBe(0)
  })

  it('wishlistUnresolvedCount counts ids that could not be resolved', async () => {
    store.toggleWishlist('missing')

    assetCatalogApi.getAssets.mockResolvedValueOnce({ data: { data: [], meta: {} } })
    await store.fetchWishlistAssets()

    expect(store.wishlistAssets).toEqual([])
    expect(store.wishlistUnresolvedCount).toBe(1)
  })

  it('fetchWishlistAssets does nothing when everything is already loaded', async () => {
    assetCatalogApi.getAssets.mockResolvedValueOnce({
      data: { data: [{ id: 'a1' }], meta: {} },
    })
    await store.fetchAssets()
    store.toggleWishlist('a1')

    await store.fetchWishlistAssets()

    // Nur der initiale fetchAssets-Aufruf, kein zusätzlicher ids-Request
    expect(assetCatalogApi.getAssets).toHaveBeenCalledTimes(1)
  })
})
