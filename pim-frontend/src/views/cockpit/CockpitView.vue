<script setup>
import { ref, computed, onMounted, onUnmounted, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import {
  Search, Package, Image, Star, FileBarChart, Upload, Languages,
  LayoutTemplate, Globe, GitBranch, ClipboardList, RefreshCw,
} from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { useAuthStore } from '@/stores/auth'
import { useQuickSearchStore } from '@/stores/quickSearch'
import userPreferencesApi from '@/api/userPreferences'
import { resolveCockpitProfile } from '@/config/cockpitProfiles'
import NotesWidget from '@/components/dashboard/NotesWidget.vue'
import WatchlistWidget from '@/components/dashboard/WatchlistWidget.vue'
import RecentlyEditedWidget from '@/components/dashboard/RecentlyEditedWidget.vue'
import MyTasksWidget from '@/components/dashboard/MyTasksWidget.vue'
import CompletenessWidget from '@/components/dashboard/CompletenessWidget.vue'
import DataQualityWidget from '@/components/dashboard/DataQualityWidget.vue'
import MediaSpotlightWidget from '@/components/dashboard/MediaSpotlightWidget.vue'
import TranslationStatusWidget from '@/components/dashboard/TranslationStatusWidget.vue'

const router = useRouter()
const store = useDashboardStore()
const authStore = useAuthStore()
const searchStore = useQuickSearchStore()

// ─── Kataloge: ID → konkrete Kachel/Komponente ─────────────
// Schnellaktions-Kacheln (permission = optionales Berechtigungs-Gate)
const TILE_CATALOG = {
  products:            { label: 'Produkte',      to: '/products',          icon: Package,        permission: 'products.view' },
  media:               { label: 'Medien',        to: '/media',             icon: Image,          permission: 'media.view' },
  watchlist:           { label: 'Merkliste',     to: '/watchlist',         icon: Star,           permission: null },
  reports:             { label: 'Berichte',      to: '/reports',           icon: FileBarChart,   permission: 'reports.view' },
  imports:             { label: 'Import',        to: '/imports',           icon: Upload,         permission: 'imports.view' },
  'translation-jobs':  { label: 'Übersetzungen', to: '/translation-jobs',  icon: Languages,      permission: 'translations.view' },
  'catalog-templates': { label: 'Kataloge',      to: '/catalog-templates', icon: LayoutTemplate, permission: 'catalog-templates.view' },
  portals:             { label: 'Portale',       to: '/portal-config',     icon: Globe,          permission: 'portals.view' },
  search:              { label: 'Profisuche',    to: '/search',            icon: Search,         permission: 'search.view' },
  hierarchies:         { label: 'Hierarchien',   to: '/hierarchies',       icon: GitBranch,      permission: 'hierarchies.view' },
  workflow:            { label: 'Workflow',      to: '/workflow',          icon: ClipboardList,  permission: 'workflow.view' },
}

// Arbeitsplatz-, Content- und KPI-Widgets: ID → Komponente (+ optionale Props/Permission)
const WIDGET_CATALOG = {
  watchlist:           { component: markRaw(WatchlistWidget),         props: () => ({}) },
  notes:               { component: markRaw(NotesWidget),             props: () => ({}) },
  recent:              { component: markRaw(RecentlyEditedWidget),    props: () => ({ products: store.recentlyEdited }) },
  tasks:               { component: markRaw(MyTasksWidget),           props: () => ({ tasks: store.myTasks }) },
  completeness:        { component: markRaw(CompletenessWidget),      props: () => ({ summary: store.completenessSummary }) },
  quality:             { component: markRaw(DataQualityWidget),       props: () => ({ quality: store.dataQuality }) },
  'media-spotlight':   { component: markRaw(MediaSpotlightWidget),    props: () => ({}), permission: 'media.view' },
  'translation-status':{ component: markRaw(TranslationStatusWidget), props: () => ({}), permission: 'translations.view' },
}

// Widget-IDs nach Existenz + Berechtigung filtern
function allowedWidgets(ids) {
  return (ids || []).filter((id) => {
    const w = WIDGET_CATALOG[id]
    return w && (!w.permission || authStore.hasPermission(w.permission))
  })
}

// ─── Profil-Auflösung: persönlich → Rolle → System ─────────
const personalProfile = ref(null)
const profile = computed(() => resolveCockpitProfile(authStore.userRole, personalProfile.value))

const tiles = computed(() =>
  (profile.value.tiles || [])
    .map(id => ({ id, ...TILE_CATALOG[id] }))
    .filter(t => t.label && (!t.permission || authStore.hasPermission(t.permission)))
)
const workplaceWidgets = computed(() => allowedWidgets(profile.value.workplace))
const contentWidgets = computed(() => allowedWidgets(profile.value.content))
const kpiWidgets = computed(() => allowedWidgets(profile.value.kpis))

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

// ─── Daten-Refresh ─────────────────────────────────────────
const AUTO_REFRESH_INTERVAL = 60000
let refreshTimer = null
async function refresh() {
  await store.fetchDashboard()
}

onMounted(async () => {
  store.fetchDashboard()
  refreshTimer = setInterval(refresh, AUTO_REFRESH_INTERVAL)
  // Persönliches Cockpit-Layout (Phase 4) — vorwärtskompatibel auslesen.
  try {
    const { data } = await userPreferencesApi.get('cockpit')
    const payload = data.data || data
    if (payload && Array.isArray(payload.tiles)) personalProfile.value = payload
  } catch { /* ignore */ }
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
        <div>
          <h1 class="text-2xl font-semibold text-[var(--color-text-primary)]">
            {{ greeting }}, {{ authStore.userName }}
          </h1>
          <p v-if="authStore.userRole" class="text-xs text-[var(--color-text-tertiary)] mt-0.5">
            Cockpit · {{ authStore.userRole }}
          </p>
        </div>
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

    <!-- Zone B: Schnellaktionen (rollenabhängig) -->
    <section v-if="tiles.length">
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Schnellzugriff</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <router-link
          v-for="tile in tiles"
          :key="tile.id"
          :to="tile.to"
          class="flex flex-col items-center justify-center gap-2 py-5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-accent)] hover:shadow-sm transition group"
        >
          <component :is="tile.icon" class="w-6 h-6 text-[var(--color-accent)] group-hover:scale-110 transition-transform" :stroke-width="1.75" />
          <span class="text-xs font-medium text-[var(--color-text-secondary)]">{{ tile.label }}</span>
        </router-link>
      </div>
    </section>

    <!-- Zone Arbeitsplatz: Notizen, gepinnte Produkte, zuletzt bearbeitet, Aufgaben -->
    <section v-if="workplaceWidgets.length">
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Mein Arbeitsplatz</h2>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <component
          :is="WIDGET_CATALOG[id].component"
          v-for="id in workplaceWidgets"
          :key="id"
          v-bind="WIDGET_CATALOG[id].props()"
        />
      </div>
    </section>

    <!-- Zone Medien & Content (rollenabhängig, z. B. Marketing) -->
    <section v-if="contentWidgets.length">
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Medien &amp; Content</h2>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <component
          :is="WIDGET_CATALOG[id].component"
          v-for="id in contentWidgets"
          :key="id"
          v-bind="WIDGET_CATALOG[id].props()"
        />
      </div>
    </section>

    <!-- Zone KPIs -->
    <section v-if="kpiWidgets.length">
      <h2 class="text-xs font-medium text-[var(--color-text-tertiary)] uppercase tracking-wider mb-3">Überblick</h2>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <component
          :is="WIDGET_CATALOG[id].component"
          v-for="id in kpiWidgets"
          :key="id"
          v-bind="WIDGET_CATALOG[id].props()"
        />
      </div>
    </section>
  </div>
</template>
