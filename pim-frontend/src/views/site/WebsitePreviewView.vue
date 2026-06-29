<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import navigationApi from '@/api/navigation'
import siteApi from '@/api/site'
import SitePreviewSection from '@/components/site/SitePreviewSection.vue'
import { Globe, Monitor, Smartphone, RefreshCw } from 'lucide-vue-next'

const route = useRoute()
const pendingSlug = ref(null)   // initial via ?slug= gezielt zu ladende Seite
const navigations = ref([])
const navKey = ref(null)        // technical_name der aktiven Navigation
const sitemap = ref(null)
const page = ref(null)
const lang = ref('de')
const device = ref('full')      // full | mobile
const loading = ref(false)
const error = ref(null)

// Website-Theme aus der Sitemap als CSS-Variablen (--site-*) + Schrift
const themeVars = computed(() => {
  const t = sitemap.value?.theme || {}
  return {
    '--site-primary': t.primary,
    '--site-on-primary': t.on_primary,
    '--site-accent': t.accent,
    '--site-text': t.text,
    '--site-muted': t.muted,
    '--site-bg': t.background,
    '--site-surface': t.surface,
    '--site-border': t.border,
    '--site-radius': t.radius,
    fontFamily: t.font,
  }
})

// Flache Menüliste: Top-Level-Knoten + Content-Kinder von Ordnern
const menu = computed(() => {
  const out = []
  for (const n of sitemap.value?.nodes || []) {
    out.push(n)
    if (n.target_type === 'folder') {
      for (const c of n.children || []) out.push({ ...c, _child: true })
    }
  }
  return out.filter((n) => n.is_visible)
})

function isLink(node) {
  return node.target_type === 'content_page'
}

async function loadSitemap() {
  if (!navKey.value) return
  loading.value = true
  error.value = null
  try {
    const { data } = await siteApi.getSitemap(navKey.value, lang.value)
    sitemap.value = data.data ?? data
    if (pendingSlug.value) {
      await loadPage(pendingSlug.value)
      pendingSlug.value = null
    } else {
      const first = findContentPage(sitemap.value.nodes)
      if (first) await loadPage(first.slug)
      else page.value = null
    }
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Navigation nicht gefunden.' : 'Vorschau konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

function findContentPage(nodes) {
  for (const n of nodes || []) {
    if (n.target_type === 'content_page' && n.slug) return n
    const hit = findContentPage(n.children)
    if (hit) return hit
  }
  return null
}

async function loadPage(slug) {
  try {
    const { data } = await siteApi.getPage(navKey.value, slug, lang.value)
    page.value = data.data ?? data
  } catch {
    page.value = null
  }
}

function onNavClick(node) {
  // Backend liefert externe Ziele als node.href (nicht external_url).
  if (node.target_type === 'external_url' && node.href) {
    window.open(node.href, '_blank')
  } else if (node.target_type === 'product') {
    loadProductPage(node)
  } else if (isLink(node)) {
    loadPage(node.slug)
  }
}

async function loadProductPage(node) {
  const id = node.product_id || (node.href || '').replace(/^\/product\//, '')
  if (!id) return
  try {
    const { data } = await siteApi.getProductPage(navKey.value, id, lang.value)
    page.value = data.data ?? data
  } catch {
    page.value = null
  }
}

async function switchNavigation(key) {
  navKey.value = key
  await loadSitemap()
}

onMounted(async () => {
  const { data } = await navigationApi.list({ perPage: 100 })
  navigations.value = data.data ?? data
  pendingSlug.value = route.query.slug || null

  // Navigation aus ?nav= (technical_name oder ID), sonst primäre/erste.
  let chosen = null
  if (route.query.nav) {
    chosen = navigations.value.find((n) => n.technical_name === route.query.nav || n.id === route.query.nav)
  }
  chosen = chosen || navigations.value.find((n) => n.is_primary) || navigations.value[0]
  if (chosen) {
    navKey.value = chosen.technical_name
    await loadSitemap()
  }
})
</script>

<template>
  <div class="space-y-3" data-testid="website-preview">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <Globe class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Website-Vorschau</h2>
        <select v-if="navigations.length" class="pim-input text-xs" :value="navKey" @change="switchNavigation($event.target.value)">
          <option v-for="n in navigations" :key="n.id" :value="n.technical_name">{{ n.name_de }}</option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <div class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
          <button v-for="l in ['de', 'en']" :key="l" class="px-2 py-1 text-xs uppercase"
            :class="lang === l ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)]'"
            @click="lang = l; loadSitemap()">{{ l }}</button>
        </div>
        <div class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
          <button class="p-1.5" :class="device === 'full' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)]'" @click="device = 'full'" title="Desktop">
            <Monitor class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button class="p-1.5" :class="device === 'mobile' ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)]'" @click="device = 'mobile'" title="Mobil">
            <Smartphone class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
        <button class="pim-btn pim-btn-secondary text-xs" @click="loadSitemap"><RefreshCw class="w-3.5 h-3.5" :stroke-width="2" /></button>
      </div>
    </div>

    <p v-if="error" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ error }}</p>

    <!-- Geräte-Rahmen -->
    <div class="mx-auto transition-all" :class="device === 'mobile' ? 'max-w-sm' : 'max-w-5xl'">
      <div class="rounded-2xl border border-[var(--color-border)] overflow-hidden shadow-sm" :style="themeVars">
        <!-- Website-Header (Menü aus Navigation) -->
        <header class="px-4 py-3 flex items-center gap-4 overflow-x-auto" style="background: var(--site-primary); color: var(--site-on-primary)">
          <span class="font-extrabold tracking-tight shrink-0">{{ sitemap?.navigation?.name || 'anyPIM' }}</span>
          <nav class="flex items-center gap-3 text-sm">
            <button v-for="(node, i) in menu" :key="i"
              class="whitespace-nowrap transition-opacity"
              :class="[node._child ? 'opacity-80 text-xs' : 'font-medium', isLink(node) || node.target_type === 'external_url' ? 'hover:underline' : 'cursor-default opacity-70']"
              @click="onNavClick(node)">
              {{ node.label }}
            </button>
          </nav>
        </header>

        <!-- Seiteninhalt -->
        <main class="p-4 sm:p-6 space-y-6" style="background: var(--site-bg); color: var(--site-text)">
          <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="pim-skeleton h-24 rounded-xl" />
          </div>
          <template v-else-if="page">
            <SitePreviewSection v-for="s in page.sections" :key="s.id" :section="s" />
            <p v-if="!page.sections?.length" class="text-sm text-[var(--color-text-tertiary)] text-center py-8">Diese Seite hat noch keine Sektionen.</p>
          </template>
          <p v-else class="text-sm text-[var(--color-text-tertiary)] text-center py-8">Keine Seite ausgewählt.</p>
        </main>

        <!-- Footer -->
        <footer class="bg-zinc-100 dark:bg-zinc-800 px-4 py-3 text-[11px] text-[var(--color-text-tertiary)] flex flex-wrap gap-3">
          <span v-for="(node, i) in menu" :key="i" class="cursor-pointer hover:underline" @click="onNavClick(node)">{{ node.label }}</span>
        </footer>
      </div>
    </div>
  </div>
</template>
