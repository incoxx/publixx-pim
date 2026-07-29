import { describe, it, expect } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { useLocalizedName } from '../useLocalizedName'

function withLocale(locale, callback) {
  const i18n = createI18n({ legacy: false, locale, messages: { de: {}, en: {} } })
  let result
  const TestComponent = defineComponent({
    setup() {
      const { localizedName } = useLocalizedName()
      result = callback(localizedName)
      return () => h('div')
    },
  })
  mount(TestComponent, { global: { plugins: [i18n] } })
  return result
}

describe('useLocalizedName', () => {
  const gewicht = { name_de: 'Gewicht', name_en: 'Weight' }
  const laenge = { name_de: 'Länge', name_en: '' }
  const laengeNull = { name_de: 'Länge', name_en: null }

  it('zeigt name_de bei UI-Sprache Deutsch, auch wenn name_en vorhanden ist', () => {
    const value = withLocale('de', (localizedName) => localizedName(gewicht))
    expect(value).toBe('Gewicht')
  })

  it('zeigt name_en bei UI-Sprache Englisch, wenn vorhanden', () => {
    const value = withLocale('en', (localizedName) => localizedName(gewicht))
    expect(value).toBe('Weight')
  })

  it('faellt bei fehlendem name_en auf name_de mit Sternchen zurueck', () => {
    const value = withLocale('en', (localizedName) => localizedName(laenge))
    expect(value).toBe('*Länge*')
  })

  it('behandelt name_en === null wie leer', () => {
    const value = withLocale('en', (localizedName) => localizedName(laengeNull))
    expect(value).toBe('*Länge*')
  })

  it('zeigt name_de unveraendert bei UI-Sprache Deutsch, wenn name_en fehlt', () => {
    const value = withLocale('de', (localizedName) => localizedName(laenge))
    expect(value).toBe('Länge')
  })

  it('unterstuetzt ein alternatives Feld-Prefix (z.B. display_value)', () => {
    const entry = { display_value_de: 'Rot', display_value_en: 'Red' }
    const value = withLocale('en', (localizedName) => localizedName(entry, 'display_value'))
    expect(value).toBe('Red')
  })

  it('gibt leeren String zurueck, wenn beide Felder fehlen', () => {
    const value = withLocale('en', (localizedName) => localizedName({}))
    expect(value).toBe('')
  })

  it('gibt leeren String zurueck, wenn die Entitaet null/undefined ist', () => {
    expect(withLocale('en', (localizedName) => localizedName(null))).toBe('')
    expect(withLocale('en', (localizedName) => localizedName(undefined))).toBe('')
  })
})
