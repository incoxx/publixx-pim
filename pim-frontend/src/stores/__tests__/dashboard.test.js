import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock all dashboard-related APIs
vi.mock('@/api/dashboard', () => ({
  default: { getData: vi.fn() },
}))
vi.mock('@/api/dashboardPresets', () => ({
  default: { list: vi.fn(), activate: vi.fn() },
}))
vi.mock('@/api/userPreferences', () => ({
  default: { get: vi.fn(), update: vi.fn() },
}))

import dashboardApi from '@/api/dashboard'
import dashboardPresetsApi from '@/api/dashboardPresets'
import userPreferencesApi from '@/api/userPreferences'
import { useDashboardStore } from '../dashboard'

describe('Dashboard Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    setActivePinia(createPinia())
    store = useDashboardStore()
  })

  describe('fetchDashboard', () => {
    it('maps the API payload onto state refs', async () => {
      dashboardApi.getData.mockResolvedValue({
        data: {
          data: {
            welcome: { name: 'Markus' },
            stats: { products: 100 },
            activity_feed: [{ id: 'a1' }],
            my_tasks: [{ id: 't1' }],
            data_flows: { imports: [{ id: 'i1' }], exports: [] },
          },
        },
      })

      await store.fetchDashboard()

      expect(store.welcome).toEqual({ name: 'Markus' })
      expect(store.stats).toEqual({ products: 100 })
      expect(store.activityFeed).toEqual([{ id: 'a1' }])
      expect(store.myTasks).toEqual([{ id: 't1' }])
      expect(store.dataFlows.imports).toHaveLength(1)
      expect(store.loading).toBe(false)
    })

    it('defaults list fields to empty arrays', async () => {
      dashboardApi.getData.mockResolvedValue({ data: { data: {} } })

      await store.fetchDashboard()

      expect(store.activityFeed).toEqual([])
      expect(store.myTasks).toEqual([])
      expect(store.dataFlows).toEqual({ imports: [], exports: [] })
    })

    it('sets error message on failure', async () => {
      dashboardApi.getData.mockRejectedValue({ response: { data: { message: 'Timeout' } } })

      await store.fetchDashboard()

      expect(store.error).toBe('Timeout')
      expect(store.loading).toBe(false)
    })
  })

  describe('loadDashboardConfig', () => {
    it('uses the server config when available', async () => {
      userPreferencesApi.get.mockResolvedValue({
        data: { data: { widgets: { stats: true } } },
      })

      await store.loadDashboardConfig()

      expect(store.dashboardConfig).toEqual({ widgets: { stats: true } })
      expect(store.configLoaded).toBe(true)
      expect(userPreferencesApi.update).not.toHaveBeenCalled()
    })

    it('migrates a localStorage config to the server', async () => {
      userPreferencesApi.get.mockRejectedValue(new Error('404'))
      userPreferencesApi.update.mockResolvedValue({})
      localStorage.setItem('pim_dashboard_widgets', JSON.stringify({ stats: false }))

      await store.loadDashboardConfig()

      expect(store.dashboardConfig).toEqual({ widgets: { stats: false } })
      expect(userPreferencesApi.update).toHaveBeenCalledWith('dashboard', { widgets: { stats: false } })
      expect(localStorage.getItem('pim_dashboard_widgets')).toBeNull()
      expect(store.configLoaded).toBe(true)
    })

    it('marks config as loaded even without any config', async () => {
      userPreferencesApi.get.mockRejectedValue(new Error('404'))

      await store.loadDashboardConfig()

      expect(store.dashboardConfig).toBeNull()
      expect(store.configLoaded).toBe(true)
    })
  })

  describe('saveDashboardConfig', () => {
    it('updates state and persists to the server', async () => {
      userPreferencesApi.update.mockResolvedValue({})

      await store.saveDashboardConfig({ widgets: { trends: true } })

      expect(store.dashboardConfig).toEqual({ widgets: { trends: true } })
      expect(userPreferencesApi.update).toHaveBeenCalledWith('dashboard', { widgets: { trends: true } })
    })

    it('keeps local state when the server call fails (offline fallback)', async () => {
      userPreferencesApi.update.mockRejectedValue(new Error('offline'))

      await store.saveDashboardConfig({ widgets: { trends: false } })

      expect(store.dashboardConfig).toEqual({ widgets: { trends: false } })
    })
  })

  describe('presets', () => {
    it('loadPresets stores the preset list', async () => {
      dashboardPresetsApi.list.mockResolvedValue({ data: { data: [{ id: 'preset-1' }] } })

      await store.loadPresets()

      expect(store.presets).toEqual([{ id: 'preset-1' }])
      expect(store.presetsLoaded).toBe(true)
    })

    it('activatePreset applies the returned config and reports success', async () => {
      dashboardPresetsApi.activate.mockResolvedValue({
        data: { data: { widgets: { stats: true } } },
      })

      const ok = await store.activatePreset('preset-1')

      expect(ok).toBe(true)
      expect(store.dashboardConfig).toEqual({ widgets: { stats: true } })
    })

    it('activatePreset reports failure without changing config', async () => {
      store.dashboardConfig = { widgets: { old: true } }
      dashboardPresetsApi.activate.mockRejectedValue(new Error('404'))

      const ok = await store.activatePreset('missing')

      expect(ok).toBe(false)
      expect(store.dashboardConfig).toEqual({ widgets: { old: true } })
    })
  })
})
