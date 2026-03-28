<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ShoppingBag, Plus, Trash2, RefreshCw, CheckCircle, XCircle, ArrowUpRight, Settings, Image,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')

const showConnectForm = ref(false)
const authMode = ref('credentials') // 'token' = Legacy, 'credentials' = Neu (ab 2026)
const formData = ref({ name: 'Shopify-Verbindung', shop_url: '', access_token: '', client_id: '', client_secret: '' })
const connecting = ref(false)

const canConnect = computed(() => {
  if (!formData.value.shop_url) return false
  if (authMode.value === 'token') return !!formData.value.access_token
  return !!formData.value.client_id && !!formData.value.client_secret
})

onMounted(loadData)

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [listRes, connRes, credRes] = await Promise.all([
      connectorsApi.list(),
      connectorsApi.connections(),
      connectorsApi.getCredentials().catch(() => null),
    ])
    const allConnectors = listRes.data.data || listRes.data
    connectorInfo.value = allConnectors.find(c => c.type === 'shopify')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'shopify')

    // Plugin-Einstellungen als Vorauswahl ins Formular uebernehmen
    const creds = credRes?.data?.data?.shopify || credRes?.data?.shopify
    if (creds) {
      formData.value.shop_url = creds.shop_url || ''
      formData.value.access_token = creds.access_token || ''
      formData.value.client_id = creds.client_id || ''
      formData.value.client_secret = creds.client_secret || ''
      // Auto-detect auth mode
      if (creds.client_id && creds.client_secret) {
        authMode.value = 'credentials'
      } else if (creds.access_token) {
        authMode.value = 'token'
      }
    }
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

async function connectShopify() {
  if (!canConnect.value) return
  connecting.value = true
  error.value = ''
  try {
    if (authMode.value === 'credentials') {
      await connectorsApi.callback('shopify', {
        code: formData.value.client_id,
        code_verifier: formData.value.client_secret,
        name: formData.value.name,
        settings: {
          shop_url: formData.value.shop_url,
          client_id: formData.value.client_id,
          client_secret: formData.value.client_secret,
        },
      })
    } else {
      await connectorsApi.callback('shopify', {
        code: formData.value.access_token,
        name: formData.value.name,
        settings: {
          shop_url: formData.value.shop_url,
          access_token: formData.value.access_token,
        },
      })
    }
    showConnectForm.value = false
    formData.value = { name: 'Shopify-Verbindung', shop_url: '', access_token: '', client_id: '', client_secret: '' }
    await loadData()
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'Verbindungsfehler'
  } finally {
    connecting.value = false
  }
}

async function deleteConnection(id) {
  if (!confirm('Verbindung wirklich trennen?')) return
  try {
    await connectorsApi.deleteConnection(id)
    connections.value = connections.value.filter(c => c.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Loeschen'
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <ShoppingBag class="w-6 h-6 text-[var(--color-accent)]" />
        <div>
          <h1 class="text-xl font-bold text-[var(--color-text-primary)]">Shopify</h1>
          <p class="text-sm text-[var(--color-text-secondary)]">Produkte und Medien in den Shop synchronisieren</p>
        </div>
      </div>
      <button class="pim-btn pim-btn-ghost text-xs" @click="loadData">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-sm">
      {{ error }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-6 h-6 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-if="!loading">
      <!-- Einrichtung -->
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-5">
        <div class="flex items-center gap-2 mb-3">
          <Settings class="w-4 h-4 text-[var(--color-accent)]" />
          <h2 class="font-semibold text-[var(--color-text-primary)]">Einrichtung</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Authentifizierung</div>
            <div class="text-sm text-[var(--color-text-primary)] mt-1">Custom App (Client Credentials oder Access Token)</div>
          </div>
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Funktionen</div>
            <div class="flex gap-1 mt-1">
              <span class="pim-badge text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-accent-light)] text-[var(--color-accent)]">
                <Image class="w-3 h-3 mr-1 inline" />Asset Upload
              </span>
              <span class="pim-badge text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-accent-light)] text-[var(--color-accent)]">
                <ShoppingBag class="w-3 h-3 mr-1 inline" />Produktdaten
              </span>
            </div>
          </div>
        </div>
        <p class="text-xs text-[var(--color-text-tertiary)] mt-3 leading-relaxed">
          Erstelle unter <strong>Einstellungen &gt; Apps und Vertriebskanale &gt; Apps entwickeln</strong> im Shopify-Admin eine Custom App.
          Verwende die <strong>Client ID + Client Secret</strong> aus dem Shopify Partner Dashboard.
        </p>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="rounded-xl border border-[var(--color-accent)]/30 bg-[var(--color-bg)] p-5 space-y-4">
        <h3 class="font-semibold text-[var(--color-text-primary)]">Neue Shopify-Verbindung</h3>

        <!-- Auth-Modus Toggle -->
        <div class="flex gap-2">
          <button
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
            :class="authMode === 'credentials'
              ? 'bg-[var(--color-accent)] text-white'
              : 'bg-[var(--color-bg-elevated)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
            @click="authMode = 'credentials'"
          >Client Credentials</button>
          <button
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
            :class="authMode === 'token'
              ? 'bg-[var(--color-accent)] text-white'
              : 'bg-[var(--color-bg-elevated)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
            @click="authMode = 'token'"
          >Access Token (Legacy)</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
            <input v-model="formData.name" type="text" class="pim-input text-xs w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Shop-URL</label>
            <input v-model="formData.shop_url" type="url" class="pim-input text-xs w-full" placeholder="https://mein-shop.myshopify.com" />
          </div>

          <!-- Client Credentials -->
          <template v-if="authMode === 'credentials'">
            <div>
              <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Client ID</label>
              <input v-model="formData.client_id" type="text" class="pim-input text-xs w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Client Secret</label>
              <input v-model="formData.client_secret" type="password" class="pim-input text-xs w-full" />
            </div>
            <p class="text-xs text-[var(--color-text-tertiary)] md:col-span-2">
              Token wird automatisch generiert und alle 24 Stunden erneuert.
            </p>
          </template>

          <!-- Legacy: Access Token -->
          <template v-if="authMode === 'token'">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Admin API Access Token</label>
              <input v-model="formData.access_token" type="password" class="pim-input text-xs w-full" placeholder="shpat_..." />
              <p class="text-xs text-[var(--color-text-tertiary)] mt-1">
                Unter Apps entwickeln &gt; API-Zugangsdaten &gt; Token einmalig anzeigen
              </p>
            </div>
          </template>
        </div>

        <div class="flex gap-2 justify-end">
          <button class="pim-btn pim-btn-ghost text-xs" @click="showConnectForm = false">Abbrechen</button>
          <button
            class="pim-btn pim-btn-primary text-xs"
            :disabled="connecting || !canConnect"
            @click="connectShopify"
          >
            <span v-if="connecting" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></span>
            Verbinden
          </button>
        </div>
      </div>

      <!-- Verbindungen -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider">Verbindungen</h2>
          <button class="pim-btn pim-btn-primary text-xs" @click="showConnectForm = true">
            <Plus class="w-4 h-4 mr-1" />
            Verbinden
          </button>
        </div>

        <div v-if="connections.length === 0" class="text-center py-6 text-[var(--color-text-tertiary)]">
          Noch keine Shopify-Verbindung hergestellt.
        </div>

        <div
          v-for="conn in connections"
          :key="conn.id"
          class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4 cursor-pointer hover:border-[var(--color-accent)]/30 transition-colors flex items-center justify-between"
          @click="router.push(`/connectors/${conn.id}`)"
        >
          <div>
            <span class="font-semibold text-[var(--color-text-primary)]">{{ conn.name }}</span>
            <span v-if="conn.settings?.shop_url" class="text-xs text-[var(--color-text-tertiary)] ml-2">{{ conn.settings.shop_url }}</span>
            <span
              v-if="conn.settings?.client_id"
              class="pim-badge text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-accent-light)] text-[var(--color-accent)] ml-2"
            >Client Credentials</span>
          </div>
          <div class="flex items-center gap-3">
            <span
              :class="conn.is_active && !conn.token_expired
                ? 'text-[var(--color-success)]'
                : 'text-[var(--color-warning,theme(colors.amber.500))]'"
              class="flex items-center gap-1 text-sm"
            >
              <CheckCircle v-if="conn.is_active && !conn.token_expired" class="w-4 h-4" />
              <XCircle v-else class="w-4 h-4" />
              {{ conn.is_active && !conn.token_expired ? 'Aktiv' : 'Token erneuern' }}
            </span>
            <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-error)]" @click.stop="deleteConnection(conn.id)">
              <Trash2 class="w-4 h-4" />
            </button>
            <ArrowUpRight class="w-4 h-4 text-[var(--color-text-tertiary)]" />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
