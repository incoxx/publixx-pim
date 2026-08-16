/**
 * Dynamische Spalten (Attribute, Metadaten) sind beim Lesen aus dem localStorage
 * noch nicht geladen. Sie muessen die Validierung trotzdem ueberleben, sonst
 * verschwinden eingeblendete Spalten bei jedem Neuladen der Seite.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useColumnConfig } from '../useColumnConfig'

const STORAGE_KEY = 'columns:test'

const DEFAULTS = [
  { key: 'technical_name', label: 'Techn. Name' },
  { key: 'name_de', label: 'Name' },
]

describe('useColumnConfig — dynamische Spalten', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('behaelt metadata.-Keys beim Laden aus dem localStorage', () => {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify(['technical_name', 'metadata.datenherkunft'])
    )

    const dynamic = ref([{ key: 'metadata.datenherkunft', label: 'Datenherkunft' }])
    const { visibleKeys } = useColumnConfig(STORAGE_KEY, DEFAULTS, [], dynamic)

    expect(visibleKeys.value).toContain('metadata.datenherkunft')
  })

  it('behaelt attributes.-Keys weiterhin', () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(['technical_name', 'attributes.farbe']))

    const { visibleKeys } = useColumnConfig(STORAGE_KEY, DEFAULTS, [], ref([]))

    expect(visibleKeys.value).toContain('attributes.farbe')
  })

  it('verwirft unbekannte statische Keys', () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(['technical_name', 'gibtesnicht']))

    const { visibleKeys } = useColumnConfig(STORAGE_KEY, DEFAULTS, [], ref([]))

    expect(visibleKeys.value).not.toContain('gibtesnicht')
    expect(visibleKeys.value).toContain('technical_name')
  })

  it('loest dynamische Spalten auf, sobald sie geladen sind', () => {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify(['technical_name', 'metadata.datenherkunft'])
    )

    const dynamic = ref([])
    const { visibleColumns } = useColumnConfig(STORAGE_KEY, DEFAULTS, [], dynamic)

    // Noch nicht geladen -> Spalte ist (noch) nicht sichtbar, der Key bleibt aber erhalten
    expect(visibleColumns.value.map(c => c.key)).toEqual(['technical_name'])

    dynamic.value = [{ key: 'metadata.datenherkunft', label: 'Datenherkunft' }]

    expect(visibleColumns.value.map(c => c.key)).toEqual([
      'technical_name',
      'metadata.datenherkunft',
    ])
  })
})
