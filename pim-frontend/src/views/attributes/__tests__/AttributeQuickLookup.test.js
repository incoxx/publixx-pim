/**
 * Quick Lookup in der Attributliste.
 *
 * Kernzusage: die Konfiguration wird aus den sichtbaren Spalten abgeleitet, es
 * gibt also fuer JEDE filterbare Spalte ein Eingabefeld — auch fuer jedes neu
 * angelegte Metadatum. Frueher waren nur vier Spalten von Hand eingetragen.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}))

// Hinweis: vi.mock-Factories werden an den Dateianfang gehoben und duerfen
// keine Variablen von aussen verwenden — daher die Helfer jeweils inline.
vi.mock('@/api/attributes', () => {
  const empty = () => Promise.resolve({ data: { data: [] } })
  return {
    default: {
      list: vi.fn(empty),
      allIds: vi.fn(() => Promise.resolve({ data: { ids: [] } })),
      copy: vi.fn(),
      delete: vi.fn(),
      bulkDelete: vi.fn(),
      bulkUpdate: vi.fn(),
      bulkAssignViews: vi.fn(),
    },
    attributeTypes: { list: vi.fn(empty) },
    valueLists: { list: vi.fn(empty) },
    formattingRules: { list: vi.fn(empty) },
    productTypes: { list: vi.fn(empty) },
    attributeMetadataDefinitions: { list: vi.fn(empty) },
    attributeViews: {
      list: vi.fn(() => Promise.resolve({
        data: { data: [{ id: 'v1', technical_name: 'basis', name_de: 'Basisdaten' }] },
      })),
    },
  }
})
vi.mock('@/api/hierarchies', () => {
  const empty = () => Promise.resolve({ data: { data: [] } })
  return { default: { list: vi.fn(empty), getTree: vi.fn(empty) } }
})
vi.mock('@/api/units', () => {
  const empty = () => Promise.resolve({ data: { data: [] } })
  return { unitGroups: { list: vi.fn(empty) } }
})
vi.mock('@/api/comparisonOperators', () => {
  const empty = () => Promise.resolve({ data: { data: [] } })
  return { comparisonOperatorGroups: { list: vi.fn(empty) } }
})

import AttributeAdminView from '@/views/attributes/AttributeAdminView.vue'
import { useAttributeStore } from '@/stores/attributes'

const flush = () => new Promise((r) => setTimeout(r, 0))

const i18n = createI18n({ legacy: false, locale: 'de', messages: { de: {} } })

const DEFINITIONS = [
  {
    id: 'd1', technical_name: 'quellsystem', name_de: 'Quellsystem',
    value_type: 'select', options: ['Sap', 'Agentur'],
  },
  { id: 'd2', technical_name: 'dateneigentuemer', name_de: 'Dateneigentümer', value_type: 'text' },
]

async function mountView() {
  const store = useAttributeStore()
  store.metadataDefinitions = DEFINITIONS
  store.fetchMetadataDefinitions = vi.fn()
  store.fetchAttributes = vi.fn(() => Promise.resolve())
  store.fetchTypes = vi.fn()
  store.fetchValueLists = vi.fn()

  const wrapper = mount(AttributeAdminView, {
    global: { plugins: [i18n], stubs: { teleport: true } },
  })
  await flush()

  return { store, wrapper }
}

describe('AttributeAdminView — Quick Lookup', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('belegt jede sichtbare Standardspalte mit einem Lookup-Feld', async () => {
    const { wrapper } = await mountView()

    const entries = wrapper.vm.quickLookupEntries
    const visible = wrapper.vm.visibleColumns.map(c => c.key)

    // Keine Standardspalte darf ohne Eingabefeld bleiben
    const ohneLookup = visible.filter(k => !entries[k])
    expect(ohneLookup).toEqual([])
  })

  it('bildet Spaltenschluessel korrekt auf Backend-Filter ab', async () => {
    const { wrapper } = await mountView()
    const entries = wrapper.vm.quickLookupEntries

    expect(entries['technical_name'].filterKey).toBe('technical_name')
    expect(entries['attribute_type.name_de'].filterKey).toBe('attribute_type_id')
    expect(entries['_children'].filterKey).toBe('has_children')
    expect(entries['_views'].filterKey).toBe('attribute_view')
    expect(entries['is_unique'].filterKey).toBe('is_unique')
    expect(entries['description_de'].filterKey).toBe('description_de')
  })

  it('nimmt Metadaten-Spalten auf, sobald sie eingeblendet sind', async () => {
    const { wrapper } = await mountView()

    wrapper.vm.toggleColumn('metadata.quellsystem')
    wrapper.vm.toggleColumn('metadata.dateneigentuemer')
    await flush()

    const entries = wrapper.vm.quickLookupEntries

    // Auswahl-Metadatum -> Dropdown mit den gepflegten Werten
    expect(entries['metadata.quellsystem'].filterKey).toBe('meta:quellsystem')
    expect(entries['metadata.quellsystem'].type).toBe('select')
    expect(entries['metadata.quellsystem'].options.map(o => o.value))
      .toEqual(expect.arrayContaining(['__none__', '__any__', 'Sap', 'Agentur']))

    // Freitext-Metadatum -> Texteingabe
    expect(entries['metadata.dateneigentuemer'].filterKey).toBe('meta:dateneigentuemer')
    expect(entries['metadata.dateneigentuemer'].type).toBe('text')
  })

  it('uebersetzt eine Lookup-Eingabe in den passenden Backend-Filter', async () => {
    const { store, wrapper } = await mountView()

    wrapper.vm.toggleColumn('metadata.quellsystem')
    await flush()

    wrapper.vm.applyQuickLookupFilters({
      'metadata.quellsystem': 'Agentur',
      is_unique: '1',
      leer: '',
    })
    await flush()

    expect(wrapper.vm.quickLookupMappedFilters).toEqual({
      'meta:quellsystem': 'Agentur',
      is_unique: '1',
    })
    expect(store.fetchAttributes).toHaveBeenCalled()
  })

  it('liefert die Sichten-Auswahl anhand technischer Namen', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.vm.quickLookupEntries['_views'].options).toEqual([
      { value: 'basis', label: 'Basisdaten' },
    ])
  })
})
