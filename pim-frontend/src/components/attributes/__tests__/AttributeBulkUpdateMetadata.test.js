/**
 * Metadaten in der Massenbearbeitung.
 *
 * Kernregel: nur angehakte Felder gehen mit. Ein angehaktes, leeres Feld muss
 * den leeren Wert *senden* (loescht das Metadatum serverseitig) statt still
 * weggelassen zu werden.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/attributes', () => ({
  default: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  attributeTypes: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  valueLists: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  formattingRules: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  productTypes: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  attributeMetadataDefinitions: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
  attributeViews: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))
vi.mock('@/api/units', () => ({
  unitGroups: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))
vi.mock('@/api/comparisonOperators', () => ({
  comparisonOperatorGroups: { list: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))

import AttributeBulkUpdateDialog from '@/components/attributes/AttributeBulkUpdateDialog.vue'
import { useAttributeStore } from '@/stores/attributes'

const flush = () => new Promise((r) => setTimeout(r, 0))

const DEFINITIONS = [
  {
    id: 'd1', technical_name: 'datenherkunft', name_de: 'Datenherkunft',
    value_type: 'select', options: ['ERP', 'Agentur'],
  },
  { id: 'd2', technical_name: 'dateneigentuemer', name_de: 'Dateneigentümer', value_type: 'text' },
]

async function mountDialog() {
  const store = useAttributeStore()
  store.metadataDefinitions = DEFINITIONS
  store.fetchMetadataDefinitions = vi.fn()
  store.bulkUpdate = vi.fn(() => Promise.resolve())
  store.bulkAssignViews = vi.fn(() => Promise.resolve())

  const wrapper = mount(AttributeBulkUpdateDialog, {
    props: { open: true, selectedIds: ['a1', 'a2'] },
  })
  await flush()

  return { store, wrapper }
}

// Der Dialog rendert per <teleport to="body">; der Wrapper selbst ist leer.
async function apply(wrapper) {
  await wrapper.vm.apply()
  await flush()
}

function renderedText() {
  return document.body.textContent || ''
}

describe('AttributeBulkUpdateDialog — Metadaten', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    // Teleportierte Inhalte bleiben sonst zwischen den Tests im Body stehen
    document.body.innerHTML = ''
  })

  it('schickt nur angehakte Metadaten mit', async () => {
    const { store, wrapper } = await mountDialog()

    wrapper.vm.metaFields.datenherkunft.enabled = true
    wrapper.vm.metaFields.datenherkunft.value = 'ERP'
    await flush()

    await apply(wrapper)

    expect(store.bulkUpdate).toHaveBeenCalled()
    const [ids, fields] = store.bulkUpdate.mock.calls[0]
    expect(ids).toEqual(['a1', 'a2'])
    expect(fields.metadata).toEqual({ datenherkunft: 'ERP' })
  })

  it('sendet ein angehaktes leeres Feld als Loeschbefehl', async () => {
    const { store, wrapper } = await mountDialog()

    wrapper.vm.metaFields.dateneigentuemer.enabled = true
    wrapper.vm.metaFields.dateneigentuemer.value = ''
    await flush()

    await apply(wrapper)

    const [, fields] = store.bulkUpdate.mock.calls[0]
    expect(fields.metadata).toHaveProperty('dateneigentuemer', '')
  })

  it('schickt gar kein metadata, wenn nichts angehakt ist', async () => {
    const { store, wrapper } = await mountDialog()

    wrapper.vm.fields.is_searchable.enabled = true
    await flush()

    await apply(wrapper)

    const [, fields] = store.bulkUpdate.mock.calls[0]
    expect(fields).not.toHaveProperty('metadata')
    expect(fields).toHaveProperty('is_searchable')
  })

  it('aktiviert Anwenden auch bei reiner Metadaten-Auswahl', async () => {
    const { wrapper } = await mountDialog()

    expect(wrapper.vm.hasEnabledFields).toBe(false)

    wrapper.vm.metaFields.datenherkunft.enabled = true
    await flush()

    expect(wrapper.vm.hasEnabledFields).toBe(true)
  })

  it('warnt vor dem Leeren angehakter Metadaten', async () => {
    const { wrapper } = await mountDialog()

    wrapper.vm.metaFields.dateneigentuemer.enabled = true
    wrapper.vm.metaFields.dateneigentuemer.value = ''
    await flush()

    expect(wrapper.vm.clearingMetaLabels).toEqual(['Dateneigentümer'])
    expect(renderedText()).toContain('Wird geleert und damit entfernt')
  })
})
