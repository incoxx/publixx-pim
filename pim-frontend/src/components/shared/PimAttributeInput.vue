<script setup>
import { ref, computed, watch, defineAsyncComponent } from 'vue'
import { Check, X } from 'lucide-vue-next'

const PimRichTextEditor = defineAsyncComponent(() => import('./PimRichTextEditor.vue'))
const PimHierarchyReferenceInput = defineAsyncComponent(() => import('./PimHierarchyReferenceInput.vue'))
const PimProductReferenceInput = defineAsyncComponent(() => import('./PimProductReferenceInput.vue'))

const props = defineProps({
  type: { type: String, default: 'text' },
  modelValue: { default: null },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  min: { type: Number, default: undefined },
  max: { type: Number, default: undefined },
  step: { type: Number, default: undefined },
  delimiter: { type: String, default: '|' },
  rows: { type: Number, default: undefined },
  cols: { type: Number, default: undefined },
})

const emit = defineEmits(['update:modelValue'])

const inputClass = computed(() => [
  'pim-input',
  props.error ? 'border-[var(--color-error)] focus:border-[var(--color-error)] focus:ring-[var(--color-error)]' : '',
])

// Dictionary combobox state
const dictSearch = ref('')
const dictOpen = ref(false)

const filteredDictOptions = computed(() => {
  if (!dictSearch.value) return props.options
  const term = dictSearch.value.toLowerCase()
  return props.options.filter(o => (o.label ?? String(o)).toLowerCase().includes(term))
})

const selectedDictLabel = computed(() => {
  if (!props.modelValue) return ''
  const found = props.options.find(o => String(o.value ?? o) === String(props.modelValue))
  return found ? (found.label ?? found) : ''
})

function selectDictEntry(opt) {
  emit('update:modelValue', opt.value ?? opt)
  dictSearch.value = ''
  dictOpen.value = false
}

function clearDictEntry() {
  emit('update:modelValue', null)
  dictSearch.value = ''
}

// Multi-combobox state
const mcSearch = ref('')
const mcOpen = ref(false)

const mcFilteredOptions = computed(() => {
  const selected = props.modelValue || []
  let opts = props.options.filter(o => !selected.includes(o.value ?? o))
  if (mcSearch.value) {
    const term = mcSearch.value.toLowerCase()
    opts = opts.filter(o => (o.label ?? String(o)).toLowerCase().includes(term))
  }
  return opts
})

const mcSelectedOptions = computed(() => {
  const selected = props.modelValue || []
  return selected.map(v => {
    const found = props.options.find(o => (o.value ?? o) === v)
    return { value: v, label: found ? (found.label ?? found) : v }
  })
})

function mcSelect(opt) {
  const val = opt.value ?? opt
  emit('update:modelValue', [...(props.modelValue || []), val])
  mcSearch.value = ''
}

function mcRemove(val) {
  emit('update:modelValue', (props.modelValue || []).filter(v => v !== val))
}

function update(value) {
  emit('update:modelValue', value)
}

// ─── Link data type helpers ───────────────────────────
const LINK_TYPES = ['hyperlink', 'imagelink', 'pdflink', 'videolink']

function parseLinkValue(val) {
  if (!val) return {}
  if (typeof val === 'object') return { ...val }
  try { return JSON.parse(val) } catch { return {} }
}

const linkData = ref(parseLinkValue(props.modelValue))

watch(() => props.modelValue, (val) => {
  linkData.value = parseLinkValue(val)
})

function emitLink() {
  emit('update:modelValue', JSON.stringify(linkData.value))
}

// ─── DelimitedValue helpers ──────────────────────────
const dvNewValue = ref('')
const dvEditIndex = ref(-1)
const dvEditValue = ref('')

const dvParsedValues = computed(() => {
  if (!props.modelValue || typeof props.modelValue !== 'string') return []
  return props.modelValue.split(props.delimiter).map(v => v.trim()).filter(v => v !== '')
})

function dvAdd() {
  const val = dvNewValue.value.trim()
  if (!val) return
  const current = dvParsedValues.value
  current.push(val)
  emit('update:modelValue', current.join(props.delimiter))
  dvNewValue.value = ''
}

function dvRemove(index) {
  const current = [...dvParsedValues.value]
  current.splice(index, 1)
  emit('update:modelValue', current.length ? current.join(props.delimiter) : null)
}

function dvStartEdit(index) {
  dvEditIndex.value = index
  dvEditValue.value = dvParsedValues.value[index]
}

function dvConfirmEdit() {
  const val = dvEditValue.value.trim()
  if (!val) {
    dvRemove(dvEditIndex.value)
  } else {
    const current = [...dvParsedValues.value]
    current[dvEditIndex.value] = val
    emit('update:modelValue', current.join(props.delimiter))
  }
  dvEditIndex.value = -1
  dvEditValue.value = ''
}

function dvCancelEdit() {
  dvEditIndex.value = -1
  dvEditValue.value = ''
}
</script>

<template>
  <!-- Text -->
  <input
    v-if="type === 'text' || type === 'email' || type === 'url'"
    :type="type"
    :class="inputClass"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    @input="update($event.target.value)"
  />

  <!-- Number / Decimal -->
  <input
    v-else-if="type === 'number' || type === 'decimal'"
    type="number"
    :class="inputClass"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :min="min"
    :max="max"
    :step="type === 'decimal' ? step ?? 0.01 : step ?? 1"
    @input="update(type === 'decimal' ? parseFloat($event.target.value) : parseInt($event.target.value))"
  />

  <!-- Rich Text Editor -->
  <PimRichTextEditor
    v-else-if="type === 'richtext'"
    :modelValue="modelValue"
    :disabled="disabled"
    :placeholder="placeholder"
    @update:modelValue="update($event)"
  />

  <!-- Textarea -->
  <textarea
    v-else-if="type === 'textarea'"
    :class="[...inputClass, 'min-h-[80px] resize-y']"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :rows="rows || 4"
    :cols="cols || undefined"
    @input="update($event.target.value)"
  />

  <!-- Select -->
  <select
    v-else-if="type === 'select'"
    :class="inputClass"
    :value="modelValue"
    :disabled="disabled"
    @change="update($event.target.value)"
  >
    <option value="" disabled>{{ placeholder || 'Auswählen…' }}</option>
    <option
      v-for="opt in options"
      :key="opt.value ?? opt"
      :value="opt.value ?? opt"
    >
      {{ opt.label ?? opt }}
    </option>
  </select>

  <!-- Multi-select (checkboxes) -->
  <div v-else-if="type === 'multiselect'" class="space-y-1">
    <label
      v-for="opt in options"
      :key="opt.value ?? opt"
      class="flex items-center gap-2 text-[13px] cursor-pointer"
    >
      <input
        type="checkbox"
        :checked="(modelValue || []).includes(opt.value ?? opt)"
        :disabled="disabled"
        class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]"
        @change="
          update(
            $event.target.checked
              ? [...(modelValue || []), opt.value ?? opt]
              : (modelValue || []).filter(v => v !== (opt.value ?? opt))
          )
        "
      />
      <span>{{ opt.label ?? opt }}</span>
    </label>
  </div>

  <!-- Boolean toggle -->
  <div v-else-if="type === 'boolean'" class="flex items-center">
    <button
      type="button"
      :class="[
        'relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out cursor-pointer',
        modelValue ? 'bg-[var(--color-accent)]' : 'bg-[var(--color-border-strong)]',
        disabled ? 'opacity-50 cursor-not-allowed' : '',
      ]"
      :disabled="disabled"
      @click="update(!modelValue)"
    >
      <span
        :class="[
          'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200 ease-in-out',
          modelValue ? 'translate-x-4' : 'translate-x-0',
        ]"
      />
    </button>
  </div>

  <!-- Date -->
  <input
    v-else-if="type === 'date' || type === 'datetime'"
    :type="type === 'datetime' ? 'datetime-local' : 'date'"
    :class="inputClass"
    :value="modelValue"
    :disabled="disabled"
    @input="update($event.target.value)"
  />

  <!-- Dictionary (searchable combobox) -->
  <div v-else-if="type === 'dictionary'" class="relative">
    <!-- Selected value display -->
    <div v-if="modelValue && !dictOpen" class="flex items-center gap-1">
      <span :class="[...inputClass, 'flex-1 cursor-pointer text-[13px]']" @click="dictOpen = true">
        {{ selectedDictLabel || modelValue }}
      </span>
      <button
        v-if="!disabled"
        type="button"
        class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]"
        @click="clearDictEntry"
      >
        <X class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>
    <!-- Search input -->
    <div v-else>
      <input
        type="text"
        :class="[...inputClass, 'text-[13px]']"
        v-model="dictSearch"
        :placeholder="placeholder || 'Wörterbuch durchsuchen…'"
        :disabled="disabled"
        @focus="dictOpen = true"
        @blur="setTimeout(() => dictOpen = false, 200)"
      />
      <!-- Dropdown -->
      <div
        v-if="dictOpen"
        class="absolute z-30 left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg"
      >
        <div
          v-for="opt in filteredDictOptions"
          :key="opt.value ?? opt"
          class="px-3 py-1.5 text-[13px] cursor-pointer hover:bg-[var(--color-bg)] transition-colors"
          @mousedown.prevent="selectDictEntry(opt)"
        >
          {{ opt.label ?? opt }}
        </div>
        <div v-if="filteredDictOptions.length === 0" class="px-3 py-2 text-[12px] text-[var(--color-text-tertiary)]">
          Keine Einträge gefunden
        </div>
      </div>
    </div>
  </div>

  <!-- Multi-Combobox (searchable multi-select dropdown) -->
  <div v-else-if="type === 'multicombobox'" class="relative">
    <!-- Selected tags -->
    <div v-if="mcSelectedOptions.length" class="flex flex-wrap gap-1 mb-1.5">
      <span
        v-for="sel in mcSelectedOptions"
        :key="sel.value"
        class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[11px] border border-[var(--color-border)]"
      >
        {{ sel.label }}
        <button
          v-if="!disabled"
          type="button"
          class="ml-0.5 hover:text-[var(--color-error)]"
          @click="mcRemove(sel.value)"
        >
          <X class="w-3 h-3" :stroke-width="2" />
        </button>
      </span>
    </div>
    <!-- Search input -->
    <input
      type="text"
      :class="[...inputClass, 'text-[13px]']"
      v-model="mcSearch"
      :placeholder="placeholder || 'Suchen…'"
      :disabled="disabled"
      @focus="mcOpen = true"
      @blur="setTimeout(() => mcOpen = false, 200)"
    />
    <!-- Dropdown -->
    <div
      v-if="mcOpen && mcFilteredOptions.length > 0"
      class="absolute z-30 left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg"
    >
      <div
        v-for="opt in mcFilteredOptions"
        :key="opt.value ?? opt"
        class="px-3 py-1.5 text-[13px] cursor-pointer hover:bg-[var(--color-bg)] transition-colors"
        @mousedown.prevent="mcSelect(opt)"
      >
        {{ opt.label ?? opt }}
      </div>
    </div>
    <div
      v-if="mcOpen && mcFilteredOptions.length === 0 && mcSearch"
      class="absolute z-30 left-0 right-0 mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg"
    >
      <div class="px-3 py-2 text-[12px] text-[var(--color-text-tertiary)]">
        Keine Einträge gefunden
      </div>
    </div>
  </div>

  <!-- Hyperlink -->
  <div v-else-if="type === 'hyperlink'" class="space-y-1.5">
    <input :class="inputClass" :value="linkData.url" :disabled="disabled" placeholder="URL" @input="linkData.url = $event.target.value; emitLink()" />
    <input :class="inputClass" :value="linkData.title" :disabled="disabled" placeholder="Titel" @input="linkData.title = $event.target.value; emitLink()" />
    <select :class="inputClass" :value="linkData.target || '_blank'" :disabled="disabled" @change="linkData.target = $event.target.value; emitLink()">
      <option value="_blank">Neues Fenster (_blank)</option>
      <option value="_self">Gleiches Fenster (_self)</option>
    </select>
  </div>

  <!-- ImageLink -->
  <div v-else-if="type === 'imagelink'" class="space-y-1.5">
    <input :class="inputClass" :value="linkData.url" :disabled="disabled" placeholder="Bild-URL" @input="linkData.url = $event.target.value; emitLink()" />
    <input :class="inputClass" :value="linkData.title" :disabled="disabled" placeholder="Titel" @input="linkData.title = $event.target.value; emitLink()" />
    <input :class="inputClass" :value="linkData.alt_text" :disabled="disabled" placeholder="Alt-Text" @input="linkData.alt_text = $event.target.value; emitLink()" />
    <div class="flex gap-2">
      <input :class="inputClass" type="number" :value="linkData.width" :disabled="disabled" placeholder="Breite (px)" @input="linkData.width = parseInt($event.target.value) || null; emitLink()" />
      <input :class="inputClass" type="number" :value="linkData.height" :disabled="disabled" placeholder="Höhe (px)" @input="linkData.height = parseInt($event.target.value) || null; emitLink()" />
    </div>
  </div>

  <!-- PdfLink -->
  <div v-else-if="type === 'pdflink'" class="space-y-1.5">
    <input :class="inputClass" :value="linkData.url" :disabled="disabled" placeholder="PDF-URL" @input="linkData.url = $event.target.value; emitLink()" />
    <input :class="inputClass" :value="linkData.title" :disabled="disabled" placeholder="Titel" @input="linkData.title = $event.target.value; emitLink()" />
    <select :class="inputClass" :value="linkData.target || '_blank'" :disabled="disabled" @change="linkData.target = $event.target.value; emitLink()">
      <option value="_blank">Neues Fenster (_blank)</option>
      <option value="_self">Gleiches Fenster (_self)</option>
    </select>
  </div>

  <!-- VideoLink -->
  <div v-else-if="type === 'videolink'" class="space-y-1.5">
    <input :class="inputClass" :value="linkData.url" :disabled="disabled" placeholder="Video-URL" @input="linkData.url = $event.target.value; emitLink()" />
    <input :class="inputClass" :value="linkData.title" :disabled="disabled" placeholder="Titel" @input="linkData.title = $event.target.value; emitLink()" />
    <div class="flex gap-2">
      <input :class="inputClass" type="number" :value="linkData.width" :disabled="disabled" placeholder="Breite (px)" @input="linkData.width = parseInt($event.target.value) || null; emitLink()" />
      <input :class="inputClass" type="number" :value="linkData.height" :disabled="disabled" placeholder="Höhe (px)" @input="linkData.height = parseInt($event.target.value) || null; emitLink()" />
    </div>
  </div>

  <!-- DelimitedValue (Tag-Input) -->
  <div v-else-if="type === 'delimitedvalue'" class="space-y-1.5">
    <!-- Tags -->
    <div v-if="dvParsedValues.length" class="flex flex-wrap gap-1">
      <template v-for="(val, idx) in dvParsedValues" :key="idx">
        <!-- Inline-Edit -->
        <span v-if="dvEditIndex === idx" class="inline-flex items-center gap-0.5">
          <input
            type="text"
            class="pim-input text-[12px] px-1.5 py-0.5 w-24"
            v-model="dvEditValue"
            @keydown.enter.prevent="dvConfirmEdit()"
            @keydown.escape.prevent="dvCancelEdit()"
            @blur="dvConfirmEdit()"
            autofocus
          />
        </span>
        <!-- Tag -->
        <span
          v-else
          class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md bg-[var(--color-accent)]/10 text-[var(--color-accent)] text-[12px] font-medium border border-[var(--color-accent)]/20 cursor-default"
          @dblclick="!disabled && dvStartEdit(idx)"
        >
          {{ val }}
          <button
            v-if="!disabled"
            type="button"
            class="ml-0.5 hover:text-[var(--color-error)] transition-colors"
            @click="dvRemove(idx)"
          >
            <X class="w-3 h-3" :stroke-width="2" />
          </button>
        </span>
      </template>
    </div>
    <!-- Neuer Wert -->
    <div v-if="!disabled" class="flex gap-1.5">
      <input
        type="text"
        :class="[...inputClass, 'text-[13px] flex-1']"
        v-model="dvNewValue"
        :placeholder="placeholder || 'Neuen Wert hinzufügen…'"
        @keydown.enter.prevent="dvAdd()"
      />
      <button
        type="button"
        class="px-2 py-1 rounded-md bg-[var(--color-accent)] text-white text-[12px] font-medium hover:opacity-90 transition-opacity shrink-0"
        @click="dvAdd()"
      >
        +
      </button>
    </div>
  </div>

  <!-- JsonArtefact (Code-Block) -->
  <div v-else-if="type === 'jsonartefact'" class="relative">
    <textarea
      :class="[...inputClass, 'font-mono text-xs min-h-[120px] resize-y bg-[var(--color-bg)] border-[var(--color-border)] rounded-lg']"
      :value="modelValue"
      :placeholder="placeholder || '{ }'"
      :disabled="disabled"
      @input="update($event.target.value)"
    />
    <span class="absolute top-1.5 right-2 text-[9px] text-[var(--color-text-tertiary)] font-mono select-none">JSON</span>
  </div>

  <!-- JSON -->
  <textarea
    v-else-if="type === 'json'"
    :class="[...inputClass, 'font-mono text-xs min-h-[120px] resize-y']"
    :value="typeof modelValue === 'object' ? JSON.stringify(modelValue, null, 2) : modelValue"
    :placeholder="placeholder || '{}'"
    :disabled="disabled"
    @input="update($event.target.value)"
  />

  <!-- HierarchyNodeReference: Hierarchie/Knoten-Picker -->
  <PimHierarchyReferenceInput
    v-else-if="type === 'hierarchyreference'"
    :modelValue="modelValue"
    :disabled="disabled"
    @update:modelValue="update($event)"
  />

  <!-- ProductReference: Produkt-Picker -->
  <PimProductReferenceInput
    v-else-if="type === 'productreference'"
    :modelValue="modelValue"
    :disabled="disabled"
    @update:modelValue="update($event)"
  />

  <!-- Fallback: text -->
  <input
    v-else
    type="text"
    :class="inputClass"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    @input="update($event.target.value)"
  />
</template>
