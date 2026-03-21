import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../auth'

// Mock the auth API
vi.mock('@/api/auth', () => ({
  default: {
    login: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
  },
}))

describe('Auth Store', () => {
  let store

  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    store = useAuthStore()
  })

  describe('initial state', () => {
    it('starts unauthenticated', () => {
      expect(store.isAuthenticated).toBe(false)
      expect(store.user).toBeNull()
      expect(store.token).toBeNull()
    })

    it('defaults locale to de', () => {
      expect(store.locale).toBe('de')
    })
  })

  describe('login', () => {
    it('sets token and user on successful login', async () => {
      const { default: authApi } = await import('@/api/auth')
      authApi.login.mockResolvedValue({
        data: {
          data: {
            token: 'test-token-123',
            user: { id: '1', name: 'Test User', email: 'test@example.com' },
          },
        },
      })

      await store.login({ email: 'test@example.com', password: 'password' })

      expect(store.token).toBe('test-token-123')
      expect(store.user.name).toBe('Test User')
      expect(store.isAuthenticated).toBe(true)
      expect(localStorage.getItem('pim_token')).toBe('test-token-123')
    })
  })

  describe('logout', () => {
    it('clears token and user', async () => {
      store.token = 'test-token'
      store.user = { id: '1', name: 'User' }

      await store.logout()

      expect(store.token).toBeNull()
      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)
      expect(localStorage.getItem('pim_token')).toBeNull()
    })
  })

  describe('hasPermission', () => {
    it('returns true for Admin role', () => {
      store.user = { roles: [{ name: 'Admin' }], all_permissions: [] }

      expect(store.hasPermission('products.edit')).toBe(true)
    })

    it('returns true if permission exists', () => {
      store.user = { roles: [{ name: 'Editor' }], all_permissions: ['products.edit'] }

      expect(store.hasPermission('products.edit')).toBe(true)
    })

    it('returns false if permission missing', () => {
      store.user = { roles: [{ name: 'Editor' }], all_permissions: ['products.view'] }

      expect(store.hasPermission('products.edit')).toBe(false)
    })
  })

  describe('hasInstanceAccess', () => {
    it('returns true for Admin role', () => {
      store.user = { roles: [{ name: 'Admin' }], entity_restrictions: [] }

      expect(store.hasInstanceAccess('hierarchy', 'uuid-1', 'delete')).toBe(true)
    })

    it('returns true when no restrictions exist', () => {
      store.user = { roles: [{ name: 'Editor' }], entity_restrictions: [] }

      expect(store.hasInstanceAccess('hierarchy', 'uuid-1')).toBe(true)
    })

    it('returns false when instance not in allowed list', () => {
      store.user = {
        roles: [{ name: 'Editor' }],
        entity_restrictions: [
          { restrictable_type: 'hierarchy', restrictable_id: 'uuid-2', access_level: 'write' },
        ],
      }

      expect(store.hasInstanceAccess('hierarchy', 'uuid-1')).toBe(false)
    })

    it('checks access level hierarchy', () => {
      store.user = {
        roles: [{ name: 'Editor' }],
        entity_restrictions: [
          { restrictable_type: 'hierarchy', restrictable_id: 'uuid-1', access_level: 'read' },
        ],
      }

      expect(store.hasInstanceAccess('hierarchy', 'uuid-1', 'read')).toBe(true)
      expect(store.hasInstanceAccess('hierarchy', 'uuid-1', 'write')).toBe(false)
      expect(store.hasInstanceAccess('hierarchy', 'uuid-1', 'delete')).toBe(false)
    })
  })

  describe('getTabAccess', () => {
    it('returns write for Admin', () => {
      store.user = { roles: [{ name: 'Admin' }], tab_permissions: {} }

      expect(store.getTabAccess('prices')).toBe('write')
    })

    it('returns configured access level', () => {
      store.user = { roles: [{ name: 'Editor' }], tab_permissions: { prices: 'read' } }

      expect(store.getTabAccess('prices')).toBe('read')
    })

    it('defaults to write when not configured', () => {
      store.user = { roles: [{ name: 'Editor' }], tab_permissions: {} }

      expect(store.getTabAccess('prices')).toBe('write')
    })
  })

  describe('UI state', () => {
    it('toggles sidebar', () => {
      expect(store.sidebarCollapsed).toBe(false)
      store.toggleSidebar()
      expect(store.sidebarCollapsed).toBe(true)
      store.toggleSidebar()
      expect(store.sidebarCollapsed).toBe(false)
    })

    it('toggles command palette', () => {
      expect(store.commandPaletteOpen).toBe(false)
      store.toggleCommandPalette()
      expect(store.commandPaletteOpen).toBe(true)
    })

    it('opens and closes panel', () => {
      store.openPanel('MyComponent', { id: 1 })
      expect(store.panelOpen).toBe(true)
      expect(store.panelComponent).toBe('MyComponent')
      expect(store.panelProps).toEqual({ id: 1 })

      store.closePanel()
      expect(store.panelOpen).toBe(false)
      expect(store.panelComponent).toBeNull()
    })

    it('sets locale and persists to localStorage', () => {
      store.setLocale('en')
      expect(store.locale).toBe('en')
      expect(localStorage.getItem('pim_locale')).toBe('en')
    })
  })
})
