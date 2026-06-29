<script setup>
import { computed } from 'vue'

const props = defineProps({
  product: { type: Object, required: true },
  compact: { type: Boolean, default: false }, // list-row-Variante
})

// Rollenwert aus der Widget-Auflösung (display.fields), sonst Basis-Summary.
function role(name) {
  const f = props.product.display?.fields?.find((x) => x.role === name)
  return f ? f.value : null
}

const image = computed(() => role('image') || props.product.image)
const title = computed(() => role('title') || props.product.name)
const subtitle = computed(() => role('subtitle'))
const badge = computed(() => role('badge'))
const rawPrice = computed(() => {
  const p = role('price')
  return p ?? props.product.price
})
const ratio = computed(() => props.product.display?.image_ratio || '4/3')
const showSku = computed(() => props.product.display?.show_sku)
const cta = computed(() => props.product.display?.cta)

const price = computed(() => {
  if (rawPrice.value == null) return null
  try {
    return new Intl.NumberFormat('de-DE', {
      style: 'currency',
      currency: props.product.currency || 'EUR',
    }).format(Number(rawPrice.value))
  } catch {
    return `${rawPrice.value} ${props.product.currency || ''}`.trim()
  }
})
</script>

<template>
  <div
    class="border overflow-hidden flex"
    :class="compact ? 'flex-row items-center gap-3 p-2' : 'flex-col'"
    style="background: var(--site-surface, var(--color-surface)); border-color: var(--site-border, var(--color-border)); border-radius: var(--site-radius, 0.75rem)"
  >
    <!-- Bild -->
    <div
      class="bg-[var(--color-bg)] flex items-center justify-center shrink-0 overflow-hidden"
      :class="compact ? 'w-16 h-16 rounded-lg' : 'w-full'"
      :style="compact ? '' : `aspect-ratio:${ratio}`"
    >
      <img v-if="image" :src="image" :alt="title" class="w-full h-full object-contain" />
      <span v-else class="text-[10px] text-[var(--color-text-tertiary)]">kein Bild</span>
    </div>

    <!-- Text -->
    <div :class="compact ? 'min-w-0 flex-1' : 'p-3 space-y-1'">
      <div v-if="badge" class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-600 text-white">{{ badge }}</div>
      <h4 class="text-sm font-semibold text-[var(--color-text-primary)] line-clamp-2">{{ title }}</h4>
      <p v-if="subtitle" class="text-xs text-[var(--color-text-tertiary)] line-clamp-2">{{ subtitle }}</p>
      <p v-if="showSku && product.sku" class="text-[10px] font-mono text-[var(--color-text-tertiary)]">{{ product.sku }}</p>
      <div class="flex items-center justify-between pt-1">
        <span v-if="price" class="text-base font-bold text-[var(--color-text-primary)]">{{ price }}</span>
        <button v-if="cta?.enabled" class="text-xs font-medium px-2 py-1 rounded-lg text-white"
          style="background: var(--site-accent, var(--color-accent))">
          {{ cta.label || 'Details' }}
        </button>
      </div>
    </div>
  </div>
</template>
