<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, RefreshCw, Image, Package, CheckCircle, XCircle, Clock,
  Play, Settings, Save, Search, ChevronDown, ChevronUp, X, Zap, Download, Trash2,
  RotateCcw, AlertTriangle,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'
import productsApi from '@/api/products'
import attributesApi from '@/api/attributes'

const route = useRoute()
const router = useRouter()

const connection = ref(null)
const syncLogs = ref([])
const loading = ref(false)
const syncing = ref(false)
const error = ref('')
const syncError = ref('')

// Sync-Dialog
const showSyncDialog = ref(false)
const syncType = ref('product')
const syncEntityId = ref('')
const syncLanguage = ref('de')

// Preview / Dry Run
const previewData = ref(null)
const previewing = ref(false)

// Produkt-Suche für Einzel-Sync
const productSearch = ref('')
const productSearchResults = ref([])
const productSearching = ref(false)
const selectedProduct = ref(null)
let productSearchTimeout = null

// Export-Profil Konfiguration
const showProfileConfig = ref(false)
const profiles = ref([])
const allAttributes = ref([])
const selectedProfileId = ref(null)
const shopwareFields = ref({})
const savingProfile = ref(false)
const profileSaveSuccess = ref(false)
const profileSyncing = ref(false)
const profileCancelled = ref(false)
const profileProgress = ref({ current: 0, total: 0, success: 0, failed: 0, currentSku: '' })
const hierarchySyncing = ref(false)
const hierarchySyncResult = ref(null)
const profileSyncResult = ref(null)

// Delta-Sync
const deltaSyncing = ref(false)
const deltaSyncResult = ref(null)

// Fehler-Detail-Anzeige
const expandedLogId = ref(null)

// Shopware-Pflichtfelder
const SHOPWARE_FIELD_DEFINITIONS = [
  { key: 'name', label: 'Produktname', description: 'Standard: product.name', defaultMode: 'default', defaultInfo: 'Produktname aus PIM' },
  { key: 'description', label: 'Beschreibung', description: 'Standard: aus Profil', defaultMode: 'default', defaultInfo: 'Aus Vorschau-Profil', modes: ['default', 'fixed', 'attribute', 'attributes'] },
  { key: 'ean', label: 'EAN', description: 'Standard: product.ean', defaultMode: 'default', defaultInfo: 'EAN aus PIM-Stammdaten' },
  { key: 'tax_id', label: 'Steuer-ID', description: 'Standard-Steuer aus Shopware', defaultMode: 'default', defaultInfo: 'Automatisch von Shopware' },
  { key: 'currency_id', label: 'Währung', description: 'Shopware-UUID', defaultMode: 'fixed', defaultValue: 'b7d2554b0ce847cd82f3ac9bd1c0dfca' },
  { key: 'manufacturer_id', label: 'Hersteller', description: 'Optional', defaultMode: 'default', defaultInfo: 'Kein Hersteller (optional)' },
  // Preise
  { key: 'price', label: 'Preis (brutto)', description: 'Standard: aus Profil', defaultMode: 'default', defaultInfo: 'Preis aus Profil oder erster Preis' },
  { key: 'purchase_price', label: 'Einkaufspreis', description: 'Purchase price', defaultMode: 'attribute' },
  { key: 'list_price', label: 'UVP (Listenpreis)', description: 'List price / UVP', defaultMode: 'attribute' },
  // Maße
  { key: 'width', label: 'Breite', description: 'Width in mm', defaultMode: 'attribute' },
  { key: 'height', label: 'Höhe', description: 'Height in mm', defaultMode: 'attribute' },
  { key: 'length', label: 'Länge', description: 'Length in mm', defaultMode: 'attribute' },
  { key: 'weight', label: 'Gewicht', description: 'Weight in kg', defaultMode: 'attribute' },
  // SEO
  { key: 'meta_title', label: 'SEO-Titel', description: 'Meta-Title', defaultMode: 'attribute' },
  { key: 'meta_description', label: 'SEO-Beschreibung', description: 'Meta-Description', defaultMode: 'attribute' },
  // Lagerbestand
  { key: 'stock', label: 'Lagerbestand', description: 'Shopware zeigt Produkte mit Stock=0 nicht an. Standard: 999', defaultMode: 'fixed', defaultValue: '999' },
]

const connectionId = computed(() => route.params.id)
const isShopware = computed(() => connection.value?.connector_type === 'shopware')
const selectedProfile = computed(() => profiles.value.find(p => p.id === selectedProfileId.value))

// Attribut-Name für Anzeige auflösen
function attributeName(attrId) {
  if (!attrId) return ''
  const attr = allAttributes.value.find(a => a.id === attrId)
  return attr ? `${attr.name_de || attr.technical_name}` : attrId.substring(0, 8) + '...'
}

onMounted(loadConnection)

async function loadConnection() {
  loading.value = true
  error.value = ''
  try {
    const [connRes, logsRes] = await Promise.all([
      connectorsApi.getConnection(connectionId.value),
      connectorsApi.syncLogs(connectionId.value, { per_page: 50 }),
    ])
    connection.value = connRes.data.data || connRes.data
    syncLogs.value = logsRes.data.data || logsRes.data

    if (isShopware.value) {
      selectedProfileId.value = connection.value.settings?.website_profile_id || null
      shopwareFields.value = connection.value.settings?.shopware_fields || {}

      for (const field of SHOPWARE_FIELD_DEFINITIONS) {
        if (!shopwareFields.value[field.key]) {
          shopwareFields.value[field.key] = {
            mode: field.defaultMode,
            value: field.defaultValue || '',
            attribute_id: '',
            attribute_ids: [],
          }
        }
      }

      // Property-Attribute-IDs und Medien-Toggle initialisieren
      if (!shopwareFields.value._property_attribute_ids) {
        shopwareFields.value._property_attribute_ids = connection.value.settings?.shopware_fields?._property_attribute_ids || []
      }
      if (shopwareFields.value._sync_media === undefined) {
        shopwareFields.value._sync_media = connection.value.settings?.shopware_fields?._sync_media ?? { enabled: true }
      }
      if (!shopwareFields.value._sales_channel_id) {
        shopwareFields.value._sales_channel_id = connection.value.settings?.shopware_fields?._sales_channel_id ?? { value: '' }
      }

      await Promise.all([loadProfiles(), loadAttributes()])
    }
  } catch (e) {
    error.value = 'Fehler beim Laden der Verbindung'
  } finally {
    loading.value = false
  }
}

async function loadProfiles() {
  try {
    const res = await connectorsApi.websiteProfiles()
    profiles.value = res.data.data || res.data || []
  } catch (e) { /* optional */ }
}

async function loadAttributes() {
  try {
    const res = await attributesApi.list({ perPage: 500, sort: 'name_de' })
    const data = res.data.data || res.data || []
    allAttributes.value = Array.isArray(data) ? data : (data.data || [])
  } catch (e) { /* optional */ }
}

// Produkt-Suche (debounced)
function onProductSearchInput() {
  clearTimeout(productSearchTimeout)
  if (productSearch.value.length < 2) {
    productSearchResults.value = []
    return
  }
  productSearchTimeout = setTimeout(async () => {
    productSearching.value = true
    try {
      const res = await productsApi.list({ search: productSearch.value, perPage: 8 })
      const data = res.data.data || res.data || []
      productSearchResults.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
      productSearchResults.value = []
    } finally {
      productSearching.value = false
    }
  }, 300)
}

function selectProduct(product) {
  selectedProduct.value = product
  syncEntityId.value = product.id
  productSearch.value = ''
  productSearchResults.value = []
}

function clearSelectedProduct() {
  selectedProduct.value = null
  syncEntityId.value = ''
  previewData.value = null
}

async function saveExportProfile() {
  savingProfile.value = true
  profileSaveSuccess.value = false
  try {
    const cleanedFields = {}
    for (const [key, mapping] of Object.entries(shopwareFields.value)) {
      if (key === '_property_attribute_ids') {
        cleanedFields[key] = Array.isArray(mapping) ? mapping : []
        continue
      }
      if (key === '_sync_media') {
        cleanedFields[key] = { enabled: mapping?.enabled ?? true }
        continue
      }
      if (key === '_sales_channel_id') {
        cleanedFields[key] = { value: mapping?.value || '' }
        continue
      }
      if (mapping.mode === 'default') {
        cleanedFields[key] = { mode: 'default' }
      } else if (mapping.mode === 'fixed') {
        cleanedFields[key] = { mode: 'fixed', value: mapping.value || '' }
      } else if (mapping.mode === 'attributes') {
        cleanedFields[key] = { mode: 'attributes', attribute_ids: mapping.attribute_ids || [] }
      } else {
        cleanedFields[key] = { mode: 'attribute', attribute_id: mapping.attribute_id || '' }
      }
    }

    await connectorsApi.updateConnection(connectionId.value, {
      settings: {
        website_profile_id: selectedProfileId.value,
        shopware_fields: cleanedFields,
      },
    })
    profileSaveSuccess.value = true
    setTimeout(() => { profileSaveSuccess.value = false }, 3000)
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Speichern fehlgeschlagen'
  } finally {
    savingProfile.value = false
  }
}

async function executeProfileSync() {
  profileSyncing.value = true
  profileCancelled.value = false
  profileSyncResult.value = null
  syncError.value = ''
  profileProgress.value = { current: 0, total: 0, success: 0, failed: 0, currentSku: '' }

  try {
    // 1. Produkt-IDs ermitteln
    const idsRes = await connectorsApi.syncProductIds(connectionId.value)
    const productIds = idsRes.data.data?.product_ids || idsRes.data.product_ids || []
    profileProgress.value.total = productIds.length

    if (productIds.length === 0) {
      profileSyncResult.value = { products: { success: 0, failed: 0 }, media: { success: 0, failed: 0 } }
      return
    }

    // 2. Produkte in 10er-Paketen synchronisieren
    const batchSize = 10
    for (let i = 0; i < productIds.length; i += batchSize) {
      if (profileCancelled.value) break

      const batch = productIds.slice(i, i + batchSize)
      profileProgress.value.current = Math.min(i + batchSize, productIds.length)
      profileProgress.value.currentSku = `Paket ${Math.floor(i / batchSize) + 1}`

      try {
        const res = await connectorsApi.syncProductBulk(connectionId.value, batch, {
          language: 'de',
        })
        const results = res.data.data || res.data || {}
        for (const [, r] of Object.entries(results)) {
          if (r.status === 'success') profileProgress.value.success++
          else profileProgress.value.failed++
        }
      } catch (e) {
        // Ganzer Batch fehlgeschlagen
        profileProgress.value.failed += batch.length
      }
    }

    profileSyncResult.value = {
      products: { success: profileProgress.value.success, failed: profileProgress.value.failed },
      media: { success: 0, failed: 0 },
      cancelled: profileCancelled.value,
    }
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Sync fehlgeschlagen'
  } finally {
    profileSyncing.value = false
    await loadConnection()
  }
}

function cancelProfileSync() {
  profileCancelled.value = true
}

async function executeHierarchySync() {
  hierarchySyncing.value = true
  hierarchySyncResult.value = null
  syncError.value = ''
  try {
    const res = await connectorsApi.syncHierarchy(connectionId.value)
    hierarchySyncResult.value = res.data.data || res.data
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Hierarchie-Sync fehlgeschlagen'
  } finally {
    hierarchySyncing.value = false
    await loadConnection()
  }
}

async function previewProduct() {
  if (!syncEntityId.value.trim()) return
  previewing.value = true
  previewData.value = null
  try {
    const res = await connectorsApi.previewProduct(connectionId.value, syncEntityId.value.trim(), syncLanguage.value)
    previewData.value = res.data.data || res.data
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Vorschau fehlgeschlagen'
  } finally {
    previewing.value = false
  }
}

async function executeSyncSingle() {
  if (!syncEntityId.value.trim()) return
  syncing.value = true
  syncError.value = ''
  try {
    if (syncType.value === 'media') {
      await connectorsApi.syncMedia(connectionId.value, syncEntityId.value.trim())
    } else {
      await connectorsApi.syncProduct(connectionId.value, syncEntityId.value.trim(), {
        language: syncLanguage.value,
      })
    }
    showSyncDialog.value = false
    syncEntityId.value = ''
    selectedProduct.value = null
  } catch (e) {
    syncError.value = e.response?.data?.message || e.message
  } finally {
    syncing.value = false
    await loadConnection()
  }
}

function toggleLogDetail(logId) {
  expandedLogId.value = expandedLogId.value === logId ? null : logId
}

async function clearSyncLogs() {
  if (!confirm('Alle Sync-Einträge dieser Verbindung löschen?')) return
  try {
    await connectorsApi.clearSyncLogs(connectionId.value)
    syncLogs.value = []
    expandedLogId.value = null
    await loadConnection()
  } catch (e) {
    console.error('clearSyncLogs error:', e)
    syncError.value = e.response?.data?.message || 'Löschen fehlgeschlagen: ' + (e.message || 'Unbekannter Fehler')
  }
}

async function executeDeltaSync() {
  deltaSyncing.value = true
  deltaSyncResult.value = null
  syncError.value = ''
  try {
    const res = await connectorsApi.deltaSync(connectionId.value)
    deltaSyncResult.value = res.data.data || res.data
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Delta-Sync fehlgeschlagen'
  } finally {
    deltaSyncing.value = false
    await loadConnection()
  }
}

async function deleteSingleSyncLog(logId) {
  try {
    await connectorsApi.deleteSyncLog(connectionId.value, logId)
    syncLogs.value = syncLogs.value.filter(l => l.id !== logId)
    if (expandedLogId.value === logId) expandedLogId.value = null
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Löschen fehlgeschlagen'
  }
}

async function exportSyncLogsExcel() {
  try {
    const res = await connectorsApi.exportSyncLogs(connectionId.value)
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `sync-logs-${connection.value?.name || 'export'}-${new Date().toISOString().slice(0, 10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    syncError.value = 'Excel-Export fehlgeschlagen'
  }
}

// Shopware Reset
const resetting = ref(false)
const resetResult = ref(null)

async function executeReset() {
  if (!confirm('⚠️ ACHTUNG: Alle vom PIM synchronisierten Produkte und Kategorien werden aus Shopware gelöscht!\n\nDieser Vorgang kann nicht rückgängig gemacht werden.\n\nFortfahren?')) return
  resetting.value = true
  resetResult.value = null
  syncError.value = ''
  try {
    const res = await connectorsApi.resetShop(connectionId.value)
    resetResult.value = res.data.data || res.data
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Reset fehlgeschlagen'
  } finally {
    resetting.value = false
    await loadConnection()
  }
}

// Alle Kategorien aus Shopware löschen
const purgingCategories = ref(false)
const purgeCategoriesResult = ref(null)

async function executePurgeCategories() {
  if (!confirm('Alle Kategorien aus Shopware löschen (außer Root)?\n\nDas betrifft ALLE Kategorien im Shop, nicht nur PIM-synchronisierte.')) return
  purgingCategories.value = true
  purgeCategoriesResult.value = null
  syncError.value = ''
  try {
    const res = await connectorsApi.purgeCategories(connectionId.value)
    purgeCategoriesResult.value = res.data.data || res.data
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Kategorie-Löschung fehlgeschlagen'
  } finally {
    purgingCategories.value = false
    await loadConnection()
  }
}

async function clearChecksums() {
  if (!confirm('Alle Checksums löschen? Der nächste Delta-Sync überträgt dann alle Produkte neu.')) return
  try {
    await connectorsApi.clearChecksums(connectionId.value)
    deltaSyncResult.value = null
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Löschen fehlgeschlagen'
  }
}

const statusColors = {
  success: 'badge-success',
  failed: 'badge-error',
  pending: 'badge-warning',
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="btn btn-ghost btn-sm" @click="router.push('/connectors')">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <h1 class="text-xl font-bold">{{ connection?.name || 'Verbindung' }}</h1>
      <span v-if="connection" class="badge badge-ghost">{{ connection.connector_type }}</span>
    </div>

    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <template v-if="connection && !loading">
      <!-- Connection Info -->
      <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <div class="text-sm text-base-content/50">Status</div>
              <div class="flex items-center gap-1 mt-1">
                <CheckCircle v-if="connection.is_active && !connection.token_expired" class="w-4 h-4 text-success" />
                <XCircle v-else class="w-4 h-4 text-error" />
                <span class="font-medium">
                  {{ connection.is_active && !connection.token_expired ? 'Aktiv' : 'Token abgelaufen' }}
                </span>
              </div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Verbunden von</div>
              <div class="font-medium mt-1">{{ connection.connected_by?.name || '–' }}</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Token gültig bis</div>
              <div class="font-medium mt-1">
                {{ connection.token_expires_at ? new Date(connection.token_expires_at).toLocaleString('de-DE') : '–' }}
              </div>
            </div>
            <div v-if="connection.sync_stats">
              <div class="text-sm text-base-content/50">Sync-Statistik</div>
              <div class="flex gap-2 mt-1">
                <span class="text-sm text-base-content/70">{{ connection.sync_stats.success }} OK</span>
                <span v-if="connection.sync_stats.failed" class="text-sm text-base-content/50">{{ connection.sync_stats.failed }} Fehler</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Export-Profil (nur Shopware) -->
      <div v-if="isShopware" class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <h2 class="card-title text-base">
              <Settings class="w-4 h-4" />
              Export-Profil
            </h2>
            <button class="btn btn-ghost btn-sm" @click="showProfileConfig = !showProfileConfig">
              {{ showProfileConfig ? 'Einklappen' : 'Konfigurieren' }}
            </button>
          </div>

          <!-- Profil-Vorschau -->
          <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
            <div class="flex-1 min-w-0">
              <span class="text-sm text-base-content/50">Vorschau-Profil:</span>
              <span class="font-medium ml-1">
                {{ selectedProfile?.name || 'Nicht konfiguriert' }}
                <span v-if="selectedProfile?.is_active" class="badge badge-ghost badge-xs ml-1">aktiv</span>
              </span>
            </div>
            <div v-if="selectedProfileId" class="flex flex-wrap gap-2">
              <button
                class="btn btn-ghost btn-sm gap-1"
                :disabled="hierarchySyncing"
                @click="executeHierarchySync"
              >
                <span v-if="hierarchySyncing" class="loading loading-spinner loading-xs"></span>
                Hierarchie
              </button>
              <button
                class="btn btn-outline btn-sm gap-1"
                :disabled="profileSyncing || deltaSyncing"
                @click="executeProfileSync"
              >
                <span v-if="profileSyncing" class="loading loading-spinner loading-xs"></span>
                <Play v-else class="w-3.5 h-3.5" />
                Komplett-Sync
              </button>
              <button
                class="btn btn-outline btn-sm gap-1"
                :disabled="deltaSyncing || profileSyncing"
                @click="executeDeltaSync"
                title="Nur neue und geänderte Produkte übertragen"
              >
                <span v-if="deltaSyncing" class="loading loading-spinner loading-xs"></span>
                <Zap v-else class="w-3.5 h-3.5" />
                Delta Sync
              </button>
              <button
                class="btn btn-outline btn-error btn-sm gap-1"
                :disabled="resetting || profileSyncing || deltaSyncing"
                @click="executeReset"
                title="Alle PIM-Produkte und Kategorien aus Shopware löschen"
              >
                <span v-if="resetting" class="loading loading-spinner loading-xs"></span>
                <Trash2 v-else class="w-3.5 h-3.5" />
                Shop Reset
              </button>
              <button
                class="btn btn-outline btn-error btn-sm gap-1"
                :disabled="purgingCategories || profileSyncing || deltaSyncing"
                @click="executePurgeCategories"
                title="Alle Kategorien aus Shopware löschen (außer Root)"
              >
                <span v-if="purgingCategories" class="loading loading-spinner loading-xs"></span>
                <Trash2 v-else class="w-3.5 h-3.5" />
                Kategorien löschen
              </button>
            </div>
          </div>

          <!-- Hierarchie-Sync Ergebnis -->
          <div v-if="hierarchySyncResult" class="alert mt-3" :class="hierarchySyncResult.errors ? 'alert-warning' : 'alert-success'">
            <div>
              <div class="font-semibold">Hierarchie: {{ hierarchySyncResult.hierarchy_name }}</div>
              <div class="text-sm">
                {{ hierarchySyncResult.synced }} von {{ hierarchySyncResult.total_nodes }} Kategorien synchronisiert
                <span v-if="hierarchySyncResult.errors" class="text-error">({{ hierarchySyncResult.errors }} Fehler)</span>
              </div>
              <div v-if="hierarchySyncResult.error_details?.length" class="mt-2 text-xs space-y-1">
                <div v-for="(err, idx) in hierarchySyncResult.error_details" :key="idx" class="text-error bg-error/5 p-1.5 rounded font-mono whitespace-pre-wrap">
                  {{ err }}
                </div>
              </div>
            </div>
          </div>

          <!-- Reset Ergebnis -->
          <div v-if="resetResult" class="alert mt-3" :class="(resetResult.products?.failed || resetResult.categories?.failed) ? 'alert-warning' : 'alert-success'">
            <div>
              <div class="font-semibold">Shop Reset abgeschlossen</div>
              <div class="text-sm">
                Produkte: {{ resetResult.products?.deleted || 0 }} gelöscht
                <span v-if="resetResult.products?.failed" class="text-error">({{ resetResult.products.failed }} fehlgeschlagen)</span>
                — Kategorien: {{ resetResult.categories?.deleted || 0 }} gelöscht
                <span v-if="resetResult.categories?.failed" class="text-error">({{ resetResult.categories.failed }} fehlgeschlagen)</span>
              </div>
            </div>
          </div>

          <!-- Purge Categories Ergebnis -->
          <div v-if="purgeCategoriesResult" class="alert mt-3" :class="purgeCategoriesResult.failed ? 'alert-warning' : 'alert-success'">
            <div>
              <div class="font-semibold">Kategorien gelöscht</div>
              <div class="text-sm">
                {{ purgeCategoriesResult.deleted }} von {{ purgeCategoriesResult.total }} Kategorien gelöscht
                <span v-if="purgeCategoriesResult.failed" class="text-error">({{ purgeCategoriesResult.failed }} fehlgeschlagen)</span>
              </div>
            </div>
          </div>

          <!-- Progress -->
          <div v-if="profileSyncing && profileProgress.total > 0" class="mt-3 p-3 bg-base-200/30 rounded-lg border border-base-200">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm font-medium">
                Produkt {{ profileProgress.current }} / {{ profileProgress.total }}
                <span class="text-base-content/50 ml-2">{{ profileProgress.currentSku }}</span>
              </div>
              <button class="btn btn-ghost btn-xs text-error" @click="cancelProfileSync">Abbrechen</button>
            </div>
            <progress
              class="progress progress-primary w-full"
              :value="profileProgress.current"
              :max="profileProgress.total"
            ></progress>
            <div class="flex gap-3 mt-1 text-xs text-base-content/50">
              <span class="text-success">{{ profileProgress.success }} OK</span>
              <span v-if="profileProgress.failed" class="text-error">{{ profileProgress.failed }} Fehler</span>
            </div>
          </div>

          <!-- Sync-Ergebnis -->
          <div v-if="profileSyncResult" class="alert mt-3" :class="profileSyncResult.cancelled ? 'alert-warning' : 'alert-success'">
            <div>
              <div class="font-semibold">{{ profileSyncResult.cancelled ? 'Sync abgebrochen' : 'Sync abgeschlossen' }}</div>
              <div class="text-sm mt-1">
                Produkte: {{ profileSyncResult.products?.success || 0 }} OK
                <span v-if="profileSyncResult.products?.failed" class="text-error">({{ profileSyncResult.products.failed }} Fehler)</span>
                &middot;
                Medien: {{ profileSyncResult.media?.success || 0 }} OK
                <span v-if="profileSyncResult.media?.failed" class="text-error">({{ profileSyncResult.media.failed }} Fehler)</span>
              </div>
            </div>
          </div>

          <!-- Delta-Sync Ergebnis -->
          <div v-if="deltaSyncResult" class="mt-3 space-y-3">
            <div class="p-4 bg-base-200/40 rounded-lg border border-base-200">
              <div class="w-full">
                <div class="font-semibold flex items-center gap-1">
                  <Zap class="w-4 h-4" />
                  Delta Sync abgeschlossen
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 text-sm">
                  <div>
                    <span class="text-base-content/50">Neu:</span>
                    <span class="font-semibold ml-1">{{ deltaSyncResult.products?.new || 0 }}</span>
                  </div>
                  <div>
                    <span class="text-base-content/50">Geändert:</span>
                    <span class="font-semibold ml-1">{{ deltaSyncResult.products?.updated || 0 }}</span>
                  </div>
                  <div>
                    <span class="text-base-content/50">Unverändert:</span>
                    <span class="font-semibold ml-1 text-success">{{ deltaSyncResult.products?.unchanged || 0 }}</span>
                  </div>
                  <div v-if="deltaSyncResult.products?.failed">
                    <span class="text-base-content/50">Fehler:</span>
                    <span class="font-semibold ml-1 text-error">{{ deltaSyncResult.products.failed }}</span>
                  </div>
                </div>
                <div v-if="deltaSyncResult.media" class="text-sm mt-1">
                  Medien: {{ deltaSyncResult.media.success || 0 }} OK
                  <span v-if="deltaSyncResult.media.failed" class="text-error">({{ deltaSyncResult.media.failed }} Fehler)</span>
                </div>
                <div class="flex gap-2 mt-2">
                  <button class="btn btn-ghost btn-xs" @click="deltaSyncResult = null">
                    <X class="w-3 h-3" /> Schließen
                  </button>
                  <button class="btn btn-ghost btn-xs text-warning" @click="clearChecksums" title="Checksums zurücksetzen — nächster Delta-Sync überträgt alles neu">
                    <RotateCcw class="w-3 h-3" /> Checksums zurücksetzen
                  </button>
                </div>
              </div>
            </div>

            <!-- Orphan-Warnung -->
            <div v-if="deltaSyncResult.orphans?.length" class="p-4 bg-base-200/40 rounded-lg border border-base-300">
              <div class="w-full">
                <div class="font-semibold flex items-center gap-1">
                  <AlertTriangle class="w-4 h-4" />
                  {{ deltaSyncResult.orphans.length }} Produkt(e) nur noch im Shop
                </div>
                <p class="text-xs mt-1 text-base-content/60">
                  Diese Produkte wurden synchronisiert, sind aber nicht mehr im PIM-Profil enthalten.
                  Bitte im Shopware-Backend prüfen und ggf. deaktivieren/löschen.
                </p>
                <div class="mt-2 overflow-x-auto">
                  <table class="table table-xs">
                    <thead>
                      <tr>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Shopware-ID</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="orphan in deltaSyncResult.orphans" :key="orphan.id">
                        <td class="font-mono text-xs">{{ orphan.sku }}</td>
                        <td class="text-sm">{{ orphan.name }}</td>
                        <td class="font-mono text-xs">{{ orphan.external_id?.substring(0, 12) }}...</td>
                        <td>
                          <span v-if="orphan.deleted" class="badge badge-error badge-xs">Gelöscht</span>
                          <span v-else class="badge badge-warning badge-xs">Nicht im Profil</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Konfiguration (aufklappbar) -->
          <div v-if="showProfileConfig" class="mt-4 space-y-5">
            <!-- Profil-Auswahl -->
            <div class="form-control">
              <label class="label"><span class="label-text font-medium">Vorschau-Profil</span></label>
              <p class="text-xs text-base-content/50 mb-2">
                Bestimmt Hierarchie, Sprache, Attribute, Preistyp und Produktfilter.
              </p>
              <select v-model="selectedProfileId" class="select select-bordered select-sm w-full max-w-md">
                <option :value="null">— Kein Profil —</option>
                <option v-for="p in profiles" :key="p.id" :value="p.id">
                  {{ p.name }} {{ p.is_active ? '(aktiv)' : '' }} — {{ p.locale?.toUpperCase() }}
                </option>
              </select>
            </div>

            <!-- Shopware-Felder -->
            <div>
              <label class="label"><span class="label-text font-medium">Shopware-Felder</span></label>
              <p class="text-xs text-base-content/50 mb-3">
                Standard nutzt den PIM-Wert, oder einen festen Wert bzw. ein Attribut zuweisen.
              </p>

              <div class="overflow-x-auto rounded-lg border border-base-200">
                <table class="table table-sm w-full">
                  <thead>
                    <tr class="bg-base-200/40">
                      <th class="w-44 font-medium text-xs">Shopware-Feld</th>
                      <th class="w-48 font-medium text-xs">Quelle</th>
                      <th class="font-medium text-xs">Wert</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="field in SHOPWARE_FIELD_DEFINITIONS"
                      :key="field.key"
                      class="hover:bg-base-200/20"
                    >
                      <td class="py-2.5">
                        <div class="font-medium text-sm">{{ field.label }}</div>
                        <div class="text-xs text-base-content/40 leading-tight">{{ field.description }}</div>
                      </td>
                      <td class="py-2.5">
                        <select
                          v-model="shopwareFields[field.key].mode"
                          class="select select-bordered select-xs w-full max-w-36"
                        >
                          <option v-if="field.defaultInfo" value="default">Standard</option>
                          <option value="fixed">Fester Wert</option>
                          <option value="attribute">Attribut</option>
                          <option v-if="field.modes?.includes('attributes')" value="attributes">Mehrere Attribute</option>
                        </select>
                      </td>
                      <td class="py-2.5">
                        <span
                          v-if="shopwareFields[field.key].mode === 'default'"
                          class="text-xs text-base-content/40 italic"
                        >{{ field.defaultInfo }}</span>

                        <input
                          v-else-if="shopwareFields[field.key].mode === 'fixed'"
                          v-model="shopwareFields[field.key].value"
                          type="text"
                          class="input input-bordered input-xs w-full"
                          :placeholder="field.defaultValue || 'Wert eingeben...'"
                        />

                        <!-- Mehrere Attribute (für Description) -->
                        <div v-else-if="shopwareFields[field.key].mode === 'attributes'" class="space-y-1">
                          <div v-for="(attrId, idx) in (shopwareFields[field.key].attribute_ids || [])" :key="idx" class="flex items-center gap-1">
                            <select
                              :value="attrId"
                              @change="shopwareFields[field.key].attribute_ids[idx] = $event.target.value"
                              class="select select-bordered select-xs flex-1"
                            >
                              <option value="">— Attribut wählen —</option>
                              <option v-for="attr in allAttributes" :key="attr.id" :value="attr.id">
                                {{ attr.name_de || attr.technical_name }}
                              </option>
                            </select>
                            <button class="btn btn-ghost btn-xs" @click="shopwareFields[field.key].attribute_ids.splice(idx, 1)">
                              <X class="w-3 h-3" />
                            </button>
                          </div>
                          <button
                            class="btn btn-ghost btn-xs text-primary"
                            @click="shopwareFields[field.key].attribute_ids = [...(shopwareFields[field.key].attribute_ids || []), '']"
                          >+ Attribut hinzufügen</button>
                        </div>

                        <select
                          v-else
                          v-model="shopwareFields[field.key].attribute_id"
                          class="select select-bordered select-xs w-full"
                        >
                          <option value="">— Attribut wählen —</option>
                          <option v-for="attr in allAttributes" :key="attr.id" :value="attr.id">
                            {{ attr.name_de || attr.technical_name }}
                          </option>
                        </select>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Properties (Selection-Attribute → Shopware Specifications) -->
            <div>
              <label class="label"><span class="label-text font-medium">Properties (Spezifikationen)</span></label>
              <p class="text-xs text-base-content/50 mb-2">
                Selection-Attribute werden automatisch als Shopware Property Groups angelegt.
              </p>

              <div v-if="!shopwareFields._property_attribute_ids?.length" class="text-xs p-2.5 bg-base-200/30 rounded-lg border border-base-200">
                <strong>Automatisch:</strong> Alle Selection-Attribute aus den Attribut-Sichten des Vorschau-Profils werden als Properties synchronisiert.
              </div>

              <!-- Manuelle Überschreibung -->
              <div v-if="shopwareFields._property_attribute_ids?.length" class="space-y-1.5 mt-2">
                <div class="text-xs text-base-content/50 font-medium">Manuelle Auswahl (überschreibt Automatik):</div>
                <div
                  v-for="(attrId, idx) in shopwareFields._property_attribute_ids"
                  :key="idx"
                  class="flex items-center gap-2"
                >
                  <select
                    :value="attrId"
                    @change="shopwareFields._property_attribute_ids[idx] = $event.target.value"
                    class="select select-bordered select-xs flex-1"
                  >
                    <option value="">— Attribut wählen —</option>
                    <option v-for="attr in allAttributes" :key="attr.id" :value="attr.id">
                      {{ attr.name_de || attr.technical_name }} {{ attr.data_type ? `(${attr.data_type})` : '' }}
                    </option>
                  </select>
                  <button class="btn btn-ghost btn-xs" @click="shopwareFields._property_attribute_ids.splice(idx, 1)">
                    <X class="w-3 h-3" />
                  </button>
                </div>
              </div>

              <button
                class="btn btn-ghost btn-xs text-primary mt-2"
                @click="shopwareFields._property_attribute_ids.push('')"
              >+ Manuell überschreiben</button>
            </div>

            <!-- Medien -->
            <div>
              <label class="label"><span class="label-text font-medium">Medien / Bilder</span></label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  class="toggle toggle-sm toggle-primary"
                  :checked="shopwareFields._sync_media?.enabled ?? true"
                  @change="shopwareFields._sync_media = { enabled: $event.target.checked }"
                />
                <span class="text-sm">Produktbilder an Shopware übertragen</span>
              </label>
              <p class="text-xs text-base-content/40 mt-1">
                Alle dem Produkt zugeordneten Medien werden hochgeladen und als Produktbilder zugewiesen.
              </p>
            </div>

            <!-- Sales Channel -->
            <div>
              <label class="label"><span class="label-text font-medium">Sales Channel</span></label>
              <p class="text-xs text-base-content/50 mb-2">
                Shopware-UUID des Sales Channels. Ohne diese werden Produkte nicht im Frontend angezeigt.
                Leer = automatisch der erste aktive Storefront.
              </p>
              <input
                v-model="shopwareFields._sales_channel_id.value"
                type="text"
                class="input input-bordered input-sm w-full max-w-md"
                placeholder="Leer = automatisch erster aktiver Storefront"
              />
            </div>

            <!-- Speichern -->
            <div class="flex items-center gap-3 pt-2">
              <button class="btn btn-outline btn-sm gap-1" :disabled="savingProfile" @click="saveExportProfile">
                <span v-if="savingProfile" class="loading loading-spinner loading-xs"></span>
                <Save v-else class="w-4 h-4" />
                Export-Profil speichern
              </button>
              <span v-if="profileSaveSuccess" class="text-success text-sm flex items-center gap-1">
                <CheckCircle class="w-4 h-4" /> Gespeichert
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Einzel-Sync Aktionen -->
      <div class="flex flex-wrap gap-2 items-center">
        <button class="btn btn-ghost btn-sm" @click="showSyncDialog = true; syncType = 'product'; clearSelectedProduct()">
          <Package class="w-4 h-4" />
          Einzelnes Produkt
        </button>
        <button class="btn btn-ghost btn-sm" @click="showSyncDialog = true; syncType = 'media'; clearSelectedProduct()">
          <Image class="w-4 h-4" />
          Einzelnes Media
        </button>
        <button class="btn btn-ghost btn-sm sm:ml-auto" @click="loadConnection">
          <RefreshCw class="w-4 h-4" />
          Aktualisieren
        </button>
      </div>

      <!-- Sync Error -->
      <div v-if="syncError" class="alert alert-error alert-sm">
        <div>
          <span>{{ syncError }}</span>
          <button class="btn btn-ghost btn-xs ml-2" @click="syncError = ''"><X class="w-3 h-3" /></button>
        </div>
      </div>

      <!-- Sync Dialog -->
      <div v-if="showSyncDialog" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">
            {{ syncType === 'media' ? 'Media-Asset' : 'Produkt' }} synchronisieren
          </h3>

          <!-- Produkt-Suche -->
          <div v-if="syncType === 'product'" class="space-y-2">
            <div v-if="selectedProduct" class="flex items-center gap-2 p-2 bg-base-200/50 rounded-lg">
              <Package class="w-4 h-4 text-primary shrink-0" />
              <div class="flex-1 min-w-0">
                <span class="font-mono text-xs text-base-content/50">{{ selectedProduct.sku }}</span>
                <span class="ml-2 text-sm font-medium">{{ selectedProduct.name }}</span>
              </div>
              <button class="btn btn-ghost btn-xs" @click="clearSelectedProduct">
                <X class="w-3 h-3" />
              </button>
            </div>
            <div v-else class="relative">
              <div class="relative">
                <Search class="absolute left-2.5 top-2 w-4 h-4 text-base-content/30" />
                <input
                  v-model="productSearch"
                  type="text"
                  class="input input-bordered input-sm w-full pl-8"
                  placeholder="SKU oder Name suchen..."
                  @input="onProductSearchInput"
                />
                <span v-if="productSearching" class="loading loading-spinner loading-xs absolute right-2.5 top-2"></span>
              </div>
              <!-- Suchergebnisse -->
              <div v-if="productSearchResults.length > 0" class="absolute z-10 mt-1 w-full bg-base-100 border border-base-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                <button
                  v-for="p in productSearchResults" :key="p.id"
                  class="flex items-center gap-2 w-full px-3 py-2 text-left hover:bg-base-200/50 transition-colors text-sm"
                  @click="selectProduct(p)"
                >
                  <span class="font-mono text-xs text-base-content/50 w-28 shrink-0 truncate">{{ p.sku }}</span>
                  <span class="truncate">{{ p.name }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Media-ID Eingabe -->
          <div v-if="syncType === 'media'" class="form-control">
            <label class="label"><span class="label-text">Media-ID (UUID)</span></label>
            <input v-model="syncEntityId" type="text" class="input input-bordered input-sm" placeholder="UUID eingeben..." />
          </div>

          <div class="flex gap-3 items-center mt-2">
            <div v-if="syncType === 'product'" class="form-control w-24">
              <select v-model="syncLanguage" class="select select-bordered select-sm">
                <option value="de">DE</option>
                <option value="en">EN</option>
              </select>
            </div>
            <div class="flex-1"></div>
            <button class="btn btn-ghost btn-sm" @click="showSyncDialog = false">Abbrechen</button>
            <button
              v-if="syncType === 'product' && isShopware"
              class="btn btn-outline btn-sm"
              :disabled="previewing || !syncEntityId.trim()"
              @click="previewProduct"
            >
              <span v-if="previewing" class="loading loading-spinner loading-xs"></span>
              Vorschau
            </button>
            <button
              class="btn btn-primary btn-sm"
              :disabled="syncing || !syncEntityId.trim()"
              @click="executeSyncSingle"
            >
              <span v-if="syncing" class="loading loading-spinner loading-xs"></span>
              Synchronisieren
            </button>
          </div>

          <!-- Preview-Anzeige -->
          <div v-if="previewData" class="mt-4 space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-semibold">Vorschau: Shopware-Payload</h4>
              <button class="btn btn-ghost btn-xs" @click="previewData = null"><X class="w-3 h-3" /></button>
            </div>

            <!-- Felder als Tabelle -->
            <div class="overflow-x-auto rounded-lg border border-base-200">
              <table class="table table-xs w-full">
                <thead>
                  <tr class="bg-base-200/40">
                    <th class="w-48 font-medium">Shopware-Feld</th>
                    <th class="font-medium">Wert</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(value, key) in previewData.product_payload" :key="key" class="hover:bg-base-200/20">
                    <td class="font-mono text-xs font-medium">{{ key }}</td>
                    <td class="text-sm">
                      <template v-if="key === 'price' && Array.isArray(value)">
                        <span v-for="(p, i) in value" :key="i">
                          {{ p.gross }} brutto / {{ p.net }} netto ({{ p.currencyId?.substring(0, 8) }}...)
                        </span>
                      </template>
                      <template v-else-if="key === 'description'">
                        <div class="max-h-20 overflow-y-auto text-xs" v-html="value"></div>
                      </template>
                      <template v-else-if="key === 'customFields' && typeof value === 'object'">
                        <div class="text-xs space-y-0.5">
                          <div v-for="(v, k) in value" :key="k">
                            <span class="font-mono text-base-content/50">{{ k }}:</span> {{ v }}
                          </div>
                        </div>
                      </template>
                      <template v-else-if="typeof value === 'object'">
                        <pre class="text-xs whitespace-pre-wrap">{{ JSON.stringify(value, null, 2) }}</pre>
                      </template>
                      <template v-else>{{ value }}</template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Properties -->
            <div v-if="previewData.properties?.length" class="rounded-lg border border-base-200 p-3">
              <div class="text-xs font-semibold mb-2">Properties (Spezifikationen)</div>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="(prop, idx) in previewData.properties" :key="idx"
                  class="badge badge-outline badge-sm"
                >
                  {{ prop.group }}: {{ prop.option }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sync-Protokoll -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Sync-Protokoll</h2>
          <div class="flex gap-1">
            <button
              v-if="syncLogs.length > 0"
              class="btn btn-ghost btn-xs gap-1"
              @click="exportSyncLogsExcel"
              title="Sync-Logs als Excel exportieren"
            >
              <Download class="w-3 h-3" /> Excel
            </button>
            <button
              v-if="syncLogs.length > 0"
              class="btn btn-ghost btn-xs text-base-content/50"
              @click="clearSyncLogs"
            >Protokoll löschen</button>
          </div>
        </div>
        <div v-if="syncLogs.length === 0" class="text-center py-6 text-base-content/40">
          Noch keine Sync-Einträge.
        </div>
        <div class="overflow-x-auto">
          <table v-if="syncLogs.length > 0" class="table table-sm">
            <thead>
              <tr>
                <th>Zeitpunkt</th>
                <th>Aktion</th>
                <th>Produkt</th>
                <th>Externe ID</th>
                <th>Status</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="log in syncLogs" :key="log.id">
                <tr
                  class="cursor-pointer hover:bg-base-200/30"
                  :class="{ 'bg-error/5': log.status === 'failed' }"
                  @click="toggleLogDetail(log.id)"
                >
                  <td class="text-xs whitespace-nowrap">{{ new Date(log.created_at).toLocaleString('de-DE') }}</td>
                  <td><span class="badge badge-ghost badge-xs">{{ log.action }}</span></td>
                  <td>
                    <div class="text-sm">
                      <span v-if="log.meta?.sku" class="font-mono text-xs">{{ log.meta.sku }}</span>
                      <span v-else class="font-mono text-xs text-base-content/40">{{ log.entity_id?.substring(0, 8) }}...</span>
                      <span v-if="log.meta?.product_name" class="ml-1 text-base-content/60">{{ log.meta.product_name }}</span>
                    </div>
                  </td>
                  <td class="font-mono text-xs">{{ log.external_id || '–' }}</td>
                  <td>
                    <span :class="statusColors[log.status]" class="badge badge-xs">{{ log.status }}</span>
                  </td>
                  <td class="text-xs">
                    <div v-if="log.error_message && expandedLogId !== log.id" class="text-error truncate max-w-xs">
                      {{ log.error_message }}
                    </div>
                    <span v-else-if="!log.error_message" class="text-base-content/30">–</span>
                    <ChevronUp v-if="expandedLogId === log.id" class="w-3 h-3 inline" />
                    <ChevronDown v-else-if="log.error_message" class="w-3 h-3 inline text-base-content/30" />
                  </td>
                </tr>
                <!-- Erweitertes Detail -->
                <tr v-if="expandedLogId === log.id">
                  <td colspan="6" class="bg-base-200/20 px-4 py-3">
                    <div v-if="log.error_message" class="mb-2">
                      <div class="text-xs font-semibold text-error mb-1">Fehlermeldung:</div>
                      <div class="text-sm text-error bg-error/5 p-2 rounded font-mono whitespace-pre-wrap break-all">{{ log.error_message }}</div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                      <div>
                        <span class="text-base-content/50">Entity-ID:</span>
                        <div class="font-mono break-all">{{ log.entity_id }}</div>
                      </div>
                      <div>
                        <span class="text-base-content/50">Entity-Typ:</span>
                        <div>{{ log.entity_type }}</div>
                      </div>
                      <div v-if="log.meta?.language">
                        <span class="text-base-content/50">Sprache:</span>
                        <div>{{ log.meta.language }}</div>
                      </div>
                      <div v-if="log.meta?.http_status">
                        <span class="text-base-content/50">HTTP-Status:</span>
                        <div>{{ log.meta.http_status }}</div>
                      </div>
                      <div v-if="log.meta?.synced_fields">
                        <span class="text-base-content/50">Synchronisierte Felder:</span>
                        <div>{{ Array.isArray(log.meta.synced_fields) ? log.meta.synced_fields.join(', ') : log.meta.synced_fields }}</div>
                      </div>
                      <div v-if="log.meta?.delta_status">
                        <span class="text-base-content/50">Delta-Status:</span>
                        <div>{{ log.meta.delta_status === 'new' ? 'Neu' : 'Geändert' }}</div>
                      </div>
                    </div>
                    <div class="mt-2 flex justify-end">
                      <button
                        class="btn btn-ghost btn-xs text-error gap-1"
                        @click.stop="deleteSingleSyncLog(log.id)"
                      >
                        <Trash2 class="w-3 h-3" /> Eintrag löschen
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
