<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import siteApi from '@/api/site'
import SitePreviewSection from '@/components/site/SitePreviewSection.vue'

/**
 * Öffentliche, standalone Website-Seite (ohne Admin/Backend).
 * Nutzt ausschließlich die public Site-API (keine Auth) und rendert
 * Header/Menü/Sektionen/Footer als eigenständige Website.
 * Aufruf z. B.:  /site/smartone   bzw.  /site/smartone?slug=...
 */
const route = useRoute()
const navKey = ref(route.params.nav || null)
const lang = ref(route.query.lang || 'de')
const sitemap = ref(null)
const page = ref(null)
const loading = ref(false)
const error = ref(null)

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

function findContentPage(nodes) {
  for (const n of nodes || []) {
    if (n.target_type === 'content_page' && n.slug) return n
    const hit = findContentPage(n.children)
    if (hit) return hit
  }
  return null
}

async function loadSitemap() {
  if (!navKey.value) {
    error.value = 'Keine Navigation angegeben. Beispiel: /site/smartone'
    return
  }
  loading.value = true
  error.value = null
  try {
    const { data } = await siteApi.getSitemap(navKey.value, lang.value, { anonymous: true })
    sitemap.value = data.data ?? data
    const slug = route.query.slug
    if (slug) {
      await loadPage(slug)
    } else {
      const first = findContentPage(sitemap.value.nodes)
      if (first) await loadPage(first.slug)
      else page.value = null
    }
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Navigation nicht gefunden.' : 'Seite konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

async function loadPage(slug) {
  try {
    const { data } = await siteApi.getPage(navKey.value, slug, lang.value, { anonymous: true })
    page.value = data.data ?? data
    window.scrollTo({ top: 0 })
  } catch {
    page.value = null
  }
}

async function loadProductPageById(id) {
  if (!id) return
  try {
    const { data } = await siteApi.getProductPage(navKey.value, id, lang.value, { anonymous: true })
    page.value = data.data ?? data
    window.scrollTo({ top: 0 })
  } catch {
    page.value = null
  }
}

function onNavClick(node) {
  if (node.target_type === 'external_url' && node.href) {
    window.open(node.href, '_blank')
  } else if (node.target_type === 'product') {
    loadProductPageById(node.product_id || (node.href || '').replace(/^\/product\//, ''))
  } else if (isLink(node)) {
    loadPage(node.slug)
  }
}

function onNavigate(payload) {
  if (!payload) return
  if (payload.kind === 'product') {
    loadProductPageById(payload.id || (payload.href || '').replace(/^\/product\//, ''))
  } else if (payload.kind === 'page') {
    loadPage(payload.slug)
  } else if (payload.kind === 'url' && payload.url) {
    if (/^(https?:|mailto:|tel:)/.test(payload.url)) window.open(payload.url, '_blank')
    else loadPage(payload.url.replace(/^\//, ''))
  }
}

watch(() => route.params.nav, (v) => { navKey.value = v; loadSitemap() })
onMounted(loadSitemap)
</script>

<template>
  <div class="min-h-screen" :style="themeVars" style="background: var(--site-bg); color: var(--site-text)">
    <!-- Header -->
    <header class="px-4 sm:px-8 py-3 flex items-center gap-4 overflow-x-auto sticky top-0 z-20"
      style="background: var(--site-primary); color: var(--site-on-primary)">
      <span class="font-extrabold tracking-tight shrink-0 text-lg cursor-pointer" @click="loadSitemap">
        {{ sitemap?.navigation?.name || 'Website' }}
      </span>
      <nav class="flex items-center gap-3 text-sm">
        <button v-for="(node, i) in menu" :key="i"
          class="whitespace-nowrap transition-opacity"
          :class="[node._child ? 'opacity-80 text-xs' : 'font-medium', isLink(node) || node.target_type === 'external_url' || node.target_type === 'product' ? 'hover:underline' : 'cursor-default opacity-70']"
          @click="onNavClick(node)">
          {{ node.label }}
        </button>
      </nav>
    </header>

    <p v-if="error" class="max-w-5xl mx-auto mt-6 text-sm text-red-600 bg-red-50 px-4 py-3 rounded-lg">{{ error }}</p>

    <!-- Inhalt -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-6">
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 4" :key="i" class="animate-pulse h-24 rounded-xl bg-black/5" />
      </div>
      <template v-else-if="page">
        <SitePreviewSection v-for="s in page.sections" :key="s.id" :section="s" @navigate="onNavigate" />
        <p v-if="!page.sections?.length" class="text-sm opacity-60 text-center py-8">Diese Seite hat noch keine Sektionen.</p>
      </template>
      <p v-else-if="!error" class="text-sm opacity-60 text-center py-8">Keine Seite ausgewählt.</p>
    </main>

    <!-- Footer -->
    <footer class="mt-10 px-4 sm:px-8 py-6 text-xs flex flex-wrap gap-4"
      style="background: var(--site-surface); color: var(--site-muted); border-top: 1px solid var(--site-border)">
      <span v-for="(node, i) in menu" :key="i" class="cursor-pointer hover:underline" @click="onNavClick(node)">{{ node.label }}</span>
    </footer>
  </div>
</template>
