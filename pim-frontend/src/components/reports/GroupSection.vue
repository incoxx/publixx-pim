<script setup>
import { useReportDesignerStore } from '@/stores/reportDesigner'
import ElementCard from './ElementCard.vue'
import { ref } from 'vue'

const props = defineProps({
  groupId: { type: String, required: true },
  section: { type: String, required: true },
  elements: { type: Array, default: () => [] },
})

const store = useReportDesignerStore()
const dragOver = ref(false)

function onDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'copy'
  dragOver.value = true
}

function onDragLeave() {
  dragOver.value = false
}

function onDrop(e) {
  e.preventDefault()
  dragOver.value = false

  const json = e.dataTransfer.getData('application/json')
  if (!json) return

  try {
    const item = JSON.parse(json)
    store.addElement(props.groupId, props.section, item)
  } catch (err) {
    // ignore invalid drops
  }
}

function removeElement(elementId) {
  store.removeElement(props.groupId, props.section, elementId)
}

function selectElement(elementId) {
  store.selectElement(elementId, props.groupId, props.section)
}
</script>

<template>
  <div
    class="min-h-[32px] rounded border border-dashed transition-colors"
    :class="dragOver
      ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]'
      : 'border-[var(--color-border)]'"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
  >
    <div v-if="!elements.length" class="flex items-center justify-center h-8 text-[10px] text-[var(--color-text-tertiary)]">
      Felder hierher ziehen
    </div>
    <div v-else class="p-1 space-y-0.5">
      <ElementCard
        v-for="el in elements"
        :key="el.id"
        :element="el"
        :selected="store.selectedElementId === el.id"
        @click="selectElement(el.id)"
        @remove="removeElement(el.id)"
      />
    </div>
  </div>
</template>
