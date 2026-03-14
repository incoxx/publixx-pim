<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'
import { Globe, Palette, AlertTriangle, Server, RotateCcw, CheckCircle, XCircle, Loader2, GitBranch, Database, Upload, Trash2, Save, Filter, LayoutGrid, Columns3, Image, Settings2, Paintbrush, BookOpen, GripVertical, Plus, X, Shield, Key, Eye, Monitor } from 'lucide-vue-next'
import { useLicenseStore } from '@/stores/license'
import adminApi from '@/api/admin'
import catalogApi from '@/api/catalog'
import mediaApi from '@/api/media'
import pdfTemplatesApi from '@/api/pdfTemplates'
import hierarchiesApi from '@/api/hierarchies'
import attributesApi, { attributeViews as attributeViewsApi } from '@/api/attributes'
import { priceTypes as priceTypesApi } from '@/api/prices'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import catalogPresets from '@/config/catalogPresets'

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
  } catch (e) {
    console.warn('Failed to load hierarchies:', e.message)
  }
}

async function loadAttributeViews() {
  try {
    const { data } = await attributeViewsApi.list()
    availableAttributeViews.value = (data.data || data || [])
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

    // Remove stale facet IDs that are no longer in the available attributes
    const availableIds = new Set(availableAttributes.value.map(a => a.id))
    themeForm.value.facet_attribute_ids = themeForm.value.facet_attribute_ids.filter(id => availableIds.has(id))
  } catch (e) {
    console.warn('Failed to load attributes:', e.message)
  }
}

async function loadPriceTypes() {
  try {
    const { data } = await priceTypesApi.list()
    availablePriceTypes.value = data.data || data || []
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
const availablePdfTemplates = ref([])
async function loadPdfTemplates() {
  try {
    const { data } = await pdfTemplatesApi.list()
    availablePdfTemplates.value = data.data || data
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
  }
})
</script>

<template>
  <div class="space-y-6 max-w-5xl">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('settings.title') }}</h2>

    <!-- ═══ TOP-LEVEL TABS ═══ -->
    <div class="flex gap-1 border-b border-[var(--color-border)]">
      <button
        v-for="tab in [
          { key: 'general', label: 'Generell', icon: Settings2 },
          ...(isAdmin ? [{ key: 'catalog', label: 'Preview Katalog', icon: Eye }] : []),
          ...(isAdmin ? [{ key: 'license', label: 'Lizenz', icon: Key }] : []),
        ]"
        :key="tab.key"
        class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
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

    </template><!-- end TAB: Generell -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: PREVIEW KATALOG                                               -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'catalog' && isAdmin">

    <div class="pim-card p-6 space-y-5">
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
    <div class="pim-card p-6 space-y-4">
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
    <!-- TAB: GENERELL (continued — admin sections)                         -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'general' && isAdmin">

    <!-- Admin: Demo-Daten laden -->
    <div v-if="isAdmin" class="pim-card border border-blue-300 dark:border-blue-800 p-6 space-y-4">
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
                class="px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                :disabled="confirmText !== 'RESET' || resetting || selectedResetCategories.length === 0"
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

    </template><!-- end TAB: Generell (continued) -->

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- TAB: LIZENZ                                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <template v-if="activeMainTab === 'license' && isAdmin">

    <div class="pim-card p-6 space-y-4">
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
