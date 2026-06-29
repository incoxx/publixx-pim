<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import SiteProductCard from './SiteProductCard.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const v = computed(() => props.section.values || {})
const type = computed(() => props.section.type)

const gridCols = computed(() => {
  const c = v.value.columns || '3'
  return { '2': 'grid-cols-2', '3': 'grid-cols-2 sm:grid-cols-3', '4': 'grid-cols-2 sm:grid-cols-4' }[String(c)] || 'grid-cols-3'
})

// ─── Countdown ────────────────────────────────────────────────────
const now = ref(Date.now())
let timer = null
onMounted(() => { if (type.value === 'countdown') timer = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => timer && clearInterval(timer))

const countdown = computed(() => {
  if (!v.value.target_at) return null
  const target = new Date(v.value.target_at).getTime()
  let diff = Math.max(0, Math.floor((target - now.value) / 1000))
  const d = Math.floor(diff / 86400); diff %= 86400
  const h = Math.floor(diff / 3600); diff %= 3600
  const m = Math.floor(diff / 60); const s = diff % 60
  return { d, h, m, s }
})

function headingTag() {
  return { h1: 'h1', h2: 'h2', h3: 'h3' }[v.value.level] || 'h2'
}

// Section-Media werden als ID gespeichert; nur echte URLs/Pfade als Bild rendern
// (verhindert eine kaputte <img src="UUID">).
function isImageUrl(s) {
  return typeof s === 'string' && (s.startsWith('/') || s.startsWith('http'))
}
</script>

<template>
  <section class="w-full">
    <!-- Überschrift -->
    <component :is="headingTag()" v-if="type === 'headline'"
      class="font-extrabold text-[var(--color-text-primary)]"
      :class="v.level === 'h1' ? 'text-3xl' : v.level === 'h3' ? 'text-lg' : 'text-2xl'">
      {{ v.text }}
    </component>

    <p v-else-if="type === 'subline'" class="text-lg text-[var(--color-text-secondary)]">{{ v.text }}</p>

    <p v-else-if="type === 'text'" class="text-sm text-[var(--color-text-secondary)] whitespace-pre-line leading-relaxed">{{ v.body }}</p>

    <!-- Teaser -->
    <div v-else-if="type === 'teaser'" class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border)] p-6 space-y-2">
      <h3 class="text-xl font-bold">{{ v.headline }}</h3>
      <p class="text-sm text-[var(--color-text-secondary)] whitespace-pre-line">{{ v.body }}</p>
    </div>

    <!-- CTA-Banner -->
    <div v-else-if="type === 'cta-banner'" class="rounded-2xl bg-red-600 text-white p-6 sm:p-8 space-y-2">
      <h3 class="text-2xl font-extrabold italic">{{ v.headline }}</h3>
      <p v-if="v.body" class="text-sm opacity-90 whitespace-pre-line">{{ v.body }}</p>
      <button v-if="v.cta" class="mt-2 inline-flex items-center gap-1 bg-white text-red-700 font-bold text-sm px-4 py-2 rounded-full">{{ v.cta }}</button>
    </div>

    <!-- Countdown -->
    <div v-else-if="type === 'countdown'" class="rounded-2xl bg-pink-50 dark:bg-pink-950/20 border border-pink-200 dark:border-pink-900 p-5 flex items-center justify-between gap-4">
      <div>
        <h3 class="text-base font-bold text-[var(--color-text-primary)]">{{ v.headline }}</h3>
        <p v-if="v.subtext" class="text-xs text-[var(--color-text-tertiary)]">{{ v.subtext }}</p>
      </div>
      <div v-if="countdown" class="flex items-center gap-2 font-mono">
        <span v-for="(unit, i) in [['T', countdown.d], ['Std', countdown.h], ['Min', countdown.m], ['Sek', countdown.s]]" :key="i"
          class="flex flex-col items-center bg-white dark:bg-black/30 rounded-lg px-2.5 py-1.5 min-w-12">
          <span class="text-lg font-bold tabular-nums">{{ String(unit[1]).padStart(2, '0') }}</span>
          <span class="text-[9px] text-[var(--color-text-tertiary)]">{{ unit[0] }}</span>
        </span>
      </div>
    </div>

    <!-- Bild / Video / Link -->
    <img v-else-if="type === 'image' && isImageUrl(v.image)" :src="v.image" :alt="v.caption" class="w-full rounded-2xl object-cover" />
    <a v-else-if="type === 'link'" :href="v.url" class="text-[var(--color-accent)] underline text-sm">{{ v.label || v.url }}</a>

    <!-- Spacer -->
    <div v-else-if="type === 'spacer'" :class="{ s: 'h-4', m: 'h-8', l: 'h-12', xl: 'h-20' }[v.size] || 'h-8'" />

    <!-- Einzelprodukt (Hero) -->
    <div v-else-if="type === 'product-teaser' && section.product" class="max-w-sm">
      <SiteProductCard :product="section.product" />
    </div>

    <!-- Produkt-Galerie / Auswahl -->
    <div v-else-if="type === 'product-gallery'" class="space-y-3">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <div v-if="section.products?.length" class="grid gap-3" :class="gridCols">
        <SiteProductCard v-for="p in section.products" :key="p.id" :product="p" />
      </div>
      <p v-else class="text-xs text-[var(--color-text-tertiary)]">Keine Produkte ausgewählt.</p>
    </div>

    <!-- Produktliste (PQL) — Hinweis: Auflösung folgt -->
    <div v-else-if="type === 'product-list'" class="space-y-2">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <p class="text-xs text-[var(--color-text-tertiary)]">Dynamische Produktliste (PQL) — Live-Auflösung folgt.</p>
    </div>

    <!-- Kategorie-Grid -->
    <div v-else-if="type === 'category-grid'" class="space-y-3">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <div class="grid gap-3" :class="gridCols">
        <div v-for="c in (section.categories || [])" :key="c.id"
          class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 text-center">
          <p class="text-sm font-semibold">{{ c.name }}</p>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">{{ c.product_count }} Produkte</p>
        </div>
      </div>
    </div>

    <!-- Kategorie-Teaser -->
    <div v-else-if="type === 'category-teaser' && section.category"
      class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
      <p class="text-sm font-semibold">{{ section.category.name }}</p>
      <p class="text-[11px] text-[var(--color-text-tertiary)]">{{ section.category.product_count }} Produkte</p>
    </div>

    <!-- Promo-Karussell -->
    <div v-else-if="type === 'promo-carousel'" class="space-y-3">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <div class="flex gap-3 overflow-x-auto pb-2 snap-x">
        <div v-for="slide in (section.slides || [])" :key="slide.id"
          class="snap-start shrink-0 w-72 rounded-2xl bg-red-600 text-white p-5 space-y-1">
          <h4 class="text-lg font-extrabold italic">{{ slide.values?.headline }}</h4>
          <p class="text-xs opacity-90 whitespace-pre-line">{{ slide.values?.body }}</p>
        </div>
      </div>
    </div>

    <!-- Fallback -->
    <div v-else class="text-[11px] text-[var(--color-text-tertiary)] border border-dashed border-[var(--color-border)] rounded-lg px-3 py-2">
      Block: {{ type }}
    </div>
  </section>
</template>
