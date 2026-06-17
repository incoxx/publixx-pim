import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock the license API
vi.mock('@/api/license', () => ({
  default: {
    get: vi.fn(),
    activate: vi.fn(),
  },
}))

import licenseApi from '@/api/license'
import { useLicenseStore } from '../license'

describe('License Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    store = useLicenseStore()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('getters', () => {
    it('isEnterprise requires edition enterprise AND valid flag', () => {
      store.data = { edition: 'enterprise', valid: true }
      expect(store.isEnterprise).toBe(true)

      store.data = { edition: 'enterprise', valid: false }
      expect(store.isEnterprise).toBe(false)

      store.data = { edition: 'community', valid: true }
      expect(store.isEnterprise).toBe(false)

      store.data = null
      expect(store.isEnterprise).toBeFalsy()
    })

    it('exposes safe defaults without license data', () => {
      expect(store.modules).toEqual({})
      expect(store.customer).toBeNull()
      expect(store.expiresAt).toBeNull()
      expect(store.maxUsers).toBe(0)
      expect(store.currentUsers).toBe(0)
    })

    it('isModuleActive checks the licensed flag', () => {
      store.data = {
        modules: {
          gaeb: { licensed: true },
          etim: { licensed: false },
        },
      }

      expect(store.isModuleActive('gaeb')).toBe(true)
      expect(store.isModuleActive('etim')).toBe(false)
      expect(store.isModuleActive('unknown')).toBe(false)
    })
  })

  describe('fetchLicense', () => {
    it('stores license data on success', async () => {
      licenseApi.get.mockResolvedValue({
        data: { data: { edition: 'enterprise', valid: true, max_users: 10 } },
      })

      await store.fetchLicense()

      expect(store.data.edition).toBe('enterprise')
      expect(store.maxUsers).toBe(10)
      expect(store.loaded).toBe(true)
      expect(store.loading).toBe(false)
    })

    it('retries after failure and succeeds on second attempt', async () => {
      vi.useFakeTimers()
      licenseApi.get
        .mockRejectedValueOnce(new Error('timeout'))
        .mockResolvedValueOnce({ data: { data: { edition: 'enterprise', valid: true } } })

      const promise = store.fetchLicense()
      await vi.advanceTimersByTimeAsync(1500)
      await promise

      expect(licenseApi.get).toHaveBeenCalledTimes(2)
      expect(store.data.edition).toBe('enterprise')
      expect(store.loaded).toBe(true)
    })

    it('marks as loaded with null data after all retries fail', async () => {
      vi.useFakeTimers()
      licenseApi.get.mockRejectedValue(new Error('down'))

      const promise = store.fetchLicense()
      await vi.advanceTimersByTimeAsync(5000)
      await promise

      expect(licenseApi.get).toHaveBeenCalledTimes(3) // initial + 2 retries
      expect(store.data).toBeNull()
      expect(store.loaded).toBe(true)
      expect(store.loading).toBe(false)
    })

    it('does not start a second request while loading', async () => {
      store.loading = true

      await store.fetchLicense()

      expect(licenseApi.get).not.toHaveBeenCalled()
    })
  })

  describe('activateLicense', () => {
    it('stores and returns the new license data', async () => {
      licenseApi.activate.mockResolvedValue({
        data: { data: { edition: 'enterprise', valid: true } },
      })

      const result = await store.activateLicense('KEY-123')

      expect(licenseApi.activate).toHaveBeenCalledWith('KEY-123')
      expect(result.edition).toBe('enterprise')
      expect(store.data.edition).toBe('enterprise')
    })

    it('propagates activation errors', async () => {
      licenseApi.activate.mockRejectedValue(new Error('Ungültiger Schlüssel'))

      await expect(store.activateLicense('BAD')).rejects.toThrow('Ungültiger Schlüssel')
    })
  })

  describe('reset', () => {
    it('clears data and loaded flag', () => {
      store.data = { edition: 'enterprise' }
      store.loaded = true

      store.reset()

      expect(store.data).toBeNull()
      expect(store.loaded).toBe(false)
    })
  })
})
