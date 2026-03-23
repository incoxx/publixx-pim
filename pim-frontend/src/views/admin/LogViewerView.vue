<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import client from '@/api/client'
import {
  FileText, Search, Trash2, RefreshCw, Copy, ChevronDown, ChevronRight,
  AlertTriangle, AlertCircle, Info, Bug, Filter, X
} from 'lucide-vue-next'

const channels = ['laravel', 'import', 'export']
const activeChannel = ref('laravel')
const entries = ref([])
const meta = ref({ channel: '', total_entries: 0, file_size: 0, file_size_human: '0 B' })
const loading = ref(false)
const levelFilter = ref('')
const searchQuery = ref('')
const autoRefresh = ref(false)
const expandedEntries = ref(new Set())
const showClearConfirm = ref(false)
const clearing = ref(false)

let refreshTimer = null
let searchTimeout = null

const errorCount = computed(() => entries.value.filter(e => e.level === 'ERROR').length)
const warningCount = computed(() => entries.value.filter(e => e.level === 'WARNING').length)

async function fetchLogs() {
  loading.value = true
  try {
    const params = { channel: activeChannel.value, limit: 1000 }
    if (levelFilter.value) params.level = levelFilter.value
    if (searchQuery.value) params.search = searchQuery.value
    const { data } = await client.get('/debug/logs/parsed', { params })
    entries.value = data.entries || []
    if (data.meta) meta.value = data.meta
    // Aufgeklappte Eintraege zuruecksetzen bei Neuladen
    expandedEntries.value = new Set()
  } catch { entries.value = [] }
  finally { loading.value = false }
}

async function clearLogs() {
  clearing.value = true
  try {
    await client.delete('/debug/logs', { params: { channel: activeChannel.value } })
    entries.value = []
    meta.value = { ...meta.value, total_entries: 0, file_size: 0, file_size_human: '0 B' }
    showClearConfirm.value = false
  } catch { /* ignore */ }
  finally { clearing.value = false }
}

function toggleExpand(index) {
  const s = new Set(expandedEntries.value)
  if (s.has(index)) {
    s.delete(index)
  } else {
    s.add(index)
  }
  expandedEntries.value = s
}

function toggleAutoRefresh() {
  autoRefresh.value = !autoRefresh.value
  if (autoRefresh.value) {
    refreshTimer = setInterval(fetchLogs, 10000)
  } else {
    clearInterval(refreshTimer)
    refreshTimer = null
  }
}

function onSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchLogs(), 400)
}

function copyMessage(entry) {
  navigator.clipboard.writeText(entry.message)
}

function levelColor(level) {
  switch (level) {
    case 'ERROR': case 'CRITICAL': case 'EMERGENCY': case 'ALERT':
      return 'bg-red-500/15 text-red-500'
    case 'WARNING':
      return 'bg-amber-500/15 text-amber-500'
    case 'INFO': case 'NOTICE':
      return 'bg-blue-500/15 text-blue-500'
    default:
      return 'bg-gray-500/15 text-[var(--color-text-tertiary)]'
  }
}

function levelIcon(level) {
  switch (level) {
    case 'ERROR': case 'CRITICAL': case 'EMERGENCY': case 'ALERT':
      return AlertCircle
    case 'WARNING':
      return AlertTriangle
    case 'INFO': case 'NOTICE':
      return Info
    default:
      return Bug
  }
}

watch(activeChannel, () => {
  searchQuery.value = ''
  levelFilter.value = ''
  fetchLogs()
})

watch(levelFilter, (newVal, oldVal) => {
  // Kein erneuter Fetch wenn Channel-Wechsel den Filter zurücksetzt
  if (oldVal !== '' || newVal !== '') {
    fetchLogs()
  }
})

onMounted(fetchLogs)

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
  clearTimeout(searchTimeout)
})
</script>

<template>
  <div class="space-y-4 max-w-6xl">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
        <FileText class="w-5 h-5" />
        Log Viewer
      </h2>
      <div class="flex items-center gap-2">
        <!-- Auto-refresh -->
        <button
          :class="[
            'pim-btn text-xs px-3 py-1.5 gap-1.5',
            autoRefresh ? 'pim-btn-primary' : 'pim-btn-secondary'
          ]"
          @click="toggleAutoRefresh"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="autoRefresh ? 'animate-spin' : ''" />
          Auto
        </button>
        <!-- Refresh -->
        <button class="pim-btn pim-btn-secondary text-xs px-3 py-1.5" @click="fetchLogs">
          <RefreshCw class="w-3.5 h-3.5" />
        </button>
        <!-- Clear -->
        <button
          class="pim-btn text-xs px-3 py-1.5 bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/20"
          @click="showClearConfirm = true"
        >
          <Trash2 class="w-3.5 h-3.5" />
          Löschen
        </button>
      </div>
    </div>

    <!-- Clear Confirm -->
    <div
      v-if="showClearConfirm"
      class="flex items-center gap-3 px-4 py-3 rounded-lg border border-red-500/30 bg-red-500/5"
    >
      <AlertTriangle class="w-4 h-4 text-red-500 shrink-0" />
      <span class="text-xs text-[var(--color-text-primary)] flex-1">
        Log-Datei <strong>{{ activeChannel }}.log</strong> wirklich löschen?
      </span>
      <button class="pim-btn pim-btn-secondary text-xs px-3 py-1" @click="showClearConfirm = false">
        Abbrechen
      </button>
      <button
        class="pim-btn text-xs px-3 py-1 bg-red-500 text-white hover:bg-red-600"
        :disabled="clearing"
        @click="clearLogs"
      >
        {{ clearing ? 'Lösche...' : 'Löschen' }}
      </button>
    </div>

    <!-- Channel Tabs + Stats -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex gap-1">
        <button
          v-for="ch in channels"
          :key="ch"
          :class="[
            'pim-btn text-xs px-3 py-1.5 capitalize',
            ch === activeChannel ? 'pim-btn-primary' : 'pim-btn-secondary'
          ]"
          @click="activeChannel = ch"
        >
          {{ ch }}
        </button>
      </div>
      <div class="flex items-center gap-4 text-xs text-[var(--color-text-tertiary)]">
        <span>{{ meta.total_entries }} Einträge</span>
        <span>{{ meta.file_size_human }}</span>
        <span v-if="errorCount" class="text-red-500 font-medium">{{ errorCount }} Fehler</span>
        <span v-if="warningCount" class="text-amber-500 font-medium">{{ warningCount }} Warnungen</span>
      </div>
    </div>

    <!-- Controls -->
    <div class="flex items-center gap-3">
      <!-- Level filter -->
      <div class="relative">
        <Filter class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
        <select
          v-model="levelFilter"
          class="pim-input text-xs pl-8 pr-8 py-1.5 appearance-none cursor-pointer"
        >
          <option value="">Alle Level</option>
          <option value="ERROR">ERROR</option>
          <option value="WARNING">WARNING</option>
          <option value="INFO">INFO</option>
          <option value="DEBUG">DEBUG</option>
        </select>
      </div>
      <!-- Search -->
      <div class="relative flex-1 max-w-md">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" />
        <input
          v-model="searchQuery"
          type="text"
          class="pim-input text-xs w-full pim-input-icon"
          placeholder="In Meldungen suchen..."
          @input="onSearch"
        />
        <button
          v-if="searchQuery"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]"
          @click="searchQuery = ''; fetchLogs()"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading && !entries.length" class="space-y-2">
      <div v-for="i in 6" :key="i" class="pim-skeleton h-12 rounded-lg" />
    </div>

    <!-- Empty -->
    <div
      v-else-if="!entries.length"
      class="text-center py-12 text-[var(--color-text-tertiary)] text-sm"
    >
      Keine Log-Einträge{{ levelFilter ? ` mit Level "${levelFilter}"` : '' }}{{ searchQuery ? ` für "${searchQuery}"` : '' }}.
    </div>

    <!-- Entries -->
    <div v-else class="space-y-1">
      <div
        v-for="(entry, index) in entries"
        :key="index"
        class="border border-[var(--color-border)] rounded-lg overflow-hidden"
      >
        <!-- Entry Row -->
        <div
          class="flex items-start gap-3 px-3 py-2 hover:bg-[var(--color-bg)] transition-colors"
          :class="entry.stack_trace ? 'cursor-pointer' : ''"
          @click="entry.stack_trace ? toggleExpand(index) : null"
        >
          <!-- Expand indicator -->
          <div class="shrink-0 mt-0.5 w-4 h-4 flex items-center justify-center">
            <template v-if="entry.stack_trace">
              <ChevronDown v-if="expandedEntries.has(index)" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
              <ChevronRight v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
            </template>
          </div>

          <!-- Level badge -->
          <span
            :class="['shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold', levelColor(entry.level)]"
          >
            <component :is="levelIcon(entry.level)" class="w-3 h-3" />
            {{ entry.level }}
          </span>

          <!-- Timestamp -->
          <span class="shrink-0 font-mono text-[11px] text-[var(--color-text-tertiary)] mt-0.5">
            {{ entry.timestamp }}
          </span>

          <!-- Message -->
          <span class="flex-1 text-xs text-[var(--color-text-primary)] break-all leading-relaxed mt-0.5">
            {{ entry.message }}
          </span>

          <!-- Copy -->
          <button
            class="shrink-0 p-1 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] transition-colors"
            title="Meldung kopieren"
            @click.stop="copyMessage(entry)"
          >
            <Copy class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Stack trace (collapsed by default) -->
        <div
          v-if="entry.stack_trace && expandedEntries.has(index)"
          class="px-3 pb-3"
        >
          <pre class="font-mono text-[11px] whitespace-pre-wrap text-[var(--color-text-tertiary)] bg-[var(--color-bg)] p-3 rounded-lg max-h-64 overflow-y-auto leading-relaxed">{{ entry.stack_trace }}</pre>
        </div>
      </div>
    </div>

  </div>
</template>
