<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Settings, Eye, EyeOff, RefreshCw, Plus } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
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

function applyServerConfig(config) {
  if (!config?.widgets) return
  const serverWidgets = config.widgets

  // Standard-Widgets aktualisieren
  widgets.value = defaultWidgets.map(dw => {
    const found = serverWidgets.find(sw => sw.id === dw.id)
    return found ? { ...dw, visible: found.visible } : { ...dw }
  })

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

function moveWidget(index, direction) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= widgets.value.length) return
  const temp = widgets.value[index]
  widgets.value[index] = widgets.value[newIndex]
  widgets.value[newIndex] = temp
  saveConfig()
}

function isVisible(id) {
  return widgets.value.find(w => w.id === id)?.visible ?? true
}

// Profilkarten-Verwaltung
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

// Auto-Refresh (60 Sekunden)
const AUTO_REFRESH_INTERVAL = 60000
let refreshTimer = null
const lastRefresh = ref(null)

async function refresh() {
  await store.fetchDashboard()
  lastRefresh.value = new Date()
}

onMounted(async () => {
  // Dashboard-Config vom Server laden
  await store.loadDashboardConfig()
  if (store.dashboardConfig) {
    applyServerConfig(store.dashboardConfig)
  }

  // Dashboard-Daten laden
  store.fetchDashboard().then(() => {
    lastRefresh.value = new Date()
  })
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
        <div class="relative">
          <button
            class="pim-btn pim-btn-ghost text-xs"
            @click="showConfig = !showConfig"
          >
            <Settings class="w-4 h-4" :stroke-width="2" />
            <span>Widgets</span>
          </button>
          <!-- Widget-Config-Dropdown -->
          <div
            v-if="showConfig"
            class="absolute right-0 top-full mt-1 w-64 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg z-30"
          >
            <p class="px-3 pb-2 text-[10px] font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider border-b border-[var(--color-border)]">
              Widgets ein-/ausblenden
            </p>
            <div
              v-for="(w, index) in widgets"
              :key="w.id"
              class="flex items-center gap-2 px-3 py-1.5 hover:bg-[var(--color-bg)] transition-colors"
            >
              <button
                class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]"
                @click="toggleWidget(w.id)"
              >
                <Eye v-if="w.visible" class="w-3.5 h-3.5" :stroke-width="2" />
                <EyeOff v-else class="w-3.5 h-3.5 opacity-40" :stroke-width="2" />
              </button>
              <span class="text-xs text-[var(--color-text-primary)] flex-1">{{ w.label }}</span>
              <div class="flex items-center gap-0.5">
                <button
                  class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] disabled:opacity-20"
                  :disabled="index === 0"
                  @click="moveWidget(index, -1)"
                >&#9650;</button>
                <button
                  class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] disabled:opacity-20"
                  :disabled="index === widgets.length - 1"
                  @click="moveWidget(index, 1)"
                >&#9660;</button>
              </div>
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

    <!-- Close config on outside click -->
    <div v-if="showConfig" class="fixed inset-0 z-20" @click="showConfig = false" />

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
        class="grid grid-cols-1 lg:grid-cols-3 gap-5"
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

      <!-- Reihe 6: Workflow (1/3) + Zuletzt bearbeitet (2/3) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <WorkflowStatusWidget
          v-if="isVisible('workflow')"
          :summary="store.workflowSummary"
        />
        <div v-if="isVisible('recent')" class="lg:col-span-2">
          <RecentlyEditedWidget :products="store.recentlyEdited" />
        </div>
      </div>

      <!-- Reihe 7: Produkt-Füllstand (optional) -->
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
