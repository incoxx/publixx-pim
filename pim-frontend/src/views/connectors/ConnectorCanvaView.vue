<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Send, Plus, Trash2, RefreshCw, CheckCircle, XCircle, ArrowUpRight, Settings, Image, Package,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')
const oauthPending = ref(false)
const codeVerifier = ref(null)

onMounted(loadData)

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [listRes, connRes] = await Promise.all([
      connectorsApi.list(),
      connectorsApi.connections(),
    ])
    const allConnectors = listRes.data.data || listRes.data
    connectorInfo.value = allConnectors.find(c => c.type === 'canva')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'canva')
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

async function startOAuth() {
  try {
    const { data } = await connectorsApi.authorize('canva')
    const authData = data.data || data
    codeVerifier.value = authData.code_verifier
    oauthPending.value = true
    window.open(authData.url, 'oauth', 'width=600,height=700')
    window.addEventListener('message', handleOAuthMessage, { once: true })
  } catch (e) {
    error.value = `OAuth-Fehler: ${e.response?.data?.message || e.message}`
  }
}

async function handleOAuthMessage(event) {
  if (!event.data?.code || !oauthPending.value) return
  try {
    await connectorsApi.callback('canva', {
      code: event.data.code,
      code_verifier: codeVerifier.value,
      name: 'Canva-Verbindung',
    })
    oauthPending.value = false
    await loadData()
  } catch (e) {
    error.value = `Callback-Fehler: ${e.response?.data?.message || e.message}`
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
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Send class="w-6 h-6 text-purple-500" />
        <div>
          <h1 class="text-xl font-bold">Canva</h1>
          <p class="text-sm text-base-content/60">Assets hochladen und Designs erstellen</p>
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
      <!-- Setup -->
      <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body">
          <h2 class="card-title text-base">
            <Settings class="w-4 h-4" />
            Einrichtung
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div>
              <div class="text-sm text-base-content/50">Status</div>
              <span :class="connectorInfo?.configured ? 'badge-success' : 'badge-warning'" class="badge badge-sm mt-1">
                {{ connectorInfo?.configured ? 'Konfiguriert' : 'Nicht konfiguriert' }}
              </span>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Fähigkeiten</div>
              <div class="flex gap-1 mt-1">
                <span class="badge badge-xs badge-outline"><Image class="w-3 h-3 mr-1" />Asset Upload</span>
                <span class="badge badge-xs badge-outline"><Package class="w-3 h-3 mr-1" />Design Create</span>
              </div>
            </div>
          </div>
          <div class="text-xs text-base-content/40 mt-3">
            Benötigt: <code>CANVA_CLIENT_ID</code>, <code>CANVA_CLIENT_SECRET</code>, <code>CANVA_REDIRECT_URI</code> in der .env
          </div>
        </div>
      </div>

      <!-- Verbindungen -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Verbindungen</h2>
          <button class="btn btn-sm btn-primary" :disabled="!connectorInfo?.configured" @click="startOAuth">
            <Plus class="w-4 h-4" />
            Verbinden (OAuth)
          </button>
        </div>

        <div v-if="connections.length === 0" class="text-center py-6 text-base-content/40">
          Noch keine Canva-Verbindung hergestellt.
        </div>

        <div v-for="conn in connections" :key="conn.id"
          class="card bg-base-100 shadow-sm border border-base-200 cursor-pointer hover:border-primary/30 transition-colors"
          @click="router.push(`/connectors/${conn.id}`)">
          <div class="card-body p-4 flex-row items-center justify-between">
            <div>
              <span class="font-semibold">{{ conn.name }}</span>
              <span class="text-xs text-base-content/40 ml-2">{{ conn.connected_by?.name }}</span>
            </div>
            <div class="flex items-center gap-3">
              <span :class="conn.is_active && !conn.token_expired ? 'text-success' : 'text-error'" class="flex items-center gap-1 text-sm">
                <CheckCircle v-if="conn.is_active && !conn.token_expired" class="w-4 h-4" />
                <XCircle v-else class="w-4 h-4" />
                {{ conn.is_active && !conn.token_expired ? 'Aktiv' : 'Token abgelaufen' }}
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
