/**
 * Tag-Verwaltung (CRUD-Dialog).
 *
 * Kernzusagen:
 *  - Die Eingabefelder für weitere Sprachen kommen aus der Sprachverwaltung,
 *    nicht aus einer festen Liste — eine neu konfigurierte Sprache taucht sofort auf.
 *  - Leere Übersetzungen landen nicht als leere Strings in name_json.
 *  - Der technische Name wird beim Anlegen nicht mitgeschickt, wenn er leer ist
 *    (das Backend leitet ihn dann aus name_de ab).
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

vi.mock('@/api/tags', () => {
  const tags = {
    list: vi.fn(() => Promise.resolve({
      data: {
        data: [{
          id: 't1', technical_name: 'neuheit', name_de: 'Neuheit', name_en: 'New',
          name_json: { fr: 'Nouveauté' }, sort_order: 0, is_active: true,
          products_count: 2, media_count: 1,
        }],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 25 },
      },
    })),
    create: vi.fn(() => Promise.resolve({ data: { data: {} } })),
    update: vi.fn(() => Promise.resolve({ data: { data: {} } })),
    delete: vi.fn(() => Promise.resolve({})),
  }
  return { tags, default: tags }
})

import TagView from '@/views/tags/TagView.vue'
import { tags as tagsApi } from '@/api/tags'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const flush = () => new Promise((r) => setTimeout(r, 0))
const i18n = createI18n({ legacy: false, locale: 'de', messages: { de: {} } })

async function mountView() {
  const authStore = useAuthStore()
  authStore.hasPermission = () => true

  const localeStore = useLocaleStore()
  localeStore.fetchLanguages = vi.fn()
  localeStore.languages = [
    { code: 'de', label: 'Deutsch', is_active: true, is_source: true },
    { code: 'en', label: 'Englisch', is_active: true },
    { code: 'fr', label: 'Französisch', is_active: true },
    { code: 'it', label: 'Italienisch', is_active: true },
  ]

  const wrapper = mount(TagView, { global: { plugins: [i18n], stubs: { teleport: true } } })
  await flush()

  return { wrapper, authStore, localeStore }
}

describe('TagView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('lädt Tags und zeigt die Verwendung je Tag', async () => {
    const { wrapper } = await mountView()

    expect(tagsApi.list).toHaveBeenCalled()
    expect(wrapper.vm.tableRows[0].usage_display).toBe('2 Produkte · 1 Medien')
    expect(wrapper.vm.tableRows[0].further_languages).toBe('FR: Nouveauté')
  })

  it('bietet Eingabefelder für alle konfigurierten Sprachen außer DE/EN', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.vm.extraLocales.map(l => l.code)).toEqual(['fr', 'it'])
  })

  it('schickt beim Anlegen keinen leeren technischen Namen mit', async () => {
    const { wrapper } = await mountView()

    wrapper.vm.openForm()
    wrapper.vm.formData.name_de = 'Für Außenbereich'
    await wrapper.vm.saveForm()

    const payload = tagsApi.create.mock.calls[0][0]
    expect(payload).not.toHaveProperty('technical_name')
    expect(payload.name_de).toBe('Für Außenbereich')
  })

  it('filtert leere Übersetzungen aus name_json heraus', async () => {
    const { wrapper } = await mountView()

    wrapper.vm.openForm()
    wrapper.vm.formData.name_de = 'Neuheit'
    wrapper.vm.formData.name_json = { fr: 'Nouveauté', it: '   ' }
    await wrapper.vm.saveForm()

    expect(tagsApi.create.mock.calls[0][0].name_json).toEqual({ fr: 'Nouveauté' })
  })

  it('sendet name_json als null, wenn keine Übersetzung gepflegt ist', async () => {
    const { wrapper } = await mountView()

    wrapper.vm.openForm()
    wrapper.vm.formData.name_de = 'Neuheit'
    await wrapper.vm.saveForm()

    expect(tagsApi.create.mock.calls[0][0].name_json).toBeNull()
  })

  it('übernimmt beim Bearbeiten die bestehenden Übersetzungen ins Formular', async () => {
    const { wrapper } = await mountView()

    wrapper.vm.openForm(wrapper.vm.items[0])

    expect(wrapper.vm.editId).toBe('t1')
    expect(wrapper.vm.formData.name_json).toEqual({ fr: 'Nouveauté' })
    expect(wrapper.vm.formData.technical_name).toBe('neuheit')
  })

  it('zeigt Validierungsfehler des Backends am Feld an', async () => {
    tagsApi.create.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { name_de: ['Pflichtfeld'] } } },
    })
    const { wrapper } = await mountView()

    wrapper.vm.openForm()
    await wrapper.vm.saveForm()

    expect(wrapper.vm.formErrors.name_de).toBe('Pflichtfeld')
    expect(wrapper.vm.showForm).toBe(true)
  })
})
