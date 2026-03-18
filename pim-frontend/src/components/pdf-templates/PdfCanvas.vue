<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import PdfCanvasElement from './PdfCanvasElement.vue'

// Load Google Fonts for canvas preview (Roboto, Open Sans, Lato, Noto Sans/Serif, Source Sans 3)
const googleFontFamilies = ['Roboto', 'Open+Sans', 'Lato', 'Noto+Sans', 'Noto+Serif', 'Source+Sans+3']
const googleFontsHref = `https://fonts.googleapis.com/css2?${googleFontFamilies.map(f => `family=${f}:ital,wght@0,400;0,700;1,400;1,700`).join('&')}&display=swap`
if (typeof document !== 'undefined' && !document.querySelector(`link[href*="fonts.googleapis.com"][data-pdf-tpl]`)) {
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = googleFontsHref
  link.setAttribute('data-pdf-tpl', '1')
  document.head.appendChild(link)
}

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
  // Click on empty canvas area → clear selection (unless Ctrl/Shift held)
  if (e.target === e.currentTarget || e.target.classList.contains('canvas-inner')) {
    if (!e.ctrlKey && !e.metaKey && !e.shiftKey) {
      store.clearSelection()
    }
  }
}

// ── Rubber-band (marquee) selection ──────────────────
const rubberBand = ref(null)

function onCanvasMouseDown(e) {
  // Only start rubber-band on direct canvas click (not on elements)
  if (e.target !== e.currentTarget && !e.target.classList.contains('canvas-inner')) return
  if (store.previewMode) return
  if (e.button !== 0) return

  const rect = e.currentTarget.getBoundingClientRect()
  const startX = e.clientX - rect.left
  const startY = e.clientY - rect.top
  let moved = false

  function onMove(ev) {
    const curX = ev.clientX - rect.left
    const curY = ev.clientY - rect.top
    const dx = Math.abs(curX - startX)
    const dy = Math.abs(curY - startY)
    if (!moved && dx < 4 && dy < 4) return // threshold before starting
    moved = true
    rubberBand.value = {
      x: Math.min(startX, curX),
      y: Math.min(startY, curY),
      w: Math.abs(curX - startX),
      h: Math.abs(curY - startY),
    }
  }

  function onUp() {
    if (moved && rubberBand.value) {
      // Find elements intersecting the rubber-band (in mm)
      const rb = rubberBand.value
      const rbLeft = rb.x / scale.value
      const rbTop = rb.y / scale.value
      const rbRight = (rb.x + rb.w) / scale.value
      const rbBottom = (rb.y + rb.h) / scale.value

      const ids = new Set()
      for (const el of store.templateJson.elements) {
        const ex = el.x || 0
        const ey = el.y || 0
        const ew = el.width || 50
        const eh = el.height || 10
        // Check intersection
        if (ex + ew > rbLeft && ex < rbRight && ey + eh > rbTop && ey < rbBottom) {
          ids.add(el.id)
        }
      }
      if (ids.size > 0) {
        store.selectedElementIds = ids
        store.selectedElementId = [...ids][ids.size - 1]
      }
    }
    rubberBand.value = null
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
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
  if (item.type === 'variant_table') {
    return {
      ...base,
      width: 190,
      height: 60,
      columns: ['sku', 'name', 'variant_attributes'],
      tableStyle: {
        headerBg: '#f3f4f6',
        headerColor: '#374151',
        borderColor: '#e5e7eb',
        alternateRowBg: '#f9fafb',
        fontSize: 8,
        headerFontSize: 8,
      },
      style: { ...base.style, padding: 0 },
    }
  }
  if (item.type === 'relation_table') {
    return {
      ...base,
      width: 190,
      height: 60,
      columns: item.columns || ['sku', 'name'],
      relationTypeId: item.relationTypeId || null,
      productAttributeIds: item.productAttributeIds || [],
      sortBy: null,
      sortDirection: 'asc',
      tableStyle: {
        headerBg: '#f3f4f6',
        headerColor: '#374151',
        borderColor: '#e5e7eb',
        alternateRowBg: '#f9fafb',
        fontSize: 8,
        headerFontSize: 8,
      },
      style: { ...base.style, padding: 0 },
    }
  }
  if (item.type === 'attribute_table') {
    return {
      ...base,
      width: 190,
      height: 60,
      sourceMode: item.sourceMode || 'group',
      attributeGroupId: item.attributeGroupId || null,
      attributeIds: item.attributeIds || [],
      tableStyle: {
        headerBg: '#f3f4f6',
        headerColor: '#374151',
        borderColor: '#e5e7eb',
        alternateRowBg: '#f9fafb',
        fontSize: 8,
        headerFontSize: 8,
      },
      style: { ...base.style, padding: 0 },
    }
  }
  return base
}

function onKeyDown(e) {
  if (store.previewMode) return
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return

  // Ctrl+A: Select all elements
  if (e.key === 'a' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault()
    store.selectAllElements()
    return
  }

  // Ctrl+C: Copy selected elements
  if (e.key === 'c' && (e.ctrlKey || e.metaKey)) {
    if (store.selectedElementIds.size > 0) {
      e.preventDefault()
      store.copySelectedElements()
    }
    return
  }

  // Ctrl+V: Paste elements
  if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
    if (store.clipboard.length > 0) {
      e.preventDefault()
      store.pasteElements()
    }
    return
  }

  if (!store.selectedElementId) return

  // Delete/Backspace: remove selected elements
  if (e.key === 'Delete' || e.key === 'Backspace') {
    e.preventDefault()
    if (store.selectedElementIds.size > 1) {
      store.removeSelectedElements()
    } else {
      store.removeElement(store.selectedElementId)
    }
    return
  }

  // Ctrl+D: Duplicate
  if (e.key === 'd' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault()
    store.duplicateElement(store.selectedElementId)
    return
  }

  // Arrow key nudge (works for multi-selection)
  const nudge = e.shiftKey ? 10 : (store.snapToGrid ? store.gridSize : 1)
  if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
    e.preventDefault()
    const dx = e.key === 'ArrowLeft' ? -nudge : e.key === 'ArrowRight' ? nudge : 0
    const dy = e.key === 'ArrowUp' ? -nudge : e.key === 'ArrowDown' ? nudge : 0
    if (store.selectedElementIds.size > 1) {
      store.moveSelectedElements(dx, dy)
    } else {
      const el = store.selectedElement
      if (el) {
        store.updateElement(el.id, {
          x: Math.max(0, (el.x || 0) + dx),
          y: Math.max(0, (el.y || 0) + dy),
        })
      }
    }
  }
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
      @mousedown="onCanvasMouseDown"
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
        :multiSelected="store.selectedElementIds.size > 1 && store.isElementSelected(el.id)"
        @select="(opts) => store.selectElement(el.id, opts?.addToSelection)"
        @move="onElementMove(el.id, $event)"
        @resize="onElementResize(el.id, $event)"
      />

      <!-- Rubber-band selection rectangle -->
      <div
        v-if="rubberBand"
        class="absolute pointer-events-none border border-[var(--color-accent)] bg-[var(--color-accent)]/10 z-30"
        :style="{
          left: rubberBand.x + 'px',
          top: rubberBand.y + 'px',
          width: rubberBand.w + 'px',
          height: rubberBand.h + 'px',
        }"
      />
    </div>
  </div>
</template>
