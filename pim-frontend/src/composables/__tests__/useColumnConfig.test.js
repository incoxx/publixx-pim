import { describe, it, expect, beforeEach } from 'vitest'
import { ref, nextTick } from 'vue'
import { useColumnConfig } from '../useColumnConfig'

const STORAGE_KEY = 'columns:test'

const defaultColumns = [
  { key: 'sku', label: 'SKU' },
  { key: 'name', label: 'Name' },
]
const extraColumns = [
  { key: 'status', label: 'Status' },
]

describe('useColumnConfig', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  describe('initialization', () => {
    it('uses default columns when nothing is stored', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      expect(cfg.visibleKeys.value).toEqual(['sku', 'name'])
      expect(cfg.visibleColumns.value.map(c => c.label)).toEqual(['SKU', 'Name'])
    })

    it('restores stored keys from localStorage', () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(['name', 'status']))

      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      expect(cfg.visibleKeys.value).toEqual(['name', 'status'])
    })

    it('drops stored keys that no longer exist', () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(['name', 'removed-column']))

      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      expect(cfg.visibleKeys.value).toEqual(['name'])
    })

    it('keeps dynamic attribute keys even before dynamic columns load', () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(['sku', 'attributes.color']))

      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      expect(cfg.visibleKeys.value).toEqual(['sku', 'attributes.color'])
    })

    it('falls back to defaults on corrupt localStorage data', () => {
      localStorage.setItem(STORAGE_KEY, '{not json')

      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      expect(cfg.visibleKeys.value).toEqual(['sku', 'name'])
    })
  })

  describe('toggleColumn', () => {
    it('shows a hidden column', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      cfg.toggleColumn('status')

      expect(cfg.isColumnVisible('status')).toBe(true)
    })

    it('hides a visible column', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      cfg.toggleColumn('name')

      expect(cfg.isColumnVisible('name')).toBe(false)
    })

    it('never hides the last remaining column', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)
      cfg.toggleColumn('name')
      expect(cfg.visibleKeys.value).toEqual(['sku'])

      cfg.toggleColumn('sku')

      expect(cfg.visibleKeys.value).toEqual(['sku'])
    })

    it('persists changes to localStorage', async () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      cfg.toggleColumn('status')
      await nextTick()

      expect(JSON.parse(localStorage.getItem(STORAGE_KEY))).toEqual(['sku', 'name', 'status'])
    })
  })

  describe('moveColumn', () => {
    it('moves a column up and down', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      cfg.moveColumn('name', 'up')
      expect(cfg.visibleKeys.value).toEqual(['name', 'sku'])

      cfg.moveColumn('name', 'down')
      expect(cfg.visibleKeys.value).toEqual(['sku', 'name'])
    })

    it('ignores moves beyond the boundaries', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      cfg.moveColumn('sku', 'up')
      cfg.moveColumn('name', 'down')

      expect(cfg.visibleKeys.value).toEqual(['sku', 'name'])
    })

    it('ignores unknown keys', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)

      cfg.moveColumn('unknown', 'up')

      expect(cfg.visibleKeys.value).toEqual(['sku', 'name'])
    })
  })

  describe('resetColumns', () => {
    it('restores the default keys', () => {
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns)
      cfg.toggleColumn('status')
      cfg.toggleColumn('name')

      cfg.resetColumns()

      expect(cfg.visibleKeys.value).toEqual(['sku', 'name'])
    })
  })

  describe('dynamic columns', () => {
    it('includes dynamic columns in allColumns once loaded', async () => {
      const dynamic = ref([])
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns, dynamic)

      expect(cfg.allColumns.value).toHaveLength(3)

      dynamic.value = [{ key: 'attributes.color', label: 'Farbe' }]
      await nextTick()

      expect(cfg.allColumns.value).toHaveLength(4)
    })

    it('resolves visible dynamic columns after they load', async () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(['sku', 'attributes.color']))
      const dynamic = ref([])
      const cfg = useColumnConfig(STORAGE_KEY, defaultColumns, extraColumns, dynamic)

      // Dynamic column not loaded yet — filtered out of visibleColumns
      expect(cfg.visibleColumns.value.map(c => c.key)).toEqual(['sku'])

      dynamic.value = [{ key: 'attributes.color', label: 'Farbe' }]
      await nextTick()

      expect(cfg.visibleColumns.value.map(c => c.key)).toEqual(['sku', 'attributes.color'])
    })
  })
})
