<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import PdfCanvasElement from './PdfCanvasElement.vue'

const store = usePdfTemplateDesignerStore()
const canvasContainer = ref(null)
const containerWidth = ref(600)

// A4 dimensions in mm
const pageWidth = computed(() => {
  const t = store.templateJson
  if (store.currentTemplate?.page_orientation === 'landscape') return t.pageHeight || 297
  return t.pageWidth || 210
})
const pageHeight = computed(() => {
  const t = store.templateJson
  if (store.currentTemplate?.page_orientation === 'landscape') return t.pageWidth || 210
  return t.pageHeight || 297
})

// Scale to fit container with some padding
const scale = computed(() => {
  const availableWidth = containerWidth.value - 40
  return Math.min(1, availableWidth / pageWidth.value) * (96 / 25.4) // mm to px at 96dpi, then scale
})

const canvasStyle = computed(() => ({
  width: pageWidth.value * scale.value + 'px',
  height: pageHeight.value * scale.value + 'px',
  backgroundColor: store.templateJson.style?.backgroundColor || '#ffffff',
}))

const gridStyle = computed(() => {
  if (!store.showGrid) return {}
  const gs = store.gridSize * scale.value
  return {
    backgroundImage: `
      linear-gradient(to right, rgba(0,0,0,0.05) 1px, transparent 1px),
      linear-gradient(to bottom, rgba(0,0,0,0.05) 1px, transparent 1px)
    `,
    backgroundSize: `${gs}px ${gs}px`,
  }
})

function updateContainerWidth() {
  if (canvasContainer.value) {
    containerWidth.value = canvasContainer.value.clientWidth
  }
}

let resizeObserver = null
onMounted(() => {
  updateContainerWidth()
  resizeObserver = new ResizeObserver(updateContainerWidth)
  if (canvasContainer.value) resizeObserver.observe(canvasContainer.value)
})
onBeforeUnmount(() => {
  resizeObserver?.disconnect()
})

function onCanvasClick(e) {
  // Click on empty canvas area → clear selection
  if (e.target === e.currentTarget || e.target.classList.contains('canvas-inner')) {
    store.clearSelection()
  }
}

function onDrop(e) {
  e.preventDefault()
  if (store.previewMode) return
  const jsonStr = e.dataTransfer.getData('application/json')
  if (!jsonStr) return

  const item = JSON.parse(jsonStr)
  const rect = e.currentTarget.getBoundingClientRect()
  const x = store.snapValue((e.clientX - rect.left) / scale.value)
  const y = store.snapValue((e.clientY - rect.top) / scale.value)

  const defaults = getElementDefaults(item)
  store.addElement({
    ...defaults,
    x: Math.max(0, x - (defaults.width || 50) / 2),
    y: Math.max(0, y - (defaults.height || 10) / 2),
  })
}

function onDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'copy'
}

function onElementMove(elId, pos) {
  store.updateElement(elId, pos)
}

function onElementResize(elId, dims) {
  store.updateElement(elId, dims)
}

function getElementDefaults(item) {
  const base = {
    type: item.type,
    width: 60,
    height: 8,
    style: {
      fontFamily: store.getDefaultFontFamily(),
      fontSize: 10,
      fontWeight: 'normal',
      fontStyle: 'normal',
      color: '#000000',
      textAlign: 'left',
      backgroundColor: null,
      borderWidth: 0,
      borderColor: '#000000',
      padding: 1,
    },
  }

  if (item.type === 'field') {
    return { ...base, field: item.field, label: item.label, showLabel: false }
  }
  if (item.type === 'attribute') {
    return { ...base, attributeId: item.attributeId, label: item.label, showLabel: true, showValue: true, showUnit: true }
  }
  if (item.type === 'text') {
    return { ...base, content: item.content || 'Text hier eingeben' }
  }
  if (item.type === 'image') {
    return { ...base, source: item.source || 'primary', width: 40, height: 40, style: { ...base.style, padding: 0 } }
  }
  if (item.type === 'shape') {
    return {
      ...base,
      width: 40,
      height: 20,
      style: { ...base.style, backgroundColor: '#f3f4f6', borderWidth: 1, borderColor: '#d1d5db', padding: 0 },
    }
  }
  return base
}

function onKeyDown(e) {
  if (!store.selectedElementId || store.previewMode) return
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return

  if (e.key === 'Delete' || e.key === 'Backspace') {
    e.preventDefault()
    store.removeElement(store.selectedElementId)
  }
  if (e.key === 'd' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault()
    store.duplicateElement(store.selectedElementId)
  }
  // Arrow key nudge
  const nudge = e.shiftKey ? 10 : (store.snapToGrid ? store.gridSize : 1)
  const el = store.selectedElement
  if (!el) return
  if (e.key === 'ArrowLeft') { e.preventDefault(); store.updateElement(el.id, { x: Math.max(0, (el.x || 0) - nudge) }) }
  if (e.key === 'ArrowRight') { e.preventDefault(); store.updateElement(el.id, { x: (el.x || 0) + nudge }) }
  if (e.key === 'ArrowUp') { e.preventDefault(); store.updateElement(el.id, { y: Math.max(0, (el.y || 0) - nudge) }) }
  if (e.key === 'ArrowDown') { e.preventDefault(); store.updateElement(el.id, { y: (el.y || 0) + nudge }) }
}
</script>

<template>
  <div
    ref="canvasContainer"
    class="flex-1 flex items-start justify-center overflow-auto p-5 bg-[var(--color-bg)]"
    tabindex="0"
    @keydown="onKeyDown"
  >
    <div
      class="relative shadow-lg border border-[var(--color-border)] shrink-0 canvas-inner"
      :style="{ ...canvasStyle, ...gridStyle }"
      @click="onCanvasClick"
      @drop="onDrop"
      @dragover="onDragOver"
    >
      <!-- Empty state -->
      <div
        v-if="store.templateJson.elements.length === 0"
        class="absolute inset-0 flex items-center justify-center pointer-events-none"
      >
        <div class="text-center">
          <p class="text-sm text-[var(--color-text-tertiary)]">Ziehe Elemente aus der Palette hierher</p>
          <p class="text-xs text-[var(--color-text-tertiary)] mt-1">oder doppelklicke in der Palette</p>
        </div>
      </div>

      <!-- Elements -->
      <PdfCanvasElement
        v-for="el in store.templateJson.elements"
        :key="el.id"
        :element="el"
        :scale="scale"
        :selected="store.selectedElementId === el.id"
        @select="store.selectElement(el.id)"
        @move="onElementMove(el.id, $event)"
        @resize="onElementResize(el.id, $event)"
      />
    </div>
  </div>
</template>
