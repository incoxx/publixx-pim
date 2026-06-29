<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { GripVertical, Trash2, Eye, EyeOff, Plus, X } from 'lucide-vue-next'
import EntityPickerDialog from '@/components/shared/EntityPickerDialog.vue'
import MediaPickerDialog from '@/components/shared/MediaPickerDialog.vue'
import productsApi from '@/api/products'
import hierarchiesApi from '@/api/hierarchies'
import widgetsApi from '@/api/productWidgets'

const props = defineProps({
  section: { type: Object, required: true },
  sectionType: { type: Object, default: null },
  lang: { type: String, default: 'de' },
})

const emit = defineEmits(['save', 'delete', 'toggle-visible'])

const values = ref(structuredClone(props.section.values_json || {}))
watch(() => props.section.id, () => {
  values.value = structuredClone(props.section.values_json || {})
  resolveAllLabels()
})

const fields = computed(() => props.sectionType?.schema?.fields || [])
const typeName = computed(() =>
  props.sectionType?.name_de || props.section.section_type?.name_de || props.section.section_type_id
)

// ─── Werte lesen/schreiben (Buckets: Sprache vs. "_") ────────────
function bucketKey(field) { return field.translatable ? props.lang : '_' }
function getValue(field) { return (values.value[bucketKey(field)] || {})[field.key] }
function setValue(field, val) {
  const bk = bucketKey(field)
  if (!values.value[bk]) values.value[bk] = {}
  values.value[bk][field.key] = val
  debouncedSave()
}
const debouncedSave = useDebounceFn(() => emit('save', structuredClone(values.value)), 500)

// ─── Klassifikation der Feldtypen ───────────────────────────────
function kindOf(type) {
  if (['product_ref'].includes(type)) return 'product'
  if (['hierarchy_node_ref'].includes(type)) return 'node'
  if (['Media'].includes(type)) return 'media'
  if (['product_widget_ref'].includes(type)) return 'widget'
  if (['RichText', 'Textarea'].includes(type)) return 'textarea'
  if (type === 'Number') return 'number'
  if (type === 'Flag') return 'checkbox'
  if (type === 'Selection') return 'select'
  return 'text'
}

// ─── Referenz-Picker (Produkt / Hierarchieknoten / Media) ───────
const pickerKind = ref(null)   // product | node | media
const activeField = ref(null)
const labelCache = ref(new Map())

function asArray(v) { return Array.isArray(v) ? v : (v ? [v] : []) }

function openPicker(field) {
  activeField.value = field
  pickerKind.value = kindOf(field.type)
}

const entityFetcher = computed(() => {
  if (pickerKind.value === 'product') {
    return async (q, page) => {
      const { data } = await productsApi.list({ search: q || undefined, page, perPage: 20 })
      return { items: data.data ?? data, meta: data.meta }
    }
  }
  if (pickerKind.value === 'node') {
    return async (q, page) => {
      const { data } = await hierarchiesApi.searchNodes({ search: q || undefined, page, perPage: 20 })
      const items = (data.data ?? data).map((n) => ({ ...n, name: n.name_de }))
      return { items, meta: data.meta }
    }
  }
  return async () => ({ items: [], meta: {} })
})

function onEntityConfirm(items) {
  const f = activeField.value
  items.forEach((it) => {
    // Vorausgewählte (nicht neu angeklickte) Items kommen als Platzhalter {id}
    // ohne Name zurück — vorhandenes Label nicht mit der UUID überschreiben.
    const label = it.name_de || it.name || it.label
    if (label) labelCache.value.set(it.id, label)
    else if (!labelCache.value.has(it.id)) labelCache.value.set(it.id, it.id)
  })
  const ids = items.map((i) => i.id)
  setValue(f, f.multiple ? ids : (ids[0] ?? null))
  pickerKind.value = null
}

function onMediaSelect(item) {
  if (!activeField.value) return
  labelCache.value.set(item.id, item.file_name || item.id)
  setValue(activeField.value, activeField.value.multiple ? [...asArray(getValue(activeField.value)), item.id] : item.id)
  pickerKind.value = null
}

// Mehrfachauswahl aus dem Media-Dialog (für Galerie-/Media-Felder mit multiple)
function onMediaSelectMultiple(items) {
  const f = activeField.value
  if (!f) return
  items.forEach((it) => labelCache.value.set(it.id, it.file_name || it.id))
  const ids = items.map((i) => i.id)
  setValue(f, f.multiple ? [...asArray(getValue(f)), ...ids] : (ids[0] ?? null))
  pickerKind.value = null
}

function removeRef(field, id) {
  const cur = asArray(getValue(field)).filter((x) => x !== id)
  setValue(field, field.multiple ? cur : (cur[0] ?? null))
}

function refLabel(id) { return labelCache.value.get(id) || id }

// Labels für bereits gespeicherte IDs nachladen
async function resolveAllLabels() {
  for (const field of fields.value) {
    const kind = kindOf(field.type)
    const ids = asArray(getValue(field))
    for (const id of ids.slice(0, 25)) {
      if (labelCache.value.has(id)) continue
      try {
        if (kind === 'product') {
          const { data } = await productsApi.list({ filters: { id }, perPage: 1 })
          const it = (data.data ?? data)[0]
          if (it) labelCache.value.set(id, it.name)
        } else if (kind === 'node') {
          const { data } = await hierarchiesApi.getNode(id)
          const n = data.data ?? data
          if (n) labelCache.value.set(id, n.name_de)
        }
      } catch { /* Fallback: ID anzeigen */ }
    }
  }
}

// ─── Widget-Dropdown ────────────────────────────────────────────
const widgets = ref([])

onMounted(async () => {
  if (fields.value.some((f) => f.type === 'product_widget_ref')) {
    try {
      const { data } = await widgetsApi.list({ perPage: 200 })
      widgets.value = data.data ?? data
    } catch { widgets.value = [] }
  }
  resolveAllLabels()
})
</script>

<template>
  <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)]">
    <!-- Kopf -->
    <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)]">
      <div class="flex items-center gap-2 min-w-0">
        <GripVertical class="w-4 h-4 text-[var(--color-text-tertiary)] cursor-grab shrink-0 drag-handle" :stroke-width="2" />
        <span class="text-xs font-semibold truncate">{{ typeName }}</span>
        <span v-if="sectionType?.category" class="text-[10px] text-[var(--color-text-tertiary)] uppercase tracking-wide">{{ sectionType.category }}</span>
      </div>
      <div class="flex items-center gap-1 shrink-0">
        <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" :title="section.is_visible ? 'Sichtbar' : 'Ausgeblendet'" @click="$emit('toggle-visible')">
          <Eye v-if="section.is_visible" class="w-3.5 h-3.5" :stroke-width="2" />
          <EyeOff v-else class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
        <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" title="Sektion löschen" @click="$emit('delete')">
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

        <!-- Referenz-Felder: Produkt / Hierarchieknoten / Media -->
        <div v-if="['product', 'node', 'media'].includes(kindOf(field.type))" class="space-y-1.5">
          <div v-if="asArray(getValue(field)).length" class="flex flex-wrap gap-1.5">
            <span v-for="id in asArray(getValue(field))" :key="id"
              class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-[var(--color-bg)] text-xs">
              <span class="truncate max-w-40">{{ refLabel(id) }}</span>
              <button class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeRef(field, id)"><X class="w-3 h-3" :stroke-width="2.5" /></button>
            </span>
          </div>
          <button class="pim-btn pim-btn-secondary text-[11px]" @click="openPicker(field)">
            <Plus class="w-3 h-3" :stroke-width="2" />
            {{ field.multiple ? 'Hinzufügen' : (asArray(getValue(field)).length ? 'Ändern' : 'Auswählen') }}
          </button>
        </div>

        <!-- Widget-Dropdown -->
        <select v-else-if="kindOf(field.type) === 'widget'" class="pim-input text-xs w-full"
          :value="getValue(field)" @change="setValue(field, $event.target.value || null)">
          <option value="">— Standard —</option>
          <option v-for="w in widgets" :key="w.id" :value="w.technical_name">{{ w.name_de }}</option>
        </select>

        <!-- textarea -->
        <textarea v-else-if="kindOf(field.type) === 'textarea'" class="pim-input text-xs w-full" rows="4"
          :value="getValue(field)" @input="setValue(field, $event.target.value)" />

        <!-- select -->
        <select v-else-if="kindOf(field.type) === 'select'" class="pim-input text-xs w-full"
          :value="getValue(field)" @change="setValue(field, $event.target.value)">
          <option value="">—</option>
          <option v-for="opt in (field.options || [])" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <!-- checkbox -->
        <input v-else-if="kindOf(field.type) === 'checkbox'" type="checkbox" class="pim-checkbox"
          :checked="!!getValue(field)" @change="setValue(field, $event.target.checked)" />

        <!-- number -->
        <input v-else-if="kindOf(field.type) === 'number'" type="number" class="pim-input text-xs w-full"
          :value="getValue(field)" @input="setValue(field, $event.target.value === '' ? null : Number($event.target.value))" />

        <!-- text / link -->
        <input v-else type="text" class="pim-input text-xs w-full"
          :value="getValue(field)" @input="setValue(field, $event.target.value)" />
      </div>

      <p v-if="!fields.length" class="text-xs text-[var(--color-text-tertiary)]">Dieser Sektionstyp hat keine Felder.</p>
    </div>

    <!-- Picker-Dialoge -->
    <EntityPickerDialog
      :model-value="pickerKind === 'product' || pickerKind === 'node'"
      :title="pickerKind === 'product' ? 'Produkt auswählen' : 'Kategorie / Knoten auswählen'"
      :fetcher="entityFetcher"
      :multiple="!!activeField?.multiple"
      :selected="asArray(activeField ? getValue(activeField) : [])"
      :label-fn="(i) => i.name_de || i.name || i.label || i.id"
      :sublabel-fn="(i) => i.sku || i.path || null"
      @update:model-value="(v) => { if (!v) pickerKind = null }"
      @confirm="onEntityConfirm"
    />
    <MediaPickerDialog
      :model-value="pickerKind === 'media'"
      @update:model-value="(v) => { if (!v) pickerKind = null }"
      @select="onMediaSelect"
      @select-multiple="onMediaSelectMultiple"
    />
  </div>
</template>
