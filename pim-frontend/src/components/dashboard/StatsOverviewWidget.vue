<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Package, GitBranch, Sliders, Image, TrendingUp, TrendingDown } from 'lucide-vue-next'

const { t } = useI18n()

const props = defineProps({
  stats: { type: Object, default: null },
  trends: { type: Object, default: null },
})

const cards = [
  { key: 'products_total', label: 'Produkte', sub: 'products_active', subLabel: 'aktiv', icon: Package, color: 'var(--color-accent)', trendKey: 'products' },
  { key: 'hierarchies_count', label: 'Hierarchien', icon: GitBranch, color: 'var(--color-success)', trendKey: null },
  { key: 'attributes_count', label: 'Attribute', icon: Sliders, color: 'var(--color-warning)', trendKey: null },
  { key: 'media_count', label: 'Medien', icon: Image, color: 'var(--color-error)', trendKey: 'media' },
]

function sparklinePath(data) {
  if (!data || data.length === 0) return ''
  const max = Math.max(...data, 1)
  const w = 60
  const h = 20
  const step = w / (data.length - 1 || 1)
  return data.map((v, i) => {
    const x = i * step
    const y = h - (v / max) * h
    return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
}

function getTrend(trendKey) {
  if (!trendKey || !props.trends?.[trendKey]) return null
  return props.trends[trendKey]
}
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div v-for="card in cards" :key="card.key" class="pim-card p-5 flex items-center gap-4">
      <div
        class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
        :style="{ background: `color-mix(in srgb, ${card.color} 10%, transparent)` }"
      >
        <component :is="card.icon" class="w-5 h-5" :style="{ color: card.color }" :stroke-width="1.75" />
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-2xl font-semibold">{{ stats?.[card.key] ?? '--' }}</p>
        <p class="text-xs text-[var(--color-text-tertiary)]">{{ t(card.label) }}</p>
        <p v-if="card.sub && stats" class="text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
          {{ stats[card.sub] ?? 0 }} {{ t(card.subLabel) }}
        </p>
        <!-- Trend-Zeile -->
        <div v-if="getTrend(card.trendKey)" class="flex items-center gap-2 mt-1.5">
          <svg width="60" height="20" class="shrink-0">
            <path
              :d="sparklinePath(getTrend(card.trendKey).daily)"
              fill="none"
              :stroke="card.color"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <span
            v-if="getTrend(card.trendKey).total > 0"
            class="text-[10px] font-medium text-emerald-600 flex items-center gap-0.5"
          >
            <TrendingUp class="w-3 h-3" :stroke-width="2" />
            +{{ getTrend(card.trendKey).total }}
          </span>
          <span
            v-else
            class="text-[10px] text-[var(--color-text-tertiary)]"
          >&mdash;</span>
        </div>
      </div>
    </div>
  </div>
</template>
