<script setup>
import { ref, computed, onMounted } from 'vue'
import { Settings, Eye, EyeOff, GripVertical, RefreshCw } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import StatsOverviewWidget from '@/components/dashboard/StatsOverviewWidget.vue'
import MyTasksWidget from '@/components/dashboard/MyTasksWidget.vue'
import RecentlyEditedWidget from '@/components/dashboard/RecentlyEditedWidget.vue'
import WorkflowStatusWidget from '@/components/dashboard/WorkflowStatusWidget.vue'
import CompletenessWidget from '@/components/dashboard/CompletenessWidget.vue'

const store = useDashboardStore()

// Widget configuration
const STORAGE_KEY = 'pim_dashboard_widgets'

const defaultWidgets = [
  { id: 'stats', label: 'Übersicht', visible: true },
  { id: 'tasks', label: 'Meine Aufgaben', visible: true },
  { id: 'workflow', label: 'Workflow-Status', visible: true },
  { id: 'recent', label: 'Zuletzt bearbeitet', visible: true },
  { id: 'completeness', label: 'Produkt-Füllstand', visible: true },
]

function loadWidgetConfig() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      const parsed = JSON.parse(saved)
      // Merge with defaults (in case new widgets were added)
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

// Get ordered widget IDs for rendering
const orderedWidgets = computed(() => {
  return widgets.value.filter(w => w.visible).map(w => w.id)
})

// Separate stats (always full width) from the grid widgets
const gridWidgets = computed(() => {
  return orderedWidgets.value.filter(id => id !== 'stats')
})

async function refresh() {
  await store.fetchDashboard()
}

onMounted(() => {
  store.fetchDashboard()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Dashboard</h2>
      <div class="flex items-center gap-2">
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
          <!-- Widget config dropdown -->
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
                >▲</button>
                <button
                  class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] disabled:opacity-20"
                  :disabled="index === widgets.length - 1"
                  @click="moveWidget(index, 1)"
                >▼</button>
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

    <!-- Widgets -->
    <template v-else>
      <!-- Stats (always full width, if visible) -->
      <template v-for="wid in orderedWidgets" :key="wid">
        <StatsOverviewWidget
          v-if="wid === 'stats'"
          :stats="store.stats"
        />
      </template>

      <!-- Grid widgets (2/3 + 1/3 layout) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <template v-for="wid in gridWidgets" :key="wid">
          <!-- Left side (2/3 width) -->
          <div v-if="wid === 'tasks'" class="lg:col-span-2">
            <MyTasksWidget :tasks="store.myTasks" />
          </div>
          <div v-else-if="wid === 'recent'" class="lg:col-span-2">
            <RecentlyEditedWidget :products="store.recentlyEdited" />
          </div>

          <!-- Right side (1/3 width) -->
          <div v-else-if="wid === 'workflow'">
            <WorkflowStatusWidget :summary="store.workflowSummary" />
          </div>
          <div v-else-if="wid === 'completeness'">
            <CompletenessWidget :summary="store.completenessSummary" />
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
