<script setup>
import { ref, computed, defineAsyncComponent } from 'vue'
import { Check, X } from 'lucide-vue-next'

const PimRichTextEditor = defineAsyncComponent(() => import('./PimRichTextEditor.vue'))

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

function update(value) {
  emit('update:modelValue', value)
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
    rows="3"
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

  <!-- JSON -->
  <textarea
    v-else-if="type === 'json'"
    :class="[...inputClass, 'font-mono text-xs min-h-[120px] resize-y']"
    :value="typeof modelValue === 'object' ? JSON.stringify(modelValue, null, 2) : modelValue"
    :placeholder="placeholder || '{}'"
    :disabled="disabled"
    @input="update($event.target.value)"
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
