<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useTranslationsStore } from '@/stores/translations'
import { useLocaleStore } from '@/stores/locale'
import translationsApi from '@/api/translations'
import { resolveApiUrl } from '@/api/client'
import { Languages, RefreshCw, Download, Upload, Search, Globe, BarChart3, AlertCircle, Check, Loader2, Settings, X, Trash2 } from 'lucide-vue-next'
import TranslationStatsCard from './TranslationStatsCard.vue'
import TranslationUnitPanel from './TranslationUnitPanel.vue'

const store = useTranslationsStore()
const localeStore = useLocaleStore()

const activeTab = ref('overview')
const searchQuery = ref('')
const selectedLang = ref('en')
const selectedDomain = ref('')
const selectedStatus = ref('')
const selectedUnitId = ref(null)
const showPanel = ref(false)
const ingestLoading = ref(false)
const syncLoading = ref(false)
const deleteLoading = ref(false)
const actionLog = ref([])
const unitsPage = ref(1)

const tabs = [
  { key: 'overview', label: 'Übersicht', icon: BarChart3 },
  // "Fehlend" war ein eigener Tab, lieferte aber dieselbe Liste wie der
  // Statusfilter "Fehlend" im Tab "Begriffe" — zwei Wege zum selben Ergebnis,
  // nur mit unterschiedlichem Funktionsumfang. Jetzt ein Ort, ein Filter.
  { key: 'units', label: 'Begriffe', icon: Globe },
  { key: 'settings', label: 'Einstellungen', icon: Settings },
]

const domainOptions = [
  { value: '', label: 'Alle Bereiche' },
  { value: 'attribute', label: 'Attribute' },
  { value: 'attribute_group', label: 'Attributgruppen' },
  { value: 'attribute_view', label: 'Attribut-Sichten' },
  { value: 'value_list', label: 'Wertelisten' },
  { value: 'value_list_entry', label: 'Wertelisten-Einträge' },
  { value: 'product_type', label: 'Produkttypen' },
  { value: 'price_type', label: 'Preisarten' },
  { value: 'relation_type', label: 'Beziehungstypen' },
  { value: 'unit_group', label: 'Einheitengruppen' },
  { value: 'hierarchy', label: 'Hierarchien' },
  { value: 'hierarchy_node', label: 'Hierarchie-Knoten' },
]

// Zielsprachen kommen aus der Sprachverwaltung, nicht mehr aus einer
// hart kodierten Liste. Die Quellsprache ist kein Uebersetzungsziel.
const languageOptions = computed(() =>
  localeStore.availableLocales
    .filter(l => !l.is_source)
    .map(l => ({ value: l.code, label: l.label })),
)

// Alle Begriffe stammen aus derselben Quellsprache — sie gehoert in die
// Spaltenueberschrift, nicht in jede einzelne Zeile.
const sourceLangLabel = computed(() =>
  (localeStore.availableLocales.find(l => l.is_source)?.code || 'de').toUpperCase(),
)

/**
 * Uebersetzung in der gewaehlten Zielsprache.
 *
 * Die Liste laedt sie ueber den lang-Parameter mit. Fehlt sie, ist der Begriff
 * in dieser Sprache schlicht noch nicht uebersetzt.
 */
function translationOf(unit) {
  return unit.translations?.[0] ?? null
}

function statusOf(unit) {
  const t = translationOf(unit)

  if (!t) {
    return { label: 'Fehlend', done: false, class: 'bg-amber-500/10 text-amber-600' }
  }
  if (t.status === 'reviewed') {
    return { label: 'Geprüft', done: true, class: 'bg-green-500/10 text-green-600' }
  }

  // 'auto' umfasst maschinelle Uebersetzungen und aus dem PIM uebernommene
  // Werte — der Provider unterscheidet beide.
  return {
    label: t.provider === 'import' ? 'Aus PIM' : 'Automatisch',
    done: true,
    class: 'bg-blue-500/10 text-blue-600',
  }
}

onMounted(async () => {
  await localeStore.fetchLanguages()

  // Vorauswahl auf die erste tatsaechlich gepflegte Zielsprache
  if (!languageOptions.value.some(o => o.value === selectedLang.value)) {
    selectedLang.value = languageOptions.value[0]?.value || 'en'
  }

  store.fetchStats()
})

watch(activeTab, (tab) => {
  if (tab === 'units') loadUnits()
  if (tab === 'overview') store.fetchStats()
})

function loadUnits() {
  store.fetchUnits({
    page: unitsPage.value,
    per_page: 50,
    search: searchQuery.value || undefined,
    domain: selectedDomain.value || undefined,
    status: selectedStatus.value || undefined,
    lang: selectedLang.value,
  })
}

function openUnit(unit) {
  selectedUnitId.value = unit.id
  showPanel.value = true
  store.fetchUnit(unit.id)
}

function closePanel() {
  showPanel.value = false
  selectedUnitId.value = null
}

function onSearch() {
  unitsPage.value = 1
  if (activeTab.value === 'units') loadUnits()
}

function goToPage(p) {
  if (activeTab.value !== 'units') return
  unitsPage.value = p
  loadUnits()
}

function addLog(type, message) {
  const time = new Date().toLocaleTimeString('de-DE')
  actionLog.value.unshift({ time, type, message })
  if (actionLog.value.length > 20) actionLog.value.pop()
}

const glossaryImportLoading = ref(false)
const glossaryFileInput = ref(null)

/**
 * Glossar herausgeben, damit es ohne MT-Anbieter übersetzt werden kann —
 * typischerweise durch den Landesvertrieb. Alle Sprachen nebeneinander,
 * damit der Quervergleich möglich ist.
 */
function exportGlossary(format) {
  const url = translationsApi.glossaryExportUrl({
    langs: selectedLang.value,
    // Exportiert, was gerade gefiltert ist — nicht mehr an einen Tab gebunden.
    status: selectedStatus.value || 'all',
    domain: selectedDomain.value || '',
    format,
  })
  addLog('info', `Glossar-Export (${format.toUpperCase()}) wird erzeugt…`)
  window.open(resolveApiUrl(url), '_blank')
}

async function onGlossaryFile(event) {
  const file = event.target.files?.[0]
  if (!file) return

  glossaryImportLoading.value = true
  addLog('info', `Import von "${file.name}" läuft…`)
  try {
    const { data } = await store.importGlossary(file)
    addLog('success', data.message || 'Import abgeschlossen.')
    if (data.unknown > 0) {
      addLog('error', `${data.unknown} Begriffe nicht gefunden — stammt die Datei aus einem älteren Export?`)
    }
    if (data.skipped_rows > 0) {
      addLog('info', `${data.skipped_rows} Zeilen ohne Schlüssel übersprungen.`)
    }
    await store.fetchStats()
    if (activeTab.value === 'units') loadUnits()
  } catch (e) {
    addLog('error', 'Import fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  } finally {
    glossaryImportLoading.value = false
    event.target.value = ''
  }
}

const rolloutLoading = ref(false)

/**
 * Rollt die aktuell gewählte Sprache über den gesamten TM-Bestand aus.
 * Das ist der Weg, eine neu hinzugefügte Sprache (z.B. Finnisch) zu füllen —
 * bis dahin bleibt sie leer, weil der Ingest nur neue Begriffe übersetzt.
 */
async function rolloutLanguage() {
  const lang = selectedLang.value
  if (!confirm(`Alle fehlenden Begriffe für "${lang}" maschinell übersetzen lassen?\n\nDas verursacht Kosten beim MT-Anbieter.`)) return

  rolloutLoading.value = true
  addLog('info', `Rollout für "${lang}" gestartet…`)
  try {
    const total = await store.translateMissing(lang, (done, remaining) => {
      addLog('info', `${done} eingeplant, noch ${remaining} offen…`)
    })
    addLog('success', `${total} Begriffe für "${lang}" eingeplant. Übersetzung läuft im Hintergrund.`)
    await store.fetchStats()
    loadUnits()
  } catch (e) {
    addLog('error', 'Rollout fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  } finally {
    rolloutLoading.value = false
  }
}

// Ingest und Sync laufen serverseitig über die Queue (HTTP 202) — sie können
// je nach Katalogumfang mehrere Minuten dauern. Die Antwort bestätigt nur die
// Einplanung; den Fortschritt sieht man über "Statistiken aktualisieren".
async function triggerIngest() {
  ingestLoading.value = true
  addLog('info', 'Ingest wird eingeplant — sende PIM-Daten an TMS...')
  try {
    const { data } = await store.triggerIngest()
    addLog('success', data.message || `${data.total_sent} Entitäten gesendet.`)
    if (data.queued) {
      addLog('info', 'Läuft im Hintergrund. Fortschritt über "Statistiken aktualisieren" prüfen.')
    }
  } catch (e) {
    addLog('error', 'Ingest fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  } finally {
    ingestLoading.value = false
  }
}

async function triggerSync() {
  syncLoading.value = true
  addLog('info', 'Sync wird eingeplant — hole Übersetzungen von TMS...')
  try {
    const { data } = await store.syncToDatabase()
    addLog('success', data.message || `${data.total_updated} Datensätze aktualisiert.`)
    if (data.queued) {
      addLog('info', 'Läuft im Hintergrund. Fortschritt über "Statistiken aktualisieren" prüfen.')
    }
  } catch (e) {
    addLog('error', 'Sync fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  } finally {
    syncLoading.value = false
  }
}

async function refreshStats() {
  addLog('info', 'Statistiken werden geladen...')
  try {
    await store.fetchStats()
    const total = store.stats?.total_units ?? 0
    addLog('success', `Statistiken aktualisiert — ${total} Begriffe im TMS.`)
  } catch (e) {
    addLog('error', 'Statistiken laden fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  }
}

async function deleteAllTranslations() {
  if (!confirm('Alle Übersetzungen unwiderruflich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.')) return
  deleteLoading.value = true
  addLog('info', 'Lösche alle Übersetzungen...')
  try {
    const { data } = await store.deleteAllTranslations()
    addLog('success', data.message || `${data.deleted} Übersetzungen gelöscht.`)
    await store.fetchStats()
  } catch (e) {
    addLog('error', 'Löschen fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  } finally {
    deleteLoading.value = false
  }
}

const purgeLoading = ref(false)

async function purgeAllUnits() {
  if (!confirm('Alle Begriffe und Übersetzungen unwiderruflich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.')) return
  purgeLoading.value = true
  addLog('info', 'Lösche alle Begriffe und Übersetzungen...')
  try {
    const { data } = await store.purgeAllUnits()
    addLog('success', data.message || 'Alle Begriffe gelöscht.')
    await store.fetchStats()
  } catch (e) {
    addLog('error', 'Löschen fehlgeschlagen: ' + (e.response?.data?.message || e.message))
  } finally {
    purgeLoading.value = false
  }
}

function dismissError() {
  store.error = null
}

const pagination = computed(() => store.unitsPagination)

const displayedUnits = computed(() => store.units)

const tableLoading = computed(() => store.unitsLoading)

const paginationPages = computed(() => {
  const last = pagination.value.lastPage || 1
  const current = pagination.value.currentPage || 1
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages = [1]
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  if (start > 2) pages.push('...')
  for (let i = start; i <= end; i++) pages.push(i)
  if (end < last - 1) pages.push('...')
  pages.push(last)
  return pages
})
</script>

<template>
  <div class="p-4 sm:p-6 max-w-[1400px] mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Languages class="w-6 h-6 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h1 class="text-xl font-semibold text-[var(--color-text-primary)]">Übersetzungen</h1>
      </div>
    </div>

    <!-- Error Banner -->
    <div
      v-if="store.error"
      class="mb-4 flex items-center justify-between gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"
    >
      <div class="flex items-center gap-2">
        <AlertCircle class="w-4 h-4 shrink-0" />
        {{ store.error }}
      </div>
      <button @click="dismissError" class="text-red-400 hover:text-red-600">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-[var(--color-border)] overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'flex items-center gap-2 px-4 py-2.5 text-[13px] font-medium border-b-2 -mb-px transition-colors whitespace-nowrap shrink-0',
          activeTab === tab.key
            ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
            : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
        ]"
        @click="activeTab = tab.key"
      >
        <component :is="tab.icon" class="w-4 h-4" :stroke-width="1.75" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab: Overview -->
    <div v-if="activeTab === 'overview'">
      <div v-if="store.stats" class="space-y-6">
        <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 sm:p-5">
          <div class="text-sm text-[var(--color-text-secondary)] mb-1">Begriffe gesamt</div>
          <div class="text-2xl font-semibold text-[var(--color-text-primary)]">{{ store.stats.total_units }}</div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
          <TranslationStatsCard
            v-for="(langStats, lang) in store.stats.languages"
            :key="lang"
            :lang="lang"
            :stats="langStats"
          />
        </div>
      </div>
      <div v-else class="flex items-center justify-center py-20 text-[var(--color-text-tertiary)]">
        <Loader2 class="w-5 h-5 animate-spin mr-2" />
        Lade Statistiken...
      </div>
    </div>

    <!-- Tab: Units / Missing -->
    <div v-if="activeTab === 'units'">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-4">
        <div class="relative flex-1 min-w-[200px]">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Begriffe suchen…"
            class="w-full pl-9 pr-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
            @keyup.enter="onSearch"
          />
        </div>

        <!-- Zielsprache: bestimmt, welche Uebersetzung die Liste zeigt, worauf
             sich der Statusfilter bezieht und was der Export enthaelt. Die
             Quellsprache steht nicht zur Wahl — aus ihr kommt jeder Begriff. -->
        <label class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
          <span class="whitespace-nowrap">Zielsprache</span>
          <select
            v-model="selectedLang"
            class="px-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
            @change="onSearch"
          >
            <option v-for="opt in languageOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </label>

        <select
          v-model="selectedDomain"
          class="flex-1 sm:flex-none min-w-0 px-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
          @change="onSearch"
        >
          <option v-for="opt in domainOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <select
          v-model="selectedStatus"
          class="flex-1 sm:flex-none min-w-0 px-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
          @change="onSearch"
        >
          <option value="">Alle Status</option>
          <option value="missing">Fehlend</option>
          <option value="auto">Automatisch</option>
          <option value="reviewed">Geprüft</option>
        </select>

        <!-- Neue Zielsprache ausrollen: plant alle fehlenden Begriffe der
             gewählten Sprache zur maschinellen Übersetzung ein. Bezieht sich
             auf die Zielsprache, nicht auf den gerade sichtbaren Filter. -->
        <button
          class="px-3 py-2 text-sm rounded-md bg-[var(--color-accent)] text-white disabled:opacity-50"
          :disabled="rolloutLoading"
          :title="`Alle fehlenden Begriffe für '${selectedLang}' übersetzen lassen`"
          @click="rolloutLanguage"
        >
          {{ rolloutLoading ? 'Wird eingeplant…' : `Alle fehlenden übersetzen (${selectedLang})` }}
        </button>

        <!-- Übersetzung ohne MT-Anbieter: Datei raus, ausgefüllt wieder rein -->
        <button
          class="flex items-center gap-1.5 px-3 py-2 text-sm rounded-md border border-[var(--color-border)] hover:bg-[var(--color-surface)]"
          title="Begriffe als Excel-Datei herunterladen (alle Sprachen nebeneinander)"
          @click="exportGlossary('xlsx')"
        >
          <Download class="w-4 h-4" /> Excel
        </button>

        <button
          class="flex items-center gap-1.5 px-3 py-2 text-sm rounded-md border border-[var(--color-border)] hover:bg-[var(--color-surface)]"
          title="Als CSV herunterladen"
          @click="exportGlossary('csv')"
        >
          <Download class="w-4 h-4" /> CSV
        </button>

        <button
          class="flex items-center gap-1.5 px-3 py-2 text-sm rounded-md border border-[var(--color-border)] hover:bg-[var(--color-surface)] disabled:opacity-50"
          :disabled="glossaryImportLoading"
          title="Ausgefüllte Datei zurückspielen — übernommene Werte gelten als geprüft"
          @click="glossaryFileInput?.click()"
        >
          <Upload class="w-4 h-4" />
          {{ glossaryImportLoading ? 'Wird eingelesen…' : 'Zurückspielen' }}
        </button>

        <input
          ref="glossaryFileInput"
          type="file"
          accept=".xlsx,.xls,.csv"
          class="hidden"
          @change="onGlossaryFile"
        />
      </div>

      <!-- Table -->
      <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
          <thead>
            <tr class="border-b border-[var(--color-border)] bg-[var(--color-bg)]">
              <th class="text-left px-4 py-3 font-medium text-[var(--color-text-secondary)]">
                Quelltext <span class="font-normal text-xs text-[var(--color-text-tertiary)]">({{ sourceLangLabel }})</span>
              </th>
              <th class="text-left px-4 py-3 font-medium text-[var(--color-text-secondary)]">Bereich</th>
              <th class="text-left px-4 py-3 font-medium text-[var(--color-text-secondary)]">
                Übersetzung <span class="font-normal text-xs text-[var(--color-text-tertiary)]">({{ selectedLang.toUpperCase() }})</span>
              </th>
              <th class="text-left px-4 py-3 font-medium text-[var(--color-text-secondary)]">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tableLoading" class="border-b border-[var(--color-border)]">
              <td colspan="4" class="px-4 py-8 text-center text-[var(--color-text-tertiary)]">
                <Loader2 class="w-5 h-5 animate-spin mx-auto mb-2" />
                Lade...
              </td>
            </tr>
            <tr v-else-if="displayedUnits.length === 0" class="border-b border-[var(--color-border)]">
              <td colspan="4" class="px-4 py-8 text-center text-[var(--color-text-tertiary)]">
                Keine Einträge gefunden
              </td>
            </tr>
            <tr
              v-for="unit in displayedUnits"
              :key="unit.id"
              class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-pointer transition-colors"
              @click="openUnit(unit)"
            >
              <td class="px-4 py-3 text-[var(--color-text-primary)] font-medium max-w-[300px] truncate">
                {{ unit.source_text }}
              </td>
              <td class="px-4 py-3 text-[var(--color-text-secondary)]">
                {{ unit.domain || '—' }}
              </td>
              <td class="px-4 py-3 max-w-[300px] truncate" :class="translationOf(unit) ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)]'">
                {{ translationOf(unit)?.translation || '—' }}
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium',
                    statusOf(unit).class,
                  ]"
                >
                  <Check v-if="statusOf(unit).done" class="w-3 h-3" />
                  <AlertCircle v-else class="w-3 h-3" />
                  {{ statusOf(unit).label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mt-4">
        <div class="text-sm text-[var(--color-text-tertiary)]">
          {{ pagination.total }} Einträge
        </div>
        <div class="flex gap-1">
          <template v-for="(p, idx) in paginationPages" :key="idx">
            <span
              v-if="p === '...'"
              class="px-2 py-1.5 text-sm text-[var(--color-text-tertiary)]"
            >…</span>
            <button
              v-else
              :class="[
                'px-3 py-1.5 text-sm rounded-md',
                p === pagination.currentPage
                  ? 'bg-[var(--color-accent)] text-white'
                  : 'bg-[var(--color-surface)] border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'
              ]"
              @click="goToPage(p)"
            >
              {{ p }}
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- Tab: Settings -->
    <div v-if="activeTab === 'settings'" class="space-y-6">
      <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 sm:p-6">
        <h2 class="text-base font-medium text-[var(--color-text-primary)] mb-4">TMS Aktionen</h2>
        <div class="flex flex-col gap-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3 border-b border-[var(--color-border)]">
            <div>
              <div class="text-sm font-medium text-[var(--color-text-primary)]">PIM-Daten an TMS senden</div>
              <div class="text-xs text-[var(--color-text-tertiary)] mt-0.5">Alle Metadaten-Entitäten an den Translation Memory Service übertragen</div>
            </div>
            <button
              class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium bg-[var(--color-accent)] text-white rounded-md hover:opacity-90 disabled:opacity-50 shrink-0 w-full sm:w-auto"
              :disabled="ingestLoading"
              @click="triggerIngest"
            >
              <Upload v-if="!ingestLoading" class="w-4 h-4" />
              <Loader2 v-else class="w-4 h-4 animate-spin" />
              Ingest starten
            </button>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3 border-b border-[var(--color-border)]">
            <div>
              <div class="text-sm font-medium text-[var(--color-text-primary)]">Übersetzungen synchronisieren</div>
              <div class="text-xs text-[var(--color-text-tertiary)] mt-0.5">TMS-Übersetzungen in die PIM-Datenbank zurückschreiben (name_json Felder)</div>
            </div>
            <button
              class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium bg-[var(--color-accent)] text-white rounded-md hover:opacity-90 disabled:opacity-50 shrink-0 w-full sm:w-auto"
              :disabled="syncLoading"
              @click="triggerSync"
            >
              <Download v-if="!syncLoading" class="w-4 h-4" />
              <Loader2 v-else class="w-4 h-4 animate-spin" />
              Sync starten
            </button>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3 border-b border-[var(--color-border)]">
            <div>
              <div class="text-sm font-medium text-[var(--color-text-primary)]">Statistiken aktualisieren</div>
              <div class="text-xs text-[var(--color-text-tertiary)] mt-0.5">Übersetzungs-Statistiken neu laden</div>
            </div>
            <button
              class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium border border-[var(--color-border)] rounded-md hover:bg-[var(--color-bg)] shrink-0 w-full sm:w-auto"
              @click="refreshStats"
            >
              <RefreshCw class="w-4 h-4" />
              Aktualisieren
            </button>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3 border-b border-[var(--color-border)]">
            <div>
              <div class="text-sm font-medium text-red-600">Alle Übersetzungen löschen</div>
              <div class="text-xs text-[var(--color-text-tertiary)] mt-0.5">Alle gespeicherten Übersetzungen unwiderruflich entfernen (Quellbegriffe bleiben erhalten)</div>
            </div>
            <button
              class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium border border-red-300 text-red-600 rounded-md hover:bg-red-50 disabled:opacity-50 shrink-0 w-full sm:w-auto"
              :disabled="deleteLoading"
              @click="deleteAllTranslations"
            >
              <Trash2 v-if="!deleteLoading" class="w-4 h-4" />
              <Loader2 v-else class="w-4 h-4 animate-spin" />
              Übersetzungen löschen
            </button>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
            <div>
              <div class="text-sm font-medium text-red-600">Alle Begriffe löschen</div>
              <div class="text-xs text-[var(--color-text-tertiary)] mt-0.5">Alle Quellbegriffe und deren Übersetzungen unwiderruflich entfernen. Per Ingest können sie neu erzeugt werden.</div>
            </div>
            <button
              class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium border border-red-300 text-red-600 rounded-md hover:bg-red-50 disabled:opacity-50 shrink-0 w-full sm:w-auto"
              :disabled="purgeLoading"
              @click="purgeAllUnits"
            >
              <Trash2 v-if="!purgeLoading" class="w-4 h-4" />
              <Loader2 v-else class="w-4 h-4 animate-spin" />
              Begriffe löschen
            </button>
          </div>
        </div>

        <!-- Action Log -->
        <div v-if="actionLog.length" class="mt-4 border-t border-[var(--color-border)] pt-4">
          <div class="flex items-center justify-between mb-2">
            <div class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wide">Protokoll</div>
            <button class="text-xs text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]" @click="actionLog = []">Leeren</button>
          </div>
          <div class="space-y-1 max-h-48 overflow-y-auto font-mono text-xs">
            <div
              v-for="(entry, i) in actionLog"
              :key="i"
              class="flex gap-2 py-1"
            >
              <span class="text-[var(--color-text-tertiary)] shrink-0">{{ entry.time }}</span>
              <span
                :class="{
                  'text-[var(--color-accent)]': entry.type === 'success',
                  'text-red-500': entry.type === 'error',
                  'text-[var(--color-text-secondary)]': entry.type === 'info',
                }"
              >{{ entry.message }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 sm:p-6">
        <h2 class="text-base font-medium text-[var(--color-text-primary)] mb-4">Konfiguration</h2>
        <div class="text-sm text-[var(--color-text-secondary)] space-y-4">
          <div>
            <p class="mb-1">
              <span class="font-medium text-[var(--color-text-primary)]">Zielsprachen</span>
              werden nicht hier konfiguriert, sondern unter
              <RouterLink to="/languages" class="text-[var(--color-accent)] hover:underline">Sprachen</RouterLink>
              gepflegt. Von dort kommen auch Quellsprache und Reihenfolge.
            </p>
          </div>

          <div>
            <div class="font-medium text-[var(--color-text-primary)] mb-1">Verbindung — in der PIM-<code>.env</code></div>
            <ul class="list-disc list-inside ml-2 space-y-1 text-xs font-mono text-[var(--color-text-tertiary)]">
              <li>TMS_ENABLED — TMS aktivieren/deaktivieren</li>
              <li>TMS_BASE_URL — URL des TMS-Dienstes</li>
              <li>TMS_API_KEY — gemeinsames Kennwort, muss in beiden Dateien identisch sein</li>
            </ul>
          </div>

          <div>
            <div class="font-medium text-[var(--color-text-primary)] mb-1">Übersetzungs-Anbieter — in <code>tms/.env</code></div>
            <ul class="list-disc list-inside ml-2 space-y-1 text-xs font-mono text-[var(--color-text-tertiary)]">
              <li>TMS_API_KEY — Gegenstück zur PIM-.env</li>
              <li>TMS_PROVIDER_CHAIN — Reihenfolge der Anbieter</li>
              <li>DEEPL_API_KEY — DeepL</li>
              <li>GOOGLE_TRANSLATE_API_KEY — Google Translate</li>
              <li>ANTHROPIC_API_KEY — Claude</li>
              <li>OPENAI_API_KEY — OpenAI</li>
            </ul>
            <p class="text-xs mt-2 text-[var(--color-text-tertiary)]">
              Ohne Anbieter-Schlüssel sammelt das Translation Memory nur Begriffe und übersetzt nichts maschinell.
            </p>
          </div>

          <p class="text-xs text-[var(--color-text-tertiary)]">
            Beide Dateien abgleichen, Dienst starten und die Verbindung prüfen:
            <code>sudo bash tms-activate.sh</code>
          </p>
        </div>
      </div>
    </div>

    <!-- Side Panel -->
    <TranslationUnitPanel
      v-if="showPanel && store.currentUnit"
      :unit="store.currentUnit"
      :language-options="languageOptions"
      @close="closePanel"
      @updated="loadUnits"
    />
  </div>
</template>
