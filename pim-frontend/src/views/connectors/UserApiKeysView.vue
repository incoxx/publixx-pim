<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Key, Plus, Trash2, RefreshCw, Copy, CheckCircle, XCircle, Eye, EyeOff,
  Shield, Clock, ArrowLeft, Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import pimSyncApi from '@/api/pimSync'

const authStore = useAuthStore()
const router = useRouter()
const isAdmin = computed(() => authStore.user?.roles?.some(r => r.name === 'Admin'))

// Tabs: eigene Keys vs. alle Keys (Admin)
const activeTab = ref('own')
const ownKeys = ref([])
const allKeys = ref([])
const loading = ref(false)
const error = ref('')
const successMsg = ref('')

// Create Form
const showCreateForm = ref(false)
const formData = ref({ name: '', description: '', expires_at: '' })
const creating = ref(false)

// Newly created key (shown once)
const newKey = ref(null)
const showToken = ref(false)

onMounted(loadKeys)

async function loadKeys() {
  loading.value = true
  error.value = ''
  try {
    const res = await pimSyncApi.listApiKeys()
    ownKeys.value = res.data.data || []

    if (isAdmin.value) {
      const adminRes = await pimSyncApi.adminListApiKeys()
      allKeys.value = adminRes.data.data || []
    }
  } catch (e) {
    error.value = 'Fehler beim Laden der API-Keys'
  } finally {
    loading.value = false
  }
}

async function createKey() {
  creating.value = true
  error.value = ''
  try {
    const data = {
      name: formData.value.name,
      description: formData.value.description || undefined,
      expires_at: formData.value.expires_at || undefined,
    }
    const res = await pimSyncApi.createApiKey(data)
    const created = res.data.data
    newKey.value = {
      name: created.name,
      token: created.token,
    }
    showCreateForm.value = false
    formData.value = { name: '', description: '', expires_at: '' }
    await loadKeys()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Erstellen'
  } finally {
    creating.value = false
  }
}

async function deleteKey(tokenId, isAdminAction = false) {
  if (!confirm('API-Key wirklich widerrufen? Alle Systeme die diesen Key nutzen verlieren sofort den Zugriff.')) return
  try {
    if (isAdminAction) {
      await pimSyncApi.adminDeleteApiKey(tokenId)
      allKeys.value = allKeys.value.filter(k => k.id !== tokenId)
    } else {
      await pimSyncApi.deleteApiKey(tokenId)
      ownKeys.value = ownKeys.value.filter(k => k.id !== tokenId)
    }
    successMsg.value = 'API-Key widerrufen'
    setTimeout(() => successMsg.value = '', 3000)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    successMsg.value = 'In Zwischenablage kopiert'
    setTimeout(() => successMsg.value = '', 2000)
  } catch { /* fallback */ }
}

const displayKeys = computed(() => activeTab.value === 'all' ? allKeys.value : ownKeys.value)
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Key class="w-6 h-6 text-[var(--color-accent)]" />
        <div>
          <h1 class="text-xl font-bold text-[var(--color-text-primary)]">API-Keys</h1>
          <p class="text-sm text-[var(--color-text-secondary)]">Langlebige API-Schlüssel für externe Integrationen</p>
        </div>
      </div>
      <button class="pim-btn pim-btn-ghost text-xs" @click="loadKeys">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <!-- Info -->
    <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4 text-xs text-[var(--color-text-secondary)] leading-relaxed">
      API-Keys sind an deinen Benutzer gebunden und erben deine Berechtigungen (Rolle).
      Nutze sie für externe Integrationen, den API-Tester, anyPIM-Sync oder eigene Skripte.
      Der Key wird nur einmal angezeigt — sicher aufbewahren.
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

    <!-- Newly Created Key (shown ONCE) -->
    <div v-if="newKey" class="rounded-xl border-2 border-[var(--color-warning,theme(colors.amber.500))] bg-[var(--color-bg)] p-5 space-y-3">
      <div class="flex items-center gap-2 text-[var(--color-warning,theme(colors.amber.500))]">
        <Shield class="w-5 h-5" />
        <h3 class="font-bold">API-Key erstellt — nur jetzt sichtbar!</h3>
      </div>
      <p class="text-xs text-[var(--color-text-secondary)]">
        Kopiere den Key jetzt. Er wird nach dem Schließen <strong>nie wieder</strong> angezeigt.
      </p>
      <div class="font-mono text-xs bg-[var(--color-bg-elevated)] p-3 rounded-lg space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-[var(--color-text-tertiary)]">Name:</span>
          <span class="text-[var(--color-text-primary)]">{{ newKey.name }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="text-[var(--color-text-tertiary)] shrink-0">API-Key:</span>
          <div class="flex items-center gap-1 min-w-0">
            <span class="text-[var(--color-text-primary)] truncate">{{ showToken ? newKey.token : '••••••••••••••••••••••••' }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)] shrink-0" @click="showToken = !showToken">
              <Eye v-if="!showToken" class="w-3 h-3" /><EyeOff v-else class="w-3 h-3" />
            </button>
            <button class="p-0.5 rounded hover:bg-[var(--color-bg-hover)] shrink-0" @click="copyToClipboard(newKey.token)">
              <Copy class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="newKey = null; showToken = false">Verstanden, schließen</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-6 h-6 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-if="!loading">
      <!-- Tabs (Admin) -->
      <div v-if="isAdmin" class="flex gap-2">
        <button
          class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
          :class="activeTab === 'own' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-bg-elevated)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
          @click="activeTab = 'own'"
        >Meine Keys ({{ ownKeys.length }})</button>
        <button
          class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
          :class="activeTab === 'all' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-bg-elevated)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
          @click="activeTab = 'all'"
        >
          <Users class="w-3 h-3 mr-1 inline" />Alle Keys ({{ allKeys.length }})
        </button>
      </div>

      <!-- Create Form -->
      <div v-if="showCreateForm" class="rounded-xl border border-[var(--color-accent)]/30 bg-[var(--color-bg)] p-5 space-y-4">
        <h3 class="font-semibold text-[var(--color-text-primary)]">Neuer API-Key</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
            <input v-model="formData.name" type="text" class="pim-input text-xs w-full" placeholder="z.B. ERP Integration" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Beschreibung (optional)</label>
            <input v-model="formData.description" type="text" class="pim-input text-xs w-full" placeholder="z.B. Für SAP-Anbindung" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Ablaufdatum (optional)</label>
            <input v-model="formData.expires_at" type="date" class="pim-input text-xs w-full" />
          </div>
        </div>
        <p class="text-xs text-[var(--color-text-tertiary)]">
          Der API-Key erbt die Berechtigungen deiner aktuellen Rolle. Für eingeschränkte Zugriffe erstelle einen eigenen Benutzer mit einer passenden Rolle.
        </p>
        <div class="flex gap-2 justify-end">
          <button class="pim-btn pim-btn-ghost text-xs" @click="showCreateForm = false">Abbrechen</button>
          <button class="pim-btn pim-btn-primary text-xs" :disabled="creating || !formData.name" @click="createKey">
            <span v-if="creating" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></span>
            Key erstellen
          </button>
        </div>
      </div>

      <!-- Key List -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider">
            {{ activeTab === 'all' ? 'Alle API-Keys' : 'Meine API-Keys' }}
          </h2>
          <button v-if="activeTab === 'own'" class="pim-btn pim-btn-primary text-xs" @click="showCreateForm = true">
            <Plus class="w-4 h-4 mr-1" />Neuer Key
          </button>
        </div>

        <div v-if="displayKeys.length === 0 && !showCreateForm" class="text-center py-6 text-[var(--color-text-tertiary)] text-sm">
          Noch keine API-Keys erstellt.
        </div>

        <div v-for="key in displayKeys" :key="key.id" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4 flex items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <Key class="w-3.5 h-3.5 text-[var(--color-accent)] shrink-0" />
              <span class="font-semibold text-sm text-[var(--color-text-primary)]">{{ key.name }}</span>
              <span v-if="key.user_name && activeTab === 'all'" class="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-bg-elevated)] text-[var(--color-text-tertiary)]">
                {{ key.user_name }}
              </span>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-1 text-[11px] text-[var(--color-text-tertiary)]">
              <span v-if="key.description">{{ key.description }}</span>
              <span v-if="key.last_used_at" class="flex items-center gap-0.5">
                <Clock class="w-3 h-3" />Zuletzt: {{ new Date(key.last_used_at).toLocaleString('de') }}
              </span>
              <span v-if="key.expires_at">Ablauf: {{ new Date(key.expires_at).toLocaleDateString('de') }}</span>
              <span>Erstellt: {{ new Date(key.created_at).toLocaleDateString('de') }}</span>
            </div>
          </div>
          <button
            class="p-1.5 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-error)] shrink-0"
            @click="deleteKey(key.id, activeTab === 'all')"
            title="Key widerrufen"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
