<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Languages, Plus, Trash2, RefreshCw, CheckCircle, XCircle, ArrowUpRight, Settings, Zap,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const router = useRouter()
const connections = ref([])
const connectorInfo = ref(null)
const loading = ref(false)
const error = ref('')

// Direct API key connection form
const showConnectForm = ref(false)
const apiKeyInput = ref('')
const connectionName = ref('DeepL-Verbindung')
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
    connectorInfo.value = allConnectors.find(c => c.type === 'deepl')
    const allConns = connRes.data.data || connRes.data
    connections.value = allConns.filter(c => c.connector_type === 'deepl')
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

async function connectWithApiKey() {
  if (!apiKeyInput.value.trim()) return
  connecting.value = true
  error.value = ''
  try {
    await connectorsApi.callback('deepl', {
      code: apiKeyInput.value.trim(),
      name: connectionName.value || 'DeepL-Verbindung',
    })
    showConnectForm.value = false
    apiKeyInput.value = ''
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
        <Languages class="w-6 h-6 text-primary" />
        <div>
          <h1 class="text-xl font-bold">DeepL</h1>
          <p class="text-sm text-[var(--color-text-secondary)]">Automatische Übersetzung von Produkttexten</p>
        </div>
      </div>
      <button class="pim-btn pim-btn-secondary" @click="loadData">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-sm">{{ error }}</div>

    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <template v-if="!loading">
      <!-- Info -->
      <div class="pim-card p-6">
        <h2 class="text-sm font-semibold flex items-center gap-2 mb-4">
          <Settings class="w-4 h-4" />
          Einrichtung
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Authentifizierung</div>
            <div class="text-sm mt-1">API-Key (kein OAuth nötig)</div>
          </div>
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Fähigkeiten</div>
            <div class="flex gap-2 mt-1">
              <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded border border-[var(--color-border)] text-[var(--color-text-secondary)]">
                <Languages class="w-3 h-3" />Übersetzung
              </span>
              <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded border border-[var(--color-border)] text-[var(--color-text-secondary)]">
                <Zap class="w-3 h-3" />Produktdaten
              </span>
            </div>
          </div>
        </div>
        <div class="text-xs text-[var(--color-text-tertiary)] mt-3">
          Optional: <code class="bg-[var(--color-bg)] px-1 py-0.5 rounded text-[11px]">DEEPL_API_KEY</code> in .env als Default. Oder API-Key direkt bei der Verbindung eingeben.
        </div>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="pim-card p-6 border-[var(--color-accent)]/30">
        <h3 class="font-semibold text-sm mb-4">Neue DeepL-Verbindung</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
            <input v-model="connectionName" type="text" class="pim-input text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">DeepL API-Key</label>
            <input v-model="apiKeyInput" type="password" class="pim-input text-sm w-full" placeholder="xxxxxxxx-xxxx-..." />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button class="pim-btn pim-btn-secondary" @click="showConnectForm = false">Abbrechen</button>
          <button class="pim-btn pim-btn-primary" :disabled="connecting || !apiKeyInput.trim()" @click="connectWithApiKey">
            <span v-if="connecting" class="loading loading-spinner loading-xs"></span>
            Verbinden
          </button>
        </div>
      </div>

      <!-- Verbindungen -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider">Verbindungen</h2>
          <button class="pim-btn pim-btn-primary" @click="showConnectForm = true">
            <Plus class="w-4 h-4" />
            Verbinden
          </button>
        </div>

        <div v-if="connections.length === 0" class="text-center py-6 text-[var(--color-text-tertiary)] text-sm">
          Noch keine DeepL-Verbindung hergestellt.
        </div>

        <div v-for="conn in connections" :key="conn.id"
          class="pim-card p-4 flex items-center justify-between cursor-pointer hover:border-[var(--color-accent)]/30 transition-colors"
          @click="router.push(`/connectors/${conn.id}`)">
          <div>
            <span class="font-semibold text-sm">{{ conn.name }}</span>
            <span class="text-xs text-[var(--color-text-tertiary)] ml-2">{{ conn.connected_by?.name }}</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-[var(--color-success)] flex items-center gap-1 text-sm">
              <CheckCircle class="w-4 h-4" />
              Aktiv
            </span>
            <button class="pim-btn pim-btn-secondary text-[var(--color-error)]" @click.stop="deleteConnection(conn.id)">
              <Trash2 class="w-4 h-4" />
            </button>
            <ArrowUpRight class="w-4 h-4 text-[var(--color-text-tertiary)]" />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
