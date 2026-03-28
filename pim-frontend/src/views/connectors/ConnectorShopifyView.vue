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
const authMode = ref('token') // 'token' = Legacy, 'credentials' = Neu (ab 2026)
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
      // Neue Apps (ab 2026): Client Credentials
      // code = client_id, code_verifier = client_secret
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
      // Legacy: Statischer Access Token
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
    error.value = `Verbindungsfehler: ${e.response?.data?.message || e.message}`
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
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <ShoppingBag class="w-6 h-6 text-primary" />
        <div>
          <h1 class="text-xl font-bold">Shopify</h1>
          <p class="text-sm text-base-content/60">Produkte und Medien in den Shop synchronisieren</p>
        </div>
      </div>
      <button class="btn btn-sm btn-ghost" @click="loadData">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <template v-if="!loading">
      <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body">
          <h2 class="card-title text-base">
            <Settings class="w-4 h-4" />
            Einrichtung
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div>
              <div class="text-sm text-base-content/50">Authentifizierung</div>
              <div class="text-sm mt-1">Custom App (Access Token oder Client Credentials)</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Funktionen</div>
              <div class="flex gap-1 mt-1">
                <span class="badge badge-xs badge-outline"><Image class="w-3 h-3 mr-1" />Asset Upload</span>
                <span class="badge badge-xs badge-outline"><ShoppingBag class="w-3 h-3 mr-1" />Produktdaten</span>
              </div>
            </div>
          </div>
          <div class="text-xs text-base-content/40 mt-3">
            Erstelle unter <strong>Einstellungen &gt; Apps und Vertriebskanale &gt; Apps entwickeln</strong> im Shopify-Admin eine Custom App.
            <br>
            <strong>Vor 2026:</strong> Statischer Admin API Access Token (Token einmalig anzeigen).
            <strong>Ab 2026:</strong> Client ID + Client Secret (Token wird automatisch generiert, 24h gueltig).
          </div>
        </div>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">Neue Shopify-Verbindung</h3>

          <!-- Auth-Modus Toggle -->
          <div class="flex gap-2 mt-2">
            <button
              class="btn btn-xs"
              :class="authMode === 'token' ? 'btn-primary' : 'btn-ghost'"
              @click="authMode = 'token'"
            >Access Token (Legacy)</button>
            <button
              class="btn btn-xs"
              :class="authMode === 'credentials' ? 'btn-primary' : 'btn-ghost'"
              @click="authMode = 'credentials'"
            >Client Credentials (ab 2026)</button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
            <div class="form-control">
              <label class="label"><span class="label-text">Name</span></label>
              <input v-model="formData.name" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Shop-URL</span></label>
              <input v-model="formData.shop_url" type="url" class="input input-bordered input-sm" placeholder="https://mein-shop.myshopify.com" />
            </div>

            <!-- Legacy: Access Token -->
            <template v-if="authMode === 'token'">
              <div class="form-control md:col-span-2">
                <label class="label"><span class="label-text">Admin API Access Token</span></label>
                <input v-model="formData.access_token" type="password" class="input input-bordered input-sm" placeholder="shpat_..." />
                <div class="text-xs text-base-content/40 mt-1">
                  Unter Apps entwickeln &gt; API-Zugangsdaten &gt; Token einmalig anzeigen
                </div>
              </div>
            </template>

            <!-- Neu: Client Credentials -->
            <template v-if="authMode === 'credentials'">
              <div class="form-control">
                <label class="label"><span class="label-text">Client ID</span></label>
                <input v-model="formData.client_id" type="text" class="input input-bordered input-sm" />
              </div>
              <div class="form-control">
                <label class="label"><span class="label-text">Client Secret</span></label>
                <input v-model="formData.client_secret" type="password" class="input input-bordered input-sm" />
              </div>
              <div class="text-xs text-base-content/40 md:col-span-2">
                Token wird automatisch generiert und alle 24 Stunden erneuert.
              </div>
            </template>
          </div>
          <div class="flex gap-2 mt-3 justify-end">
            <button class="btn btn-ghost btn-sm" @click="showConnectForm = false">Abbrechen</button>
            <button class="btn btn-primary btn-sm" :disabled="connecting || !canConnect" @click="connectShopify">
              <span v-if="connecting" class="loading loading-spinner loading-xs"></span>
              Verbinden
            </button>
          </div>
        </div>
      </div>

      <!-- Verbindungen -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Verbindungen</h2>
          <button class="btn btn-sm btn-primary" @click="showConnectForm = true">
            <Plus class="w-4 h-4" />
            Verbinden
          </button>
        </div>

        <div v-if="connections.length === 0" class="text-center py-6 text-base-content/40">
          Noch keine Shopify-Verbindung hergestellt.
        </div>

        <div v-for="conn in connections" :key="conn.id"
          class="card bg-base-100 shadow-sm border border-base-200 cursor-pointer hover:border-primary/30 transition-colors"
          @click="router.push(`/connectors/${conn.id}`)">
          <div class="card-body p-4 flex-row items-center justify-between">
            <div>
              <span class="font-semibold">{{ conn.name }}</span>
              <span v-if="conn.settings?.shop_url" class="text-xs text-base-content/40 ml-2">{{ conn.settings.shop_url }}</span>
              <span v-if="conn.settings?.client_id" class="badge badge-xs badge-ghost ml-2">Client Credentials</span>
            </div>
            <div class="flex items-center gap-3">
              <span :class="conn.is_active && !conn.token_expired ? 'text-success' : 'text-warning'" class="flex items-center gap-1 text-sm">
                <CheckCircle v-if="conn.is_active && !conn.token_expired" class="w-4 h-4" />
                <XCircle v-else class="w-4 h-4" />
                {{ conn.is_active && !conn.token_expired ? 'Aktiv' : 'Token erneuern' }}
              </span>
              <button class="btn btn-ghost btn-xs text-error" @click.stop="deleteConnection(conn.id)">
                <Trash2 class="w-4 h-4" />
              </button>
              <ArrowUpRight class="w-4 h-4 text-base-content/30" />
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
