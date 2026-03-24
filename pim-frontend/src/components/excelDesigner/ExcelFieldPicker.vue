<script setup>
import { ref, computed } from 'vue'
import { X, Hash, Tag, DollarSign, Image, Link2, GitBranch, Type, Square } from 'lucide-vue-next'
import { useExcelDesignerStore } from '@/stores/excelDesigner'

const emit = defineEmits(['close'])
const store = useExcelDesignerStore()
const searchQuery = ref('')

// Felder laden wenn nicht vorhanden
if (!store.availableFields) store.loadFields()

const filteredFields = computed(() => {
  const q = searchQuery.value.toLowerCase()
  const fields = store.availableFields
  if (!fields) return null

  const filterFn = (item) => {
    if (!q) return true
    return (item.label_de || '').toLowerCase().includes(q)
      || (item.technical_name || '').toLowerCase().includes(q)
      || (item.field || '').toLowerCase().includes(q)
  }

  return {
    base_fields: (fields.base_fields || []).filter(filterFn),
    attributes: (fields.attributes || []).filter(filterFn),
    price_types: (fields.price_types || []).filter(filterFn),
    media_usage_types: (fields.media_usage_types || []).filter(filterFn),
    relation_types: (fields.relation_types || []).filter(filterFn),
  }
})

// Attributgruppen
const attributeGroups = computed(() => {
  if (!filteredFields.value) return []
  const groups = {}
  for (const attr of filteredFields.value.attributes) {
    const group = attr.group_de || 'Sonstige'
    if (!groups[group]) groups[group] = []
    groups[group].push(attr)
  }
  return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
})

function createDragData(item) {
  return JSON.stringify(item)
}

function onDragStart(e, item) {
  e.dataTransfer.effectAllowed = 'copy'
  e.dataTransfer.setData('application/json', createDragData(item))
}

function addToSheet(item) {
  if (!store.selectedSheetId) return
  store.addColumn(store.selectedSheetId, item)
}

function makeFieldColumn(field) {
  return {
    type: 'field',
    label: field.label_de,
    field: field.field,
    dataType: 'string',
    width: null,
  }
}

function makeAttributeColumn(attr) {
  return {
    type: 'attribute',
    label: attr.label_de,
    attributeId: attr.attributeId,
    includeUnit: false,
    dataType: attr.data_type === 'Number' ? 'number' : 'string',
    width: null,
  }
}

function makePriceColumn(pt) {
  return {
    type: 'price',
    label: pt.label_de,
    priceType: pt.priceType,
    dataType: 'number',
    width: null,
  }
}

function makeImageColumn(mut) {
  return {
    type: 'image',
    label: mut.label_de,
    mediaUsageType: mut.mediaUsageType,
    renderMode: 'embedded',
    thumbnailHeight: 50,
    width: null,
  }
}

function makeRelationColumn(rt) {
  return {
    type: 'relation',
    label: rt.label_de,
    relationType: rt.relationType,
    displayField: 'sku',
    arrayMode: 'join',
    width: null,
  }
}

function makeStaticColumn() {
  return {
    type: 'static',
    label: 'Neue Spalte',
    defaultValue: '',
    dataType: 'string',
    width: null,
  }
}
</script>

<template>
  <div class="p-3 space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-xs font-semibold text-[var(--color-text-secondary)] uppercase tracking-wide">Felder</h3>
      <button class="p-1 text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]" @click="emit('close')">
        <X class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>

    <input
      v-model="searchQuery"
      class="pim-input text-xs w-full"
      placeholder="Felder suchen..."
    />

    <p v-if="!store.selectedSheetId" class="text-[11px] text-amber-600 dark:text-amber-400">
      Erst ein Sheet auswählen, dann Felder hinzufügen.
    </p>

    <div v-if="store.fieldsLoading" class="text-center py-4 text-xs text-[var(--color-text-tertiary)]">Lade Felder...</div>

    <div v-else-if="filteredFields" class="space-y-4 text-xs">
      <!-- Stammfelder -->
      <div v-if="filteredFields.base_fields.length">
        <div class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1.5 flex items-center gap-1">
          <Hash class="w-3 h-3" :stroke-width="2" /> Stammfelder
        </div>
        <div class="space-y-0.5">
          <div
            v-for="f in filteredFields.base_fields"
            :key="f.field"
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, makeFieldColumn(f))"
            @dblclick="addToSheet(makeFieldColumn(f))"
          >
            <span class="text-[var(--color-text-primary)]">{{ f.label_de }}</span>
            <span class="text-[var(--color-text-tertiary)] text-[10px]">{{ f.field }}</span>
          </div>
        </div>
      </div>

      <!-- Preise -->
      <div v-if="filteredFields.price_types.length">
        <div class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1.5 flex items-center gap-1">
          <DollarSign class="w-3 h-3" :stroke-width="2" /> Preise
        </div>
        <div class="space-y-0.5">
          <div
            v-for="pt in filteredFields.price_types"
            :key="pt.priceType"
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, makePriceColumn(pt))"
            @dblclick="addToSheet(makePriceColumn(pt))"
          >
            <span class="text-[var(--color-text-primary)]">{{ pt.label_de }}</span>
          </div>
        </div>
      </div>

      <!-- Medien / Bilder -->
      <div v-if="filteredFields.media_usage_types.length">
        <div class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1.5 flex items-center gap-1">
          <Image class="w-3 h-3" :stroke-width="2" /> Bilder / Medien
        </div>
        <div class="space-y-0.5">
          <div
            v-for="mut in filteredFields.media_usage_types"
            :key="mut.mediaUsageType"
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, makeImageColumn(mut))"
            @dblclick="addToSheet(makeImageColumn(mut))"
          >
            <span class="text-[var(--color-text-primary)]">{{ mut.label_de }}</span>
          </div>
        </div>
      </div>

      <!-- Relationen -->
      <div v-if="filteredFields.relation_types.length">
        <div class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1.5 flex items-center gap-1">
          <Link2 class="w-3 h-3" :stroke-width="2" /> Relationen
        </div>
        <div class="space-y-0.5">
          <div
            v-for="rt in filteredFields.relation_types"
            :key="rt.relationType"
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, makeRelationColumn(rt))"
            @dblclick="addToSheet(makeRelationColumn(rt))"
          >
            <span class="text-[var(--color-text-primary)]">{{ rt.label_de }}</span>
          </div>
        </div>
      </div>

      <!-- Attribute (gruppiert) -->
      <div v-for="[groupName, attrs] in attributeGroups" :key="groupName">
        <div class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1.5 flex items-center gap-1">
          <Tag class="w-3 h-3" :stroke-width="2" /> {{ groupName }}
        </div>
        <div class="space-y-0.5">
          <div
            v-for="attr in attrs"
            :key="attr.attributeId"
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, makeAttributeColumn(attr))"
            @dblclick="addToSheet(makeAttributeColumn(attr))"
          >
            <span class="text-[var(--color-text-primary)]">{{ attr.label_de }}</span>
            <span class="text-[var(--color-text-tertiary)] text-[10px]">{{ attr.data_type }}</span>
          </div>
        </div>
      </div>

      <!-- Statische Spalte -->
      <div>
        <div class="text-[11px] font-semibold text-[var(--color-text-tertiary)] mb-1.5 flex items-center gap-1">
          <Square class="w-3 h-3" :stroke-width="2" /> Sonstiges
        </div>
        <div class="space-y-0.5">
          <div
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, makeStaticColumn())"
            @dblclick="addToSheet(makeStaticColumn())"
          >
            <span class="text-[var(--color-text-primary)]">Statische / Leere Spalte</span>
          </div>
          <div
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, { type: 'text', label: 'Text', content: '', dataType: 'string', width: null })"
            @dblclick="addToSheet({ type: 'text', label: 'Text', content: '', dataType: 'string', width: null })"
          >
            <span class="text-[var(--color-text-primary)]">Dynamischer Text</span>
            <span class="text-[var(--color-text-tertiary)] text-[10px]">{sku}-{ean}</span>
          </div>
          <div
            class="px-2 py-1 rounded hover:bg-[var(--color-bg)] cursor-grab flex items-center gap-2"
            draggable="true"
            @dragstart="onDragStart($event, { type: 'variant', label: 'Varianten', displayField: 'sku', arrayMode: 'join', width: null })"
            @dblclick="addToSheet({ type: 'variant', label: 'Varianten', displayField: 'sku', arrayMode: 'join', width: null })"
          >
            <span class="text-[var(--color-text-primary)]">Varianten</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
