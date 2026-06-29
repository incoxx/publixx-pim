<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import navigationApi from '@/api/navigation'

const props = defineProps({
  navigation: { type: Object, required: true },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const saving = ref(false)
const error = ref(null)

const DEFAULTS = {
  primary: '#e11d48', on_primary: '#ffffff', accent: '#3b82f6',
  text: '#111827', muted: '#6b7280', background: '#f8fafc',
  surface: '#ffffff', border: '#e5e7eb', radius: '1rem', font: 'system-ui, sans-serif',
}

const COLORS = [
  { key: 'primary', label: 'Primär (Header / CTA)' },
  { key: 'on_primary', label: 'Text auf Primär' },
  { key: 'accent', label: 'Akzent (Buttons / Links)' },
  { key: 'background', label: 'Seitenhintergrund' },
  { key: 'surface', label: 'Karten / Flächen' },
  { key: 'text', label: 'Text' },
  { key: 'muted', label: 'Sekundärtext' },
  { key: 'border', label: 'Rahmen' },
]
const RADII = [
  { value: '0px', label: 'Eckig (0)' },
  { value: '0.5rem', label: 'Leicht (8px)' },
  { value: '1rem', label: 'Mittel (16px)' },
  { value: '1.5rem', label: 'Stark (24px)' },
]
const FONTS = [
  { value: 'system-ui, sans-serif', label: 'System (Sans-Serif)' },
  { value: 'Georgia, serif', label: 'Serif (Georgia)' },
  { value: '"Helvetica Neue", Arial, sans-serif', label: 'Helvetica / Arial' },
  { value: '"Courier New", monospace', label: 'Monospace' },
]

const theme = ref({ ...DEFAULTS, ...(props.navigation.theme_json || {}) })

function resetDefaults() {
  theme.value = { ...DEFAULTS }
}

async function save() {
  saving.value = true
  error.value = null
  try {
    await navigationApi.update(props.navigation.id, { theme_json: theme.value })
    authStore.closePanel()
    if (props.onSaved) props.onSaved(theme.value)
  } catch (e) {
    error.value = e.response?.data?.message || 'Speichern fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="p-4 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Website-Theme — {{ navigation.name_de }}</h3>
    <p class="text-[11px] text-[var(--color-text-tertiary)]">
      Farben, Schrift und Radius für die gesamte Website (Vorschau). Gilt für alle Seiten dieser Navigation.
    </p>
    <p v-if="error" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ error }}</p>

    <!-- Farben -->
    <div class="space-y-2">
      <div v-for="c in COLORS" :key="c.key" class="flex items-center justify-between gap-2">
        <label class="text-[12px] text-[var(--color-text-secondary)]">{{ c.label }}</label>
        <div class="flex items-center gap-1.5">
          <input type="color" v-model="theme[c.key]" class="h-7 w-9 rounded border border-[var(--color-border)] cursor-pointer" />
          <input v-model="theme[c.key]" class="pim-input text-[11px] w-24 font-mono" />
        </div>
      </div>
    </div>

    <!-- Radius + Schrift -->
    <div class="grid grid-cols-1 gap-2 pt-2 border-t border-[var(--color-border)]">
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Eckenradius</label>
        <select v-model="theme.radius" class="pim-input text-xs w-full">
          <option v-for="r in RADII" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Schriftart</label>
        <select v-model="theme.font" class="pim-input text-xs w-full">
          <option v-for="f in FONTS" :key="f.value" :value="f.value">{{ f.label }}</option>
        </select>
      </div>
    </div>

    <!-- Mini-Vorschau -->
    <div class="rounded-lg overflow-hidden border border-[var(--color-border)]" :style="{ fontFamily: theme.font }">
      <div class="px-3 py-2 text-xs font-bold" :style="{ background: theme.primary, color: theme.on_primary }">Header</div>
      <div class="p-3 text-xs" :style="{ background: theme.background, color: theme.text }">
        <div class="inline-block p-2 mb-2" :style="{ background: theme.surface, border: `1px solid ${theme.border}`, borderRadius: theme.radius }">Karte</div>
        <button class="px-2 py-1 text-white text-[11px] rounded" :style="{ background: theme.accent }">Button</button>
      </div>
    </div>

    <div class="flex items-center gap-2 pt-3 border-t border-[var(--color-border)]">
      <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="save">{{ saving ? 'Speichern…' : 'Speichern' }}</button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="resetDefaults">Zurücksetzen</button>
      <button class="pim-btn pim-btn-ghost text-xs" @click="authStore.closePanel()">Abbrechen</button>
    </div>
  </div>
</template>
