<script setup>
import { ref, computed } from 'vue'
import { Plus, Trash2, ChevronUp, ChevronDown, ChevronRight } from 'lucide-vue-next'
import PimAttributeInput from './PimAttributeInput.vue'

const props = defineProps({
  /**
   * Array von { multiplied_index, children: { childId: value | { data_type: 'Composite', children: { gcId: value } } } }
   */
  modelValue: { type: Array, default: () => [] },
  compositeAttribute: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
  maxMultiplied: { type: Number, default: null },
  mapType: { type: Function, default: (t) => t },
})

const emit = defineEmits(['update:modelValue'])

// Track welche Instanzen aufgeklappt sind
const expandedInstances = ref(new Set([0]))

const children = computed(() => props.compositeAttribute?._children || [])

const entries = computed(() => {
  if (!props.modelValue || props.modelValue.length === 0) {
    return [{ multiplied_index: 0, children: {} }]
  }
  return [...props.modelValue].sort((a, b) => a.multiplied_index - b.multiplied_index)
})

const canAdd = computed(() => {
  if (props.disabled) return false
  if (props.maxMultiplied && entries.value.length >= props.maxMultiplied) return false
  return true
})

function toggleExpand(idx) {
  if (expandedInstances.value.has(idx)) {
    expandedInstances.value.delete(idx)
  } else {
    expandedInstances.value.add(idx)
  }
}

function updateChildValue(entryIndex, childId, newValue) {
  const updated = entries.value.map((e, i) => {
    if (i !== entryIndex) return { ...e }
    return {
      ...e,
      children: { ...e.children, [childId]: newValue },
    }
  })
  emit('update:modelValue', updated)
}

function updateSubCompositeChildValue(entryIndex, subCompositeId, grandchildId, newValue) {
  const updated = entries.value.map((e, i) => {
    if (i !== entryIndex) return { ...e }
    const existing = e.children[subCompositeId] || { data_type: 'Composite', children: {} }
    return {
      ...e,
      children: {
        ...e.children,
        [subCompositeId]: {
          ...existing,
          children: { ...(existing.children || {}), [grandchildId]: newValue },
        },
      },
    }
  })
  emit('update:modelValue', updated)
}

function addEntry() {
  if (!canAdd.value) return
  const maxIdx = entries.value.reduce((max, e) => Math.max(max, e.multiplied_index), -1)
  const newIdx = maxIdx + 1
  const updated = [...entries.value, { multiplied_index: newIdx, children: {} }]
  expandedInstances.value.add(newIdx)
  emit('update:modelValue', updated)
}

function removeEntry(index) {
  if (entries.value.length <= 1) {
    emit('update:modelValue', [{ multiplied_index: 0, children: {} }])
    return
  }
  const updated = entries.value
    .filter((_, i) => i !== index)
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

function getFormatPreview(entry) {
  if (!props.compositeAttribute?.composite_format) return null
  let result = props.compositeAttribute.composite_format
  children.value.forEach((child, i) => {
    const val = child.data_type === 'Composite'
      ? '…'
      : entry.children?.[child.id]
    result = result.replace(`{${i}}`, val !== undefined && val !== null ? String(val) : '…')
  })
  return result
}

function getInstanceSummary(entry) {
  const parts = []
  for (const child of children.value) {
    if (child.data_type === 'Composite') continue
    const val = entry.children?.[child.id]
    if (val !== null && val !== undefined && val !== '') {
      parts.push(String(val))
    }
  }
  return parts.length > 0 ? parts.join(' · ') : null
}
</script>

<template>
  <div class="space-y-1.5">
    <div
      v-for="(entry, index) in entries"
      :key="entry.multiplied_index"
      class="border border-[var(--color-border)] rounded-lg overflow-hidden group/instance"
    >
      <!-- Instance Header -->
      <div
        class="flex items-center gap-1.5 px-2.5 py-1.5 bg-[var(--color-bg)] cursor-pointer select-none"
        @click="toggleExpand(entry.multiplied_index)"
      >
        <ChevronRight
          class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] transition-transform"
          :class="{ 'rotate-90': expandedInstances.has(entry.multiplied_index) }"
          :stroke-width="2"
        />
        <span class="text-[11px] font-mono text-[var(--color-text-tertiary)] tabular-nums min-w-[16px]">
          {{ index + 1 }}
        </span>
        <span class="flex-1 text-[12px] text-[var(--color-text-secondary)] truncate">
          {{ getFormatPreview(entry) || getInstanceSummary(entry) || 'Leer' }}
        </span>

        <!-- Reorder + Delete -->
        <div v-if="!disabled" class="flex items-center gap-0.5 opacity-0 group-hover/instance:opacity-100 transition-opacity" @click.stop>
          <button
            v-if="entries.length > 1"
            type="button"
            class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] disabled:opacity-30"
            :disabled="index === 0"
            @click="moveUp(index)"
            title="Nach oben"
          >
            <ChevronUp class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            v-if="entries.length > 1"
            type="button"
            class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)] disabled:opacity-30"
            :disabled="index === entries.length - 1"
            @click="moveDown(index)"
            title="Nach unten"
          >
            <ChevronDown class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            type="button"
            class="p-0.5 text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
            @click="removeEntry(index)"
            title="Instanz entfernen"
          >
            <Trash2 class="w-3 h-3" :stroke-width="1.75" />
          </button>
        </div>
      </div>

      <!-- Instance Body (expanded) -->
      <div v-if="expandedInstances.has(entry.multiplied_index)" class="px-3 py-2.5 space-y-2.5 border-t border-[var(--color-border)]">
        <template v-for="child in children" :key="child.id">
          <!-- Sub-Composite: inline Fieldset -->
          <fieldset
            v-if="child.data_type === 'Composite'"
            class="border border-dashed border-[var(--color-border)] rounded-md px-3 py-2 space-y-2"
          >
            <legend class="text-[11px] font-medium text-[var(--color-text-tertiary)] px-1">
              {{ child.name_de || child.technical_name }}
            </legend>
            <div v-for="gc in (child._children || [])" :key="gc.id">
              <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-0.5">
                {{ gc.name_de || gc.technical_name }}
                <span v-if="gc.is_mandatory" class="text-[var(--color-error)]">*</span>
              </label>
              <PimAttributeInput
                :type="mapType(gc.data_type)"
                :modelValue="entry.children?.[child.id]?.children?.[gc.id] ?? null"
                :disabled="disabled"
                @update:modelValue="updateSubCompositeChildValue(index, child.id, gc.id, $event)"
              />
            </div>
          </fieldset>

          <!-- Einfaches Kind-Attribut -->
          <div v-else>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-0.5">
              {{ child.name_de || child.technical_name }}
              <span v-if="child.is_mandatory" class="text-[var(--color-error)]">*</span>
            </label>
            <PimAttributeInput
              :type="mapType(child.data_type)"
              :modelValue="entry.children?.[child.id] ?? null"
              :disabled="disabled"
              @update:modelValue="updateChildValue(index, child.id, $event)"
            />
          </div>
        </template>
      </div>
    </div>

    <!-- Add button -->
    <button
      v-if="canAdd"
      type="button"
      class="flex items-center gap-1 text-[11px] text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] transition-colors py-0.5"
      @click="addEntry"
    >
      <Plus class="w-3.5 h-3.5" :stroke-width="2" />
      <span>Weitere Instanz hinzufügen</span>
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
