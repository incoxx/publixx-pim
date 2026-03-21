import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useLocaleStore } from '../locale'

describe('Locale Store', () => {
  let store

  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    store = useLocaleStore()
  })

  describe('initial state', () => {
    it('defaults to de locale', () => {
      expect(store.currentLocale).toBe('de')
    })

    it('has 3 available locales', () => {
      expect(store.availableLocales).toHaveLength(3)
      expect(store.availableLocales.map(l => l.code)).toEqual(['de', 'en', 'fr'])
    })

    it('all data locales are active by default', () => {
      expect(store.activeDataLocales).toEqual(['de', 'en', 'fr'])
    })
  })

  describe('setUiLocale', () => {
    it('updates current locale', () => {
      store.setUiLocale('en')
      expect(store.currentLocale).toBe('en')
    })

    it('persists to localStorage', () => {
      store.setUiLocale('fr')
      expect(localStorage.getItem('pim_locale')).toBe('fr')
    })
  })

  describe('toggleDataLocale', () => {
    it('removes locale when active', () => {
      store.toggleDataLocale('fr')
      expect(store.activeDataLocales).toEqual(['de', 'en'])
    })

    it('adds locale when inactive', () => {
      store.activeDataLocales = ['de']
      store.toggleDataLocale('en')
      expect(store.activeDataLocales).toContain('en')
    })

    it('prevents removing last locale', () => {
      store.activeDataLocales = ['de']
      store.toggleDataLocale('de')
      expect(store.activeDataLocales).toEqual(['de'])
    })
  })

  describe('getLocalizedValue', () => {
    it('returns value for current locale', () => {
      store.setUiLocale('de')
      const obj = { name_de: 'Deutsch', name_en: 'English' }
      expect(store.getLocalizedValue(obj, 'name')).toBe('Deutsch')
    })

    it('falls back to en when current locale missing', () => {
      store.setUiLocale('fr')
      const obj = { name_en: 'English' }
      expect(store.getLocalizedValue(obj, 'name')).toBe('English')
    })

    it('falls back to de when en also missing', () => {
      store.setUiLocale('fr')
      const obj = { name_de: 'Deutsch' }
      expect(store.getLocalizedValue(obj, 'name')).toBe('Deutsch')
    })

    it('parses JSON field as fallback', () => {
      store.setUiLocale('de')
      const obj = { name_json: '{"de": "JSON Deutsch", "en": "JSON English"}' }
      expect(store.getLocalizedValue(obj, 'name')).toBe('JSON Deutsch')
    })

    it('returns fallback when nothing found', () => {
      const obj = {}
      expect(store.getLocalizedValue(obj, 'name', 'N/A')).toBe('N/A')
    })

    it('returns fallback for null object', () => {
      expect(store.getLocalizedValue(null, 'name', 'default')).toBe('default')
    })
  })
})
