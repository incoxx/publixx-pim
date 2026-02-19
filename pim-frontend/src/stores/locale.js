import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

export const useLocaleStore = defineStore('locale', () => {
  const currentLocale = ref(localStorage.getItem('pim_locale') || 'de')
  const dataLocales = ref(['de', 'en', 'fr'])
  const activeDataLocales = ref(['de'])

  const availableLocales = computed(() => [
    { code: 'de', label: 'Deutsch', flag: 'DE' },
    { code: 'en', label: 'English', flag: 'EN' },
    { code: 'fr', label: 'Français', flag: 'FR' },
  ])

  function setUiLocale(locale) {
    currentLocale.value = locale
    localStorage.setItem('pim_locale', locale)
  }

  function setActiveDataLocales(locales) {
    activeDataLocales.value = locales
  }

  function toggleDataLocale(locale) {
    const idx = activeDataLocales.value.indexOf(locale)
    if (idx === -1) {
      activeDataLocales.value.push(locale)
    } else if (activeDataLocales.value.length > 1) {
      activeDataLocales.value.splice(idx, 1)
    }
  }

  function getLocalizedValue(obj, field, fallback = '') {
    if (!obj) return fallback
    for (const loc of [currentLocale.value, 'en', 'de']) {
      const key = `${field}_${loc}`
      if (obj[key]) return obj[key]
    }
    if (obj[`${field}_json`]) {
      try {
        const parsed = JSON.parse(obj[`${field}_json`])
        return parsed[currentLocale.value] || parsed.en || parsed.de || fallback
      } catch { /* ignore */ }
    }
    return obj[field] || fallback
  }

  return {
    currentLocale, dataLocales, activeDataLocales, availableLocales,
    setUiLocale, setActiveDataLocales, toggleDataLocale, getLocalizedValue,
  }
})
