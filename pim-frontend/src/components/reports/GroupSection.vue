<script setup>
import { useReportDesignerStore } from '@/stores/reportDesigner'
import ElementCard from './ElementCard.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  groupId: { type: String, required: true },
  section: { type: String, required: true },
  elements: { type: Array, default: () => [] },
})

const store = useReportDesignerStore()
const dragOver = ref(false)
const dragOverIndex = ref(-1)
const draggingIndex = ref(-1)

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

  // Internal reorder
  if (draggingIndex.value >= 0) {
    const dropIndex = getDropIndex(e)
    if (dropIndex >= 0 && dropIndex !== draggingIndex.value) {
      store.moveElement(props.groupId, props.section, draggingIndex.value, dropIndex)
    }
    draggingIndex.value = -1
    return
  }

  // External drop from palette
  const json = e.dataTransfer.getData('application/json')
  if (!json) return

  try {
    const item = JSON.parse(json)
    store.addElement(props.groupId, props.section, item)
  } catch (err) {
    // ignore invalid drops
  }
}

function getDropIndex(e) {
  const container = e.currentTarget
  const cards = container.querySelectorAll('[data-element-index]')
  for (const card of cards) {
    const rect = card.getBoundingClientRect()
    const midY = rect.top + rect.height / 2
    if (e.clientY < midY) {
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
  store.selectElement(elementId, props.groupId, props.section)
}

function focusSection() {
  store.setFocusedSection(props.groupId, props.section)
}

const isFocused = computed(() =>
  store.focusedSection.groupId === props.groupId && store.focusedSection.section === props.section
)
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
      {{ isFocused ? 'Doppelklick auf Feld zum Hinzufügen' : 'Klicken zum Fokussieren oder Felder hierher ziehen' }}
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
        <ElementCard
          :element="el"
          :selected="store.selectedElementId === el.id"
          @click="selectElement(el.id)"
          @remove="removeElement(el.id)"
        />
      </div>
    </div>
  </div>
</template>
