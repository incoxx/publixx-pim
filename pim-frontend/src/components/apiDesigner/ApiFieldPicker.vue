<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { X, Search, Hash, Tag, GripVertical, ChevronsDown, CircleDollarSign, Image, Link } from 'lucide-vue-next'

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
  if (!requireFocus()) return
  const { groupId, section } = store.focusedSection
  if (!item.jsonKey) {
    item.jsonKey = item.field || item.technical_name || item.label?.toLowerCase().replace(/\s+/g, '_') || 'field'
  }
  item.dataType = item.dataType || 'string'
  store.addElement(groupId, section, item)
}

function addAllAttrs(attrs) {
  const { groupId, section } = store.focusedSection
  if (!groupId || !section) return
  for (const attr of attrs) {
    store.addElement(groupId, section, {
      type: 'attribute',
      attributeId: attr.attributeId,
      label: attr.label_de,
      jsonKey: attr.technical_name,
      dataType: 'string',
    })
  }
}

function onDoubleClickPrice(pt) {
  if (!requireFocus()) return
  const { groupId, section } = store.focusedSection
  store.addElement(groupId, section, {
    type: 'price',
    priceTypeId: pt.priceTypeId,
    label: pt.label_de,
    jsonKey: pt.technical_name,
  })
}

function onDoubleClickMedia(mt, mode = 'url') {
  if (!requireFocus()) return
  const { groupId, section } = store.focusedSection
  store.addElement(groupId, section, {
    type: 'media',
    usageTypeId: mt.usageTypeId,
    label: mt.label_de,
    jsonKey: mt.technical_name,
    mediaMode: mode,
  })
}

function onDoubleClickRelation(rt) {
  if (!requireFocus()) return
  const { groupId, section } = store.focusedSection
  store.addElement(groupId, section, {
    type: 'relation',
    relationTypeId: rt.relationTypeId,
    label: rt.label_de,
    jsonKey: rt.technical_name,
  })
}

function addAllBaseFields() {
  const { groupId, section } = store.focusedSection
  if (!groupId || !section) return
  for (const field of store.availableFields?.base_fields ?? []) {
    store.addElement(groupId, section, {
      type: 'field',
      field: field.field,
      label: field.label_de,
      jsonKey: field.field,
      dataType: 'string',
    })
  }
}

const hasFocus = computed(() => !!store.focusedSection.groupId)
const noFocusMsg = ref(false)

function requireFocus(): boolean {
  if (hasFocus.value) return true
  noFocusMsg.value = true
  setTimeout(() => { noFocusMsg.value = false }, 2500)
  return false
}

function addAllBaseFieldsGuarded() {
  if (requireFocus()) addAllBaseFields()
}

function addAllAttrsGuarded(attrs) {
  if (requireFocus()) addAllAttrs(attrs)
}
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
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <input
            v-model="searchQuery"
            class="pim-input text-[11px] w-full pim-input-icon"
            placeholder="Felder suchen..."
            autofocus
          />
        </div>
        <p v-if="noFocusMsg" class="text-[9px] text-amber-500 mt-1 font-medium">↑ Bitte erst eine Sektion im Baum anklicken.</p>
        <p v-else-if="!hasFocus" class="text-[9px] text-[var(--color-text-tertiary)] mt-1">Sektion anklicken, dann Doppelklick oder Drag & Drop.</p>
      </div>

      <!-- Scrollable list -->
      <div class="overflow-y-auto flex-1 px-3 pb-3 space-y-2">
        <!-- Base Fields -->
        <div>
          <div class="flex items-center gap-1">
            <button
              class="text-[11px] font-semibold text-[var(--color-text-secondary)] flex-1 text-left py-1 hover:text-[var(--color-text-primary)]"
              @click="toggleGroup('base')"
            >
              {{ expandedGroups.base ? '▾' : '▸' }} Grunddaten
            </button>
            <button
              class="shrink-0 flex items-center gap-0.5 text-[9px] text-[var(--color-accent)] hover:opacity-70 px-1 py-0.5 rounded"
              title="Alle Grunddaten hinzufügen"
              @click.stop="addAllBaseFieldsGuarded"
            >
              <ChevronsDown class="w-3 h-3" :stroke-width="2" />
              alle
            </button>
          </div>
          <div v-if="expandedGroups.base && store.availableFields?.base_fields" class="space-y-0.5">
            <div
              v-for="field in store.availableFields.base_fields"
              :key="field.field"
              class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
              class="cursor-pointer"
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
          <div class="flex items-center gap-1">
            <button
              class="text-[11px] font-semibold text-[var(--color-text-secondary)] flex-1 text-left py-1 hover:text-[var(--color-text-primary)]"
              @click="toggleGroup(groupName)"
            >
              {{ expandedGroups[groupName] ? '▾' : '▸' }} {{ groupName }}
            </button>
            <button
              class="shrink-0 flex items-center gap-0.5 text-[9px] text-[var(--color-accent)] hover:opacity-70 px-1 py-0.5 rounded"
              :title="`Alle ${attrs.length} Attribute aus '${groupName}' hinzufügen`"
              @click.stop="addAllAttrsGuarded(attrs)"
            >
              <ChevronsDown class="w-3 h-3" :stroke-width="2" />
              alle {{ attrs.length }}
            </button>
          </div>
          <div v-if="expandedGroups[groupName]" class="space-y-0.5">
            <div
              v-for="attr in attrs"
              :key="attr.attributeId"
              class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
              class="cursor-pointer"
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
        <!-- Price Types -->
        <div v-if="store.availableFields?.price_types?.length">
          <div class="flex items-center gap-1">
            <button
              class="text-[11px] font-semibold text-[var(--color-text-secondary)] flex-1 text-left py-1 hover:text-[var(--color-text-primary)]"
              @click="toggleGroup('prices')"
            >
              {{ expandedGroups.prices ? '▾' : '▸' }} Preise
            </button>
          </div>
          <div v-if="expandedGroups.prices" class="space-y-0.5">
            <div
              v-for="pt in store.availableFields.price_types"
              :key="pt.priceTypeId"
              class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
              class="cursor-pointer"
              draggable="true"
              @dragstart="onDragStart($event, { type: 'price', priceTypeId: pt.priceTypeId, label: pt.label_de, jsonKey: pt.technical_name })"
              @dblclick="onDoubleClickPrice(pt)"
              :title="pt.technical_name"
            >
              <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
              <CircleDollarSign class="w-3 h-3 text-amber-500 shrink-0" :stroke-width="2" />
              <span class="truncate">{{ pt.label_de }}</span>
              <span class="text-[9px] text-[var(--color-text-tertiary)] ml-auto font-mono">{{ pt.technical_name }}</span>
            </div>
          </div>
        </div>

        <!-- Media Usage Types -->
        <div v-if="store.availableFields?.media_usage_types?.length">
          <div class="flex items-center gap-1">
            <button
              class="text-[11px] font-semibold text-[var(--color-text-secondary)] flex-1 text-left py-1 hover:text-[var(--color-text-primary)]"
              @click="toggleGroup('media')"
            >
              {{ expandedGroups.media ? '▾' : '▸' }} Medien
            </button>
          </div>
          <div v-if="expandedGroups.media" class="space-y-0.5">
            <div
              v-for="mt in store.availableFields.media_usage_types"
              :key="mt.usageTypeId"
              class="flex flex-col px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
            >
              <div class="flex items-center gap-2">
                <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
                <Image class="w-3 h-3 text-sky-500 shrink-0" :stroke-width="2" />
                <span class="truncate flex-1">{{ mt.label_de }}</span>
                <span class="text-[9px] text-[var(--color-text-tertiary)] font-mono">{{ mt.technical_name }}</span>
              </div>
              <div class="flex gap-2 mt-0.5 ml-5">
                <button
                  class="text-[9px] text-sky-600 hover:underline cursor-pointer"
                  draggable="true"
                  @dragstart="onDragStart($event, { type: 'media', usageTypeId: mt.usageTypeId, label: mt.label_de, jsonKey: mt.technical_name, mediaMode: 'url' })"
                  @click="onDoubleClickMedia(mt, 'url')"
                >+ URL</button>
                <button
                  class="text-[9px] text-sky-600 hover:underline cursor-pointer"
                  draggable="true"
                  @dragstart="onDragStart($event, { type: 'media', usageTypeId: mt.usageTypeId, label: mt.label_de, jsonKey: mt.technical_name + '_gallery', mediaMode: 'array' })"
                  @click="onDoubleClickMedia(mt, 'array')"
                >+ Array</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Relation Types -->
        <div v-if="store.availableFields?.relation_types?.length">
          <div class="flex items-center gap-1">
            <button
              class="text-[11px] font-semibold text-[var(--color-text-secondary)] flex-1 text-left py-1 hover:text-[var(--color-text-primary)]"
              @click="toggleGroup('relations')"
            >
              {{ expandedGroups.relations ? '▾' : '▸' }} Beziehungen
            </button>
          </div>
          <div v-if="expandedGroups.relations" class="space-y-0.5">
            <div
              v-for="rt in store.availableFields.relation_types"
              :key="rt.relationTypeId"
              class="flex items-center gap-2 px-2 py-1 rounded text-[11px] hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)]"
              class="cursor-pointer"
              draggable="true"
              @dragstart="onDragStart($event, { type: 'relation', relationTypeId: rt.relationTypeId, label: rt.label_de, jsonKey: rt.technical_name })"
              @dblclick="onDoubleClickRelation(rt)"
              :title="rt.technical_name"
            >
              <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
              <Link class="w-3 h-3 text-purple-500 shrink-0" :stroke-width="2" />
              <span class="truncate">{{ rt.label_de }}</span>
              <span class="text-[9px] text-[var(--color-text-tertiary)] ml-auto font-mono">{{ rt.technical_name }}</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
