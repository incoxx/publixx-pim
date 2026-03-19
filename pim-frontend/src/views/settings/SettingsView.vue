<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'
import { Globe, Palette, AlertTriangle, Server, RotateCcw, CheckCircle, XCircle, Loader2, GitBranch, Database, Upload, Trash2, Save, Filter, LayoutGrid, Columns3, Image, Settings2, Paintbrush, BookOpen, GripVertical, Plus, X, Shield, Key, Eye, Monitor, RefreshCw, FileCode2, Activity, HardDrive, Cpu, Check, Zap, Play, Clock, Ban, RotateCw, Power, Terminal, WifiOff } from 'lucide-vue-next'
import { useLicenseStore } from '@/stores/license'
import adminApi from '@/api/admin'
import catalogApi from '@/api/catalog'
import mediaApi from '@/api/media'
import pdfTemplatesApi from '@/api/pdfTemplates'
import hierarchiesApi from '@/api/hierarchies'
import attributesApi, { attributeViews as attributeViewsApi } from '@/api/attributes'
import { priceTypes as priceTypesApi, relationTypes as relationTypesApi } from '@/api/prices'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import catalogPresets from '@/config/catalogPresets'
import offlineCatalogApi from '@/api/offlineCatalog'
import catalogTemplatesApi from '@/api/catalogTemplates'

const { t } = useI18n()
const localeStore = useLocaleStore()
const authStore = useAuthStore()

const isAdmin = authStore.hasPermission('*') || authStore.userRole === 'Admin'
const licenseStore = useLicenseStore()

// ── License State ──
const licenseKeyInput = ref('')
const licenseActivating = ref(false)
const licenseError = ref(null)
const licenseSuccess = ref(false)

// ── Offline Catalog Export ──
const offlineCatalogExporting = ref(false)
const offlineCatalogProgress = ref(null)
const offlineCatalogError = ref(null)
const offlineCatalogResult = ref(null)
let offlineCatalogPollTimer = null
const offlineCatalogTemplates = ref([])
const offlineCatalogTemplateId = ref(null)
const offlineCatalogTemplatesLoading = ref(false)

const offlineCatalogCleaning = ref(false)
const offlineCatalogCleanupResult = ref(null)

const offlineBundleStatus = ref(null)
const offlineBundleBuilding = ref(false)
const offlineBundleError = ref(null)

async function loadOfflineBundleStatus() {
  try {
    const { data } = await offlineCatalogApi.bundleStatus()
    offlineBundleStatus.value = data
  } catch { /* ignore */ }
}

async function buildOfflineBundle() {
  offlineBundleBuilding.value = true
  offlineBundleError.value = null
  try {
    await offlineCatalogApi.buildBundle()
    await loadOfflineBundleStatus()
  } catch (e) {
    offlineBundleError.value = e.response?.data?.message || 'Build fehlgeschlagen.'
  } finally {
    offlineBundleBuilding.value = false
  }
}

async function loadOfflineCatalogTemplates() {
  offlineCatalogTemplatesLoading.value = true
  try {
    const { data } = await catalogTemplatesApi.list()
    offlineCatalogTemplates.value = (data.data || []).filter(t => t.is_active)
  } catch { /* ignore */ }
  finally { offlineCatalogTemplatesLoading.value = false }
}

async function startOfflineCatalogExport() {
  offlineCatalogExporting.value = true
  offlineCatalogError.value = null
  offlineCatalogResult.value = null
  offlineCatalogProgress.value = { phase: 'Initialisiere...', percent: 0, status: 'initializing' }

  // Start polling for progress
  offlineCatalogPollTimer = setInterval(async () => {
    try {
      const { data } = await offlineCatalogApi.progress()
      offlineCatalogProgress.value = data
    } catch { /* ignore poll errors */ }
  }, 1000)

  try {
    const { data } = await offlineCatalogApi.generate(themeForm.value.default_locale || 'de', offlineCatalogTemplateId.value)
    offlineCatalogProgress.value = null

    if (data.cancelled) {
      offlineCatalogError.value = 'Export wurde abgebrochen.'
    } else if (data.total_products === 0) {
      offlineCatalogError.value = 'Keine aktiven Produkte gefunden.'
    } else {
      offlineCatalogResult.value = data
    }
  } catch (e) {
    offlineCatalogError.value = e.response?.data?.message || e.message || 'Export fehlgeschlagen.'
  } finally {
    offlineCatalogExporting.value = false
    clearInterval(offlineCatalogPollTimer)
    offlineCatalogPollTimer = null
  }
}

async function cancelOfflineCatalogExport() {
  try {
    await offlineCatalogApi.cancel()
  } catch { /* ignore */ }
}

function handleOfflineCatalogKeydown(e) {
  if (e.key === 'Escape' && offlineCatalogExporting.value) {
    e.preventDefault()
    cancelOfflineCatalogExport()
  }
}

function downloadOfflineCatalog() {
  if (!offlineCatalogResult.value) return
  try {
    const url = offlineCatalogApi.downloadUrl(authStore.token)
    const link = document.createElement('a')
    link.href = url
    link.download = offlineCatalogResult.value.file_name || 'offline-catalog.zip'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (e) {
    offlineCatalogError.value = 'Download fehlgeschlagen.'
  }
}

function previewOfflineCatalog() {
  const url = offlineCatalogApi.previewUrl(authStore.token)
  window.open(url, '_blank')
}

async function cleanupOfflineCatalog() {
  offlineCatalogCleaning.value = true
  offlineCatalogCleanupResult.value = null
  try {
    const { data } = await offlineCatalogApi.cleanup()
    const freed = data.freed_bytes ? ` (${formatFileSize(data.freed_bytes)} freigegeben)` : ''
    offlineCatalogCleanupResult.value = `${data.message}${freed}`
    offlineCatalogResult.value = null
    offlineCatalogError.value = null
  } catch (e) {
    offlineCatalogError.value = 'Aufräumen fehlgeschlagen.'
  } finally {
    offlineCatalogCleaning.value = false
  }
}

function formatFileSize(bytes) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let size = bytes
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
  return `${size.toFixed(1)} ${units[i]}`
}

async function activateLicense() {
  licenseActivating.value = true
  licenseError.value = null
  licenseSuccess.value = false
  try {
    await licenseStore.activateLicense(licenseKeyInput.value)
    licenseSuccess.value = true
    licenseKeyInput.value = ''
    setTimeout(() => { licenseSuccess.value = false }, 3000)
  } catch (e) {
    licenseError.value = e.response?.data?.detail || 'Ungültiger Lizenzschlüssel'
  } finally {
    licenseActivating.value = false
  }
}

async function clearLicense() {
  licenseActivating.value = true
  try {
    await licenseStore.activateLicense('')
    licenseSuccess.value = true
    setTimeout(() => { licenseSuccess.value = false }, 3000)
  } finally {
    licenseActivating.value = false
  }
}

// ── Environment & System Status State ──
const envGroups = ref([])
const envLoading = ref(false)
const envFilter = ref('all') // 'all', 'set', 'unset'
const envMeta = ref(null)

const systemStatus = ref(null)
const systemLoading = ref(false)

async function loadEnvInfo() {
  envLoading.value = true
  try {
    const { data } = await adminApi.getEnvInfo()
    envGroups.value = data.data || []
    envMeta.value = data.meta || null
  } catch (e) {
    console.warn('Failed to load env info:', e.message)
  } finally {
    envLoading.value = false
  }
}

async function loadSystemStatus() {
  systemLoading.value = true
  try {
    const { data } = await adminApi.getSystemStatus()
    systemStatus.value = data.data || null
  } catch (e) {
    console.warn('Failed to load system status:', e.message)
  } finally {
    systemLoading.value = false
  }
}

// ── Prozesse (Queue/Job Management) ──
const queueJobs = ref(null)
const queueLoading = ref(false)
const queueAction = ref(null) // for loading state on buttons
const apacheRestarting = ref(false)
const horizonRestarting = ref(false)

async function loadQueueJobs() {
  queueLoading.value = true
  try {
    const { data } = await adminApi.getQueueJobs()
    queueJobs.value = data.data || null
  } catch (e) {
    console.warn('Failed to load queue jobs:', e.message)
  } finally {
    queueLoading.value = false
  }
}

async function flushQueue(queue = null) {
  queueAction.value = queue || 'all'
  try {
    await adminApi.flushQueue(queue)
    await loadQueueJobs()
  } catch (e) {
    console.warn('Flush failed:', e.message)
  } finally {
    queueAction.value = null
  }
}

async function cancelJob(jobId, jobType) {
  queueAction.value = jobId
  try {
    await adminApi.cancelJob(jobId, jobType)
    // Kurz warten, damit der Cancel-Flag greift
    setTimeout(() => loadQueueJobs(), 1000)
  } catch (e) {
    console.warn('Cancel failed:', e.message)
  } finally {
    queueAction.value = null
  }
}

async function flushFailedJobs() {
  queueAction.value = 'failed'
  try {
    await adminApi.flushFailedJobs()
    await loadQueueJobs()
  } catch (e) {
    console.warn('Flush failed jobs failed:', e.message)
  } finally {
    queueAction.value = null
  }
}

async function restartApache() {
  apacheRestarting.value = true
  try {
    await adminApi.restartApache()
  } catch (e) {
    console.warn('Apache restart failed:', e.message)
  } finally {
    setTimeout(() => { apacheRestarting.value = false }, 2000)
  }
}

async function restartHorizon() {
  horizonRestarting.value = true
  try {
    await adminApi.restartHorizon()
    // Horizon braucht etwas Zeit zum Neustarten
    setTimeout(() => {
      horizonRestarting.value = false
      loadQueueJobs()
    }, 5000)
  } catch (e) {
    console.warn('Horizon restart failed:', e.message)
    horizonRestarting.value = false
  }
}

// ── System-Prozesse (www-data, mysql) ──
const systemProcesses = ref(null)
const processesLoading = ref(false)

async function loadSystemProcesses() {
  processesLoading.value = true
  try {
    const { data } = await adminApi.getSystemProcesses()
    systemProcesses.value = data.data || null
  } catch (e) {
    console.warn('Failed to load system processes:', e.message)
  } finally {
    processesLoading.value = false
  }
}

function serviceStatusColor(status) {
  if (status === 'running') return 'text-[var(--color-success)]'
  if (status === 'paused') return 'text-[var(--color-warning)]'
  if (status === 'error' || status === 'stopped' || status === 'inactive') return 'text-[var(--color-error)]'
  if (status === 'not_configured') return 'text-[var(--color-text-tertiary)]'
  return 'text-[var(--color-text-secondary)]'
}

function serviceStatusLabel(status) {
  const map = {
    running: 'Aktiv',
    paused: 'Pausiert',
    error: 'Fehler',
    stopped: 'Gestoppt',
    inactive: 'Inaktiv',
    not_configured: 'Nicht konfiguriert',
    unknown: 'Unbekannt',
  }
  return map[status] || status
}

function serviceStatusBg(status) {
  if (status === 'running') return 'bg-[var(--color-success)]/10 border-[var(--color-success)]/30'
  if (status === 'paused') return 'bg-[var(--color-warning)]/10 border-[var(--color-warning)]/30'
  if (status === 'error' || status === 'stopped' || status === 'inactive') return 'bg-[var(--color-error)]/10 border-[var(--color-error)]/30'
  return 'bg-[var(--color-bg)] border-[var(--color-border)]'
}

function progressBarColor(percent) {
  if (percent >= 90) return 'bg-[var(--color-error)]'
  if (percent >= 75) return 'bg-[var(--color-warning)]'
  return 'bg-[var(--color-success)]'
}

const filteredEnvGroups = computed(() => {
  if (envFilter.value === 'all') return envGroups.value
  return envGroups.value.map(g => ({
    ...g,
    items: g.items.filter(i => envFilter.value === 'set' ? i.is_set : !i.is_set),
  })).filter(g => g.items.length > 0)
})

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

const POPUP_SIZE_OPTIONS = [
  { value: '4xl', label: 'Standard (896px)' },
  { value: '5xl', label: 'Groß (1024px)' },
  { value: '6xl', label: 'Sehr groß (1152px)' },
  { value: '7xl', label: 'Extra groß (1280px)' },
  { value: 'full', label: 'Vollbild' },
]

const FACET_DATA_TYPES = ['Selection', 'Dictionary', 'Flag', 'Number', 'Float', 'String']

const DETAIL_LAYOUT_OPTIONS = [
  { value: 'classic', label: 'Klassisch', desc: 'Bild links, Info rechts' },
  { value: 'tabs', label: 'Tabs', desc: 'Bild links, Tabs rechts' },
  { value: 'hero', label: 'Hero', desc: 'Bild oben, Info darunter' },
]

const IMAGE_RATIO_OPTIONS = [
  { value: '4/3', label: 'Quer (4:3)' },
  { value: '1/1', label: 'Quadrat (1:1)' },
  { value: '3/4', label: 'Hoch (3:4)' },
  { value: '16/9', label: 'Breit (16:9)' },
]

const PDF_DISPLAY_MODE_OPTIONS = [
  { value: 'link', label: 'Link', desc: 'PDFs als klickbare Links anzeigen' },
  { value: 'embedded', label: 'Eingebettete Vorschau', desc: 'PDFs inline im Browser-Viewer anzeigen' },
]

// ── Hierarchies, Attribute Views & Attributes for catalog config ──
const availableHierarchies = ref([])
const availableAttributeViews = ref([])
const availableAttributes = ref([])
const allAttributes = ref([])
const availablePriceTypes = ref([])
const availableUsageTypes = ref([])

async function loadHierarchies() {
  try {
    const { data } = await hierarchiesApi.list({ per_page: 200 })
    availableHierarchies.value = (data.data || data || [])

    // Remove stale hierarchy ID if it no longer exists
    if (themeForm.value.hierarchy_id) {
      const hIds = new Set(availableHierarchies.value.map(h => h.id))
      if (!hIds.has(themeForm.value.hierarchy_id)) {
        themeForm.value.hierarchy_id = null
      }
    }
  } catch (e) {
    console.warn('Failed to load hierarchies:', e.message)
  }
}

async function loadAttributeViews() {
  try {
    const { data } = await attributeViewsApi.list()
    availableAttributeViews.value = (data.data || data || [])

    // Remove stale attribute view IDs that no longer exist
    const avIds = new Set(availableAttributeViews.value.map(av => av.id))
    themeForm.value.attribute_view_ids = (themeForm.value.attribute_view_ids || []).filter(id => avIds.has(id))
  } catch (e) {
    console.warn('Failed to load attribute views:', e.message)
  }
}

async function loadAttributes(hierarchyId = null) {
  try {
    let all
    if (hierarchyId) {
      const { data } = await hierarchiesApi.allNodeAttributes(hierarchyId)
      all = data.data || data || []
    } else {
      const { data } = await attributesApi.list({ per_page: 500 })
      all = data.data || data || []
    }
    allAttributes.value = all.filter(a => a.data_type !== 'Composite')
    availableAttributes.value = all.filter(a => FACET_DATA_TYPES.includes(a.data_type))

    // Remove stale attribute IDs that no longer exist
    const availableIds = new Set(availableAttributes.value.map(a => a.id))
    themeForm.value.facet_attribute_ids = themeForm.value.facet_attribute_ids.filter(id => availableIds.has(id))

    const allIds = new Set(allAttributes.value.map(a => a.id))
    themeForm.value.card_attribute_ids = (themeForm.value.card_attribute_ids || []).filter(id => allIds.has(id))
    if (themeForm.value.primary_card_attribute_id && !allIds.has(themeForm.value.primary_card_attribute_id)) {
      themeForm.value.primary_card_attribute_id = null
    }
    themeForm.value.description_attributes = (themeForm.value.description_attributes || []).filter(da => allIds.has(da.attribute_id))
  } catch (e) {
    console.warn('Failed to load attributes:', e.message)
  }
}

async function loadPriceTypes() {
  try {
    const { data } = await priceTypesApi.list()
    availablePriceTypes.value = data.data || data || []

    // Remove stale price type ID if it no longer exists
    if (themeForm.value.card_price_type_id) {
      const ptIds = new Set(availablePriceTypes.value.map(pt => pt.id))
      if (!ptIds.has(themeForm.value.card_price_type_id)) {
        themeForm.value.card_price_type_id = null
      }
    }
  } catch (e) {
    console.warn('Failed to load price types:', e.message)
  }
}

async function loadUsageTypes() {
  try {
    const { data } = await mediaUsageTypes.list()
    availableUsageTypes.value = data.data || data || []
  } catch (e) {
    console.warn('Failed to load usage types:', e.message)
  }
}

// ── Card attribute ordering (drag & drop) ──
const cardDragIdx = ref(null)

const selectedCardAttributes = computed(() => {
  return (themeForm.value.card_attribute_ids || [])
    .map(id => allAttributes.value.find(a => a.id === id))
    .filter(Boolean)
})

const unselectedCardAttributes = computed(() => {
  const selected = new Set(themeForm.value.card_attribute_ids || [])
  return allAttributes.value.filter(a => !selected.has(a.id) && !a.is_internal)
})

function addCardAttribute(attr) {
  if (!themeForm.value.card_attribute_ids.includes(attr.id)) {
    themeForm.value.card_attribute_ids.push(attr.id)
  }
}

function removeCardAttribute(attrId) {
  themeForm.value.card_attribute_ids = themeForm.value.card_attribute_ids.filter(id => id !== attrId)
}

function onCardDragStart(idx) {
  cardDragIdx.value = idx
}

function onCardDragOver(e, idx) {
  e.preventDefault()
  if (cardDragIdx.value === null || cardDragIdx.value === idx) return
  const ids = [...themeForm.value.card_attribute_ids]
  const [moved] = ids.splice(cardDragIdx.value, 1)
  ids.splice(idx, 0, moved)
  themeForm.value.card_attribute_ids = ids
  cardDragIdx.value = idx
}

function onCardDragEnd() {
  cardDragIdx.value = null
}

// ── Description attribute ordering (drag & drop) ──
const TYPOGRAPHY_OPTIONS = [
  { value: 'xs', label: 'Sehr klein' },
  { value: 'sm', label: 'Klein' },
  { value: 'base', label: 'Normal' },
  { value: 'lg', label: 'Groß' },
  { value: 'xl', label: 'Sehr groß' },
  { value: '2xl', label: 'Extra groß' },
  { value: '3xl', label: 'Maximal' },
]

const descDragIdx = ref(null)

const selectedDescAttributes = computed(() => {
  return (themeForm.value.description_attributes || [])
    .map(da => {
      const attr = allAttributes.value.find(a => a.id === da.attribute_id)
      return attr ? { ...da, name: attr.name_de || attr.name, data_type: attr.data_type } : null
    })
    .filter(Boolean)
})

const unselectedDescAttributes = computed(() => {
  const selected = new Set((themeForm.value.description_attributes || []).map(da => da.attribute_id))
  return allAttributes.value.filter(a => !selected.has(a.id) && !a.is_internal)
})

function addDescAttribute(attr) {
  const existing = themeForm.value.description_attributes || []
  if (!existing.some(da => da.attribute_id === attr.id)) {
    themeForm.value.description_attributes = [...existing, { attribute_id: attr.id, typography: 'base' }]
  }
}

function removeDescAttribute(attrId) {
  themeForm.value.description_attributes = (themeForm.value.description_attributes || []).filter(da => da.attribute_id !== attrId)
}

function setDescTypography(attrId, typography) {
  themeForm.value.description_attributes = (themeForm.value.description_attributes || []).map(da =>
    da.attribute_id === attrId ? { ...da, typography } : da
  )
}

function onDescDragStart(idx) {
  descDragIdx.value = idx
}

function onDescDragOver(e, idx) {
  e.preventDefault()
  if (descDragIdx.value === null || descDragIdx.value === idx) return
  const items = [...(themeForm.value.description_attributes || [])]
  const [moved] = items.splice(descDragIdx.value, 1)
  items.splice(idx, 0, moved)
  themeForm.value.description_attributes = items
  descDragIdx.value = idx
}

function onDescDragEnd() {
  descDragIdx.value = null
}

function applyPreset(preset) {
  for (const [key, value] of Object.entries(preset.colors)) {
    themeForm.value[key] = value
  }
}

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
  color_header_bg: '',
  color_header_text: '',
  color_mobile_menu_bg: '',
  color_mobile_menu_text: '',
  logo_media_id: null,
  catalog_title: 'Produktkatalog',
  seo_title: '',
  seo_description: '',
  impressum_url: '',
  kontakt_url: '',
  impressum_text: '',
  kontakt_text: '',
  footer_text: '',
  hierarchy_id: null,
  attribute_view_ids: [],
  default_locale: 'de',
  popup_max_width: '4xl',
  facet_attribute_ids: [],
  detail_layout: 'classic',
  card_attribute_ids: [],
  primary_card_attribute_id: null,
  card_show_sku: false,
  card_show_category: true,
  card_show_price: true,
  card_price_type_id: null,
  card_price_country: null,
  card_image_ratio: '4/3',
  thumbnail_usage_type_id: null,
  description_attributes: [],
  pdf_display_mode: 'link',
  catalog_pdf_enabled: false,
  catalog_pdf_template_id: null,
  catalog_compare_enabled: false,
  catalog_compare_max_products: 3,
  catalog_excel_export_enabled: false,
  catalog_share_wishlist_enabled: false,
  catalog_access_mode: 'public',
  catalog_linked_products_only: false,
  catalog_relation_type_ids: [],
})
const activeMainTab = ref('general')
const activeThemeTab = ref('general')
const previewPage = ref('overview')
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
        hierarchy_id: d.hierarchy_id || null,
        attribute_view_ids: d.attribute_view_ids || [],
        default_locale: d.default_locale || 'de',
        color_header_bg: d.color_header_bg || '',
        color_header_text: d.color_header_text || '',
        color_mobile_menu_bg: d.color_mobile_menu_bg || '',
        color_mobile_menu_text: d.color_mobile_menu_text || '',
        popup_max_width: d.popup_max_width || '4xl',
        facet_attribute_ids: d.facet_attribute_ids || [],
        detail_layout: d.detail_layout || 'classic',
        card_attribute_ids: d.card_attribute_ids || [],
        primary_card_attribute_id: d.primary_card_attribute_id || null,
        card_show_sku: d.card_show_sku ?? false,
        card_show_category: d.card_show_category ?? true,
        card_show_price: d.card_show_price ?? true,
        card_price_type_id: d.card_price_type_id || null,
        card_price_country: d.card_price_country || null,
        card_image_ratio: d.card_image_ratio || '4/3',
        thumbnail_usage_type_id: d.thumbnail_usage_type_id || null,
        description_attributes: d.description_attributes || [],
        pdf_display_mode: d.pdf_display_mode || 'link',
        catalog_pdf_enabled: !!d.catalog_pdf_enabled,
        catalog_pdf_template_id: d.catalog_pdf_template_id || null,
        catalog_compare_enabled: !!d.catalog_compare_enabled,
        catalog_compare_max_products: d.catalog_compare_max_products ?? 3,
        catalog_excel_export_enabled: !!d.catalog_excel_export_enabled,
        catalog_share_wishlist_enabled: !!d.catalog_share_wishlist_enabled,
        catalog_access_mode: d.catalog_access_mode || 'public',
        catalog_linked_products_only: !!d.catalog_linked_products_only,
        catalog_relation_type_ids: d.catalog_relation_type_ids || [],
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
    // Convert empty strings to null for optional text fields
    for (const key of ['impressum_url', 'kontakt_url', 'impressum_text', 'kontakt_text', 'footer_text', 'catalog_title', 'seo_title', 'seo_description', 'color_header_bg', 'color_header_text', 'color_mobile_menu_bg', 'color_mobile_menu_text']) {
      if (!payload[key]) payload[key] = null
    }
    if (!payload.hierarchy_id) payload.hierarchy_id = null
    if (!payload.attribute_view_ids || payload.attribute_view_ids.length === 0) payload.attribute_view_ids = []
    if (!payload.facet_attribute_ids || payload.facet_attribute_ids.length === 0) payload.facet_attribute_ids = []
    if (!payload.card_attribute_ids || payload.card_attribute_ids.length === 0) payload.card_attribute_ids = []
    if (!payload.description_attributes || payload.description_attributes.length === 0) payload.description_attributes = []
    if (!payload.catalog_relation_type_ids || payload.catalog_relation_type_ids.length === 0) payload.catalog_relation_type_ids = []
    // Ensure booleans are actual booleans (not strings)
    payload.card_show_sku = !!payload.card_show_sku
    payload.card_show_category = !!payload.card_show_category
    payload.card_show_price = !!payload.card_show_price
    payload.catalog_pdf_enabled = !!payload.catalog_pdf_enabled
    payload.catalog_compare_enabled = !!payload.catalog_compare_enabled
    payload.catalog_excel_export_enabled = !!payload.catalog_excel_export_enabled
    payload.catalog_share_wishlist_enabled = !!payload.catalog_share_wishlist_enabled
    payload.catalog_compare_max_products = parseInt(payload.catalog_compare_max_products) || 3
    if (!payload.catalog_pdf_template_id) payload.catalog_pdf_template_id = null
    if (!payload.primary_card_attribute_id) payload.primary_card_attribute_id = null
    if (!payload.card_price_type_id) payload.card_price_type_id = null
    if (!payload.card_price_country) payload.card_price_country = null
    if (!payload.thumbnail_usage_type_id) payload.thumbnail_usage_type_id = null
    await adminApi.updateCatalogTheme(payload)
    themeSaved.value = true
    setTimeout(() => { themeSaved.value = false }, 3000)
  } catch (e) {
    // Laravel validation errors come in e.response.data.errors (object with field → messages[])
    const respData = e.response?.data
    if (respData?.errors) {
      const msgs = Object.values(respData.errors).flat()
      themeError.value = msgs.join('; ')
    } else {
      themeError.value = respData?.message || e.message || 'Speichern fehlgeschlagen'
    }
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

// ── PDF Templates for catalog ──
const availableRelationTypes = ref([])
async function loadRelationTypes() {
  try {
    const { data } = await relationTypesApi.list()
    availableRelationTypes.value = data.data || data || []

    // Remove stale relation type IDs that no longer exist
    const rtIds = new Set(availableRelationTypes.value.map(rt => rt.id))
    themeForm.value.catalog_relation_type_ids = (themeForm.value.catalog_relation_type_ids || []).filter(id => rtIds.has(id))
  } catch (e) {
    console.warn('Failed to load relation types:', e.message)
  }
}

const availablePdfTemplates = ref([])
async function loadPdfTemplates() {
  try {
    const { data } = await pdfTemplatesApi.list()
    availablePdfTemplates.value = data.data || data

    // Remove stale PDF template ID if it no longer exists
    if (themeForm.value.catalog_pdf_template_id) {
      const tplIds = new Set(availablePdfTemplates.value.map(t => t.id))
      if (!tplIds.has(themeForm.value.catalog_pdf_template_id)) {
        themeForm.value.catalog_pdf_template_id = null
      }
    }
  } catch (e) {
    console.warn('Failed to load PDF templates:', e.message)
  }
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
  // Force reload categories so newly added backend categories appear
  resetCategories.value = []
  loadResetCategories()
}

function cancelReset() {
  showConfirm.value = false
  confirmText.value = ''
}

async function executeReset() {
  if (confirmText.value !== 'RESET') return
  if (selectedResetCategories.value.length === 0) return
  resetting.value = true
  resultMessage.value = ''
  resultError.value = false
  try {
    const { data } = await adminApi.resetData('RESET', selectedResetCategories.value)
    resultMessage.value = `Erfolgreich zurückgesetzt: ${selectedResetCategories.value.length} Kategorie(n), ${data.tables?.length || 0} Tabelle(n).`
    resultError.value = false
    showConfirm.value = false
    confirmText.value = ''
    // Auto-dismiss success message after 8 seconds
    setTimeout(() => {
      if (!resultError.value) resultMessage.value = ''
    }, 8000)
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

// ── Test Data Generator State ──
const testDataForm = ref({
  count: 1000,
  with_prices: true,
  category_count: 20,
  category_depth: 3,
  attributes_per_product: null,
})
const generatingTestData = ref(false)
const testDataResult = ref(null)
const testDataError = ref(null)
const showConfirmGenerate = ref(false)
const cleaningTestData = ref(false)
const cleanupResult = ref(null)
const cleanupError = ref(null)
const showConfirmCleanup = ref(false)
const testDataStats = ref(null)
const testDataStatsLoading = ref(false)
const testDataProgress = ref(null)
const cancellingTestData = ref(false)
let progressPollTimer = null

async function loadTestDataStats() {
  testDataStatsLoading.value = true
  try {
    const { data } = await adminApi.getTestDataStats()
    testDataStats.value = data.data || data
  } catch (e) {
    testDataStats.value = null
  } finally {
    testDataStatsLoading.value = false
  }
}

function startProgressPolling() {
  stopProgressPolling()
  progressPollTimer = setInterval(async () => {
    try {
      const { data } = await adminApi.getTestDataProgress()
      const p = data.data || data
      testDataProgress.value = p.phase ? p : null
    } catch (e) {
      // Ignore polling errors
    }
  }, 1000)
}

function stopProgressPolling() {
  if (progressPollTimer) {
    clearInterval(progressPollTimer)
    progressPollTimer = null
  }
  testDataProgress.value = null
}

async function triggerGenerateTestData() {
  showConfirmGenerate.value = false
  generatingTestData.value = true
  testDataResult.value = null
  testDataError.value = null
  cancellingTestData.value = false
  startProgressPolling()
  try {
    // Filter out null/empty values so backend uses defaults
    const params = Object.fromEntries(
      Object.entries(testDataForm.value).filter(([, v]) => v != null && v !== '')
    )
    const { data } = await adminApi.generateTestData(params)
    testDataResult.value = data.data || data
    loadTestDataStats()
  } catch (e) {
    testDataError.value = e.response?.data?.detail || e.response?.data?.message || e.message || 'Testdaten-Generierung fehlgeschlagen'
  } finally {
    generatingTestData.value = false
    stopProgressPolling()
  }
}

async function triggerCancelTestData() {
  cancellingTestData.value = true
  try {
    await adminApi.cancelTestData()
  } catch (e) {
    // Ignore cancel errors
  }
}

async function triggerCleanupTestData() {
  showConfirmCleanup.value = false
  cleaningTestData.value = true
  cleanupResult.value = null
  cleanupError.value = null
  try {
    const { data } = await adminApi.cleanupTestData()
    cleanupResult.value = data.data || data
    loadTestDataStats()
  } catch (e) {
    cleanupError.value = e.response?.data?.detail || e.response?.data?.message || e.message || 'Testdaten-Löschung fehlgeschlagen'
  } finally {
    cleaningTestData.value = false
  }
}

// ── Reset Categories State ──
const resetCategories = ref([])
const selectedResetCategories = ref([])
const loadingCategories = ref(false)

async function loadResetCategories() {
  if (resetCategories.value.length > 0) return
  loadingCategories.value = true
  try {
    const { data } = await adminApi.getResetCategories()
    resetCategories.value = data
    selectedResetCategories.value = data.map(c => c.key)
  } catch (e) {
    // fallback
  } finally {
    loadingCategories.value = false
  }
}

function toggleResetCategory(key) {
  const idx = selectedResetCategories.value.indexOf(key)
  if (idx >= 0) {
    selectedResetCategories.value.splice(idx, 1)
  } else {
    selectedResetCategories.value.push(key)
  }
}

function selectAllResetCategories() {
  selectedResetCategories.value = resetCategories.value.map(c => c.key)
}

function deselectAllResetCategories() {
  selectedResetCategories.value = []
}

// ── Deployment State ──
const serverStatus = ref(null)
const deploying = ref(false)
const deployResult = ref(null)
const deployError = ref(null)
const rollbackHash = ref('')
const rollingBack = ref(false)
const showConfirmDeploy = ref(false)

// Search reindex
const reindexing = ref(false)
const reindexResult = ref(null)
const reindexError = ref(null)
const reindexProgress = ref(null)
let reindexPollTimer = null

async function triggerReindex() {
  reindexing.value = true
  reindexResult.value = null
  reindexError.value = null
  reindexProgress.value = null
  try {
    await adminApi.reindexSearch()
    startReindexPolling()
  } catch (e) {
    if (e.response?.status === 409) {
      // Already running, start polling
      reindexProgress.value = e.response.data.progress
      startReindexPolling()
    } else {
      reindexError.value = e.response?.data?.message || e.message
      reindexing.value = false
    }
  }
}

function startReindexPolling() {
  stopReindexPolling()
  reindexPollTimer = setInterval(async () => {
    try {
      const { data } = await adminApi.reindexProgress()
      reindexProgress.value = data.progress
      if (data.progress.status === 'completed') {
        stopReindexPolling()
        reindexing.value = false
        reindexResult.value = { message: `Suchindex aktualisiert: ${data.progress.total.toLocaleString('de-DE')} Produkte indiziert.` }
      } else if (data.progress.status === 'failed') {
        stopReindexPolling()
        reindexing.value = false
        reindexError.value = data.progress.error || 'Reindex fehlgeschlagen'
      }
    } catch {
      // Ignore poll errors
    }
  }, 2000)
}

function stopReindexPolling() {
  if (reindexPollTimer) {
    clearInterval(reindexPollTimer)
    reindexPollTimer = null
  }
}

// ── PDF Batch Processing ──
const pdfBatchProcessing = ref(false)
const pdfBatchResult = ref(null)
const pdfBatchError = ref(null)
const pdfBatchMode = ref('missing')

async function triggerPdfBatchProcess() {
  pdfBatchProcessing.value = true
  pdfBatchResult.value = null
  pdfBatchError.value = null
  try {
    const { data } = await adminApi.batchProcessPdfs(pdfBatchMode.value)
    pdfBatchResult.value = data
  } catch (e) {
    pdfBatchError.value = e.response?.data?.message || e.message
  } finally {
    pdfBatchProcessing.value = false
  }
}

// ── Combined: PDF Thumbnails + Search Reindex ──
const combinedProcessing = ref(false)
const combinedStep = ref('')  // 'pdf' | 'reindex' | ''
const combinedResult = ref(null)
const combinedError = ref(null)

async function triggerCombinedProcess() {
  combinedProcessing.value = true
  combinedResult.value = null
  combinedError.value = null

  try {
    // Step 1: PDF thumbnails
    combinedStep.value = 'pdf'
    await adminApi.batchProcessPdfs('missing')

    // Step 2: Reindex (now async with polling)
    combinedStep.value = 'reindex'
    await adminApi.reindexSearch()

    // Poll until reindex completes
    await new Promise((resolve, reject) => {
      const poll = setInterval(async () => {
        try {
          const { data } = await adminApi.reindexProgress()
          reindexProgress.value = data.progress
          if (data.progress.status === 'completed') {
            clearInterval(poll)
            resolve()
          } else if (data.progress.status === 'failed') {
            clearInterval(poll)
            reject(new Error(data.progress.error || 'Reindex fehlgeschlagen'))
          }
        } catch { /* ignore poll errors */ }
      }, 2000)
    })

    combinedResult.value = { message: 'PDF-Vorschaubilder erzeugt und Suchindex aktualisiert.' }
  } catch (e) {
    combinedError.value = `Fehler bei ${combinedStep.value === 'pdf' ? 'PDF-Verarbeitung' : 'Suchindex'}: ${e.response?.data?.message || e.message}`
  } finally {
    combinedProcessing.value = false
    combinedStep.value = ''
    reindexProgress.value = null
  }
}

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

// Lazy-load data when switching to env/system tabs
watch(activeMainTab, (tab) => {
  if (tab === 'env' && envGroups.value.length === 0) loadEnvInfo()
  if (tab === 'system' && !systemStatus.value) loadSystemStatus()
  if (tab === 'processes') { loadQueueJobs(); loadSystemProcesses() }
  if (tab === 'offline') {
    if (offlineCatalogTemplates.value.length === 0) loadOfflineCatalogTemplates()
    if (!offlineBundleStatus.value) loadOfflineBundleStatus()
  }
})

// Reload attributes when hierarchy selection changes
watch(() => themeForm.value.hierarchy_id, (newId, oldId) => {
  if (newId !== oldId) {
    loadAttributes(newId || null)
  }
})

onMounted(async () => {
  loadStatus()
  if (isAdmin) {
    await loadThemeSettings()
    loadHierarchies()
    loadAttributeViews()
    loadAttributes(themeForm.value.hierarchy_id || null)
    loadPriceTypes()
    loadUsageTypes()
    loadPdfTemplates()
    loadRelationTypes()
    loadTestDataStats()
  }
  // Global Escape listener for offline catalog cancel
  window.addEventListener('keydown', handleOfflineCatalogKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleOfflineCatalogKeydown)
  if (offlineCatalogPollTimer) {
    clearInterval(offlineCatalogPollTimer)
  }
})
</script>

<template>
  <div class="space-y-4 sm:space-y-6 max-w-5xl">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('settings.title') }}</h2>

    <!-- ═══ TOP-LEVEL TABS ═══ -->
    <div class="flex gap-1 border-b border-[var(--color-border)] overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
      <button
        v-for="tab in [
          { key: 'general', label: 'Generell', icon: Settings2 },
          ...(isAdmin ? [{ key: 'catalog', label: 'Preview Katalog', icon: Eye }] : []),
          ...(isAdmin ? [{ key: 'offline', label: 'Offline', icon: WifiOff }] : []),
          ...(isAdmin ? [{ key: 'env', label: 'Umgebung', icon: FileCode2 }] : []),
          ...(isAdmin ? [{ key: 'processes', label: 'Prozesse', icon: Zap }] : []),
          ...(isAdmin ? [{ key: 'system', label: 'System', icon: Activity }] : []),
          ...(isAdmin ? [{ key: 'license', label: 'Lizenz', icon: Key }] : []),
        ]"
        :key="tab.key"
        class="flex items-center gap-2 px-3 sm:px-4 py-2.5 text-[13px] sm:text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap shrink-0"
        :class="activeMainTab === tab.key
          ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
          : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
        @click="activeMainTab = tab.key"
      >
        <component :is="tab.icon" class="w-4 h-4" :stroke-width="2" />
        {{ tab.label }}
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: GENERELL                                                      -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'general'">

    <!-- Sprache -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
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

    </template><!-- end TAB: Generell -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: PREVIEW KATALOG                                               -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'catalog' && isAdmin">

    <div class="pim-card p-4 sm:p-6 space-y-5">
      <div class="flex items-center gap-3 mb-2">
        <Palette class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Katalog-Darstellung</h3>
      </div>

      <div v-if="themeLoading" class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-4 h-4 animate-spin" /> Lade Einstellungen…
      </div>

      <template v-else>
        <!-- Sub-Tab Navigation -->
        <div class="flex gap-1 border-b border-[var(--color-border)] -mx-6 px-6">
          <button
            v-for="tab in [
              { key: 'general', label: 'Allgemein', icon: Settings2 },
              { key: 'design', label: 'Design', icon: Paintbrush },
              { key: 'catalog', label: 'Katalog', icon: BookOpen },
            ]"
            :key="tab.key"
            class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors"
            :class="activeThemeTab === tab.key
              ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
              : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
            @click="activeThemeTab = tab.key"
          >
            <component :is="tab.icon" class="w-3.5 h-3.5" :stroke-width="2" />
            {{ tab.label }}
          </button>
        </div>

        <!-- ═══ TAB: Allgemein ═══ -->
        <div v-show="activeThemeTab === 'general'" class="space-y-5">

        <!-- Katalog-Konfiguration -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Katalog-Konfiguration</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Hierarchie</label>
              <select class="pim-input text-xs" v-model="themeForm.hierarchy_id">
                <option :value="null">— Standard (erste Master-Hierarchie) —</option>
                <option v-for="h in availableHierarchies" :key="h.id" :value="h.id">
                  {{ h.name_de || h.name }} ({{ h.hierarchy_type }})
                </option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Standard-Sprache</label>
              <select class="pim-input text-xs" v-model="themeForm.default_locale">
                <option value="de">Deutsch</option>
                <option value="en">English</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Produkt-Popup Breite</label>
              <select class="pim-input text-xs" v-model="themeForm.popup_max_width">
                <option v-for="o in POPUP_SIZE_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Katalog-Zugriff</label>
              <select class="pim-input text-xs" v-model="themeForm.catalog_access_mode">
                <option value="public">Öffentlich (ohne Login)</option>
                <option value="login">Login erforderlich</option>
              </select>
            </div>
          </div>
          <label v-if="themeForm.hierarchy_id" class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="checkbox" v-model="themeForm.catalog_linked_products_only"
                   class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            Nur verknüpfte Produkte darstellen
          </label>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Angezeigte Attribute</label>
            <div class="flex gap-3 mt-1.5 mb-2">
              <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                <input
                  type="radio"
                  name="attr_view_mode"
                  :checked="themeForm.attribute_view_ids.length === 0"
                  @change="themeForm.attribute_view_ids = []"
                  class="radio radio-xs border-[var(--color-border-strong)] text-[var(--color-accent)]"
                />
                Alle Attribute anzeigen
              </label>
              <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                <input
                  type="radio"
                  name="attr_view_mode"
                  :checked="themeForm.attribute_view_ids.length > 0"
                  @change="() => { if (themeForm.attribute_view_ids.length === 0 && availableAttributeViews.length > 0) themeForm.attribute_view_ids.push(availableAttributeViews[0].id) }"
                  class="radio radio-xs border-[var(--color-border-strong)] text-[var(--color-accent)]"
                />
                Nur bestimmte Attribut-Sichten
              </label>
            </div>
            <div v-if="themeForm.attribute_view_ids.length > 0 || availableAttributeViews.length === 0">
              <div v-if="availableAttributeViews.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Attribut-Views vorhanden</div>
              <div v-else class="flex flex-wrap gap-x-4 gap-y-1.5 pl-1 border-l-2 border-[var(--color-accent)]/30 ml-1">
                <label v-for="av in availableAttributeViews" :key="av.id" class="flex items-center gap-1.5 text-xs cursor-pointer">
                  <input
                    type="checkbox"
                    :value="av.id"
                    :checked="themeForm.attribute_view_ids.includes(av.id)"
                    @change="
                      $event.target.checked
                        ? themeForm.attribute_view_ids.push(av.id)
                        : themeForm.attribute_view_ids = themeForm.attribute_view_ids.filter(id => id !== av.id)
                    "
                    class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]"
                  />
                  {{ av.name_de || av.name }}
                </label>
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
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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

        </div><!-- end TAB: Allgemein -->

        <!-- ═══ TAB: Design ═══ -->
        <div v-show="activeThemeTab === 'design'" class="space-y-5">

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

        <!-- Farb-Presets -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Farb-Presets</h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
            <button
              v-for="preset in catalogPresets"
              :key="preset.name"
              class="flex items-center gap-2 px-3 py-2.5 rounded-lg border text-left transition-all hover:shadow-sm"
              :class="themeForm.color_primary === preset.colors.color_primary && themeForm.color_accent === preset.colors.color_accent
                ? 'border-[var(--color-accent)] ring-2 ring-[var(--color-accent)]/30 bg-[var(--color-accent)]/5'
                : 'border-[var(--color-border)] hover:border-[var(--color-border-strong)]'"
              @click="applyPreset(preset)"
            >
              <div class="flex -space-x-1.5 shrink-0">
                <span class="w-5 h-5 rounded-full border-2 border-white shadow-sm" :style="{ backgroundColor: preset.colors.color_primary }"></span>
                <span class="w-5 h-5 rounded-full border-2 border-white shadow-sm" :style="{ backgroundColor: preset.colors.color_accent }"></span>
              </div>
              <span class="text-[11px] font-medium text-[var(--color-text-primary)] truncate">{{ preset.name }}</span>
            </button>
          </div>
        </div>

        <!-- Farben -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Farben</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div v-for="c in [
              { key: 'color_primary', label: 'Primär / Überschriften' },
              { key: 'color_accent', label: 'Akzentfarbe' },
              { key: 'color_sidebar', label: 'Menüpunkte (Sidebar)' },
              { key: 'color_button', label: 'Buttons' },
              { key: 'color_table_bg', label: 'Tabellen-Hintergrund' },
              { key: 'color_table_stripe', label: 'Tabellen-Zeilen (alternierend)' },
              { key: 'color_body_text', label: 'Textfarbe' },
              { key: 'color_header_bg', label: 'Header-Hintergrund' },
              { key: 'color_header_text', label: 'Header-Text' },
              { key: 'color_mobile_menu_bg', label: 'Mobiles Menü Hintergrund' },
              { key: 'color_mobile_menu_text', label: 'Mobiles Menü Text' },
            ]" :key="c.key">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ c.label }}</label>
              <div class="flex items-center gap-2">
                <input
                  type="color"
                  :value="themeForm[c.key] || '#000000'"
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

        </div><!-- end TAB: Design -->

        <!-- ═══ TAB: Katalog ═══ -->
        <div v-show="activeThemeTab === 'catalog'" class="space-y-5">

        <!-- Detail-Ansicht -->
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <Columns3 class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="2" />
            <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Detail-Ansicht</h4>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Layout für die Produktdetailseite und das Popup.</p>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="opt in DETAIL_LAYOUT_OPTIONS"
              :key="opt.value"
              class="flex flex-col items-center gap-2 px-3 py-3 rounded-lg border text-center transition-all hover:shadow-sm"
              :class="themeForm.detail_layout === opt.value
                ? 'border-[var(--color-accent)] ring-2 ring-[var(--color-accent)]/30 bg-[var(--color-accent)]/5'
                : 'border-[var(--color-border)] hover:border-[var(--color-border-strong)]'"
              @click="themeForm.detail_layout = opt.value"
            >
              <!-- Mini layout preview -->
              <div class="w-full aspect-[4/3] rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-1.5 flex gap-1" v-if="opt.value === 'classic'">
                <div class="w-1/2 rounded-sm bg-[var(--color-border)]"></div>
                <div class="w-1/2 flex flex-col gap-0.5">
                  <div class="h-1.5 w-3/4 rounded-full bg-[var(--color-border-strong)]"></div>
                  <div class="h-1 w-1/2 rounded-full bg-[var(--color-border)]"></div>
                  <div class="flex-1 rounded-sm bg-[var(--color-border)]/50 mt-0.5"></div>
                </div>
              </div>
              <div class="w-full aspect-[4/3] rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-1.5 flex gap-1" v-else-if="opt.value === 'tabs'">
                <div class="w-1/2 rounded-sm bg-[var(--color-border)]"></div>
                <div class="w-1/2 flex flex-col gap-0.5">
                  <div class="h-1.5 w-3/4 rounded-full bg-[var(--color-border-strong)]"></div>
                  <div class="flex gap-0.5 mt-0.5">
                    <div class="h-1 w-4 rounded-full bg-[var(--color-accent)]"></div>
                    <div class="h-1 w-4 rounded-full bg-[var(--color-border)]"></div>
                    <div class="h-1 w-4 rounded-full bg-[var(--color-border)]"></div>
                  </div>
                  <div class="flex-1 rounded-sm bg-[var(--color-border)]/50 mt-0.5"></div>
                </div>
              </div>
              <div class="w-full aspect-[4/3] rounded border border-[var(--color-border)] bg-[var(--color-bg)] p-1.5 flex flex-col gap-1" v-else>
                <div class="h-1/2 w-full rounded-sm bg-[var(--color-border)]"></div>
                <div class="h-1.5 w-2/3 rounded-full bg-[var(--color-border-strong)]"></div>
                <div class="flex gap-1 flex-1">
                  <div class="w-1/2 rounded-sm bg-[var(--color-border)]/50"></div>
                  <div class="w-1/2 rounded-sm bg-[var(--color-border)]/50"></div>
                </div>
              </div>
              <div>
                <span class="text-[11px] font-semibold text-[var(--color-text-primary)]">{{ opt.label }}</span>
                <span class="block text-[10px] text-[var(--color-text-tertiary)]">{{ opt.desc }}</span>
              </div>
            </button>
          </div>
        </div>

        <!-- PDF-Anzeigemodus -->
        <div class="space-y-2">
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)]">PDF-Anzeigemodus</label>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Wie PDF-Link-Attribute in der Katalog-Detailansicht dargestellt werden.</p>
          <select class="pim-input text-xs" v-model="themeForm.pdf_display_mode">
            <option v-for="o in PDF_DISPLAY_MODE_OPTIONS" :key="o.value" :value="o.value">{{ o.label }} — {{ o.desc }}</option>
          </select>
        </div>

        <!-- PDF & Export im Katalog -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">PDF & Export</h4>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">PDF-Downloads und Exportfunktionen für den öffentlichen Katalog aktivieren.</p>
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="checkbox" v-model="themeForm.catalog_pdf_enabled" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            PDF-Download im Katalog aktivieren
          </label>
          <div v-if="themeForm.catalog_pdf_enabled" class="ml-6 space-y-2">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Standard-PDF-Vorlage</label>
              <select class="pim-input text-xs" v-model="themeForm.catalog_pdf_template_id">
                <option :value="null">— Keine Vorlage gewählt —</option>
                <option v-for="t in availablePdfTemplates" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
              </select>
              <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">Wird für PDF-Downloads im Katalog verwendet.</p>
            </div>
          </div>
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="checkbox" v-model="themeForm.catalog_excel_export_enabled" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            Excel-Export in Merkliste aktivieren
          </label>
        </div>

        <!-- Produktvergleich -->
        <div class="space-y-3">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Produktvergleich</h4>
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="checkbox" v-model="themeForm.catalog_compare_enabled" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            Produktvergleich im Katalog aktivieren
          </label>
          <div v-if="themeForm.catalog_compare_enabled" class="ml-6">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Max. Produkte im Vergleich</label>
            <select class="pim-input text-xs w-24" v-model="themeForm.catalog_compare_max_products">
              <option :value="2">2</option>
              <option :value="3">3</option>
            </select>
          </div>
        </div>

        <!-- Merkliste teilen -->
        <div class="space-y-2">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Teilen</h4>
          <label class="flex items-center gap-2 text-xs cursor-pointer">
            <input type="checkbox" v-model="themeForm.catalog_share_wishlist_enabled" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            Merkliste teilen per Link aktivieren
          </label>
        </div>

        <!-- Produktbeziehungen -->
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <GitBranch class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="2" />
            <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Produktbeziehungen</h4>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Beziehungsarten, die im Vorschaukatalog als eigener Tab auf der Produktdetailseite angezeigt werden. Bei Auswahl erscheint ein Tab „Beziehungen" mit SKU und Name (anklickbar).</p>
          <div v-if="availableRelationTypes.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Beziehungsarten vorhanden</div>
          <div v-else class="space-y-1">
            <label
              v-for="rt in availableRelationTypes"
              :key="rt.id"
              class="flex items-center gap-2 text-xs cursor-pointer text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
            >
              <input
                type="checkbox"
                :checked="(themeForm.catalog_relation_type_ids || []).includes(rt.id)"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]"
                @change="
                  $event.target.checked
                    ? themeForm.catalog_relation_type_ids.push(rt.id)
                    : themeForm.catalog_relation_type_ids = themeForm.catalog_relation_type_ids.filter(id => id !== rt.id)
                "
              />
              {{ rt.name_de || rt.technical_name }}
            </label>
          </div>
        </div>

        <!-- Beschreibung (Produktdetail) -->
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <BookOpen class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="2" />
            <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Beschreibung (Produktdetail)</h4>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Attribute, die im Beschreibungs-Bereich der Produktdetailseite angezeigt werden. Reihenfolge und Typografie sind konfigurierbar.</p>
          <div v-if="allAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute vorhanden</div>
          <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-1">
            <!-- Selected description attributes (draggable, ordered, with typography) -->
            <div>
              <p class="text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Ausgewählt (Reihenfolge ziehen)</p>
              <div v-if="selectedDescAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] italic py-3 px-2 border border-dashed border-[var(--color-border)] rounded-md text-center">
                Keine Attribute ausgewählt – Standard-Beschreibung wird verwendet
              </div>
              <div class="space-y-1">
                <div
                  v-for="(da, idx) in selectedDescAttributes"
                  :key="da.attribute_id"
                  draggable="true"
                  @dragstart="onDescDragStart(idx)"
                  @dragover="onDescDragOver($event, idx)"
                  @drop.prevent
                  @dragend="onDescDragEnd"
                  class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-bg-elevated,var(--color-bg))] cursor-grab active:cursor-grabbing hover:border-[var(--color-accent)] transition-colors group"
                  :class="{ 'opacity-50': descDragIdx === idx }"
                >
                  <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
                  <span class="text-[11px] font-medium text-[var(--color-accent)] w-4 shrink-0">{{ idx + 1 }}</span>
                  <span class="text-xs text-[var(--color-text-primary)] truncate flex-1">{{ da.name }}</span>
                  <select
                    class="text-[10px] px-1 py-0.5 rounded border border-[var(--color-border)] bg-[var(--color-bg)] text-[var(--color-text-secondary)] shrink-0 cursor-pointer"
                    :value="da.typography"
                    @change="setDescTypography(da.attribute_id, $event.target.value)"
                  >
                    <option v-for="t in TYPOGRAPHY_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</option>
                  </select>
                  <button
                    type="button"
                    class="p-0.5 rounded hover:bg-red-100 text-[var(--color-text-tertiary)] hover:text-red-500 transition-colors shrink-0"
                    @click="removeDescAttribute(da.attribute_id)"
                    title="Entfernen"
                  >
                    <X class="w-3.5 h-3.5" :stroke-width="2" />
                  </button>
                </div>
              </div>
            </div>
            <!-- Available attributes -->
            <div>
              <p class="text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Verfügbar</p>
              <div class="space-y-1 max-h-48 overflow-y-auto">
                <div
                  v-for="attr in unselectedDescAttributes"
                  :key="attr.id"
                  class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-transparent hover:border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-pointer transition-colors"
                  @click="addDescAttribute(attr)"
                >
                  <Plus class="w-3.5 h-3.5 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
                  <span class="text-xs text-[var(--color-text-primary)] truncate flex-1">{{ attr.name_de || attr.name }}</span>
                  <span class="text-[10px] px-1 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">{{ attr.data_type }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Produktkarten -->
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <LayoutGrid class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="2" />
            <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Produktkarten</h4>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Darstellung und Inhalte der Produktkarten im Katalog.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                <Image class="w-3 h-3 inline-block mr-1" :stroke-width="2" />
                Bild-Seitenverhältnis
              </label>
              <select class="pim-input text-xs" v-model="themeForm.card_image_ratio">
                <option v-for="o in IMAGE_RATIO_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                <Image class="w-3 h-3 inline-block mr-1" :stroke-width="2" />
                Thumbnail-Bildtyp
              </label>
              <select class="pim-input text-xs" v-model="themeForm.thumbnail_usage_type_id">
                <option :value="null">Standard (Primärbild)</option>
                <option v-for="ut in availableUsageTypes" :key="ut.id" :value="ut.id">
                  {{ ut.name_de || ut.technical_name }}
                </option>
              </select>
              <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">Welcher Bildtyp als Thumbnail in den Produktkacheln verwendet wird.</p>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="flex flex-col justify-end gap-2">
              <label class="flex items-center gap-2 text-xs cursor-pointer min-h-[44px]">
                <input type="checkbox" v-model="themeForm.card_show_category" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                Kategorie-Pfad anzeigen
              </label>
              <label class="flex items-center gap-2 text-xs cursor-pointer min-h-[44px]">
                <input type="checkbox" v-model="themeForm.card_show_sku" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                Artikelnummer (SKU) anzeigen
              </label>
              <label class="flex items-center gap-2 text-xs cursor-pointer min-h-[44px]">
                <input type="checkbox" v-model="themeForm.card_show_price" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
                Preis anzeigen
              </label>
            </div>
          </div>
          <!-- Price type & country config (only shown when price is enabled) -->
          <div v-if="themeForm.card_show_price" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Preistyp</label>
              <select class="pim-input text-xs" v-model="themeForm.card_price_type_id">
                <option :value="null">Standard (Listenpreis)</option>
                <option v-for="pt in availablePriceTypes" :key="pt.id" :value="pt.id">
                  {{ pt.name_de || pt.name_en || pt.technical_name }}
                </option>
              </select>
              <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">Welcher Preistyp auf Karten angezeigt wird.</p>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Land (ISO)</label>
              <input
                type="text"
                class="pim-input text-xs uppercase"
                v-model="themeForm.card_price_country"
                maxlength="2"
                placeholder="z.B. DE, AT, CH"
              />
              <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">Länderspezifischer Preis (optional, 2-stelliger ISO-Code).</p>
            </div>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
              Primärattribut auf Karten
              <span class="text-[var(--color-text-tertiary)] font-normal">(wird prominent als Titel angezeigt)</span>
            </label>
            <select
              v-model="themeForm.primary_card_attribute_id"
              class="pim-input text-xs w-full sm:w-1/2"
            >
              <option :value="null">– Produktname verwenden –</option>
              <option v-for="attr in allAttributes" :key="attr.id" :value="attr.id">
                {{ attr.name_de || attr.name }} ({{ attr.data_type }})
              </option>
            </select>
            <p class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">Wird anstelle des Produktnamens als Hauptfeld auf der Kachel angezeigt.</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
              Attribute auf Karten
              <span class="text-[var(--color-text-tertiary)] font-normal">(max. 3 werden angezeigt – Reihenfolge per Drag &amp; Drop)</span>
            </label>
            <div v-if="allAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine Attribute vorhanden</div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-1">
              <!-- Selected attributes (draggable, ordered) -->
              <div>
                <p class="text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Ausgewählt (Reihenfolge ziehen)</p>
                <div v-if="selectedCardAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] italic py-3 px-2 border border-dashed border-[var(--color-border)] rounded-md text-center">
                  Keine Attribute ausgewählt
                </div>
                <div class="space-y-1">
                  <div
                    v-for="(attr, idx) in selectedCardAttributes"
                    :key="attr.id"
                    draggable="true"
                    @dragstart="onCardDragStart(idx)"
                    @dragover="onCardDragOver($event, idx)"
                    @drop.prevent
                    @dragend="onCardDragEnd"
                    class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-bg-elevated,var(--color-bg))] cursor-grab active:cursor-grabbing hover:border-[var(--color-accent)] transition-colors group"
                    :class="{ 'opacity-50': cardDragIdx === idx }"
                  >
                    <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
                    <span class="text-[11px] font-medium text-[var(--color-accent)] w-4 shrink-0">{{ idx + 1 }}</span>
                    <span class="text-xs text-[var(--color-text-primary)] truncate flex-1">{{ attr.name_de || attr.name }}</span>
                    <span class="text-[10px] px-1 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">{{ attr.data_type }}</span>
                    <button
                      type="button"
                      class="p-0.5 rounded hover:bg-red-100 text-[var(--color-text-tertiary)] hover:text-red-500 transition-colors shrink-0"
                      @click="removeCardAttribute(attr.id)"
                      title="Entfernen"
                    >
                      <X class="w-3.5 h-3.5" :stroke-width="2" />
                    </button>
                  </div>
                </div>
              </div>
              <!-- Available attributes -->
              <div>
                <p class="text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Verfügbar</p>
                <div class="space-y-1 max-h-48 overflow-y-auto">
                  <div
                    v-for="attr in unselectedCardAttributes"
                    :key="attr.id"
                    class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-transparent hover:border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-pointer transition-colors"
                    @click="addCardAttribute(attr)"
                  >
                    <Plus class="w-3.5 h-3.5 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
                    <span class="text-xs text-[var(--color-text-primary)] truncate flex-1">{{ attr.name_de || attr.name }}</span>
                    <span class="text-[10px] px-1 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">{{ attr.data_type }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Facettensuche -->
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <Filter class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="2" />
            <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Facettensuche</h4>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Attribute als Filter-Facetten im Katalog anzeigen. Nur Wertelisten, Boolean, Dezimal, Ganzzahl und Text werden unterstuetzt.</p>
          <div v-if="availableAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)]">Keine passenden Attribute vorhanden</div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 mt-1">
            <label
              v-for="attr in availableAttributes"
              :key="attr.id"
              class="flex items-center gap-2 px-2.5 py-2 rounded-md cursor-pointer hover:bg-[var(--color-bg)] transition-colors min-h-[44px]"
            >
              <input
                type="checkbox"
                :value="attr.id"
                :checked="themeForm.facet_attribute_ids.includes(attr.id)"
                @change="
                  $event.target.checked
                    ? themeForm.facet_attribute_ids.push(attr.id)
                    : themeForm.facet_attribute_ids = themeForm.facet_attribute_ids.filter(id => id !== attr.id)
                "
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] shrink-0"
              />
              <span class="text-xs text-[var(--color-text-primary)] truncate">{{ attr.name_de || attr.name }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">{{ attr.data_type }}</span>
            </label>
          </div>
        </div>

        </div><!-- end TAB: Katalog -->

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

    <!-- SVG Live Preview -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Monitor class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Vorschau</h3>
        <div class="flex gap-1 ml-auto">
          <button
            class="px-2.5 py-1 text-[11px] font-medium rounded transition-colors"
            :class="previewPage === 'overview'
              ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
              : 'text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
            @click="previewPage = 'overview'"
          >Übersicht</button>
          <button
            class="px-2.5 py-1 text-[11px] font-medium rounded transition-colors"
            :class="previewPage === 'detail'
              ? 'bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
              : 'text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
            @click="previewPage = 'detail'"
          >Detailseite</button>
        </div>
      </div>

      <!-- Overview Page Preview -->
      <div v-if="previewPage === 'overview'" class="rounded-lg border border-[var(--color-border)] overflow-hidden">
        <svg viewBox="0 0 800 500" class="w-full" xmlns="http://www.w3.org/2000/svg">
          <!-- Header -->
          <rect width="800" height="48" :fill="themeForm.color_header_bg || themeForm.color_primary" />
          <rect x="20" y="14" width="80" height="20" rx="3" fill="white" opacity="0.9" />
          <rect x="300" y="16" width="200" height="16" rx="8" fill="white" opacity="0.15" />
          <circle cx="760" cy="24" r="12" :fill="themeForm.color_accent" opacity="0.8" />
          <!-- Nav bar -->
          <rect y="48" width="800" height="32" :fill="themeForm.color_table_bg || '#F5F5F5'" />
          <rect x="120" y="58" width="60" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <rect x="200" y="58" width="80" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <rect x="300" y="58" width="70" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <rect x="390" y="58" width="75" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <!-- Body background -->
          <rect y="80" width="800" height="420" :fill="themeForm.color_table_bg || '#F5F5F5'" />
          <!-- Sidebar -->
          <rect x="0" y="80" width="180" height="420" :fill="themeForm.color_mobile_menu_bg || '#FFFFFF'" />
          <line x1="180" y1="80" x2="180" y2="500" stroke="#E0E0E0" stroke-width="1" />
          <!-- Sidebar items -->
          <rect x="16" y="100" width="100" height="8" rx="2" :fill="themeForm.color_sidebar || themeForm.color_primary" opacity="0.8" />
          <rect x="24" y="120" width="85" height="6" rx="2" :fill="themeForm.color_mobile_menu_text || '#333'" opacity="0.45" />
          <rect x="24" y="134" width="75" height="6" rx="2" :fill="themeForm.color_mobile_menu_text || '#333'" opacity="0.45" />
          <rect x="24" y="148" width="90" height="6" rx="2" :fill="themeForm.color_mobile_menu_text || '#333'" opacity="0.45" />
          <rect x="24" y="162" width="70" height="6" rx="2" :fill="themeForm.color_mobile_menu_text || '#333'" opacity="0.45" />
          <rect x="16" y="184" width="95" height="8" rx="2" :fill="themeForm.color_sidebar || themeForm.color_primary" opacity="0.8" />
          <rect x="24" y="204" width="80" height="6" rx="2" :fill="themeForm.color_mobile_menu_text || '#333'" opacity="0.45" />
          <rect x="24" y="218" width="85" height="6" rx="2" :fill="themeForm.color_mobile_menu_text || '#333'" opacity="0.45" />
          <!-- Product cards row 1 -->
          <g v-for="col in 3" :key="'r1-'+col">
            <rect :x="196 + (col-1)*204" y="100" width="190" height="170" rx="6" fill="white" stroke="#E5E7EB" stroke-width="1" />
            <rect :x="210 + (col-1)*204" y="112" width="162" height="90" rx="3" :fill="themeForm.color_table_stripe || '#F0F0F0'" />
            <rect :x="210 + (col-1)*204" y="214" width="100" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.7" />
            <rect :x="210 + (col-1)*204" y="228" width="130" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.35" />
            <rect :x="210 + (col-1)*204" y="240" width="80" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.25" />
            <rect :x="310 + (col-1)*204" y="252" width="60" height="10" rx="3" :fill="themeForm.color_button || themeForm.color_accent" opacity="0.15" />
          </g>
          <!-- Product cards row 2 -->
          <g v-for="col in 3" :key="'r2-'+col">
            <rect :x="196 + (col-1)*204" y="284" width="190" height="170" rx="6" fill="white" stroke="#E5E7EB" stroke-width="1" />
            <rect :x="210 + (col-1)*204" y="296" width="162" height="90" rx="3" :fill="themeForm.color_table_stripe || '#F0F0F0'" />
            <rect :x="210 + (col-1)*204" y="398" width="110" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.7" />
            <rect :x="210 + (col-1)*204" y="412" width="90" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.35" />
            <rect :x="210 + (col-1)*204" y="424" width="70" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.25" />
            <rect :x="310 + (col-1)*204" y="436" width="60" height="10" rx="3" :fill="themeForm.color_button || themeForm.color_accent" opacity="0.15" />
          </g>
        </svg>
      </div>

      <!-- Detail Page Preview -->
      <div v-if="previewPage === 'detail'" class="rounded-lg border border-[var(--color-border)] overflow-hidden">
        <svg viewBox="0 0 800 500" class="w-full" xmlns="http://www.w3.org/2000/svg">
          <!-- Header -->
          <rect width="800" height="48" :fill="themeForm.color_header_bg || themeForm.color_primary" />
          <rect x="20" y="14" width="80" height="20" rx="3" fill="white" opacity="0.9" />
          <rect x="300" y="16" width="200" height="16" rx="8" fill="white" opacity="0.15" />
          <circle cx="760" cy="24" r="12" :fill="themeForm.color_accent" opacity="0.8" />
          <!-- Breadcrumb -->
          <rect y="48" width="800" height="28" :fill="themeForm.color_table_bg || '#F5F5F5'" />
          <rect x="30" y="57" width="50" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.3" />
          <text x="86" y="63" font-size="8" :fill="themeForm.color_body_text || '#333'" opacity="0.3">›</text>
          <rect x="96" y="57" width="70" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.3" />
          <text x="172" y="63" font-size="8" :fill="themeForm.color_body_text || '#333'" opacity="0.3">›</text>
          <rect x="182" y="57" width="90" height="6" rx="2" :fill="themeForm.color_accent" opacity="0.5" />
          <!-- Body -->
          <rect y="76" width="800" height="424" :fill="themeForm.color_table_bg || '#F5F5F5'" />
          <!-- Product image -->
          <rect x="30" y="96" width="300" height="280" rx="6" fill="white" stroke="#E5E7EB" stroke-width="1" />
          <rect x="50" y="116" width="260" height="240" rx="3" :fill="themeForm.color_table_stripe || '#F0F0F0'" />
          <!-- Image placeholder icon -->
          <rect x="150" y="210" width="60" height="40" rx="4" :fill="themeForm.color_body_text || '#333'" opacity="0.08" />
          <!-- Product info -->
          <rect x="360" y="96" width="320" height="18" rx="3" :fill="themeForm.color_primary" opacity="0.85" />
          <rect x="360" y="124" width="120" height="8" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.35" />
          <rect x="360" y="148" width="380" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <rect x="360" y="162" width="350" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <rect x="360" y="176" width="280" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <!-- More info link -->
          <rect x="360" y="200" width="120" height="8" rx="2" :fill="themeForm.color_primary" opacity="0.6" />
          <!-- Separator -->
          <line x1="360" y1="224" x2="740" y2="224" stroke="#E5E7EB" stroke-width="1" />
          <!-- Attributes table -->
          <rect x="360" y="236" width="380" height="24" rx="3" :fill="themeForm.color_table_stripe || '#F0F0F0'" />
          <rect x="370" y="243" width="80" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <rect x="560" y="243" width="100" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <rect x="370" y="270" width="70" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <rect x="560" y="270" width="80" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <rect x="360" y="288" width="380" height="24" rx="3" :fill="themeForm.color_table_stripe || '#F0F0F0'" />
          <rect x="370" y="295" width="90" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <rect x="560" y="295" width="110" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <rect x="370" y="322" width="60" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.4" />
          <rect x="560" y="322" width="70" height="6" rx="2" :fill="themeForm.color_body_text || '#333'" opacity="0.5" />
          <!-- CTA Button -->
          <rect x="360" y="352" width="160" height="32" rx="5" :fill="themeForm.color_button || themeForm.color_accent" />
          <rect x="390" y="364" width="100" height="8" rx="2" fill="white" opacity="0.9" />
        </svg>
      </div>
    </div>

    </template><!-- end TAB: Preview Katalog -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: OFFLINE                                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'offline' && isAdmin">

    <!-- Offline-Katalog: Generierung -->
    <div class="pim-card p-4 sm:p-6 space-y-5">
      <div class="flex items-center gap-3 mb-2">
        <WifiOff class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Offline-Katalog</h3>
      </div>

      <p class="text-xs text-[var(--color-text-tertiary)]">
        Generiert eine ZIP-Datei mit allen Katalogdaten als JSON-Chunks.
        Die ZIP kann auf einem Webserver oder lokal ohne Backend betrieben werden.
        PDF-Export und Excel-Export stehen im Offline-Katalog nicht zur Verfügung.
      </p>

      <!-- Offline Bundle Status -->
      <div class="p-3 rounded-lg border" :class="offlineBundleStatus?.built ? 'border-[var(--color-border)] bg-[var(--color-bg)]' : 'border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800'">
        <div class="text-xs">
          <span v-if="offlineBundleStatus?.built" class="flex items-center gap-1.5 text-[var(--color-text-secondary)]">
            <CheckCircle class="w-3.5 h-3.5 text-green-500" />
            Offline-Bundle vorhanden
            <span class="text-[var(--color-text-tertiary)]">
              ({{ formatFileSize(offlineBundleStatus.js_size + offlineBundleStatus.css_size) }},
              gebaut am {{ new Date(offlineBundleStatus.built_at).toLocaleDateString('de') }})
            </span>
          </span>
          <span v-else class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
            <AlertTriangle class="w-3.5 h-3.5" />
            Offline-Bundle nicht vorhanden. Bitte per Deployment bereitstellen:
            <code class="ml-1 text-[10px] bg-[var(--color-bg)] px-1.5 py-0.5 rounded font-mono">cd catalog-embed && VITE_BUILD_TARGET=offline npx vite build</code>
          </span>
        </div>
      </div>

      <!-- Template Selection -->
      <div class="space-y-1">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)]">Katalog-Vorlage</label>
        <select
          class="pim-input text-xs max-w-md"
          v-model="offlineCatalogTemplateId"
          :disabled="offlineCatalogExporting"
        >
          <option :value="null">— Standard (Basic) —</option>
          <option v-for="tpl in offlineCatalogTemplates" :key="tpl.id" :value="tpl.id">
            {{ tpl.name }}
          </option>
        </select>
        <p class="text-[10px] text-[var(--color-text-tertiary)]">
          HTML-Template für die index.html im ZIP. Vorlagen können unter „Katalog Vorlagen" erstellt werden.
        </p>
      </div>

      <!-- Export Button / Progress -->
      <div v-if="offlineCatalogExporting" class="space-y-3">
        <div class="flex items-center gap-2">
          <Loader2 class="w-4 h-4 animate-spin text-[var(--color-accent)]" />
          <span class="text-xs font-medium text-[var(--color-text-secondary)]">
            {{ offlineCatalogProgress?.phase || 'Exportiere...' }}
          </span>
        </div>
        <div v-if="offlineCatalogProgress?.total > 0" class="space-y-1.5">
          <div class="w-full bg-[var(--color-bg-tertiary)] rounded-full h-2">
            <div
              class="bg-[var(--color-accent)] h-2 rounded-full transition-all duration-300"
              :style="{ width: (offlineCatalogProgress?.percent || 0) + '%' }"
            ></div>
          </div>
          <div class="flex items-center justify-between">
            <p class="text-[11px] text-[var(--color-text-tertiary)]">
              {{ offlineCatalogProgress?.current?.toLocaleString() }} / {{ offlineCatalogProgress?.total?.toLocaleString() }} Produkte
              ({{ offlineCatalogProgress?.percent || 0 }}%)
            </p>
            <button
              class="pim-btn-sm pim-btn-secondary text-[11px] !py-0.5 !px-2"
              @click="cancelOfflineCatalogExport"
            >
              Abbrechen
            </button>
          </div>
        </div>
        <p v-else class="text-[10px] text-[var(--color-text-tertiary)]">
          Drücke <kbd class="px-1 py-0.5 bg-[var(--color-bg-tertiary)] rounded text-[9px]">Esc</kbd> zum Abbrechen
        </p>
      </div>

      <div v-else class="space-y-3">
        <button
          class="pim-btn pim-btn-primary text-xs"
          @click="startOfflineCatalogExport"
        >
          <Play class="w-3.5 h-3.5" /> Offline-Katalog generieren
        </button>

        <!-- Error -->
        <div v-if="offlineCatalogError" class="flex items-center gap-2 text-xs text-red-600">
          <XCircle class="w-3.5 h-3.5" />
          {{ offlineCatalogError }}
        </div>

        <!-- Success + Download -->
        <div v-if="offlineCatalogResult" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg space-y-3">
          <div class="flex items-center gap-2 text-sm text-green-700 dark:text-green-400 font-medium">
            <CheckCircle class="w-4 h-4" />
            Export abgeschlossen
          </div>
          <div class="text-xs text-[var(--color-text-secondary)] space-y-0.5">
            <p>{{ offlineCatalogResult.total_products?.toLocaleString() }} Produkte in {{ offlineCatalogResult.chunks }} Chunks</p>
            <p>{{ formatFileSize(offlineCatalogResult.file_size) }} · {{ offlineCatalogResult.duration }}s</p>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <button
              class="pim-btn pim-btn-primary text-xs"
              @click="downloadOfflineCatalog"
            >
              <HardDrive class="w-3.5 h-3.5" /> ZIP herunterladen
            </button>
            <button
              class="pim-btn pim-btn-secondary text-xs"
              @click="previewOfflineCatalog"
            >
              <Eye class="w-3.5 h-3.5" /> Vorschau
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Aufräumen -->
    <div class="pim-card p-4 sm:p-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h4 class="text-xs font-medium text-[var(--color-text-secondary)] mb-1">Aufräumen</h4>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">
            Generierte ZIPs, Preview-Dateien und temporäre Arbeitsdateien löschen.
          </p>
        </div>
        <button
          class="pim-btn-sm pim-btn-secondary text-xs shrink-0"
          :disabled="offlineCatalogCleaning"
          @click="cleanupOfflineCatalog"
        >
          <Trash2 class="w-3 h-3" />
          {{ offlineCatalogCleaning ? 'Bereinige...' : 'Aufräumen' }}
        </button>
      </div>
      <div v-if="offlineCatalogCleanupResult" class="mt-2 text-xs text-green-600 dark:text-green-400">
        {{ offlineCatalogCleanupResult }}
      </div>
    </div>

    </template><!-- end TAB: Offline -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: GENERELL (continued — admin sections)                         -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'general' && isAdmin">

    <!-- Admin: Demo-Daten laden -->
    <div v-if="isAdmin" class="pim-card border border-blue-300 dark:border-blue-800 p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Database class="w-5 h-5 text-blue-500" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-blue-600 dark:text-blue-400">Demo-Daten laden</h3>
      </div>

      <p class="text-xs text-[var(--color-text-tertiary)]">
        Alle Produktdaten werden gelöscht und durch ein vollständiges Demo-Sortiment ersetzt
        (Produkttypen, Attribute, Hierarchien, Produkte, Preise, Medien, Beziehungen).
        Benutzer, Rollen und Berechtigungen bleiben erhalten.
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
      <div class="flex items-center gap-3 flex-wrap" v-if="!loadingDemo">
        <button
          v-if="!showConfirmDemo"
          @click="showConfirmDemo = true"
          class="pim-btn pim-btn-primary"
        >
          <Database class="w-4 h-4" />
          Demo-Daten laden
        </button>
        <template v-if="showConfirmDemo">
          <span class="text-xs text-[var(--color-text-secondary)]">Alle Daten werden gelöscht und durch Demo-Daten ersetzt!</span>
          <button @click="triggerLoadDemo" class="pim-btn pim-btn-danger">Ja, laden</button>
          <button @click="showConfirmDemo = false" class="pim-btn pim-btn-secondary">Abbrechen</button>
        </template>
      </div>
    </div>

    <!-- Admin: Testdaten-Generator -->
    <div v-if="isAdmin" class="pim-card border border-purple-300 dark:border-purple-800 p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Server class="w-5 h-5 text-purple-500" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-purple-600 dark:text-purple-400">Testdaten-Generator</h3>
      </div>

      <p class="text-xs text-[var(--color-text-tertiary)]">
        Generiert realistische Testprodukte basierend auf dem vorhandenen Datenmodell (Produkttypen, Attribute, Hierarchien).
        Testprodukte erhalten das SKU-Präfix <code class="text-[10px] bg-[var(--color-bg-secondary)] px-1 py-0.5 rounded">TEST-</code>
        und können separat wieder gelöscht werden.
      </p>

      <!-- Stats -->
      <div v-if="testDataStats" class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs text-[var(--color-text-secondary)] bg-[var(--color-bg-secondary)] rounded-lg px-4 py-2.5">
        <span><strong class="text-[var(--color-text-primary)]">{{ testDataStats.test_products ?? 0 }}</strong> Testprodukte</span>
        <span><strong class="text-[var(--color-text-primary)]">{{ testDataStats.test_attribute_values ?? 0 }}</strong> Attributwerte</span>
        <span><strong class="text-[var(--color-text-primary)]">{{ testDataStats.test_prices ?? 0 }}</strong> Preise</span>
        <span><strong class="text-[var(--color-text-primary)]">{{ testDataStats.total_products ?? 0 }}</strong> Produkte gesamt</span>
      </div>

      <!-- Settings -->
      <div v-if="!generatingTestData && !cleaningTestData" class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-secondary)] mb-1">Anzahl Produkte</label>
          <input v-model.number="testDataForm.count" type="number" min="1" max="100000" step="100"
            class="pim-input text-sm w-full" />
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-secondary)] mb-1">Attribute pro Produkt</label>
          <input v-model.number="testDataForm.attributes_per_product" type="number" min="1" max="500"
            :placeholder="'Alle'"
            class="pim-input text-sm w-full" />
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-secondary)] mb-1">Kategorien</label>
          <input v-model.number="testDataForm.category_count" type="number" min="1" max="200"
            class="pim-input text-sm w-full" />
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--color-text-secondary)] mb-1">Kategorie-Tiefe</label>
          <input v-model.number="testDataForm.category_depth" type="number" min="1" max="10"
            class="pim-input text-sm w-full" />
        </div>
        <div class="flex items-end pb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="testDataForm.with_prices" type="checkbox" class="accent-purple-600 w-3.5 h-3.5" />
            <span class="text-xs text-[var(--color-text-secondary)]">Mit Preisen</span>
          </label>
        </div>
      </div>

      <!-- Result -->
      <div v-if="testDataResult" class="rounded-lg p-4 border" :class="testDataResult.cancelled ? 'bg-yellow-50 dark:bg-yellow-950/30 border-yellow-200 dark:border-yellow-800' : 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800'">
        <div class="flex items-center gap-2 text-sm font-medium" :class="testDataResult.cancelled ? 'text-yellow-700 dark:text-yellow-400' : 'text-green-700 dark:text-green-400'">
          <CheckCircle class="w-4 h-4" />
          {{ testDataResult.cancelled ? 'Generierung abgebrochen.' : testDataResult.message }}
        </div>
        <div class="flex gap-4 mt-2 text-xs" :class="testDataResult.cancelled ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400'">
          <span>{{ testDataResult.products_created }} Produkte</span>
          <span>{{ testDataResult.attribute_values_created }} Attributwerte</span>
          <span>{{ testDataResult.prices_created }} Preise</span>
          <span>{{ testDataResult.categories_created }} Kategorien</span>
        </div>
      </div>

      <!-- Cleanup Result -->
      <div v-if="cleanupResult" class="rounded-lg p-4 border bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800">
        <div class="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
          <CheckCircle class="w-4 h-4" />
          {{ cleanupResult.message }}
        </div>
      </div>

      <!-- Errors -->
      <div v-if="testDataError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
          <XCircle class="w-4 h-4" />
          Generierung fehlgeschlagen
        </div>
        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ testDataError }}</p>
      </div>
      <div v-if="cleanupError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
          <XCircle class="w-4 h-4" />
          Löschung fehlgeschlagen
        </div>
        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ cleanupError }}</p>
      </div>

      <!-- Loading / Progress -->
      <div v-if="generatingTestData" class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
            <Loader2 class="w-5 h-5 animate-spin text-purple-500" />
            <span>{{ testDataProgress?.phase || 'Testdaten werden generiert...' }}</span>
          </div>
          <button
            @click="triggerCancelTestData"
            :disabled="cancellingTestData"
            class="pim-btn pim-btn-secondary !text-[var(--color-error)] !border-[var(--color-error)]/30"
          >
            {{ cancellingTestData ? 'Wird abgebrochen...' : 'Abbrechen' }}
          </button>
        </div>
        <div v-if="testDataProgress" class="space-y-1">
          <div class="w-full bg-[var(--color-bg-tertiary)] rounded-full h-2.5 overflow-hidden">
            <div
              class="bg-purple-500 h-2.5 rounded-full transition-all duration-500"
              :style="{ width: testDataProgress.percent + '%' }"
            ></div>
          </div>
          <div class="flex justify-between text-[10px] text-[var(--color-text-tertiary)]">
            <span>{{ testDataProgress.current.toLocaleString() }} / {{ testDataProgress.total.toLocaleString() }} Produkte</span>
            <span>{{ testDataProgress.percent }}%</span>
          </div>
        </div>
      </div>
      <div v-if="cleaningTestData" class="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-5 h-5 animate-spin text-purple-500" />
        <span>Testdaten werden gelöscht...</span>
      </div>

      <!-- Buttons -->
      <div class="flex items-center gap-3 flex-wrap" v-if="!generatingTestData && !cleaningTestData">
        <!-- Generate Button -->
        <button
          v-if="!showConfirmGenerate"
          @click="showConfirmGenerate = true"
          class="pim-btn pim-btn-primary"
        >
          <Database class="w-4 h-4" />
          {{ testDataForm.count.toLocaleString() }} Testprodukte generieren
        </button>
        <template v-if="showConfirmGenerate">
          <span class="text-xs text-[var(--color-text-secondary)]">{{ testDataForm.count.toLocaleString() }} Produkte mit Attributwerten generieren?</span>
          <button @click="triggerGenerateTestData" class="pim-btn pim-btn-primary">Ja, generieren</button>
          <button @click="showConfirmGenerate = false" class="pim-btn pim-btn-secondary">Abbrechen</button>
        </template>

        <!-- Cleanup Button -->
        <template v-if="testDataStats && testDataStats.test_products > 0 && !showConfirmGenerate">
          <button
            v-if="!showConfirmCleanup"
            @click="showConfirmCleanup = true"
            class="pim-btn pim-btn-secondary !text-[var(--color-error)] !border-[var(--color-error)]/30"
          >
            <Trash2 class="w-4 h-4" />
            Testdaten löschen
          </button>
          <template v-if="showConfirmCleanup">
            <span class="text-xs text-[var(--color-text-secondary)]">Alle {{ testDataStats.test_products }} Testprodukte löschen?</span>
            <button @click="triggerCleanupTestData" class="pim-btn pim-btn-danger">Ja, löschen</button>
            <button @click="showConfirmCleanup = false" class="pim-btn pim-btn-secondary">Abbrechen</button>
          </template>
        </template>
      </div>
    </div>

    <!-- Admin: Reset Data Model (Danger Zone) -->
    <div v-if="authStore.userRole === 'Admin'" class="pim-card border border-red-300 dark:border-red-800 p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <AlertTriangle class="w-5 h-5 text-red-500" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">{{ t('settings.dangerZone') }}</h3>
      </div>

      <div>
        <h4 class="text-sm font-medium text-[var(--color-text-primary)] mb-1">{{ t('settings.resetTitle') }}</h4>
        <p class="text-xs text-[var(--color-text-tertiary)] mb-3">
          Löscht ausgewählte Daten unwiderruflich. Benutzer, Rollen, Berechtigungen und Zugangslinks bleiben immer erhalten.
        </p>

        <!-- Result message -->
        <div v-if="resultMessage" class="mb-3 text-xs px-3 py-2 rounded" :class="resultError ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'">
          {{ resultMessage }}
        </div>

        <!-- Confirmation dialog with category checkboxes -->
        <div v-if="showConfirm" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 space-y-4">

          <!-- Category selection -->
          <div v-if="resetCategories.length > 0" class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-xs text-red-700 dark:text-red-300 font-medium">Welche Daten sollen zurückgesetzt werden?</p>
              <div class="flex gap-2">
                <button @click="selectAllResetCategories" class="text-[10px] text-red-600 dark:text-red-400 hover:underline">Alle</button>
                <span class="text-[10px] text-red-300 dark:text-red-600">|</span>
                <button @click="deselectAllResetCategories" class="text-[10px] text-red-600 dark:text-red-400 hover:underline">Keine</button>
              </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <label
                v-for="cat in resetCategories"
                :key="cat.key"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-xs cursor-pointer transition-colors"
                :class="selectedResetCategories.includes(cat.key)
                  ? 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300 border border-red-300 dark:border-red-700'
                  : 'bg-white dark:bg-[var(--color-bg-secondary)] text-[var(--color-text-secondary)] border border-[var(--color-border)] hover:border-red-300 dark:hover:border-red-700'"
              >
                <input
                  type="checkbox"
                  :checked="selectedResetCategories.includes(cat.key)"
                  @change="toggleResetCategory(cat.key)"
                  class="accent-red-600 w-3.5 h-3.5"
                />
                {{ cat.label }}
              </label>
            </div>
            <p class="text-[10px] text-red-500 dark:text-red-400">
              {{ selectedResetCategories.length }} von {{ resetCategories.length }} Kategorien ausgewählt
            </p>
          </div>
          <div v-else-if="loadingCategories" class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)]">
            <Loader2 class="w-4 h-4 animate-spin" /> Kategorien werden geladen...
          </div>

          <!-- RESET confirmation -->
          <div class="space-y-3 pt-2 border-t border-red-200 dark:border-red-700">
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
                class="pim-btn pim-btn-danger"
                :disabled="confirmText !== 'RESET' || resetting || selectedResetCategories.length === 0"
                @click="executeReset"
              >
                <span v-if="resetting">{{ t('common.loading') }}</span>
                <span v-else>{{ t('settings.resetExecute') }}</span>
              </button>
              <button
                class="pim-btn pim-btn-secondary"
                :disabled="resetting"
                @click="cancelReset"
              >
                {{ t('common.cancel') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Initial button -->
        <button
          v-else
          class="pim-btn pim-btn-secondary !text-[var(--color-error)] !border-[var(--color-error)]/30"
          @click="openConfirmDialog"
        >
          {{ t('settings.resetButton') }}
        </button>
      </div>
    </div>

    <!-- Admin: PDF Processing & Search Reindex (combined) -->
    <div v-if="isAdmin" class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <RefreshCw class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">PDF-Verarbeitung & Suchindex</h3>
      </div>

      <p class="text-xs text-[var(--color-text-secondary)]">
        Erzeugt fehlende PDF-Vorschaubilder (WebP) und aktualisiert den Suchindex für den Vorschaukatalog.
        Empfohlen nach Massenänderungen, dem initialen Setup oder wenn PDF-Thumbnails fehlen.
      </p>

      <!-- Combined button -->
      <div class="flex items-center gap-3">
        <button
          @click="triggerCombinedProcess"
          :disabled="combinedProcessing || reindexing || pdfBatchProcessing"
          class="pim-btn pim-btn-primary"
        >
          <Loader2 v-if="combinedProcessing" class="w-4 h-4 animate-spin" />
          <RefreshCw v-else class="w-4 h-4" :stroke-width="1.75" />
          Thumbnails & Suchindex aktualisieren
        </button>
      </div>

      <!-- Progress indicator -->
      <div v-if="combinedProcessing || reindexing" class="space-y-2">
        <div class="flex items-center gap-3 text-xs text-[var(--color-text-secondary)]">
          <Loader2 class="w-3.5 h-3.5 animate-spin" />
          <span v-if="combinedStep === 'pdf'">Schritt 1/2 — PDF-Vorschaubilder werden erzeugt…</span>
          <span v-else-if="reindexProgress && reindexProgress.status === 'running'">
            {{ combinedStep === 'reindex' ? 'Schritt 2/2 — ' : '' }}Suchindex: {{ reindexProgress.processed?.toLocaleString('de-DE') }} / {{ reindexProgress.total?.toLocaleString('de-DE') }} Produkte ({{ reindexProgress.percent }}%)
          </span>
          <span v-else>Suchindex wird vorbereitet…</span>
        </div>
        <div v-if="reindexProgress && reindexProgress.status === 'running'" class="w-full bg-[var(--color-border)] rounded-full h-2 overflow-hidden">
          <div
            class="h-full bg-[var(--color-accent)] rounded-full transition-all duration-500 ease-out"
            :style="{ width: reindexProgress.percent + '%' }"
          />
        </div>
      </div>

      <div v-if="combinedResult" class="bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-lg p-3">
        <div class="flex items-center gap-2 text-green-700 dark:text-green-400 text-sm font-medium">
          <CheckCircle class="w-4 h-4" />
          {{ combinedResult.message }}
        </div>
      </div>

      <div v-if="combinedError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-3">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
          <XCircle class="w-4 h-4" />
          {{ combinedError }}
        </div>
      </div>

      <!-- Advanced: individual actions -->
      <details class="mt-2">
        <summary class="text-xs text-[var(--color-text-tertiary)] cursor-pointer hover:text-[var(--color-text-secondary)]">
          Erweiterte Optionen
        </summary>
        <div class="mt-3 space-y-3 pl-2 border-l-2 border-[var(--color-border)]">
          <!-- PDF only -->
          <div class="flex items-center gap-3">
            <select v-model="pdfBatchMode" class="pim-input text-xs py-1.5 px-2">
              <option value="missing">Nur fehlende</option>
              <option value="failed">Fehlende + fehlerhafte</option>
              <option value="all">Alle neu verarbeiten</option>
            </select>
            <button
              @click="triggerPdfBatchProcess"
              :disabled="pdfBatchProcessing || combinedProcessing"
              class="pim-btn pim-btn-secondary"
            >
              <Loader2 v-if="pdfBatchProcessing" class="w-3.5 h-3.5 animate-spin" />
              <BookOpen v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              Nur Vorschaubilder
            </button>
          </div>

          <div v-if="pdfBatchResult" class="bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-lg p-3">
            <div class="flex items-center gap-2 text-green-700 dark:text-green-400 text-sm font-medium">
              <CheckCircle class="w-4 h-4" />
              {{ pdfBatchResult.message }}
            </div>
          </div>

          <div v-if="pdfBatchError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-3">
            <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
              <XCircle class="w-4 h-4" />
              {{ pdfBatchError }}
            </div>
          </div>

          <!-- Reindex only -->
          <div class="flex items-center gap-3">
            <button
              @click="triggerReindex"
              :disabled="reindexing || combinedProcessing"
              class="pim-btn pim-btn-secondary"
            >
              <Loader2 v-if="reindexing" class="w-3.5 h-3.5 animate-spin" />
              <RefreshCw v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
              Nur Suchindex
            </button>
          </div>

          <div v-if="reindexResult && !combinedProcessing" class="bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-lg p-3">
            <div class="flex items-center gap-2 text-green-700 dark:text-green-400 text-sm font-medium">
              <CheckCircle class="w-4 h-4" />
              {{ reindexResult.message }}
            </div>
          </div>

          <div v-if="reindexError && !combinedProcessing" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-3">
            <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
              <XCircle class="w-4 h-4" />
              {{ reindexError }}
            </div>
          </div>
        </div>
      </details>
    </div>

    <!-- Admin: Deployment -->
    <div v-if="isAdmin" class="pim-card p-4 sm:p-6 space-y-5">
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
        <div class="flex items-center gap-3 text-[11px] text-[var(--color-text-tertiary)] pt-1 border-t border-[var(--color-border)]">
          <span>Web-User: <span class="font-mono">{{ serverStatus.web_user || '–' }}</span></span>
          <span v-if="serverStatus.deploy_user">Deploy-User: <span class="font-mono">{{ serverStatus.deploy_user }}</span></span>
          <span v-if="serverStatus.deploy_user" :class="serverStatus.sudo_available ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
            sudo: {{ serverStatus.sudo_available ? 'OK' : 'Nicht verfügbar' }}
          </span>
        </div>
        <div v-if="serverStatus.deploy_user && !serverStatus.sudo_available" class="bg-yellow-50 dark:bg-yellow-950/30 border border-yellow-200 dark:border-yellow-800 rounded p-2 text-[11px] text-yellow-700 dark:text-yellow-400">
          <strong>Hinweis:</strong> sudo ist nicht konfiguriert. Bitte in <code>/etc/sudoers.d/</code> konfigurieren:
          <code class="block mt-1 bg-yellow-100 dark:bg-yellow-900/30 p-1 rounded font-mono">{{ serverStatus.web_user }} ALL=({{ serverStatus.deploy_user }}) NOPASSWD: ALL</code>
          Oder <code>DEPLOY_USER</code> in <code>.env</code> entfernen, wenn PHP als Datei-Eigentümer läuft.
        </div>
      </div>

      <!-- Deploy-Button -->
      <div class="flex items-center gap-3">
        <button
          v-if="!showConfirmDeploy"
          @click="showConfirmDeploy = true"
          :disabled="deploying"
          class="pim-btn pim-btn-primary"
        >
          <Loader2 v-if="deploying" class="w-4 h-4 animate-spin" />
          <Server v-else class="w-4 h-4" :stroke-width="1.75" />
          Main Branch laden & deployen
        </button>

        <template v-if="showConfirmDeploy && !deploying">
          <span class="text-xs text-[var(--color-text-secondary)]">Sicher? Der Server wird aktualisiert.</span>
          <button @click="triggerDeploy" class="pim-btn pim-btn-danger">Ja, deployen</button>
          <button @click="showConfirmDeploy = false" class="pim-btn pim-btn-secondary">Abbrechen</button>
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
            class="pim-btn pim-btn-secondary"
          >
            <Loader2 v-if="rollingBack" class="w-3 h-3 animate-spin" />
            <template v-else>Rollback</template>
          </button>
        </div>
      </div>
    </div>

    </template><!-- end TAB: Generell (continued) -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: UMGEBUNG (ENV)                                                -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'env' && isAdmin">

    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <FileCode2 class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold">Umgebungsvariablen</h3>
        </div>
        <div class="flex items-center gap-2">
          <select v-model="envFilter" class="pim-input text-xs py-1 px-2">
            <option value="all">Alle</option>
            <option value="set">Nur gesetzte</option>
            <option value="unset">Nur fehlende</option>
          </select>
          <button class="pim-btn pim-btn-secondary text-xs py-1" @click="loadEnvInfo" :disabled="envLoading">
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': envLoading }" :stroke-width="2" />
          </button>
        </div>
      </div>

      <!-- .env file warning -->
      <div v-if="envMeta && !envMeta.env_file_exists" class="flex items-start gap-3 px-4 py-3 rounded-lg bg-[var(--color-warning)]/10 border border-[var(--color-warning)]/30">
        <AlertTriangle class="w-4 h-4 text-[var(--color-warning)] shrink-0 mt-0.5" :stroke-width="2" />
        <div class="text-xs">
          <p class="font-medium text-[var(--color-warning)]">Keine .env-Datei gefunden</p>
          <p class="text-[var(--color-text-secondary)] mt-0.5">Erwartet in: <code class="font-mono text-[11px] bg-[var(--color-bg-secondary)] px-1 rounded">{{ envMeta.env_file_path }}</code></p>
          <p class="text-[var(--color-text-tertiary)] mt-0.5">Die Werte werden direkt aus der .env-Datei gelesen. Wenn die Datei fehlt, erscheinen alle Variablen als leer.</p>
        </div>
      </div>

      <!-- Base path info -->
      <div v-if="envMeta" class="text-[11px] text-[var(--color-text-quaternary)] font-mono">
        Base Path: {{ envMeta.base_path }}
      </div>

      <div v-if="envLoading && envGroups.length === 0" class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-4 h-4 animate-spin" :stroke-width="2" />
        Lade Umgebungsvariablen...
      </div>

      <div v-else-if="envGroups.length === 0" class="text-sm text-[var(--color-text-tertiary)]">
        Keine Daten geladen.
      </div>

      <template v-else>
        <div v-for="group in filteredEnvGroups" :key="group.group" class="space-y-1">
          <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider pt-3 pb-1 border-b border-[var(--color-border)]">
            {{ group.group }}
          </h4>
          <div class="divide-y divide-[var(--color-border)]">
            <div
              v-for="item in group.items"
              :key="item.key"
              class="flex items-center gap-3 py-2 text-xs"
            >
              <div class="w-4 shrink-0">
                <Check v-if="item.is_set" class="w-3.5 h-3.5 text-[var(--color-success)]" :stroke-width="2.5" />
                <XCircle v-else class="w-3.5 h-3.5 text-[var(--color-text-quaternary)]" :stroke-width="2" />
              </div>
              <div class="min-w-0 flex-1">
                <span class="font-mono font-medium" :class="item.is_set ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)]'">{{ item.key }}</span>
                <span class="ml-2 text-[var(--color-text-tertiary)]">{{ item.description }}</span>
              </div>
              <div class="shrink-0 text-right">
                <span v-if="item.value" class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-[var(--color-bg-secondary)] text-[var(--color-text-secondary)]">{{ item.value }}</span>
                <span v-else class="text-[var(--color-text-quaternary)]">—</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    </template><!-- end TAB: Umgebung -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: PROZESSE                                                      -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'processes' && isAdmin">

    <!-- Service-Aktionen -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Power class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Service-Aktionen</h3>
      </div>

      <div class="flex flex-wrap gap-3">
        <button
          class="pim-btn pim-btn-secondary text-xs py-2 px-4 flex items-center gap-2"
          :disabled="apacheRestarting"
          @click="restartApache"
        >
          <RotateCw class="w-3.5 h-3.5" :class="{ 'animate-spin': apacheRestarting }" :stroke-width="2" />
          {{ apacheRestarting ? 'Apache wird neu geladen...' : 'Apache Reload' }}
        </button>
        <button
          class="pim-btn pim-btn-secondary text-xs py-2 px-4 flex items-center gap-2"
          :disabled="horizonRestarting"
          @click="restartHorizon"
        >
          <RotateCw class="w-3.5 h-3.5" :class="{ 'animate-spin': horizonRestarting }" :stroke-width="2" />
          {{ horizonRestarting ? 'Horizon wird neu gestartet...' : 'Horizon Neustart' }}
        </button>
      </div>
    </div>

    <!-- Queue-Übersicht -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <Zap class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold">Queue-Warteschlangen</h3>
        </div>
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-secondary text-xs py-1" @click="loadQueueJobs" :disabled="queueLoading">
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': queueLoading }" :stroke-width="2" />
          </button>
          <button
            class="pim-btn text-xs py-1 px-3 bg-[var(--color-error)]/10 text-[var(--color-error)] hover:bg-[var(--color-error)]/20 border border-[var(--color-error)]/20"
            :disabled="queueAction === 'all'"
            @click="flushQueue(null)"
          >
            <Trash2 class="w-3.5 h-3.5 mr-1 inline" :stroke-width="2" />
            Alle Queues leeren
          </button>
        </div>
      </div>

      <div v-if="queueLoading && !queueJobs" class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-4 h-4 animate-spin" :stroke-width="2" />
        Lade Queue-Status...
      </div>

      <!-- Queue Sizes -->
      <div v-if="queueJobs?.queue_sizes" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
        <div
          v-for="(size, queue) in queueJobs.queue_sizes"
          :key="queue"
          class="flex items-center justify-between px-3 py-2 rounded-lg border text-xs"
          :class="size > 0
            ? 'bg-[var(--color-warning)]/10 border-[var(--color-warning)]/30'
            : 'bg-[var(--color-bg)] border-[var(--color-border)]'"
        >
          <span class="font-mono font-medium text-[var(--color-text-primary)]">{{ queue }}</span>
          <div class="flex items-center gap-1.5">
            <span class="font-bold" :class="size > 0 ? 'text-[var(--color-warning)]' : 'text-[var(--color-text-quaternary)]'">{{ size }}</span>
            <button
              v-if="size > 0"
              class="text-[var(--color-error)] hover:text-[var(--color-error)]/80 p-0.5"
              :disabled="queueAction === queue"
              @click="flushQueue(queue)"
              :title="queue + ' leeren'"
            >
              <X class="w-3 h-3" :stroke-width="2.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Jobs-Liste -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <Activity class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold">Aktive &amp; fehlgeschlagene Jobs</h3>
        </div>
        <button
          v-if="queueJobs?.jobs?.some(j => j.type === 'failed')"
          class="pim-btn text-xs py-1 px-3 bg-[var(--color-error)]/10 text-[var(--color-error)] hover:bg-[var(--color-error)]/20 border border-[var(--color-error)]/20"
          :disabled="queueAction === 'failed'"
          @click="flushFailedJobs"
        >
          <Trash2 class="w-3.5 h-3.5 mr-1 inline" :stroke-width="2" />
          Alle fehlgeschlagenen löschen
        </button>
      </div>

      <div v-if="!queueJobs?.jobs?.length" class="text-sm text-[var(--color-text-tertiary)] py-4 text-center">
        Keine aktiven oder fehlgeschlagenen Jobs.
      </div>

      <div v-else class="space-y-2 max-h-[500px] overflow-y-auto">
        <div
          v-for="(job, idx) in queueJobs.jobs"
          :key="job.id || idx"
          class="flex items-center gap-3 px-4 py-3 rounded-lg border text-xs"
          :class="{
            'bg-[var(--color-success)]/5 border-[var(--color-success)]/20': job.type === 'running',
            'bg-[var(--color-warning)]/5 border-[var(--color-warning)]/20': job.type === 'pending',
            'bg-[var(--color-error)]/5 border-[var(--color-error)]/20': job.type === 'failed',
            'bg-[var(--color-accent)]/5 border-[var(--color-accent)]/20': job.type === 'import',
          }"
        >
          <!-- Status Icon -->
          <div class="shrink-0">
            <Play v-if="job.type === 'running'" class="w-4 h-4 text-[var(--color-success)]" :stroke-width="2" />
            <Clock v-else-if="job.type === 'pending'" class="w-4 h-4 text-[var(--color-warning)]" :stroke-width="2" />
            <XCircle v-else-if="job.type === 'failed'" class="w-4 h-4 text-[var(--color-error)]" :stroke-width="2" />
            <Loader2 v-else-if="job.type === 'import'" class="w-4 h-4 text-[var(--color-accent)] animate-spin" :stroke-width="2" />
          </div>

          <!-- Job Info -->
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-[var(--color-text-primary)]">
              {{ job.job }}
              <span class="font-normal text-[var(--color-text-tertiary)] ml-1">{{ job.queue }}</span>
            </p>
            <p v-if="job.error" class="text-[10px] text-[var(--color-error)] truncate mt-0.5">{{ job.error }}</p>
            <p v-if="job.created_at || job.failed_at" class="text-[10px] text-[var(--color-text-quaternary)] mt-0.5">
              {{ job.failed_at || job.created_at }}
            </p>
          </div>

          <!-- Actions -->
          <div class="shrink-0 flex items-center gap-1">
            <button
              v-if="job.type === 'import' && job.cancelable"
              class="pim-btn text-[11px] py-1 px-2 bg-[var(--color-error)]/10 text-[var(--color-error)] hover:bg-[var(--color-error)]/20 border border-[var(--color-error)]/20"
              :disabled="queueAction === job.id"
              @click="cancelJob(job.id, 'import')"
            >
              <Ban class="w-3 h-3 mr-1 inline" :stroke-width="2" />
              Abbrechen
            </button>
            <button
              v-if="job.type === 'failed' && job.id"
              class="pim-btn text-[11px] py-1 px-2 bg-[var(--color-error)]/10 text-[var(--color-error)] hover:bg-[var(--color-error)]/20 border border-[var(--color-error)]/20"
              :disabled="queueAction === job.id"
              @click="cancelJob(job.id, 'failed')"
            >
              <Trash2 class="w-3 h-3" :stroke-width="2" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Linux-Prozesse (www-data, mysql) -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <Terminal class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold">Linux-Prozesse (www-data &amp; mysql)</h3>
        </div>
        <button class="pim-btn pim-btn-secondary text-xs py-1" @click="loadSystemProcesses" :disabled="processesLoading">
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': processesLoading }" :stroke-width="2" />
        </button>
      </div>

      <div v-if="processesLoading && !systemProcesses" class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-4 h-4 animate-spin" :stroke-width="2" />
        Lade Prozesse...
      </div>

      <div v-else-if="!systemProcesses?.processes?.length && !systemProcesses?.mysql_queries?.length" class="text-sm text-[var(--color-text-tertiary)] py-4 text-center">
        Keine Prozesse geladen.
      </div>

      <template v-else>
        <!-- Prozess-Tabelle -->
        <div v-if="systemProcesses?.processes?.length" class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-left text-[var(--color-text-tertiary)]">
                <th class="py-2 px-2 font-medium">User</th>
                <th class="py-2 px-2 font-medium">PID</th>
                <th class="py-2 px-2 font-medium text-right">CPU%</th>
                <th class="py-2 px-2 font-medium text-right">MEM%</th>
                <th class="py-2 px-2 font-medium text-right">RSS</th>
                <th class="py-2 px-2 font-medium">Status</th>
                <th class="py-2 px-2 font-medium">Zeit</th>
                <th class="py-2 px-2 font-medium">Befehl</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="proc in systemProcesses.processes"
                :key="proc.pid"
                class="border-b border-[var(--color-border)]/50 hover:bg-[var(--color-bg-secondary)]/50"
              >
                <td class="py-1.5 px-2">
                  <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono"
                    :class="proc.user === 'mysql' ? 'bg-blue-500/10 text-blue-600' : 'bg-purple-500/10 text-purple-600'">
                    {{ proc.user }}
                  </span>
                </td>
                <td class="py-1.5 px-2 font-mono text-[var(--color-text-secondary)]">{{ proc.pid }}</td>
                <td class="py-1.5 px-2 text-right font-mono" :class="proc.cpu > 50 ? 'text-[var(--color-error)] font-bold' : proc.cpu > 10 ? 'text-[var(--color-warning)]' : 'text-[var(--color-text-secondary)]'">
                  {{ proc.cpu }}%
                </td>
                <td class="py-1.5 px-2 text-right font-mono text-[var(--color-text-secondary)]">{{ proc.mem }}%</td>
                <td class="py-1.5 px-2 text-right font-mono text-[var(--color-text-secondary)]">{{ proc.rss_mb }} MB</td>
                <td class="py-1.5 px-2 font-mono text-[var(--color-text-tertiary)]">{{ proc.stat }}</td>
                <td class="py-1.5 px-2 font-mono text-[var(--color-text-tertiary)]">{{ proc.time }}</td>
                <td class="py-1.5 px-2 font-mono text-[var(--color-text-secondary)] max-w-[400px] truncate" :title="proc.command">{{ proc.command }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- MySQL Queries -->
        <div v-if="systemProcesses?.mysql_queries?.length" class="mt-4">
          <div class="flex items-center gap-2 mb-3">
            <Database class="w-4 h-4 text-blue-500" :stroke-width="1.75" />
            <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">Aktive MySQL-Queries</h4>
          </div>
          <div class="space-y-2">
            <div
              v-for="q in systemProcesses.mysql_queries"
              :key="q.id"
              class="px-3 py-2 rounded-lg border text-xs"
              :class="q.time > 10 ? 'bg-[var(--color-error)]/5 border-[var(--color-error)]/20' : q.time > 3 ? 'bg-[var(--color-warning)]/5 border-[var(--color-warning)]/20' : 'bg-[var(--color-bg)] border-[var(--color-border)]'"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="font-mono font-medium text-[var(--color-text-primary)]">
                  ID {{ q.id }} · {{ q.user }}@{{ q.db || '?' }}
                </span>
                <span class="font-mono" :class="q.time > 10 ? 'text-[var(--color-error)] font-bold' : q.time > 3 ? 'text-[var(--color-warning)]' : 'text-[var(--color-text-tertiary)]'">
                  {{ q.time }}s · {{ q.command }}
                </span>
              </div>
              <p v-if="q.info" class="font-mono text-[10px] text-[var(--color-text-tertiary)] truncate" :title="q.info">{{ q.info }}</p>
              <p v-if="q.state" class="text-[10px] text-[var(--color-text-quaternary)]">{{ q.state }}</p>
            </div>
          </div>
        </div>
      </template>
    </div>

    </template><!-- end TAB: Prozesse -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: SYSTEM                                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'system' && isAdmin">

    <!-- Services -->
    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <Activity class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold">Services</h3>
        </div>
        <button class="pim-btn pim-btn-secondary text-xs py-1" @click="loadSystemStatus" :disabled="systemLoading">
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': systemLoading }" :stroke-width="2" />
        </button>
      </div>

      <div v-if="systemLoading && !systemStatus" class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-4 h-4 animate-spin" :stroke-width="2" />
        Lade Systemstatus...
      </div>

      <div v-else-if="!systemStatus" class="text-sm text-[var(--color-text-tertiary)]">
        Keine Daten geladen.
      </div>

      <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="svc in systemStatus.services"
            :key="svc.name"
            class="flex items-center gap-3 px-4 py-3 rounded-lg border"
            :class="serviceStatusBg(svc.status)"
          >
            <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="svc.status === 'running' ? 'bg-[var(--color-success)]' : svc.status === 'paused' ? 'bg-[var(--color-warning)]' : svc.status === 'error' || svc.status === 'stopped' ? 'bg-[var(--color-error)]' : 'bg-[var(--color-text-quaternary)]'"></div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold text-[var(--color-text-primary)]">{{ svc.name }}</p>
              <p class="text-[11px]" :class="serviceStatusColor(svc.status)">
                {{ serviceStatusLabel(svc.status) }}
                <span v-if="svc.version" class="text-[var(--color-text-tertiary)]">· {{ svc.version }}</span>
              </p>
              <p v-if="svc.error" class="text-[10px] text-[var(--color-error)] truncate mt-0.5">{{ svc.error }}</p>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Resources -->
    <div v-if="systemStatus" class="pim-card p-4 sm:p-6 space-y-5">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <Cpu class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold">Ressourcen</h3>
        </div>
        <button class="pim-btn pim-btn-secondary text-xs py-1" @click="loadSystemStatus" :disabled="systemLoading">
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': systemLoading }" :stroke-width="2" />
        </button>
      </div>

      <!-- RAM -->
      <div v-if="systemStatus.resources?.ram?.total_mb" class="space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="font-medium text-[var(--color-text-secondary)]">RAM</span>
          <span class="text-[var(--color-text-tertiary)]">
            {{ Math.round(systemStatus.resources.ram.used_mb / 1024 * 10) / 10 }} GB /
            {{ Math.round(systemStatus.resources.ram.total_mb / 1024 * 10) / 10 }} GB
            <span class="font-medium" :class="systemStatus.resources.ram.percent >= 90 ? 'text-[var(--color-error)]' : systemStatus.resources.ram.percent >= 75 ? 'text-[var(--color-warning)]' : 'text-[var(--color-success)]'">
              ({{ systemStatus.resources.ram.percent }}%)
            </span>
          </span>
        </div>
        <div class="w-full h-2 rounded-full bg-[var(--color-bg-secondary)] overflow-hidden">
          <div class="h-full rounded-full transition-all" :class="progressBarColor(systemStatus.resources.ram.percent)" :style="{ width: systemStatus.resources.ram.percent + '%' }"></div>
        </div>
      </div>

      <!-- Disk -->
      <div v-if="systemStatus.resources?.disk?.total_gb" class="space-y-2">
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-2">
            <span class="font-medium text-[var(--color-text-secondary)]">Festplatte</span>
            <span class="text-[10px] font-mono text-[var(--color-text-quaternary)]">{{ systemStatus.resources.disk.path }}</span>
          </div>
          <span class="text-[var(--color-text-tertiary)]">
            {{ systemStatus.resources.disk.used_gb }} GB /
            {{ systemStatus.resources.disk.total_gb }} GB
            <span class="font-medium" :class="systemStatus.resources.disk.percent >= 90 ? 'text-[var(--color-error)]' : systemStatus.resources.disk.percent >= 75 ? 'text-[var(--color-warning)]' : 'text-[var(--color-success)]'">
              ({{ systemStatus.resources.disk.percent }}%)
            </span>
          </span>
        </div>
        <div class="w-full h-2 rounded-full bg-[var(--color-bg-secondary)] overflow-hidden">
          <div class="h-full rounded-full transition-all" :class="progressBarColor(systemStatus.resources.disk.percent)" :style="{ width: systemStatus.resources.disk.percent + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- PHP Info -->
    <div v-if="systemStatus?.php" class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Server class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">PHP</h3>
        <span class="text-xs font-mono text-[var(--color-text-tertiary)]">{{ systemStatus.php.version }}</span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="text-xs">
          <p class="text-[var(--color-text-tertiary)]">Memory Limit</p>
          <p class="font-mono font-medium text-[var(--color-text-primary)]">{{ systemStatus.php.memory_limit }}</p>
        </div>
        <div class="text-xs">
          <p class="text-[var(--color-text-tertiary)]">Max Execution</p>
          <p class="font-mono font-medium text-[var(--color-text-primary)]">{{ systemStatus.php.max_execution_time }}s</p>
        </div>
        <div class="text-xs">
          <p class="text-[var(--color-text-tertiary)]">Upload Max</p>
          <p class="font-mono font-medium text-[var(--color-text-primary)]">{{ systemStatus.php.upload_max_filesize }}</p>
        </div>
        <div class="text-xs">
          <p class="text-[var(--color-text-tertiary)]">Post Max</p>
          <p class="font-mono font-medium text-[var(--color-text-primary)]">{{ systemStatus.php.post_max_size }}</p>
        </div>
      </div>

      <div class="pt-3 border-t border-[var(--color-border)]">
        <p class="text-xs font-medium text-[var(--color-text-secondary)] mb-2">Extensions</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="(loaded, ext) in systemStatus.php.extensions"
            :key="ext"
            class="inline-flex items-center gap-1 px-2 py-1 rounded text-[11px] font-mono border"
            :class="loaded
              ? 'bg-[var(--color-success)]/5 border-[var(--color-success)]/20 text-[var(--color-success)]'
              : 'bg-[var(--color-error)]/5 border-[var(--color-error)]/20 text-[var(--color-error)]'"
          >
            <Check v-if="loaded" class="w-3 h-3" :stroke-width="2.5" />
            <XCircle v-else class="w-3 h-3" :stroke-width="2" />
            {{ ext }}
          </span>
        </div>
      </div>
    </div>

    </template><!-- end TAB: System -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: LIZENZ                                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'license' && isAdmin">

    <div class="pim-card p-4 sm:p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <Key class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Lizenz</h3>
        <span
          class="pim-badge text-[10px]"
          :class="licenseStore.isEnterprise ? 'bg-[var(--color-success)]/10 text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]'"
        >
          {{ licenseStore.isEnterprise ? 'Enterprise' : 'Community' }}
        </span>
      </div>

      <!-- License info -->
      <div v-if="licenseStore.isEnterprise" class="space-y-3">
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div>
            <span class="text-[var(--color-text-tertiary)]">Kunde</span>
            <p class="font-medium text-[var(--color-text-primary)]">{{ licenseStore.customer }}</p>
          </div>
          <div>
            <span class="text-[var(--color-text-tertiary)]">Ablaufdatum</span>
            <p class="font-medium" :class="licenseStore.daysRemaining !== null && licenseStore.daysRemaining < 30 ? 'text-[var(--color-warning)]' : 'text-[var(--color-text-primary)]'">
              {{ licenseStore.expiresAt ? new Date(licenseStore.expiresAt).toLocaleDateString('de-DE') : 'Unbegrenzt' }}
              <span v-if="licenseStore.daysRemaining !== null" class="text-[var(--color-text-tertiary)]">({{ licenseStore.daysRemaining }} Tage)</span>
            </p>
          </div>
          <div>
            <span class="text-[var(--color-text-tertiary)]">Benutzer</span>
            <p class="font-medium text-[var(--color-text-primary)]">
              {{ licenseStore.currentUsers }} / {{ licenseStore.maxUsers || '∞' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Module overview -->
      <div class="space-y-2">
        <p class="text-xs font-medium text-[var(--color-text-secondary)]">Module</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div
            v-for="(mod, key) in licenseStore.modules"
            :key="key"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border text-xs"
            :class="mod.licensed
              ? 'border-[var(--color-success)]/30 bg-[var(--color-success)]/5'
              : 'border-[var(--color-border)] bg-[var(--color-bg)]'"
          >
            <Shield class="w-3.5 h-3.5 shrink-0" :class="mod.licensed ? 'text-[var(--color-success)]' : 'text-[var(--color-text-tertiary)]'" :stroke-width="2" />
            <div class="min-w-0">
              <p class="font-medium truncate" :class="mod.licensed ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)]'">{{ mod.name }}</p>
              <p class="text-[10px] text-[var(--color-text-tertiary)] truncate">{{ mod.description }}</p>
            </div>
            <span v-if="mod.licensed" class="ml-auto text-[10px] text-[var(--color-success)] font-medium shrink-0">Aktiv</span>
          </div>
        </div>
      </div>

      <!-- License key input -->
      <div class="pt-3 border-t border-[var(--color-border)] space-y-2">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)]">Lizenzschlüssel</label>
        <div class="flex gap-2">
          <input
            v-model="licenseKeyInput"
            type="text"
            class="pim-input flex-1 text-xs font-mono"
            placeholder="ANYPIM-..."
          />
          <button
            class="pim-btn pim-btn-primary text-xs"
            :disabled="!licenseKeyInput.trim() || licenseActivating"
            @click="activateLicense"
          >
            {{ licenseActivating ? 'Prüfe...' : 'Aktivieren' }}
          </button>
          <button
            v-if="licenseStore.isEnterprise"
            class="pim-btn pim-btn-secondary text-xs"
            :disabled="licenseActivating"
            @click="clearLicense"
          >
            Entfernen
          </button>
        </div>
        <p v-if="licenseError" class="text-xs text-[var(--color-error)]">{{ licenseError }}</p>
        <p v-if="licenseSuccess" class="text-xs text-[var(--color-success)]">Lizenz erfolgreich aktualisiert.</p>
      </div>
    </div>

    </template><!-- end TAB: Lizenz -->

  </div>
</template>
