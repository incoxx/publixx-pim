import { computed } from 'vue'
import { useLocaleStore } from '@/stores/locale'

export function useLocale() {
  const store = useLocaleStore()

  function localized(obj, field, fallback = '') {
    return store.getLocalizedValue(obj, field, fallback)
  }

  const currentLocale = computed(() => store.currentLocale)
  const activeDataLocales = computed(() => store.activeDataLocales)

  return { localized, currentLocale, activeDataLocales }
}
