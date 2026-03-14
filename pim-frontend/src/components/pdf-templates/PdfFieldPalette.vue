<script setup>
import { ref, computed } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import {
  Type, Hash, Tag, Image, Square, Table2, GripVertical, Search, Network,
} from 'lucide-vue-next'

const store = usePdfTemplateDesignerStore()
const searchQuery = ref('')
const expandedGroups = ref({ base: true, layout: true })

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

function getIcon(type) {
  const icons = { text: Type, image: Image, shape: Square, variant_table: Table2, relation_table: Network }
  return icons[type] || Tag
}

function onDragStart(event, item) {
  event.dataTransfer.setData('application/json', JSON.stringify(item))
  event.dataTransfer.effectAllowed = 'copy'
}

function toggleGroup(key) {
  expandedGroups.value[key] = !expandedGroups.value[key]
}

function onDoubleClick(item) {
  // Add element at center of page
  const pw = store.templateJson.pageWidth || 210
  const ph = store.templateJson.pageHeight || 297
  store.addElement({
    ...item,
    x: pw / 2 - 30,
    y: ph / 2 - 5,
    width: 60,
    height: 10,
    style: {
      fontFamily: store.getDefaultFontFamily(),
      fontSize: 10,
      fontWeight: 'normal',
      fontStyle: 'normal',
      color: '#000000',
      textAlign: 'left',
    },
    ...(item.type === 'image' ? { width: 40, height: 40 } : {}),
    ...(item.type === 'shape' ? { width: 40, height: 20, style: { backgroundColor: '#f3f4f6', borderWidth: 1, borderColor: '#d1d5db' } } : {}),
  })
}
</script>

<template>
  <div class="p-3 space-y-3">
    <div class="text-xs font-semibold text-[var(--color-text-primary)] mb-1">Elemente</div>

    <!-- Search -->
    <div class="relative">
      <Search class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
      <input
        v-model="searchQuery"
        class="pim-input text-[11px] w-full pl-7"
        placeholder="Attribute suchen..."
      />
    </div>

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
          class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)] cursor-grab"
          draggable="true"
          @dragstart="onDragStart($event, { type: 'field', field: field.field, label: field.label_de })"
          @dblclick="onDoubleClick({ type: 'field', field: field.field, label: field.label_de, showLabel: false })"
        >
          <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
          <Hash class="w-3 h-3 text-[var(--color-accent)]" :stroke-width="2" />
          <span>{{ field.label_de }}</span>
        </div>
      </div>
    </div>

    <!-- Layout Elements -->
    <div>
      <button
        class="text-[11px] font-semibold text-[var(--color-text-secondary)] w-full text-left py-1 hover:text-[var(--color-text-primary)]"
        @click="toggleGroup('layout')"
      >
        {{ expandedGroups.layout ? '▾' : '▸' }} Layout
      </button>
      <div v-if="expandedGroups.layout && store.availableFields?.layout_elements" class="space-y-0.5">
        <div
          v-for="el in store.availableFields.layout_elements"
          :key="el.type"
          class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)] cursor-grab"
          draggable="true"
          @dragstart="onDragStart($event, { type: el.type, ...(el.type === 'text' ? { content: 'Text hier eingeben' } : {}), ...(el.type === 'image' ? { source: 'primary' } : {}), ...(el.type === 'relation_table' ? { columns: ['sku', 'name'], relationTypeId: null } : {}) })"
          @dblclick="onDoubleClick({ type: el.type, ...(el.type === 'text' ? { content: 'Text hier eingeben' } : {}), ...(el.type === 'image' ? { source: 'primary' } : {}), ...(el.type === 'relation_table' ? { columns: ['sku', 'name'], relationTypeId: null } : {}) })"
        >
          <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
          <component :is="getIcon(el.type)" class="w-3 h-3 text-[var(--color-accent)]" :stroke-width="2" />
          <span>{{ el.label_de }}</span>
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
          class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)] cursor-grab"
          draggable="true"
          @dragstart="onDragStart($event, { type: 'attribute', attributeId: attr.attributeId, label: attr.label_de, showLabel: true, showValue: true, showUnit: true })"
          @dblclick="onDoubleClick({ type: 'attribute', attributeId: attr.attributeId, label: attr.label_de, showLabel: true, showValue: true, showUnit: true })"
          :title="attr.technical_name"
        >
          <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
          <Tag class="w-3 h-3 text-emerald-500" :stroke-width="2" />
          <span class="truncate">{{ attr.label_de }}</span>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="store.fieldsLoading" class="text-center py-4 text-[var(--color-text-tertiary)] text-[11px]">
      Lade Felder...
    </div>
  </div>
</template>
