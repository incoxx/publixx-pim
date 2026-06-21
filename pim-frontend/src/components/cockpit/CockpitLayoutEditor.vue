<script setup>
import { reactive, watch } from 'vue'
import { ChevronUp, ChevronDown, X, Plus } from 'lucide-vue-next'
import { allTiles, widgetsForZone } from '@/config/cockpitCatalog'

/**
 * Wiederverwendbarer Zonen-Editor für Cockpit-Layouts.
 * Wird vom Admin-Editor (Rollen-Layout) und vom persönlichen Editor genutzt.
 */
const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

const ZONES = [
  { key: 'tiles', label: 'Schnellzugriff (Kacheln)', catalog: allTiles() },
  { key: 'workplace', label: 'Mein Arbeitsplatz', catalog: widgetsForZone('workplace') },
  { key: 'content', label: 'Medien & Content', catalog: widgetsForZone('content') },
  { key: 'kpis', label: 'Überblick (KPIs)', catalog: widgetsForZone('kpis') },
]

const layout = reactive({ tiles: [], workplace: [], content: [], kpis: [] })

watch(() => props.modelValue, (v) => {
  for (const z of ZONES) {
    layout[z.key] = Array.isArray(v?.[z.key]) ? [...v[z.key]] : []
  }
}, { immediate: true })

function sync() {
  emit('update:modelValue', {
    tiles: [...layout.tiles],
    workplace: [...layout.workplace],
    content: [...layout.content],
    kpis: [...layout.kpis],
  })
}

function labelFor(zoneKey, id) {
  const zone = ZONES.find(z => z.key === zoneKey)
  return zone?.catalog.find(c => c.id === id)?.label || id
}
function availableFor(zoneKey) {
  const zone = ZONES.find(z => z.key === zoneKey)
  return zone.catalog.filter(c => !layout[zoneKey].includes(c.id))
}
function addItem(zoneKey, id) {
  if (!layout[zoneKey].includes(id)) { layout[zoneKey].push(id); sync() }
}
function removeItem(zoneKey, idx) {
  layout[zoneKey].splice(idx, 1); sync()
}
function moveUp(zoneKey, idx) {
  if (idx <= 0) return
  const a = layout[zoneKey]
  ;[a[idx - 1], a[idx]] = [a[idx], a[idx - 1]]
  sync()
}
function moveDown(zoneKey, idx) {
  const a = layout[zoneKey]
  if (idx >= a.length - 1) return
  ;[a[idx + 1], a[idx]] = [a[idx], a[idx + 1]]
  sync()
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-for="zone in ZONES"
      :key="zone.key"
      class="pim-card p-4"
    >
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">{{ zone.label }}</h3>

      <!-- Gewählte Bausteine (geordnet) -->
      <div v-if="layout[zone.key].length" class="space-y-1.5 mb-3">
        <div
          v-for="(id, idx) in layout[zone.key]"
          :key="id"
          class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)]"
        >
          <span class="text-[10px] text-[var(--color-text-tertiary)] w-4 text-center">{{ idx + 1 }}</span>
          <span class="text-xs text-[var(--color-text-primary)] flex-1">{{ labelFor(zone.key, id) }}</span>
          <button class="p-0.5 rounded hover:bg-[var(--color-surface)] disabled:opacity-30" :disabled="idx === 0" title="Nach oben" @click="moveUp(zone.key, idx)">
            <ChevronUp class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button class="p-0.5 rounded hover:bg-[var(--color-surface)] disabled:opacity-30" :disabled="idx === layout[zone.key].length - 1" title="Nach unten" @click="moveDown(zone.key, idx)">
            <ChevronDown class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-error)]" title="Entfernen" @click="removeItem(zone.key, idx)">
            <X class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </div>
      <p v-else class="text-[11px] text-[var(--color-text-tertiary)] mb-3">Keine Bausteine in dieser Zone.</p>

      <!-- Hinzufügbare Bausteine -->
      <div v-if="availableFor(zone.key).length" class="flex flex-wrap gap-1.5">
        <button
          v-for="item in availableFor(zone.key)"
          :key="item.id"
          class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)] hover:text-[var(--color-accent)] transition"
          @click="addItem(zone.key, item.id)"
        >
          <Plus class="w-3 h-3" :stroke-width="2" /> {{ item.label }}
        </button>
      </div>
    </div>
  </div>
</template>
