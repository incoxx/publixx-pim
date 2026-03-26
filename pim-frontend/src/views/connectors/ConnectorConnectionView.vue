<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, RefreshCw, Image, Package, CheckCircle, XCircle, AlertCircle, Clock,
  Play, Settings, Save,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

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
const syncType = ref('media') // 'media' | 'product'
const syncEntityId = ref('')
const syncLanguage = ref('de')

// Export-Profil Konfiguration
const showProfileConfig = ref(false)
const profiles = ref([])
const attributes = ref([])
const selectedProfileId = ref(null)
const shopwareFields = ref({})
const savingProfile = ref(false)
const profileSaveSuccess = ref(false)
const profileSyncing = ref(false)
const profileSyncResult = ref(null)

// Standard Shopware-Pflichtfelder
const SHOPWARE_FIELD_DEFINITIONS = [
  { key: 'name', label: 'Produktname', description: 'Standard: product.name', defaultMode: 'default', defaultInfo: 'Produktname aus PIM' },
  { key: 'tax_id', label: 'Steuer-ID (taxId)', description: 'Leer = Standard-Steuer aus Shopware', defaultMode: 'default', defaultInfo: 'Erste Steuer aus Shopware' },
  { key: 'manufacturer_id', label: 'Hersteller-ID (manufacturerId)', description: 'Shopware Manufacturer-UUID', defaultMode: 'fixed' },
  { key: 'currency_id', label: 'Währung (currencyId)', description: 'Standard: EUR', defaultMode: 'fixed', defaultValue: 'b7d2554b0ce847cd82f3ac9bd1c0dfca' },
  { key: 'ean', label: 'EAN', description: 'Standard: product.ean', defaultMode: 'default', defaultInfo: 'EAN aus PIM-Stammdaten' },
  { key: 'weight', label: 'Gewicht', description: 'Produktgewicht in kg', defaultMode: 'attribute' },
  { key: 'meta_title', label: 'SEO-Titel', description: 'Meta-Title für Shopware', defaultMode: 'attribute' },
  { key: 'meta_description', label: 'SEO-Beschreibung', description: 'Meta-Description für Shopware', defaultMode: 'attribute' },
]

const connectionId = computed(() => route.params.id)
const isShopware = computed(() => connection.value?.connector_type === 'shopware')
const selectedProfile = computed(() => profiles.value.find(p => p.id === selectedProfileId.value))

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

    // Shopware-spezifisch: Profil-Einstellungen laden
    if (isShopware.value) {
      selectedProfileId.value = connection.value.settings?.website_profile_id || null
      shopwareFields.value = connection.value.settings?.shopware_fields || {}

      // Defaults für nicht konfigurierte Felder setzen
      for (const field of SHOPWARE_FIELD_DEFINITIONS) {
        if (!shopwareFields.value[field.key]) {
          shopwareFields.value[field.key] = {
            mode: field.defaultMode,
            value: field.defaultValue || '',
            attribute_id: '',
          }
        }
      }

      await loadProfiles()
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
  } catch (e) {
    // Ignore — profiles optional
  }
}

async function saveExportProfile() {
  savingProfile.value = true
  profileSaveSuccess.value = false
  try {
    // shopware_fields bereinigen: nur relevante Daten pro Modus speichern
    const cleanedFields = {}
    for (const [key, mapping] of Object.entries(shopwareFields.value)) {
      if (mapping.mode === 'default') {
        cleanedFields[key] = { mode: 'default' }
      } else if (mapping.mode === 'fixed') {
        cleanedFields[key] = { mode: 'fixed', value: mapping.value || '' }
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
  profileSyncResult.value = null
  syncError.value = ''
  try {
    const res = await connectorsApi.syncFromProfile(connectionId.value)
    profileSyncResult.value = res.data.data || res.data
    await loadConnection()
  } catch (e) {
    syncError.value = e.response?.data?.message || 'Sync fehlgeschlagen'
  } finally {
    profileSyncing.value = false
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
    await loadConnection()
  } catch (e) {
    syncError.value = e.response?.data?.message || e.message
  } finally {
    syncing.value = false
  }
}

const statusColors = {
  success: 'badge-success',
  failed: 'badge-error',
  pending: 'badge-warning',
}

const statusIcons = {
  success: CheckCircle,
  failed: XCircle,
  pending: Clock,
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

    <!-- Error -->
    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <!-- Loading -->
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
                <span class="badge badge-success badge-sm">{{ connection.sync_stats.success }} OK</span>
                <span class="badge badge-error badge-sm">{{ connection.sync_stats.failed }} Fehler</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Export-Profil Konfiguration (nur Shopware) -->
      <div v-if="isShopware" class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body">
          <div class="flex items-center justify-between">
            <h2 class="card-title text-base">
              <Settings class="w-4 h-4" />
              Export-Profil
            </h2>
            <button
              class="btn btn-ghost btn-sm"
              @click="showProfileConfig = !showProfileConfig"
            >
              {{ showProfileConfig ? 'Einklappen' : 'Konfigurieren' }}
            </button>
          </div>

          <!-- Profil-Vorschau (immer sichtbar) -->
          <div class="flex items-center gap-4 mt-2">
            <div class="flex-1">
              <span class="text-sm text-base-content/50">Vorschau-Profil:</span>
              <span class="font-medium ml-1">
                {{ selectedProfile?.name || 'Nicht konfiguriert' }}
                <span v-if="selectedProfile?.is_active" class="badge badge-success badge-xs ml-1">aktiv</span>
              </span>
            </div>
            <button
              v-if="selectedProfileId"
              class="btn btn-primary btn-sm gap-1"
              :disabled="profileSyncing"
              @click="executeProfileSync"
            >
              <span v-if="profileSyncing" class="loading loading-spinner loading-xs"></span>
              <Play v-else class="w-4 h-4" />
              Sync starten
            </button>
          </div>

          <!-- Sync-Ergebnis -->
          <div v-if="profileSyncResult" class="alert alert-success mt-3">
            <div>
              <div class="font-semibold">Sync abgeschlossen</div>
              <div class="text-sm mt-1">
                Kategorien: {{ profileSyncResult.categories?.synced || 0 }} synchronisiert
                <span v-if="profileSyncResult.categories?.errors" class="text-error">({{ profileSyncResult.categories.errors }} Fehler)</span>
                &middot;
                Produkte: {{ profileSyncResult.products?.success || 0 }} OK
                <span v-if="profileSyncResult.products?.failed" class="text-error">({{ profileSyncResult.products.failed }} Fehler)</span>
                &middot;
                Medien: {{ profileSyncResult.media?.success || 0 }} OK
                <span v-if="profileSyncResult.media?.failed" class="text-error">({{ profileSyncResult.media.failed }} Fehler)</span>
              </div>
            </div>
          </div>

          <!-- Konfiguration (aufklappbar) -->
          <div v-if="showProfileConfig" class="mt-4 space-y-4">
            <!-- Profil-Auswahl -->
            <div class="form-control">
              <label class="label"><span class="label-text font-medium">Vorschau-Profil auswählen</span></label>
              <p class="text-xs text-base-content/50 mb-2">
                Das Profil bestimmt Hierarchie, Sprache, Attribute, Preistyp und Produktfilter für den Export.
              </p>
              <select v-model="selectedProfileId" class="select select-bordered select-sm w-full max-w-md">
                <option :value="null">— Kein Profil —</option>
                <option v-for="p in profiles" :key="p.id" :value="p.id">
                  {{ p.name }} {{ p.is_active ? '(aktiv)' : '' }} — {{ p.locale?.toUpperCase() }}
                </option>
              </select>
            </div>

            <!-- Shopware-Pflichtfelder -->
            <div>
              <label class="label"><span class="label-text font-medium">Shopware-Felder</span></label>
              <p class="text-xs text-base-content/50 mb-3">
                Felder für Shopware — Standard nutzt den PIM-Wert, oder einen festen Wert bzw. ein Attribut zuweisen.
              </p>

              <div class="overflow-x-auto">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th class="w-48">Feld</th>
                      <th class="w-40">Modus</th>
                      <th>Wert / Attribut</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="field in SHOPWARE_FIELD_DEFINITIONS" :key="field.key">
                      <td>
                        <div class="font-medium text-sm">{{ field.label }}</div>
                        <div class="text-xs text-base-content/40">{{ field.description }}</div>
                      </td>
                      <td>
                        <div class="flex gap-2 flex-wrap">
                          <label v-if="field.defaultInfo" class="label cursor-pointer gap-1 p-0">
                            <input
                              type="radio"
                              :name="'mode-' + field.key"
                              value="default"
                              v-model="shopwareFields[field.key].mode"
                              class="radio radio-xs"
                            />
                            <span class="label-text text-xs">Standard</span>
                          </label>
                          <label class="label cursor-pointer gap-1 p-0">
                            <input
                              type="radio"
                              :name="'mode-' + field.key"
                              value="fixed"
                              v-model="shopwareFields[field.key].mode"
                              class="radio radio-xs"
                            />
                            <span class="label-text text-xs">Fix</span>
                          </label>
                          <label class="label cursor-pointer gap-1 p-0">
                            <input
                              type="radio"
                              :name="'mode-' + field.key"
                              value="attribute"
                              v-model="shopwareFields[field.key].mode"
                              class="radio radio-xs"
                            />
                            <span class="label-text text-xs">Attribut</span>
                          </label>
                        </div>
                      </td>
                      <td>
                        <span
                          v-if="shopwareFields[field.key].mode === 'default'"
                          class="text-sm text-base-content/40 italic"
                        >
                          {{ field.defaultInfo }}
                        </span>
                        <input
                          v-else-if="shopwareFields[field.key].mode === 'fixed'"
                          v-model="shopwareFields[field.key].value"
                          type="text"
                          class="input input-bordered input-sm w-full"
                          :placeholder="field.defaultValue || 'Wert eingeben...'"
                        />
                        <input
                          v-else
                          v-model="shopwareFields[field.key].attribute_id"
                          type="text"
                          class="input input-bordered input-sm w-full"
                          placeholder="Attribut-UUID eingeben..."
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Speichern -->
            <div class="flex items-center gap-3">
              <button
                class="btn btn-primary btn-sm gap-1"
                :disabled="savingProfile"
                @click="saveExportProfile"
              >
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

      <!-- Sync Actions -->
      <div class="flex gap-2">
        <button class="btn btn-ghost btn-sm" @click="showSyncDialog = true; syncType = 'media'">
          <Image class="w-4 h-4" />
          Einzelnes Media
        </button>
        <button class="btn btn-ghost btn-sm" @click="showSyncDialog = true; syncType = 'product'">
          <Package class="w-4 h-4" />
          Einzelnes Produkt
        </button>
        <button class="btn btn-ghost btn-sm ml-auto" @click="loadConnection">
          <RefreshCw class="w-4 h-4" />
          Aktualisieren
        </button>
      </div>

      <!-- Sync Error -->
      <div v-if="syncError" class="alert alert-error">{{ syncError }}</div>

      <!-- Sync Dialog -->
      <div v-if="showSyncDialog" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">
            {{ syncType === 'media' ? 'Media-Asset' : 'Produkt' }} synchronisieren
          </h3>
          <div class="flex gap-3 items-end">
            <div class="form-control flex-1">
              <label class="label"><span class="label-text">{{ syncType === 'media' ? 'Media-ID' : 'Produkt-ID' }} (UUID)</span></label>
              <input
                v-model="syncEntityId"
                type="text"
                class="input input-bordered input-sm"
                placeholder="UUID eingeben..."
              />
            </div>
            <div v-if="syncType === 'product'" class="form-control w-24">
              <label class="label"><span class="label-text">Sprache</span></label>
              <select v-model="syncLanguage" class="select select-bordered select-sm">
                <option value="de">DE</option>
                <option value="en">EN</option>
              </select>
            </div>
            <button
              class="btn btn-primary btn-sm"
              :disabled="syncing || !syncEntityId.trim()"
              @click="executeSyncSingle"
            >
              <span v-if="syncing" class="loading loading-spinner loading-xs"></span>
              Synchronisieren
            </button>
            <button class="btn btn-ghost btn-sm" @click="showSyncDialog = false">Abbrechen</button>
          </div>
        </div>
      </div>

      <!-- Sync Logs -->
      <div class="space-y-2">
        <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Sync-Protokoll</h2>
        <div v-if="syncLogs.length === 0" class="text-center py-6 text-base-content/40">
          Noch keine Sync-Einträge.
        </div>
        <div class="overflow-x-auto">
          <table v-if="syncLogs.length > 0" class="table table-sm">
            <thead>
              <tr>
                <th>Zeitpunkt</th>
                <th>Aktion</th>
                <th>Typ</th>
                <th>Entity-ID</th>
                <th>Externe ID</th>
                <th>Status</th>
                <th>Fehler</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in syncLogs" :key="log.id">
                <td class="text-xs">{{ new Date(log.created_at).toLocaleString('de-DE') }}</td>
                <td><span class="badge badge-ghost badge-xs">{{ log.action }}</span></td>
                <td>{{ log.entity_type }}</td>
                <td class="font-mono text-xs">{{ log.entity_id?.substring(0, 8) }}...</td>
                <td class="font-mono text-xs">{{ log.external_id || '–' }}</td>
                <td>
                  <span :class="statusColors[log.status]" class="badge badge-xs">
                    {{ log.status }}
                  </span>
                </td>
                <td class="text-xs text-error max-w-xs truncate">{{ log.error_message || '–' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
