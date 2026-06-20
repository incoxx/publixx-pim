<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Package, Image, Star, FileBarChart, Upload, Languages, LayoutTemplate, RefreshCw } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { useAuthStore } from '@/stores/auth'
import { useQuickSearchStore } from '@/stores/quickSearch'
import NotesWidget from '@/components/dashboard/NotesWidget.vue'
import WatchlistWidget from '@/components/dashboard/WatchlistWidget.vue'
import RecentlyEditedWidget from '@/components/dashboard/RecentlyEditedWidget.vue'
import MyTasksWidget from '@/components/dashboard/MyTasksWidget.vue'
import CompletenessWidget from '@/components/dashboard/CompletenessWidget.vue'
import DataQualityWidget from '@/components/dashboard/DataQualityWidget.vue'

const router = useRouter()
const store = useDashboardStore()
const authStore = useAuthStore()
const searchStore = useQuickSearchStore()

// ─── Begrüßung ─────────────────────────────────────────────
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 11) return 'Guten Morgen'
  if (h < 18) return 'Hallo'
  return 'Guten Abend'
})

// ─── Zone A: Hero-Suche ────────────────────────────────────
const term = ref('')
function runSearch() {
  const q = term.value.trim()
  if (!q) {
    router.push('/quick-search')
    return
  }
  searchStore.query = q
  searchStore.search(q)
  router.push('/quick-search')
}

// ─── Zone B: Schnellaktionen (Standard-Set, permission-gefiltert) ──
const ALL_TILES = [
  { label: 'Produkte',      to: '/products',         icon: Package,        permission: 'products.view' },
  { label: 'Medien',        to: '/media',            icon: Image,          permission: 'media.view' },
  { label: 'Merkliste',     to: '/watchlist',        icon: Star,           permission: null },
  { label: 'Berichte',      to: '/reports',          icon: FileBarChart,   permission: 'reports.view' },
  { label: 'Übersetzungen', to: '/translation-jobs', icon: Languages,      permission: 'translations.view' },
  { label: 'Kataloge',      to: '/catalog-templates', icon: LayoutTemplate, permission: 'catalog-templates.view' },
  { label: 'Import',        to: '/imports',          icon: Upload,         permission: 'imports.view' },
]
const tiles = computed(() =>
  ALL_TILES.filter(t => !t.permission || authStore.hasPermission(t.permission))
)

// ─── Daten-Refresh ─────────────────────────────────────────
const AUTO_REFRESH_INTERVAL = 60000
let refreshTimer = null
async function refresh() {
  await store.fetchDashboard()
}

onMounted(() => {
  store.fetchDashboard()
  refreshTimer = setInterval(refresh, AUTO_REFRESH_INTERVAL)
})
onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
  <div class="max-w-6xl mx-auto space-y-8" data-testid="cockpit">
    <!-- Zone A: Begrüßung + Hero-Suche -->
    <section class="pt-2">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold text-[var(--color-text-primary)]">
          {{ greeting }}, {{ authStore.userName }}
        </h1>
        <button class="pim-btn pim-btn-ghost text-xs" :disabled="store.loading" @click="refresh">
          <RefreshCw class="w-4 h-4" :class="store.loading ? 'animate-spin' : ''" :stroke-width="2" />
        </button>
      </div>

      <form class="relative" @submit.prevent="runSearch">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <input
          v-model="term"
          type="text"
          placeholder="Produkte, Medien, Hierarchien suchen …"
          class="w-full h-14 pl-12 pr-28 text-base rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/40 focus:border-[var(--color-accent)] transition"
        />
        <button type="submit" class="pim-btn pim-btn-primary absolute right-2 top-1/2 -translate-y-1/2">
          Suchen
        </button>
      </form>
    </section>

    <!-- Zone B: Schnellaktionen -->
    <section>
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Schnellzugriff</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3">
        <router-link
          v-for="tile in tiles"
          :key="tile.to"
          :to="tile.to"
          class="flex flex-col items-center justify-center gap-2 py-5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-accent)] hover:shadow-sm transition group"
        >
          <component :is="tile.icon" class="w-6 h-6 text-[var(--color-accent)] group-hover:scale-110 transition-transform" :stroke-width="1.75" />
          <span class="text-xs font-medium text-[var(--color-text-secondary)]">{{ tile.label }}</span>
        </router-link>
      </div>
    </section>

    <!-- Zone Arbeitsplatz: Notizen, gepinnte Produkte, zuletzt bearbeitet, Aufgaben -->
    <section>
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Mein Arbeitsplatz</h2>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <WatchlistWidget />
        <NotesWidget />
        <RecentlyEditedWidget :products="store.recentlyEdited" />
        <MyTasksWidget :tasks="store.myTasks" />
      </div>
    </section>

    <!-- Zone KPIs -->
    <section>
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Überblick</h2>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <CompletenessWidget :summary="store.completenessSummary" />
        <DataQualityWidget :quality="store.dataQuality" />
      </div>
    </section>
  </div>
</template>
