/**
 * Regressionsschutz gegen stillen Metadaten-Verlust im Attribut-Panel.
 *
 * Das Panel schickt fuer jede bekannte Definition einen Wert mit — ein nicht
 * gefuelltes Feld als `null`, was serverseitig die Zeile loescht. Das ist nur
 * korrekt, wenn der Ist-Zustand bekannt ist. Kommt das Attribut ohne
 * `metadata`-Schluessel herein (Aufrufer ohne include=metadataValues), darf
 * `metadata` gar nicht erst im Payload landen.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/attributes', () => ({
  default: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })), migrateLanguage: vi.fn() },
  attributeTypes: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  valueLists: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  formattingRules: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  productTypes: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  attributeMetadataDefinitions: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))
vi.mock('@/api/units', () => ({
  units: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  unitGroups: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))
vi.mock('@/api/comparisonOperators', () => ({
  comparisonOperatorGroups: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))

import AttributeFormPanel from '@/components/panels/AttributeFormPanel.vue'
import { useAttributeStore } from '@/stores/attributes'

const flush = () => new Promise((r) => setTimeout(r, 0))

const DEFINITIONS = [
  { id: 'd1', technical_name: 'datenherkunft', name_de: 'Datenherkunft', value_type: 'select', options: ['ERP', 'Agentur'] },
  { id: 'd2', technical_name: 'dateneigentuemer', name_de: 'Dateneigentümer', value_type: 'text' },
]

const BASE_ATTRIBUTE = {
  id: 'a1',
  technical_name: 'gewicht',
  name_de: 'Gewicht',
  data_type: 'String',
  is_translatable: false,
  is_multipliable: false,
  children: [],
}

/** Mountet das Panel und liefert den Store sowie das gemountete Wrapper-Objekt. */
async function mountPanel(attribute) {
  const store = useAttributeStore()
  store.metadataDefinitions = DEFINITIONS
  store.updateAttribute = vi.fn(() => Promise.resolve({ id: 'a1' }))
  store.createAttribute = vi.fn(() => Promise.resolve({ id: 'neu' }))
  store.fetchAttributes = vi.fn(() => Promise.resolve())

  const wrapper = mount(AttributeFormPanel, { props: { attribute } })
  await flush()

  return { store, wrapper }
}

/** Loest den Speichern-Button des eingebetteten PimForm aus. */
async function save(wrapper) {
  await wrapper.find('[data-testid="btn-save"]').trigger('submit')
  await flush()
}

describe('AttributeFormPanel — Metadaten im Payload', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('sendet metadata, wenn der Ist-Zustand bekannt ist', async () => {
    const { store, wrapper } = await mountPanel({
      ...BASE_ATTRIBUTE,
      metadata: { datenherkunft: 'ERP' },
    })

    await save(wrapper)

    expect(store.updateAttribute).toHaveBeenCalled()
    const payload = store.updateAttribute.mock.calls[0][1]
    expect(payload.metadata).toEqual({ datenherkunft: 'ERP', dateneigentuemer: null })
  })

  it('sendet metadata NICHT, wenn das Attribut ohne metadata-Schluessel kommt', async () => {
    const { store, wrapper } = await mountPanel({ ...BASE_ATTRIBUTE })

    await save(wrapper)

    expect(store.updateAttribute).toHaveBeenCalled()
    const payload = store.updateAttribute.mock.calls[0][1]
    expect(payload).not.toHaveProperty('metadata')
  })

  it('sendet metadata beim Neuanlegen, auch ohne Vorzustand', async () => {
    const { store, wrapper } = await mountPanel(null)

    await save(wrapper)

    expect(store.createAttribute).toHaveBeenCalled()
    const payload = store.createAttribute.mock.calls[0][0]
    expect(payload).toHaveProperty('metadata')
  })

  it('behaelt eine leere Metadaten-Map als bekannten Zustand', async () => {
    const { store, wrapper } = await mountPanel({ ...BASE_ATTRIBUTE, metadata: {} })

    await save(wrapper)

    const payload = store.updateAttribute.mock.calls[0][1]
    expect(payload.metadata).toEqual({ datenherkunft: null, dateneigentuemer: null })
  })

  it('schickt keine meta__-Schluessel als Attribut-Spalten mit', async () => {
    const { store, wrapper } = await mountPanel({
      ...BASE_ATTRIBUTE,
      metadata: { datenherkunft: 'ERP' },
    })

    await save(wrapper)

    const payload = store.updateAttribute.mock.calls[0][1]
    expect(Object.keys(payload).some(k => k.startsWith('meta__'))).toBe(false)
  })
})
