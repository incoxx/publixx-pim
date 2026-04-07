<script setup>
import { computed } from 'vue'
import { Plus, Trash2, GripVertical, ChevronUp, ChevronDown } from 'lucide-vue-next'
import PimAttributeInput from './PimAttributeInput.vue'

const props = defineProps({
  /** Array of { value, multiplied_index, unit_id? } entries */
  modelValue: { type: Array, default: () => [] },
  type: { type: String, default: 'text' },
  options: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  maxMultiplied: { type: Number, default: null },
  placeholder: { type: String, default: '' },
  /** Unit group with units array for unit selector: { id, units: [{ id, abbreviation }] } */
  unitGroup: { type: Object, default: null },
  delimiter: { type: String, default: '|' },
  rows: { type: Number, default: undefined },
  cols: { type: Number, default: undefined },
  min: { type: Number, default: undefined },
  max: { type: Number, default: undefined },
})

const emit = defineEmits(['update:modelValue'])

const entries = computed(() => {
  if (!props.modelValue || props.modelValue.length === 0) {
    return [{ value: null, multiplied_index: 0 }]
  }
  return [...props.modelValue].sort((a, b) => a.multiplied_index - b.multiplied_index)
})

const canAdd = computed(() => {
  if (props.disabled) return false
  if (props.maxMultiplied && entries.value.length >= props.maxMultiplied) return false
  return true
})

function updateValue(index, newValue) {
  const updated = entries.value.map((e, i) =>
    i === index ? { ...e, value: newValue } : { ...e }
  )
  emit('update:modelValue', updated)
}

function updateUnit(index, unitId) {
  const updated = entries.value.map((e, i) =>
    i === index ? { ...e, unit_id: unitId || null } : { ...e }
  )
  emit('update:modelValue', updated)
}

function addEntry() {
  if (!canAdd.value) return
  const maxIdx = entries.value.reduce((max, e) => Math.max(max, e.multiplied_index), -1)
  // Einheit vom letzten Eintrag übernehmen (falls vorhanden)
  const lastUnit = entries.value.length > 0 ? entries.value[entries.value.length - 1].unit_id : null
  const updated = [...entries.value, { value: null, multiplied_index: maxIdx + 1, unit_id: lastUnit || null }]
  emit('update:modelValue', updated)
}

function removeEntry(index) {
  if (entries.value.length <= 1) {
    // Keep at least one entry, but clear its value
    emit('update:modelValue', [{ value: null, multiplied_index: 0 }])
    return
  }
  const updated = entries.value.filter((_, i) => i !== index)
    .map((e, i) => ({ ...e, multiplied_index: i }))
  emit('update:modelValue', updated)
}

function moveUp(index) {
  if (index <= 0) return
  const updated = [...entries.value]
  ;[updated[index - 1], updated[index]] = [updated[index], updated[index - 1]]
  emit('update:modelValue', updated.map((e, i) => ({ ...e, multiplied_index: i })))
}

function moveDown(index) {
  if (index >= entries.value.length - 1) return
  const updated = [...entries.value]
  ;[updated[index], updated[index + 1]] = [updated[index + 1], updated[index]]
  emit('update:modelValue', updated.map((e, i) => ({ ...e, multiplied_index: i })))
}
</script>

<template>
  <div class="space-y-1.5 border-l-2 border-[var(--color-border)] pl-3">
    <div
      v-for="(entry, index) in entries"
      :key="entry.multiplied_index"
      class="flex items-start gap-1 group"
    >
      <!-- Reorder buttons -->
      <div v-if="entries.length > 1 && !disabled" class="flex flex-col items-center pt-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          type="button"
          class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] disabled:opacity-30"
          :disabled="index === 0"
          @click="moveUp(index)"
          title="Nach oben"
        >
          <ChevronUp class="w-3 h-3" :stroke-width="2" />
        </button>
        <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <button
          type="button"
          class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] disabled:opacity-30"
          :disabled="index === entries.length - 1"
          @click="moveDown(index)"
          title="Nach unten"
        >
          <ChevronDown class="w-3 h-3" :stroke-width="2" />
        </button>
      </div>

      <!-- Index badge -->
      <span
        v-if="entries.length > 1"
        class="mt-2 text-[10px] font-mono text-[var(--color-text-tertiary)] tabular-nums min-w-[16px] text-center"
      >{{ index + 1 }}</span>

      <!-- Input + optional Unit -->
      <div class="flex-1 flex gap-1.5 items-start">
        <PimAttributeInput
          class="flex-1 min-w-0"
          :type="type"
          :modelValue="entry.value"
          :options="options"
          :disabled="disabled"
          :placeholder="placeholder"
          :delimiter="delimiter"
          :rows="rows"
          :cols="cols"
          :min="min"
          :max="max"
          @update:modelValue="updateValue(index, $event)"
        />
        <select
          v-if="unitGroup?.units?.length"
          class="pim-input text-[12px] !w-16 !min-w-0 shrink-0 !px-1"
          :value="entry.unit_id || ''"
          :disabled="disabled"
          @change="updateUnit(index, $event.target.value)"
        >
          <option value="">—</option>
          <option v-for="u in unitGroup.units" :key="u.id" :value="u.id">{{ u.abbreviation }}</option>
        </select>
      </div>

      <!-- Remove button -->
      <button
        v-if="!disabled"
        type="button"
        class="mt-1.5 p-1 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] hover:bg-red-50 dark:hover:bg-red-950/30 opacity-0 group-hover:opacity-100 transition-all"
        @click="removeEntry(index)"
        title="Eintrag entfernen"
      >
        <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Add button -->
    <button
      v-if="canAdd"
      type="button"
      class="flex items-center gap-1 text-[11px] text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] transition-colors py-0.5"
      @click="addEntry"
    >
      <Plus class="w-3.5 h-3.5" :stroke-width="2" />
      <span>Weiteren Wert hinzufügen</span>
    </button>

    <!-- Max hint -->
    <p
      v-if="maxMultiplied && entries.length >= maxMultiplied"
      class="text-[10px] text-[var(--color-text-tertiary)]"
    >
      Maximum erreicht ({{ maxMultiplied }})
    </p>
  </div>
</template>
