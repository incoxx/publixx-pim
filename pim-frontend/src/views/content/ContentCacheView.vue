<script setup>
import { ref, computed, onMounted } from 'vue'
import contentApi from '@/api/content'
import { Gauge, RefreshCw, Trash2, RotateCcw, CheckCircle2, XCircle } from 'lucide-vue-next'

const stats = ref(null)
const loading = ref(false)
const busy = ref(false)
const message = ref('')
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await contentApi.cacheStats()
    stats.value = data.data ?? data
  } catch (e) {
    error.value = e.response?.data?.message || 'Status konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

const hitRatePct = computed(() => Math.round((stats.value?.metrics?.hit_rate ?? 0) * 100))
const total = computed(() => stats.value?.metrics?.total ?? 0)
const hits = computed(() => stats.value?.metrics?.hits ?? 0)
const misses = computed(() => stats.value?.metrics?.misses ?? 0)

// Farbe der Hit-Rate (rot < 50 < gelb < 80 < grün)
const rateColor = computed(() => {
  const r = hitRatePct.value
  if (total.value === 0) return 'var(--color-text-tertiary)'
  if (r >= 80) return '#10b981'
  if (r >= 50) return '#f59e0b'
  return '#ef4444'
})

async function flash(msg) {
  message.value = msg
  setTimeout(() => { if (message.value === msg) message.value = '' }, 2500)
}

async function clearCache() {
  if (!confirm('Kompletten Content-Cache leeren? Die nächste Anfrage rendert neu.')) return
  busy.value = true
  try {
    const { data } = await contentApi.clearCache({})
    stats.value = { ...stats.value, metrics: data.metrics }
    flash(data.message || 'Cache geleert.')
  } catch (e) {
    error.value = e.response?.data?.message || 'Leeren fehlgeschlagen.'
  } finally {
    busy.value = false
  }
}

async function resetMetrics() {
  busy.value = true
  try {
    const { data } = await contentApi.clearCache({ metrics_only: true })
    stats.value = { ...stats.value, metrics: data.metrics }
    flash('Metriken zurückgesetzt.')
  } catch (e) {
    error.value = e.response?.data?.message || 'Zurücksetzen fehlgeschlagen.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-4 max-w-3xl" data-testid="content-cache">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <Gauge class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Content-Cache</h2>
      </div>
      <button class="pim-btn pim-btn-secondary text-xs" :disabled="loading" @click="load">
        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" :stroke-width="2" /> Aktualisieren
      </button>
    </div>

    <p v-if="error" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ error }}</p>
    <p v-if="message" class="text-[12px] text-emerald-700 bg-emerald-50 dark:bg-emerald-950/20 px-3 py-2 rounded-lg">{{ message }}</p>

    <div v-if="loading && !stats" class="pim-skeleton h-40 rounded-xl" />

    <template v-else-if="stats">
      <!-- Status-Karten -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <!-- Hit-Rate (Gauge) -->
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 flex flex-col items-center justify-center">
          <div class="relative w-24 h-24">
            <svg viewBox="0 0 36 36" class="w-24 h-24 -rotate-90">
              <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--color-bg)" stroke-width="3.5" />
              <circle cx="18" cy="18" r="15.9155" fill="none" :stroke="rateColor" stroke-width="3.5"
                stroke-linecap="round" :stroke-dasharray="`${hitRatePct}, 100`" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-xl font-bold" :style="{ color: rateColor }">{{ total ? hitRatePct + '%' : '–' }}</span>
              <span class="text-[10px] text-[var(--color-text-tertiary)]">Hit-Rate</span>
            </div>
          </div>
        </div>

        <!-- Treffer / Misses -->
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2 justify-center flex flex-col">
          <div class="flex items-center justify-between">
            <span class="text-xs text-[var(--color-text-tertiary)]">Treffer (HIT)</span>
            <span class="text-sm font-semibold text-emerald-600">{{ hits.toLocaleString('de-DE') }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-xs text-[var(--color-text-tertiary)]">Fehlschläge (MISS)</span>
            <span class="text-sm font-semibold text-[var(--color-text-secondary)]">{{ misses.toLocaleString('de-DE') }}</span>
          </div>
          <div class="flex items-center justify-between border-t border-[var(--color-border)] pt-2">
            <span class="text-xs text-[var(--color-text-tertiary)]">Gesamt</span>
            <span class="text-sm font-semibold">{{ total.toLocaleString('de-DE') }}</span>
          </div>
        </div>

        <!-- Konfiguration -->
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2 justify-center flex flex-col">
          <div class="flex items-center justify-between">
            <span class="text-xs text-[var(--color-text-tertiary)]">Status</span>
            <span class="inline-flex items-center gap-1 text-xs font-semibold" :class="stats.enabled ? 'text-emerald-600' : 'text-[var(--color-text-tertiary)]'">
              <component :is="stats.enabled ? CheckCircle2 : XCircle" class="w-3.5 h-3.5" :stroke-width="2" />
              {{ stats.enabled ? 'Aktiv' : 'Deaktiviert' }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-xs text-[var(--color-text-tertiary)]">Server-TTL</span>
            <span class="text-sm font-medium">{{ Math.round(stats.ttl / 60) }} min</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-xs text-[var(--color-text-tertiary)]">HTTP max-age</span>
            <span class="text-sm font-medium">{{ stats.http_max_age }} s</span>
          </div>
        </div>
      </div>

      <!-- Aktionen -->
      <div class="flex flex-wrap items-center gap-2">
        <button class="pim-btn pim-btn-primary text-xs" :disabled="busy" @click="clearCache">
          <Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> Cache leeren
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" :disabled="busy" @click="resetMetrics">
          <RotateCcw class="w-3.5 h-3.5" :stroke-width="2" /> Metriken zurücksetzen
        </button>
      </div>

      <p class="text-[11px] text-[var(--color-text-tertiary)] leading-relaxed">
        Gecacht werden die öffentlichen Ausgaben (Sitemap, Seiten, Produktseiten) für anonyme Besucher.
        Eingeloggte Redakteure sehen immer den Live-Stand. Inhalts- und Produktänderungen invalidieren den
        Cache automatisch – „Cache leeren" ist nur als Notbremse nötig.
      </p>
    </template>
  </div>
</template>
