<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import {
  ArrowRight, Plus, Trash2, Save, Loader2, Sparkles,
  RefreshCw, Filter, ChevronDown, AlertTriangle, Check,
} from 'lucide-vue-next'
import attributeMappingsApi from '@/api/attributeMappings'
import attributesApi from '@/api/attributes'
import hierarchiesApi from '@/api/hierarchies'

// ─── State ───────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const successMsg = ref('')

// Daten
const mappings = ref([])
const rules = ref([])
const attributes = ref([])
const hierarchies = ref([])

// Filter
const selectedHierarchyId = ref(null)
const searchQuery = ref('')

// Neues Mapping
const showAddRow = ref(false)
const newMapping = ref(createEmptyMapping())

// Neue Regel
const showAddRule = ref(false)
const newRule = ref(createEmptyRule())

// ─── Computed ────────────────────────────────────────────

const outputHierarchies = computed(() =>
  hierarchies.value.filter(h => h.hierarchy_type === 'output')
)

const sourceAttributes = computed(() =>
  attributes.value.filter(a => !a.source_system || a.source_system === null)
)

const targetAttributes = computed(() => {
  if (!selectedHierarchyId.value) return attributes.value.filter(a => a.source_system)
  const hierarchy = hierarchies.value.find(h => h.id === selectedHierarchyId.value)
  if (!hierarchy) return []
  return attributes.value.filter(a => a.source_system)
})

const filteredMappings = computed(() => {
  let result = mappings.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(m =>
      m.source_attribute?.technical_name?.toLowerCase().includes(q) ||
      m.source_attribute?.name_de?.toLowerCase().includes(q) ||
      m.target_attribute?.technical_name?.toLowerCase().includes(q) ||
      m.target_attribute?.name_de?.toLowerCase().includes(q)
    )
  }
  return result
})

// ─── Lifecycle ───────────────────────────────────────────

onMounted(async () => {
  await Promise.all([loadHierarchies(), loadAttributes()])
  loading.value = false
})

watch(selectedHierarchyId, () => {
  if (selectedHierarchyId.value) {
    loadMappings()
    loadRules()
  }
})

// ─── API Calls ───────────────────────────────────────────

async function loadHierarchies() {
  try {
    const res = await hierarchiesApi.list({ per_page: 100 })
    hierarchies.value = res.data.data || res.data
  } catch (e) {
    error.value = 'Hierarchien konnten nicht geladen werden'
  }
}

async function loadAttributes() {
  try {
    const res = await attributesApi.list({ per_page: 500, sort: 'technical_name', direction: 'asc' })
    attributes.value = res.data.data || res.data
  } catch (e) {
    error.value = 'Attribute konnten nicht geladen werden'
  }
}

async function loadMappings() {
  loading.value = true
  error.value = ''
  try {
    const res = await attributeMappingsApi.list({
      output_hierarchy_id: selectedHierarchyId.value,
      per_page: 200,
    })
    mappings.value = res.data.data || res.data
  } catch (e) {
    error.value = 'Mappings konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

async function loadRules() {
  try {
    const res = await attributeMappingsApi.listRules({
      output_hierarchy_id: selectedHierarchyId.value,
    })
    rules.value = res.data.data || res.data
  } catch (e) {
    console.error('Regeln konnten nicht geladen werden', e)
  }
}

// ─── CRUD ────────────────────────────────────────────────

async function addMapping() {
  if (!newMapping.value.source_attribute_id || !newMapping.value.target_attribute_id) return
  saving.value = true
  error.value = ''
  try {
    await attributeMappingsApi.create({
      ...newMapping.value,
      output_hierarchy_id: selectedHierarchyId.value,
    })
    newMapping.value = createEmptyMapping()
    showAddRow.value = false
    showSuccess('Mapping erstellt')
    await loadMappings()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Erstellen'
  } finally {
    saving.value = false
  }
}

async function deleteMapping(id) {
  if (!confirm('Mapping wirklich löschen?')) return
  try {
    await attributeMappingsApi.remove(id)
    showSuccess('Mapping gelöscht')
    await loadMappings()
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

async function addRule() {
  if (!newRule.value.name || !newRule.value.condition_attribute_id) return
  saving.value = true
  try {
    await attributeMappingsApi.createRule({
      ...newRule.value,
      output_hierarchy_id: selectedHierarchyId.value,
    })
    newRule.value = createEmptyRule()
    showAddRule.value = false
    showSuccess('Regel erstellt')
    await loadRules()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Erstellen'
  } finally {
    saving.value = false
  }
}

async function deleteRule(id) {
  if (!confirm('Regel wirklich löschen?')) return
  try {
    await attributeMappingsApi.removeRule(id)
    showSuccess('Regel gelöscht')
    await loadRules()
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

// ─── Helpers ─────────────────────────────────────────────

function createEmptyMapping() {
  return {
    source_attribute_id: null,
    target_attribute_id: null,
    transform_type: 'direct',
    transform_config: null,
  }
}

function createEmptyRule() {
  return {
    name: '',
    condition_attribute_id: null,
    condition_operator: '=',
    condition_value: '',
    actions: [{ target_attribute_id: null, value: '', value_type: 'static' }],
    is_active: true,
  }
}

function addRuleAction() {
  newRule.value.actions.push({ target_attribute_id: null, value: '', value_type: 'static' })
}

function removeRuleAction(index) {
  newRule.value.actions.splice(index, 1)
}

function getAttributeName(id) {
  const attr = attributes.value.find(a => a.id === id)
  return attr ? `${attr.name_de} (${attr.technical_name})` : id
}

function showSuccess(msg) {
  successMsg.value = msg
  setTimeout(() => { successMsg.value = '' }, 3000)
}

const transformTypes = [
  { value: 'direct', label: 'Direkt (1:1)' },
  { value: 'unit_convert', label: 'Einheiten-Umrechnung' },
  { value: 'value_map', label: 'Wert-Zuordnung' },
]

const operators = [
  { value: '=', label: '=' },
  { value: '!=', label: '≠' },
  { value: '>', label: '>' },
  { value: '<', label: '<' },
  { value: 'in', label: 'in' },
  { value: 'contains', label: 'enthält' },
  { value: 'is_empty', label: 'ist leer' },
  { value: 'is_not_empty', label: 'ist nicht leer' },
]
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">Attribut-Mapping</h1>
        <p class="text-base-content/60 mt-1">Quell-Attribute auf Klassifikations-Attribute zuordnen</p>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="alert alert-error mb-4">
      <AlertTriangle class="w-5 h-5" />
      <span>{{ error }}</span>
      <button class="btn btn-ghost btn-sm" @click="error = ''">×</button>
    </div>
    <div v-if="successMsg" class="alert alert-success mb-4">
      <Check class="w-5 h-5" />
      <span>{{ successMsg }}</span>
    </div>

    <!-- Hierarchie-Auswahl -->
    <div class="card bg-base-200 mb-6">
      <div class="card-body py-4">
        <div class="flex items-center gap-4">
          <label class="font-semibold whitespace-nowrap">Ziel-Klassifikation:</label>
          <select
            v-model="selectedHierarchyId"
            class="select select-bordered flex-1"
          >
            <option :value="null" disabled>Bitte Klassifikation wählen...</option>
            <option v-for="h in outputHierarchies" :key="h.id" :value="h.id">
              {{ h.name_de || h.technical_name }}
            </option>
          </select>
          <button
            v-if="selectedHierarchyId"
            class="btn btn-ghost btn-sm"
            @click="loadMappings(); loadRules()"
            title="Aktualisieren"
          >
            <RefreshCw class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Kein Hierarchie gewählt -->
    <div v-if="!selectedHierarchyId" class="text-center py-20 text-base-content/40">
      <Filter class="w-12 h-12 mx-auto mb-3 opacity-30" />
      <p>Wähle eine Ziel-Klassifikation, um die Mappings zu bearbeiten.</p>
    </div>

    <!-- Mapping-Tabelle -->
    <template v-else>
      <div class="card bg-base-100 shadow mb-6">
        <div class="card-body p-0">
          <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
            <h2 class="font-semibold">Attribut-Zuordnungen ({{ filteredMappings.length }})</h2>
            <div class="flex gap-2">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Suchen..."
                class="input input-bordered input-sm w-48"
              />
              <button class="btn btn-primary btn-sm" @click="showAddRow = true">
                <Plus class="w-4 h-4" /> Mapping
              </button>
            </div>
          </div>

          <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-6 h-6 animate-spin" />
          </div>

          <table v-else class="table table-sm">
            <thead>
              <tr>
                <th>Quell-Attribut</th>
                <th class="w-10 text-center">→</th>
                <th>Ziel-Attribut</th>
                <th>Transform</th>
                <th v-if="mappings.some(m => m.ai_suggested)">KI</th>
                <th class="w-16"></th>
              </tr>
            </thead>
            <tbody>
              <!-- Neue Zeile -->
              <tr v-if="showAddRow" class="bg-primary/5">
                <td>
                  <select v-model="newMapping.source_attribute_id" class="select select-bordered select-sm w-full">
                    <option :value="null" disabled>Quell-Attribut...</option>
                    <option v-for="a in sourceAttributes" :key="a.id" :value="a.id">
                      {{ a.name_de }} ({{ a.technical_name }})
                    </option>
                  </select>
                </td>
                <td class="text-center text-base-content/30">
                  <ArrowRight class="w-4 h-4 mx-auto" />
                </td>
                <td>
                  <select v-model="newMapping.target_attribute_id" class="select select-bordered select-sm w-full">
                    <option :value="null" disabled>Ziel-Attribut...</option>
                    <option v-for="a in targetAttributes" :key="a.id" :value="a.id">
                      {{ a.name_de }} ({{ a.technical_name }})
                    </option>
                  </select>
                </td>
                <td>
                  <select v-model="newMapping.transform_type" class="select select-bordered select-sm">
                    <option v-for="t in transformTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                  </select>
                </td>
                <td v-if="mappings.some(m => m.ai_suggested)"></td>
                <td>
                  <div class="flex gap-1">
                    <button class="btn btn-success btn-xs" @click="addMapping" :disabled="saving">
                      <Save class="w-3 h-3" />
                    </button>
                    <button class="btn btn-ghost btn-xs" @click="showAddRow = false">×</button>
                  </div>
                </td>
              </tr>

              <!-- Bestehende Mappings -->
              <tr v-for="m in filteredMappings" :key="m.id">
                <td>
                  <div>
                    <span class="font-medium">{{ m.source_attribute?.name_de }}</span>
                    <span class="text-xs text-base-content/50 ml-1">{{ m.source_attribute?.technical_name }}</span>
                  </div>
                  <span class="badge badge-xs badge-ghost">{{ m.source_attribute?.data_type }}</span>
                </td>
                <td class="text-center text-base-content/30">
                  <ArrowRight class="w-4 h-4 mx-auto" />
                </td>
                <td>
                  <div>
                    <span class="font-medium">{{ m.target_attribute?.name_de }}</span>
                    <span class="text-xs text-base-content/50 ml-1">{{ m.target_attribute?.technical_name }}</span>
                  </div>
                  <span class="badge badge-xs badge-outline">{{ m.target_attribute?.source_system }}</span>
                </td>
                <td>
                  <span class="badge badge-sm">{{ transformTypes.find(t => t.value === m.transform_type)?.label || m.transform_type }}</span>
                </td>
                <td v-if="mappings.some(mm => mm.ai_suggested)">
                  <div v-if="m.ai_suggested" class="flex items-center gap-1" :title="`Konfidenz: ${(m.ai_confidence * 100).toFixed(0)}%`">
                    <Sparkles class="w-3 h-3 text-warning" />
                    <span class="text-xs">{{ (m.ai_confidence * 100).toFixed(0) }}%</span>
                  </div>
                </td>
                <td>
                  <button class="btn btn-ghost btn-xs text-error" @click="deleteMapping(m.id)">
                    <Trash2 class="w-3 h-3" />
                  </button>
                </td>
              </tr>

              <tr v-if="!loading && filteredMappings.length === 0 && !showAddRow">
                <td :colspan="5" class="text-center text-base-content/40 py-8">
                  Noch keine Mappings vorhanden. Klicke auf "+ Mapping" um zu beginnen.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Bedingte Regeln -->
      <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
          <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
            <h2 class="font-semibold">Bedingte Regeln ({{ rules.length }})</h2>
            <button class="btn btn-primary btn-sm" @click="showAddRule = true">
              <Plus class="w-4 h-4" /> Regel
            </button>
          </div>

          <!-- Regel-Formular -->
          <div v-if="showAddRule" class="p-4 bg-primary/5 border-b border-base-300">
            <div class="grid grid-cols-2 gap-3 mb-3">
              <input v-model="newRule.name" type="text" placeholder="Regelname" class="input input-bordered input-sm" />
              <div class="flex gap-2">
                <select v-model="newRule.condition_attribute_id" class="select select-bordered select-sm flex-1">
                  <option :value="null" disabled>Bedingung: Attribut...</option>
                  <option v-for="a in attributes" :key="a.id" :value="a.id">{{ a.name_de }}</option>
                </select>
                <select v-model="newRule.condition_operator" class="select select-bordered select-sm w-24">
                  <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                </select>
                <input v-model="newRule.condition_value" type="text" placeholder="Wert" class="input input-bordered input-sm w-32" />
              </div>
            </div>

            <div class="text-sm font-semibold mb-2">Dann setze:</div>
            <div v-for="(action, i) in newRule.actions" :key="i" class="flex gap-2 mb-2">
              <select v-model="action.target_attribute_id" class="select select-bordered select-sm flex-1">
                <option :value="null" disabled>Ziel-Attribut...</option>
                <option v-for="a in targetAttributes" :key="a.id" :value="a.id">{{ a.name_de }}</option>
              </select>
              <span class="self-center">=</span>
              <input v-model="action.value" type="text" placeholder="Wert" class="input input-bordered input-sm w-40" />
              <button v-if="newRule.actions.length > 1" class="btn btn-ghost btn-xs" @click="removeRuleAction(i)">×</button>
            </div>

            <div class="flex justify-between mt-3">
              <button class="btn btn-ghost btn-xs" @click="addRuleAction">+ Aktion</button>
              <div class="flex gap-2">
                <button class="btn btn-ghost btn-sm" @click="showAddRule = false">Abbrechen</button>
                <button class="btn btn-success btn-sm" @click="addRule" :disabled="saving">
                  <Save class="w-4 h-4" /> Speichern
                </button>
              </div>
            </div>
          </div>

          <!-- Bestehende Regeln -->
          <div v-if="rules.length === 0 && !showAddRule" class="text-center text-base-content/40 py-8">
            Noch keine bedingten Regeln vorhanden.
          </div>
          <div v-for="rule in rules" :key="rule.id" class="px-4 py-3 border-b border-base-200 last:border-b-0">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="badge badge-sm" :class="rule.is_active ? 'badge-success' : 'badge-ghost'">
                  {{ rule.is_active ? 'Aktiv' : 'Inaktiv' }}
                </span>
                <span class="font-medium">{{ rule.name }}</span>
              </div>
              <button class="btn btn-ghost btn-xs text-error" @click="deleteRule(rule.id)">
                <Trash2 class="w-3 h-3" />
              </button>
            </div>
            <div class="text-sm text-base-content/60 mt-1">
              WENN <strong>{{ getAttributeName(rule.condition_attribute_id) }}</strong>
              {{ operators.find(o => o.value === rule.condition_operator)?.label || rule.condition_operator }}
              <strong>{{ rule.condition_value }}</strong>
              DANN
              <span v-for="(action, i) in rule.actions" :key="i">
                {{ i > 0 ? ' UND ' : '' }}
                <strong>{{ getAttributeName(action.target_attribute_id) }}</strong> = {{ action.value }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
