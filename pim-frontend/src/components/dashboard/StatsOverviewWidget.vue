<script setup>
import { Package, GitBranch, Sliders, Image } from 'lucide-vue-next'

defineProps({
  stats: { type: Object, default: null },
})

const cards = [
  { key: 'products_total', label: 'Produkte', sub: 'products_active', subLabel: 'aktiv', icon: Package, color: 'var(--color-accent)' },
  { key: 'hierarchies_count', label: 'Hierarchien', icon: GitBranch, color: 'var(--color-success)' },
  { key: 'attributes_count', label: 'Attribute', icon: Sliders, color: 'var(--color-warning)' },
  { key: 'media_count', label: 'Medien', icon: Image, color: 'var(--color-error)' },
]
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div v-for="card in cards" :key="card.key" class="pim-card p-5 flex items-center gap-4">
      <div
        class="w-10 h-10 rounded-lg flex items-center justify-center"
        :style="{ background: `color-mix(in srgb, ${card.color} 10%, transparent)` }"
      >
        <component :is="card.icon" class="w-5 h-5" :style="{ color: card.color }" :stroke-width="1.75" />
      </div>
      <div>
        <p class="text-2xl font-semibold">{{ stats?.[card.key] ?? '--' }}</p>
        <p class="text-xs text-[var(--color-text-tertiary)]">{{ card.label }}</p>
        <p v-if="card.sub && stats" class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
          {{ stats[card.sub] ?? 0 }} {{ card.subLabel }}
        </p>
      </div>
    </div>
  </div>
</template>
