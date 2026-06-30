<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import SiteProductCard from './SiteProductCard.vue'
import SectionEcommerceCart from './SectionEcommerceCart.vue'
import { useCartStore } from '@/stores/cart'

const props = defineProps({
  section: { type: Object, required: true },
})

const emit = defineEmits(['navigate'])

const cartStore = useCartStore()

async function onHeroCta() {
  // Warenkorb aktiv + Produkt vorhanden → in den Warenkorb legen
  if (cartStore.activeCartType && heroProduct.value?.id) {
    await cartStore.addItem(heroProduct.value.id, 1)
    cartStore.drawerOpen = true
    return
  }
  if (heroProduct.value) {
    emit('navigate', { kind: 'product', id: heroProduct.value.id, href: heroProduct.value.href })
  } else if (v.value.cta_url) {
    emit('navigate', { kind: 'url', url: v.value.cta_url })
  }
}

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

// ─── Hero / USP (schema-aufgelöste Felder mitnutzen) ──────────────
function field(key) {
  return (props.section.fields || []).find((x) => x.key === key)
}
const heroProduct = computed(() => field('product')?.products?.[0] || null)
const heroImage = computed(() => {
  const img = field('image')?.media
  return (typeof img === 'string' && img) ? img : (heroProduct.value?.image || null)
})
const heroPrice = computed(() => {
  const raw = heroProduct.value?.price
  if (raw == null) return null
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: heroProduct.value.currency || 'EUR' }).format(Number(raw))
  } catch { return `${raw} €` }
})
const uspItems = computed(() => ['usp1', 'usp2', 'usp3', 'usp4'].map((k) => v.value[k]).filter(Boolean))

// Section-Media werden als ID gespeichert; nur echte URLs/Pfade als Bild rendern
// (verhindert eine kaputte <img src="UUID">).
function isImageUrl(s) {
  return typeof s === 'string' && (s.startsWith('/') || s.startsWith('http'))
}

// Heuristik: Feld-Keys, die als Überschrift gerendert werden
function isHeadingKey(key) {
  return /headline|title|überschrift|titel|head/i.test(key || '')
}
</script>

<template>
  <section class="w-full">
    <!-- Hero-Banner (full-bleed, MediaMarkt-Stil) -->
    <div v-if="type === 'hero'"
      class="-mx-4 sm:-mx-6 -mt-4 sm:-mt-6 px-6 sm:px-10 py-10 sm:py-14"
      style="background-color: var(--site-primary); background-image: linear-gradient(135deg, rgba(255,255,255,0.14), rgba(0,0,0,0.28)); color: var(--site-on-primary)">
      <div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-10 max-w-5xl mx-auto">
        <div class="flex-1 space-y-3">
          <p v-if="v.eyebrow" class="text-xs font-bold uppercase tracking-[0.2em] opacity-80">{{ v.eyebrow }}</p>
          <h1 class="text-3xl sm:text-5xl font-black leading-[1.05]">{{ v.headline }}</h1>
          <p v-if="v.subline" class="text-base sm:text-lg opacity-90 max-w-md">{{ v.subline }}</p>
          <div class="flex items-center gap-4 pt-2">
            <button v-if="v.cta_label" type="button" @click="onHeroCta"
              class="inline-flex items-center font-bold text-sm px-5 py-2.5 rounded-full shadow-lg"
              style="background: var(--site-surface); color: var(--site-primary)">{{ v.cta_label }}</button>
            <span v-if="heroPrice" class="text-sm font-semibold opacity-90">ab {{ heroPrice }}</span>
          </div>
        </div>
        <div v-if="heroImage" class="flex-1 flex justify-center">
          <img :src="heroImage" :alt="v.headline" class="max-h-72 object-contain drop-shadow-2xl" />
        </div>
      </div>
    </div>

    <!-- USP-/Vertrauensleiste -->
    <div v-else-if="type === 'usp-strip'" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div v-for="(u, i) in uspItems" :key="i"
        class="flex items-center gap-2 rounded-xl px-3 py-2.5 border"
        style="background: var(--site-surface); border-color: var(--site-border)">
        <span class="flex items-center justify-center w-6 h-6 rounded-full text-white text-xs shrink-0" style="background: var(--site-accent)">✓</span>
        <span class="text-xs font-semibold text-[var(--color-text-primary)] leading-tight">{{ u }}</span>
      </div>
    </div>

    <!-- Überschrift -->
    <component :is="headingTag()" v-else-if="type === 'headline'"
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
    <div v-else-if="type === 'cta-banner'" class="rounded-2xl p-6 sm:p-8 space-y-2"
      style="background: var(--site-primary); color: var(--site-on-primary); border-radius: var(--site-radius)">
      <h3 class="text-2xl font-extrabold italic">{{ v.headline }}</h3>
      <p v-if="v.body" class="text-sm opacity-90 whitespace-pre-line">{{ v.body }}</p>
      <button v-if="v.cta" class="mt-2 inline-flex items-center gap-1 font-bold text-sm px-4 py-2 rounded-full"
        style="background: var(--site-surface); color: var(--site-primary)">{{ v.cta }}</button>
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
      <SiteProductCard :product="section.product" @navigate="emit('navigate', $event)" />
    </div>

    <!-- Produkt-Galerie / Auswahl -->
    <div v-else-if="type === 'product-gallery'" class="space-y-3">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <div v-if="section.products?.length" class="grid gap-3" :class="gridCols">
        <SiteProductCard v-for="p in section.products" :key="p.id" :product="p" @navigate="emit('navigate', $event)" />
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

    <!-- Technische Daten (Produkt-Detailseite) -->
    <div v-else-if="type === 'product-specs'" class="space-y-3">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-0 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
        <div v-for="(s, i) in (section.specs || [])" :key="i"
          class="flex items-baseline justify-between gap-3 py-2 border-b border-[var(--color-border)] last:border-0">
          <dt class="text-xs text-[var(--color-text-tertiary)]">{{ s.label }}</dt>
          <dd class="text-sm font-medium text-[var(--color-text-primary)] text-right">{{ s.value }}</dd>
        </div>
      </dl>
    </div>

    <!-- Promo-Karussell -->
    <div v-else-if="type === 'promo-carousel'" class="space-y-3">
      <h3 v-if="v.headline" class="text-xl font-bold">{{ v.headline }}</h3>
      <div class="flex gap-3 overflow-x-auto pb-2 snap-x">
        <div v-for="slide in (section.slides || [])" :key="slide.id"
          class="snap-start shrink-0 w-72 rounded-2xl p-5 space-y-1"
          style="background: var(--site-primary); color: var(--site-on-primary)">
          <h4 class="text-lg font-extrabold italic">{{ slide.values?.headline }}</h4>
          <p class="text-xs opacity-90 whitespace-pre-line">{{ slide.values?.body }}</p>
        </div>
      </div>
    </div>

    <!-- e-Commerce Warenkorb-Widget (System-Widget) -->
    <SectionEcommerceCart v-else-if="type === 'ecommerce_cart'" :section="section" />

    <!-- Generischer Renderer: jeder (auch eigene) Sektionstyp über seine Felder -->
    <div v-else class="space-y-3">
      <template v-for="f in (section.fields || [])" :key="f.key">
        <!-- Produkte -->
        <div v-if="f.type === 'product_ref' && f.products?.length" class="grid gap-3" :class="gridCols">
          <SiteProductCard v-for="p in f.products" :key="p.id" :product="p" @navigate="emit('navigate', $event)" />
        </div>
        <!-- Kategorien -->
        <div v-else-if="f.type === 'hierarchy_node_ref' && f.categories?.length" class="grid gap-3" :class="gridCols">
          <div v-for="c in f.categories" :key="c.id" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 text-center">
            <p class="text-sm font-semibold">{{ c.name }}</p>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">{{ c.product_count }} Produkte</p>
          </div>
        </div>
        <!-- Einzelbild -->
        <img v-else-if="f.type === 'Media' && typeof f.media === 'string' && f.media" :src="f.media" class="w-full rounded-2xl object-contain max-h-96" />
        <!-- Bildergalerie -->
        <div v-else-if="f.type === 'Media' && Array.isArray(f.media) && f.media.length" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <img v-for="(m, i) in f.media" :key="i" :src="m" class="w-full rounded-lg object-cover" />
        </div>
        <!-- Link -->
        <a v-else-if="f.type === 'Link' && f.value" :href="f.value" class="text-[var(--color-accent)] underline text-sm">{{ f.value }}</a>
        <!-- RichText / Textarea -->
        <p v-else-if="['RichText', 'Textarea'].includes(f.type) && f.value" class="text-sm text-[var(--color-text-secondary)] whitespace-pre-line leading-relaxed">{{ f.value }}</p>
        <!-- Überschrift-artige Strings -->
        <h3 v-else-if="isHeadingKey(f.key) && f.value" class="text-xl font-bold text-[var(--color-text-primary)]">{{ f.value }}</h3>
        <!-- sonstiger Text -->
        <p v-else-if="f.value !== null && f.value !== undefined && f.value !== ''" class="text-sm text-[var(--color-text-secondary)]">{{ f.value }}</p>
      </template>
      <p v-if="!(section.fields || []).length" class="text-[11px] text-[var(--color-text-tertiary)]">Block: {{ type }}</p>
    </div>
  </section>
</template>
