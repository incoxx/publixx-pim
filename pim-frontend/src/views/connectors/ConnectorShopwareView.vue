<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Package, Plus, Trash2, RefreshCw, CheckCircle, XCircle, ArrowUpRight, Settings, Image,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')

const showConnectForm = ref(false)
const formData = ref({ name: 'Shopware-Verbindung', shop_url: '', client_id: '', client_secret: '' })
const connecting = ref(false)

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
    connectorInfo.value = allConnectors.find(c => c.type === 'shopware')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'shopware')

    // Plugin-Einstellungen als Vorauswahl ins Formular übernehmen
    const creds = credRes?.data?.data?.shopware || credRes?.data?.shopware
    if (creds) {
      formData.value.shop_url = creds.shop_url || ''
      formData.value.client_id = creds.client_id || ''
      formData.value.client_secret = creds.client_secret || ''
    }
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

async function connectShopware() {
  if (!formData.value.client_id || !formData.value.client_secret) return
  connecting.value = true
  error.value = ''
  try {
    // Use client_id as "code" and client_secret as "code_verifier"
    await connectorsApi.callback('shopware', {
      code: formData.value.client_id,
      code_verifier: formData.value.client_secret,
      name: formData.value.name,
      settings: {
        shop_url: formData.value.shop_url,
        client_id: formData.value.client_id,
        client_secret: formData.value.client_secret,
      },
    })
    showConnectForm.value = false
    formData.value = { name: 'Shopware-Verbindung', shop_url: '', client_id: '', client_secret: '' }
    await loadData()
  } catch (e) {
    error.value = `Verbindungsfehler: ${e.response?.data?.message || e.message}`
  } finally {
    connecting.value = false
  }
}

const refreshing = ref(null)

async function refreshToken(conn) {
  refreshing.value = conn.id
  error.value = ''
  try {
    // Re-authenticate mit gespeicherten Credentials → neuer Token
    await connectorsApi.callback('shopware', {
      code: conn.settings?.client_id || '',
      code_verifier: conn.settings?.client_secret || '',
      name: conn.name,
      settings: conn.settings,
    })
    await loadData()
  } catch (e) {
    error.value = `Token-Erneuerung fehlgeschlagen: ${e.response?.data?.message || e.message}`
  } finally {
    refreshing.value = null
  }
}

async function deleteConnection(id) {
  if (!confirm('Verbindung wirklich trennen?')) return
  try {
    await connectorsApi.deleteConnection(id)
    connections.value = connections.value.filter(c => c.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Package class="w-6 h-6 text-primary" />
        <div>
          <h1 class="text-xl font-bold">Shopware 6</h1>
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
              <div class="text-sm mt-1">Integration (Client Credentials)</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Fähigkeiten</div>
              <div class="flex gap-1 mt-1">
                <span class="badge badge-xs badge-outline"><Image class="w-3 h-3 mr-1" />Asset Upload</span>
                <span class="badge badge-xs badge-outline"><Package class="w-3 h-3 mr-1" />Produktdaten</span>
              </div>
            </div>
          </div>
          <div class="text-xs text-base-content/40 mt-3">
            Benötigt eine Shopware 6 Integration mit API-Zugang. Erstelle unter <strong>Einstellungen &gt; System &gt; Integrationen</strong> im Shopware-Admin.
          </div>
        </div>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">Neue Shopware-Verbindung</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
            <div class="form-control">
              <label class="label"><span class="label-text">Name</span></label>
              <input v-model="formData.name" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Shop-URL</span></label>
              <input v-model="formData.shop_url" type="url" class="input input-bordered input-sm" placeholder="https://mein-shop.de" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Client-ID (Access Key ID)</span></label>
              <input v-model="formData.client_id" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Client-Secret (Secret Access Key)</span></label>
              <input v-model="formData.client_secret" type="password" class="input input-bordered input-sm" />
            </div>
          </div>
          <div class="flex gap-2 mt-3 justify-end">
            <button class="btn btn-ghost btn-sm" @click="showConnectForm = false">Abbrechen</button>
            <button class="btn btn-primary btn-sm" :disabled="connecting || !formData.client_id || !formData.client_secret" @click="connectShopware">
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
          Noch keine Shopware-Verbindung hergestellt.
        </div>

        <div v-for="conn in connections" :key="conn.id"
          class="card bg-base-100 shadow-sm border border-base-200 cursor-pointer hover:border-primary/30 transition-colors"
          @click="router.push(`/connectors/${conn.id}`)">
          <div class="card-body p-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-between">
              <div class="min-w-0">
                <span class="font-semibold">{{ conn.name }}</span>
                <span v-if="conn.settings?.shop_url" class="text-xs text-base-content/40 ml-2 break-all">{{ conn.settings.shop_url }}</span>
              </div>
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="conn.is_active && !conn.token_expired ? 'text-success' : 'text-error'" class="flex items-center gap-1 text-sm whitespace-nowrap">
                  <CheckCircle v-if="conn.is_active && !conn.token_expired" class="w-4 h-4 shrink-0" />
                  <XCircle v-else class="w-4 h-4 shrink-0" />
                  {{ conn.is_active && !conn.token_expired ? 'Aktiv' : 'Token abgelaufen' }}
                </span>
                <button
                  v-if="conn.token_expired || !(conn.is_active)"
                  class="btn btn-ghost btn-xs text-warning whitespace-nowrap"
                  :disabled="refreshing === conn.id"
                  @click.stop="refreshToken(conn)"
                >
                  <RefreshCw class="w-4 h-4 shrink-0" :class="{ 'animate-spin': refreshing === conn.id }" />
                  Token erneuern
                </button>
                <button class="btn btn-ghost btn-xs text-error" @click.stop="deleteConnection(conn.id)">
                  <Trash2 class="w-4 h-4" />
                </button>
                <ArrowUpRight class="w-4 h-4 text-base-content/30 hidden sm:block" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
