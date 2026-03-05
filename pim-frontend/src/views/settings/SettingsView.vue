<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'
import { Globe, Palette, AlertTriangle, Server, RotateCcw, CheckCircle, XCircle, Loader2, GitBranch, Database, Upload, Trash2, Save } from 'lucide-vue-next'
import adminApi from '@/api/admin'
import catalogApi from '@/api/catalog'
import mediaApi from '@/api/media'

const { t } = useI18n()
const localeStore = useLocaleStore()
const authStore = useAuthStore()

const isAdmin = authStore.hasPermission('*') || authStore.userRole === 'Admin'

// ── Catalog Theme State ──
const FONT_OPTIONS = [
  'Inter', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Source Sans 3', 'Montserrat', 'System (sans-serif)',
]
const HEADING_SIZE_OPTIONS = [
  { value: '1.25rem', label: '1.25rem (klein)' },
  { value: '1.5rem', label: '1.5rem' },
  { value: '1.75rem', label: '1.75rem (Standard)' },
  { value: '2rem', label: '2rem' },
  { value: '2.25rem', label: '2.25rem (groß)' },
]
const BODY_SIZE_OPTIONS = [
  { value: '0.8125rem', label: '0.8125rem (klein)' },
  { value: '0.875rem', label: '0.875rem (Standard)' },
  { value: '1rem', label: '1rem (groß)' },
]

const themeForm = ref({
  font_family: 'Inter',
  font_heading_size: '1.75rem',
  font_body_size: '0.875rem',
  color_primary: '#1B3A5C',
  color_accent: '#0D9488',
  color_table_bg: '#f8fafc',
  color_body_text: '#111827',
  color_sidebar: '#1B3A5C',
  color_button: '#0D9488',
  color_table_stripe: '#f1f5f9',
  logo_media_id: null,
  catalog_title: 'Produktkatalog',
  seo_title: '',
  seo_description: '',
  impressum_url: '',
  kontakt_url: '',
  impressum_text: '',
  kontakt_text: '',
  footer_text: '',
})
const themeLogoPreview = ref(null)
const themeSaving = ref(false)
const themeSaved = ref(false)
const themeError = ref(null)
const themeLoading = ref(false)

async function loadThemeSettings() {
  themeLoading.value = true
  try {
    const { data } = await catalogApi.getSettings()
    if (data.data) {
      const d = data.data
      themeForm.value = {
        font_family: d.font_family || 'Inter',
        font_heading_size: d.font_heading_size || '1.75rem',
        font_body_size: d.font_body_size || '0.875rem',
        color_primary: d.color_primary || '#1B3A5C',
        color_accent: d.color_accent || '#0D9488',
        color_table_bg: d.color_table_bg || '#f8fafc',
        color_body_text: d.color_body_text || '#111827',
        color_sidebar: d.color_sidebar || '#1B3A5C',
        color_button: d.color_button || '#0D9488',
        color_table_stripe: d.color_table_stripe || '#f1f5f9',
        logo_media_id: d.logo_media_id || null,
        catalog_title: d.catalog_title || 'Produktkatalog',
        seo_title: d.seo_title || '',
        seo_description: d.seo_description || '',
        impressum_url: d.impressum_url || '',
        kontakt_url: d.kontakt_url || '',
        impressum_text: d.impressum_text || '',
        kontakt_text: d.kontakt_text || '',
        footer_text: d.footer_text || '',
      }
      themeLogoPreview.value = d.logo_url || null
    }
  } catch (e) {
    console.warn('Failed to load theme settings:', e.message)
  } finally { themeLoading.value = false }
}

async function saveThemeSettings() {
  themeSaving.value = true
  themeSaved.value = false
  themeError.value = null
  try {
    const payload = { ...themeForm.value }
    // Convert empty strings to null for optional fields
    for (const key of ['impressum_url', 'kontakt_url', 'impressum_text', 'kontakt_text', 'footer_text', 'catalog_title', 'seo_title', 'seo_description']) {
      if (!payload[key]) payload[key] = null
    }
    await adminApi.updateCatalogTheme(payload)
    themeSaved.value = true
    setTimeout(() => { themeSaved.value = false }, 3000)
  } catch (e) {
    themeError.value = e.response?.data?.message || e.message
  } finally { themeSaving.value = false }
}

async function uploadLogo(event) {
  const file = event.target.files?.[0]
  if (!file) return
  try {
    const { data } = await mediaApi.upload(file, { usage_purpose: 'catalog_logo' })
    const media = data.data || data
    themeForm.value.logo_media_id = media.id
    themeLogoPreview.value = media.thumb_url || media.url || media.file_url
  } catch (e) {
    themeError.value = 'Logo-Upload fehlgeschlagen: ' + (e.response?.data?.message || e.message)
  }
  event.target.value = ''
}

function removeLogo() {
  themeForm.value.logo_media_id = null
  themeLogoPreview.value = null
}

// ── Reset State ──
const confirmText = ref('')
const resetting = ref(false)
const showConfirm = ref(false)
const resultMessage = ref('')
const resultError = ref(false)

function openConfirmDialog() {
  confirmText.value = ''
  resultMessage.value = ''
  resultError.value = false
  showConfirm.value = true
}

function cancelReset() {
  showConfirm.value = false
  confirmText.value = ''
}

async function executeReset() {
  if (confirmText.value !== 'RESET') return
  resetting.value = true
  resultMessage.value = ''
  resultError.value = false
  try {
    await adminApi.resetData('RESET')
    resultMessage.value = t('settings.resetSuccess')
    resultError.value = false
    showConfirm.value = false
    confirmText.value = ''
  } catch (err) {
    resultMessage.value = err.response?.data?.detail || t('settings.resetError')
    resultError.value = true
  } finally {
    resetting.value = false
  }
}

// ── Demo-Daten State ──
const loadingDemo = ref(false)
const demoResult = ref(null)
const demoError = ref(null)
const showConfirmDemo = ref(false)

async function triggerLoadDemo() {
  showConfirmDemo.value = false
  loadingDemo.value = true
  demoResult.value = null
  demoError.value = null
  try {
    const { data } = await adminApi.loadDemoData()
    demoResult.value = data
  } catch (e) {
    demoError.value = e.response?.data?.detail || e.message || 'Demo-Daten laden fehlgeschlagen'
  } finally {
    loadingDemo.value = false
  }
}

// ── Deployment State ──
const serverStatus = ref(null)
const deploying = ref(false)
const deployResult = ref(null)
const deployError = ref(null)
const rollbackHash = ref('')
const rollingBack = ref(false)
const showConfirmDeploy = ref(false)

async function loadStatus() {
  if (!isAdmin) return
  try {
    const { data } = await adminApi.getDeployStatus()
    serverStatus.value = data
  } catch {
    serverStatus.value = null
  }
}

async function triggerDeploy() {
  showConfirmDeploy.value = false
  deploying.value = true
  deployResult.value = null
  deployError.value = null
  try {
    const { data } = await adminApi.deploy()
    deployResult.value = data
    rollbackHash.value = data.backup_hash || ''
    await loadStatus()
  } catch (e) {
    deployError.value = e.response?.data?.detail || e.message || 'Deployment fehlgeschlagen'
  } finally {
    deploying.value = false
  }
}

async function triggerRollback() {
  if (!rollbackHash.value) return
  rollingBack.value = true
  deployResult.value = null
  deployError.value = null
  try {
    const { data } = await adminApi.rollback(rollbackHash.value)
    deployResult.value = data
    await loadStatus()
  } catch (e) {
    deployError.value = e.response?.data?.detail || e.message || 'Rollback fehlgeschlagen'
  } finally {
    rollingBack.value = false
  }
}

onMounted(() => {
  loadStatus()
  if (isAdmin) loadThemeSettings()
})
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('settings.title') }}</h2>

    <!-- Sprache -->
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Globe class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">{{ t('settings.language') }}</h3></div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ t('settings.uiLanguage') }}</label>
        <select class="pim-input max-w-xs" :value="localeStore.currentLocale" @change="localeStore.setUiLocale($event.target.value)">
          <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ t('settings.dataLanguages') }}</label>
        <div class="flex gap-2">
          <label v-for="loc in localeStore.availableLocales" :key="loc.code" class="flex items-center gap-1.5 text-xs cursor-pointer">
            <input type="checkbox" :checked="localeStore.activeDataLocales.includes(loc.code)" @change="localeStore.toggleDataLocale(loc.code)" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            {{ loc.label }}
          </label>
        </div>
      </div>
    </div>

    <!-- Darstellung / Katalog-Theme -->
    <div v-if="isAdmin" class="pim-card p-6 space-y-5">
      <div class="flex items-center gap-3 mb-2">
        <Palette class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Katalog-Darstellung</h3>
      </div>

      <div v-if="themeLoading" class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-4 h-4 animate-spin" /> Lade Einstellungen…
      </div>

      <template v-else>
        <!-- Typografie -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Typografie</h4>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Schriftart</label>
              <select class="pim-input" v-model="themeForm.font_family">
                <option v-for="f in FONT_OPTIONS" :key="f" :value="f">{{ f }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Überschriften</label>
              <select class="pim-input" v-model="themeForm.font_heading_size">
                <option v-for="o in HEADING_SIZE_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Fließtext</label>
              <select class="pim-input" v-model="themeForm.font_body_size">
                <option v-for="o in BODY_SIZE_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Farben -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Farben</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="c in [
              { key: 'color_primary', label: 'Primär / Überschriften' },
              { key: 'color_accent', label: 'Akzentfarbe' },
              { key: 'color_sidebar', label: 'Menüpunkte (Sidebar)' },
              { key: 'color_button', label: 'Buttons' },
              { key: 'color_table_bg', label: 'Tabellen-Hintergrund' },
              { key: 'color_table_stripe', label: 'Tabellen-Zeilen (alternierend)' },
              { key: 'color_body_text', label: 'Textfarbe' },
            ]" :key="c.key">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ c.label }}</label>
              <div class="flex items-center gap-2">
                <input
                  type="color"
                  :value="themeForm[c.key]"
                  @input="themeForm[c.key] = $event.target.value"
                  class="w-9 h-9 rounded border border-[var(--color-border)] cursor-pointer p-0.5"
                />
                <input
                  type="text"
                  :value="themeForm[c.key]"
                  @input="themeForm[c.key] = $event.target.value"
                  class="pim-input font-mono text-xs flex-1"
                  maxlength="7"
                  placeholder="#000000"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Logo & Titel -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Logo & Titel</h4>
          <div class="flex items-start gap-4">
            <div class="w-24 h-16 rounded border border-[var(--color-border)] bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
              <img v-if="themeLogoPreview" :src="themeLogoPreview" class="max-w-full max-h-full object-contain p-1" alt="Logo" />
              <span v-else class="text-[10px] text-[var(--color-text-tertiary)]">Kein Logo</span>
            </div>
            <div class="flex-1 space-y-2">
              <div class="flex gap-2">
                <label class="pim-btn pim-btn-secondary text-xs cursor-pointer">
                  <Upload class="w-3.5 h-3.5" /> Logo hochladen
                  <input type="file" accept="image/*" class="hidden" @change="uploadLogo" />
                </label>
                <button v-if="themeForm.logo_media_id" class="pim-btn pim-btn-ghost text-xs text-[var(--color-error)]" @click="removeLogo">
                  <Trash2 class="w-3.5 h-3.5" /> Entfernen
                </button>
              </div>
              <div>
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Katalog-Titel</label>
                <input class="pim-input text-xs" v-model="themeForm.catalog_title" placeholder="Produktkatalog" />
              </div>
            </div>
          </div>
        </div>

        <!-- SEO -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">SEO / Meta-Tags</h4>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SEO-Titel <span class="text-[var(--color-text-tertiary)] font-normal">(Browser-Tab & Suchmaschinen)</span></label>
            <input class="pim-input text-xs" v-model="themeForm.seo_title" placeholder="z.B. Produktkatalog – Firma GmbH" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Meta-Description <span class="text-[var(--color-text-tertiary)] font-normal">(max. 160 Zeichen)</span></label>
            <textarea class="pim-input text-xs" rows="2" v-model="themeForm.seo_description" maxlength="500" placeholder="Kurze Beschreibung für Suchmaschinen…"></textarea>
            <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">{{ (themeForm.seo_description || '').length }} / 160 Zeichen</p>
          </div>
        </div>

        <!-- Legal -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Impressum & Kontakt</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Impressum-URL</label>
              <input class="pim-input text-xs" v-model="themeForm.impressum_url" placeholder="https://..." />
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Kontakt-URL</label>
              <input class="pim-input text-xs" v-model="themeForm.kontakt_url" placeholder="https://..." />
            </div>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Impressum-Text <span class="text-[var(--color-text-tertiary)] font-normal">(wird als eigene Seite angezeigt)</span></label>
            <textarea class="pim-input text-xs" rows="4" v-model="themeForm.impressum_text" placeholder="Firma GmbH, Musterstraße 1, ..."></textarea>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Kontakt-Text <span class="text-[var(--color-text-tertiary)] font-normal">(wird als eigene Seite angezeigt)</span></label>
            <textarea class="pim-input text-xs" rows="4" v-model="themeForm.kontakt_text" placeholder="E-Mail: info@firma.de, Tel: ..."></textarea>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Footer-Text <span class="text-[var(--color-text-tertiary)] font-normal">(ersetzt &bdquo;Powered by&ldquo;)</span></label>
            <input class="pim-input text-xs" v-model="themeForm.footer_text" placeholder="© 2026 Firma GmbH" />
          </div>
        </div>

        <!-- Save -->
        <div class="flex items-center gap-3 pt-2 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="themeSaving" @click="saveThemeSettings">
            <Save class="w-3.5 h-3.5" :stroke-width="2" />
            {{ themeSaving ? 'Speichern…' : 'Katalog-Theme speichern' }}
          </button>
          <span v-if="themeSaved" class="text-xs text-[var(--color-success)] flex items-center gap-1">
            <CheckCircle class="w-3.5 h-3.5" /> Gespeichert
          </span>
          <span v-if="themeError" class="text-xs text-[var(--color-error)]">{{ themeError }}</span>
        </div>
      </template>
    </div>

    <!-- Admin: Demo-Daten laden -->
    <div v-if="isAdmin" class="pim-card border border-blue-300 dark:border-blue-800 p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Database class="w-5 h-5 text-blue-500" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-blue-600 dark:text-blue-400">Demo-Daten laden</h3>
      </div>

      <p class="text-xs text-[var(--color-text-tertiary)]">
        Alle bestehenden Daten werden geloescht und durch ein vollstaendiges Demo-Sortiment ersetzt
        (Produkttypen, Attribute, Hierarchien, Produkte, Preise, Medien, Beziehungen).
      </p>

      <!-- Demo Result -->
      <div v-if="demoResult" class="rounded-lg p-4 border" :class="demoResult.success ? 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800'">
        <div class="flex items-center gap-2 text-sm font-medium" :class="demoResult.success ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'">
          <CheckCircle v-if="demoResult.success" class="w-4 h-4" />
          <XCircle v-else class="w-4 h-4" />
          {{ demoResult.success ? 'Demo-Daten erfolgreich geladen' : 'Fehler beim Laden' }}
        </div>
        <p v-if="demoResult.message" class="text-xs mt-1 text-[var(--color-text-secondary)]">{{ demoResult.message }}</p>
        <details v-if="demoResult.import_result" class="mt-2 text-xs">
          <summary class="cursor-pointer text-[var(--color-text-secondary)]">Import-Details anzeigen</summary>
          <pre class="mt-2 bg-[var(--color-bg-secondary)] rounded p-3 overflow-x-auto text-[11px]">{{ JSON.stringify(demoResult.import_result, null, 2) }}</pre>
        </details>
      </div>

      <!-- Demo Error -->
      <div v-if="demoError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
          <XCircle class="w-4 h-4" />
          Fehler
        </div>
        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ demoError }}</p>
      </div>

      <!-- Loading -->
      <div v-if="loadingDemo" class="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-5 h-5 animate-spin text-blue-500" />
        <span>Demo-Daten werden geladen... Reset, Excel-Generierung, Import...</span>
      </div>

      <!-- Buttons -->
      <div class="flex items-center gap-3" v-if="!loadingDemo">
        <button
          v-if="!showConfirmDemo"
          @click="showConfirmDemo = true"
          class="px-4 py-1.5 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors flex items-center gap-2"
        >
          <Database class="w-4 h-4" />
          Demo-Daten laden
        </button>
        <template v-if="showConfirmDemo">
          <span class="text-xs text-[var(--color-text-secondary)]">Alle Daten werden geloescht und durch Demo-Daten ersetzt!</span>
          <button @click="triggerLoadDemo" class="px-4 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">Ja, laden</button>
          <button @click="showConfirmDemo = false" class="px-4 py-1.5 text-xs font-medium rounded-md text-[var(--color-text-secondary)] bg-[var(--color-bg-secondary)] hover:bg-[var(--color-bg-tertiary)] transition-colors">Abbrechen</button>
        </template>
      </div>
    </div>

    <!-- Admin: Reset Data Model (Danger Zone) -->
    <div v-if="authStore.userRole === 'Admin'" class="pim-card border border-red-300 dark:border-red-800 p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <AlertTriangle class="w-5 h-5 text-red-500" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">{{ t('settings.dangerZone') }}</h3>
      </div>

      <div>
        <h4 class="text-sm font-medium text-[var(--color-text-primary)] mb-1">{{ t('settings.resetTitle') }}</h4>
        <p class="text-xs text-[var(--color-text-tertiary)] mb-3">{{ t('settings.resetDescription') }}</p>

        <!-- Result message -->
        <div v-if="resultMessage" class="mb-3 text-xs px-3 py-2 rounded" :class="resultError ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'">
          {{ resultMessage }}
        </div>

        <!-- Confirmation dialog -->
        <div v-if="showConfirm" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 space-y-3">
          <p class="text-xs text-red-700 dark:text-red-300 font-medium">{{ t('settings.resetConfirmPrompt') }}</p>
          <input
            v-model="confirmText"
            type="text"
            class="pim-input max-w-xs text-sm"
            placeholder="RESET"
            :disabled="resetting"
          />
          <div class="flex gap-2">
            <button
              class="px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              :disabled="confirmText !== 'RESET' || resetting"
              @click="executeReset"
            >
              <span v-if="resetting">{{ t('common.loading') }}</span>
              <span v-else>{{ t('settings.resetExecute') }}</span>
            </button>
            <button
              class="px-3 py-1.5 text-xs font-medium rounded-md text-[var(--color-text-secondary)] bg-[var(--color-bg-secondary)] hover:bg-[var(--color-bg-tertiary)] transition-colors"
              :disabled="resetting"
              @click="cancelReset"
            >
              {{ t('common.cancel') }}
            </button>
          </div>
        </div>

        <!-- Initial button -->
        <button
          v-else
          class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 border border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/20 transition-colors"
          @click="openConfirmDialog"
        >
          {{ t('settings.resetButton') }}
        </button>
      </div>
    </div>

    <!-- Admin: Deployment -->
    <div v-if="isAdmin" class="pim-card p-6 space-y-5">
      <div class="flex items-center gap-3 mb-2">
        <Server class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Server-Deployment</h3>
      </div>

      <!-- Server-Status -->
      <div v-if="serverStatus" class="bg-[var(--color-bg-secondary)] rounded-lg p-4 space-y-2">
        <div class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)]">
          <GitBranch class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="font-mono font-medium">{{ serverStatus.branch }}</span>
          <span class="text-[var(--color-text-tertiary)]">@</span>
          <span class="font-mono text-[var(--color-accent)]">{{ serverStatus.commit }}</span>
        </div>
        <p class="text-xs text-[var(--color-text-secondary)]">{{ serverStatus.message }}</p>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">
          {{ serverStatus.date }} &middot; Laravel {{ serverStatus.laravel_version }} &middot; PHP {{ serverStatus.php_version }}
        </p>
      </div>

      <!-- Deploy-Button -->
      <div class="flex items-center gap-3">
        <button
          v-if="!showConfirmDeploy"
          @click="showConfirmDeploy = true"
          :disabled="deploying"
          class="pim-btn-primary flex items-center gap-2 text-sm"
        >
          <Loader2 v-if="deploying" class="w-4 h-4 animate-spin" />
          <Server v-else class="w-4 h-4" :stroke-width="1.75" />
          Main Branch laden & deployen
        </button>

        <template v-if="showConfirmDeploy && !deploying">
          <span class="text-xs text-[var(--color-text-secondary)]">Sicher? Der Server wird aktualisiert.</span>
          <button @click="triggerDeploy" class="pim-btn-danger text-sm px-4 py-1.5">Ja, deployen</button>
          <button @click="showConfirmDeploy = false" class="pim-btn-secondary text-sm px-4 py-1.5">Abbrechen</button>
        </template>
      </div>

      <!-- Deploying Spinner -->
      <div v-if="deploying" class="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-5 h-5 animate-spin text-[var(--color-accent)]" />
        <span>Deployment läuft... Git Pull, Composer, Migrationen, Cache...</span>
      </div>

      <!-- Deploy Error -->
      <div v-if="deployError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
          <XCircle class="w-4 h-4" />
          Deployment-Fehler
        </div>
        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ deployError }}</p>
      </div>

      <!-- Deploy Result -->
      <div v-if="deployResult" class="space-y-3">
        <div
          class="rounded-lg p-4 border"
          :class="deployResult.success
            ? 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800'
            : 'bg-yellow-50 dark:bg-yellow-950/30 border-yellow-200 dark:border-yellow-800'"
        >
          <div class="flex items-center gap-2 text-sm font-medium" :class="deployResult.success ? 'text-green-700 dark:text-green-400' : 'text-yellow-700 dark:text-yellow-400'">
            <CheckCircle v-if="deployResult.success" class="w-4 h-4" />
            <XCircle v-else class="w-4 h-4" />
            {{ deployResult.success ? 'Deployment erfolgreich' : 'Deployment mit Warnungen' }}
          </div>
          <p class="text-xs mt-1" :class="deployResult.success ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'">
            Commit: {{ deployResult.commit }} &middot; Dauer: {{ deployResult.duration_seconds }}s &middot; Von: {{ deployResult.deployed_by }}
          </p>
        </div>

        <!-- Step Details -->
        <details class="text-xs">
          <summary class="cursor-pointer text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
            Deployment-Schritte anzeigen ({{ deployResult.steps?.length || 0 }})
          </summary>
          <div class="mt-2 space-y-1 font-mono bg-[var(--color-bg-secondary)] rounded-lg p-3 max-h-64 overflow-y-auto">
            <div v-for="step in deployResult.steps" :key="step.step" class="flex items-start gap-2">
              <CheckCircle v-if="step.success" class="w-3.5 h-3.5 text-green-500 mt-0.5 shrink-0" />
              <XCircle v-else class="w-3.5 h-3.5 text-red-500 mt-0.5 shrink-0" />
              <div>
                <span class="font-medium">{{ step.step }}</span>
                <span v-if="step.output" class="text-[var(--color-text-tertiary)] ml-2">{{ step.output.substring(0, 200) }}</span>
              </div>
            </div>
          </div>
        </details>

        <!-- Rollback -->
        <div v-if="deployResult.backup_hash" class="flex items-center gap-3 pt-2 border-t border-[var(--color-border)]">
          <RotateCcw class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          <span class="text-xs text-[var(--color-text-secondary)]">
            Rollback auf <span class="font-mono">{{ deployResult.backup_hash?.substring(0, 7) }}</span>
          </span>
          <button
            @click="triggerRollback"
            :disabled="rollingBack"
            class="pim-btn-secondary text-xs px-3 py-1"
          >
            <Loader2 v-if="rollingBack" class="w-3 h-3 animate-spin" />
            <template v-else>Rollback</template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
