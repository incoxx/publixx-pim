<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  RefreshCw, Plus, Trash2, CheckCircle, XCircle, ArrowUpRight, Settings,
  ArrowDownUp, ArrowUp, ArrowDown, Zap, TestTube, Loader2, Key, Globe,
  SlidersHorizontal, StopCircle, ImageOff,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'
import pimSyncApi from '@/api/pimSync'

const router = useRouter()
const connections = ref([])
const loading = ref(false)
const error = ref('')
const successMsg = ref('')

// Connect Form
const showConnectForm = ref(false)
const formData = ref({
  name: '',
  remote_url: '',
  api_key: '',
  sync_direction: 'bidirectional',
  conflict_resolution: 'newer_wins',
  media_transfer_mode: 'reference',
})
const connecting = ref(false)
const testResult = ref(null)

// Sync State
const syncing = ref({})

// Konfigurations-Sync ("Maske" pro Verbindung)
const availableConfigSections = ref([])
const openConfigForm = ref(null) // connId oder null
const selectedConfigSections = ref([])
const configPulling = ref({})
const configProgress = ref({})
const configProgressLog = ref({})
const configCancelling = ref({})
const configResult = ref({})

// Fehlende-Medien-Sync ("Maske" pro Verbindung)
const openMediaForm = ref(null) // connId oder null
const missingMediaCounts = ref({})
const missingMediaCountLoading = ref({})
const mediaPulling = ref({})
const mediaProgress = ref({})
const mediaProgressLog = ref({})
const mediaCancelling = ref({})
const mediaResult = ref({})

const canConnect = computed(() =>
  formData.value.name && formData.value.remote_url && formData.value.api_key
)

onMounted(() => {
  loadData()
  loadConfigSections()
})

async function loadConfigSections() {
  try {
    const res = await pimSyncApi.configSections()
    availableConfigSections.value = res.data.data || res.data
  } catch (e) { /* ignore — Formular zeigt dann nur den Standard-Button */ }
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const res = await connectorsApi.connections()
    const allConns = res.data.data || res.data
    connections.value = allConns.filter(c => c.connector_type === 'anypim')
  } catch (e) {
    error.value = 'Fehler beim Laden der Verbindungen'
  } finally {
    loading.value = false
  }
}

async function createConnection() {
  if (!canConnect.value) return
  connecting.value = true
  error.value = ''
  try {
    await connectorsApi.callback('anypim', {
      code: formData.value.api_key, // API-Key = Bearer Token
      name: formData.value.name,
      settings: {
        remote_url: formData.value.remote_url,
        sync_direction: formData.value.sync_direction,
        conflict_resolution: formData.value.conflict_resolution,
        media_transfer_mode: formData.value.media_transfer_mode,
      },
    })
    showConnectForm.value = false
    formData.value = { name: '', remote_url: '', api_key: '', sync_direction: 'bidirectional', conflict_resolution: 'newer_wins', media_transfer_mode: 'reference' }
    testResult.value = null
    await loadData()
    successMsg.value = 'Verbindung erfolgreich erstellt'
    setTimeout(() => successMsg.value = '', 4000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Verbindung fehlgeschlagen'
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

async function runSync(connId, action) {
  syncing.value = { ...syncing.value, [connId]: action }
  error.value = ''
  successMsg.value = ''
  try {
    let res
    if (action === 'push') {
      res = await connectorsApi.syncFromProfile(connId)
    } else if (action === 'pull') {
      res = await pimSyncApi.pullProducts(connId, { delta: true })
    } else if (action === 'pull-translations') {
      res = await pimSyncApi.pullTranslations(connId)
    } else if (action === 'bidirectional') {
      res = await pimSyncApi.syncBidirectional(connId)
    } else if (action === 'delta-push') {
      res = await connectorsApi.deltaSync(connId)
    } else if (action === 'test') {
      res = await pimSyncApi.testConnection(connId)
    }
    const data = res?.data?.data || res?.data
    if (action === 'test') {
      successMsg.value = data?.status === 'ok' ? 'Verbindung OK' : `Fehler: ${data?.message}`
    } else {
      successMsg.value = formatSyncResult(data)
    }
    setTimeout(() => successMsg.value = '', 8000)
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'Sync fehlgeschlagen'
  } finally {
    syncing.value = { ...syncing.value, [connId]: null }
  }
}

function formatSyncResult(data) {
  if (!data) return 'Sync abgeschlossen'
  if (data.push && data.pull) {
    return `Push: ${data.push.pushed ?? 0} gesendet, Pull: ${data.pull.pulled ?? 0} empfangen`
  }
  if (data.pushed !== undefined) return `${data.pushed} Produkte gesendet, ${data.skipped ?? 0} übersprungen`
  if (data.pulled !== undefined) return `${data.pulled} Produkte empfangen, ${data.skipped ?? 0} übersprungen`
  if (data.stats) return `${data.stats.created ?? 0} erstellt, ${data.stats.updated ?? 0} aktualisiert`
  return 'Sync abgeschlossen'
}

function directionLabel(dir) {
  return { push: 'Push', pull: 'Pull', bidirectional: 'Bidirektional' }[dir] || dir
}

function conflictLabel(cr) {
  return { remote_wins: 'Remote gewinnt', local_wins: 'Lokal gewinnt', newer_wins: 'Neuer gewinnt' }[cr] || cr
}

// --- Konfigurations-Sync ("Maske" pro Verbindung) ---

function toggleConfigForm(connId) {
  if (openConfigForm.value === connId) {
    openConfigForm.value = null
    return
  }
  openConfigForm.value = connId
  selectedConfigSections.value = [...availableConfigSections.value]
  configResult.value = { ...configResult.value, [connId]: null }
}

function toggleConfigSection(section) {
  const idx = selectedConfigSections.value.indexOf(section)
  if (idx >= 0) {
    selectedConfigSections.value.splice(idx, 1)
  } else {
    selectedConfigSections.value.push(section)
  }
}

async function runConfigPull(connId) {
  configPulling.value = { ...configPulling.value, [connId]: true }
  configCancelling.value = { ...configCancelling.value, [connId]: false }
  configResult.value = { ...configResult.value, [connId]: null }
  configProgressLog.value = { ...configProgressLog.value, [connId]: [] }
  configProgress.value = {
    ...configProgress.value,
    [connId]: { phase: 'starting', message: 'Konfigurations-Pull wird gestartet...', current: 0, total: 0, percent: 0 },
  }
  error.value = ''
  try {
    const result = await pimSyncApi.pullConfigWithProgress(
      connId,
      selectedConfigSections.value,
      (progress) => {
        const entry = {
          ...progress,
          percent: progress.total > 0 ? Math.round((progress.current / progress.total) * 100) : 0,
          at: new Date(),
        }
        configProgress.value = { ...configProgress.value, [connId]: entry }
        configProgressLog.value = {
          ...configProgressLog.value,
          [connId]: [...(configProgressLog.value[connId] || []), entry],
        }
      },
    )
    configResult.value = { ...configResult.value, [connId]: result }
    configProgress.value = { ...configProgress.value, [connId]: null }
  } catch (e) {
    error.value = e.message || 'Konfigurations-Pull fehlgeschlagen'
    configProgress.value = { ...configProgress.value, [connId]: null }
  } finally {
    configPulling.value = { ...configPulling.value, [connId]: false }
    configCancelling.value = { ...configCancelling.value, [connId]: false }
  }
}

async function cancelConfigPull(connId) {
  const importId = configProgress.value[connId]?.import_id
  if (!importId || configCancelling.value[connId]) return
  configCancelling.value = { ...configCancelling.value, [connId]: true }
  try {
    await pimSyncApi.cancelConfigPull(importId)
    configProgress.value = {
      ...configProgress.value,
      [connId]: { ...configProgress.value[connId], message: 'Wird abgebrochen...' },
    }
  } catch (e) {
    configCancelling.value = { ...configCancelling.value, [connId]: false }
  }
}

function configSectionLabel(s) {
  return {
    unit_groups: 'Einheitengruppen',
    units: 'Einheiten',
    attribute_views: 'Attributsichten',
    attribute_groups: 'Attributgruppen',
    attribute_formatting_rules: 'Formatierungsregeln',
    comparison_operator_groups: 'Vergleichsoperator-Gruppen',
    comparison_operators: 'Vergleichsoperatoren',
    value_lists: 'Wertelisten',
    attributes: 'Attribute',
    product_types: 'Produkttypen',
    price_types: 'Preisarten',
    price_regions: 'Preisregionen',
    relation_types: 'Beziehungstypen',
    manufacturers: 'Hersteller',
    media_languages: 'Medien-Sprachen',
    media_countries: 'Medien-Länder',
    media_usage_types: 'Medien-Verwendungstypen',
    dictionary_entries: 'Wörterbuch',
    collection_types: 'Collection-Typen',
    hierarchies: 'Hierarchien',
    hierarchy_attribute_assignments: 'Hierarchie-Attribute',
    hierarchy_level_attribute_assignments: 'Hierarchie-Ebene-Attribute',
    product_reference_profiles: 'Referenz-Profile',
  }[s] || s
}

// --- Fehlende-Medien-Sync ("Maske" pro Verbindung) ---

async function toggleMediaForm(connId) {
  if (openMediaForm.value === connId) {
    openMediaForm.value = null
    return
  }
  openMediaForm.value = connId
  mediaResult.value = { ...mediaResult.value, [connId]: null }
  missingMediaCountLoading.value = { ...missingMediaCountLoading.value, [connId]: true }
  try {
    const res = await pimSyncApi.missingMediaCount(connId)
    const data = res.data.data || res.data
    missingMediaCounts.value = { ...missingMediaCounts.value, [connId]: data.missing }
  } catch (e) {
    missingMediaCounts.value = { ...missingMediaCounts.value, [connId]: null }
  } finally {
    missingMediaCountLoading.value = { ...missingMediaCountLoading.value, [connId]: false }
  }
}

async function runMediaPull(connId) {
  mediaPulling.value = { ...mediaPulling.value, [connId]: true }
  mediaCancelling.value = { ...mediaCancelling.value, [connId]: false }
  mediaResult.value = { ...mediaResult.value, [connId]: null }
  mediaProgressLog.value = { ...mediaProgressLog.value, [connId]: [] }
  mediaProgress.value = {
    ...mediaProgress.value,
    [connId]: { phase: 'starting', message: 'Fehlende Medien werden ermittelt...', current: 0, total: 0, percent: 0 },
  }
  error.value = ''
  try {
    const result = await pimSyncApi.pullMissingMediaWithProgress(
      connId,
      (progress) => {
        const entry = {
          ...progress,
          percent: progress.total > 0 ? Math.round((progress.current / progress.total) * 100) : 0,
          at: new Date(),
        }
        mediaProgress.value = { ...mediaProgress.value, [connId]: entry }
        mediaProgressLog.value = {
          ...mediaProgressLog.value,
          [connId]: [...(mediaProgressLog.value[connId] || []), entry],
        }
      },
    )
    mediaResult.value = { ...mediaResult.value, [connId]: result }
    missingMediaCounts.value = { ...missingMediaCounts.value, [connId]: result.missing - result.downloaded }
    mediaProgress.value = { ...mediaProgress.value, [connId]: null }
  } catch (e) {
    error.value = e.message || 'Media-Pull fehlgeschlagen'
    mediaProgress.value = { ...mediaProgress.value, [connId]: null }
  } finally {
    mediaPulling.value = { ...mediaPulling.value, [connId]: false }
    mediaCancelling.value = { ...mediaCancelling.value, [connId]: false }
  }
}

async function cancelMediaPull(connId) {
  const importId = mediaProgress.value[connId]?.import_id
  if (!importId || mediaCancelling.value[connId]) return
  mediaCancelling.value = { ...mediaCancelling.value, [connId]: true }
  try {
    await pimSyncApi.cancelMissingMediaPull(importId)
    mediaProgress.value = {
      ...mediaProgress.value,
      [connId]: { ...mediaProgress.value[connId], message: 'Wird abgebrochen...' },
    }
  } catch (e) {
    mediaCancelling.value = { ...mediaCancelling.value, [connId]: false }
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <ArrowDownUp class="w-6 h-6 text-[var(--color-accent)]" />
        <div>
          <h1 class="text-xl font-bold text-[var(--color-text-primary)]">anyPIM Sync</h1>
          <p class="text-sm text-[var(--color-text-secondary)]">Bidirektionaler Sync zwischen anyPIM-Instanzen</p>
        </div>
      </div>
      <button class="pim-btn pim-btn-ghost text-xs" @click="loadData">
        <RefreshCw class="w-4 h-4" />
      </button>
    </div>

    <!-- Success -->
    <div v-if="successMsg" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">
      <CheckCircle class="w-4 h-4 shrink-0" />
      {{ successMsg }}
    </div>

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error)]/10 text-[var(--color-error)] text-sm">
      <XCircle class="w-4 h-4 shrink-0" />
      {{ error }}
      <button class="ml-auto text-xs underline" @click="error = ''">Schließen</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-6 h-6 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-if="!loading">
      <!-- Info -->
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-5">
        <div class="flex items-center gap-2 mb-3">
          <Settings class="w-4 h-4 text-[var(--color-accent)]" />
          <h2 class="font-semibold text-[var(--color-text-primary)]">Einrichtung</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Authentifizierung</div>
            <div class="text-sm text-[var(--color-text-primary)] mt-1">User-API-Key (Bearer Token)</div>
          </div>
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Funktionen</div>
            <div class="flex flex-wrap gap-1 mt-1">
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-accent)]/10 text-[var(--color-accent)]">
                <ArrowUp class="w-3 h-3 mr-0.5 inline" />Push
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-accent)]/10 text-[var(--color-accent)]">
                <ArrowDown class="w-3 h-3 mr-0.5 inline" />Pull
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-accent)]/10 text-[var(--color-accent)]">
                <Zap class="w-3 h-3 mr-0.5 inline" />Delta-Sync
              </span>
            </div>
          </div>
          <div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Sync-Daten</div>
            <div class="text-sm text-[var(--color-text-primary)] mt-1">Produkte, Attribute, Preise, Media</div>
          </div>
        </div>
        <p class="text-xs text-[var(--color-text-tertiary)] mt-3 leading-relaxed">
          Erstelle auf der Remote-Instanz unter <strong>Connectoren &gt; API-Keys</strong> einen neuen API-Key.
          Verwende den <strong>API-Key als Bearer Token</strong> für die Verbindung. Die Berechtigungen werden durch die Rolle des Benutzers bestimmt.
        </p>
      </div>

      <!-- Connect Form -->
      <div v-if="showConnectForm" class="rounded-xl border border-[var(--color-accent)]/30 bg-[var(--color-bg)] p-5 space-y-4">
        <h3 class="font-semibold text-[var(--color-text-primary)]">Neue anyPIM-Verbindung</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Verbindungsname</label>
            <input v-model="formData.name" type="text" class="pim-input text-xs w-full" placeholder="z.B. Tochter Frankreich" />
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Remote-URL</label>
            <input v-model="formData.remote_url" type="url" class="pim-input text-xs w-full" placeholder="https://pim-tochter.example.com" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">API-Key</label>
            <input v-model="formData.api_key" type="password" class="pim-input text-xs w-full" placeholder="Auf der Remote-Instanz unter API-Keys erstellen" />
            <p class="text-xs text-[var(--color-text-tertiary)] mt-1">
              Der API-Key wird auf der Remote-Instanz unter <strong>Connectoren &gt; API-Keys</strong> erstellt.
            </p>
          </div>
        </div>

        <!-- Sync-Optionen -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2 border-t border-[var(--color-border)]">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Sync-Richtung</label>
            <select v-model="formData.sync_direction" class="pim-input text-xs w-full">
              <option value="bidirectional">Bidirektional</option>
              <option value="push">Nur Push (senden)</option>
              <option value="pull">Nur Pull (empfangen)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Konfliktstrategie</label>
            <select v-model="formData.conflict_resolution" class="pim-input text-xs w-full">
              <option value="newer_wins">Neuer gewinnt</option>
              <option value="remote_wins">Remote gewinnt</option>
              <option value="local_wins">Lokal gewinnt</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Media-Transfer</label>
            <select v-model="formData.media_transfer_mode" class="pim-input text-xs w-full">
              <option value="reference">URL-Referenz (schnell)</option>
              <option value="direct">Direkte Übertragung</option>
            </select>
          </div>
        </div>

        <!-- Test Result -->
        <div v-if="testResult" class="p-2 rounded-lg text-xs" :class="testResult.status === 'ok' ? 'bg-[var(--color-success)]/10 text-[var(--color-success)]' : 'bg-[var(--color-error)]/10 text-[var(--color-error)]'">
          {{ testResult.message }}
        </div>

        <div class="flex gap-2 justify-end">
          <button class="pim-btn pim-btn-ghost text-xs" @click="showConnectForm = false; testResult = null">Abbrechen</button>
          <button class="pim-btn pim-btn-primary text-xs" :disabled="connecting || !canConnect" @click="createConnection">
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
            <Plus class="w-4 h-4 mr-1" />Verbinden
          </button>
        </div>

        <div v-if="connections.length === 0" class="text-center py-6 text-[var(--color-text-tertiary)] text-sm">
          Noch keine anyPIM-Verbindung hergestellt.
        </div>

        <!-- Connection Cards -->
        <div v-for="conn in connections" :key="conn.id" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4 space-y-3">
          <!-- Connection Header -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <Globe class="w-5 h-5 text-[var(--color-accent)]" />
              <div>
                <span class="font-semibold text-[var(--color-text-primary)]">{{ conn.name }}</span>
                <span v-if="conn.settings?.remote_url" class="text-xs text-[var(--color-text-tertiary)] ml-2">{{ conn.settings.remote_url }}</span>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <span
                :class="conn.is_active && !conn.token_expired ? 'text-[var(--color-success)]' : 'text-[var(--color-warning,theme(colors.amber.500))]'"
                class="flex items-center gap-1 text-sm"
              >
                <CheckCircle v-if="conn.is_active && !conn.token_expired" class="w-4 h-4" />
                <XCircle v-else class="w-4 h-4" />
                {{ conn.is_active && !conn.token_expired ? 'Aktiv' : 'Token erneuern' }}
              </span>
              <button class="p-1 rounded hover:bg-[var(--color-bg-hover)] text-[var(--color-error)]" @click.stop="deleteConnection(conn.id)">
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Connection Meta -->
          <div class="flex flex-wrap gap-3 text-xs text-[var(--color-text-tertiary)]">
            <span v-if="conn.settings?.sync_direction" class="flex items-center gap-1">
              <ArrowDownUp class="w-3 h-3" />{{ directionLabel(conn.settings.sync_direction) }}
            </span>
            <span v-if="conn.settings?.conflict_resolution" class="flex items-center gap-1">
              <Zap class="w-3 h-3" />{{ conflictLabel(conn.settings.conflict_resolution) }}
            </span>
          </div>

          <!-- Sync Actions -->
          <div class="flex flex-wrap gap-2 pt-2 border-t border-[var(--color-border)]">
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!!syncing[conn.id]"
              @click="runSync(conn.id, 'bidirectional')"
            >
              <Loader2 v-if="syncing[conn.id] === 'bidirectional'" class="w-3 h-3 mr-1 animate-spin" />
              <ArrowDownUp v-else class="w-3 h-3 mr-1" />
              Bidirektional
            </button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="!!syncing[conn.id]"
              @click="runSync(conn.id, 'delta-push')"
            >
              <Loader2 v-if="syncing[conn.id] === 'delta-push'" class="w-3 h-3 mr-1 animate-spin" />
              <ArrowUp v-else class="w-3 h-3 mr-1" />
              Delta Push
            </button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="!!syncing[conn.id]"
              @click="runSync(conn.id, 'pull')"
            >
              <Loader2 v-if="syncing[conn.id] === 'pull'" class="w-3 h-3 mr-1 animate-spin" />
              <ArrowDown v-else class="w-3 h-3 mr-1" />
              Pull
            </button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="!!syncing[conn.id]"
              @click="runSync(conn.id, 'pull-translations')"
            >
              <Loader2 v-if="syncing[conn.id] === 'pull-translations'" class="w-3 h-3 mr-1 animate-spin" />
              Übersetzungen holen
            </button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              :disabled="!!syncing[conn.id]"
              @click="runSync(conn.id, 'test')"
            >
              <Loader2 v-if="syncing[conn.id] === 'test'" class="w-3 h-3 mr-1 animate-spin" />
              <TestTube v-else class="w-3 h-3 mr-1" />
              Testen
            </button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              @click="toggleConfigForm(conn.id)"
            >
              <SlidersHorizontal class="w-3 h-3 mr-1" />
              Konfiguration synchronisieren
            </button>
            <button
              class="pim-btn pim-btn-ghost text-xs"
              @click="toggleMediaForm(conn.id)"
            >
              <ImageOff class="w-3 h-3 mr-1" />
              Fehlende Medien synchronisieren
            </button>

            <!-- Detail-View Link -->
            <button
              class="pim-btn pim-btn-ghost text-xs ml-auto"
              @click="router.push(`/connectors/${conn.id}`)"
            >
              Logs & Details
              <ArrowUpRight class="w-3 h-3 ml-1" />
            </button>
          </div>

          <!-- Konfigurations-Sync ("Maske") -->
          <div v-if="openConfigForm === conn.id" class="pt-3 border-t border-[var(--color-border)] space-y-3">
            <p class="text-xs text-[var(--color-text-tertiary)]">
              Holt das Datenmodell (Produkttypen, Attribute, Wertelisten, Hersteller, ...) von
              <strong>{{ conn.settings?.remote_url }}</strong> und legt es hier an bzw. aktualisiert es.
              Produktbestand (Artikel/Preise/Medien) ist nicht enthalten — dafür die Buttons oben nutzen.
            </p>

            <div v-if="!configPulling[conn.id]" class="flex flex-wrap gap-1.5">
              <label
                v-for="section in availableConfigSections"
                :key="section"
                class="flex items-center gap-1.5 text-[11px] px-2 py-1 rounded-full border cursor-pointer"
                :class="selectedConfigSections.includes(section)
                  ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
                  : 'border-[var(--color-border)] text-[var(--color-text-tertiary)]'"
              >
                <input
                  type="checkbox"
                  class="hidden"
                  :checked="selectedConfigSections.includes(section)"
                  @change="toggleConfigSection(section)"
                />
                {{ configSectionLabel(section) }}
              </label>
            </div>

            <div v-if="!configPulling[conn.id]" class="flex justify-end">
              <button
                class="pim-btn pim-btn-primary text-xs"
                :disabled="selectedConfigSections.length === 0"
                @click="runConfigPull(conn.id)"
              >
                <ArrowDown class="w-3.5 h-3.5 mr-1" />
                Konfiguration holen
              </button>
            </div>

            <!-- Progress -->
            <div v-if="configProgress[conn.id]" class="space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="text-[var(--color-text-secondary)] flex items-center gap-2">
                  <Loader2 v-if="!configCancelling[conn.id]" class="w-3.5 h-3.5 animate-spin text-[var(--color-accent)]" />
                  <StopCircle v-else class="w-3.5 h-3.5 text-orange-500" />
                  {{ configProgress[conn.id].message }}
                </span>
                <span v-if="configProgress[conn.id].current > 0" class="text-[var(--color-text-tertiary)]">
                  {{ configProgress[conn.id].current }}<template v-if="configProgress[conn.id].total > 0"> / {{ configProgress[conn.id].total }}</template>
                </span>
              </div>
              <div class="w-full bg-[var(--color-bg)] rounded-full h-2 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300 ease-out"
                  :class="[
                    configCancelling[conn.id] ? 'bg-orange-500' : 'bg-[var(--color-accent)]',
                    configProgress[conn.id].total > 0 ? '' : 'animate-pulse',
                  ]"
                  :style="{ width: configProgress[conn.id].total > 0 ? configProgress[conn.id].percent + '%' : '100%' }"
                />
              </div>
              <div v-if="configProgress[conn.id].import_id && !configCancelling[conn.id]" class="flex justify-end">
                <button
                  class="pim-btn pim-btn-secondary text-xs text-red-600 hover:text-red-700 border-red-200 hover:border-red-300"
                  @click="cancelConfigPull(conn.id)"
                >
                  <StopCircle class="w-3.5 h-3.5 mr-1" />
                  Abbrechen
                </button>
              </div>
            </div>

            <!-- Progress-Log -->
            <div v-if="configProgressLog[conn.id]?.length" class="max-h-40 overflow-y-auto space-y-1 font-mono text-[11px] rounded-lg bg-[var(--color-bg)] p-2">
              <div
                v-for="(entry, i) in configProgressLog[conn.id]"
                :key="i"
                class="flex items-start gap-2 text-[var(--color-text-secondary)]"
              >
                <span class="text-[var(--color-text-tertiary)] shrink-0">{{ entry.at.toLocaleTimeString() }}</span>
                <span class="truncate">{{ entry.message }}</span>
              </div>
            </div>

            <!-- Result -->
            <div v-if="configResult[conn.id]" class="flex items-start gap-2 text-xs text-[var(--color-success)]">
              <CheckCircle class="w-4 h-4 shrink-0" />
              <span>
                Konfiguration importiert.
                <template v-if="configResult[conn.id].stats">
                  {{ Object.values(configResult[conn.id].stats).reduce((s, v) => s + (v.created || 0), 0) }} neu,
                  {{ Object.values(configResult[conn.id].stats).reduce((s, v) => s + (v.updated || 0), 0) }} aktualisiert.
                </template>
              </span>
            </div>
          </div>

          <!-- Fehlende-Medien-Sync ("Maske") -->
          <div v-if="openMediaForm === conn.id" class="pt-3 border-t border-[var(--color-border)] space-y-3">
            <p class="text-xs text-[var(--color-text-tertiary)]">
              Lädt Dateien für Media-Einträge nach, die hier zwar als Datensatz existieren
              (z.B. durch einen JSON-Import), deren Datei aber physisch fehlt — Download über
              den öffentlichen <code>/storage/</code>-Pfad von <strong>{{ conn.settings?.remote_url }}</strong>.
            </p>

            <div v-if="!mediaPulling[conn.id]" class="flex items-center gap-3">
              <span v-if="missingMediaCountLoading[conn.id]" class="text-xs text-[var(--color-text-tertiary)] flex items-center gap-1.5">
                <Loader2 class="w-3.5 h-3.5 animate-spin" /> Prüfe fehlende Dateien...
              </span>
              <span v-else-if="missingMediaCounts[conn.id] === 0" class="text-xs text-[var(--color-success)] flex items-center gap-1.5">
                <CheckCircle class="w-3.5 h-3.5" /> Keine fehlenden Mediendateien.
              </span>
              <span v-else-if="missingMediaCounts[conn.id] > 0" class="text-xs text-[var(--color-text-secondary)]">
                {{ missingMediaCounts[conn.id] }} Mediendatei(en) fehlen lokal.
              </span>
              <button
                v-if="missingMediaCounts[conn.id] > 0"
                class="pim-btn pim-btn-primary text-xs ml-auto"
                @click="runMediaPull(conn.id)"
              >
                <ArrowDown class="w-3.5 h-3.5 mr-1" />
                Medien nachladen
              </button>
            </div>

            <!-- Progress -->
            <div v-if="mediaProgress[conn.id]" class="space-y-2">
              <div class="flex items-center justify-between text-xs">
                <span class="text-[var(--color-text-secondary)] flex items-center gap-2">
                  <Loader2 v-if="!mediaCancelling[conn.id]" class="w-3.5 h-3.5 animate-spin text-[var(--color-accent)]" />
                  <StopCircle v-else class="w-3.5 h-3.5 text-orange-500" />
                  {{ mediaProgress[conn.id].message }}
                </span>
                <span v-if="mediaProgress[conn.id].current > 0" class="text-[var(--color-text-tertiary)]">
                  {{ mediaProgress[conn.id].current }}<template v-if="mediaProgress[conn.id].total > 0"> / {{ mediaProgress[conn.id].total }}</template>
                </span>
              </div>
              <div class="w-full bg-[var(--color-bg)] rounded-full h-2 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300 ease-out"
                  :class="[
                    mediaCancelling[conn.id] ? 'bg-orange-500' : 'bg-[var(--color-accent)]',
                    mediaProgress[conn.id].total > 0 ? '' : 'animate-pulse',
                  ]"
                  :style="{ width: mediaProgress[conn.id].total > 0 ? mediaProgress[conn.id].percent + '%' : '100%' }"
                />
              </div>
              <div v-if="mediaProgress[conn.id].import_id && !mediaCancelling[conn.id]" class="flex justify-end">
                <button
                  class="pim-btn pim-btn-secondary text-xs text-red-600 hover:text-red-700 border-red-200 hover:border-red-300"
                  @click="cancelMediaPull(conn.id)"
                >
                  <StopCircle class="w-3.5 h-3.5 mr-1" />
                  Abbrechen
                </button>
              </div>
            </div>

            <!-- Progress-Log -->
            <div v-if="mediaProgressLog[conn.id]?.length" class="max-h-40 overflow-y-auto space-y-1 font-mono text-[11px] rounded-lg bg-[var(--color-bg)] p-2">
              <div
                v-for="(entry, i) in mediaProgressLog[conn.id]"
                :key="i"
                class="flex items-start gap-2 text-[var(--color-text-secondary)]"
              >
                <span class="text-[var(--color-text-tertiary)] shrink-0">{{ entry.at.toLocaleTimeString() }}</span>
                <span class="truncate">{{ entry.message }}</span>
              </div>
            </div>

            <!-- Result -->
            <div v-if="mediaResult[conn.id]" class="flex items-start gap-2 text-xs" :class="mediaResult[conn.id].failed > 0 ? 'text-orange-600' : 'text-[var(--color-success)]'">
              <CheckCircle class="w-4 h-4 shrink-0" />
              <span>
                {{ mediaResult[conn.id].downloaded }} Datei(en) nachgeladen.
                <template v-if="mediaResult[conn.id].failed > 0">
                  {{ mediaResult[conn.id].failed }} fehlgeschlagen.
                </template>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- API-Clients Link -->
      <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Key class="w-4 h-4 text-[var(--color-text-tertiary)]" />
            <span class="text-sm text-[var(--color-text-secondary)]">
              API-Keys verwalten — damit andere Instanzen oder Systeme auf dieses PIM zugreifen können
            </span>
          </div>
          <button class="pim-btn pim-btn-ghost text-xs" @click="router.push('/connectors/api-keys')">
            Verwalten
            <ArrowUpRight class="w-3 h-3 ml-1" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
