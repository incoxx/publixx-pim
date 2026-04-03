<script setup>
import { ref, computed, onMounted, onUnmounted, markRaw } from 'vue'
import { Settings, Eye, EyeOff, RefreshCw, Plus, GripVertical, LayoutDashboard, Star, Save, Trash2, Check } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { useAuthStore } from '@/stores/auth'
import dashboardPresetsApi from '@/api/dashboardPresets'
import WelcomeWidget from '@/components/dashboard/WelcomeWidget.vue'
import StatsOverviewWidget from '@/components/dashboard/StatsOverviewWidget.vue'
import DataQualityWidget from '@/components/dashboard/DataQualityWidget.vue'
import ActivityFeedWidget from '@/components/dashboard/ActivityFeedWidget.vue'
import DataFlowWidget from '@/components/dashboard/DataFlowWidget.vue'
import MyTasksWidget from '@/components/dashboard/MyTasksWidget.vue'
import RecentlyEditedWidget from '@/components/dashboard/RecentlyEditedWidget.vue'
import WorkflowStatusWidget from '@/components/dashboard/WorkflowStatusWidget.vue'
import CompletenessWidget from '@/components/dashboard/CompletenessWidget.vue'
import ProfileStatCard from '@/components/dashboard/ProfileStatCard.vue'
import ProfileCardConfigurator from '@/components/dashboard/ProfileCardConfigurator.vue'
import QuickLinksWidget from '@/components/dashboard/QuickLinksWidget.vue'
import WatchlistWidget from '@/components/dashboard/WatchlistWidget.vue'
import NotesWidget from '@/components/dashboard/NotesWidget.vue'

const store = useDashboardStore()
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.user?.roles?.some(r => r.name === 'Admin'))

// ─── Widget-Registry: jedes Widget mit Span-Definition ────────────────
// span: 6=full, 4=two-thirds, 3=half, 2=third
const WIDGET_REGISTRY = {
  welcome:      { label: 'Begrüßung',        span: 6, component: markRaw(WelcomeWidget) },
  stats:        { label: 'Übersicht',         span: 6, component: markRaw(StatsOverviewWidget) },
  quality:      { label: 'Datenqualität',     span: 2, component: markRaw(DataQualityWidget) },
  activity:     { label: 'Aktivitäten',       span: 4, component: markRaw(ActivityFeedWidget) },
  tasks:        { label: 'Meine Aufgaben',    span: 3, component: markRaw(MyTasksWidget) },
  dataflows:    { label: 'Datenflüsse',       span: 3, component: markRaw(DataFlowWidget) },
  quicklinks:   { label: 'Schnellzugriff',    span: 2, component: markRaw(QuickLinksWidget) },
  watchlist:    { label: 'Merkliste',          span: 2, component: markRaw(WatchlistWidget) },
  notes:        { label: 'Notizen',            span: 2, component: markRaw(NotesWidget) },
  workflow:     { label: 'Workflow-Status',    span: 2, component: markRaw(WorkflowStatusWidget) },
  recent:       { label: 'Zuletzt bearbeitet', span: 4, component: markRaw(RecentlyEditedWidget) },
  completeness: { label: 'Produkt-Füllstand', span: 6, component: markRaw(CompletenessWidget) },
}

const DEFAULT_ORDER = Object.keys(WIDGET_REGISTRY)

// Widget-Config (aus Server oder Default)
const widgets = ref(DEFAULT_ORDER.map(id => ({
  id,
  label: WIDGET_REGISTRY[id].label,
  visible: id !== 'completeness',
})))
const profileCards = ref([])
const showConfig = ref(false)

// Configurator State
const showConfigurator = ref(false)
const configuratorTarget = ref(null)

// Preset State
const showPresetPanel = ref(false)
const presetSaveName = ref('')
const activePresetId = ref(null) // null = Standard, sonst Preset-UUID

// Eingebautes Standard-Layout (immer vorhanden, nicht löschbar)
const BUILTIN_DEFAULT_PAYLOAD = {
  widgets: DEFAULT_ORDER.map(id => ({
    id,
    visible: id !== 'completeness',
  })),
}

function loadBuiltinDefault() {
  activePresetId.value = null
  applyServerConfig(BUILTIN_DEFAULT_PAYLOAD)
  store.saveDashboardConfig(BUILTIN_DEFAULT_PAYLOAD)
  showPresetPanel.value = false
}

// ─── Computed: sichtbare Widgets in Reihenfolge ────────────────
const visibleWidgets = computed(() =>
  widgets.value.filter(w => w.visible && WIDGET_REGISTRY[w.id])
)

// Span-Klasse für das 6-Spalten-Grid
function spanClass(widgetId) {
  const span = WIDGET_REGISTRY[widgetId]?.span || 6
  return {
    2: 'lg:col-span-2',
    3: 'lg:col-span-3',
    4: 'lg:col-span-4',
    6: 'lg:col-span-6',
  }[span] || 'lg:col-span-6'
}

// Props für jede Widget-Komponente
function widgetProps(widgetId) {
  switch (widgetId) {
    case 'welcome':      return { welcome: store.welcome }
    case 'stats':        return { stats: store.stats, trends: store.trends }
    case 'quality':      return { quality: store.dataQuality }
    case 'activity':     return { items: store.activityFeed }
    case 'tasks':        return { tasks: store.myTasks }
    case 'dataflows':    return { flows: store.dataFlows }
    case 'workflow':     return { summary: store.workflowSummary }
    case 'recent':       return { products: store.recentlyEdited }
    case 'completeness': return { summary: store.completenessSummary }
    default:             return {}
  }
}

function applyServerConfig(config) {
  if (!config?.widgets) return
  const serverWidgets = config.widgets

  // Standard-Widgets: Reihenfolge aus Server, Visibility übernehmen
  const serverOrder = serverWidgets.filter(sw => !sw.id.startsWith('profile-card-'))
  const orderedWidgets = []

  for (const sw of serverOrder) {
    if (WIDGET_REGISTRY[sw.id]) {
      orderedWidgets.push({
        id: sw.id,
        label: WIDGET_REGISTRY[sw.id].label,
        visible: sw.visible,
      })
    }
  }
  // Fehlende Defaults anhängen (neue Widgets aus Updates)
  for (const id of DEFAULT_ORDER) {
    if (!orderedWidgets.find(w => w.id === id)) {
      orderedWidgets.push({
        id,
        label: WIDGET_REGISTRY[id].label,
        visible: id !== 'completeness',
      })
    }
  }
  widgets.value = orderedWidgets

  // Profilkarten extrahieren
  profileCards.value = serverWidgets
    .filter(w => w.id.startsWith('profile-card-'))
    .map(w => ({ ...w, visible: w.visible !== false }))
}

async function saveConfig() {
  const allWidgets = [
    ...widgets.value.map(w => ({ id: w.id, visible: w.visible })),
    ...profileCards.value.map(pc => ({
      id: pc.id,
      visible: pc.visible !== false,
      search_profile_id: pc.search_profile_id,
      chart_type: pc.chart_type,
      metric: pc.metric,
      color: pc.color,
    })),
  ]
  await store.saveDashboardConfig({ widgets: allWidgets })
}

function toggleWidget(id) {
  const w = widgets.value.find(w => w.id === id)
  if (w) {
    w.visible = !w.visible
    saveConfig()
  }
}

// ─── Drag & Drop für Widget-Reihenfolge ────────────────
const dragIdx = ref(null)

function onDragStart(index) {
  dragIdx.value = index
}

function onDragOver(e, index) {
  e.preventDefault()
  if (dragIdx.value === null || dragIdx.value === index) return
  const dragged = widgets.value.splice(dragIdx.value, 1)[0]
  widgets.value.splice(index, 0, dragged)
  dragIdx.value = index
}

function onDragEnd() {
  dragIdx.value = null
  saveConfig()
}

// ─── Profilkarten-Verwaltung ────────────────
function addProfileCard() {
  configuratorTarget.value = { id: `profile-card-${Date.now()}`, search_profile_id: '', chart_type: 'gauge', metric: 'completeness', color: '#2E75B6' }
  showConfigurator.value = true
}

function configureProfileCard(config) {
  configuratorTarget.value = { ...config }
  showConfigurator.value = true
}

function saveProfileCard(formData) {
  const existing = profileCards.value.findIndex(pc => pc.id === configuratorTarget.value.id)
  const card = { ...configuratorTarget.value, ...formData, visible: true }

  if (existing >= 0) {
    profileCards.value[existing] = card
  } else {
    profileCards.value.push(card)
  }

  showConfigurator.value = false
  configuratorTarget.value = null
  saveConfig()
}

function removeProfileCard(config) {
  profileCards.value = profileCards.value.filter(pc => pc.id !== config.id)
  showConfigurator.value = false
  configuratorTarget.value = null
  saveConfig()
}

// ─── Dashboard-Presets ────────────────
async function loadPreset(preset) {
  const ok = await store.activatePreset(preset.id)
  if (ok && store.dashboardConfig) {
    applyServerConfig(store.dashboardConfig)
    activePresetId.value = preset.id
  }
  showPresetPanel.value = false
}

// Feedback-Toast
const savedFeedback = ref(null)
let feedbackTimer = null
function showSavedFeedback(name) {
  savedFeedback.value = name
  clearTimeout(feedbackTimer)
  feedbackTimer = setTimeout(() => { savedFeedback.value = null }, 2000)
}

async function overwritePreset(preset) {
  try {
    const currentConfig = store.dashboardConfig
    if (!currentConfig?.widgets) return
    await dashboardPresetsApi.update(preset.id, { payload: currentConfig })
    await store.loadPresets()
    showSavedFeedback(preset.name)
  } catch { /* ignore */ }
}

async function saveAsPreset() {
  if (!presetSaveName.value.trim()) return
  const name = presetSaveName.value.trim()
  try {
    await dashboardPresetsApi.saveFromCurrent({ name })
    presetSaveName.value = ''
    await store.loadPresets()
    showSavedFeedback(name)
  } catch { /* ignore */ }
}

// Löschen mit Bestätigung
const confirmDeletePreset = ref(null)
let confirmTimer = null

function requestDeletePreset(preset) {
  confirmDeletePreset.value = preset.id
  clearTimeout(confirmTimer)
  confirmTimer = setTimeout(() => { confirmDeletePreset.value = null }, 4000)
}

async function executeDeletePreset(preset) {
  confirmDeletePreset.value = null
  try {
    await dashboardPresetsApi.remove(preset.id)
    if (activePresetId.value === preset.id) {
      activePresetId.value = null
    }
    await store.loadPresets()
  } catch { /* ignore */ }
}

// ─── Auto-Refresh ────────────────
const AUTO_REFRESH_INTERVAL = 60000
let refreshTimer = null
const lastRefresh = ref(null)

async function refresh() {
  await store.fetchDashboard()
  lastRefresh.value = new Date()
}

onMounted(async () => {
  await store.loadDashboardConfig()
  if (store.dashboardConfig) {
    applyServerConfig(store.dashboardConfig)
  }

  store.loadPresets()
  store.fetchDashboard().then(() => { lastRefresh.value = new Date() })
  refreshTimer = setInterval(refresh, AUTO_REFRESH_INTERVAL)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Dashboard</h2>
      <div class="flex items-center gap-2">
        <span v-if="lastRefresh" class="text-[10px] text-[var(--color-text-tertiary)] hidden sm:inline">
          Auto-Refresh 60s
        </span>
        <button
          class="pim-btn pim-btn-ghost text-xs"
          @click="refresh"
          :disabled="store.loading"
        >
          <RefreshCw class="w-4 h-4" :class="store.loading ? 'animate-spin' : ''" :stroke-width="2" />
        </button>

        <!-- Preset-Selektor -->
        <div class="relative">
          <button
            class="pim-btn pim-btn-ghost text-xs"
            @click="showPresetPanel = !showPresetPanel; showConfig = false"
          >
            <LayoutDashboard class="w-4 h-4" :stroke-width="2" />
            <span class="hidden sm:inline">Layouts</span>
          </button>
          <div
            v-if="showPresetPanel"
            class="absolute right-0 top-full mt-1 w-72 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg z-30"
          >
            <p class="px-3 pb-2 text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider border-b border-[var(--color-border)]">
              Dashboard-Layouts
            </p>

            <div class="max-h-56 overflow-y-auto">
              <!-- Eingebautes Standard-Layout (immer da, nicht löschbar) -->
              <div
                class="flex items-center gap-2 px-3 py-2 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
                :class="{ 'bg-[var(--color-accent)]/5': activePresetId === null }"
                @click="loadBuiltinDefault"
              >
                <Check v-if="activePresetId === null" class="w-3 h-3 text-[var(--color-accent)] shrink-0" :stroke-width="2.5" />
                <Star v-else class="w-3 h-3 text-amber-500 shrink-0 fill-amber-500" :stroke-width="2" />
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium" :class="activePresetId === null ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-primary)]'">Standard</p>
                  <p class="text-[10px] text-[var(--color-text-tertiary)]">Werkseinstellung</p>
                </div>
              </div>

              <!-- Gespeicherte Layouts -->
              <div
                v-for="preset in store.presets"
                :key="preset.id"
                class="px-3 py-2 hover:bg-[var(--color-bg)] transition-colors group"
                :class="{ 'bg-[var(--color-accent)]/5': activePresetId === preset.id }"
              >
                <div class="flex items-center gap-2">
                  <Check v-if="activePresetId === preset.id" class="w-3 h-3 text-[var(--color-accent)] shrink-0" :stroke-width="2.5" />
                  <LayoutDashboard v-else class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
                  <div class="flex-1 min-w-0 cursor-pointer" @click="loadPreset(preset)">
                    <p class="text-xs font-medium truncate" :class="activePresetId === preset.id ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-primary)]'">{{ preset.name }}</p>
                    <p class="text-[9px] text-[var(--color-text-tertiary)]">von {{ preset.creator?.name }}</p>
                  </div>
                  <div v-if="isAdmin" class="flex items-center gap-0.5 shrink-0">
                    <button
                      v-if="activePresetId === preset.id"
                      class="p-0.5 rounded hover:bg-[var(--color-accent)]/10"
                      title="Änderungen in dieses Layout speichern"
                      @click.stop="overwritePreset(preset)"
                    >
                      <Save class="w-3 h-3 text-[var(--color-accent)]" :stroke-width="2" />
                    </button>
                    <button
                      class="p-0.5 rounded hover:bg-[var(--color-bg-elevated,var(--color-bg))] opacity-0 group-hover:opacity-100 transition-opacity"
                      title="Löschen"
                      @click.stop="requestDeletePreset(preset)"
                    >
                      <Trash2 class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                    </button>
                  </div>
                </div>
                <!-- Lösch-Bestätigung -->
                <div
                  v-if="confirmDeletePreset === preset.id"
                  class="flex items-center gap-2 mt-1.5 ml-5 p-1.5 rounded bg-red-50 border border-red-200"
                >
                  <p class="text-[10px] text-red-700 flex-1">„{{ preset.name }}" löschen?</p>
                  <button class="text-[10px] font-medium text-red-600 hover:text-red-800 px-1.5 py-0.5 rounded hover:bg-red-100" @click.stop="executeDeletePreset(preset)">Ja</button>
                  <button class="text-[10px] text-[var(--color-text-tertiary)] px-1.5 py-0.5 rounded hover:bg-[var(--color-bg)]" @click.stop="confirmDeletePreset = null">Nein</button>
                </div>
              </div>
            </div>

            <!-- Gespeichert-Feedback -->
            <div v-if="savedFeedback" class="px-3 py-1.5 border-t border-[var(--color-border)]">
              <p class="text-[10px] text-green-600 font-medium flex items-center gap-1">
                <Check class="w-3 h-3" :stroke-width="2.5" /> „{{ savedFeedback }}" gespeichert
              </p>
            </div>
            <div v-if="isAdmin" class="border-t border-[var(--color-border)] mt-1 pt-1">
              <p class="px-3 pt-1 pb-1 text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">
                Layout speichern
              </p>
              <div class="px-3 py-2 space-y-2">
                <input v-model="presetSaveName" class="pim-input text-xs w-full" placeholder="Name des Layouts..." maxlength="100" @keydown.enter="saveAsPreset" />
                <button
                  class="pim-btn pim-btn-primary text-xs w-full"
                  @click="saveAsPreset"
                  :disabled="!presetSaveName.trim()"
                >
                  <Save class="w-3.5 h-3.5" :stroke-width="2" /> Aktuelles Layout speichern
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Widget-Config -->
        <div class="relative">
          <button
            class="pim-btn pim-btn-ghost text-xs"
            @click="showConfig = !showConfig; showPresetPanel = false"
          >
            <Settings class="w-4 h-4" :stroke-width="2" />
            <span class="hidden sm:inline">Widgets</span>
          </button>
          <div
            v-if="showConfig"
            class="absolute right-0 top-full mt-1 w-64 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg z-30"
          >
            <p class="px-3 pb-2 text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider border-b border-[var(--color-border)]">
              Kacheln ein-/ausblenden &amp; sortieren
            </p>
            <div
              v-for="(w, index) in widgets"
              :key="w.id"
              draggable="true"
              @dragstart="onDragStart(index)"
              @dragover="onDragOver($event, index)"
              @dragend="onDragEnd"
              class="flex items-center gap-1 px-2 py-1.5 hover:bg-[var(--color-bg)] transition-colors cursor-grab active:cursor-grabbing"
              :class="{ 'opacity-40': dragIdx === index }"
            >
              <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
              <button
                class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]"
                @click="toggleWidget(w.id)"
              >
                <Eye v-if="w.visible" class="w-3.5 h-3.5 text-[var(--color-accent)]" :stroke-width="2" />
                <EyeOff v-else class="w-3.5 h-3.5 opacity-40" :stroke-width="2" />
              </button>
              <span class="text-xs flex-1" :class="w.visible ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)] line-through'">{{ w.label }}</span>
            </div>
            <div class="border-t border-[var(--color-border)] mt-1 pt-1">
              <p class="px-3 pb-1 text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider">
                Profilkarten
              </p>
              <div
                v-for="pc in profileCards"
                :key="pc.id"
                class="flex items-center gap-2 px-3 py-1.5 hover:bg-[var(--color-bg)] transition-colors cursor-pointer"
                @click="configureProfileCard(pc)"
              >
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: pc.color }" />
                <span class="text-xs text-[var(--color-text-primary)] flex-1 truncate">{{ pc.title || 'Profilkarte' }}</span>
              </div>
              <button
                class="flex items-center gap-2 w-full px-3 py-1.5 hover:bg-[var(--color-bg)] transition-colors text-xs text-[var(--color-accent)]"
                @click="addProfileCard(); showConfig = false"
              >
                <Plus class="w-3.5 h-3.5" :stroke-width="2" />
                <span>Profilkarte hinzufügen</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Close panels on outside click -->
    <div v-if="showConfig || showPresetPanel" class="fixed inset-0 z-20" @click="showConfig = false; showPresetPanel = false" />

    <!-- Loading state -->
    <div v-if="store.loading && !store.stats" class="flex items-center justify-center py-20">
      <div class="w-6 h-6 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Dynamisches Widget-Grid: 6-Spalten, Reihenfolge aus Config -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-6 gap-5">
      <!-- Profilkarten-Reihe (nur wenn Karten vorhanden) -->
      <div v-if="profileCards.length" class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <ProfileStatCard
          v-for="pc in profileCards"
          :key="pc.id"
          :config="pc"
          @configure="configureProfileCard"
          @remove="removeProfileCard"
        />
      </div>

      <!-- Widgets in Reihenfolge aus Config -->
      <div
        v-for="w in visibleWidgets"
        :key="w.id"
        :class="spanClass(w.id)"
        class="col-span-1"
      >
        <component
          :is="WIDGET_REGISTRY[w.id].component"
          v-bind="widgetProps(w.id)"
        />
      </div>
    </div>

    <!-- Profilkarten-Konfigurator -->
    <ProfileCardConfigurator
      v-if="showConfigurator"
      :config="configuratorTarget"
      @save="saveProfileCard"
      @cancel="showConfigurator = false; configuratorTarget = null"
      @remove="removeProfileCard"
    />
  </div>
</template>
