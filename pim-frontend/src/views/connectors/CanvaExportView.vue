<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Send, Search, Package, Tag, DollarSign, Image, Globe,
  Play, Loader2, CheckCircle, XCircle, ArrowLeft, ChevronDown,
} from 'lucide-vue-next'
import canvaExportProfilesApi from '@/api/canvaExportProfiles'
import connectorsApi from '@/api/connectors'
import productsApi from '@/api/products'
import attributesApi from '@/api/attributes'
import searchApi from '@/api/search'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'

const router = useRouter()

// --- State ---
const activeTab = ref('products') // 'products' | 'attributes' | 'options'
const loading = ref(false)
const error = ref('')
const executing = ref(false)
const executeResults = ref(null)

// Canva-Verbindungen
const canvaConnections = ref([])
const selectedConnectionId = ref(null)

// Profile
const profiles = ref([])
const selectedProfileId = ref(null)

// Produkte
const productSearch = ref('')
const productSearchResults = ref([])
const productSearchLoading = ref(false)
const selectedProducts = ref([]) // [{id, sku, name}]

// Attribute
const allAttributes = ref([])
const selectedAttributeIds = ref([])
const attrSearchTerm = ref('')
const showAttrDropdown = ref(false)

// Sprachen
const selectedLanguages = ref(['de'])
const availableLanguages = [
  { code: 'de', label: 'Deutsch' },
  { code: 'en', label: 'Englisch' },
  { code: 'fr', label: 'Französisch' },
  { code: 'it', label: 'Italienisch' },
  { code: 'es', label: 'Spanisch' },
]

// Optionen
const includePrices = ref(false)
const includeMedia = ref(true)
const brandTemplateId = ref('')

// --- Computed ---
const tabs = [
  { key: 'products', label: 'Produkte' },
  { key: 'attributes', label: 'Attribute & Sprache' },
  { key: 'options', label: 'Optionen & Senden' },
]

const selectedConnection = computed(() =>
  canvaConnections.value.find(c => c.id === selectedConnectionId.value)
)

const filteredAttributes = computed(() => {
  const attrs = allAttributes.value.filter(a => !a.is_internal)
  if (!attrSearchTerm.value) return attrs
  const term = attrSearchTerm.value.toLowerCase()
  return attrs.filter(a =>
    (a.name_de || '').toLowerCase().includes(term) ||
    (a.technical_name || '').toLowerCase().includes(term)
  )
})

const canExecute = computed(() =>
  selectedConnectionId.value && selectedProducts.value.length > 0
)

// --- Load ---
onMounted(async () => {
  loading.value = true
  try {
    const [connRes, profilesRes, attrsRes] = await Promise.all([
      connectorsApi.connections(),
      canvaExportProfilesApi.list(),
      attributesApi.list({ per_page: 9999 }),
    ])

    const allConns = connRes.data.data || connRes.data
    canvaConnections.value = allConns.filter(c => c.connector_type === 'canva' && c.is_active && !c.token_expired)
    if (canvaConnections.value.length === 1) {
      selectedConnectionId.value = canvaConnections.value[0].id
    }

    profiles.value = profilesRes.data.data || profilesRes.data
    allAttributes.value = (attrsRes.data.data || attrsRes.data)
  } catch (e) {
    error.value = 'Fehler beim Laden der Daten'
  } finally {
    loading.value = false
  }
})

// --- Produkt-Suche ---
let searchTimeout = null
function onProductSearch() {
  clearTimeout(searchTimeout)
  if (!productSearch.value || productSearch.value.length < 2) {
    productSearchResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    productSearchLoading.value = true
    try {
      const { data } = await searchApi.search({
        search: productSearch.value,
        per_page: 20,
      })
      const items = data.data || []
      productSearchResults.value = items.filter(
        p => !selectedProducts.value.some(sp => sp.id === p.id)
      )
    } catch (e) { /* ignore */ }
    finally { productSearchLoading.value = false }
  }, 300)
}

function addProduct(product) {
  selectedProducts.value.push({
    id: product.id,
    sku: product.sku,
    name: product.name,
  })
  productSearchResults.value = productSearchResults.value.filter(p => p.id !== product.id)
}

function removeProduct(id) {
  selectedProducts.value = selectedProducts.value.filter(p => p.id !== id)
}

// --- Attribut-Auswahl ---
function toggleAttribute(attrId) {
  const idx = selectedAttributeIds.value.indexOf(attrId)
  if (idx >= 0) selectedAttributeIds.value.splice(idx, 1)
  else selectedAttributeIds.value.push(attrId)
}

function isAttrSelected(attrId) {
  return selectedAttributeIds.value.includes(attrId)
}

function selectAllAttributes() {
  selectedAttributeIds.value = allAttributes.value.filter(a => !a.is_internal).map(a => a.id)
}

function deselectAllAttributes() {
  selectedAttributeIds.value = []
}

// --- Sprache ---
function toggleLanguage(code) {
  const idx = selectedLanguages.value.indexOf(code)
  if (idx >= 0 && selectedLanguages.value.length > 1) {
    selectedLanguages.value.splice(idx, 1)
  } else if (idx < 0) {
    selectedLanguages.value.push(code)
  }
}

// --- Profil CRUD ---
function loadProfile(id) {
  const profile = profiles.value.find(p => p.id === id)
  if (!profile) return
  selectedConnectionId.value = profile.connector_connection_id || null
  selectedLanguages.value = profile.languages || ['de']
  selectedAttributeIds.value = profile.attribute_ids || []
  includePrices.value = profile.include_prices ?? false
  includeMedia.value = profile.include_media ?? true
  brandTemplateId.value = profile.brand_template_id || ''
  // Produkte aus Profil laden
  if (profile.product_ids?.length) {
    loadProductsByIds(profile.product_ids)
  }
}

async function loadProductsByIds(ids) {
  try {
    const { data } = await productsApi.list({ ids: ids.join(','), per_page: 50 })
    const items = data.data || []
    selectedProducts.value = items.map(p => ({ id: p.id, sku: p.sku, name: p.name }))
  } catch (e) { /* ignore */ }
}

function gatherProfileData() {
  return {
    connector_connection_id: selectedConnectionId.value,
    product_ids: selectedProducts.value.map(p => p.id),
    attribute_ids: selectedAttributeIds.value,
    languages: selectedLanguages.value,
    include_prices: includePrices.value,
    include_media: includeMedia.value,
    brand_template_id: brandTemplateId.value || null,
  }
}

async function saveProfile({ name, is_shared }) {
  try {
    await canvaExportProfilesApi.create({ name, is_shared, ...gatherProfileData() })
    const { data } = await canvaExportProfilesApi.list()
    profiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht gespeichert werden'
  }
}

async function updateProfile({ id, name, is_shared }) {
  try {
    await canvaExportProfilesApi.update(id, { name, is_shared, ...gatherProfileData() })
    const { data } = await canvaExportProfilesApi.list()
    profiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht aktualisiert werden'
  }
}

async function deleteProfile(id) {
  try {
    await canvaExportProfilesApi.remove(id)
    if (selectedProfileId.value === id) selectedProfileId.value = null
    const { data } = await canvaExportProfilesApi.list()
    profiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht gelöscht werden'
  }
}

// --- Export ausführen ---
async function executeExport() {
  if (!canExecute.value) return
  executing.value = true
  executeResults.value = null
  error.value = ''
  try {
    // Profil speichern/nutzen oder direkt senden
    if (selectedProfileId.value) {
      // Vorhandenes Profil ausführen
      const { data } = await canvaExportProfilesApi.execute(selectedProfileId.value)
      executeResults.value = data.data || data
    } else {
      // Direkt über Bulk-API senden
      const productIds = selectedProducts.value.map(p => p.id)
      const options = {
        language: selectedLanguages.value[0],
        attributes: selectedAttributeIds.value.length
          ? allAttributes.value
              .filter(a => selectedAttributeIds.value.includes(a.id))
              .map(a => a.code || a.technical_name)
          : undefined,
        include_prices: includePrices.value,
        include_media: includeMedia.value,
        brand_template_id: brandTemplateId.value || undefined,
      }
      const { data } = await connectorsApi.syncProductBulk(
        selectedConnectionId.value,
        productIds,
        options,
      )
      executeResults.value = data.data || data
    }
  } catch (e) {
    error.value = `Export fehlgeschlagen: ${e.response?.data?.message || e.message}`
  } finally {
    executing.value = false
  }
}
</script>

<template>
  <div class="space-y-5 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button class="pim-btn pim-btn-ghost px-2 py-1.5" @click="router.push('/connectors/canva')">
          <ArrowLeft class="w-4 h-4" :stroke-width="1.75" />
        </button>
        <Send class="w-5 h-5 text-purple-500" :stroke-width="1.75" />
        <div>
          <h1 class="text-base font-bold text-[var(--color-text-primary)]">Canva Export</h1>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">Produkte an Canva senden und Designs erstellen</p>
        </div>
      </div>
    </div>

    <!-- Fehler -->
    <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ error }}
      <button class="ml-2 underline text-xs" @click="error = ''">schließen</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <Loader2 class="w-6 h-6 animate-spin text-[var(--color-accent)]" />
    </div>

    <template v-if="!loading">
      <!-- Profil-Auswahl + Verbindung -->
      <div class="pim-card p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Canva-Verbindung -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1.5">Canva-Verbindung</label>
            <select v-model="selectedConnectionId" class="pim-input text-xs w-full py-1.5">
              <option :value="null">— Verbindung wählen —</option>
              <option v-for="conn in canvaConnections" :key="conn.id" :value="conn.id">
                {{ conn.name }}
              </option>
            </select>
            <p v-if="canvaConnections.length === 0" class="text-[11px] text-[var(--color-text-tertiary)] mt-1">
              Keine aktive Canva-Verbindung vorhanden.
              <router-link to="/connectors/canva" class="text-[var(--color-accent)] underline">Einrichten</router-link>
            </p>
          </div>

          <!-- Export-Profil -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1.5">Export-Profil</label>
            <ProfileSelector
              :profiles="profiles"
              v-model="selectedProfileId"
              label="Canva-Profil"
              @load="loadProfile"
              @save="saveProfile"
              @update="updateProfile"
              @delete="deleteProfile"
            />
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="pim-card">
        <div class="flex border-b border-[var(--color-border)]">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :class="[
              'px-4 py-2.5 text-xs font-medium border-b-2 -mb-px transition-colors',
              activeTab === tab.key
                ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
                : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]',
            ]"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
            <span v-if="tab.key === 'products' && selectedProducts.length" class="ml-1 px-1.5 py-0.5 rounded-full bg-[var(--color-accent)] text-white text-[10px]">
              {{ selectedProducts.length }}
            </span>
            <span v-if="tab.key === 'attributes' && selectedAttributeIds.length" class="ml-1 px-1.5 py-0.5 rounded-full bg-[var(--color-accent)] text-white text-[10px]">
              {{ selectedAttributeIds.length }}
            </span>
          </button>
        </div>

        <div class="p-4">
          <!-- Tab: Produkte -->
          <div v-if="activeTab === 'products'" class="space-y-4">
            <!-- Suche -->
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
              <input
                v-model="productSearch"
                @input="onProductSearch"
                class="pim-input text-xs w-full pl-9 py-2"
                placeholder="Produkt suchen (Name, SKU, EAN)..."
              />
              <Loader2 v-if="productSearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-[var(--color-text-tertiary)]" />
            </div>

            <!-- Suchergebnisse -->
            <div v-if="productSearchResults.length" class="border border-[var(--color-border)] rounded-lg max-h-48 overflow-y-auto">
              <button
                v-for="p in productSearchResults"
                :key="p.id"
                class="w-full text-left px-3 py-2 text-xs hover:bg-[var(--color-bg)] border-b border-[var(--color-border)] last:border-b-0 flex items-center justify-between"
                @click="addProduct(p)"
              >
                <div>
                  <span class="font-medium text-[var(--color-text-primary)]">{{ p.name || '—' }}</span>
                  <span class="ml-2 text-[var(--color-text-tertiary)]">{{ p.sku }}</span>
                </div>
                <span class="text-[var(--color-accent)] text-[10px] font-medium">+ Hinzufügen</span>
              </button>
            </div>

            <!-- Ausgewählte Produkte -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                  Ausgewählte Produkte ({{ selectedProducts.length }})
                </p>
                <button
                  v-if="selectedProducts.length"
                  class="text-[11px] text-[var(--color-error)] hover:underline"
                  @click="selectedProducts = []"
                >
                  Alle entfernen
                </button>
              </div>

              <div v-if="selectedProducts.length === 0" class="text-center py-8 text-[var(--color-text-tertiary)] text-xs">
                <Package class="w-8 h-8 mx-auto mb-2 opacity-40" :stroke-width="1.5" />
                Noch keine Produkte ausgewählt. Nutze die Suche oben.
              </div>

              <div v-else class="space-y-1 max-h-64 overflow-y-auto">
                <div
                  v-for="p in selectedProducts"
                  :key="p.id"
                  class="flex items-center justify-between px-3 py-2 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)]"
                >
                  <div class="text-xs">
                    <span class="font-medium text-[var(--color-text-primary)]">{{ p.name || '—' }}</span>
                    <span class="ml-2 text-[var(--color-text-tertiary)]">{{ p.sku }}</span>
                  </div>
                  <button
                    class="text-[var(--color-error)] hover:text-red-700 p-1"
                    @click="removeProduct(p.id)"
                  >
                    <XCircle class="w-3.5 h-3.5" :stroke-width="1.75" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: Attribute & Sprache -->
          <div v-if="activeTab === 'attributes'" class="space-y-5">
            <!-- Sprach-Auswahl -->
            <div>
              <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">
                <Globe class="w-3.5 h-3.5 inline -mt-0.5 mr-1" :stroke-width="1.75" />
                Sprachen
              </p>
              <div class="flex gap-3">
                <label
                  v-for="lang in availableLanguages"
                  :key="lang.code"
                  class="flex items-center gap-1.5 text-xs cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="selectedLanguages.includes(lang.code)"
                    @change="toggleLanguage(lang.code)"
                    class="rounded border-[var(--color-border-strong)]"
                  />
                  {{ lang.label }}
                </label>
              </div>
            </div>

            <!-- Attribut-Auswahl -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                  <Tag class="w-3.5 h-3.5 inline -mt-0.5 mr-1" :stroke-width="1.75" />
                  Attribute ({{ selectedAttributeIds.length }} ausgewählt)
                </p>
                <div class="flex gap-2">
                  <button class="text-[11px] text-[var(--color-accent)] hover:underline" @click="selectAllAttributes">Alle</button>
                  <button class="text-[11px] text-[var(--color-text-tertiary)] hover:underline" @click="deselectAllAttributes">Keine</button>
                </div>
              </div>

              <!-- Suche -->
              <div class="relative mb-2">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                <input
                  v-model="attrSearchTerm"
                  class="pim-input text-xs w-full pl-8 py-1.5"
                  placeholder="Attribut suchen..."
                />
              </div>

              <!-- Attribut-Liste -->
              <div class="border border-[var(--color-border)] rounded-lg max-h-64 overflow-y-auto">
                <label
                  v-for="attr in filteredAttributes"
                  :key="attr.id"
                  class="flex items-center gap-2.5 px-3 py-2 text-xs cursor-pointer hover:bg-[var(--color-bg)] border-b border-[var(--color-border)] last:border-b-0"
                >
                  <input
                    type="checkbox"
                    :checked="isAttrSelected(attr.id)"
                    @change="toggleAttribute(attr.id)"
                    class="rounded border-[var(--color-border-strong)]"
                  />
                  <span class="font-medium text-[var(--color-text-primary)]">{{ attr.name_de || attr.technical_name }}</span>
                  <span class="text-[var(--color-text-tertiary)]">{{ attr.technical_name }}</span>
                </label>
                <div v-if="filteredAttributes.length === 0" class="px-3 py-4 text-center text-[var(--color-text-tertiary)] text-xs">
                  Keine Attribute gefunden
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: Optionen & Senden -->
          <div v-if="activeTab === 'options'" class="space-y-5">
            <!-- Daten-Optionen -->
            <div class="space-y-3">
              <p class="text-[12px] font-medium text-[var(--color-text-secondary)]">Zusätzliche Daten</p>
              <label class="flex items-center gap-2.5 text-xs cursor-pointer">
                <input type="checkbox" v-model="includePrices" class="rounded border-[var(--color-border-strong)]" />
                <DollarSign class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                Preise übertragen
              </label>
              <label class="flex items-center gap-2.5 text-xs cursor-pointer">
                <input type="checkbox" v-model="includeMedia" class="rounded border-[var(--color-border-strong)]" />
                <Image class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
                Medien übertragen
              </label>
            </div>

            <!-- Brand Template -->
            <div>
              <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1.5">Brand Template ID (optional)</label>
              <input
                v-model="brandTemplateId"
                class="pim-input text-xs w-full py-1.5"
                placeholder="Canva Brand-Template-ID eingeben..."
              />
              <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
                Wenn angegeben, werden die Produktdaten per Autofill in das Template eingefügt.
              </p>
            </div>

            <!-- Zusammenfassung -->
            <div class="rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)] p-4 space-y-2">
              <p class="text-[12px] font-semibold text-[var(--color-text-primary)]">Zusammenfassung</p>
              <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-[var(--color-text-secondary)]">
                <span>Verbindung:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ selectedConnection?.name || '—' }}</span>
                <span>Produkte:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ selectedProducts.length }}</span>
                <span>Attribute:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ selectedAttributeIds.length || 'Alle' }}</span>
                <span>Sprachen:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ selectedLanguages.join(', ').toUpperCase() }}</span>
                <span>Preise:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ includePrices ? 'Ja' : 'Nein' }}</span>
                <span>Medien:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ includeMedia ? 'Ja' : 'Nein' }}</span>
                <span>Template:</span>
                <span class="font-medium text-[var(--color-text-primary)]">{{ brandTemplateId || '—' }}</span>
              </div>
            </div>

            <!-- Senden-Button -->
            <div class="flex items-center gap-3">
              <button
                class="pim-btn pim-btn-primary text-xs px-5 py-2"
                :disabled="!canExecute || executing"
                @click="executeExport"
              >
                <Loader2 v-if="executing" class="w-4 h-4 animate-spin mr-1.5" />
                <Play v-else class="w-4 h-4 mr-1.5" :stroke-width="1.75" />
                {{ executing ? 'Wird gesendet...' : 'An Canva senden' }}
              </button>
              <p v-if="!canExecute && !executing" class="text-[11px] text-[var(--color-text-tertiary)]">
                Wähle eine Verbindung und mindestens ein Produkt aus.
              </p>
            </div>

            <!-- Ergebnisse -->
            <div v-if="executeResults" class="rounded-lg border border-[var(--color-border)] overflow-hidden">
              <div class="px-4 py-2.5 bg-[var(--color-bg)] border-b border-[var(--color-border)]">
                <p class="text-[12px] font-semibold text-[var(--color-text-primary)]">Ergebnisse</p>
              </div>
              <div class="divide-y divide-[var(--color-border)]">
                <div
                  v-for="(result, key) in executeResults"
                  :key="key"
                  class="flex items-center justify-between px-4 py-2.5 text-xs"
                >
                  <span class="text-[var(--color-text-secondary)] truncate max-w-xs">{{ key }}</span>
                  <span :class="result.status === 'success' ? 'text-[var(--color-success)]' : 'text-[var(--color-error)]'" class="flex items-center gap-1">
                    <CheckCircle v-if="result.status === 'success'" class="w-3.5 h-3.5" />
                    <XCircle v-else class="w-3.5 h-3.5" />
                    {{ result.status === 'success' ? 'Erfolgreich' : (result.error || 'Fehler') }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
