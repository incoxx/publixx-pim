<script setup>
import { ref, onMounted } from 'vue'
import {
  Key, Plus, Trash2, RefreshCw, Copy, CheckCircle, XCircle, Eye, EyeOff,
  Shield, Clock, ArrowLeft, RotateCcw,
} from 'lucide-vue-next'
import pimSyncApi from '@/api/pimSync'

const clients = ref([])
const loading = ref(false)
const error = ref('')
const successMsg = ref('')

// Create Form
const showCreateForm = ref(false)
const formData = ref({ name: '', scopes: [], rate_limit: 600, expires_at: '' })
const creating = ref(false)

// Newly created secret (shown once)
const newSecret = ref(null)
const showSecret = ref(false)

// Regenerate
const regenerating = ref({})
const regeneratedSecret = ref(null)

const availableScopes = [
  { value: 'products:read', label: 'Produkte lesen' },
  { value: 'products:write', label: 'Produkte schreiben' },
  { value: 'media:read', label: 'Media lesen' },
  { value: 'media:write', label: 'Media schreiben' },
  { value: 'categories:read', label: 'Kategorien lesen' },
  { value: 'categories:write', label: 'Kategorien schreiben' },
  { value: 'prices:read', label: 'Preise lesen' },
  { value: 'prices:write', label: 'Preise schreiben' },
  { value: 'attributes:read', label: 'Attribute lesen' },
  { value: 'attributes:write', label: 'Attribute schreiben' },
]

onMounted(loadClients)

async function loadClients() {
  loading.value = true
  error.value = ''
  try {
    const res = await pimSyncApi.listClients()
    clients.value = res.data.data || []
  } catch (e) {
    error.value = 'Fehler beim Laden der API-Clients'
  } finally {
    loading.value = false
  }
}

async function createClient() {
  creating.value = true
  error.value = ''
  try {
    const data = {
      name: formData.value.name,
      scopes: formData.value.scopes.length > 0 ? formData.value.scopes : undefined,
      rate_limit: formData.value.rate_limit || 600,
      expires_at: formData.value.expires_at || undefined,
    }
    const res = await pimSyncApi.createClient(data)
    const created = res.data.data
    newSecret.value = {
      name: created.name,
      client_id: created.client_id,
      client_secret: created.client_secret,
    }
    showCreateForm.value = false
    formData.value = { name: '', scopes: [], rate_limit: 600, expires_at: '' }
    await loadClients()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Erstellen'
  } finally {
    creating.value = false
  }
}

async function deleteClient(id) {
  if (!confirm('API-Client wirklich löschen? Alle bestehenden Verbindungen werden ungültig.')) return
  try {
    await pimSyncApi.deleteClient(id)
    clients.value = clients.value.filter(c => c.id !== id)
    successMsg.value = 'API-Client gelöscht'
    setTimeout(() => successMsg.value = '', 3000)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

async function toggleActive(client) {
  try {
    await pimSyncApi.updateClient(client.id, { is_active: !client.is_active })
    client.is_active = !client.is_active
  } catch (e) {
    error.value = 'Fehler beim Aktualisieren'
  }
}

async function regenerateSecret(client) {
  if (!confirm(`Secret für "${client.name}" neu generieren? Das alte Secret wird sofort ungültig.`)) return
  regenerating.value = { ...regenerating.value, [client.id]: true }
  try {
    const res = await pimSyncApi.regenerateSecret(client.id)
    regeneratedSecret.value = {
      name: client.name,
      client_id: res.data.data.client_id,
      client_secret: res.data.data.client_secret,
    }
  } catch (e) {
    error.value = 'Fehler beim Regenerieren'
  } finally {
    regenerating.value = { ...regenerating.value, [client.id]: false }
  }
}

function toggleScope(scope) {
  const idx = formData.value.scopes.indexOf(scope)
  if (idx >= 0) {
    formData.value.scopes.splice(idx, 1)
  } else {
    formData.value.scopes.push(scope)
  }
}

function selectAllScopes() {
  formData.value.scopes = availableScopes.map(s => s.value)
}

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    successMsg.value = 'In Zwischenablage kopiert'
    setTimeout(() => successMsg.value = '', 2000)
  } catch {
    // Fallback
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Key class="w-6 h-6 text-[var(--color-accent)]" />
        <div>
          <h1 class="text-xl font-bold text-[var(--color-text-primary)]">API-Clients</h1>
          <p class="text-sm text-[var(--color-text-secondary)]">Machine-to-Machine Credentials für PimSync</p>
        </div>
      </div>
      <div class="flex gap-2">
        <router-link to="/connectors/anypim" class="pim-btn pim-btn-ghost text-xs">
          <ArrowLeft class="w-4 h-4 mr-1" />anyPIM Sync
        </router-link>
        <button class="pim-btn pim-btn-ghost text-xs" @click="loadClients">
          <RefreshCw class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Success -->
    <div v-if="successMsg" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">
      <CheckCircle class="w-4 h-4 shrink-0" />{{ successMsg }}
    </div>

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error)]/10 text-[var(--color-error)] text-sm">
      <XCircle class="w-4 h-4 shrink-0" />{{ error }}
      <button class="ml-auto text-xs underline" @click="error = ''">Schließen</button>
    </div>

    <!-- Newly Created Secret (shown ONCE) -->
    <div v-if="newSecret" class="rounded-xl border-2 border-[var(--color-warning,theme(colors.amber.500))] bg-[var(--color-bg)] p-5 space-y-3">
      <div class="flex items-center gap-2 text-[var(--color-warning,theme(colors.amber.500))]">
        <Shield class="w-5 h-5" />
        <h3 class="font-bold">Client Secret — nur jetzt sichtbar!</h3>
      </div>
      <p class="text-xs text-[var(--color-text-secondary)]">
        Kopiere diese Daten jetzt. Das Secret wird nach dem Schließen <strong>nie wieder</strong> angezeigt.
      </p>
      <div class="space-y-2 font-mono text-xs bg-[var(--color-bg-elevated)] p-3 rounded-lg">
        <div class="flex items-center justify-between">
          <span class="text-[var(--color-text-tertiary)]">Name:</span>
          <span class="text-[var(--color-text-primary)]">{{ newSecret.name }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-[var(--color-text-tertiary)]">Client ID:</span>
          <div class="flex items-center gap-1">
            <span class="text-[var(--color-text-primary)]">{{ newSecret.client_id }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)]" @click="copyToClipboard(newSecret.client_id)"><Copy class="w-3 h-3" /></button>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-[var(--color-text-tertiary)]">Client Secret:</span>
          <div class="flex items-center gap-1">
            <span class="text-[var(--color-text-primary)]">{{ showSecret ? newSecret.client_secret : '••••••••••••••••' }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)]" @click="showSecret = !showSecret">
              <Eye v-if="!showSecret" class="w-3 h-3" /><EyeOff v-else class="w-3 h-3" />
            </button>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)]" @click="copyToClipboard(newSecret.client_secret)"><Copy class="w-3 h-3" /></button>
          </div>
        </div>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="newSecret = null; showSecret = false">Verstanden, schließen</button>
    </div>

    <!-- Regenerated Secret (shown ONCE) -->
    <div v-if="regeneratedSecret" class="rounded-xl border-2 border-[var(--color-warning,theme(colors.amber.500))] bg-[var(--color-bg)] p-5 space-y-3">
      <div class="flex items-center gap-2 text-[var(--color-warning,theme(colors.amber.500))]">
        <Shield class="w-5 h-5" />
        <h3 class="font-bold">Neues Secret für "{{ regeneratedSecret.name }}"</h3>
      </div>
      <div class="space-y-2 font-mono text-xs bg-[var(--color-bg-elevated)] p-3 rounded-lg">
        <div class="flex items-center justify-between">
          <span class="text-[var(--color-text-tertiary)]">Client ID:</span>
          <div class="flex items-center gap-1">
            <span>{{ regeneratedSecret.client_id }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)]" @click="copyToClipboard(regeneratedSecret.client_id)"><Copy class="w-3 h-3" /></button>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-[var(--color-text-tertiary)]">Neues Secret:</span>
          <div class="flex items-center gap-1">
            <span>{{ regeneratedSecret.client_secret }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)]" @click="copyToClipboard(regeneratedSecret.client_secret)"><Copy class="w-3 h-3" /></button>
          </div>
        </div>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="regeneratedSecret = null">Verstanden, schließen</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-6 h-6 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-if="!loading">
      <!-- Create Form -->
      <div v-if="showCreateForm" class="rounded-xl border border-[var(--color-accent)]/30 bg-[var(--color-bg)] p-5 space-y-4">
        <h3 class="font-semibold text-[var(--color-text-primary)]">Neuer API-Client</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
            <input v-model="formData.name" type="text" class="pim-input text-xs w-full" placeholder="z.B. Zentrale Deutschland" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Rate Limit (Req/Min)</label>
            <input v-model.number="formData.rate_limit" type="number" class="pim-input text-xs w-full" min="1" max="10000" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Ablaufdatum (optional)</label>
            <input v-model="formData.expires_at" type="date" class="pim-input text-xs w-full" />
          </div>
        </div>

        <!-- Scopes -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-xs font-medium text-[var(--color-text-secondary)]">Berechtigungen</label>
            <button class="text-[10px] text-[var(--color-accent)] hover:underline" @click="selectAllScopes">Alle auswählen</button>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="scope in availableScopes"
              :key="scope.value"
              class="px-2.5 py-1 rounded-lg text-[11px] font-medium transition-colors border"
              :class="formData.scopes.includes(scope.value)
                ? 'bg-[var(--color-accent)] text-white border-[var(--color-accent)]'
                : 'bg-[var(--color-bg-elevated)] text-[var(--color-text-secondary)] border-[var(--color-border)] hover:border-[var(--color-accent)]/30'"
              @click="toggleScope(scope.value)"
            >
              {{ scope.label }}
            </button>
          </div>
          <p v-if="formData.scopes.length === 0" class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
            Ohne Auswahl werden alle Berechtigungen vergeben.
          </p>
        </div>

        <div class="flex gap-2 justify-end">
          <button class="pim-btn pim-btn-ghost text-xs" @click="showCreateForm = false">Abbrechen</button>
          <button class="pim-btn pim-btn-primary text-xs" :disabled="creating || !formData.name" @click="createClient">
            <span v-if="creating" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></span>
            Erstellen
          </button>
        </div>
      </div>

      <!-- Client List -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider">API-Clients</h2>
          <button class="pim-btn pim-btn-primary text-xs" @click="showCreateForm = true">
            <Plus class="w-4 h-4 mr-1" />Neuer Client
          </button>
        </div>

        <div v-if="clients.length === 0 && !showCreateForm" class="text-center py-6 text-[var(--color-text-tertiary)] text-sm">
          Noch keine API-Clients erstellt.
        </div>

        <div v-for="client in clients" :key="client.id" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Key class="w-4 h-4 text-[var(--color-accent)]" />
              <span class="font-semibold text-sm text-[var(--color-text-primary)]">{{ client.name }}</span>
              <span
                class="text-[10px] px-2 py-0.5 rounded-full"
                :class="client.is_active ? 'bg-[var(--color-success)]/10 text-[var(--color-success)]' : 'bg-[var(--color-error)]/10 text-[var(--color-error)]'"
              >{{ client.is_active ? 'Aktiv' : 'Deaktiviert' }}</span>
            </div>
            <div class="flex items-center gap-1">
              <button
                class="pim-btn pim-btn-ghost text-[10px]"
                @click="toggleActive(client)"
              >{{ client.is_active ? 'Deaktivieren' : 'Aktivieren' }}</button>
              <button
                class="pim-btn pim-btn-ghost text-[10px]"
                :disabled="regenerating[client.id]"
                @click="regenerateSecret(client)"
              >
                <RotateCcw class="w-3 h-3 mr-0.5" />Secret
              </button>
              <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-error)]" @click="deleteClient(client.id)">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-[var(--color-text-tertiary)]">
            <span class="font-mono">ID: {{ client.client_id }}</span>
            <span v-if="client.last_used_at" class="flex items-center gap-1">
              <Clock class="w-3 h-3" />Zuletzt: {{ new Date(client.last_used_at).toLocaleString('de') }}
            </span>
            <span v-if="client.expires_at" class="flex items-center gap-1">
              Ablauf: {{ new Date(client.expires_at).toLocaleDateString('de') }}
            </span>
            <span>{{ client.rate_limit }} Req/Min</span>
          </div>

          <div class="flex flex-wrap gap-1">
            <span
              v-for="scope in (client.scopes || [])"
              :key="scope"
              class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg-elevated)] text-[var(--color-text-tertiary)]"
            >{{ scope }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
