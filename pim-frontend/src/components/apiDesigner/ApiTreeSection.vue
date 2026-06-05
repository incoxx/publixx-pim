<script setup>
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { ref, computed } from 'vue'
import { X, GripVertical, Hash, Tag, Type } from 'lucide-vue-next'

const props = defineProps({
  groupId: { type: String, required: true },
  section: { type: String, required: true },
  elements: { type: Array, default: () => [] },
})

const store = useApiDesignerStore()
const dragOver = ref(false)
const dragOverIndex = ref(-1)
const draggingIndex = ref(-1)
const editingId = ref(null)

function onDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = draggingIndex.value >= 0 ? 'move' : 'copy'
  dragOver.value = true
}

function onDragLeave() {
  dragOver.value = false
  dragOverIndex.value = -1
}

function onDrop(e) {
  e.preventDefault()
  dragOver.value = false
  dragOverIndex.value = -1

  if (draggingIndex.value >= 0) {
    const dropIndex = getDropIndex(e)
    if (dropIndex >= 0 && dropIndex !== draggingIndex.value) {
      store.moveElement(props.groupId, props.section, draggingIndex.value, dropIndex)
    }
    draggingIndex.value = -1
    return
  }

  const json = e.dataTransfer.getData('application/json')
  if (!json) return

  try {
    const item = JSON.parse(json)
    // Add jsonKey based on field name
    if (!item.jsonKey) {
      item.jsonKey = item.field || item.technical_name || item.label?.toLowerCase().replace(/\s+/g, '_') || 'field'
    }
    item.dataType = item.dataType || 'string'
    store.addElement(props.groupId, props.section, item)
  } catch (err) { /* ignore */ }
}

function getDropIndex(e) {
  const container = e.currentTarget
  const cards = container.querySelectorAll('[data-element-index]')
  for (const card of cards) {
    const rect = card.getBoundingClientRect()
    if (e.clientY < rect.top + rect.height / 2) {
      return parseInt(card.dataset.elementIndex)
    }
  }
  return props.elements.length - 1
}

function onElementDragStart(e, index) {
  draggingIndex.value = index
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(index))
}

function onElementDragEnd() {
  draggingIndex.value = -1
  dragOverIndex.value = -1
}

function onElementDragOver(e, index) {
  e.preventDefault()
  e.stopPropagation()
  dragOverIndex.value = index
}

function removeElement(elementId) {
  store.removeElement(props.groupId, props.section, elementId)
}

function selectElement(elementId) {
  store.selectElement(elementId, props.groupId)
  editingId.value = editingId.value === elementId ? null : elementId
}

function updateElement(elementId, key, value) {
  store.updateElement(props.groupId, props.section, elementId, { [key]: value })
}

function focusSection() {
  store.setFocusedSection(props.groupId, props.section)
}

const isFocused = computed(() =>
  store.focusedSection.groupId === props.groupId && store.focusedSection.section === props.section
)

function getTypeIcon(type) {
  return { field: Hash, attribute: Tag, text: Type }[type] || Hash
}
</script>

<template>
  <div
    class="min-h-[32px] rounded border transition-colors"
    :class="[
      dragOver
        ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]'
        : isFocused
          ? 'border-[var(--color-accent)] border-solid'
          : 'border-[var(--color-border)] border-dashed',
    ]"
    @click.self="focusSection"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
  >
    <div v-if="!elements.length" class="flex items-center justify-center h-8 text-[10px] text-[var(--color-text-tertiary)] cursor-pointer" @click="focusSection">
      Felder hierher ziehen
    </div>
    <div v-else class="p-1 space-y-0.5">
      <div
        v-for="(el, index) in elements"
        :key="el.id"
        :data-element-index="index"
        draggable="true"
        class="transition-transform"
        :class="{
          'opacity-40': draggingIndex === index,
          'border-t-2 border-[var(--color-accent)]': dragOverIndex === index && draggingIndex >= 0 && draggingIndex !== index,
        }"
        @dragstart="onElementDragStart($event, index)"
        @dragend="onElementDragEnd"
        @dragover="onElementDragOver($event, index)"
      >
        <!-- Element Card -->
        <div
          class="flex items-center gap-1.5 px-2 py-1 rounded text-[11px] cursor-pointer transition-colors"
          :class="store.selectedElementId === el.id
            ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] ring-1 ring-[var(--color-accent)]'
            : 'hover:bg-[var(--color-bg)]'"
          @click="selectElement(el.id)"
        >
          <GripVertical class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0 cursor-grab" :stroke-width="1.5" />
          <component :is="getTypeIcon(el.type)" class="w-3 h-3 shrink-0" :class="el.type === 'attribute' ? 'text-emerald-500' : 'text-[var(--color-accent)]'" :stroke-width="2" />
          <span class="font-mono text-[var(--color-text-secondary)] truncate">{{ el.jsonKey || el.field || '...' }}</span>
          <span class="text-[9px] text-[var(--color-text-tertiary)] shrink-0">{{ el.dataType || 'string' }}</span>
          <span
            class="text-[9px] font-medium shrink-0 px-1 rounded"
            :class="{
              'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400': (el.access || 'readwrite') === 'read',
              'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400': (el.access || 'readwrite') === 'write',
              'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400': (el.access || 'readwrite') === 'readwrite',
            }"
          >{{ (el.access || 'readwrite') === 'readwrite' ? 'R+W' : (el.access || 'readwrite').toUpperCase() }}</span>
          <span class="text-[9px] text-[var(--color-text-tertiary)] truncate flex-1">{{ el.label || '' }}</span>
          <button
            class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] shrink-0"
            @click.stop="removeElement(el.id)"
          >
            <X class="w-3 h-3" :stroke-width="2" />
          </button>
        </div>

        <!-- Inline Edit (when selected) -->
        <div v-if="editingId === el.id" class="px-2 py-1.5 bg-[var(--color-bg)] rounded mt-0.5 space-y-1.5" @click.stop>
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">JSON-Key</label>
              <input
                :value="el.jsonKey || el.field || ''"
                class="pim-input text-[11px] w-full font-mono"
                placeholder="json_key"
                @input="updateElement(el.id, 'jsonKey', $event.target.value)"
              />
            </div>
            <div class="w-20">
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Datentyp</label>
              <select
                :value="el.dataType || 'string'"
                class="pim-input text-[11px] w-full"
                @change="updateElement(el.id, 'dataType', $event.target.value)"
              >
                <option value="string">string</option>
                <option value="number">number</option>
                <option value="integer">integer</option>
                <option value="boolean">boolean</option>
              </select>
            </div>
          </div>
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="block text-[9px] text-[var(--color-text-tertiary)] mb-0.5">Zugriff</label>
              <select
                :value="el.access || 'readwrite'"
                class="pim-input text-[11px] w-full"
                @change="updateElement(el.id, 'access', $event.target.value)"
              >
                <option value="readwrite">Lesen + Schreiben</option>
                <option value="read">Nur Lesen</option>
                <option value="write">Nur Schreiben</option>
              </select>
            </div>
          </div>
          <div v-if="el.type === 'attribute'" class="flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-[10px] cursor-pointer text-[var(--color-text-secondary)]">
              <input type="checkbox" :checked="el.includeUnit" class="rounded" @change="updateElement(el.id, 'includeUnit', $event.target.checked)" />
              Einheit
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
