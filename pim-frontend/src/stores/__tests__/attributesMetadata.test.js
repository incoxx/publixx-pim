/**
 * Regressionsschutz für die Metadaten-Anbindung im Attribut-Store.
 *
 * Hintergrund: Fehlt `metadataValues` im Include, liefern die Listenzeilen kein
 * `metadata`. Das Attribut-Panel liest den Ist-Zustand aus genau dieser Zeile —
 * ohne ihn hielte es beim nächsten Speichern jedes Feld für geleert.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/api/attributes', () => ({
  default: {
    list: vi.fn(() => Promise.resolve({ data: { data: [] } })),
    create: vi.fn(),
    update: vi.fn(),
    copy: vi.fn(),
    delete: vi.fn(),
    allIds: vi.fn(),
    bulkUpdate: vi.fn(),
    bulkDelete: vi.fn(),
    bulkAssignViews: vi.fn(),
  },
  attributeTypes: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  valueLists: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  formattingRules: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  productTypes: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  attributeMetadataDefinitions: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))
vi.mock('@/api/units', () => ({ unitGroups: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) } }))
vi.mock('@/api/comparisonOperators', () => ({
  comparisonOperatorGroups: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))

import attributesApi, { attributeMetadataDefinitions } from '@/api/attributes'
import { useAttributeStore } from '../attributes'

describe('Attribut-Store: Metadaten', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    store = useAttributeStore()
  })

  it('fordert metadataValues auch ohne explizite Optionen an', async () => {
    attributesApi.list.mockResolvedValue({ data: { data: [] } })

    await store.fetchAttributes()

    const params = attributesApi.list.mock.calls[0][0]
    expect(params.include).toContain('metadataValues')
  })

  it('laesst einen expliziten Include unveraendert', async () => {
    attributesApi.list.mockResolvedValue({ data: { data: [] } })

    await store.fetchAttributes({ include: 'children,metadataValues' })

    const params = attributesApi.list.mock.calls[0][0]
    expect(params.include).toBe('children,metadataValues')
  })

  it('laedt die Metadaten-Definitionen nach sort_order', async () => {
    attributeMetadataDefinitions.list.mockResolvedValue({
      data: { data: [{ id: 'd1', technical_name: 'datenherkunft' }] },
    })

    await store.fetchMetadataDefinitions()

    expect(store.metadataDefinitions).toHaveLength(1)
    expect(attributeMetadataDefinitions.list.mock.calls[0][0].sort).toBe('sort_order')
  })

  it('blockiert das Formular nicht, wenn die Definitionen nicht ladbar sind', async () => {
    attributeMetadataDefinitions.list.mockRejectedValue({ response: { status: 403 } })

    await store.fetchMetadataDefinitions()

    expect(store.metadataDefinitions).toEqual([])
  })
})
