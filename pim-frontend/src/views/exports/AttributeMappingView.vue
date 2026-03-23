<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowRight, Plus, Trash2, Save, Loader2, Sparkles,
  RefreshCw, Filter, ChevronDown, AlertTriangle, Check,
  Download, Upload,
} from 'lucide-vue-next'
import attributeMappingsApi from '@/api/attributeMappings'
import hierarchiesApi from '@/api/hierarchies'

const route = useRoute()
const router = useRouter()

// ─── State ───────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const syncing = ref(false)
const exporting = ref(false)
const importing = ref(false)
const error = ref('')
const successMsg = ref('')

// Daten
const mappings = ref([])
const rules = ref([])
const hierarchies = ref([])
const sourceHierarchyAttributes = ref([])
const targetHierarchyAttributes = ref([])

// Filter
const sourceHierarchyId = ref(null)
const targetHierarchyId = ref(null)
const searchQuery = ref('')

// Neues Mapping
const showAddRow = ref(false)
const newMapping = ref(createEmptyMapping())

// Neue Regel
const showAddRule = ref(false)
const newRule = ref(createEmptyRule())

// ─── Computed ────────────────────────────────────────────

const hierarchyPairSelected = computed(() =>
  sourceHierarchyId.value && targetHierarchyId.value
)

const sourceAttributes = computed(() => sourceHierarchyAttributes.value)

const targetAttributes = computed(() => targetHierarchyAttributes.value)

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
  await loadHierarchies()

  // Restore hierarchy selection from query params
  if (route.query.source) sourceHierarchyId.value = route.query.source
  if (route.query.target) targetHierarchyId.value = route.query.target

  loading.value = false
})

watch([sourceHierarchyId, targetHierarchyId], () => {
  // Persist selection to URL query params
  router.replace({
    query: {
      ...(sourceHierarchyId.value ? { source: sourceHierarchyId.value } : {}),
      ...(targetHierarchyId.value ? { target: targetHierarchyId.value } : {}),
    },
  })

  if (hierarchyPairSelected.value) {
    loadMappings()
    loadRules()
  }
})

// Load hierarchy-scoped attributes when source/target changes
watch(sourceHierarchyId, async (id) => {
  sourceHierarchyAttributes.value = []
  if (id) {
    try {
      const res = await hierarchiesApi.allNodeAttributes(id)
      sourceHierarchyAttributes.value = res.data.data || res.data
    } catch (e) {
      console.error('Quell-Attribute konnten nicht geladen werden', e)
    }
  }
})

watch(targetHierarchyId, async (id) => {
  targetHierarchyAttributes.value = []
  if (id) {
    try {
      const res = await hierarchiesApi.allNodeAttributes(id)
      targetHierarchyAttributes.value = res.data.data || res.data
    } catch (e) {
      console.error('Ziel-Attribute konnten nicht geladen werden', e)
    }
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

async function loadMappings() {
  loading.value = true
  error.value = ''
  try {
    const res = await attributeMappingsApi.list({
      source_hierarchy_id: sourceHierarchyId.value,
      target_hierarchy_id: targetHierarchyId.value,
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
      source_hierarchy_id: sourceHierarchyId.value,
      target_hierarchy_id: targetHierarchyId.value,
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
      source_hierarchy_id: sourceHierarchyId.value,
      target_hierarchy_id: targetHierarchyId.value,
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
      source_hierarchy_id: sourceHierarchyId.value,
      target_hierarchy_id: targetHierarchyId.value,
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

// ─── Sync ────────────────────────────────────────────────

async function syncAll() {
  if (!confirm('Alle aktiven Produkte für dieses Mapping synchronisieren?')) return
  syncing.value = true
  error.value = ''
  try {
    const res = await attributeMappingsApi.syncBatch({
      source_hierarchy_id: sourceHierarchyId.value,
      target_hierarchy_id: targetHierarchyId.value,
    })
    showSuccess(`Sync gestartet: ${res.data.product_count} Produkte`)
  } catch (e) {
    error.value = e.response?.data?.message || 'Sync fehlgeschlagen'
  } finally {
    syncing.value = false
  }
}

// ─── Excel Export / Import ───────────────────────────────

async function exportExcel() {
  exporting.value = true
  error.value = ''
  try {
    const res = await attributeMappingsApi.exportExcel({
      source_hierarchy_id: sourceHierarchyId.value,
      target_hierarchy_id: targetHierarchyId.value,
    })
    // Blob-Download auslösen
    const url = URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `attribut-mappings-${new Date().toISOString().slice(0, 10)}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
    showSuccess('Excel exportiert')
  } catch (e) {
    error.value = e.response?.data?.message || 'Export fehlgeschlagen'
  } finally {
    exporting.value = false
  }
}

const importFileInput = ref(null)

function triggerImport() {
  importFileInput.value?.click()
}

async function handleImportFile(event) {
  const file = event.target.files?.[0]
  if (!file) return
  importing.value = true
  error.value = ''
  try {
    const res = await attributeMappingsApi.importExcel(
      file,
      sourceHierarchyId.value,
      targetHierarchyId.value,
    )
    const s = res.data.stats || {}
    const parts = []
    if (s.mappings_created) parts.push(`${s.mappings_created} Mappings erstellt`)
    if (s.mappings_updated) parts.push(`${s.mappings_updated} Mappings aktualisiert`)
    if (s.rules_created) parts.push(`${s.rules_created} Regeln erstellt`)
    if (s.rules_updated) parts.push(`${s.rules_updated} Regeln aktualisiert`)
    showSuccess(parts.length ? parts.join(', ') : 'Import abgeschlossen (keine Änderungen)')
    if (s.errors?.length) {
      error.value = s.errors.join('\n')
    }
    await loadMappings()
    await loadRules()
  } catch (e) {
    error.value = e.response?.data?.message || 'Import fehlgeschlagen'
  } finally {
    importing.value = false
    // File-Input zurücksetzen
    if (importFileInput.value) importFileInput.value.value = ''
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
  const attr = sourceHierarchyAttributes.value.find(a => a.id === id)
    || targetHierarchyAttributes.value.find(a => a.id === id)
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
        <p class="text-[var(--color-text-tertiary)] mt-1">Quell-Attribute auf Klassifikations-Attribute zuordnen</p>
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

    <!-- Hierarchie-Auswahl: Quelle → Ziel -->
    <div class="card bg-[var(--color-surface-nav)] border border-[var(--color-border)] mb-6">
      <div class="card-body py-4">
        <div class="flex items-center gap-3">
          <div class="flex-1">
            <label class="text-xs font-semibold text-[var(--color-text-tertiary)] mb-1 block">Quell-Schema</label>
            <select v-model="sourceHierarchyId" class="select select-bordered w-full">
              <option :value="null" disabled>Quell-Hierarchie...</option>
              <option v-for="h in hierarchies" :key="h.id" :value="h.id">
                {{ h.name_de || h.technical_name }} ({{ h.hierarchy_type }})
              </option>
            </select>
          </div>
          <ArrowRight class="w-5 h-5 mt-5 text-[var(--color-border-strong)] shrink-0" />
          <div class="flex-1">
            <label class="text-xs font-semibold text-[var(--color-text-tertiary)] mb-1 block">Ziel-Schema</label>
            <select v-model="targetHierarchyId" class="select select-bordered w-full">
              <option :value="null" disabled>Ziel-Hierarchie...</option>
              <option v-for="h in hierarchies" :key="h.id" :value="h.id">
                {{ h.name_de || h.technical_name }} ({{ h.hierarchy_type }})
              </option>
            </select>
          </div>
          <button
            v-if="hierarchyPairSelected"
            class="btn btn-ghost btn-sm mt-5"
            @click="loadMappings(); loadRules()"
            title="Aktualisieren"
          >
            <RefreshCw class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Kein Hierarchie-Paar gewählt -->
    <div v-if="!hierarchyPairSelected" class="text-center py-20 text-[var(--color-text-tertiary)]">
      <Filter class="w-12 h-12 mx-auto mb-3 opacity-30" />
      <p>Wähle Quell- und Ziel-Schema, um die Attribut-Zuordnungen zu bearbeiten.</p>
    </div>

    <!-- Mapping-Tabelle -->
    <template v-else>
      <div class="card bg-[var(--color-surface)] border border-[var(--color-border)] shadow-sm mb-6">
        <div class="card-body p-0">
          <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
            <h2 class="font-semibold">Attribut-Zuordnungen ({{ filteredMappings.length }})</h2>
            <div class="flex gap-2">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Suchen..."
                class="input input-bordered input-sm w-48"
              />
              <button class="btn btn-outline btn-sm" @click="exportExcel" :disabled="exporting" title="Als Excel exportieren">
                <Download class="w-4 h-4" :class="{ 'animate-pulse': exporting }" /> Export
              </button>
              <button class="btn btn-outline btn-sm" @click="triggerImport" :disabled="importing" title="Aus Excel importieren">
                <Upload class="w-4 h-4" :class="{ 'animate-pulse': importing }" /> Import
              </button>
              <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="handleImportFile" />
              <button class="btn btn-outline btn-sm" @click="syncAll" :disabled="syncing">
                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': syncing }" /> Alle synchronisieren
              </button>
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
              <tr v-if="showAddRow" class="bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]">
                <td>
                  <select v-model="newMapping.source_attribute_id" class="select select-bordered select-sm w-full">
                    <option :value="null" disabled>Quell-Attribut...</option>
                    <option v-for="a in sourceAttributes" :key="a.id" :value="a.id">
                      {{ a.name_de }} ({{ a.technical_name }})
                    </option>
                  </select>
                </td>
                <td class="text-center text-[var(--color-border-strong)]">
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
                    <span class="text-xs text-[var(--color-text-tertiary)] ml-1">{{ m.source_attribute?.technical_name }}</span>
                  </div>
                  <span class="badge badge-xs badge-ghost">{{ m.source_attribute?.data_type }}</span>
                </td>
                <td class="text-center text-[var(--color-border-strong)]">
                  <ArrowRight class="w-4 h-4 mx-auto" />
                </td>
                <td>
                  <div>
                    <span class="font-medium">{{ m.target_attribute?.name_de }}</span>
                    <span class="text-xs text-[var(--color-text-tertiary)] ml-1">{{ m.target_attribute?.technical_name }}</span>
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
                <td :colspan="5" class="text-center text-[var(--color-text-tertiary)] py-8">
                  Noch keine Mappings vorhanden. Klicke auf "+ Mapping" um zu beginnen.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Bedingte Regeln -->
      <div class="card bg-[var(--color-surface)] border border-[var(--color-border)] shadow-sm">
        <div class="card-body p-0">
          <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
            <h2 class="font-semibold">Bedingte Regeln ({{ rules.length }})</h2>
            <button class="btn btn-primary btn-sm" @click="showAddRule = true">
              <Plus class="w-4 h-4" /> Regel
            </button>
          </div>

          <!-- Regel-Formular -->
          <div v-if="showAddRule" class="p-4 bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)] border-b border-[var(--color-border)]">
            <div class="grid grid-cols-2 gap-3 mb-3">
              <input v-model="newRule.name" type="text" placeholder="Regelname" class="input input-bordered input-sm" />
              <div class="flex gap-2">
                <select v-model="newRule.condition_attribute_id" class="select select-bordered select-sm flex-1">
                  <option :value="null" disabled>Bedingung: Attribut...</option>
                  <option v-for="a in sourceAttributes" :key="a.id" :value="a.id">{{ a.name_de }}</option>
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
          <div v-if="rules.length === 0 && !showAddRule" class="text-center text-[var(--color-text-tertiary)] py-8">
            Noch keine bedingten Regeln vorhanden.
          </div>
          <div v-for="rule in rules" :key="rule.id" class="px-4 py-3 border-b border-[var(--color-border)] last:border-b-0">
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
            <div class="text-sm text-[var(--color-text-secondary)] mt-1">
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
