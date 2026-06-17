<script setup>
import { ref, watch } from 'vue'
import { X, Loader2 } from 'lucide-vue-next'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import productsApi from '@/api/products'
import attributesApi, { valueLists } from '@/api/attributes'
import dictionaryApi from '@/api/dictionary'

const props = defineProps({
  open: { type: Boolean, default: false },
  productId: { type: String, default: null },
  attributeId: { type: String, default: null },
  label: { type: String, default: '' },
  language: { type: String, default: 'de' },
})

const emit = defineEmits(['close', 'saved'])

// Datentyp → Eingabe-Widget (nur flache Typen; identisch zum Produkteditor)
const DATA_TYPE_INPUT = {
  String: 'text',
  Number: 'number',
  Float: 'decimal',
  Date: 'date',
  Flag: 'boolean',
  Selection: 'select',
  MultiSelection: 'multicombobox',
  Dictionary: 'dictionary',
  Textarea: 'textarea',
}

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const attribute = ref(null)
const inputType = ref('text')
const options = ref([])
const editValue = ref(null)
const unitId = ref(null)

function rawValueFromRow(row, dataType) {
  if (!row) return dataType === 'MultiSelection' ? [] : (dataType === 'Flag' ? false : null)
  if (dataType === 'MultiSelection') {
    const raw = row.value_string
    if (Array.isArray(raw)) return raw
    if (typeof raw === 'string' && raw) {
      try {
        const decoded = JSON.parse(raw)
        return Array.isArray(decoded) ? decoded : []
      } catch { return [] }
    }
    return []
  }
  if (dataType === 'Flag') return !!row.value_flag
  if (dataType === 'Date' && typeof row.value_date === 'string') return row.value_date.slice(0, 10)
  // Universelle Auflösung wie im Produkteditor (Selection/Dictionary liegen je nach
  // Speicherpfad in value_string oder value_selection_id).
  return row.value_string ?? row.value_number ?? row.value_date ?? row.value_flag ?? row.value_selection_id ?? null
}

async function loadOptions(attr) {
  const dt = attr.data_type
  if ((dt === 'Selection' || dt === 'MultiSelection') && attr.value_list_id) {
    try {
      const { data } = await valueLists.getEntries(attr.value_list_id, { perPage: 500 })
      const entries = data.data || data
      options.value = entries.map(e => ({
        value: e.id,
        label: e.display_value_de || e.value_de || e.label_de || e.code || e.technical_name,
      }))
    } catch (e) { console.error('Wertliste konnte nicht geladen werden:', e.message) }
  } else if (dt === 'Dictionary') {
    try {
      const { data } = await dictionaryApi.list({ perPage: 1000 })
      const entries = data.data || data
      options.value = entries.map(e => ({
        value: e.id,
        label: e.short_text_de || e.short_text_en || e.category || String(e.id),
      }))
    } catch (e) { console.error('Wörterbuch konnte nicht geladen werden:', e.message) }
  } else {
    options.value = []
  }
}

async function load() {
  if (!props.productId || !props.attributeId) return
  loading.value = true
  error.value = ''
  options.value = []
  editValue.value = null
  unitId.value = null
  try {
    const { data: attrResp } = await attributesApi.get(props.attributeId)
    const attr = attrResp.data || attrResp
    attribute.value = attr

    inputType.value = DATA_TYPE_INPUT[attr.data_type] || 'text'
    if (!DATA_TYPE_INPUT[attr.data_type]) {
      error.value = 'Dieser Datentyp kann hier nicht bearbeitet werden. Bitte den Produkteditor verwenden.'
    }

    await loadOptions(attr)

    const { data: valResp } = await productsApi.getAttributeValues(props.productId, {
      lang: props.language,
      perPage: 500,
    })
    const rows = valResp.data || valResp
    const row = rows.find(r =>
      r.attribute_id === props.attributeId &&
      (r.multiplied_index == null || r.multiplied_index === 0) &&
      (r.language === props.language || r.language == null)
    )
    editValue.value = rawValueFromRow(row, attr.data_type)
    unitId.value = row?.unit_id ?? attr.default_unit_id ?? null
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Wert konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

async function save() {
  const attr = attribute.value
  if (!attr) return
  saving.value = true
  error.value = ''
  try {
    // Wert wie im Produkteditor übergeben: MultiSelection als Array (Backend kodiert
    // selbst nach JSON), alle anderen Typen als Rohwert.
    let value = editValue.value
    if (attr.data_type === 'MultiSelection') {
      value = Array.isArray(value) ? value : []
    }
    const entry = { attribute_id: props.attributeId, value }
    if (attr.is_translatable) entry.language = props.language
    if (unitId.value) entry.unit_id = unitId.value
    await productsApi.saveAttributeValues(props.productId, [entry])
    emit('saved')
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Speichern fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}

watch(
  () => [props.open, props.attributeId, props.language],
  ([open]) => { if (open) load() },
  { immediate: true },
)
</script>

<template>
  <dialog class="modal" :class="{ 'modal-open': open }">
    <div class="modal-box max-w-md p-0 overflow-visible">
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
        <div class="min-w-0">
          <h3 class="font-semibold text-base-content truncate">{{ label || 'Wert bearbeiten' }}</h3>
          <p class="text-xs text-base-content/50">Sprache: {{ (language || 'de').toUpperCase() }}</p>
        </div>
        <button class="btn btn-sm btn-circle btn-ghost" @click="emit('close')">
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-4">
        <div v-if="loading" class="flex justify-center py-8">
          <Loader2 class="w-6 h-6 animate-spin text-primary" />
        </div>
        <template v-else>
          <div v-if="error" class="alert alert-warning text-sm mb-3 py-2">{{ error }}</div>
          <PimAttributeInput
            v-if="attribute && DATA_TYPE_INPUT[attribute.data_type]"
            v-model="editValue"
            :type="inputType"
            :options="options"
            :delimiter="attribute.delimiter || '|'"
            :rows="attribute.textarea_rows || undefined"
            :cols="attribute.textarea_cols || undefined"
            :min="attribute.min_value ?? undefined"
            :max="attribute.max_value ?? undefined"
          />
        </template>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-2 px-4 py-3 border-t border-base-300">
        <button class="btn btn-sm btn-ghost" @click="emit('close')">Abbrechen</button>
        <button
          class="btn btn-sm btn-primary gap-1"
          :disabled="saving || loading || !attribute || !DATA_TYPE_INPUT[attribute?.data_type]"
          @click="save"
        >
          <Loader2 v-if="saving" class="w-4 h-4 animate-spin" />
          Speichern
        </button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop bg-black/40" @click="emit('close')">
      <button>close</button>
    </form>
  </dialog>
</template>
