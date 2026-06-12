import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock the router (used for navigation after closing the active tab)
vi.mock('@/router', () => ({
  default: { push: vi.fn() },
}))

import router from '@/router'
import { useTabStore } from '../tabs'

function makeRoute(name, id = null, meta = {}) {
  return {
    name,
    params: id ? { id } : {},
    fullPath: id ? `/${name}/${id}` : `/${name}`,
    meta,
  }
}

describe('Tab Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    setActivePinia(createPinia())
    store = useTabStore()
  })

  describe('openTab', () => {
    it('adds a tab and activates it', () => {
      store.openTab(makeRoute('products', '1'), 'Produkt 1')

      expect(store.tabs).toHaveLength(1)
      expect(store.tabs[0].id).toBe('products-1')
      expect(store.tabs[0].title).toBe('Produkt 1')
      expect(store.activeTabId).toBe('products-1')
    })

    it('does not duplicate an existing tab, only activates it', () => {
      store.openTab(makeRoute('products', '1'))
      store.openTab(makeRoute('products', '2'))
      store.openTab(makeRoute('products', '1'))

      expect(store.tabs).toHaveLength(2)
      expect(store.activeTabId).toBe('products-1')
    })

    it('derives the title from route meta when no label given', () => {
      store.openTab(makeRoute('settings', null, { tabTitle: 'Einstellungen' }))

      expect(store.tabs[0].title).toBe('Einstellungen')
    })

    it('evicts the oldest tab when MAX_TABS (10) is reached', () => {
      for (let i = 1; i <= 11; i++) {
        store.openTab(makeRoute('products', String(i)))
      }

      expect(store.tabs).toHaveLength(10)
      expect(store.tabs[0].id).toBe('products-2')
      expect(store.tabs[9].id).toBe('products-11')
    })

    it('persists tabs to localStorage', () => {
      store.openTab(makeRoute('products', '1'))

      const saved = JSON.parse(localStorage.getItem('pim_open_tabs'))
      expect(saved).toHaveLength(1)
      expect(saved[0].id).toBe('products-1')
    })
  })

  describe('persistence', () => {
    it('restores tabs from localStorage on store creation', () => {
      localStorage.setItem('pim_open_tabs', JSON.stringify([
        { id: 'products-1', routeName: 'products', routeFullPath: '/products/1', title: 'P1' },
      ]))
      setActivePinia(createPinia())
      const fresh = useTabStore()

      expect(fresh.tabs).toHaveLength(1)
      expect(fresh.tabs[0].id).toBe('products-1')
    })

    it('ignores corrupt localStorage data', () => {
      localStorage.setItem('pim_open_tabs', '{not json')
      setActivePinia(createPinia())
      const fresh = useTabStore()

      expect(fresh.tabs).toEqual([])
    })
  })

  describe('closeTab', () => {
    it('removes the tab', () => {
      store.openTab(makeRoute('products', '1'))
      store.openTab(makeRoute('products', '2'))

      store.closeTab('products-1')

      expect(store.tabs).toHaveLength(1)
      expect(store.tabs[0].id).toBe('products-2')
    })

    it('navigates to the next tab when closing the active one', () => {
      store.openTab(makeRoute('products', '1'))
      store.openTab(makeRoute('products', '2'))
      store.activeTabId = 'products-1'

      store.closeTab('products-1')

      expect(store.activeTabId).toBe('products-2')
      expect(router.push).toHaveBeenCalledWith('/products/2')
    })

    it('clears activeTabId when the last tab is closed', () => {
      store.openTab(makeRoute('products', '1'))

      store.closeTab('products-1')

      expect(store.tabs).toEqual([])
      expect(store.activeTabId).toBeNull()
      expect(router.push).not.toHaveBeenCalled()
    })

    it('does not navigate when closing an inactive tab', () => {
      store.openTab(makeRoute('products', '1'))
      store.openTab(makeRoute('products', '2'))
      // active is products-2

      store.closeTab('products-1')

      expect(router.push).not.toHaveBeenCalled()
      expect(store.activeTabId).toBe('products-2')
    })

    it('ignores unknown tab ids', () => {
      store.openTab(makeRoute('products', '1'))

      store.closeTab('does-not-exist')

      expect(store.tabs).toHaveLength(1)
    })
  })

  describe('closeOtherTabs / closeAllTabs', () => {
    it('closeOtherTabs keeps only the given tab', () => {
      store.openTab(makeRoute('products', '1'))
      store.openTab(makeRoute('products', '2'))
      store.openTab(makeRoute('products', '3'))

      store.closeOtherTabs('products-2')

      expect(store.tabs).toHaveLength(1)
      expect(store.activeTabId).toBe('products-2')
    })

    it('closeAllTabs empties the tab list', () => {
      store.openTab(makeRoute('products', '1'))

      store.closeAllTabs()

      expect(store.tabs).toEqual([])
      expect(store.activeTabId).toBeNull()
      expect(JSON.parse(localStorage.getItem('pim_open_tabs'))).toEqual([])
    })
  })

  describe('pinCurrentRoute', () => {
    it('pins an unpinned route and returns true', () => {
      const result = store.pinCurrentRoute(makeRoute('products', '1'))

      expect(result).toBe(true)
      expect(store.isRoutePinned(makeRoute('products', '1'))).toBe(true)
    })

    it('unpins an already pinned route and returns false', () => {
      store.pinCurrentRoute(makeRoute('products', '1'))

      const result = store.pinCurrentRoute(makeRoute('products', '1'))

      expect(result).toBe(false)
      expect(store.isRoutePinned(makeRoute('products', '1'))).toBe(false)
    })
  })

  describe('setActiveByRoute', () => {
    it('activates the matching tab', () => {
      store.openTab(makeRoute('products', '1'))
      store.openTab(makeRoute('products', '2'))

      store.setActiveByRoute(makeRoute('products', '1'))

      expect(store.activeTabId).toBe('products-1')
    })

    it('clears active tab for unknown routes', () => {
      store.openTab(makeRoute('products', '1'))

      store.setActiveByRoute(makeRoute('dashboard'))

      expect(store.activeTabId).toBeNull()
    })
  })

  describe('updateTabTitle', () => {
    it('updates the title of an existing tab', () => {
      store.openTab(makeRoute('products', '1'), 'Alt')

      store.updateTabTitle(makeRoute('products', '1'), 'Neu')

      expect(store.tabs[0].title).toBe('Neu')
      const saved = JSON.parse(localStorage.getItem('pim_open_tabs'))
      expect(saved[0].title).toBe('Neu')
    })
  })
})
