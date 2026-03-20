<script setup>
defineOptions({ name: 'ConnectorConnectionView' })

import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, RefreshCw, Image, Package, CheckCircle, XCircle, AlertCircle, Clock,
} from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const route = useRoute()
const router = useRouter()

const connection = ref(null)
const syncLogs = ref([])
const loading = ref(false)
const syncing = ref(false)
const error = ref('')
const syncError = ref('')

// Sync-Dialog
const showSyncDialog = ref(false)
const syncType = ref('media') // 'media' | 'product'
const syncEntityId = ref('')
const syncLanguage = ref('de')

const connectionId = computed(() => route.params.id)

onMounted(loadConnection)

async function loadConnection() {
  loading.value = true
  error.value = ''
  try {
    const [connRes, logsRes] = await Promise.all([
      connectorsApi.getConnection(connectionId.value),
      connectorsApi.syncLogs(connectionId.value, { per_page: 50 }),
    ])
    connection.value = connRes.data.data || connRes.data
    syncLogs.value = logsRes.data.data || logsRes.data
  } catch (e) {
    error.value = 'Fehler beim Laden der Verbindung'
  } finally {
    loading.value = false
  }
}

async function executeSyncSingle() {
  if (!syncEntityId.value.trim()) return
  syncing.value = true
  syncError.value = ''
  try {
    if (syncType.value === 'media') {
      await connectorsApi.syncMedia(connectionId.value, syncEntityId.value.trim())
    } else {
      await connectorsApi.syncProduct(connectionId.value, syncEntityId.value.trim(), {
        language: syncLanguage.value,
      })
    }
    showSyncDialog.value = false
    syncEntityId.value = ''
    await loadConnection()
  } catch (e) {
    syncError.value = e.response?.data?.message || e.message
  } finally {
    syncing.value = false
  }
}

const statusColors = {
  success: 'badge-success',
  failed: 'badge-error',
  pending: 'badge-warning',
}

const statusIcons = {
  success: CheckCircle,
  failed: XCircle,
  pending: Clock,
}
</script>

<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="btn btn-ghost btn-sm" @click="router.push('/connectors')">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <h1 class="text-xl font-bold">{{ connection?.name || 'Verbindung' }}</h1>
      <span v-if="connection" class="badge badge-ghost">{{ connection.connector_type }}</span>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <template v-if="connection && !loading">
      <!-- Connection Info -->
      <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <div class="text-sm text-base-content/50">Status</div>
              <div class="flex items-center gap-1 mt-1">
                <CheckCircle v-if="connection.is_active && !connection.token_expired" class="w-4 h-4 text-success" />
                <XCircle v-else class="w-4 h-4 text-error" />
                <span class="font-medium">
                  {{ connection.is_active && !connection.token_expired ? 'Aktiv' : 'Token abgelaufen' }}
                </span>
              </div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Verbunden von</div>
              <div class="font-medium mt-1">{{ connection.connected_by?.name || '–' }}</div>
            </div>
            <div>
              <div class="text-sm text-base-content/50">Token gültig bis</div>
              <div class="font-medium mt-1">
                {{ connection.token_expires_at ? new Date(connection.token_expires_at).toLocaleString('de-DE') : '–' }}
              </div>
            </div>
            <div v-if="connection.sync_stats">
              <div class="text-sm text-base-content/50">Sync-Statistik</div>
              <div class="flex gap-2 mt-1">
                <span class="badge badge-success badge-sm">{{ connection.sync_stats.success }} OK</span>
                <span class="badge badge-error badge-sm">{{ connection.sync_stats.failed }} Fehler</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sync Actions -->
      <div class="flex gap-2">
        <button class="btn btn-primary btn-sm" @click="showSyncDialog = true; syncType = 'media'">
          <Image class="w-4 h-4" />
          Media synchronisieren
        </button>
        <button class="btn btn-secondary btn-sm" @click="showSyncDialog = true; syncType = 'product'">
          <Package class="w-4 h-4" />
          Produkt synchronisieren
        </button>
        <button class="btn btn-ghost btn-sm ml-auto" @click="loadConnection">
          <RefreshCw class="w-4 h-4" />
          Aktualisieren
        </button>
      </div>

      <!-- Sync Dialog -->
      <div v-if="showSyncDialog" class="card bg-base-100 shadow-sm border border-primary/20">
        <div class="card-body">
          <h3 class="font-semibold">
            {{ syncType === 'media' ? 'Media-Asset' : 'Produkt' }} synchronisieren
          </h3>
          <div v-if="syncError" class="alert alert-error alert-sm">{{ syncError }}</div>
          <div class="flex gap-3 items-end">
            <div class="form-control flex-1">
              <label class="label"><span class="label-text">{{ syncType === 'media' ? 'Media-ID' : 'Produkt-ID' }} (UUID)</span></label>
              <input
                v-model="syncEntityId"
                type="text"
                class="input input-bordered input-sm"
                placeholder="UUID eingeben..."
              />
            </div>
            <div v-if="syncType === 'product'" class="form-control w-24">
              <label class="label"><span class="label-text">Sprache</span></label>
              <select v-model="syncLanguage" class="select select-bordered select-sm">
                <option value="de">DE</option>
                <option value="en">EN</option>
              </select>
            </div>
            <button
              class="btn btn-primary btn-sm"
              :disabled="syncing || !syncEntityId.trim()"
              @click="executeSyncSingle"
            >
              <span v-if="syncing" class="loading loading-spinner loading-xs"></span>
              Synchronisieren
            </button>
            <button class="btn btn-ghost btn-sm" @click="showSyncDialog = false">Abbrechen</button>
          </div>
        </div>
      </div>

      <!-- Sync Logs -->
      <div class="space-y-2">
        <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wider">Sync-Protokoll</h2>
        <div v-if="syncLogs.length === 0" class="text-center py-6 text-base-content/40">
          Noch keine Sync-Einträge.
        </div>
        <div class="overflow-x-auto">
          <table v-if="syncLogs.length > 0" class="table table-sm">
            <thead>
              <tr>
                <th>Zeitpunkt</th>
                <th>Aktion</th>
                <th>Typ</th>
                <th>Entity-ID</th>
                <th>Externe ID</th>
                <th>Status</th>
                <th>Fehler</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in syncLogs" :key="log.id">
                <td class="text-xs">{{ new Date(log.created_at).toLocaleString('de-DE') }}</td>
                <td><span class="badge badge-ghost badge-xs">{{ log.action }}</span></td>
                <td>{{ log.entity_type }}</td>
                <td class="font-mono text-xs">{{ log.entity_id?.substring(0, 8) }}...</td>
                <td class="font-mono text-xs">{{ log.external_id || '–' }}</td>
                <td>
                  <span :class="statusColors[log.status]" class="badge badge-xs">
                    {{ log.status }}
                  </span>
                </td>
                <td class="text-xs text-error max-w-xs truncate">{{ log.error_message || '–' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
