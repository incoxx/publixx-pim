<script setup>
import { ref, computed } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import { Type, Hash, Tag, Image, Square, Table2, List, LayoutGrid } from 'lucide-vue-next'

const props = defineProps({
  element: { type: Object, required: true },
  scale: { type: Number, default: 1 },
  selected: { type: Boolean, default: false },
  multiSelected: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'move', 'resize', 'contextmenu'])

const store = usePdfTemplateDesignerStore()
const dragging = ref(false)
const resizing = ref(false)
const resizeHandle = ref(null)

const style = computed(() => {
  const el = props.element
  const s = el.style || {}
  return {
    left: (el.x || 0) * props.scale + 'px',
    top: (el.y || 0) * props.scale + 'px',
    width: (el.width || 50) * props.scale + 'px',
    height: (el.height || 10) * props.scale + 'px',
    fontFamily: s.fontFamily || 'DejaVu Sans, sans-serif',
    fontSize: ((s.fontSize || 10) * (25.4 / 72) * props.scale) + 'px',
    color: s.color || '#000000',
    fontWeight: s.fontWeight || 'normal',
    fontStyle: s.fontStyle || 'normal',
    textAlign: s.textAlign || 'left',
    backgroundColor: s.backgroundColor || 'transparent',
    border: s.borderWidth && parseInt(s.borderWidth) > 0
      ? `${parseInt(s.borderWidth) * props.scale}px solid ${s.borderColor || '#000'}`
      : props.selected ? '1px dashed var(--color-accent)'
      : props.multiSelected ? '1px dashed var(--color-accent)'
      : '1px dashed rgba(0,0,0,0.15)',
    padding: ((s.padding || 0) * props.scale) + 'px',
    lineHeight: s.lineHeight || 'normal',
    overflow: 'hidden',
  }
})

const displayContent = computed(() => {
  const el = props.element
  const type = el.type || 'text'

  // In preview mode, show resolved data
  if (store.previewMode && store.resolvedElements.length > 0) {
    const resolved = store.getResolvedElement(el.id)
    if (resolved) {
      if (type === 'shape') return ''
      return resolved.displayValue ?? ''
    }
  }

  if (type === 'text') return el.content || 'Text hier eingeben'
  if (type === 'field') return el.label || `{${el.field || 'feld'}}`
  if (type === 'attribute') return el.label || '{Attribut}'
  if (type === 'price') return el.label || '{Preis}'
  if (type === 'shape') return ''
  return ''
})

const variantTableData = computed(() => {
  const el = props.element
  if (el.type !== 'variant_table') return null

  // Preview mode: real data
  if (store.previewMode && store.resolvedElements.length > 0) {
    const resolved = store.getResolvedElement(el.id)
    if (resolved?.variantTableData) return resolved.variantTableData
  }

  // Design mode: placeholder
  const columns = el.columns || ['sku', 'name', 'variant_attributes']
  const headers = []
  if (columns.includes('sku')) headers.push('SKU')
  if (columns.includes('name')) headers.push('Name')
  if (columns.includes('variant_attributes')) {
    headers.push('Attribut 1', 'Attribut 2')
  }

  const rows = [
    headers.map((_, i) => {
      if (i === 0 && columns.includes('sku')) return 'VAR-001'
      if ((i === 0 && !columns.includes('sku')) || (i === 1 && columns.includes('sku'))) return 'Variante 1'
      return 'Wert'
    }),
    headers.map((_, i) => {
      if (i === 0 && columns.includes('sku')) return 'VAR-002'
      if ((i === 0 && !columns.includes('sku')) || (i === 1 && columns.includes('sku'))) return 'Variante 2'
      return 'Wert'
    }),
  ]

  return { headers, rows }
})

const relationTableData = computed(() => {
  const el = props.element
  if (el.type !== 'relation_table') return null

  // Preview mode: real data
  if (store.previewMode && store.resolvedElements.length > 0) {
    const resolved = store.getResolvedElement(el.id)
    if (resolved?.variantTableData) return resolved.variantTableData
  }

  // Design mode: placeholder
  const columns = el.columns || ['sku', 'name']
  const headers = []
  if (columns.includes('relation_type')) headers.push('Beziehungsart')
  if (columns.includes('sku')) headers.push('SKU')
  if (columns.includes('name')) headers.push('Name')
  if (columns.includes('ean')) headers.push('EAN')
  if (columns.includes('relation_attributes')) {
    headers.push('Bez.-Attr. 1', 'Bez.-Attr. 2')
  }
  if (columns.includes('product_attributes')) {
    const ids = el.productAttributeIds || []
    for (let i = 0; i < Math.max(ids.length, 1); i++) {
      headers.push('Prod.-Attr. ' + (i + 1))
    }
  }

  const rows = [
    headers.map((_, i) => i === 0 ? 'REL-001' : 'Beispiel'),
    headers.map((_, i) => i === 0 ? 'REL-002' : 'Beispiel'),
  ]

  return { headers, rows }
})

const attributeTableData = computed(() => {
  const el = props.element
  if (el.type !== 'attribute_table') return null

  // Preview mode: real data
  if (store.previewMode && store.resolvedElements.length > 0) {
    const resolved = store.getResolvedElement(el.id)
    if (resolved?.variantTableData) return resolved.variantTableData
  }

  // Design mode: placeholder
  const headers = ['Attributname', 'Attributwert', 'Einheit']
  const rows = [
    ['Farbe', 'Rot', ''],
    ['Gewicht', '1.5', 'kg'],
    ['Breite', '100', 'mm'],
  ]

  return { headers, rows }
})

const smartTableData = computed(() => {
  const el = props.element
  if (el.type !== 'smart_table') return null

  // Preview mode: echte Daten vom Backend
  if (store.previewMode && store.resolvedElements.length > 0) {
    const resolved = store.getResolvedElement(el.id)
    if (resolved?.smartTableData) return resolved.smartTableData
  }

  // Design mode: Placeholder basierend auf PTL-Konfiguration
  const ptl = el.ptl || {}
  const mode = ptl.mode || 'normal'

  if (mode === 'pivot') {
    const p = ptl.pivot || {}
    return {
      headerRows: [[
        { label: p.rowField || 'Zeile', colspan: 1, rowspan: 1 },
        { label: 'Wert A', colspan: 1, rowspan: 1 },
        { label: 'Wert B', colspan: 1, rowspan: 1 },
      ]],
      bodyRows: [
        ['Zeile 1', '10', '20'],
        ['Zeile 2', '15', '25'],
      ],
      columns: [
        { align: 'left' },
        { align: 'right' },
        { align: 'right' },
      ],
    }
  }

  // Normal mode
  const columns = ptl.columns || []
  if (columns.length === 0) {
    return {
      headerRows: [[
        { label: 'Spalte 1', colspan: 1, rowspan: 1 },
        { label: 'Spalte 2', colspan: 1, rowspan: 1 },
      ]],
      bodyRows: [
        ['Beispiel', 'Wert'],
        ['Beispiel', 'Wert'],
      ],
      columns: [{ align: 'left' }, { align: 'left' }],
    }
  }

  // Header-Zeilen aus Spalten-Definitionen aufbauen
  const maxDepth = getColumnDepth(columns)
  const headerRows = []
  for (let i = 0; i < maxDepth; i++) headerRows.push([])
  collectHeaderCells(columns, headerRows, 0, maxDepth)

  // Flache Spalten
  const flat = []
  flattenCols(columns, flat)

  // Placeholder-Zeilen
  const bodyRows = [
    flat.map(() => 'Beispiel'),
    flat.map(() => 'Wert'),
  ]

  return { headerRows, bodyRows, columns: flat }
})

function getColumnDepth(columns) {
  let max = 1
  for (const col of columns) {
    if (col.children?.length) {
      max = Math.max(max, 1 + getColumnDepth(col.children))
    }
  }
  return max
}

function collectHeaderCells(columns, rows, level, maxDepth) {
  for (const col of columns) {
    if (col.children?.length) {
      const colspan = countLeaves(col.children)
      rows[level].push({ label: col.label || '', colspan, rowspan: 1 })
      collectHeaderCells(col.children, rows, level + 1, maxDepth)
    } else {
      rows[level].push({ label: col.label || col.field || '', colspan: 1, rowspan: maxDepth - level })
    }
  }
}

function countLeaves(columns) {
  let c = 0
  for (const col of columns) {
    c += col.children?.length ? countLeaves(col.children) : 1
  }
  return c
}

function flattenCols(columns, flat) {
  for (const col of columns) {
    if (col.children?.length) flattenCols(col.children, flat)
    else flat.push(col)
  }
}

// ── Split Label+Value rendering ───────────────────────
const showSplitLabel = computed(() => {
  const el = props.element
  return !!(el.showLabel && ['field', 'attribute', 'price'].includes(el.type))
})

const labelDisplayText = computed(() => {
  const el = props.element
  const pos = el.labelPosition || 'left'
  if (pos === 'concat') {
    return (el.label || 'Label') + (el.labelSeparator ?? ': ')
  }
  return el.label || 'Label'
})

const valueDisplayText = computed(() => {
  const el = props.element
  const type = el.type

  if (store.previewMode && store.resolvedElements.length > 0) {
    const resolved = store.getResolvedElement(el.id)
    if (resolved) {
      if (type === 'attribute') {
        return [resolved.rawValue, resolved.rawUnit].filter(v => v !== null && v !== undefined && v !== '').join(' ')
      }
      if (type === 'field') return resolved.rawValue ?? ''
      if (type === 'price') {
        if (resolved.rawValue != null) {
          return Number(resolved.rawValue).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (resolved.currency || 'EUR')
        }
        return ''
      }
    }
  }

  // Design-Modus Platzhalter
  if (type === 'field') return `{${el.field || 'Feld'}}`
  if (type === 'attribute') return '{Wert}'
  if (type === 'price') return '{0,00 EUR}'
  return ''
})

const labelContainerStyle = computed(() => {
  const el = props.element
  const pos = el.labelPosition || 'left'
  const gapMm = pos === 'concat' ? 0 : (el.labelGap ?? 2)
  return {
    display: 'flex',
    flexDirection: pos === 'top' ? 'column' : 'row',
    alignItems: pos === 'top' ? 'flex-start' : 'baseline',
    gap: (gapMm * props.scale) + 'px',
    width: '100%',
  }
})

const labelSpanStyle = computed(() => {
  const el = props.element
  const s = el.style || {}
  const ls = el.labelStyle || {}
  const pxPerPt = (25.4 / 72) * props.scale
  return {
    fontSize: ((ls.fontSize ?? s.fontSize ?? 10) * pxPerPt) + 'px',
    color: ls.color || s.color || '#000000',
    fontWeight: ls.fontWeight || s.fontWeight || 'normal',
    flexShrink: '0',
  }
})

const previewImageUrl = computed(() => {
  if (!store.previewMode || !store.resolvedElements.length) return null
  const el = props.element
  if (el.type !== 'image') return null
  const resolved = store.getResolvedElement(el.id)
  if (resolved?.imageUrls?.length > 0) {
    return resolved.imageUrls[0]
  }
  return null
})

const typeIcon = computed(() => {
  const icons = { text: Type, field: Hash, attribute: Tag, image: Image, shape: Square, variant_table: Table2, attribute_table: List, smart_table: LayoutGrid }
  return icons[props.element.type] || Type
})

function onContextMenu(e) {
  if (store.previewMode) return
  e.stopPropagation()
  e.preventDefault()
  if (!store.isElementSelected(props.element.id)) {
    store.selectElement(props.element.id)
  }
  emit('contextmenu', { x: e.clientX, y: e.clientY })
}

function onMouseDown(e) {
  if (resizing.value || store.previewMode) return
  e.stopPropagation()

  const addToSelection = e.ctrlKey || e.metaKey || e.shiftKey
  // When already part of a multi-selection, keep the group — don't clear on plain click
  const alreadyInGroup = !addToSelection && store.selectedElementIds.size > 1 && store.isElementSelected(props.element.id)
  if (!alreadyInGroup) {
    emit('select', { addToSelection })
  }

  dragging.value = true
  const startX = e.clientX
  const startY = e.clientY
  const startElX = props.element.x || 0
  const startElY = props.element.y || 0

  // Capture start positions of all selected elements for group move
  const isMulti = alreadyInGroup || store.selectedElementIds.size > 1
  const startPositions = isMulti ? new Map() : null
  if (isMulti) {
    for (const id of store.selectedElementIds) {
      const el = store.templateJson.elements.find(e => e.id === id)
      if (el) startPositions.set(id, { x: el.x || 0, y: el.y || 0 })
    }
  }

  function onMove(ev) {
    const dx = (ev.clientX - startX) / props.scale
    const dy = (ev.clientY - startY) / props.scale

    if (isMulti && startPositions) {
      // Move all selected elements together
      for (const [id, startPos] of startPositions) {
        let newX = store.snapValue(startPos.x + dx)
        let newY = store.snapValue(startPos.y + dy)
        newX = Math.max(0, newX)
        newY = Math.max(0, newY)
        store.updateElement(id, { x: newX, y: newY })
      }
    } else {
      let newX = store.snapValue(startElX + dx)
      let newY = store.snapValue(startElY + dy)
      newX = Math.max(0, newX)
      newY = Math.max(0, newY)
      emit('move', { x: newX, y: newY })
    }
  }

  function onUp() {
    dragging.value = false
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }

  document.body.style.cursor = 'move'
  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}

function onResizeStart(e, handle) {
  if (store.previewMode) return
  e.stopPropagation()
  e.preventDefault()
  resizing.value = true
  resizeHandle.value = handle

  const startX = e.clientX
  const startY = e.clientY
  const startElX = props.element.x || 0
  const startElY = props.element.y || 0
  const startW = props.element.width || 50
  const startH = props.element.height || 10

  function onMove(ev) {
    const dx = (ev.clientX - startX) / props.scale
    const dy = (ev.clientY - startY) / props.scale

    let x = startElX, y = startElY, w = startW, h = startH

    if (handle.includes('e')) w = store.snapValue(Math.max(5, startW + dx))
    if (handle.includes('w')) { w = store.snapValue(Math.max(5, startW - dx)); x = store.snapValue(startElX + dx) }
    if (handle.includes('s')) h = store.snapValue(Math.max(5, startH + dy))
    if (handle.includes('n')) { h = store.snapValue(Math.max(5, startH - dy)); y = store.snapValue(startElY + dy) }

    emit('resize', { x, y, width: w, height: h })
  }

  function onUp() {
    resizing.value = false
    resizeHandle.value = null
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }

  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}
</script>

<template>
  <div
    class="absolute select-none group"
    :style="style"
    :class="{ 'z-10': selected || multiSelected, 'cursor-default': store.previewMode }"
    @mousedown="onMouseDown"
    @contextmenu.stop.prevent="onContextMenu"
  >
    <!-- Content -->
    <div class="w-full h-full overflow-hidden" :class="element.type === 'image' ? 'flex items-center justify-center' : ''">
      <template v-if="element.type === 'image'">
        <template v-if="previewImageUrl">
          <img :src="previewImageUrl" class="w-full h-full" style="object-fit: contain;" />
        </template>
        <template v-else>
          <Image class="w-1/3 h-1/3 text-[var(--color-text-tertiary)] opacity-40" :stroke-width="1" />
        </template>
      </template>
      <template v-else-if="element.type === 'variant_table' && variantTableData">
        <table
          :style="{
            width: '100%',
            borderCollapse: 'collapse',
            fontSize: ((element.tableStyle?.fontSize || 8) * (25.4 / 72) * scale) + 'px',
            tableLayout: element.columnWidths?.length ? 'fixed' : 'auto',
          }"
        >
          <thead>
            <tr :style="{ background: element.tableStyle?.headerBg || '#f3f4f6', color: element.tableStyle?.headerColor || '#374151' }">
              <th
                v-for="(h, hi) in variantTableData.headers"
                :key="hi"
                :style="{
                  border: '1px solid ' + (element.tableStyle?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                  textAlign: 'left',
                  fontWeight: 'bold',
                  fontSize: ((element.tableStyle?.headerFontSize || 8) * (25.4 / 72) * scale) + 'px',
                  whiteSpace: 'nowrap',
                  width: (element.columnWidths || [])[hi] ? (element.columnWidths[hi] + '%') : undefined,
                }"
              >{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, ri) in variantTableData.rows"
              :key="ri"
              :style="{ background: ri % 2 === 1 ? (element.tableStyle?.alternateRowBg || '#f9fafb') : 'transparent' }"
            >
              <td
                v-for="(cell, ci) in row"
                :key="ci"
                :style="{
                  border: '1px solid ' + (element.tableStyle?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                }"
              >{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </template>
      <template v-else-if="element.type === 'relation_table' && relationTableData">
        <table
          :style="{
            width: '100%',
            borderCollapse: 'collapse',
            fontSize: ((element.tableStyle?.fontSize || 8) * (25.4 / 72) * scale) + 'px',
            tableLayout: element.columnWidths?.length ? 'fixed' : 'auto',
          }"
        >
          <thead>
            <tr :style="{ background: element.tableStyle?.headerBg || '#f3f4f6', color: element.tableStyle?.headerColor || '#374151' }">
              <th
                v-for="(h, hi) in relationTableData.headers"
                :key="hi"
                :style="{
                  border: '1px solid ' + (element.tableStyle?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                  textAlign: 'left',
                  fontWeight: 'bold',
                  fontSize: ((element.tableStyle?.headerFontSize || 8) * (25.4 / 72) * scale) + 'px',
                  whiteSpace: 'nowrap',
                  width: (element.columnWidths || [])[hi] ? (element.columnWidths[hi] + '%') : undefined,
                }"
              >{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, ri) in relationTableData.rows"
              :key="ri"
              :style="{ background: ri % 2 === 1 ? (element.tableStyle?.alternateRowBg || '#f9fafb') : 'transparent' }"
            >
              <td
                v-for="(cell, ci) in row"
                :key="ci"
                :style="{
                  border: '1px solid ' + (element.tableStyle?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                }"
              >{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </template>
      <template v-else-if="element.type === 'attribute_table' && attributeTableData">
        <table
          :style="{
            width: '100%',
            borderCollapse: 'collapse',
            fontSize: ((element.tableStyle?.fontSize || 8) * (25.4 / 72) * scale) + 'px',
            tableLayout: element.columnWidths?.length ? 'fixed' : 'auto',
          }"
        >
          <thead>
            <tr :style="{ background: element.tableStyle?.headerBg || '#f3f4f6', color: element.tableStyle?.headerColor || '#374151' }">
              <th
                v-for="(h, hi) in attributeTableData.headers"
                :key="hi"
                :style="{
                  border: '1px solid ' + (element.tableStyle?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                  textAlign: 'left',
                  fontWeight: 'bold',
                  fontSize: ((element.tableStyle?.headerFontSize || 8) * (25.4 / 72) * scale) + 'px',
                  whiteSpace: 'nowrap',
                  width: (element.columnWidths || [])[hi] ? (element.columnWidths[hi] + '%') : undefined,
                }"
              >{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, ri) in attributeTableData.rows"
              :key="ri"
              :style="{ background: ri % 2 === 1 ? (element.tableStyle?.alternateRowBg || '#f9fafb') : 'transparent' }"
            >
              <td
                v-for="(cell, ci) in row"
                :key="ci"
                :style="{
                  border: '1px solid ' + (element.tableStyle?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                }"
              >{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </template>
      <template v-else-if="element.type === 'smart_table' && smartTableData">
        <table
          :style="{
            width: '100%',
            borderCollapse: 'collapse',
            fontSize: ((element.ptl?.rowStyle?.fontSize || 8) * (25.4 / 72) * scale) + 'px',
            tableLayout: 'auto',
          }"
        >
          <thead>
            <tr
              v-for="(headerRow, hri) in smartTableData.headerRows"
              :key="'hr' + hri"
              :style="{
                background: element.ptl?.headerStyle?.backgroundColor || 'transparent',
                color: element.ptl?.headerStyle?.color || '#374151',
              }"
            >
              <th
                v-for="(cell, ci) in headerRow"
                :key="ci"
                :colspan="cell.colspan"
                :rowspan="cell.rowspan"
                :style="{
                  border: '1px solid ' + (element.ptl?.borderColor || '#e5e7eb'),
                  padding: (1 * scale) + 'px ' + (2 * scale) + 'px',
                  textAlign: cell.colspan > 1 ? 'center' : 'left',
                  fontWeight: element.ptl?.headerStyle?.fontWeight || 700,
                  fontSize: ((element.ptl?.headerStyle?.fontSize || 9) * (25.4 / 72) * scale) + 'px',
                  whiteSpace: 'nowrap',
                }"
              >{{ cell.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, ri) in smartTableData.bodyRows"
              :key="ri"
              :style="{
                background: (element.ptl?.zebraStripes && ri % 2 === 1)
                  ? (element.ptl?.zebraColor || '#f9fafb')
                  : 'transparent',
              }"
            >
              <td
                v-for="(cell, ci) in row"
                :key="ci"
                :style="{
                  border: '1px solid ' + (element.ptl?.borderColor || '#e5e7eb'),
                  padding: (element.ptl?.rowStyle?.padding || 4) * scale * 0.25 + 'px ' + (2 * scale) + 'px',
                  textAlign: (smartTableData.columns[ci]?.align) || 'left',
                }"
              >{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </template>
      <template v-else-if="element.type === 'shape'">
        <!-- Shape: purely visual box, content comes from background/border -->
      </template>
      <template v-else>
        <!-- Split label + value for field/attribute/price with showLabel -->
        <template v-if="showSplitLabel">
          <div :style="labelContainerStyle">
            <span :style="labelSpanStyle">{{ labelDisplayText }}</span>
            <span :class="{ 'opacity-50': !store.previewMode }">{{ valueDisplayText }}</span>
          </div>
        </template>
        <template v-else>
          <span class="block w-full" :class="{ 'opacity-50': !store.previewMode && element.type !== 'text' }">{{ displayContent }}</span>
        </template>
      </template>
    </div>

    <!-- Type badge (on hover or selected) -->
    <div
      v-if="selected && !store.previewMode"
      class="absolute -top-4 left-0 text-[8px] font-medium px-1 py-0.5 rounded bg-[var(--color-accent)] text-white whitespace-nowrap"
    >
      {{ { text: 'Text', field: 'Feld', attribute: 'Attribut', price: 'Preis', image: 'Bild', shape: 'Form', variant_table: 'Varianten', relation_table: 'Beziehungen', attribute_table: 'Attr.-Tabelle', smart_table: 'Smart Table' }[element.type] || element.type }}
    </div>

    <!-- Resize handles (only when single-selected and not in preview mode) -->
    <template v-if="selected && !multiSelected && !store.previewMode">
      <div
        v-for="handle in ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']"
        :key="handle"
        class="absolute w-2 h-2 bg-[var(--color-accent)] border border-white rounded-sm z-20"
        :class="{
          '-top-1 -left-1 cursor-nw-resize': handle === 'nw',
          '-top-1 left-1/2 -translate-x-1/2 cursor-n-resize': handle === 'n',
          '-top-1 -right-1 cursor-ne-resize': handle === 'ne',
          'top-1/2 -right-1 -translate-y-1/2 cursor-e-resize': handle === 'e',
          '-bottom-1 -right-1 cursor-se-resize': handle === 'se',
          '-bottom-1 left-1/2 -translate-x-1/2 cursor-s-resize': handle === 's',
          '-bottom-1 -left-1 cursor-sw-resize': handle === 'sw',
          'top-1/2 -left-1 -translate-y-1/2 cursor-w-resize': handle === 'w',
        }"
        @mousedown="onResizeStart($event, handle)"
      />
    </template>
  </div>
</template>
