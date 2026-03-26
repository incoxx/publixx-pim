<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuickSearchStore } from '@/stores/quickSearch'
import {
  Search, Package, Image, GitBranch, Sliders, X, ChevronRight,
  FolderTree, ArrowRight, Sparkles, Loader2,
} from 'lucide-vue-next'

const router = useRouter()
const { t } = useI18n()
const store = useQuickSearchStore()
const inputRef = ref(null)
const sentinelRef = ref(null)

const tabs = [
  { key: 'products', label: 'Produkte', icon: Package },
  { key: 'media', label: 'Medien', icon: Image },
  { key: 'hierarchies', label: 'Hierarchien', icon: GitBranch },
  { key: 'attributes', label: 'Attribute', icon: Sliders },
]

// ─── Infinite Scroll via IntersectionObserver ────────
let observer = null

onMounted(() => {
  nextTick(() => inputRef.value?.focus())

  // Sentinel beobachten: wenn sichtbar → nachladen
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && store.hasMore && !store.loadingMore) {
        store.loadMore()
      }
    },
    { rootMargin: '200px' } // 200px vor dem Ende nachladen
  )
  if (sentinelRef.value) observer.observe(sentinelRef.value)
})

// Sentinel bei Änderung neu beobachten
watch(sentinelRef, (el) => {
  if (observer && el) observer.observe(el)
})

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
})

// ─── Eingabe-Handler ─────────────────────────────────
function onInput(e) {
  store.search(e.target.value)
}

// ─── Keyboard-Navigation ─────────────────────────────
function onKeyDown(e) {
  const items = store.results
  if (!items || items.length === 0) return

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    store.selectedIndex = Math.min(store.selectedIndex + 1, items.length - 1)
    scrollToSelected()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    store.selectedIndex = Math.max(store.selectedIndex - 1, -1)
    scrollToSelected()
  } else if (e.key === 'Enter' && store.selectedIndex >= 0) {
    e.preventDefault()
    openResult(items[store.selectedIndex])
  }
}

function scrollToSelected() {
  nextTick(() => {
    const el = document.querySelector(`[data-result-index="${store.selectedIndex}"]`)
    if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  })
}

// ─── Navigation ──────────────────────────────────────
function openResult(item) {
  if (!item) return
  const routes = {
    product: `/products/${item.id}`,
    media: `/media`,
    hierarchy: `/hierarchies`,
    attribute: `/attributes`,
  }
  const path = routes[item.type]
  if (path) router.push(path)
}

// ─── Drill-Down (Querverweis-Klick) ──────────────────
function onBadgeClick(item, badgeRef) {
  if (!badgeRef) return

  const drillMap = {
    // Produkt → Kategorie
    category: {
      tab: 'hierarchies',
      filter: { category_id: null, attribute_id: null, media_id: null },
      queryOverride: badgeRef.label,
    },
    // Kategorie → Produkte
    category_products: {
      tab: 'products',
      filter: { category_id: badgeRef.id, attribute_id: null, media_id: null },
    },
    // Attribut → Produkte
    attribute_products: {
      tab: 'products',
      filter: { category_id: null, attribute_id: badgeRef.id, media_id: null },
    },
    // Attribut → Kategorien
    attribute_categories: {
      tab: 'hierarchies',
      filter: { category_id: null, attribute_id: badgeRef.id, media_id: null },
    },
    // Medium → Produkte
    media_products: {
      tab: 'products',
      filter: { category_id: null, attribute_id: null, media_id: badgeRef.id },
    },
  }

  const drill = drillMap[badgeRef.type]
  if (!drill) return

  if (drill.queryOverride) {
    store.drillDown({ tab: drill.tab, filter: drill.filter, label: badgeRef.label })
    store.query = drill.queryOverride
    store.search()
  } else {
    store.drillDown({ tab: drill.tab, filter: drill.filter, label: badgeRef.label })
  }
}

// ─── Verlauf löschen ─────────────────────────────────
function clearAll() {
  store.clear()
  nextTick(() => inputRef.value?.focus())
}

// ─── Media Thumb URL ─────────────────────────────────
const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'

function thumbUrl(item) {
  if (!item.image) return null
  if (item.type === 'media') {
    return `${apiBase}/media/thumb/${item.image}`
  }
  // Produkt: primary_image ist ein Dateiname
  if (item.type === 'product' && item.image) {
    return `${apiBase}/media/file/${item.image}`
  }
  return null
}

// ─── Typ-Icon ────────────────────────────────────────
function typeIcon(type) {
  return { product: Package, media: Image, hierarchy: FolderTree, attribute: Sliders }[type] || Search
}

// ─── Typ-Farbe für Badges ────────────────────────────
function badgeClass(type) {
  return {
    category: 'badge-primary',
    category_products: 'badge-info',
    attribute_products: 'badge-info',
    attribute_categories: 'badge-secondary',
    media_products: 'badge-info',
  }[type] || 'badge-ghost'
}
</script>

<template>
  <div class="flex flex-col items-center w-full min-h-full px-4 pt-8 pb-16">

    <!-- Verlauf / Breadcrumbs -->
    <div
      v-if="store.history.length > 0"
      class="flex items-center gap-1 text-sm mb-4 max-w-2xl w-full flex-wrap"
    >
      <button
        v-for="(entry, idx) in store.history"
        :key="idx"
        class="btn btn-xs btn-ghost gap-1"
        @click="store.jumpToHistory(idx)"
      >
        <Search v-if="idx === 0" class="w-3 h-3" />
        <ChevronRight v-else class="w-3 h-3 opacity-40" />
        <span>{{ entry.label || entry.query || '...' }}</span>
      </button>
      <ChevronRight class="w-3 h-3 opacity-40" />
      <span class="text-base-content/60 text-xs">
        {{ store.activeTab === 'products' ? 'Produkte' : store.activeTab === 'media' ? 'Medien' : store.activeTab === 'hierarchies' ? 'Hierarchien' : 'Attribute' }}
        <span v-if="store.hasActiveFilter" class="badge badge-xs badge-warning ml-1">Filter</span>
      </span>
      <button class="btn btn-xs btn-ghost btn-circle ml-auto" @click="clearAll" title="Verlauf löschen">
        <X class="w-3 h-3" />
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 max-w-2xl w-full">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="btn btn-sm gap-2"
        :class="store.activeTab === tab.key ? 'btn-primary' : 'btn-ghost'"
        @click="store.switchTab(tab.key)"
      >
        <component :is="tab.icon" class="w-4 h-4" />
        {{ tab.label }}
        <span
          v-if="store.counts[tab.key] > 0"
          class="badge badge-xs"
          :class="store.activeTab === tab.key ? 'badge-primary-content bg-primary-content/20' : 'badge-ghost'"
        >
          {{ store.counts[tab.key] }}
        </span>
      </button>
    </div>

    <!-- Suchfeld -->
    <div class="relative max-w-2xl w-full mb-6">
      <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
      <input
        ref="inputRef"
        type="text"
        :value="store.query"
        @input="onInput"
        @keydown="onKeyDown"
        class="input input-lg input-bordered w-full pl-12 pr-12 text-lg"
        placeholder="Schnellsuche..."
        autocomplete="off"
        spellcheck="false"
      />
      <button
        v-if="store.query || store.hasActiveFilter"
        class="btn btn-sm btn-ghost btn-circle absolute right-2 top-1/2 -translate-y-1/2"
        @click="clearAll"
      >
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Aktiver Drill-Down-Filter -->
    <div
      v-if="store.hasActiveFilter"
      class="flex items-center gap-2 text-sm mb-4 max-w-2xl w-full"
    >
      <span class="badge badge-warning badge-sm gap-1">
        {{ store.activeFilterLabel }}
        <button @click="store.clearFilters(); store.search()" class="ml-1">&times;</button>
      </span>
    </div>

    <!-- Ladeindikator (nur bei initialer Suche, nicht beim Nachladen) -->
    <div v-if="store.loading && store.results.length === 0" class="max-w-2xl w-full">
      <div class="flex items-center gap-3 py-8 justify-center text-base-content/50">
        <span class="loading loading-spinner loading-sm"></span>
        Suche läuft...
      </div>
    </div>

    <!-- Ergebnisse -->
    <div v-if="store.results.length > 0" class="max-w-2xl w-full space-y-2">
      <div
        v-for="(item, idx) in store.results"
        :key="item.id"
        :data-result-index="idx"
        class="card card-compact bg-base-100 shadow-sm hover:shadow-md transition-shadow cursor-pointer border border-base-200"
        :class="{ 'ring-2 ring-primary': store.selectedIndex === idx }"
        @click="openResult(item)"
      >
        <div class="card-body flex-row items-center gap-3">
          <!-- Thumbnail / Icon -->
          <div class="shrink-0 w-10 h-10 rounded bg-base-200 flex items-center justify-center overflow-hidden">
            <img
              v-if="thumbUrl(item)"
              :src="thumbUrl(item)"
              class="w-full h-full object-cover"
              loading="lazy"
              @error="$event.target.style.display = 'none'"
            />
            <component v-else :is="typeIcon(item.type)" class="w-5 h-5 text-base-content/30" />
          </div>

          <!-- Text -->
          <div class="flex-1 min-w-0">
            <div class="font-medium text-sm truncate">{{ item.title }}</div>
            <div v-if="item.subtitle" class="text-xs text-base-content/50 truncate">{{ item.subtitle }}</div>
          </div>

          <!-- Badges (Querverweise) -->
          <div v-if="item.badge_refs && item.badge_refs.length" class="flex gap-1 flex-wrap justify-end">
            <button
              v-for="(ref, bIdx) in item.badge_refs"
              :key="bIdx"
              class="badge badge-sm gap-1 cursor-pointer hover:brightness-90 transition-all"
              :class="badgeClass(ref.type)"
              @click.stop="onBadgeClick(item, ref)"
              :title="'Drill-Down: ' + ref.label"
            >
              <ArrowRight class="w-3 h-3" />
              {{ ref.label }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Infinite Scroll: Sentinel + Lade-Indikator -->
    <div v-if="store.results.length > 0" ref="sentinelRef" class="max-w-2xl w-full">
      <div v-if="store.loadingMore" class="flex items-center justify-center gap-2 py-4 text-base-content/40">
        <span class="loading loading-spinner loading-xs"></span>
        <span class="text-sm">Lade weitere Ergebnisse...</span>
      </div>
      <div v-else-if="!store.hasMore && store.results.length >= 20" class="text-center py-3 text-xs text-base-content/30">
        Alle {{ store.counts[store.activeTab] }} Ergebnisse geladen
      </div>
    </div>

    <!-- Leerer Zustand: Keine Ergebnisse -->
    <div v-else-if="store.hasQuery && !store.loading && store.results.length === 0" class="max-w-2xl w-full text-center py-12">
      <Search class="w-12 h-12 mx-auto mb-3 text-base-content/20" />
      <p class="text-base-content/50">Keine Ergebnisse gefunden</p>
      <p class="text-xs text-base-content/30 mt-1">Versuche einen anderen Begriff oder wechsle den Tab</p>
    </div>

    <!-- Leerer Zustand: Kein Suchbegriff -->
    <div v-if="!store.hasQuery && !store.loading && store.results.length === 0" class="max-w-2xl w-full text-center py-16">
      <Sparkles class="w-16 h-16 mx-auto mb-4 text-primary/30" />
      <h2 class="text-xl font-semibold text-base-content/60 mb-2">Schnellsuche</h2>
      <p class="text-base-content/40 text-sm">
        Durchsuche Produkte, Medien, Hierarchien und Attribute.<br />
        Klicke auf Querverweise, um tiefer einzutauchen.
      </p>
      <div class="flex items-center justify-center gap-4 mt-6 text-xs text-base-content/30">
        <span><kbd class="kbd kbd-xs">&#8593;</kbd><kbd class="kbd kbd-xs">&#8595;</kbd> Navigieren</span>
        <span><kbd class="kbd kbd-xs">Enter</kbd> Öffnen</span>
        <span><kbd class="kbd kbd-xs">Esc</kbd> Löschen</span>
      </div>
    </div>

  </div>
</template>
