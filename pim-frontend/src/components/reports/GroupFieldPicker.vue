<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Search, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: String, default: 'none' },
  groupFields: { type: Array, default: () => [] },
  attributes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const searchQuery = ref('')
const panelRef = ref(null)

const standardFields = computed(() =>
  props.groupFields.filter(f => !f.field.startsWith('attribute:'))
)

const filteredAttributes = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return props.attributes
  return props.attributes.filter(a =>
    a.label_de?.toLowerCase().includes(q) ||
    a.label_en?.toLowerCase().includes(q) ||
    a.technical_name?.toLowerCase().includes(q)
  )
})

const attributesByGroup = computed(() => {
  const groups = {}
  for (const attr of filteredAttributes.value) {
    const group = attr.group_de || 'Sonstige'
    if (!groups[group]) groups[group] = []
    groups[group].push(attr)
  }
  return groups
})

const currentLabel = computed(() => {
  const v = props.modelValue
  if (!v || v === 'none') return 'Keine Gruppierung'
  const std = props.groupFields.find(f => f.field === v)
  if (std) return std.label_de
  if (v.startsWith('attribute:')) {
    const attrId = v.replace('attribute:', '')
    const attr = props.attributes.find(a => a.attributeId === attrId)
    return attr ? attr.label_de : v
  }
  return v
})

function select(value) {
  emit('update:modelValue', value)
  isOpen.value = false
  searchQuery.value = ''
}

function onClickOutside(e) {
  if (panelRef.value && !panelRef.value.contains(e.target)) {
    isOpen.value = false
    searchQuery.value = ''
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="panelRef" class="relative">
    <!-- Trigger -->
    <button
      class="pim-input text-xs w-full flex items-center justify-between gap-1 text-left"
      @click="isOpen = !isOpen"
    >
      <span class="truncate">{{ currentLabel }}</span>
      <ChevronDown class="w-3 h-3 shrink-0 text-[var(--color-text-tertiary)]" :stroke-width="2" />
    </button>

    <!-- Dropdown Panel -->
    <div
      v-if="isOpen"
      class="absolute z-50 top-full left-0 mt-1 w-64 max-h-[320px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg overflow-hidden flex flex-col"
    >
      <!-- Search -->
      <div class="p-2 border-b border-[var(--color-border)]">
        <div class="relative">
          <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <input
            v-model="searchQuery"
            class="pim-input text-[11px] w-full pl-7"
            placeholder="Feld suchen..."
            @click.stop
            ref="searchInput"
          />
        </div>
      </div>

      <div class="overflow-y-auto flex-1">
        <!-- Standard Fields -->
        <div v-if="!searchQuery" class="p-1">
          <div class="px-2 py-1 text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase">Standardfelder</div>
          <button
            v-for="f in standardFields"
            :key="f.field"
            class="w-full text-left px-2 py-1.5 rounded text-[11px] flex items-center gap-2 transition-colors"
            :class="modelValue === f.field
              ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)] font-medium'
              : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
            @click="select(f.field)"
          >
            <span class="w-3 h-3 rounded-full border shrink-0 flex items-center justify-center"
              :class="modelValue === f.field ? 'border-[var(--color-accent)] bg-[var(--color-accent)]' : 'border-[var(--color-border)]'"
            >
              <span v-if="modelValue === f.field" class="w-1.5 h-1.5 rounded-full bg-white"></span>
            </span>
            {{ f.label_de }}
          </button>
        </div>

        <!-- Attributes -->
        <div v-for="(attrs, groupName) in attributesByGroup" :key="groupName" class="p-1">
          <div class="px-2 py-1 text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase">{{ groupName }}</div>
          <button
            v-for="attr in attrs"
            :key="attr.attributeId"
            class="w-full text-left px-2 py-1.5 rounded text-[11px] flex items-center gap-2 transition-colors"
            :class="modelValue === 'attribute:' + attr.attributeId
              ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)] font-medium'
              : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
            @click="select('attribute:' + attr.attributeId)"
          >
            <span class="w-3 h-3 rounded-full border shrink-0 flex items-center justify-center"
              :class="modelValue === 'attribute:' + attr.attributeId ? 'border-[var(--color-accent)] bg-[var(--color-accent)]' : 'border-[var(--color-border)]'"
            >
              <span v-if="modelValue === 'attribute:' + attr.attributeId" class="w-1.5 h-1.5 rounded-full bg-white"></span>
            </span>
            <span class="truncate">{{ attr.label_de }}</span>
          </button>
        </div>

        <!-- No results -->
        <div v-if="searchQuery && Object.keys(attributesByGroup).length === 0 && standardFields.length === 0" class="p-4 text-center text-[11px] text-[var(--color-text-tertiary)]">
          Keine Ergebnisse
        </div>
      </div>
    </div>
  </div>
</template>
