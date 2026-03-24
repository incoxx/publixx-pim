<script setup>
import { ref, computed } from 'vue'
import { X, Search, ChevronUp, ChevronDown, GripVertical } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const searchQuery = ref('')
const showDropdown = ref(false)

const selectedOptions = computed(() => {
  return props.modelValue
    .map(id => props.options.find(o => o.value === id))
    .filter(Boolean)
})

const filteredOptions = computed(() => {
  const selected = new Set(props.modelValue)
  return props.options
    .filter(o => !selected.has(o.value))
    .filter(o => {
      if (!searchQuery.value) return true
      const q = searchQuery.value.toLowerCase()
      return (o.label || '').toLowerCase().includes(q)
        || (o.technical_name || '').toLowerCase().includes(q)
    })
})

function placeholderKey(opt) {
  const tn = opt.technical_name || ''
  const lastDash = tn.lastIndexOf('-')
  return lastDash >= 0 ? tn.substring(lastDash + 1) : tn
}

function addChild(id) {
  emit('update:modelValue', [...props.modelValue, id])
  searchQuery.value = ''
  showDropdown.value = false
}

function removeChild(id) {
  emit('update:modelValue', props.modelValue.filter(v => v !== id))
}

function moveUp(index) {
  if (index <= 0) return
  const arr = [...props.modelValue]
  ;[arr[index - 1], arr[index]] = [arr[index], arr[index - 1]]
  emit('update:modelValue', arr)
}

function moveDown(index) {
  if (index >= props.modelValue.length - 1) return
  const arr = [...props.modelValue]
  ;[arr[index], arr[index + 1]] = [arr[index + 1], arr[index]]
  emit('update:modelValue', arr)
}

function onSearchFocus() {
  showDropdown.value = true
}

function onSearchBlur() {
  setTimeout(() => { showDropdown.value = false }, 200)
}
</script>

<template>
  <div class="space-y-2">
    <!-- Ausgewählte Kind-Attribute -->
    <div v-if="selectedOptions.length" class="space-y-1">
      <div
        v-for="(opt, idx) in selectedOptions"
        :key="opt.value"
        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-bg)] group"
      >
        <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.5" />
        <div class="flex-1 min-w-0">
          <span class="text-[12px] text-[var(--color-text-primary)]">{{ opt.label }}</span>
          <span class="ml-1.5 text-[10px] text-[var(--color-text-tertiary)] font-mono">{<span class="text-[var(--color-accent)]">{{ placeholderKey(opt) }}</span>}</span>
        </div>
        <div class="flex items-center gap-0.5 shrink-0">
          <button
            type="button"
            class="p-0.5 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)] transition-colors"
            :disabled="idx === 0 || disabled"
            @click="moveUp(idx)"
          >
            <ChevronUp class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button
            type="button"
            class="p-0.5 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)] transition-colors"
            :disabled="idx === selectedOptions.length - 1 || disabled"
            @click="moveDown(idx)"
          >
            <ChevronDown class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button
            type="button"
            class="p-0.5 rounded hover:bg-red-100 text-[var(--color-text-tertiary)] hover:text-red-600 transition-colors ml-1"
            :disabled="disabled"
            @click="removeChild(opt.value)"
          >
            <X class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>
      </div>
    </div>

    <!-- Suchfeld zum Hinzufügen -->
    <div v-if="!disabled" class="relative">
      <div class="relative">
        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <input
          v-model="searchQuery"
          type="text"
          class="pim-input text-[12px] w-full !pl-8"
          placeholder="Attribut hinzufügen…"
          @focus="onSearchFocus"
          @blur="onSearchBlur"
        />
      </div>

      <!-- Dropdown -->
      <div
        v-if="showDropdown && filteredOptions.length"
        class="absolute z-20 w-full mt-1 max-h-48 overflow-y-auto rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg"
      >
        <button
          v-for="opt in filteredOptions"
          :key="opt.value"
          type="button"
          class="w-full px-3 py-1.5 text-left hover:bg-[var(--color-bg)] transition-colors flex items-center justify-between gap-2"
          @mousedown.prevent="addChild(opt.value)"
        >
          <span class="text-[12px] text-[var(--color-text-primary)] truncate">{{ opt.label }}</span>
          <span class="text-[10px] text-[var(--color-text-tertiary)] font-mono shrink-0">{{ opt.technical_name }}</span>
        </button>
      </div>
      <div
        v-else-if="showDropdown && searchQuery && !filteredOptions.length"
        class="absolute z-20 w-full mt-1 px-3 py-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg"
      >
        <span class="text-[11px] text-[var(--color-text-tertiary)]">Keine passenden Attribute gefunden</span>
      </div>
    </div>
  </div>
</template>
