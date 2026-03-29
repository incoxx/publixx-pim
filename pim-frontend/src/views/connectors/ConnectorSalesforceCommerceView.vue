<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Cloud, Plus, Trash2, RefreshCw, CheckCircle, XCircle, ArrowUpRight, Settings, Image, Package,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')

const showConnectForm = ref(false)
const formData = ref({
  name: 'Salesforce Commerce Cloud',
  instance_url: '',
  client_id: '',
  client_secret: '',
  site_id: '',
  catalog_id: 'storefront-catalog',
})
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
    connectorInfo.value = allConnectors.find(c => c.type === 'salesforce_commerce')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'salesforce_commerce')

    // Plugin-Einstellungen als Vorauswahl ins Formular uebernehmen
    const creds = credRes?.data?.data?.salesforce_commerce || credRes?.data?.salesforce_commerce
    if (creds) {
      formData.value.instance_url = creds.instance_url || ''
      formData.value.client_id = creds.client_id || ''
      formData.value.client_secret = creds.client_secret || ''
      formData.value.site_id = creds.site_id || ''
      formData.value.catalog_id = creds.catalog_id || 'storefront-catalog'
    }
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

async function connectSfcc() {
  if (!formData.value.client_id || !formData.value.client_secret || !formData.value.instance_url) return
  connecting.value = true
  error.value = ''
  try {
    await connectorsApi.callback('salesforce_commerce', {
      code: formData.value.client_id,
      code_verifier: formData.value.client_secret,
      name: formData.value.name,
      shop_url: formData.value.instance_url,
      settings: {
        instance_url: formData.value.instance_url,
        client_id: formData.value.client_id,
        client_secret: formData.value.client_secret,
        site_id: formData.value.site_id,
        catalog_id: formData.value.catalog_id,
      },
    })
    showConnectForm.value = false
    formData.value = {
      name: 'Salesforce Commerce Cloud',
      instance_url: '',
      client_id: '',
      client_secret: '',
      site_id: '',
      catalog_id: 'storefront-catalog',
    }
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
    await connectorsApi.callback('salesforce_commerce', {
      code: conn.settings?.client_id || '',
      code_verifier: conn.settings?.client_secret || '',
      name: conn.name,
      shop_url: conn.settings?.instance_url || '',
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
    error.value = 'Fehler beim Loeschen'
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Cloud class="w-6 h-6 text-primary" />
        <div>
          <h1 class="text-xl font-bold">Salesforce Commerce Cloud</h1>
          <p class="text-sm text-base-content/60">Produkte und Kataloge in SFCC synchronisieren</p>
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
              <div class="text-sm mt-1">Account Manager (Client Credentials)</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">API</div>
              <div class="text-sm mt-1">OCAPI Data API v24_5</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Medien</div>
              <div class="text-sm mt-1">URL-Referenz (image_groups)</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Funktionen</div>
              <div class="flex gap-1 mt-1 flex-wrap">
                <span class="badge badge-xs badge-outline"><Package class="w-3 h-3 mr-1" />Produktdaten</span>
                <span class="badge badge-xs badge-outline"><Image class="w-3 h-3 mr-1" />Medien</span>
              </div>
            </div>
          </div>
          <div class="text-xs text-base-content/40 mt-3">
            Benoetig einen SFCC API-Client im Account Manager mit Zugriff auf die OCAPI Data API.
            Konfiguriere die OCAPI-Berechtigungen im Business Manager unter
            <strong>Administration &gt; Site Development &gt; Open Commerce API Settings</strong>.
          </div>
        </div>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">Neue SFCC-Verbindung</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
            <div class="form-control">
              <label class="label"><span class="label-text">Name</span></label>
              <input v-model="formData.name" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Instance URL</span></label>
              <input v-model="formData.instance_url" type="url" class="input input-bordered input-sm" placeholder="https://abcd-001.dx.commercecloud.salesforce.com" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Client-ID</span></label>
              <input v-model="formData.client_id" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Client-Secret</span></label>
              <input v-model="formData.client_secret" type="password" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Site-ID</span></label>
              <input v-model="formData.site_id" type="text" class="input input-bordered input-sm" placeholder="z.B. RefArch oder SiteGenesis" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Katalog-ID</span></label>
              <input v-model="formData.catalog_id" type="text" class="input input-bordered input-sm" placeholder="storefront-catalog" />
            </div>
          </div>
          <div class="flex gap-2 mt-3 justify-end">
            <button class="btn btn-ghost btn-sm" @click="showConnectForm = false">Abbrechen</button>
            <button class="btn btn-primary btn-sm" :disabled="connecting || !formData.client_id || !formData.client_secret || !formData.instance_url" @click="connectSfcc">
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
          Noch keine SFCC-Verbindung hergestellt.
        </div>

        <div v-for="conn in connections" :key="conn.id"
          class="card bg-base-100 shadow-sm border border-base-200 cursor-pointer hover:border-primary/30 transition-colors"
          @click="router.push(`/connectors/${conn.id}`)">
          <div class="card-body p-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-between">
              <div class="min-w-0">
                <span class="font-semibold">{{ conn.name }}</span>
                <span v-if="conn.settings?.instance_url" class="text-xs text-base-content/40 ml-2 break-all">{{ conn.settings.instance_url }}</span>
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
