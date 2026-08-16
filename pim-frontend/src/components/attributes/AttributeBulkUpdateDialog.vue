<script setup>
import { ref, computed, watch } from 'vue'
import { X } from 'lucide-vue-next'
import { useAttributeStore } from '@/stores/attributes'
import { attributeViews as attributeViewsApi } from '@/api/attributes'

const props = defineProps({
  open: { type: Boolean, default: false },
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'updated'])

const store = useAttributeStore()
const saving = ref(false)
const error = ref(null)

// Each field has: enabled (checkbox), value
const fields = ref({
  is_translatable: { enabled: false, value: true },
  is_multipliable: { enabled: false, value: false },
  is_searchable: { enabled: false, value: true },
  is_mandatory: { enabled: false, value: false },
  is_unique: { enabled: false, value: false },
  is_inheritable: { enabled: false, value: true },
  is_variant_attribute: { enabled: false, value: false },
  is_internal: { enabled: false, value: false },
  is_readonly: { enabled: false, value: false },
  is_hidden: { enabled: false, value: false },
  attribute_type_id: { enabled: false, value: '' },
  status: { enabled: false, value: 'active' },
})

// Attribute views state
const availableViews = ref([])
const viewsEnabled = ref(false)
const viewsMode = ref('add')
const selectedViewIds = ref([])

// Metadaten (Data Quality & Ownership) — gleiches Muster wie `fields`:
// { [technical_name]: { enabled, value } }
const metaFields = ref({})

function buildMetaFields() {
  const next = {}
  for (const d of store.metadataDefinitions) {
    next[d.technical_name] = {
      enabled: metaFields.value[d.technical_name]?.enabled ?? false,
      value: metaFields.value[d.technical_name]?.value ?? (d.value_type === 'multiselect' ? [] : ''),
    }
  }
  metaFields.value = next
}

// Direkt an die Definitionen gekoppelt (immediate), damit die Felder vor dem
// ersten Rendern stehen — der Dialog kann bereits offen gemountet werden und
// die Definitionen treffen asynchron ein.
watch(() => store.metadataDefinitions, buildMetaFields, { immediate: true })

/** Definition + zugehoeriges Formularfeld, nur vollstaendige Paare. */
const metaRows = computed(() =>
  store.metadataDefinitions
    .filter(d => metaFields.value[d.technical_name])
    .map(d => ({ definition: d, field: metaFields.value[d.technical_name] }))
)

/** Auswahloptionen im Format `Label::Wert` — gespeichert wird der Wert. */
function metaOptions(definition) {
  return (definition.options || []).map((raw) => {
    const parts = raw.split('::')
    const value = (parts[1] ?? parts[0]).trim()
    return { value, label: parts.length > 1 ? `${parts[0].trim()} (${value})` : value }
  })
}

function toggleMetaMultiValue(technicalName, optionValue) {
  const current = metaFields.value[technicalName].value
  const list = Array.isArray(current) ? [...current] : []
  const idx = list.indexOf(optionValue)
  if (idx === -1) list.push(optionValue)
  else list.splice(idx, 1)
  metaFields.value[technicalName].value = list
}

watch(() => props.open, async (isOpen) => {
  if (!isOpen) return

  if (!store.metadataDefinitions.length) {
    await store.fetchMetadataDefinitions()
  }

  if (availableViews.value.length === 0) {
    try {
      const { data } = await attributeViewsApi.list({ per_page: 200 })
      availableViews.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch attribute views', e)
    }
  }
})

const boolFields = [
  { key: 'is_translatable', label: 'Übersetzbar' },
  { key: 'is_multipliable', label: 'Multipliziert' },
  { key: 'is_searchable', label: 'Suchbar' },
  { key: 'is_mandatory', label: 'Pflichtfeld' },
  { key: 'is_unique', label: 'Einzigartig' },
  { key: 'is_inheritable', label: 'Vererbbar' },
  { key: 'is_variant_attribute', label: 'Varianten-Attribut' },
  { key: 'is_internal', label: 'Intern' },
  { key: 'is_readonly', label: 'Schreibgeschützt' },
  { key: 'is_hidden', label: 'Versteckt' },
]

const attributeTypeOptions = computed(() =>
  store.types.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))
)

const enabledMetaFields = computed(() =>
  Object.entries(metaFields.value).filter(([, f]) => f.enabled)
)

const hasEnabledFields = computed(() =>
  Object.values(fields.value).some(f => f.enabled) ||
  enabledMetaFields.value.length > 0 ||
  (viewsEnabled.value && selectedViewIds.value.length > 0)
)

/** Angehakte Metadaten, deren Wert leer ist — die loeschen das Metadatum. */
const clearingMetaLabels = computed(() =>
  enabledMetaFields.value
    .filter(([, f]) => f.value === '' || (Array.isArray(f.value) && f.value.length === 0))
    .map(([technicalName]) => {
      const d = store.metadataDefinitions.find(x => x.technical_name === technicalName)
      return d?.name_de || technicalName
    })
)

function resetFields() {
  for (const key in fields.value) {
    fields.value[key].enabled = false
    if (key === 'attribute_type_id') fields.value[key].value = ''
    else if (key === 'status') fields.value[key].value = 'active'
    else fields.value[key].value = false
  }
  metaFields.value = {}
  buildMetaFields()
  viewsEnabled.value = false
  viewsMode.value = 'add'
  selectedViewIds.value = []
  error.value = null
}

async function apply() {
  saving.value = true
  error.value = null
  try {
    const hasFieldUpdates = Object.values(fields.value).some(f => f.enabled)
    const hasMetaUpdates = enabledMetaFields.value.length > 0
    const hasViewUpdates = viewsEnabled.value && selectedViewIds.value.length > 0

    if (hasFieldUpdates || hasMetaUpdates) {
      const updateFields = {}
      for (const [key, field] of Object.entries(fields.value)) {
        if (field.enabled) {
          updateFields[key] = key === 'attribute_type_id'
            ? (field.value || null)
            : field.value
        }
      }

      // Angehakte Metadaten als Map technical_name => Wert. Ein leerer Wert
      // wird bewusst mitgeschickt — er loescht das Metadatum serverseitig.
      if (hasMetaUpdates) {
        updateFields.metadata = Object.fromEntries(
          enabledMetaFields.value.map(([technicalName, field]) => [technicalName, field.value])
        )
      }

      await store.bulkUpdate(props.selectedIds, updateFields)
    }

    if (hasViewUpdates) {
      await store.bulkAssignViews(props.selectedIds, viewsMode.value, selectedViewIds.value)
    }

    resetFields()
    emit('updated')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

function close() {
  resetFields()
  emit('close')
}
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="close" />
        <div class="relative bg-[var(--color-surface)] rounded-xl shadow-2xl border border-[var(--color-border)] w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Attribute Bulk Update</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ selectedIds.length }} Attribute ausgewählt</p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors" @click="close">
              <X class="w-4 h-4 text-[var(--color-text-tertiary)]" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-5 py-4 space-y-3">
            <p class="text-[11px] text-[var(--color-text-tertiary)]">Nur angehakte Felder werden geändert.</p>

            <!-- Boolean fields -->
            <div v-for="bf in boolFields" :key="bf.key" class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="fields[bf.key].enabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">{{ bf.label }}</label>
              <select
                v-model="fields[bf.key].value"
                :disabled="!fields[bf.key].enabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!fields[bf.key].enabled ? 'opacity-40' : ''"
              >
                <option :value="true">Ja</option>
                <option :value="false">Nein</option>
              </select>
            </div>

            <!-- Divider -->
            <div class="border-t border-[var(--color-border)] my-2" />

            <!-- Attribute Group -->
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="fields.attribute_type_id.enabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">Attributgruppe</label>
              <select
                v-model="fields.attribute_type_id.value"
                :disabled="!fields.attribute_type_id.enabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!fields.attribute_type_id.enabled ? 'opacity-40' : ''"
              >
                <option value="">— Keine —</option>
                <option v-for="o in attributeTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>

            <!-- Status -->
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="fields.status.enabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">Status</label>
              <select
                v-model="fields.status.value"
                :disabled="!fields.status.enabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!fields.status.enabled ? 'opacity-40' : ''"
              >
                <option value="active">Aktiv</option>
                <option value="inactive">Inaktiv</option>
              </select>
            </div>

            <!-- Metadaten (Data Quality & Ownership) -->
            <template v-if="metaRows.length">
              <div class="border-t border-[var(--color-border)] my-2" />
              <div class="text-xs font-semibold text-[var(--color-text-secondary)]">Metadaten</div>

              <div
                v-for="{ definition: d, field } in metaRows"
                :key="d.technical_name"
                class="flex items-start gap-3"
              >
                <input
                  type="checkbox"
                  v-model="field.enabled"
                  class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)] mt-1"
                />
                <label class="text-xs text-[var(--color-text-secondary)] w-36 mt-1">
                  {{ d.name_de || d.technical_name }}
                </label>

                <!-- Mehrfachauswahl -->
                <div
                  v-if="d.value_type === 'multiselect'"
                  class="flex-1 max-h-24 overflow-y-auto border border-[var(--color-border)] rounded p-1"
                  :class="!field.enabled ? 'opacity-40' : ''"
                >
                  <label
                    v-for="o in metaOptions(d)"
                    :key="o.value"
                    class="flex items-center gap-2 px-1 py-0.5 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      :checked="field.value.includes(o.value)"
                      :disabled="!field.enabled"
                      class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]"
                      @change="toggleMetaMultiValue(d.technical_name, o.value)"
                    />
                    <span class="text-xs text-[var(--color-text-secondary)]">{{ o.label }}</span>
                  </label>
                </div>

                <!-- Einfachauswahl -->
                <select
                  v-else-if="d.value_type === 'select'"
                  v-model="field.value"
                  :disabled="!field.enabled"
                  class="pim-input text-xs py-1 px-2 flex-1"
                  :class="!field.enabled ? 'opacity-40' : ''"
                >
                  <option value="">— Wert entfernen —</option>
                  <option v-for="o in metaOptions(d)" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>

                <!-- Ja/Nein -->
                <select
                  v-else-if="d.value_type === 'boolean'"
                  v-model="field.value"
                  :disabled="!field.enabled"
                  class="pim-input text-xs py-1 px-2 flex-1"
                  :class="!field.enabled ? 'opacity-40' : ''"
                >
                  <option value="">— Wert entfernen —</option>
                  <option :value="true">Ja</option>
                  <option :value="false">Nein</option>
                </select>

                <!-- Freitext / Zahl / Datum -->
                <input
                  v-else
                  v-model="field.value"
                  :type="d.value_type === 'number' ? 'number' : d.value_type === 'date' ? 'date' : 'text'"
                  :step="d.value_type === 'number' ? 'any' : undefined"
                  :disabled="!field.enabled"
                  placeholder="leer lassen = Wert entfernen"
                  class="pim-input text-xs py-1 px-2 flex-1"
                  :class="!field.enabled ? 'opacity-40' : ''"
                />
              </div>

              <p
                v-if="clearingMetaLabels.length"
                class="text-[10px] text-[var(--color-warning,#f59e0b)]"
              >
                Wird geleert und damit entfernt: {{ clearingMetaLabels.join(', ') }}
              </p>
            </template>

            <!-- Divider -->
            <div class="border-t border-[var(--color-border)] my-2" />

            <!-- Attribute Views -->
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="viewsEnabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">Attributsichten</label>
              <select
                v-model="viewsMode"
                :disabled="!viewsEnabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!viewsEnabled ? 'opacity-40' : ''"
              >
                <option value="add">Hinzufügen</option>
                <option value="remove">Entfernen</option>
                <option value="set">Setzen</option>
              </select>
            </div>

            <!-- Multi-select for views -->
            <div v-if="viewsEnabled" class="ml-8 mt-1">
              <div class="border border-[var(--color-border)] rounded-lg max-h-32 overflow-y-auto p-1.5">
                <label
                  v-for="view in availableViews"
                  :key="view.id"
                  class="flex items-center gap-2 px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :value="view.id"
                    v-model="selectedViewIds"
                    class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
                  />
                  <span class="text-xs text-[var(--color-text-secondary)]">{{ view.name_de || view.technical_name }}</span>
                </label>
                <p v-if="availableViews.length === 0" class="text-xs text-[var(--color-text-tertiary)] px-2 py-1">
                  Keine Attributsichten vorhanden.
                </p>
              </div>
              <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
                {{ viewsMode === 'add' ? 'Ausgewählte Sichten werden hinzugefügt.' :
                   viewsMode === 'remove' ? 'Ausgewählte Sichten werden entfernt.' :
                   'Alle bestehenden Sichten werden durch die Auswahl ersetzt.' }}
              </p>
            </div>

            <!-- Error -->
            <p v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!hasEnabledFields || saving"
              @click="apply"
            >
              {{ saving ? 'Wird gespeichert…' : 'Anwenden' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
