/**
 * Tag-Eingabe (Chips + Type-Ahead) für Produkte und Medien.
 *
 * Kernzusagen:
 *  - Vorschläge kommen aus den Stammdaten, bereits vergebene fallen raus.
 *  - Neu anlegen nur mit tags.create und nur, wenn es den Tag nicht schon gibt.
 *  - Die Komponente ändert `modelValue` nie selbst, sie emittiert nur.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'

vi.mock('@/api/tags', () => {
  const tags = {
    list: vi.fn(() => Promise.resolve({
      data: {
        data: [
          { id: 't1', technical_name: 'neuheit', name_de: 'Neuheit', name_en: 'New' },
          { id: 't2', technical_name: 'aktion', name_de: 'Aktion', name_en: 'Promotion' },
          { id: 't3', technical_name: 'auslauf', name_de: 'Auslaufmodell', name_en: null },
        ],
      },
    })),
    create: vi.fn((payload) => Promise.resolve({
      data: { data: { id: 'neu', technical_name: 'sonderposten', name_de: payload.name_de } },
    })),
  }
  return { tags, default: tags }
})

import PimTagInput from '@/components/shared/PimTagInput.vue'
import { tags as tagsApi } from '@/api/tags'
import { useAuthStore } from '@/stores/auth'

const flush = () => new Promise((r) => setTimeout(r, 0))
const i18n = createI18n({ legacy: false, locale: 'de', messages: { de: {} } })

async function mountInput({ modelValue = [], permissions = ['tags.view', 'tags.create'] } = {}) {
  const authStore = useAuthStore()
  authStore.hasPermission = (p) => permissions.includes(p)

  const wrapper = mount(PimTagInput, {
    props: { modelValue },
    global: { plugins: [i18n] },
  })
  await flush()

  return wrapper
}

describe('PimTagInput', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('lädt nur aktive Tags als Auswahl', async () => {
    await mountInput()

    expect(tagsApi.list).toHaveBeenCalledWith(
      expect.objectContaining({ filters: { is_active: 1 } }),
    )
  })

  it('blendet bereits vergebene Tags aus den Vorschlägen aus', async () => {
    const wrapper = await mountInput({ modelValue: [{ id: 't1', name_de: 'Neuheit' }] })

    expect(wrapper.vm.suggestions.map(t => t.id)).toEqual(['t2', 't3'])
  })

  it('laesst den Server filtern statt clientseitig zu kappen', async () => {
    // Die API deckelt per_page auf 100 — clientseitiges Filtern haette ab dem
    // 101. Tag Werte verschwiegen und "anlegen" faelschlich angeboten.
    const wrapper = await mountInput()
    tagsApi.list.mockClear()

    await wrapper.find('input').setValue('auslauf')
    await new Promise((r) => setTimeout(r, 300))

    expect(tagsApi.list).toHaveBeenCalledWith(expect.objectContaining({ search: 'auslauf', perPage: 100 }))
  })

  it('uebernimmt die Servertreffer als Vorschlaege', async () => {
    const wrapper = await mountInput()

    // Erst nach dem Mount setzen — der initiale Aufruf verbraucht sonst den Mock
    tagsApi.list.mockResolvedValueOnce({ data: { data: [{ id: 't3', technical_name: 'auslauf', name_de: 'Auslaufmodell' }] } })
    await wrapper.vm.loadOptions('auslauf')
    await flush()

    expect(wrapper.vm.suggestions.map(t => t.id)).toEqual(['t3'])
  })

  it('emittiert die neue Auswahl statt modelValue zu verändern', async () => {
    const modelValue = []
    const wrapper = await mountInput({ modelValue })

    wrapper.vm.add({ id: 't2', name_de: 'Aktion' })
    await flush()

    expect(wrapper.emitted('update:modelValue')[0][0].map(t => t.id)).toEqual(['t2'])
    expect(modelValue).toEqual([])
  })

  it('entfernt einen Tag über sein Chip', async () => {
    const wrapper = await mountInput({ modelValue: [{ id: 't1', name_de: 'Neuheit' }, { id: 't2', name_de: 'Aktion' }] })

    wrapper.vm.remove('t1')
    await flush()

    expect(wrapper.emitted('update:modelValue')[0][0].map(t => t.id)).toEqual(['t2'])
  })

  it('bietet Anlegen nur an, wenn es den Tag nicht schon gibt', async () => {
    const wrapper = await mountInput()

    await wrapper.find('input').setValue('Sonderposten')
    expect(wrapper.vm.showCreateOption).toBe(true)

    await wrapper.find('input').setValue('Neuheit')
    expect(wrapper.vm.showCreateOption).toBe(false)
  })

  it('bietet Anlegen im Filter-Kontext nicht an', async () => {
    // Im Filter wird ausgewaehlt, nicht gepflegt — sonst legt ein Tippfehler
    // beim Filtern versehentlich einen neuen Tag an.
    const wrapper = mount(PimTagInput, {
      props: { modelValue: [], allowCreate: false },
      global: { plugins: [i18n] },
    })
    await flush()
    useAuthStore().hasPermission = () => true

    await wrapper.find('input').setValue('Sonderposten')
    expect(wrapper.vm.showCreateOption).toBe(false)
  })

  it('bietet Anlegen ohne tags.create nicht an', async () => {
    const wrapper = await mountInput({ permissions: ['tags.view'] })

    await wrapper.find('input').setValue('Sonderposten')
    expect(wrapper.vm.showCreateOption).toBe(false)
  })

  it('legt einen neuen Tag an und übernimmt ihn direkt', async () => {
    const wrapper = await mountInput()

    await wrapper.find('input').setValue('Sonderposten')
    await wrapper.vm.createTag()
    await flush()

    expect(tagsApi.create).toHaveBeenCalledWith({ name_de: 'Sonderposten' })
    expect(wrapper.emitted('update:modelValue')[0][0].map(t => t.id)).toEqual(['neu'])
  })

  it('übernimmt mit Enter den genauen Treffer', async () => {
    const wrapper = await mountInput()

    await wrapper.find('input').setValue('Neuheit')
    wrapper.vm.onEnter()
    await flush()

    expect(tagsApi.create).not.toHaveBeenCalled()
    expect(wrapper.emitted('update:modelValue')[0][0].map(t => t.id)).toEqual(['t1'])
  })

  it('zeigt im Lesemodus keine Eingabe', async () => {
    const wrapper = mount(PimTagInput, {
      props: { modelValue: [{ id: 't1', name_de: 'Neuheit' }], disabled: true },
      global: { plugins: [i18n] },
    })
    await flush()

    expect(wrapper.find('input').exists()).toBe(false)
    expect(wrapper.text()).toContain('Neuheit')
  })
})
