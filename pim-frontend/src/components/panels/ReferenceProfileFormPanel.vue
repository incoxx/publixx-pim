<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { referenceProfiles } from '@/api/referenceProfiles'
import attributesApi from '@/api/attributes'
import searchApi from '@/api/search'
import { Plus, Trash2, Wand2, X } from 'lucide-vue-next'

const props = defineProps({
  profile: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const isEdit = computed(() => !!props.profile)

const CHECKS = [
  { value: 'required', label: 'Pflichtfeld' },
  { value: 'not_empty', label: 'Nicht leer' },
  { value: 'min', label: 'Minimum' },
  { value: 'max', label: 'Maximum' },
  { value: 'between', label: 'Bereich (min–max)' },
  { value: 'in_list', label: 'Werteliste' },
  { value: 'max_length', label: 'Max. Länge' },
  { value: 'regex', label: 'Muster (Regex)' },
]
const SEVERITIES = [
  { value: 'error', label: 'Fehler' },
  { value: 'warning', label: 'Warnung' },
  { value: 'info', label: 'Info' },
]

const form = ref({
  technical_name: props.profile?.technical_name || '',
  name: props.profile?.name || '',
  description: props.profile?.description || '',
  parent_profile_id: props.profile?.parent_profile_id || '',
  is_abstract: props.profile?.is_abstract ?? false,
  is_active: props.profile?.is_active ?? true,
})

const rules = ref([])
const goldenProductIds = ref(props.profile?.golden_product_ids || [])
const effectiveRules = ref([])
const errors = ref({})
const saving = ref(false)

// Stammdaten für Dropdowns / Autocomplete
const allProfiles = ref([])
const allAttributes = ref([])

// Musterprodukt-Auswahl
const sampleQuery = ref('')
const sampleResults = ref([])
const sampleProducts = ref([])
const tolerance = ref(20)
const suggesting = ref(false)

const parentOptions = computed(() =>
  allProfiles.value.filter(p => p.id !== props.profile?.id)
)

function toRow(rule) {
  return {
    attribute: rule.attribute || '',
    check: rule.check || 'required',
    severity: rule.severity || 'error',
    unit: rule.unit || '',
    min: rule.min ?? '',
    max: rule.max ?? '',
    value: rule.value ?? '',
    valuesText: Array.isArray(rule.values) ? rule.values.join(', ') : '',
    pattern: rule.pattern || '',
  }
}

async function loadInitial() {
  try {
    const [{ data: profData }, { data: attrData }] = await Promise.all([
      referenceProfiles.list(),
      attributesApi.list({ perPage: 1000, sort: 'technical_name', order: 'asc' }),
    ])
    allProfiles.value = profData.data || profData
    allAttributes.value = (attrData.data || attrData).map(a => ({
      technical_name: a.technical_name,
      name_de: a.name_de,
    }))
  } catch { /* ignore */ }

  if (isEdit.value) {
    try {
      const { data } = await referenceProfiles.get(props.profile.id)
      const p = data.data || data
      rules.value = (p.rules || []).map(toRow)
      effectiveRules.value = data.effective_rules || []
      goldenProductIds.value = p.golden_product_ids || []
    } catch { /* ignore */ }
  } else {
    rules.value = []
  }
}

// ─── Musterprodukt-Picker ────────────────────────────────────
let searchTimer = null
function onSampleQuery() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(searchSamples, 250)
}
async function searchSamples() {
  const q = sampleQuery.value.trim()
  if (q.length < 2) { sampleResults.value = []; return }
  try {
    // Echte Produktsuche (GET /products wertet "search" nicht aus)
    const { data } = await searchApi.search({ search: q, per_page: 10 })
    sampleResults.value = (data.data || data).map(p => ({ id: p.id, sku: p.sku, name: p.name }))
  } catch { sampleResults.value = [] }
}
function addSample(p) {
  if (!sampleProducts.value.some(s => s.id === p.id)) sampleProducts.value.push(p)
  sampleQuery.value = ''
  sampleResults.value = []
}
function removeSample(id) {
  sampleProducts.value = sampleProducts.value.filter(s => s.id !== id)
}

async function deriveRules() {
  if (sampleProducts.value.length === 0) return
  suggesting.value = true
  try {
    const ids = sampleProducts.value.map(s => s.id)
    const { data } = await referenceProfiles.suggestRules(ids, Number(tolerance.value))
    // Vorschläge anhängen, ohne bestehende (attribute+check) zu duplizieren
    const existing = new Set(rules.value.map(r => `${r.attribute}|${r.check}`))
    for (const rule of (data.rules || [])) {
      const key = `${rule.attribute}|${rule.check}`
      if (!existing.has(key)) {
        rules.value.push(toRow(rule))
        existing.add(key)
      }
    }
    // Musterprodukte als KI-Kontext (golden samples) vormerken
    for (const id of (data.golden_product_ids || [])) {
      if (!goldenProductIds.value.includes(id)) goldenProductIds.value.push(id)
    }
  } finally {
    suggesting.value = false
  }
}

// ─── Regel-Editor ────────────────────────────────────────────
function addRule() {
  rules.value.push(toRow({ check: 'required', severity: 'error' }))
}
function removeRule(i) {
  rules.value.splice(i, 1)
}
function needs(check, field) {
  const map = {
    between: ['min', 'max'],
    min: ['value'],
    max: ['value'],
    max_length: ['value'],
    in_list: ['values'],
    regex: ['pattern'],
  }
  return (map[check] || []).includes(field)
}

function buildRulePayload() {
  return rules.value
    .filter(r => r.attribute && r.check)
    .map(r => {
      const out = { attribute: r.attribute, check: r.check, severity: r.severity }
      if (r.unit) out.unit = r.unit
      if (needs(r.check, 'min')) out.min = Number(r.min)
      if (needs(r.check, 'max')) out.max = Number(r.max)
      if (needs(r.check, 'value')) out.value = r.check === 'max_length' ? Number(r.value) : r.value
      if (needs(r.check, 'values')) {
        out.values = r.valuesText.split(',').map(s => s.trim()).filter(Boolean)
      }
      if (needs(r.check, 'pattern')) out.pattern = r.pattern
      return out
    })
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    ...form.value,
    parent_profile_id: form.value.parent_profile_id || null,
    rules: buildRulePayload(),
    golden_product_ids: goldenProductIds.value,
  }
  try {
    if (isEdit.value) {
      await referenceProfiles.update(props.profile.id, payload)
    } else {
      await referenceProfiles.create(payload)
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    saving.value = false
  }
}

onMounted(loadInitial)
</script>

<template>
  <div class="p-4 space-y-5">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Referenz-Profil bearbeiten' : 'Neues Referenz-Profil' }}
    </h3>

    <!-- Stammdaten -->
    <div class="space-y-3">
      <div>
        <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Name</label>
        <input v-model="form.name" class="pim-input w-full" placeholder="z.B. Akkubohrschrauber" />
        <p v-if="errors.name" class="text-xs text-[var(--color-error)] mt-1">{{ errors.name }}</p>
      </div>
      <div>
        <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Technischer Name</label>
        <input v-model="form.technical_name" :disabled="isEdit" class="pim-input w-full font-mono" placeholder="akkubohrschrauber" />
        <p v-if="errors.technical_name" class="text-xs text-[var(--color-error)] mt-1">{{ errors.technical_name }}</p>
      </div>
      <div>
        <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Beschreibung</label>
        <textarea v-model="form.description" rows="2" class="pim-input w-full"></textarea>
      </div>
      <div>
        <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Erbt von (Eltern-Profil)</label>
        <select v-model="form.parent_profile_id" class="pim-input w-full">
          <option value="">— kein Eltern-Profil —</option>
          <option v-for="p in parentOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="flex gap-6">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.is_abstract" class="pim-checkbox" />
          Abstrakt (nur Basis, nicht direkt zuweisbar)
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.is_active" class="pim-checkbox" />
          Aktiv
        </label>
      </div>
    </div>

    <!-- Aus Musterprodukt ableiten -->
    <div class="pim-card p-3 space-y-2">
      <div class="flex items-center gap-2 text-sm font-medium text-[var(--color-text-primary)]">
        <Wand2 class="w-4 h-4" /> Regeln aus Musterprodukt(en) ableiten
      </div>
      <div class="relative">
        <input v-model="sampleQuery" @input="onSampleQuery" class="pim-input w-full"
               placeholder="Produkt suchen (SKU oder Name)…" />
        <ul v-if="sampleResults.length" class="absolute z-10 left-0 right-0 mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded shadow max-h-48 overflow-auto">
          <li v-for="p in sampleResults" :key="p.id"
              class="px-3 py-1.5 text-sm hover:bg-[var(--color-surface-hover)] cursor-pointer"
              @click="addSample(p)">
            <span class="font-mono text-xs text-[var(--color-text-tertiary)]">{{ p.sku }}</span> — {{ p.name }}
          </li>
        </ul>
      </div>
      <div v-if="sampleProducts.length" class="flex flex-wrap gap-2">
        <span v-for="p in sampleProducts" :key="p.id"
              class="inline-flex items-center gap-1 text-xs bg-[var(--color-surface-hover)] rounded px-2 py-1">
          {{ p.sku || p.name }}
          <X class="w-3 h-3 cursor-pointer" @click="removeSample(p.id)" />
        </span>
      </div>
      <div class="flex items-center gap-3">
        <label class="text-xs text-[var(--color-text-secondary)]">Toleranz ±%</label>
        <input v-model="tolerance" type="number" min="0" max="100" class="pim-input w-20" />
        <button class="pim-btn pim-btn-secondary" :disabled="!sampleProducts.length || suggesting" @click="deriveRules">
          {{ suggesting ? 'Leite ab…' : 'Regeln ableiten' }}
        </button>
      </div>
    </div>

    <!-- Regel-Editor -->
    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-[var(--color-text-primary)]">Prüfregeln ({{ rules.length }})</span>
        <button class="pim-btn pim-btn-ghost" @click="addRule">
          <Plus class="w-4 h-4" /> Regel
        </button>
      </div>

      <div v-for="(r, i) in rules" :key="i"
           class="grid grid-cols-12 gap-2 items-center border border-[var(--color-border)] rounded p-2">
        <input v-model="r.attribute" list="ref-attr-list" class="pim-input col-span-3 font-mono text-xs"
               placeholder="attribut" />
        <select v-model="r.check" class="pim-input col-span-2 text-xs">
          <option v-for="c in CHECKS" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>

        <!-- Parameter je nach Check -->
        <template v-if="needs(r.check, 'min') || needs(r.check, 'max')">
          <input v-if="needs(r.check,'min')" v-model="r.min" type="number" class="pim-input col-span-2 text-xs" placeholder="min" />
          <input v-if="needs(r.check,'max')" v-model="r.max" type="number" class="pim-input col-span-2 text-xs" placeholder="max" />
        </template>
        <input v-else-if="needs(r.check,'value')" v-model="r.value" class="pim-input col-span-4 text-xs" placeholder="Wert" />
        <input v-else-if="needs(r.check,'values')" v-model="r.valuesText" class="pim-input col-span-4 text-xs" placeholder="wert1, wert2, …" />
        <input v-else-if="needs(r.check,'pattern')" v-model="r.pattern" class="pim-input col-span-4 text-xs font-mono" placeholder="/^\\d+$/" />
        <span v-else class="col-span-4"></span>

        <select v-model="r.severity" class="pim-input col-span-2 text-xs">
          <option v-for="s in SEVERITIES" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <button class="col-span-1 text-[var(--color-error)] flex justify-center" @click="removeRule(i)">
          <Trash2 class="w-4 h-4" />
        </button>
      </div>

      <datalist id="ref-attr-list">
        <option v-for="a in allAttributes" :key="a.technical_name" :value="a.technical_name">{{ a.name_de }}</option>
      </datalist>
    </div>

    <!-- Vererbte Regeln (nur Anzeige) -->
    <div v-if="effectiveRules.length" class="text-xs text-[var(--color-text-tertiary)]">
      <span class="font-medium">Effektiv (inkl. Vererbung):</span>
      {{ effectiveRules.length }} Regeln aktiv.
    </div>

    <!-- Aktionen -->
    <div class="flex justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
      <button class="pim-btn pim-btn-ghost" @click="authStore.closePanel()">Abbrechen</button>
      <button class="pim-btn pim-btn-primary" :disabled="saving" @click="save">
        {{ saving ? 'Speichert…' : 'Speichern' }}
      </button>
    </div>
  </div>
</template>
