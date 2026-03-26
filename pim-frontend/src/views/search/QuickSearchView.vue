<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuickSearchStore } from '@/stores/quickSearch'
import {
  Search, Package, Image, GitBranch, Sliders, X, ChevronRight,
  FolderTree, ArrowRight, Sparkles, Link2,
} from 'lucide-vue-next'

const router = useRouter()
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

  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && store.hasMore && !store.loadingMore) {
        store.loadMore()
      }
    },
    { rootMargin: '200px' }
  )
  if (sentinelRef.value) observer.observe(sentinelRef.value)
})

// Sentinel bei Änderung: alten unobserven, neuen observen
watch(sentinelRef, (el, oldEl) => {
  if (observer) {
    if (oldEl) observer.unobserve(oldEl)
    if (el) observer.observe(el)
  }
}, { flush: 'post' })

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
  store.clear()
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
  if (!item?.id) return
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
function onBadgeClick(item, badge) {
  if (!badge) return

  const drillMap = {
    category: {
      tab: 'hierarchies',
      filter: { category_id: null, attribute_id: null, media_id: null },
      queryOverride: badge.label,
    },
    category_products: {
      tab: 'products',
      filter: { category_id: badge.id, attribute_id: null, media_id: null },
    },
    attribute_products: {
      tab: 'products',
      filter: { category_id: null, attribute_id: badge.id, media_id: null },
    },
    attribute_categories: {
      tab: 'hierarchies',
      filter: { category_id: null, attribute_id: badge.id, media_id: null },
    },
    media_products: {
      tab: 'products',
      filter: { category_id: null, attribute_id: null, media_id: badge.id },
    },
  }

  const drill = drillMap[badge.type]
  if (!drill) return

  if (drill.queryOverride) {
    store.drillDown({ tab: drill.tab, filter: drill.filter, label: badge.label })
    store.query = drill.queryOverride
    store.search()
  } else {
    store.drillDown({ tab: drill.tab, filter: drill.filter, label: badge.label })
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
  if (item.type === 'product' && item.image) {
    return `${apiBase}/media/file/${item.image}`
  }
  return null
}

// ─── Typ-Icon ────────────────────────────────────────
function typeIcon(type) {
  return { product: Package, media: Image, hierarchy: FolderTree, attribute: Sliders }[type] || Search
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
        class="pim-btn pim-btn-ghost text-xs gap-1 px-2 py-1"
        @click="store.jumpToHistory(idx)"
      >
        <Search v-if="idx === 0" class="w-3 h-3" :stroke-width="1.75" />
        <ChevronRight v-else class="w-3 h-3 opacity-40" :stroke-width="1.75" />
        <span>{{ entry.label || entry.query || '...' }}</span>
      </button>
      <ChevronRight class="w-3 h-3 opacity-40" :stroke-width="1.75" />
      <span class="text-[var(--color-text-tertiary)] text-xs">
        {{ store.activeTab === 'products' ? 'Produkte' : store.activeTab === 'media' ? 'Medien' : store.activeTab === 'hierarchies' ? 'Hierarchien' : 'Attribute' }}
        <span
          v-if="store.hasActiveFilter"
          class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[color-mix(in_srgb,var(--color-warning,#f59e0b)_20%,transparent)] text-[var(--color-warning,#d97706)] ml-1"
        >Filter</span>
      </span>
      <button class="pim-btn pim-btn-ghost p-1 rounded-full ml-auto" @click="clearAll" title="Verlauf löschen">
        <X class="w-3 h-3" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 max-w-2xl w-full">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="pim-btn text-xs gap-1.5 px-3 py-1.5"
        :class="store.activeTab === tab.key ? 'pim-btn-primary' : 'pim-btn-ghost'"
        @click="store.switchTab(tab.key)"
      >
        <component :is="tab.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
        {{ tab.label }}
        <span
          v-if="store.counts[tab.key] > 0"
          class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-semibold"
          :class="store.activeTab === tab.key
            ? 'bg-white/20 text-white'
            : 'bg-[var(--color-bg)] text-[var(--color-text-secondary)]'"
        >
          {{ store.counts[tab.key] }}
        </span>
      </button>
    </div>

    <!-- Suchfeld -->
    <div class="relative max-w-2xl w-full mb-6">
      <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <input
        ref="inputRef"
        type="text"
        :value="store.query"
        @input="onInput"
        @keydown="onKeyDown"
        class="w-full h-12 pl-12 pr-12 text-base rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)] focus:outline-none focus:border-[var(--color-accent)] focus:ring-2 focus:ring-[var(--color-accent)]/20 transition-colors"
        placeholder="Schnellsuche..."
        autocomplete="off"
        spellcheck="false"
      />
      <button
        v-if="store.query || store.hasActiveFilter"
        class="pim-btn pim-btn-ghost p-1.5 rounded-full absolute right-2 top-1/2 -translate-y-1/2"
        @click="clearAll"
      >
        <X class="w-4 h-4" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Aktiver Drill-Down-Filter -->
    <div
      v-if="store.hasActiveFilter"
      class="flex items-center gap-2 text-sm mb-4 max-w-2xl w-full"
    >
      <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-[color-mix(in_srgb,var(--color-warning,#f59e0b)_20%,transparent)] text-[var(--color-warning,#d97706)]">
        {{ store.activeFilterLabel }}
        <button @click="store.clearFilters(); store.search()" class="ml-1 opacity-70 hover:opacity-100">&times;</button>
      </span>
    </div>

    <!-- Ladeindikator (nur bei initialer Suche, nicht beim Nachladen) -->
    <div v-if="store.loading && store.results.length === 0" class="max-w-2xl w-full">
      <div class="flex items-center gap-3 py-8 justify-center text-[var(--color-text-tertiary)]">
        <div class="w-4 h-4 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
        Suche läuft...
      </div>
    </div>

    <!-- Ergebnisse -->
    <div v-if="store.results.length > 0" class="max-w-2xl w-full space-y-1.5">
      <div
        v-for="(item, idx) in store.results"
        :key="item.id"
        :data-result-index="idx"
        class="pim-card p-3 cursor-pointer hover:border-[var(--color-accent)] transition-colors"
        :class="{ 'border-[var(--color-accent)] ring-1 ring-[var(--color-accent)]/30': store.selectedIndex === idx }"
        @click="openResult(item)"
      >
        <div class="flex items-center gap-3">
          <!-- Thumbnail / Icon -->
          <div class="shrink-0 w-10 h-10 rounded-lg bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
            <img
              v-if="thumbUrl(item)"
              :src="thumbUrl(item)"
              class="w-full h-full object-cover"
              loading="lazy"
              @error="$event.target.classList.add('hidden')"
            />
            <component v-else :is="typeIcon(item.type)" class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          </div>

          <!-- Text -->
          <div class="flex-1 min-w-0">
            <div class="font-medium text-sm text-[var(--color-text-primary)] truncate">{{ item.title }}</div>
            <div v-if="item.subtitle" class="text-xs text-[var(--color-text-tertiary)] truncate">{{ item.subtitle }}</div>
          </div>

          <!-- Badges (Querverweise) -->
          <div v-if="item.badge_refs && item.badge_refs.length" class="flex gap-1 flex-wrap justify-end shrink-0">
            <button
              v-for="badge in item.badge_refs"
              :key="`${item.id}-${badge.type}-${badge.id}`"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-[color-mix(in_srgb,var(--color-accent)_12%,transparent)] text-[var(--color-accent)] hover:bg-[color-mix(in_srgb,var(--color-accent)_20%,transparent)] transition-colors cursor-pointer"
              @click.stop="onBadgeClick(item, badge)"
              :title="'Drill-Down: ' + badge.label"
            >
              <ArrowRight class="w-3 h-3" :stroke-width="2" />
              {{ badge.label }}
            </button>
          </div>
        </div>

        <!-- Snippet: Schnellsuche-Attribute (Google-Teaser) -->
        <div v-if="item.snippet" class="mt-1.5 ml-[52px] text-xs text-[var(--color-text-secondary)] leading-relaxed truncate">
          {{ item.snippet }}
        </div>

        <!-- Produktbeziehungen -->
        <div v-if="item.relations && item.relations.length" class="mt-1.5 ml-[52px] flex items-center gap-1.5 flex-wrap">
          <Link2 class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
          <button
            v-for="rel in item.relations"
            :key="`${item.id}-rel-${rel.id}`"
            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] bg-[var(--color-bg)] text-[var(--color-text-secondary)] hover:text-[var(--color-accent)] hover:bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] transition-colors cursor-pointer"
            @click.stop="rel.id && openResult({ type: 'product', id: rel.id })"
            :title="(rel.type || '') + ': ' + (rel.name || rel.sku || '')"
          >
            <span v-if="rel.type" class="text-[var(--color-text-tertiary)]">{{ rel.type }}:</span>
            {{ rel.name || rel.sku || '(ohne Name)' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Infinite Scroll: Sentinel (immer gemountet wenn Ergebnisse da) -->
    <div v-if="store.results.length > 0" ref="sentinelRef" class="max-w-2xl w-full pt-1">
      <div v-if="store.loadingMore" class="flex items-center justify-center gap-2 py-4 text-[var(--color-text-tertiary)]">
        <div class="w-3.5 h-3.5 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm">Lade weitere Ergebnisse...</span>
      </div>
      <div v-else-if="!store.hasMore && store.results.length >= 20" class="text-center py-3 text-xs text-[var(--color-text-tertiary)]">
        Alle {{ store.counts[store.activeTab] }} Ergebnisse geladen
      </div>
    </div>

    <!-- Leerer Zustand: Keine Ergebnisse -->
    <div v-else-if="store.hasQuery && !store.loading && store.results.length === 0" class="max-w-2xl w-full text-center py-12">
      <Search class="w-12 h-12 mx-auto mb-3 text-[var(--color-text-tertiary)] opacity-30" :stroke-width="1.5" />
      <p class="text-[var(--color-text-secondary)]">Keine Ergebnisse gefunden</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Versuche einen anderen Begriff oder wechsle den Tab</p>
    </div>

    <!-- Leerer Zustand: Kein Suchbegriff -->
    <div v-if="!store.hasQuery && !store.loading && store.results.length === 0" class="max-w-2xl w-full text-center py-16">
      <Sparkles class="w-16 h-16 mx-auto mb-4 text-[var(--color-accent)] opacity-25" :stroke-width="1.25" />
      <h2 class="text-xl font-semibold text-[var(--color-text-secondary)] mb-2">Schnellsuche</h2>
      <p class="text-[var(--color-text-tertiary)] text-sm">
        Durchsuche Produkte, Medien, Hierarchien und Attribute.<br />
        Klicke auf Querverweise, um tiefer einzutauchen.
      </p>
      <div class="flex items-center justify-center gap-4 mt-6 text-xs text-[var(--color-text-tertiary)]">
        <span><span class="pim-kbd text-[10px]">&uarr;</span><span class="pim-kbd text-[10px]">&darr;</span> Navigieren</span>
        <span><span class="pim-kbd text-[10px]">Enter</span> Öffnen</span>
        <span><span class="pim-kbd text-[10px]">Esc</span> Löschen</span>
      </div>
    </div>

  </div>
</template>
