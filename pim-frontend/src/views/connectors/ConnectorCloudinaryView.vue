<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Image, Plus, Trash2, RefreshCw, CheckCircle, ArrowUpRight, Settings, Zap,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')

const showConnectForm = ref(false)
const formData = ref({ name: 'Cloudinary-Verbindung', cloud_name: '', api_key: '', api_secret: '' })
const connecting = ref(false)

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
    connectorInfo.value = allConnectors.find(c => c.type === 'cloudinary')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'cloudinary')
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

async function connectCloudinary() {
  if (!formData.value.cloud_name || !formData.value.api_key || !formData.value.api_secret) return
  connecting.value = true
  error.value = ''
  try {
    await connectorsApi.callback('cloudinary', {
      code: formData.value.api_key,
      name: formData.value.name,
      settings: {
        cloud_name: formData.value.cloud_name,
        api_key: formData.value.api_key,
        api_secret: formData.value.api_secret,
      },
    })
    showConnectForm.value = false
    formData.value = { name: 'Cloudinary-Verbindung', cloud_name: '', api_key: '', api_secret: '' }
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
    error.value = 'Fehler beim Löschen'
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Image class="w-6 h-6 text-primary" />
        <div>
          <h1 class="text-xl font-bold">Cloudinary</h1>
          <p class="text-sm text-base-content/60">Bildoptimierung, Transformation und CDN-Delivery</p>
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
              <div class="text-sm mt-1">API Key + Secret</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Fähigkeiten</div>
              <div class="flex gap-1 mt-1">
                <span class="badge badge-xs badge-outline"><Image class="w-3 h-3 mr-1" />Asset Upload</span>
                <span class="badge badge-xs badge-outline"><Zap class="w-3 h-3 mr-1" />Transformation</span>
              </div>
            </div>
          </div>
          <div class="text-xs text-base-content/40 mt-3">
            Cloud-Name, API-Key und API-Secret findest du im <strong>Cloudinary Dashboard</strong> unter Settings &gt; API Keys.
          </div>
        </div>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">Neue Cloudinary-Verbindung</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
            <div class="form-control">
              <label class="label"><span class="label-text">Name</span></label>
              <input v-model="formData.name" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">Cloud Name</span></label>
              <input v-model="formData.cloud_name" type="text" class="input input-bordered input-sm" placeholder="my-cloud" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">API Key</span></label>
              <input v-model="formData.api_key" type="text" class="input input-bordered input-sm" />
            </div>
            <div class="form-control">
              <label class="label"><span class="label-text">API Secret</span></label>
              <input v-model="formData.api_secret" type="password" class="input input-bordered input-sm" />
            </div>
          </div>
          <div class="flex gap-2 mt-3 justify-end">
            <button class="btn btn-ghost btn-sm" @click="showConnectForm = false">Abbrechen</button>
            <button class="btn btn-primary btn-sm" :disabled="connecting || !formData.cloud_name || !formData.api_key || !formData.api_secret" @click="connectCloudinary">
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
          Noch keine Cloudinary-Verbindung hergestellt.
        </div>

        <div v-for="conn in connections" :key="conn.id"
          class="card bg-base-100 shadow-sm border border-base-200 cursor-pointer hover:border-primary/30 transition-colors"
          @click="router.push(`/connectors/${conn.id}`)">
          <div class="card-body p-4 flex-row items-center justify-between">
            <div>
              <span class="font-semibold">{{ conn.name }}</span>
              <span v-if="conn.settings?.cloud_name" class="text-xs text-base-content/40 ml-2">{{ conn.settings.cloud_name }}</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-success flex items-center gap-1 text-sm">
                <CheckCircle class="w-4 h-4" />
                Aktiv
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
