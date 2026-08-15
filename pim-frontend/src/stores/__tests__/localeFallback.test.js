import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useLocaleStore } from '../locale'

/**
 * Anzeige-Fallback im Produkteditor.
 *
 * Fehlt ein Wert in der gewählten Sprache, zeigt der Editor den Wert der
 * Fallback-Sprache ausgegraut an. Gespeichert wird dabei nichts — das wäre
 * gefährlich, weil ein gefülltes Zielfeld von Übersetzungs-Jobs und vom
 * XLIFF-Export als "übersetzt" gewertet wird.
 */
describe('Locale Store — Anzeige-Fallback', () => {
  let store

  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    store = useLocaleStore()

    // fi → en → de
    store.languages = [
      { code: 'de', label: 'Deutsch', is_source: true, is_active: true, fallback_language: null },
      { code: 'en', label: 'Englisch', is_active: true, fallback_language: 'de' },
      { code: 'fi', label: 'Finnisch', is_active: true, fallback_language: 'en' },
      { code: 'fr', label: 'Französisch', is_active: true, fallback_language: null },
    ]
  })

  describe('fallbackChain', () => {
    it('folgt der Kette über mehrere Stufen', () => {
      expect(store.fallbackChain('fi')).toEqual(['en', 'de'])
    })

    it('endet bei einer Sprache ohne Fallback', () => {
      expect(store.fallbackChain('en')).toEqual(['de'])
      expect(store.fallbackChain('de')).toEqual([])
      expect(store.fallbackChain('fr')).toEqual([])
    })

    it('bricht bei einem Zyklus ab, statt endlos zu laufen', () => {
      store.languages = [
        { code: 'fi', is_active: true, fallback_language: 'en' },
        { code: 'en', is_active: true, fallback_language: 'fi' },
      ]

      expect(store.fallbackChain('fi')).toEqual(['en'])
    })

    it('bricht bei einem Selbstbezug ab', () => {
      store.languages = [{ code: 'fi', is_active: true, fallback_language: 'fi' }]

      expect(store.fallbackChain('fi')).toEqual([])
    })

    it('verträgt eine unbekannte Sprache', () => {
      expect(store.fallbackChain('xx')).toEqual([])
    })
  })

  describe('resolveFallback', () => {
    const values = { en: 'Weight', de: 'Gewicht' }

    it('nimmt die erste Sprache der Kette, die einen Wert hat', () => {
      expect(store.resolveFallback('fi', l => values[l])).toEqual({ lang: 'en', value: 'Weight' })
    })

    it('überspringt leere Werte und geht weiter', () => {
      expect(store.resolveFallback('fi', l => (l === 'en' ? '' : values[l])))
        .toEqual({ lang: 'de', value: 'Gewicht' })
    })

    it('überspringt reine Leerzeichen', () => {
      expect(store.resolveFallback('fi', l => (l === 'en' ? '   ' : values[l])))
        .toEqual({ lang: 'de', value: 'Gewicht' })
    })

    it('liefert null, wenn die ganze Kette leer ist', () => {
      expect(store.resolveFallback('fi', () => '')).toBeNull()
      expect(store.resolveFallback('fi', () => undefined)).toBeNull()
    })

    it('liefert null ohne Fallback-Sprache', () => {
      expect(store.resolveFallback('fr', l => values[l])).toBeNull()
    })

    it('greift nie auf die eigene Sprache zurück', () => {
      // Sonst würde ein leeres Feld sich selbst als Fallback anzeigen
      const seen = []
      store.resolveFallback('fi', (l) => { seen.push(l); return '' })

      expect(seen).not.toContain('fi')
    })
  })
})
