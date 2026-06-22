/**
 * Smoke-Tests für die Social-Video-Ansicht und den Kamerafahrt-Editor.
 *
 * Zweck: Frühwarnung bei "View lädt nicht" (z.B. ein Setup-/TDZ-Fehler in
 * <script setup> bricht die gesamte Komponente ab). Die API-Module sind
 * gemockt, damit keine echten Requests nötig sind.
 */
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/socialVideo', () => ({
  default: {
    defaultMapping: () => Promise.resolve({
      data: {
        fields: [
          { target: 'hero_image', label: 'Aufmacherbild', kind: 'media', type: 'media_url', default: 'teaser' },
          { target: 'headline', label: 'Überschrift', kind: 'attribute', type: 'text', default: 'name' },
          { target: 'price', label: 'Preis', kind: 'price', type: 'price', default: 'list_price' },
        ],
      },
    }),
    productImages: () => Promise.resolve({ data: { images: [] } }),
    generate: vi.fn(),
    status: vi.fn(),
    downloadUrl: () => '#',
  },
}))
vi.mock('@/api/products', () => ({ default: { list: () => Promise.resolve({ data: { data: [] } }) } }))
vi.mock('@/api/attributes', () => ({ default: { list: () => Promise.resolve({ data: { data: [] } }) } }))
vi.mock('@/api/mediaUsageTypes', () => ({ default: { list: () => Promise.resolve({ data: { data: [] } }) } }))
vi.mock('@/api/prices', () => ({ priceTypes: { list: () => Promise.resolve({ data: { data: [] } }) } }))

import SocialVideoView from '@/views/publish/SocialVideoView.vue'
import CameraPathEditor from '@/views/publish/CameraPathEditor.vue'

const flush = () => new Promise((r) => setTimeout(r, 0))

describe('SocialVideoView', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('mountet ohne Setup-Fehler und zeigt den Erstellen-Button', async () => {
    const wrapper = mount(SocialVideoView)
    await flush()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.find('[data-testid="social-video-generate"]').exists()).toBe(true)
  })

  it('lädt die konfigurierbaren Inhalts-Felder aus dem Backend (nicht hartkodiert)', async () => {
    const wrapper = mount(SocialVideoView)
    await flush()
    // Pro Feld ein Quell-Dropdown mit data-testid social-video-source-<target>
    expect(wrapper.find('[data-testid="social-video-source-hero_image"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="social-video-source-headline"]').exists()).toBe(true)
  })
})

describe('CameraPathEditor', () => {
  it('mountet mit modelValue=null (Default-Fahrt)', () => {
    const wrapper = mount(CameraPathEditor, { props: { image: '/x.jpg', modelValue: null, format: '9x16' } })
    expect(wrapper.exists()).toBe(true)
  })

  it('mountet mit vorgegebener Fahrt', () => {
    const wrapper = mount(CameraPathEditor, {
      props: { image: '/x.jpg', modelValue: { from: { x: 30, y: 30, zoom: 1 }, to: { x: 60, y: 60, zoom: 1.5 } }, format: '9x16' },
    })
    expect(wrapper.exists()).toBe(true)
  })

  it('emittiert update:modelValue, wenn der Zoom geändert wird', async () => {
    const wrapper = mount(CameraPathEditor, { props: { image: '/x.jpg', modelValue: null, format: '9x16' } })
    const range = wrapper.find('input[type="range"]')
    await range.setValue(2)
    const events = wrapper.emitted('update:modelValue')
    expect(events).toBeTruthy()
    const last = events[events.length - 1][0]
    expect(last.from.zoom).toBe(2)
  })
})
