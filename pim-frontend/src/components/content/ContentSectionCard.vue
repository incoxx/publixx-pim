<script setup>
import { ref, computed, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { GripVertical, Trash2, Eye, EyeOff } from 'lucide-vue-next'

const props = defineProps({
  section: { type: Object, required: true },
  sectionType: { type: Object, default: null },
  lang: { type: String, default: 'de' },
})

const emit = defineEmits(['save', 'delete', 'toggle-visible'])

// Lokale, editierbare Kopie der Feldwerte
const values = ref(structuredClone(props.section.values_json || {}))

watch(() => props.section.id, () => {
  values.value = structuredClone(props.section.values_json || {})
})

const fields = computed(() => props.sectionType?.schema?.fields || [])

const typeName = computed(() =>
  props.sectionType?.name_de || props.section.section_type?.name_de || props.section.section_type_id
)

// Bucket: übersetzbare Felder je Sprache, sprachneutrale unter "_"
function bucketKey(field) {
  return field.translatable ? props.lang : '_'
}

function getValue(field) {
  const bucket = values.value[bucketKey(field)] || {}
  return bucket[field.key]
}

function setValue(field, val) {
  const bk = bucketKey(field)
  if (!values.value[bk]) values.value[bk] = {}
  values.value[bk][field.key] = val
  debouncedSave()
}

const debouncedSave = useDebounceFn(() => {
  emit('save', structuredClone(values.value))
}, 500)

// Mapping data_type → HTML-Input-Variante
function inputKind(type) {
  switch (type) {
    case 'RichText':
    case 'Textarea':
      return 'textarea'
    case 'Number':
      return 'number'
    case 'Flag':
      return 'checkbox'
    case 'Selection':
      return 'select'
    default:
      return 'text' // String, Link, Media, product_ref, hierarchy_node_ref, pql
  }
}

// Platzhalter-Hinweis für (noch) nicht mit Picker ausgestattete Feldtypen
function fieldHint(type) {
  switch (type) {
    case 'Media': return 'Media-ID (Picker folgt)'
    case 'product_ref': return 'Produkt-ID (Picker folgt)'
    case 'hierarchy_node_ref': return 'Hierarchieknoten-ID (Picker folgt)'
    case 'pql': return 'PQL-Ausdruck'
    case 'Link': return 'URL'
    default: return ''
  }
}
</script>

<template>
  <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
    <!-- Kopfzeile -->
    <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)]">
      <div class="flex items-center gap-2 min-w-0">
        <GripVertical class="w-4 h-4 text-[var(--color-text-tertiary)] cursor-grab shrink-0 drag-handle" :stroke-width="2" />
        <span class="text-xs font-semibold truncate">{{ typeName }}</span>
        <span v-if="sectionType?.category" class="text-[10px] text-[var(--color-text-tertiary)] uppercase tracking-wide">{{ sectionType.category }}</span>
      </div>
      <div class="flex items-center gap-1 shrink-0">
        <button
          class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]"
          :title="section.is_visible ? 'Sichtbar' : 'Ausgeblendet'"
          @click="$emit('toggle-visible')"
        >
          <Eye v-if="section.is_visible" class="w-3.5 h-3.5" :stroke-width="2" />
          <EyeOff v-else class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <button
          class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
          title="Sektion löschen"
          @click="$emit('delete')"
        >
          <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Felder -->
    <div class="p-3 space-y-3">
      <div v-for="field in fields" :key="field.key">
        <label class="flex items-center gap-1.5 text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
          {{ field.label || field.key }}
          <span v-if="field.required" class="text-[var(--color-error)]">*</span>
          <span v-if="field.translatable" class="text-[10px] text-[var(--color-text-tertiary)] uppercase">{{ lang }}</span>
        </label>

        <!-- textarea -->
        <textarea
          v-if="inputKind(field.type) === 'textarea'"
          class="pim-input text-xs w-full"
          rows="4"
          :value="getValue(field)"
          @input="setValue(field, $event.target.value)"
        />

        <!-- select -->
        <select
          v-else-if="inputKind(field.type) === 'select'"
          class="pim-input text-xs w-full"
          :value="getValue(field)"
          @change="setValue(field, $event.target.value)"
        >
          <option value="">—</option>
          <option v-for="opt in (field.options || [])" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <!-- checkbox -->
        <input
          v-else-if="inputKind(field.type) === 'checkbox'"
          type="checkbox"
          class="pim-checkbox"
          :checked="!!getValue(field)"
          @change="setValue(field, $event.target.checked)"
        />

        <!-- number -->
        <input
          v-else-if="inputKind(field.type) === 'number'"
          type="number"
          class="pim-input text-xs w-full"
          :value="getValue(field)"
          @input="setValue(field, $event.target.value === '' ? null : Number($event.target.value))"
        />

        <!-- text / link / refs -->
        <input
          v-else
          type="text"
          class="pim-input text-xs w-full"
          :placeholder="fieldHint(field.type)"
          :value="getValue(field)"
          @input="setValue(field, $event.target.value)"
        />

        <p v-if="fieldHint(field.type) && inputKind(field.type) === 'text'" class="mt-1 text-[10px] text-[var(--color-text-tertiary)]">
          {{ fieldHint(field.type) }}
        </p>
      </div>

      <p v-if="!fields.length" class="text-xs text-[var(--color-text-tertiary)]">
        Dieser Sektionstyp hat keine Felder.
      </p>
    </div>
  </div>
</template>
