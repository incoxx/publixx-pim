<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { X } from 'lucide-vue-next'
import searchProfilesApi from '@/api/searchProfiles'

const { t } = useI18n()

const props = defineProps({
  config: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['save', 'cancel', 'remove'])

const profiles = ref([])
const loadingProfiles = ref(false)

const form = ref({
  search_profile_id: props.config.search_profile_id || '',
  chart_type: props.config.chart_type || 'gauge',
  metric: props.config.metric || 'completeness',
  color: props.config.color || '#2E75B6',
})

const chartTypes = [
  { value: 'gauge', label: 'Gauge (Halbkreis)' },
  { value: 'number', label: 'Zahl (groß)' },
  { value: 'trend', label: 'Trend (7 Tage)' },
  { value: 'bar', label: 'Status-Verteilung' },
  { value: 'donut', label: 'Donut (Multi-Metrik)' },
]

const metrics = [
  { value: 'completeness', label: 'Vollständigkeit' },
  { value: 'media_coverage', label: 'Medien-Abdeckung' },
  { value: 'price_coverage', label: 'Preis-Abdeckung' },
  { value: 'translation_coverage', label: 'Übersetzungen' },
  { value: 'total', label: 'Gesamt-Anzahl' },
  { value: 'active', label: 'Aktive Produkte' },
  { value: 'draft', label: 'Entwürfe' },
]

const colors = [
  { value: '#2E75B6', label: 'Blau' },
  { value: '#059669', label: 'Grün' },
  { value: '#D97706', label: 'Orange' },
  { value: '#DC2626', label: 'Rot' },
  { value: '#8b5cf6', label: 'Violett' },
  { value: '#06b6d4', label: 'Cyan' },
  { value: '#ec4899', label: 'Pink' },
  { value: '#111827', label: 'Dunkel' },
]

// Metrik nicht relevant für Trend/Bar/Donut
const showMetric = (type) => !['trend', 'bar', 'donut'].includes(type)

onMounted(async () => {
  loadingProfiles.value = true
  try {
    const { data } = await searchProfilesApi.list()
    profiles.value = data.data || data || []
  } catch { /* ignore */ }
  loadingProfiles.value = false
})

function save() {
  emit('save', { ...form.value })
}

const isEditing = !!props.config.search_profile_id
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" @click.self="emit('cancel')">
    <div class="bg-[var(--color-surface)] rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-[var(--color-border)]">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
          {{ isEditing ? t('Profilkarte bearbeiten') : t('Neue Profilkarte') }}
        </h3>
        <button @click="emit('cancel')" class="p-1 rounded hover:bg-[var(--color-bg)]">
          <X class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        </button>
      </div>

      <!-- Form -->
      <div class="p-5 space-y-4">
        <!-- Suchprofil -->
        <div>
          <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Suchprofil</label>
          <select
            v-model="form.search_profile_id"
            class="w-full text-sm px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] text-[var(--color-text-primary)] focus:outline-none focus:border-[var(--color-accent)]"
          >
            <option value="" disabled>Suchprofil wählen...</option>
            <option v-for="p in profiles" :key="p.id" :value="p.id">
              {{ p.name }}{{ p.is_shared ? ` ${t('(geteilt)')}` : '' }}
            </option>
          </select>
          <p v-if="loadingProfiles" class="text-[10px] text-[var(--color-text-tertiary)] mt-1">Laden...</p>
        </div>

        <!-- Chart-Typ -->
        <div>
          <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Darstellung</label>
          <div class="grid grid-cols-5 gap-1.5">
            <button
              v-for="ct in chartTypes"
              :key="ct.value"
              class="px-2 py-2 text-[10px] rounded-lg border transition-colors text-center"
              :class="form.chart_type === ct.value
                ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)] font-medium'
                : 'border-[var(--color-border)] text-[var(--color-text-tertiary)] hover:border-[var(--color-accent)]'"
              @click="form.chart_type = ct.value"
            >
              {{ t(ct.label) }}
            </button>
          </div>
        </div>

        <!-- Metrik (nicht für Trend/Bar/Donut) -->
        <div v-if="showMetric(form.chart_type)">
          <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Metrik</label>
          <select
            v-model="form.metric"
            class="w-full text-sm px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] text-[var(--color-text-primary)] focus:outline-none focus:border-[var(--color-accent)]"
          >
            <option v-for="m in metrics" :key="m.value" :value="m.value">{{ t(m.label) }}</option>
          </select>
        </div>

        <!-- Farbe -->
        <div>
          <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Farbe</label>
          <div class="flex items-center gap-2">
            <button
              v-for="c in colors"
              :key="c.value"
              class="w-7 h-7 rounded-full border-2 transition-all"
              :style="{ background: c.value }"
              :class="form.color === c.value ? 'border-[var(--color-text-primary)] scale-110' : 'border-transparent'"
              :title="t(c.label)"
              @click="form.color = c.value"
            />
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between px-5 py-3 border-t border-[var(--color-border)] bg-[var(--color-bg)]">
        <button
          v-if="isEditing"
          class="text-xs text-[var(--color-error)] hover:underline"
          @click="emit('remove', config)"
        >
          Entfernen
        </button>
        <span v-else />
        <div class="flex items-center gap-2">
          <button
            class="px-3 py-1.5 text-xs rounded-lg border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]"
            @click="emit('cancel')"
          >Abbrechen</button>
          <button
            class="px-4 py-1.5 text-xs rounded-lg bg-[var(--color-accent)] text-white hover:opacity-90 disabled:opacity-50"
            :disabled="!form.search_profile_id"
            @click="save"
          >Speichern</button>
        </div>
      </div>
    </div>
  </div>
</template>
