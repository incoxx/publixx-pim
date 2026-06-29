<script setup>
import { ref, onMounted } from 'vue'
import contentApi from '@/api/content'
import { Plus, Trash2, GripVertical, Save, Boxes } from 'lucide-vue-next'

// Reaktivitätssicherer Deep-Clone (structuredClone wirft auf Vue-Proxies).
function clone(v) { return v == null ? v : JSON.parse(JSON.stringify(v)) }

const items = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const errors = ref({})

const CATEGORIES = ['', 'text', 'media', 'commerce', 'layout']
const FIELD_TYPES = [
  'String', 'RichText', 'Textarea', 'Number', 'Flag', 'Selection', 'Date', 'Link',
  'Media', 'product_ref', 'hierarchy_node_ref', 'product_widget_ref', 'pql',
]
const REF_TYPES = ['Media', 'product_ref', 'hierarchy_node_ref']

async function load() {
  loading.value = true
  try {
    const { data } = await contentApi.listSectionTypes({ perPage: 200 })
    items.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

function selectItem(s) {
  errors.value = {}
  current.value = {
    ...clone(s),
    schema: { fields: clone(s.schema?.fields || []) },
  }
}

function newItem() {
  errors.value = {}
  current.value = {
    id: null, technical_name: '', name_de: '', name_en: '', icon: '', category: 'text',
    is_repeatable: false, is_nestable: false, preview_component: '', is_active: true, is_system: false,
    schema: { fields: [] },
  }
}

function addField() {
  current.value.schema.fields.push({ key: '', label: '', type: 'String', translatable: false, required: false })
}
function removeField(idx) { current.value.schema.fields.splice(idx, 1) }

const dragIdx = ref(null)
function onDragStart(i) { dragIdx.value = i }
function onDrop(i) {
  const from = dragIdx.value; dragIdx.value = null
  if (from === null || from === i) return
  const f = current.value.schema.fields
  const [m] = f.splice(from, 1); f.splice(i, 0, m)
}

// options als kommagetrennter String <-> Array
function optionsStr(field) { return (field.options || []).join(', ') }
function setOptions(field, str) {
  field.options = str.split(',').map((x) => x.trim()).filter(Boolean)
}

async function save() {
  errors.value = {}
  // Unvollständige Feldzeilen (ohne key) verwerfen; feldlose Typen sind erlaubt.
  const cleanFields = (current.value.schema.fields || []).filter((f) => f.key && f.key.trim())
  current.value.schema.fields = cleanFields

  saving.value = true
  const payload = {
    technical_name: current.value.technical_name,
    name_de: current.value.name_de,
    name_en: current.value.name_en || null,
    icon: current.value.icon || null,
    category: current.value.category || null,
    is_repeatable: current.value.is_repeatable,
    is_nestable: current.value.is_nestable,
    preview_component: current.value.preview_component || null,
    is_active: current.value.is_active,
    schema: { fields: cleanFields },
  }
  try {
    if (current.value.id) {
      const { data } = await contentApi.updateSectionType(current.value.id, payload)
      Object.assign(current.value, data.data ?? data)
    } else {
      const { data } = await contentApi.createSectionType(payload)
      current.value = { ...current.value, ...(data.data ?? data) }
    }
    await load()
  } catch (e) {
    if (e.response?.status === 422) {
      const se = e.response.data.errors || {}
      for (const [k, v] of Object.entries(se)) errors.value[k] = Array.isArray(v) ? v[0] : v
      if (Object.keys(se).some((k) => k.startsWith('schema.fields'))) {
        errors.value._general = 'Bitte für jedes Feld einen technischen Namen (key) und einen Typ angeben.'
      } else if (!Object.keys(se).length && e.response.data.message) {
        errors.value._general = e.response.data.message
      }
    } else {
      errors.value._general = e.response?.data?.message || 'Speichern fehlgeschlagen.'
    }
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!current.value?.id || current.value.is_system) return
  if (!confirm(`Sektionstyp "${current.value.name_de}" löschen?`)) return
  try {
    await contentApi.deleteSectionType(current.value.id)
    current.value = null
    await load()
  } catch (e) {
    errors.value._general = e.response?.status === 409
      ? 'Sektionstyp wird noch von Content-Sektionen verwendet.'
      : (e.response?.data?.message || 'Löschen fehlgeschlagen.')
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-4" data-testid="section-types">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <Boxes class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Sektionstypen</h2>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="newItem"><Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neuer Sektionstyp</button>
    </div>
    <p class="text-[11px] text-[var(--color-text-tertiary)] -mt-2">Typisierte Bausteine. System-Typen sind erweiterbar, aber nicht löschbar.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Liste -->
      <div class="md:col-span-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-1 self-start max-h-[70vh] overflow-y-auto">
        <div v-if="loading" class="space-y-1 p-1"><div v-for="i in 8" :key="i" class="pim-skeleton h-9 rounded" /></div>
        <button v-for="s in items" :key="s.id"
          class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-left transition-colors"
          :class="current?.id === s.id ? 'bg-[var(--color-bg)]' : 'hover:bg-[var(--color-bg)]'"
          @click="selectItem(s)">
          <span class="min-w-0">
            <span class="block text-xs font-medium truncate">{{ s.name_de }}</span>
            <span class="block text-[10px] text-[var(--color-text-tertiary)] font-mono truncate">{{ s.technical_name }} · {{ s.category }}</span>
          </span>
          <span v-if="s.is_system" class="text-[9px] px-1.5 py-0.5 rounded-full bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">System</span>
        </button>
      </div>

      <!-- Editor -->
      <div v-if="current" class="md:col-span-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
        <p v-if="errors._general" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ errors._general }}</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name *</label>
            <input v-model="current.technical_name" :disabled="!!current.id" class="pim-input text-xs w-full" placeholder="z. B. hero-banner" />
            <p v-if="errors.technical_name" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.technical_name }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (DE) *</label>
            <input v-model="current.name_de" class="pim-input text-xs w-full" />
            <p v-if="errors.name_de" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.name_de }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Kategorie</label>
            <select v-model="current.category" class="pim-input text-xs w-full">
              <option v-for="c in CATEGORIES" :key="c" :value="c">{{ c || '—' }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Icon (Lucide)</label>
            <input v-model="current.icon" class="pim-input text-xs w-full" placeholder="z. B. Image" />
          </div>
        </div>

        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]"><input type="checkbox" v-model="current.is_repeatable" class="pim-checkbox" /> Wiederholbar</label>
          <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]"><input type="checkbox" v-model="current.is_nestable" class="pim-checkbox" /> Verschachtelbar (Kind-Sektionen)</label>
          <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]"><input type="checkbox" v-model="current.is_active" class="pim-checkbox" /> Aktiv</label>
        </div>

        <!-- Feld-Schema -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)]">Felder</label>
            <button class="pim-btn pim-btn-secondary text-[11px]" @click="addField"><Plus class="w-3 h-3" :stroke-width="2" /> Feld</button>
          </div>
          <p v-if="errors['schema.fields']" class="mb-1 text-[11px] text-[var(--color-error)]">{{ errors['schema.fields'] }}</p>
          <div class="space-y-2">
            <div v-for="(f, idx) in current.schema.fields" :key="idx"
              class="rounded-lg border border-[var(--color-border)] p-2 space-y-1.5"
              draggable="true" @dragstart="onDragStart(idx)" @dragover.prevent @drop="onDrop(idx)">
              <div class="grid items-center gap-1.5" style="grid-template-columns: 1.25rem 7.5rem minmax(0,1fr) 8.5rem 1.75rem;">
                <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] cursor-grab" :stroke-width="2" />
                <input v-model="f.key" class="pim-input text-xs min-w-0 w-full" placeholder="key" />
                <input v-model="f.label" class="pim-input text-xs min-w-0 w-full" placeholder="Label" />
                <select v-model="f.type" class="pim-input text-xs min-w-0">
                  <option v-for="t in FIELD_TYPES" :key="t" :value="t">{{ t }}</option>
                </select>
                <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeField(idx)"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /></button>
              </div>
              <div class="flex flex-wrap items-center gap-3 pl-5">
                <label class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-tertiary)]"><input type="checkbox" v-model="f.translatable" class="pim-checkbox" /> übersetzbar</label>
                <label class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-tertiary)]"><input type="checkbox" v-model="f.required" class="pim-checkbox" /> Pflicht</label>
                <label v-if="REF_TYPES.includes(f.type)" class="flex items-center gap-1.5 text-[11px] text-[var(--color-text-tertiary)]"><input type="checkbox" v-model="f.multiple" class="pim-checkbox" /> mehrfach</label>
                <input v-if="f.type === 'Selection'" :value="optionsStr(f)" @input="setOptions(f, $event.target.value)"
                  class="pim-input text-[11px] flex-1 min-w-40" placeholder="Optionen, kommagetrennt" />
              </div>
            </div>
            <p v-if="!current.schema.fields.length" class="text-xs text-[var(--color-text-tertiary)]">Noch keine Felder.</p>
          </div>
        </div>

        <div class="flex items-center gap-2 pt-3 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="save"><Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ saving ? 'Speichern…' : 'Speichern' }}</button>
          <button v-if="current.id && !current.is_system" class="pim-btn pim-btn-secondary text-xs" @click="remove"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> Löschen</button>
          <span v-if="current.is_system" class="text-[11px] text-[var(--color-text-tertiary)]">System-Typ — nicht löschbar</span>
        </div>
      </div>

      <div v-else class="md:col-span-2 flex items-center justify-center text-xs text-[var(--color-text-tertiary)] border border-dashed border-[var(--color-border)] rounded-lg p-8">
        Wähle links einen Sektionstyp oder lege einen neuen an.
      </div>
    </div>
  </div>
</template>
