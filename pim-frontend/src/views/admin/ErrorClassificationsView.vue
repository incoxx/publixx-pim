<script setup>
import { ref, computed, onMounted } from 'vue'
import { useErrorClassificationsStore } from '@/stores/errorClassifications'
import {
  Bug, Search, Trash2, RefreshCw, ChevronDown, ChevronRight,
  AlertTriangle, AlertCircle, HelpCircle, CheckCircle2, X,
  Send, Clock, RotateCcw, FlaskConical, Download,
} from 'lucide-vue-next'

const store = useErrorClassificationsStore()
const expandedIds = ref(new Set())
const showDeleteConfirm = ref(false)
const classifyResult = ref(null)
const searchQuery = ref('')
let searchTimeout = null

// Filter
const severityFilter = ref('')
const classificationFilter = ref('')
const statusFilter = ref('')

// Statistiken
const criticalCount = computed(() => store.items.filter(i => i.severity === 'critical').length)
const highCount = computed(() => store.items.filter(i => i.severity === 'high').length)
const unclassifiedCount = computed(() => store.items.filter(i => !i.classification).length)

function applyFilters() {
  store.setFilters({
    severity: severityFilter.value || undefined,
    classification: classificationFilter.value || undefined,
    status: statusFilter.value || undefined,
  })
  store.fetchList()
}

function onSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    store.fetchList({ search: searchQuery.value })
  }, 400)
}

function clearSearch() {
  searchQuery.value = ''
  store.fetchList()
}

function toggleExpand(id) {
  const s = new Set(expandedIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  expandedIds.value = s
}

async function runDryRun() {
  classifyResult.value = null
  const result = await store.runClassification(50, true)
  if (result) classifyResult.value = { ...result, dry_run: true }
}

async function runLiveLauf() {
  classifyResult.value = null
  const result = await store.runClassification(20)
  if (result) classifyResult.value = result
}

async function handleExport() {
  await store.exportExcel()
}

async function markStatus(item, status) {
  await store.updateRecord(item.id, { status })
}

async function confirmDelete() {
  await store.deleteAll({ 'filter[status]': 'resolved' })
  showDeleteConfirm.value = false
}

// Badge-Styling
function severityClass(severity) {
  switch (severity) {
    case 'critical': return 'bg-red-500/15 text-red-500 border border-red-500/30'
    case 'high':     return 'bg-orange-500/15 text-orange-500 border border-orange-500/30'
    case 'medium':   return 'bg-yellow-500/15 text-yellow-600 border border-yellow-500/30'
    default:         return 'bg-gray-500/10 text-[var(--color-text-tertiary)] border border-[var(--color-border)]'
  }
}

function severityLabel(severity) {
  return { critical: 'KRITISCH', high: 'HOCH', medium: 'MITTEL', low: 'NIEDRIG' }[severity] ?? severity?.toUpperCase() ?? '?'
}

function classificationClass(c) {
  switch (c) {
    case 'real_bug':   return 'bg-red-500/10 text-red-500'
    case 'user_error': return 'bg-blue-500/10 text-blue-500'
    default:           return 'bg-gray-500/10 text-[var(--color-text-tertiary)]'
  }
}

function classificationLabel(c) {
  return { real_bug: 'Echter Bug', user_error: 'Nutzerfehler', uncertain: 'Unklar' }[c] ?? 'Nicht klassifiziert'
}

function statusClass(s) {
  switch (s) {
    case 'forwarded': return 'bg-purple-500/10 text-purple-500'
    case 'resolved':  return 'bg-emerald-500/10 text-emerald-500'
    case 'reviewed':  return 'bg-blue-500/10 text-blue-500'
    default:          return 'bg-gray-500/10 text-[var(--color-text-tertiary)]'
  }
}

function statusLabel(s) {
  return { new: 'Neu', reviewed: 'Geprüft', forwarded: 'Weitergeleitet', resolved: 'Erledigt' }[s] ?? s
}

function severityIcon(severity) {
  switch (severity) {
    case 'critical': case 'high': return AlertCircle
    case 'medium':                return AlertTriangle
    default:                      return HelpCircle
  }
}

function relativeTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const diff = Math.floor((Date.now() - d.getTime()) / 1000)
  if (diff < 60)   return 'Gerade eben'
  if (diff < 3600) return `vor ${Math.floor(diff / 60)} Min`
  if (diff < 86400) return `vor ${Math.floor(diff / 3600)} Std`
  return `vor ${Math.floor(diff / 86400)} Tagen`
}

onMounted(() => {
  store.fetchStatus()
  store.fetchList()
})
</script>

<template>
  <div class="space-y-4 max-w-6xl">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
        <Bug class="w-5 h-5" />
        Fehlerklassifikation
      </h2>
      <div class="flex items-center gap-2">
        <!-- Dry-Run -->
        <button
          class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 gap-1.5"
          :disabled="store.classifying"
          :title="store.hasApiKey ? 'Logs analysieren ohne zu speichern' : 'Logs analysieren ohne zu speichern (regelbasiert)'"
          @click="runDryRun"
        >
          <FlaskConical class="w-3.5 h-3.5" :class="store.classifying ? 'animate-pulse' : ''" />
          Dry-Run
        </button>
        <!-- Live-Lauf -->
        <button
          class="pim-btn pim-btn-primary text-xs px-3 py-1.5 gap-1.5"
          :disabled="store.classifying"
          :title="store.hasApiKey ? 'Logs analysieren und klassifizieren (max. 20)' : 'Logs analysieren und klassifizieren (regelbasiert, max. 20)'"
          @click="runLiveLauf"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="store.classifying ? 'animate-spin' : ''" />
          {{ store.classifying ? 'Analysiere...' : 'Live-Lauf (20)' }}
        </button>
        <!-- Reload -->
        <button
          class="pim-btn pim-btn-secondary text-xs px-3 py-1.5"
          @click="store.fetchList()"
        >
          <RefreshCw class="w-3.5 h-3.5" />
        </button>
        <!-- Excel Export -->
        <button
          class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 gap-1.5"
          title="Aktuelle Ansicht als Excel exportieren"
          @click="handleExport"
        >
          <Download class="w-3.5 h-3.5" />
          Excel
        </button>
        <!-- Löschen -->
        <button
          class="pim-btn text-xs px-3 py-1.5 bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/20"
          @click="showDeleteConfirm = true"
        >
          <Trash2 class="w-3.5 h-3.5" />
          Erledigte löschen
        </button>
      </div>
    </div>

    <!-- Klassifikations-Ergebnis -->
    <div
      v-if="classifyResult"
      class="flex items-center gap-3 px-4 py-3 rounded-lg border border-emerald-500/30 bg-emerald-500/5 text-xs"
    >
      <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0" />
      <span class="text-[var(--color-text-primary)]">
        Analyse abgeschlossen:
        <strong>{{ classifyResult.errors_found }}</strong> Fehler gefunden,
        <strong>{{ classifyResult.groups }}</strong> Gruppen,
        <strong>{{ classifyResult.classified }}</strong> neu klassifiziert.
        <span v-if="classifyResult.dry_run" class="ml-1 text-yellow-600 font-medium">(Dry-Run — nichts gespeichert)</span>
      </span>
      <button class="ml-auto text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]" @click="classifyResult = null">
        <X class="w-3.5 h-3.5" />
      </button>
    </div>

    <!-- Delete Confirm -->
    <div
      v-if="showDeleteConfirm"
      class="flex items-center gap-3 px-4 py-3 rounded-lg border border-red-500/30 bg-red-500/5"
    >
      <AlertTriangle class="w-4 h-4 text-red-500 shrink-0" />
      <span class="text-xs text-[var(--color-text-primary)] flex-1">
        Alle Einträge mit Status "Erledigt" löschen?
      </span>
      <button class="pim-btn pim-btn-secondary text-xs px-3 py-1" @click="showDeleteConfirm = false">Abbrechen</button>
      <button class="pim-btn text-xs px-3 py-1 bg-red-500 text-white hover:bg-red-600" @click="confirmDelete">Löschen</button>
    </div>

    <!-- Kein API Key: Info-Banner (Fallback vorhanden) -->
    <div
      v-if="!store.hasApiKey"
      class="flex items-center gap-2 text-xs text-blue-600 bg-blue-500/10 border border-blue-500/20 rounded-lg px-3 py-2"
    >
      <AlertCircle class="w-3.5 h-3.5 shrink-0 text-blue-500" />
      <span>
        Kein Claude API Key — es wird <strong>regel-basierte Klassifikation</strong> verwendet.
        Für KI-Analyse: <code class="font-mono bg-blue-500/10 px-1 rounded">CLAUDE_AI_API_KEY</code> in <code class="font-mono bg-blue-500/10 px-1 rounded">.env</code> setzen.
      </span>
    </div>

    <!-- Statistiken -->
    <div class="flex items-center gap-6 text-xs text-[var(--color-text-tertiary)]">
      <span>{{ store.meta.total }} Einträge</span>
      <span v-if="criticalCount" class="text-red-500 font-medium">{{ criticalCount }} kritisch</span>
      <span v-if="highCount" class="text-orange-500 font-medium">{{ highCount }} hoch</span>
      <span v-if="unclassifiedCount" class="text-[var(--color-text-tertiary)]">{{ unclassifiedCount }} nicht klassifiziert</span>
    </div>

    <!-- Filter + Suche -->
    <div class="flex items-center gap-3 flex-wrap">
      <!-- Schweregrad -->
      <select v-model="severityFilter" class="pim-input text-xs py-1.5 w-auto cursor-pointer" @change="applyFilters">
        <option value="">Alle Schweregrade</option>
        <option value="critical">Kritisch</option>
        <option value="high">Hoch</option>
        <option value="medium">Mittel</option>
        <option value="low">Niedrig</option>
      </select>
      <!-- Klassifikation -->
      <select v-model="classificationFilter" class="pim-input text-xs py-1.5 w-auto cursor-pointer" @change="applyFilters">
        <option value="">Alle Typen</option>
        <option value="real_bug">Echter Bug</option>
        <option value="user_error">Nutzerfehler</option>
        <option value="uncertain">Unklar</option>
      </select>
      <!-- Status -->
      <select v-model="statusFilter" class="pim-input text-xs py-1.5 w-auto cursor-pointer" @change="applyFilters">
        <option value="">Alle Status</option>
        <option value="new">Neu</option>
        <option value="reviewed">Geprüft</option>
        <option value="forwarded">Weitergeleitet</option>
        <option value="resolved">Erledigt</option>
      </select>
      <!-- Suche -->
      <div class="relative flex-1 max-w-md">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" />
        <input
          v-model="searchQuery"
          type="text"
          class="pim-input text-xs w-full pim-input-icon"
          placeholder="Exception, Datei, Meldung..."
          @input="onSearch"
        />
        <button
          v-if="searchQuery"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]"
          @click="clearSearch"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="store.loading && !store.items.length" class="space-y-2">
      <div v-for="i in 5" :key="i" class="pim-skeleton h-16 rounded-lg" />
    </div>

    <!-- Leer -->
    <div
      v-else-if="!store.items.length"
      class="text-center py-16 text-[var(--color-text-tertiary)] text-sm space-y-2"
    >
      <Bug class="w-8 h-8 mx-auto opacity-30" />
      <p>Keine Fehler gefunden.</p>
      <p class="text-xs">Klicke auf "Dry-Run" oder "Live-Lauf (20)" um Logs zu verarbeiten.</p>
    </div>

    <!-- Einträge -->
    <div v-else class="space-y-1">
      <div
        v-for="item in store.items"
        :key="item.id"
        class="border border-[var(--color-border)] rounded-lg overflow-hidden"
        :class="item.severity === 'critical' ? 'border-red-500/30' : item.severity === 'high' ? 'border-orange-500/20' : ''"
      >
        <!-- Hauptzeile -->
        <div
          class="flex items-start gap-3 px-3 py-2.5 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
          @click="toggleExpand(item.id)"
        >
          <!-- Expand -->
          <div class="shrink-0 mt-0.5 w-4 h-4 flex items-center justify-center">
            <ChevronDown v-if="expandedIds.has(item.id)" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
            <ChevronRight v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
          </div>

          <!-- Severity Badge -->
          <span :class="['shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold', severityClass(item.severity)]">
            <component :is="severityIcon(item.severity)" class="w-3 h-3" />
            {{ severityLabel(item.severity) }}
          </span>

          <!-- Titel / Meldung -->
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-[var(--color-text-primary)] truncate">
              {{ item.ai_title || item.exception_class }}
            </p>
            <p class="text-[11px] text-[var(--color-text-tertiary)] truncate mt-0.5">
              {{ item.file }}:{{ item.line }}
              <span class="mx-1">·</span>
              {{ item.occurrence_count }}× aufgetreten
              <span class="mx-1">·</span>
              <Clock class="w-3 h-3 inline -mt-0.5" /> {{ relativeTime(item.last_seen_at) }}
            </p>
          </div>

          <!-- Badges -->
          <div class="shrink-0 flex items-center gap-1.5">
            <span
              v-if="item.classification"
              :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium', classificationClass(item.classification)]"
            >
              {{ classificationLabel(item.classification) }}
            </span>
            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium', statusClass(item.status)]">
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>

        <!-- Expandierter Bereich -->
        <div v-if="expandedIds.has(item.id)" class="px-4 pb-4 border-t border-[var(--color-border)] bg-[var(--color-bg)] space-y-3">

          <!-- KI-Beschreibung -->
          <div v-if="item.ai_description" class="pt-3">
            <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Beschreibung</p>
            <p class="text-xs text-[var(--color-text-primary)] leading-relaxed">{{ item.ai_description }}</p>
          </div>

          <!-- Hinweis -->
          <div v-if="item.ai_hint" class="p-3 rounded-lg bg-amber-500/5 border border-amber-500/20">
            <p class="text-[11px] font-semibold text-amber-600 mb-1">Hinweis / Mögliche Ursache</p>
            <p class="text-xs text-[var(--color-text-primary)]">{{ item.ai_hint }}</p>
          </div>

          <!-- Technische Details -->
          <div>
            <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Technisch</p>
            <div class="text-[11px] text-[var(--color-text-tertiary)] space-y-0.5 font-mono">
              <p>Exception: {{ item.exception_class }}</p>
              <p>Datei: {{ item.file }}:{{ item.line }}</p>
              <p>Quelle: {{ item.source === 'queue' ? 'Background-Job (Queue)' : 'HTTP-Request' }}</p>
              <p>Erstmals: {{ item.first_seen_at ? new Date(item.first_seen_at).toLocaleString('de-DE') : '—' }}</p>
              <p>Zuletzt: {{ item.last_seen_at ? new Date(item.last_seen_at).toLocaleString('de-DE') : '—' }}</p>
              <p v-if="item.ai_confidence">KI-Konfidenz: {{ Math.round(item.ai_confidence * 100) }}%</p>
            </div>
          </div>

          <!-- Stack Trace -->
          <div v-if="item.stack_trace">
            <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide mb-1">Stack Trace</p>
            <pre class="font-mono text-[11px] whitespace-pre-wrap text-[var(--color-text-tertiary)] bg-[var(--color-bg)] border border-[var(--color-border)] p-3 rounded-lg max-h-48 overflow-y-auto leading-relaxed">{{ item.stack_trace }}</pre>
          </div>

          <!-- Notizen -->
          <div v-if="item.notes" class="p-3 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)]">
            <p class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1">Notizen</p>
            <p class="text-xs text-[var(--color-text-primary)]">{{ item.notes }}</p>
          </div>

          <!-- Aktionen -->
          <div class="flex items-center gap-2 pt-1">
            <button
              v-if="item.status === 'new'"
              class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 gap-1.5"
              @click="markStatus(item, 'reviewed')"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              Als geprüft markieren
            </button>
            <button
              v-if="item.status !== 'forwarded' && item.status !== 'resolved'"
              class="pim-btn text-xs px-3 py-1.5 gap-1.5 bg-purple-500/10 text-purple-500 hover:bg-purple-500/20 border border-purple-500/20"
              @click="markStatus(item, 'forwarded')"
            >
              <Send class="w-3.5 h-3.5" />
              An Entwicklung weiterleiten
            </button>
            <button
              v-if="item.status !== 'resolved'"
              class="pim-btn text-xs px-3 py-1.5 gap-1.5 bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 border border-emerald-500/20"
              @click="markStatus(item, 'resolved')"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              Erledigt
            </button>
            <button
              v-if="item.status !== 'new'"
              class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 gap-1.5"
              @click="markStatus(item, 'new')"
            >
              <RotateCcw class="w-3.5 h-3.5" />
              Zurücksetzen
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="store.meta.last_page > 1"
      class="flex items-center justify-between text-xs text-[var(--color-text-tertiary)] pt-2"
    >
      <span>Seite {{ store.meta.current_page }} von {{ store.meta.last_page }} ({{ store.meta.total }} gesamt)</span>
      <div class="flex gap-1">
        <button
          class="pim-btn pim-btn-secondary text-xs px-3 py-1.5"
          :disabled="store.meta.current_page <= 1"
          @click="store.setPage(store.meta.current_page - 1); store.fetchList()"
        >
          Zurück
        </button>
        <button
          class="pim-btn pim-btn-secondary text-xs px-3 py-1.5"
          :disabled="store.meta.current_page >= store.meta.last_page"
          @click="store.setPage(store.meta.current_page + 1); store.fetchList()"
        >
          Weiter
        </button>
      </div>
    </div>

  </div>
</template>
