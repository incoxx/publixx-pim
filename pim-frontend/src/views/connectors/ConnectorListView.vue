<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Plug, Plus, Trash2, RefreshCw, CheckCircle, XCircle, ExternalLink, ArrowUpRight,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()

const connectors = ref([])
const connections = ref([])
const loading = ref(false)
const error = ref('')

// OAuth state
const oauthPending = ref(null) // connector type in auth flow
const codeVerifier = ref(null)

onMounted(loadAll)

async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    const [connectorsRes, connectionsRes] = await Promise.all([
      connectorsApi.list(),
      connectorsApi.connections(),
    ])
    connectors.value = connectorsRes.data.data || connectorsRes.data
    connections.value = connectionsRes.data.data || connectionsRes.data
  } catch (e) {
    error.value = 'Fehler beim Laden der Connectoren'
  } finally {
    loading.value = false
  }
}

async function startOAuth(type) {
  try {
    const { data } = await connectorsApi.authorize(type)
    const authData = data.data || data
    // Store code verifier for PKCE
    codeVerifier.value = authData.code_verifier
    oauthPending.value = type
    // Open auth URL in popup
    const popup = window.open(authData.url, 'oauth', 'width=600,height=700')
    // Listen for callback
    window.addEventListener('message', handleOAuthMessage, { once: true })
  } catch (e) {
    error.value = `OAuth-Fehler: ${e.response?.data?.message || e.message}`
  }
}

async function handleOAuthMessage(event) {
  if (!event.data?.code || !oauthPending.value) return
  try {
    await connectorsApi.callback(oauthPending.value, {
      code: event.data.code,
      code_verifier: codeVerifier.value,
      name: `${oauthPending.value.charAt(0).toUpperCase() + oauthPending.value.slice(1)}-Verbindung`,
    })
    oauthPending.value = null
    codeVerifier.value = null
    await loadAll()
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

function getConnectorName(type) {
  const c = connectors.value.find(c => c.type === type)
  return c?.name || type
}

function viewConnection(id) {
  router.push(`/connectors/${id}`)
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Plug class="w-6 h-6 text-primary" />
        <h1 class="text-xl font-bold">Connectoren</h1>
      </div>
      <button class="btn btn-sm btn-ghost" @click="loadAll">
        <RefreshCw class="w-4 h-4" />
        Aktualisieren
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <template v-else>
      <!-- Verfügbare Connectoren -->
      <div class="space-y-3">
        <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Verfügbare Connectoren</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="connector in connectors"
            :key="connector.type"
            class="card bg-base-100 shadow-sm border border-base-200"
          >
            <div class="card-body p-4">
              <div class="flex items-center justify-between">
                <h3 class="font-semibold">{{ connector.name }}</h3>
                <span
                  :class="connector.configured ? 'badge-success' : 'badge-warning'"
                  class="badge badge-sm"
                >
                  {{ connector.configured ? 'Konfiguriert' : 'Nicht konfiguriert' }}
                </span>
              </div>
              <p class="text-sm text-base-content/60">{{ connector.description }}</p>
              <div class="flex flex-wrap gap-1 mt-1">
                <span
                  v-for="cap in connector.capabilities"
                  :key="cap"
                  class="badge badge-xs badge-outline"
                >
                  {{ cap }}
                </span>
              </div>
              <div class="card-actions justify-end mt-2">
                <button
                  class="btn btn-sm btn-primary"
                  :disabled="!connector.configured"
                  @click="startOAuth(connector.type)"
                >
                  <Plus class="w-4 h-4" />
                  Verbinden
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Aktive Verbindungen -->
      <div class="space-y-3">
        <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Aktive Verbindungen</h2>
        <div v-if="connections.length === 0" class="text-center py-8 text-base-content/40">
          Noch keine Verbindungen hergestellt.
        </div>
        <div class="space-y-2">
          <div
            v-for="conn in connections"
            :key="conn.id"
            class="card bg-base-100 shadow-sm border border-base-200 cursor-pointer hover:border-primary/30 transition-colors"
            @click="viewConnection(conn.id)"
          >
            <div class="card-body p-4 flex-row items-center justify-between">
              <div class="flex items-center gap-3">
                <div>
                  <span class="font-semibold">{{ conn.name }}</span>
                  <span class="badge badge-sm badge-ghost ml-2">{{ getConnectorName(conn.connector_type) }}</span>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <span
                  :class="conn.is_active && !conn.token_expired ? 'text-success' : 'text-error'"
                  class="flex items-center gap-1 text-sm"
                >
                  <CheckCircle v-if="conn.is_active && !conn.token_expired" class="w-4 h-4" />
                  <XCircle v-else class="w-4 h-4" />
                  {{ conn.is_active && !conn.token_expired ? 'Aktiv' : 'Token abgelaufen' }}
                </span>
                <span class="text-xs text-base-content/40">
                  {{ conn.connected_by?.name }}
                </span>
                <button
                  class="btn btn-ghost btn-xs text-error"
                  @click.stop="deleteConnection(conn.id)"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
                <ArrowUpRight class="w-4 h-4 text-base-content/30" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
