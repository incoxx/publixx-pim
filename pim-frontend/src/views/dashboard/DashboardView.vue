<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Settings, Eye, EyeOff, RefreshCw } from 'lucide-vue-next'
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

const store = useDashboardStore()

// Widget-Konfiguration
const STORAGE_KEY = 'pim_dashboard_widgets'

const defaultWidgets = [
  { id: 'welcome', label: 'Begrüßung', visible: true },
  { id: 'stats', label: 'Übersicht', visible: true },
  { id: 'quality', label: 'Datenqualität', visible: true },
  { id: 'activity', label: 'Aktivitäten', visible: true },
  { id: 'tasks', label: 'Meine Aufgaben', visible: true },
  { id: 'dataflows', label: 'Datenflüsse', visible: true },
  { id: 'workflow', label: 'Workflow-Status', visible: true },
  { id: 'recent', label: 'Zuletzt bearbeitet', visible: true },
  { id: 'completeness', label: 'Produkt-Füllstand', visible: false },
]

function loadWidgetConfig() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      const parsed = JSON.parse(saved)
      return defaultWidgets.map(dw => {
        const found = parsed.find(p => p.id === dw.id)
        return found ? { ...dw, visible: found.visible } : { ...dw }
      })
    }
  } catch { /* ignore */ }
  return defaultWidgets.map(w => ({ ...w }))
}

const widgets = ref(loadWidgetConfig())
const showConfig = ref(false)

function saveWidgetConfig() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(widgets.value))
}

function toggleWidget(id) {
  const w = widgets.value.find(w => w.id === id)
  if (w) {
    w.visible = !w.visible
    saveWidgetConfig()
  }
}

function moveWidget(index, direction) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= widgets.value.length) return
  const temp = widgets.value[index]
  widgets.value[index] = widgets.value[newIndex]
  widgets.value[newIndex] = temp
  saveWidgetConfig()
}

function isVisible(id) {
  return widgets.value.find(w => w.id === id)?.visible ?? true
}

// Auto-Refresh (60 Sekunden)
const AUTO_REFRESH_INTERVAL = 60000
let refreshTimer = null
const lastRefresh = ref(null)

async function refresh() {
  await store.fetchDashboard()
  lastRefresh.value = new Date()
}

onMounted(() => {
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
        <!-- Auto-Refresh-Indikator -->
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

      <!-- Reihe 3: Datenqualität (1/3) + Aktivitäten (2/3) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <DataQualityWidget
          v-if="isVisible('quality')"
          :quality="store.dataQuality"
        />
        <div v-if="isVisible('activity')" class="lg:col-span-2">
          <ActivityFeedWidget :items="store.activityFeed" />
        </div>
      </div>

      <!-- Reihe 4: Meine Aufgaben (1/2) + Datenflüsse (1/2) -->
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

      <!-- Reihe 5: Workflow (1/3) + Zuletzt bearbeitet (2/3) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <WorkflowStatusWidget
          v-if="isVisible('workflow')"
          :summary="store.workflowSummary"
        />
        <div v-if="isVisible('recent')" class="lg:col-span-2">
          <RecentlyEditedWidget :products="store.recentlyEdited" />
        </div>
      </div>

      <!-- Reihe 6: Produkt-Füllstand (optional, standardmäßig ausgeblendet) -->
      <CompletenessWidget
        v-if="isVisible('completeness')"
        :summary="store.completenessSummary"
      />
    </template>
  </div>
</template>
