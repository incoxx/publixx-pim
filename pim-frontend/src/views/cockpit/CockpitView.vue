<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Search, RefreshCw, SlidersHorizontal, X, Save, RotateCcw } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { useAuthStore } from '@/stores/auth'
import { useQuickSearchStore } from '@/stores/quickSearch'
import userPreferencesApi from '@/api/userPreferences'
import cockpitProfilesApi from '@/api/cockpitProfiles'
import { resolveCockpitProfile, isLayout } from '@/config/cockpitProfiles'
import { TILE_CATALOG, WIDGET_CATALOG } from '@/config/cockpitCatalog'
import CockpitLayoutEditor from '@/components/cockpit/CockpitLayoutEditor.vue'

const router = useRouter()
const store = useDashboardStore()
const authStore = useAuthStore()
const searchStore = useQuickSearchStore()

// Store-gebundene Props je Widget-ID (Komponenten kommen aus dem Katalog)
function widgetProps(id) {
  switch (id) {
    case 'recent':         return { products: store.recentlyEdited }
    case 'tasks':          return { tasks: store.myTasks }
    case 'completeness':   return { summary: store.completenessSummary }
    case 'quality':        return { quality: store.dataQuality }
    case 'media-spotlight': return { scope: 'all' }
    case 'watchlist-media': return { scope: 'watchlist' }
    default:               return {}
  }
}

// Widget-IDs nach Existenz + Berechtigung filtern
function allowedWidgets(ids) {
  return (ids || []).filter((id) => {
    const w = WIDGET_CATALOG[id]
    return w && (!w.permission || authStore.hasPermission(w.permission))
  })
}

// ─── Profil-Auflösung: persönlich → Rollen-Layout (Admin) → Code-Default ──
const personalProfile = ref(null)
const savedRoleProfile = ref(null)
const profile = computed(() => resolveCockpitProfile(authStore.userRole, personalProfile.value, savedRoleProfile.value))

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

// ─── Persönlicher Layout-Editor ────────────────────────────
const showCustomize = ref(false)
const draftLayout = ref({ tiles: [], workplace: [], content: [], kpis: [] })
const savingPersonal = ref(false)

function openCustomize() {
  // Mit dem aktuell wirksamen Layout vorbefüllen
  const p = profile.value
  draftLayout.value = {
    tiles: [...(p.tiles || [])],
    workplace: [...(p.workplace || [])],
    content: [...(p.content || [])],
    kpis: [...(p.kpis || [])],
  }
  showCustomize.value = true
}

async function savePersonal() {
  savingPersonal.value = true
  try {
    await userPreferencesApi.update('cockpit', {
      tiles: draftLayout.value.tiles || [],
      workplace: draftLayout.value.workplace || [],
      content: draftLayout.value.content || [],
      kpis: draftLayout.value.kpis || [],
    })
    personalProfile.value = { ...draftLayout.value }
    showCustomize.value = false
  } catch { /* ignore */ } finally {
    savingPersonal.value = false
  }
}

async function resetPersonal() {
  savingPersonal.value = true
  try {
    // Leeres Objekt = persönliche Anpassung entfernen → Rollen-/Standard-Layout greift
    await userPreferencesApi.update('cockpit', {})
    personalProfile.value = null
    showCustomize.value = false
  } catch { /* ignore */ } finally {
    savingPersonal.value = false
  }
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
  // Gespeichertes Rollen-Layout (Admin) und persönliches Layout parallel laden.
  const [roleRes, persRes] = await Promise.allSettled([
    cockpitProfilesApi.mine(),
    userPreferencesApi.get('cockpit'),
  ])
  if (roleRes.status === 'fulfilled') {
    savedRoleProfile.value = roleRes.value.data?.data || null
  }
  if (persRes.status === 'fulfilled') {
    const payload = persRes.value.data?.data || persRes.value.data
    // Nur als persönlicher Override übernehmen, wenn echtes (nicht-leeres) Layout.
    personalProfile.value = isLayout(payload) ? payload : null
  }
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
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-ghost text-xs" title="Cockpit anpassen" @click="openCustomize">
            <SlidersHorizontal class="w-4 h-4" :stroke-width="2" />
            <span class="hidden sm:inline">Anpassen</span>
          </button>
          <button class="pim-btn pim-btn-ghost text-xs" :disabled="store.loading" @click="refresh">
            <RefreshCw class="w-4 h-4" :class="store.loading ? 'animate-spin' : ''" :stroke-width="2" />
          </button>
        </div>
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
          class="group relative flex flex-col items-center justify-center gap-2.5 py-5 rounded-2xl border border-[var(--color-border)] bg-gradient-to-b from-[var(--color-surface)] to-[color-mix(in_srgb,var(--color-accent)_5%,var(--color-surface))] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:border-[color-mix(in_srgb,var(--color-accent)_40%,var(--color-border))]"
        >
          <span
            class="flex items-center justify-center w-11 h-11 rounded-xl text-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_12%,transparent)] shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] transition-all duration-200 group-hover:scale-105 group-hover:bg-[color-mix(in_srgb,var(--color-accent)_18%,transparent)]"
          >
            <component :is="tile.icon" class="w-5 h-5" :stroke-width="1.75" />
          </span>
          <span class="text-xs font-medium text-[var(--color-text-secondary)] transition-colors group-hover:text-[var(--color-text-primary)]">{{ tile.label }}</span>
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
          v-bind="widgetProps(id)"
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
          v-bind="widgetProps(id)"
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
          v-bind="widgetProps(id)"
        />
      </div>
    </section>

    <!-- Persönlicher Layout-Editor (Overlay) -->
    <div
      v-if="showCustomize"
      class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 overflow-y-auto"
      @click.self="showCustomize = false"
    >
      <div class="bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-2xl my-8 flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)] shrink-0">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
            <SlidersHorizontal class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
            Mein Cockpit anpassen
          </h3>
          <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="showCustomize = false">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto">
          <p class="text-xs text-[var(--color-text-tertiary)] mb-4">
            Wähle, welche Bausteine dein Cockpit zeigt. Deine Auswahl gilt nur für dich
            und hat Vorrang vor dem Rollen-Standard.
          </p>
          <CockpitLayoutEditor v-model="draftLayout" />
        </div>

        <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-[var(--color-border)] shrink-0">
          <button class="pim-btn pim-btn-ghost text-xs" :disabled="savingPersonal" @click="resetPersonal">
            <RotateCcw class="w-3.5 h-3.5" :stroke-width="2" /> Standard wiederherstellen
          </button>
          <div class="flex items-center gap-2">
            <button class="pim-btn pim-btn-secondary text-xs" :disabled="savingPersonal" @click="showCustomize = false">Abbrechen</button>
            <button class="pim-btn pim-btn-primary text-xs" :disabled="savingPersonal" @click="savePersonal">
              <Save class="w-3.5 h-3.5" :stroke-width="2" /> Speichern
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
