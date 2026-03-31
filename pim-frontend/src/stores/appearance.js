import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import userPreferencesApi from '@/api/userPreferences'
import client from '@/api/client'

const STORAGE_KEY = 'pim_appearance'
const DEFAULT_PRESET = 'dark-navy'

const PRESETS = {
  light: {
    sidebar_bg: '#F4F6FA',
    sidebar_text: '#4B5563',
    sidebar_icon: '#6B7280',
    sidebar_active_bg: '#E8F0FE',
    sidebar_active_text: '#2E75B6',
    sidebar_font_size: 13,
    sidebar_colored_icons: false,
    toolbar_bg: '#F4F6FA',
    toolbar_text: '#111827',
    toolbar_font_size: 14,
  },
  'dark-navy': {
    sidebar_bg: '#1B2A4A',
    sidebar_text: '#CBD5E1',
    sidebar_icon: '#60A5FA',
    sidebar_active_bg: '#233B6E',
    sidebar_active_text: '#93C5FD',
    sidebar_font_size: 13,
    sidebar_colored_icons: true,
    toolbar_bg: '#1B2A4A',
    toolbar_text: '#E2E8F0',
    toolbar_font_size: 14,
  },
  'dark-charcoal': {
    sidebar_bg: '#1E1E2E',
    sidebar_text: '#CDD6F4',
    sidebar_icon: '#89B4FA',
    sidebar_active_bg: '#313244',
    sidebar_active_text: '#89DCEB',
    sidebar_font_size: 13,
    sidebar_colored_icons: true,
    toolbar_bg: '#181825',
    toolbar_text: '#CDD6F4',
    toolbar_font_size: 14,
  },
}

// Farbige Icon-Farben pro Menü-Sektion
const SECTION_ICON_COLORS = {
  daily: '#60A5FA',      // blau
  publish: '#34D399',    // grün
  plugins: '#FB923C',    // orange
  translations: '#A78BFA', // lila
  project: '#F472B6',   // pink
  config: '#FBBF24',    // gelb
  admin: '#F87171',      // rot
  help: '#94A3B8',       // grau
}

const CSS_VAR_MAP = {
  sidebar_bg: '--pim-sidebar-bg',
  sidebar_text: '--pim-sidebar-text',
  sidebar_icon: '--pim-sidebar-icon',
  sidebar_active_bg: '--pim-sidebar-active-bg',
  sidebar_active_text: '--pim-sidebar-active-text',
  sidebar_font_size: '--pim-sidebar-font-size',
  toolbar_bg: '--pim-toolbar-bg',
  toolbar_text: '--pim-toolbar-text',
  toolbar_font_size: '--pim-toolbar-font-size',
}

export { PRESETS, SECTION_ICON_COLORS }

export const useAppearanceStore = defineStore('appearance', () => {
  const preset = ref(DEFAULT_PRESET)
  const settings = ref({ ...PRESETS[DEFAULT_PRESET] })
  const loaded = ref(false)
  const enforced = ref(false) // Firmen-CI aktiv?

  const sidebarColoredIcons = computed(() => settings.value.sidebar_colored_icons)

  function applyTheme() {
    const root = document.documentElement.style
    for (const [key, cssVar] of Object.entries(CSS_VAR_MAP)) {
      const val = settings.value[key]
      if (val != null) {
        root.setProperty(cssVar, key.includes('font_size') ? `${val}px` : val)
      }
    }
    // Sidebar-Border: automatisch abgeleitet (heller/dunkler je nach BG)
    const bg = settings.value.sidebar_bg || '#F4F6FA'
    const isDark = isColorDark(bg)
    root.setProperty('--pim-sidebar-border', isDark
      ? lighten(bg, 15)
      : 'var(--color-border)')
    // Sidebar hover BG
    root.setProperty('--pim-sidebar-hover-bg', isDark
      ? lighten(bg, 8)
      : 'var(--color-bg)')

    // Toolbar border
    const toolbarBg = settings.value.toolbar_bg || '#F4F6FA'
    const toolbarDark = isColorDark(toolbarBg)
    root.setProperty('--pim-toolbar-border', toolbarDark
      ? lighten(toolbarBg, 15)
      : 'var(--color-border)')

    saveToLocalStorage()
  }

  function applyPreset(name) {
    if (PRESETS[name]) {
      preset.value = name
      settings.value = { ...PRESETS[name] }
      applyTheme()
    }
  }

  function setCustom(partial) {
    preset.value = 'custom'
    Object.assign(settings.value, partial)
    applyTheme()
  }

  function reset() {
    applyPreset(DEFAULT_PRESET)
  }

  function saveToLocalStorage() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      preset: preset.value,
      settings: settings.value,
    }))
  }

  function loadFromLocalStorage() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (raw) {
        const parsed = JSON.parse(raw)
        if (parsed.preset) preset.value = parsed.preset
        if (parsed.settings) settings.value = { ...PRESETS[DEFAULT_PRESET], ...parsed.settings }
      }
    } catch { /* ignore */ }
    // Theme immer anwenden — auch wenn kein localStorage vorhanden
    applyTheme()
  }

  async function loadFromApi() {
    try {
      // Firmen-CI pruefen: erzwungenes Theme hat Vorrang
      try {
        const { data: enforcedRes } = await client.get('/catalog/settings/enforced-appearance')
        const enforcedData = enforcedRes.data
        if (enforcedData && enforcedData.preset) {
          enforced.value = true
          const enforcedPreset = enforcedData.preset
          preset.value = enforcedPreset
          settings.value = {
            ...(PRESETS[enforcedPreset] || PRESETS[DEFAULT_PRESET]),
            ...enforcedData,
          }
          applyTheme()
          loaded.value = true
          return
        }
      } catch { /* Kein Firmen-CI gesetzt oder Endpoint nicht verfuegbar */ }

      // Benutzer-Praeferenz laden
      const { data } = await userPreferencesApi.get('appearance')
      if (data.data) {
        preset.value = data.data.preset || DEFAULT_PRESET
        settings.value = { ...PRESETS[DEFAULT_PRESET], ...data.data }
      }
      applyTheme()
      loaded.value = true
    } catch {
      loaded.value = true
    }
  }

  async function saveToApi() {
    try {
      await userPreferencesApi.update('appearance', {
        preset: preset.value,
        ...settings.value,
      })
    } catch (e) {
      console.warn('Darstellung speichern fehlgeschlagen:', e.message)
      throw e
    }
  }

  return {
    preset, settings, loaded, enforced, sidebarColoredIcons,
    applyTheme, applyPreset, setCustom, reset,
    loadFromLocalStorage, loadFromApi, saveToApi,
  }
})

// ── Hilfsfunktionen ──

function isColorDark(hex) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  // Relative luminance
  return (0.299 * r + 0.587 * g + 0.114 * b) < 128
}

function lighten(hex, percent) {
  const r = Math.min(255, parseInt(hex.slice(1, 3), 16) + Math.round(255 * percent / 100))
  const g = Math.min(255, parseInt(hex.slice(3, 5), 16) + Math.round(255 * percent / 100))
  const b = Math.min(255, parseInt(hex.slice(5, 7), 16) + Math.round(255 * percent / 100))
  return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`
}
