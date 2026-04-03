<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Settings, Eye, EyeOff, RefreshCw, Plus, GripVertical, LayoutDashboard, Star, Save, Trash2 } from 'lucide-vue-next'
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

// Standard-Widgets (nicht-Profil)
const defaultWidgets = [
  { id: 'welcome', label: 'Begrüßung', visible: true },
  { id: 'stats', label: 'Übersicht', visible: true },
  { id: 'quality', label: 'Datenqualität', visible: true },
  { id: 'activity', label: 'Aktivitäten', visible: true },
  { id: 'tasks', label: 'Meine Aufgaben', visible: true },
  { id: 'dataflows', label: 'Datenflüsse', visible: true },
  { id: 'quicklinks', label: 'Schnellzugriff', visible: true },
  { id: 'watchlist', label: 'Merkliste', visible: true },
  { id: 'notes', label: 'Notizen', visible: true },
  { id: 'workflow', label: 'Workflow-Status', visible: true },
  { id: 'recent', label: 'Zuletzt bearbeitet', visible: true },
  { id: 'completeness', label: 'Produkt-Füllstand', visible: false },
]

// Widget-Config (aus Server oder Default)
const widgets = ref(defaultWidgets.map(w => ({ ...w })))
const profileCards = ref([])
const showConfig = ref(false)

// Configurator State
const showConfigurator = ref(false)
const configuratorTarget = ref(null)

// Preset State
const showPresetPanel = ref(false)
const presetSaveName = ref('')
const presetSaveDesc = ref('')
const showPresetSave = ref(false)

function applyServerConfig(config) {
  if (!config?.widgets) return
  const serverWidgets = config.widgets

  // Standard-Widgets: Reihenfolge aus Server, Visibility übernehmen
  const serverOrder = serverWidgets.filter(sw => !sw.id.startsWith('profile-card-'))
  const orderedWidgets = []

  // Erst die Server-Reihenfolge
  for (const sw of serverOrder) {
    const dw = defaultWidgets.find(d => d.id === sw.id)
    if (dw) orderedWidgets.push({ ...dw, visible: sw.visible })
  }
  // Dann fehlende Defaults anhängen (neue Widgets)
  for (const dw of defaultWidgets) {
    if (!orderedWidgets.find(w => w.id === dw.id)) {
      orderedWidgets.push({ ...dw })
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

function isVisible(id) {
  return widgets.value.find(w => w.id === id)?.visible ?? true
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
  }
  showPresetPanel.value = false
}

async function saveAsPreset() {
  if (!presetSaveName.value.trim()) return
  try {
    await dashboardPresetsApi.saveFromCurrent({
      name: presetSaveName.value.trim(),
      description: presetSaveDesc.value.trim() || null,
    })
    presetSaveName.value = ''
    presetSaveDesc.value = ''
    showPresetSave.value = false
    await store.loadPresets()
  } catch { /* ignore */ }
}

async function setDefaultPreset(preset) {
  await dashboardPresetsApi.setDefault(preset.id)
  await store.loadPresets()
}

async function deletePreset(preset) {
  try {
    await dashboardPresetsApi.remove(preset.id)
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

            <!-- Preset-Liste -->
            <div v-if="store.presets.length" class="max-h-48 overflow-y-auto">
              <div
                v-for="preset in store.presets"
                :key="preset.id"
                class="flex items-center gap-2 px-3 py-2 hover:bg-[var(--color-bg)] transition-colors group"
              >
                <Star v-if="preset.is_default" class="w-3 h-3 text-amber-500 shrink-0 fill-amber-500" :stroke-width="2" />
                <Star v-else class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0 opacity-30" :stroke-width="2" />
                <div class="flex-1 min-w-0 cursor-pointer" @click="loadPreset(preset)">
                  <p class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ preset.name }}</p>
                  <p v-if="preset.description" class="text-[10px] text-[var(--color-text-tertiary)] truncate">{{ preset.description }}</p>
                  <p class="text-[9px] text-[var(--color-text-tertiary)]">von {{ preset.creator?.name }}</p>
                </div>
                <div v-if="isAdmin" class="hidden group-hover:flex items-center gap-0.5 shrink-0">
                  <button v-if="!preset.is_default" class="p-0.5 rounded hover:bg-[var(--color-bg-elevated,var(--color-bg))]" title="Als Standard" @click.stop="setDefaultPreset(preset)">
                    <Star class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                  </button>
                  <button v-if="!preset.is_default" class="p-0.5 rounded hover:bg-[var(--color-bg-elevated,var(--color-bg))]" title="Löschen" @click.stop="deletePreset(preset)">
                    <Trash2 class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="px-3 py-3 text-xs text-[var(--color-text-tertiary)] text-center">Keine Layouts vorhanden</p>

            <!-- Admin: Als Preset speichern -->
            <div v-if="isAdmin" class="border-t border-[var(--color-border)] mt-1 pt-1">
              <div v-if="!showPresetSave">
                <button class="w-full flex items-center gap-2 px-3 py-2 text-xs text-[var(--color-accent)] hover:bg-[var(--color-bg)]" @click="showPresetSave = true">
                  <Save class="w-3.5 h-3.5" :stroke-width="2" /> Aktuelles Layout speichern
                </button>
              </div>
              <div v-else class="px-3 py-2 space-y-2">
                <input v-model="presetSaveName" class="pim-input text-xs w-full" placeholder="Name des Layouts..." maxlength="100" @keydown.enter="saveAsPreset" />
                <input v-model="presetSaveDesc" class="pim-input text-xs w-full" placeholder="Beschreibung (optional)..." maxlength="500" />
                <div class="flex gap-2">
                  <button class="pim-btn pim-btn-primary text-xs flex-1" @click="saveAsPreset" :disabled="!presetSaveName.trim()">Speichern</button>
                  <button class="pim-btn pim-btn-ghost text-xs" @click="showPresetSave = false">Abbrechen</button>
                </div>
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
          <!-- Widget-Config-Dropdown -->
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
            <!-- Profilkarten im Dropdown -->
            <div v-if="profileCards.length" class="border-t border-[var(--color-border)] mt-1 pt-1">
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

    <!-- Bento-Grid Widgets -->
    <template v-else>
      <!-- Reihe 1: Welcome (full width) -->
      <WelcomeWidget
        v-if="isVisible('welcome')"
        :welcome="store.welcome"
      />

      <!-- Reihe 2: Stats (full width) -->
      <StatsOverviewWidget
        v-if="isVisible('stats')"
        :stats="store.stats"
        :trends="store.trends"
      />

      <!-- Reihe 3: Profilkarten (dynamisch, scrollbar) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <ProfileStatCard
          v-for="pc in profileCards"
          :key="pc.id"
          :config="pc"
          @configure="configureProfileCard"
          @remove="removeProfileCard"
        />
        <!-- "+ Karte hinzufügen" -->
        <button
          class="pim-card flex flex-col items-center justify-center gap-2 p-6 border-2 border-dashed border-[var(--color-border)] hover:border-[var(--color-accent)] transition-colors cursor-pointer group min-h-[140px]"
          @click="addProfileCard"
        >
          <Plus class="w-6 h-6 text-[var(--color-text-tertiary)] group-hover:text-[var(--color-accent)] transition-colors" :stroke-width="1.5" />
          <span class="text-xs text-[var(--color-text-tertiary)] group-hover:text-[var(--color-accent)] transition-colors">Profilkarte hinzufügen</span>
        </button>
      </div>

      <!-- Reihe 4: Datenqualität (1/3) + Aktivitäten (2/3) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <DataQualityWidget
          v-if="isVisible('quality')"
          :quality="store.dataQuality"
        />
        <div v-if="isVisible('activity')" class="lg:col-span-2">
          <ActivityFeedWidget :items="store.activityFeed" />
        </div>
      </div>

      <!-- Reihe 5: Schnellzugriff + Merkliste + Notizen -->
      <div
        v-if="isVisible('quicklinks') || isVisible('watchlist') || isVisible('notes')"
        class="grid grid-cols-1 gap-5"
        :class="{
          'lg:grid-cols-3': isVisible('quicklinks') && isVisible('watchlist') && isVisible('notes'),
          'lg:grid-cols-2': [isVisible('quicklinks'), isVisible('watchlist'), isVisible('notes')].filter(Boolean).length === 2,
        }"
      >
        <QuickLinksWidget v-if="isVisible('quicklinks')" />
        <WatchlistWidget v-if="isVisible('watchlist')" />
        <NotesWidget v-if="isVisible('notes')" />
      </div>

      <!-- Reihe 6: Meine Aufgaben (1/2) + Datenflüsse (1/2) -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <MyTasksWidget
          v-if="isVisible('tasks')"
          :tasks="store.myTasks"
        />
        <DataFlowWidget
          v-if="isVisible('dataflows')"
          :flows="store.dataFlows"
        />
      </div>

      <!-- Reihe 7: Workflow (1/3) + Zuletzt bearbeitet (2/3) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <WorkflowStatusWidget
          v-if="isVisible('workflow')"
          :summary="store.workflowSummary"
        />
        <div v-if="isVisible('recent')" class="lg:col-span-2">
          <RecentlyEditedWidget :products="store.recentlyEdited" />
        </div>
      </div>

      <!-- Reihe 8: Produkt-Füllstand (optional) -->
      <CompletenessWidget
        v-if="isVisible('completeness')"
        :summary="store.completenessSummary"
      />
    </template>

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
