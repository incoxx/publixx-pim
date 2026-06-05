<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { conformance, referenceProfiles } from '@/api/referenceProfiles'
import { ShieldCheck, ShieldAlert, ShieldX, RefreshCw, AlertTriangle, Info, XCircle, Sparkles } from 'lucide-vue-next'

const props = defineProps({
  productId: { type: String, required: true },
})

const authStore = useAuthStore()
const canRun = () => authStore.hasPermission('conformance.run')
const canExplain = () => authStore.hasPermission('conformance.ai-explain')

const loading = ref(true)
const checking = ref(false)
const result = ref(null)
const profile = ref(null)
const isStale = ref(false)

const profiles = ref([])
const selectedProfileId = ref('')
const assigning = ref(false)

const STATUS_META = {
  pass: { label: 'Konform', icon: ShieldCheck, color: 'var(--color-success)' },
  warning: { label: 'Warnungen', icon: ShieldAlert, color: 'var(--color-warning)' },
  fail: { label: 'Nicht konform', icon: ShieldX, color: 'var(--color-error)' },
}
const statusMeta = computed(() => STATUS_META[result.value?.status] || null)

const SEVERITY_META = {
  error: { label: 'Fehler', icon: XCircle, color: 'var(--color-error)' },
  warning: { label: 'Warnung', icon: AlertTriangle, color: 'var(--color-warning)' },
  info: { label: 'Hinweis', icon: Info, color: 'var(--color-text-tertiary)' },
}

const groupedDeviations = computed(() => {
  const groups = { error: [], warning: [], info: [] }
  for (const d of (result.value?.deviations || [])) {
    (groups[d.severity] || groups.info).push(d)
  }
  return groups
})

async function loadAll() {
  loading.value = true
  try {
    const [{ data: conf }, { data: profData }] = await Promise.all([
      conformance.show(props.productId),
      referenceProfiles.list({ only_concrete: true }),
    ])
    result.value = conf.data
    profile.value = conf.profile
    isStale.value = conf.is_stale
    selectedProfileId.value = conf.profile?.id || ''
    profiles.value = (profData.data || profData).filter(p => !p.is_abstract)
  } catch {
    result.value = null
  } finally {
    loading.value = false
  }
}

async function assignProfile() {
  assigning.value = true
  try {
    const { data } = await conformance.assign(props.productId, selectedProfileId.value || null)
    result.value = data.result
    await loadAll()
  } finally {
    assigning.value = false
  }
}

async function recheck() {
  checking.value = true
  try {
    const { data } = await conformance.check(props.productId)
    result.value = data.data
    isStale.value = false
    explanation.value = ''
  } finally {
    checking.value = false
  }
}

const explaining = ref(false)
const explanation = ref('')
const explainError = ref('')

async function explain() {
  explaining.value = true
  explainError.value = ''
  try {
    const { data } = await conformance.explain(props.productId)
    explanation.value = data.data?.text || ''
  } catch (e) {
    explainError.value = e.response?.data?.message || 'KI-Erklärung fehlgeschlagen.'
  } finally {
    explaining.value = false
  }
}

function fmt(val) {
  if (val === null || val === undefined) return '—'
  if (Array.isArray(val)) return val.join(', ')
  if (typeof val === 'boolean') return val ? 'ja' : 'nein'
  return String(val)
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-4">
    <div v-if="loading" class="text-center py-12 text-sm text-[var(--color-text-tertiary)]">
      Lade Konformität…
    </div>

    <template v-else>
      <!-- Profil-Zuweisung -->
      <div class="pim-card p-3 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-48">
          <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Referenz-Profil</label>
          <select v-model="selectedProfileId" class="pim-input w-full">
            <option value="">— kein Profil —</option>
            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <button v-if="canRun()" class="pim-btn pim-btn-secondary" :disabled="assigning || selectedProfileId === (profile?.id || '')"
                @click="assignProfile">
          {{ assigning ? 'Speichert…' : 'Zuweisen' }}
        </button>
        <button v-if="profile && canRun()" class="pim-btn pim-btn-primary" :disabled="checking" @click="recheck">
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': checking }" />
          Neu prüfen
        </button>
        <button v-if="profile && result && result.deviations?.length && canExplain()" class="pim-btn pim-btn-secondary"
                :disabled="explaining" @click="explain">
          <Sparkles class="w-4 h-4" />
          {{ explaining ? 'Analysiere…' : 'KI-Erklärung' }}
        </button>
      </div>

      <!-- Kein Profil -->
      <div v-if="!profile" class="text-center py-10 text-sm text-[var(--color-text-tertiary)]">
        Diesem Produkt ist kein Referenz-Profil zugewiesen.
        Wähle oben ein Profil, um die Konformität zu prüfen.
      </div>

      <template v-else>
        <!-- Veraltet-Hinweis -->
        <div v-if="isStale" class="flex items-center gap-2 text-sm rounded p-2"
             style="background: color-mix(in srgb, var(--color-warning) 12%, transparent); color: var(--color-warning)">
          <AlertTriangle class="w-4 h-4" />
          Das Profil wurde seit der letzten Prüfung geändert. Bitte neu prüfen.
        </div>

        <!-- Status-Kopf -->
        <div v-if="result" class="pim-card p-4 flex items-center gap-4">
          <component :is="statusMeta?.icon" class="w-9 h-9" :style="{ color: statusMeta?.color }" />
          <div class="flex-1">
            <div class="text-base font-semibold" :style="{ color: statusMeta?.color }">
              {{ statusMeta?.label }}
            </div>
            <div class="text-xs text-[var(--color-text-tertiary)]">
              {{ result.error_count }} Fehler · {{ result.warning_count }} Warnungen · {{ result.info_count }} Hinweise
            </div>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold" :style="{ color: statusMeta?.color }">{{ result.score }}%</div>
            <div class="text-xs text-[var(--color-text-tertiary)]">Score</div>
          </div>
        </div>

        <!-- KI-Erklärung -->
        <div v-if="explainError" class="text-sm rounded p-2"
             style="background: color-mix(in srgb, var(--color-error) 12%, transparent); color: var(--color-error)">
          {{ explainError }}
        </div>
        <div v-if="explanation" class="pim-card p-3 space-y-1">
          <div class="flex items-center gap-1.5 text-xs font-medium" style="color: var(--color-primary, #7c3aed)">
            <Sparkles class="w-3.5 h-3.5" /> KI-Erklärung
          </div>
          <div class="text-sm text-[var(--color-text-primary)] whitespace-pre-wrap leading-relaxed">{{ explanation }}</div>
        </div>

        <!-- Noch nicht geprüft -->
        <div v-if="!result" class="text-center py-10 text-sm text-[var(--color-text-tertiary)]">
          Noch keine Prüfung vorhanden. Klicke „Neu prüfen".
        </div>

        <!-- Abweichungen -->
        <div v-else-if="result.deviations?.length" class="space-y-3">
          <template v-for="sev in ['error', 'warning', 'info']" :key="sev">
            <div v-if="groupedDeviations[sev].length" class="space-y-1.5">
              <div class="flex items-center gap-1.5 text-xs font-medium" :style="{ color: SEVERITY_META[sev].color }">
                <component :is="SEVERITY_META[sev].icon" class="w-3.5 h-3.5" />
                {{ SEVERITY_META[sev].label }} ({{ groupedDeviations[sev].length }})
              </div>
              <div v-for="(d, i) in groupedDeviations[sev]" :key="i"
                   class="border border-[var(--color-border)] rounded p-2 text-sm">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-xs text-[var(--color-text-secondary)]">{{ d.attribute }}</span>
                  <span class="text-xs text-[var(--color-text-tertiary)]">{{ d.check }}</span>
                </div>
                <div class="text-[var(--color-text-primary)]">{{ d.message }}</div>
                <div class="text-xs text-[var(--color-text-tertiary)] mt-0.5">
                  Erwartet: {{ fmt(d.expected) }}<span v-if="d.unit"> {{ d.unit }}</span>
                  · Ist: {{ fmt(d.actual) }}
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Alles konform -->
        <div v-else class="flex items-center gap-2 text-sm py-6 justify-center" style="color: var(--color-success)">
          <ShieldCheck class="w-5 h-5" /> Alle Prüfregeln erfüllt.
        </div>
      </template>
    </template>
  </div>
</template>
