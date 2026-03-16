<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { X, Search, Hash, Tag, GripVertical } from 'lucide-vue-next'

const emit = defineEmits(['close'])
const store = useApiDesignerStore()
const searchQuery = ref('')
const expandedGroups = ref({ base: true })

const filteredAttributes = computed(() => {
  if (!store.availableFields?.attributes) return []
  const q = searchQuery.value.toLowerCase()
  if (!q) return store.availableFields.attributes
  return store.availableFields.attributes.filter(a =>
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

const isDragging = ref(false)

function onDragEnd() {
  isDragging.value = false
}

onMounted(() => document.addEventListener('dragend', onDragEnd))
onBeforeUnmount(() => document.removeEventListener('dragend', onDragEnd))

function onDragStart(event, item) {
  isDragging.value = true
  event.dataTransfer.setData('application/json', JSON.stringify(item))
  event.dataTransfer.effectAllowed = 'copy'
}

function toggleGroup(key) {
  expandedGroups.value[key] = !expandedGroups.value[key]
}

function onDoubleClick(item) {
  const { groupId, section } = store.focusedSection
  if (!groupId || !section) return
  if (!item.jsonKey) {
    item.jsonKey = item.field || item.technical_name || item.label?.toLowerCase().replace(/\s+/g, '_') || 'field'
  }
  item.dataType = item.dataType || 'string'
  store.addElement(groupId, section, item)
}

const hasFocus = computed(() => !!store.focusedSection.groupId)
</script>

<template>
  <!-- Overlay -->
  <div class="fixed inset-0 z-50 flex items-start justify-center pt-20" :class="isDragging ? 'pointer-events-none' : ''" @click.self="emit('close')">
    <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-xl w-80 max-h-[60vh] flex flex-col" :class="isDragging ? 'pointer-events-auto' : ''">
      <!-- Header -->
      <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)]">
        <span class="text-xs font-semibold text-[var(--color-text-primary)]">Felder hinzufügen</span>
        <button @click="emit('close')" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
          <X class="w-4 h-4" :stroke-width="2" />
        </button>
      </div>

      <!-- Search -->
      <div class="px-3 py-2">
        <div class="relative">
          <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <input
            v-model="searchQuery"
            class="pim-input text-[11px] w-full pl-7"
            placeholder="Felder suchen..."
            autofocus
          />
        </div>
        <p v-if="!hasFocus" class="text-[9px] text-amber-500 mt-1">Erst eine Sektion im Baum fokussieren (anklicken), dann Felder per Doppelklick oder Drag & Drop hinzufügen.</p>
      </div>

      <!-- Scrollable list -->
      <div class="overflow-y-auto flex-1 px-3 pb-3 space-y-2">
        <!-- Base Fields -->
        <div>
          <button
            class="text-[11px] font-semibold text-[var(--color-text-secondary)] w-full text-left py-1 hover:text-[var(--color-text-primary)]"
            @click="toggleGroup('base')"
          >
            {{ expandedGroups.base ? '▾' : '▸' }} Grunddaten
          </button>
          <div v-if="expandedGroups.base && store.availableFields?.base_fields" class="space-y-0.5">
            <div
              v-for="field in store.availableFields.base_fields"
              :key="field.field"
              class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
              :class="hasFocus ? 'cursor-pointer' : 'cursor-grab'"
              draggable="true"
              @dragstart="onDragStart($event, { type: 'field', field: field.field, label: field.label_de, jsonKey: field.field, dataType: 'string' })"
              @dblclick="onDoubleClick({ type: 'field', field: field.field, label: field.label_de, jsonKey: field.field, dataType: 'string' })"
            >
              <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
              <Hash class="w-3 h-3 text-[var(--color-accent)]" :stroke-width="2" />
              <span>{{ field.label_de }}</span>
              <span class="text-[9px] text-[var(--color-text-tertiary)] ml-auto font-mono">{{ field.field }}</span>
            </div>
          </div>
        </div>

        <!-- Attributes by Group -->
        <div v-for="(attrs, groupName) in attributesByGroup" :key="groupName">
          <button
            class="text-[11px] font-semibold text-[var(--color-text-secondary)] w-full text-left py-1 hover:text-[var(--color-text-primary)]"
            @click="toggleGroup(groupName)"
          >
            {{ expandedGroups[groupName] ? '▾' : '▸' }} {{ groupName }}
          </button>
          <div v-if="expandedGroups[groupName]" class="space-y-0.5">
            <div
              v-for="attr in attrs"
              :key="attr.attributeId"
              class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
              :class="hasFocus ? 'cursor-pointer' : 'cursor-grab'"
              draggable="true"
              @dragstart="onDragStart($event, { type: 'attribute', attributeId: attr.attributeId, label: attr.label_de, jsonKey: attr.technical_name, dataType: 'string' })"
              @dblclick="onDoubleClick({ type: 'attribute', attributeId: attr.attributeId, label: attr.label_de, jsonKey: attr.technical_name, dataType: 'string' })"
              :title="attr.technical_name"
            >
              <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
              <Tag class="w-3 h-3 text-emerald-500" :stroke-width="2" />
              <span class="truncate">{{ attr.label_de }}</span>
              <span class="text-[9px] text-[var(--color-text-tertiary)] ml-auto font-mono">{{ attr.technical_name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
