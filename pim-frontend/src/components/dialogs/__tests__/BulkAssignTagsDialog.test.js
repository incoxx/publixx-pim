/**
 * Massenzuordnung von Tags (Profisuche und Merkliste).
 *
 * Kernzusagen:
 *  - Ohne Tag-Auswahl passiert nichts (kein Aufruf mit leerer Liste).
 *  - Der Modus wird mitgeschickt; "Ergänzen" ist die Voreinstellung, weil
 *    "Ersetzen" bei einer Massenoperation Daten vernichtet.
 *  - "Ersetzen" wird im Dialog ausdrücklich gewarnt.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'

vi.mock('@/api/tags', () => {
  const tags = {
    list: vi.fn(() => Promise.resolve({ data: { data: [{ id: 't1', technical_name: 'neuheit', name_de: 'Neuheit' }] } })),
    create: vi.fn(),
    bulkAssignProducts: vi.fn(() => Promise.resolve({ data: { message: '2 Produkt(e) aktualisiert.' } })),
  }
  return { tags, default: tags }
})

import BulkAssignTagsDialog from '@/components/dialogs/BulkAssignTagsDialog.vue'
import { tags as tagsApi } from '@/api/tags'
import { useAuthStore } from '@/stores/auth'

const flush = () => new Promise((r) => setTimeout(r, 0))
const i18n = createI18n({ legacy: false, locale: 'de', messages: { de: {} } })

async function mountDialog(productIds = ['p1', 'p2']) {
  const authStore = useAuthStore()
  authStore.hasPermission = () => true

  const wrapper = mount(BulkAssignTagsDialog, {
    props: { open: true, productIds },
    global: { plugins: [i18n], stubs: { teleport: true } },
  })
  await flush()

  return wrapper
}

describe('BulkAssignTagsDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('startet im Modus "Ergänzen"', async () => {
    const wrapper = await mountDialog()

    expect(wrapper.vm.mode).toBe('add')
  })

  it('speichert nicht ohne ausgewählte Tags', async () => {
    const wrapper = await mountDialog()

    expect(wrapper.vm.canSave).toBe(false)
    await wrapper.vm.save()

    expect(tagsApi.bulkAssignProducts).not.toHaveBeenCalled()
  })

  it('speichert nicht ohne ausgewählte Produkte', async () => {
    const wrapper = await mountDialog([])
    wrapper.vm.selectedTags = [{ id: 't1', name_de: 'Neuheit' }]
    await flush()

    expect(wrapper.vm.canSave).toBe(false)
  })

  it('schickt Produkte, Tags und Modus an die Massenoperation', async () => {
    const wrapper = await mountDialog()
    wrapper.vm.selectedTags = [{ id: 't1', name_de: 'Neuheit' }]
    wrapper.vm.mode = 'remove'
    await flush()

    await wrapper.vm.save()

    expect(tagsApi.bulkAssignProducts).toHaveBeenCalledWith(['p1', 'p2'], ['t1'], 'remove')
    expect(wrapper.emitted('assigned')).toBeTruthy()
  })

  it('warnt sichtbar vor dem Ersetzen', async () => {
    const wrapper = await mountDialog()

    expect(wrapper.text()).not.toContain('werden entfernt und durch die Auswahl ersetzt')

    wrapper.vm.mode = 'replace'
    await flush()

    expect(wrapper.text()).toContain('werden entfernt und durch die Auswahl ersetzt')
  })

  it('zeigt einen Fehler statt still zu scheitern', async () => {
    tagsApi.bulkAssignProducts.mockRejectedValueOnce({ response: { data: { message: 'Keine Berechtigung' } } })
    const wrapper = await mountDialog()
    wrapper.vm.selectedTags = [{ id: 't1', name_de: 'Neuheit' }]
    await flush()

    await wrapper.vm.save()
    await flush()

    expect(wrapper.text()).toContain('Keine Berechtigung')
  })
})
