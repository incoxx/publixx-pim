import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import languagesApi from '@/api/languages'

/**
 * Sprachen des PIM.
 *
 * Einzige Quelle im Frontend. Die Liste kommt aus der Tabelle `languages`
 * (GUI: Einstellungen → Sprachen). Vorher war sie an vier Stellen hart
 * kodiert — unter anderem im Produkteditor —, sodass eine per Konfiguration
 * ergänzte Sprache in der Oberfläche schlicht nicht auftauchte.
 *
 * `fallbackLocales` greift nur, solange die API noch nicht geantwortet hat
 * oder nicht erreichbar ist. Sie ist bewusst minimal: sie soll die Oberfläche
 * bedienbar halten, nicht die Konfiguration ersetzen.
 */
const fallbackLocales = [
  { code: 'de', label: 'Deutsch', is_source: true },
]

export const useLocaleStore = defineStore('locale', () => {
  // Sprache der Oberfläche (nicht der Daten) — davon unabhängig
  const currentLocale = ref(localStorage.getItem('pim_locale') || 'de')

  const languages = ref([...fallbackLocales])
  const loaded = ref(false)
  const loading = ref(false)

  /** Alle gepflegten Sprachen, auch inaktive (für die Verwaltung). */
  const allLanguages = computed(() => languages.value)

  /** Aktive Sprachen — das, was Redakteure überall sehen sollen. */
  const availableLocales = computed(() =>
    languages.value
      .filter(l => l.is_active !== false)
      .map(l => ({
        code: l.code,
        label: l.label,
        flag: l.code.toUpperCase(),
        is_source: !!l.is_source,
      })),
  )

  const sourceLocale = computed(
    () => availableLocales.value.find(l => l.is_source)?.code || 'de',
  )

  /** Codes aller aktiven Sprachen — Basis für Produkteditor und Filter. */
  const dataLocales = computed(() => availableLocales.value.map(l => l.code))

  // Vom Benutzer im Produkteditor eingeblendete Sprachen. Wird beim Laden
  // auf die aktiven Sprachen gesetzt, bleibt danach seine Auswahl.
  const activeDataLocales = ref([])
  const userPickedLocales = ref(false)

  function labelFor(code) {
    return languages.value.find(l => l.code === code)?.label || code.toUpperCase()
  }

  /**
   * Lädt die Sprachen. Idempotent — mehrfacher Aufruf lädt nur einmal,
   * `force` erzwingt das Neuladen (nach Änderungen in der Verwaltung).
   */
  async function fetchLanguages(force = false) {
    if (loading.value) return
    if (loaded.value && !force) return

    loading.value = true
    try {
      const { data } = await languagesApi.list()
      const rows = data?.data ?? []

      if (rows.length) {
        languages.value = rows.map(r => ({
          id: r.id,
          code: r.technical_name,
          label: r.name_de || r.technical_name,
          name_en: r.name_en,
          is_source: !!r.is_source,
          is_active: r.is_active !== false,
          sort_order: r.sort_order ?? 0,
        }))
      }

      loaded.value = true

      // Auswahl des Benutzers nicht überschreiben; nur initial befüllen und
      // inzwischen entfernte/deaktivierte Sprachen aussortieren.
      const active = dataLocales.value
      if (!userPickedLocales.value || activeDataLocales.value.length === 0) {
        activeDataLocales.value = [...active]
      } else {
        activeDataLocales.value = activeDataLocales.value.filter(c => active.includes(c))
        if (activeDataLocales.value.length === 0) activeDataLocales.value = [...active]
      }
    } catch (e) {
      // Ohne Sprachen wäre der Produkteditor unbedienbar — Fallback behalten
      console.warn('Sprachen konnten nicht geladen werden, Fallback aktiv.', e)
      if (activeDataLocales.value.length === 0) {
        activeDataLocales.value = languages.value.map(l => l.code)
      }
    } finally {
      loading.value = false
    }
  }

  function setUiLocale(locale) {
    currentLocale.value = locale
    localStorage.setItem('pim_locale', locale)
  }

  function setActiveDataLocales(locales) {
    userPickedLocales.value = true
    activeDataLocales.value = locales
  }

  function toggleDataLocale(locale) {
    userPickedLocales.value = true
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
        const raw = obj[`${field}_json`]
        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw
        return parsed[currentLocale.value] || parsed.en || parsed.de || fallback
      } catch (e) { console.warn('Failed to parse localized JSON field', e) }
    }
    return obj[field] || fallback
  }

  return {
    currentLocale, languages, allLanguages, loaded, loading,
    dataLocales, activeDataLocales, availableLocales, sourceLocale,
    fetchLanguages, labelFor,
    setUiLocale, setActiveDataLocales, toggleDataLocale, getLocalizedValue,
  }
})
