<script setup>
import { ref, computed } from 'vue'
import { usePdfTemplateDesignerStore } from '@/stores/pdfTemplateDesigner'
import { Type, Hash, Tag, Image, Square } from 'lucide-vue-next'

const props = defineProps({
  element: { type: Object, required: true },
  scale: { type: Number, default: 1 },
  selected: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'move', 'resize'])

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
    fontSize: ((s.fontSize || 10) * props.scale) + 'px',
    color: s.color || '#000000',
    fontWeight: s.fontWeight || 'normal',
    fontStyle: s.fontStyle || 'normal',
    textAlign: s.textAlign || 'left',
    backgroundColor: s.backgroundColor || 'transparent',
    border: s.borderWidth && parseInt(s.borderWidth) > 0
      ? `${parseInt(s.borderWidth) * props.scale}px solid ${s.borderColor || '#000'}`
      : props.selected ? '1px dashed var(--color-accent)' : '1px dashed transparent',
    padding: ((s.padding || 0) * props.scale) + 'px',
    lineHeight: s.lineHeight || 'normal',
    overflow: 'hidden',
  }
})

const displayContent = computed(() => {
  const el = props.element
  const type = el.type || 'text'

  if (type === 'text') return el.content || 'Text hier eingeben'
  if (type === 'field') return el.label || `{${el.field || 'feld'}}`
  if (type === 'attribute') return el.label || '{Attribut}'
  if (type === 'shape') return ''
  return ''
})

const typeIcon = computed(() => {
  const icons = { text: Type, field: Hash, attribute: Tag, image: Image, shape: Square }
  return icons[props.element.type] || Type
})

function onMouseDown(e) {
  if (resizing.value) return
  e.stopPropagation()
  emit('select')

  dragging.value = true
  const startX = e.clientX
  const startY = e.clientY
  const startElX = props.element.x || 0
  const startElY = props.element.y || 0

  function onMove(ev) {
    const dx = (ev.clientX - startX) / props.scale
    const dy = (ev.clientY - startY) / props.scale
    let newX = store.snapValue(startElX + dx)
    let newY = store.snapValue(startElY + dy)
    newX = Math.max(0, newX)
    newY = Math.max(0, newY)
    emit('move', { x: newX, y: newY })
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
    :class="{ 'z-10': selected }"
    @mousedown="onMouseDown"
  >
    <!-- Content -->
    <div class="w-full h-full overflow-hidden" :class="element.type === 'image' ? 'flex items-center justify-center' : ''">
      <template v-if="element.type === 'image'">
        <Image class="w-1/3 h-1/3 text-[var(--color-text-tertiary)] opacity-40" :stroke-width="1" />
      </template>
      <template v-else-if="element.type === 'shape'">
        <!-- Shape: purely visual box, content comes from background/border -->
      </template>
      <template v-else>
        <span class="block w-full" :class="{ 'opacity-50': element.type !== 'text' }">{{ displayContent }}</span>
      </template>
    </div>

    <!-- Type badge (on hover or selected) -->
    <div
      v-if="selected || false"
      class="absolute -top-4 left-0 text-[8px] font-medium px-1 py-0.5 rounded bg-[var(--color-accent)] text-white whitespace-nowrap"
    >
      {{ { text: 'Text', field: 'Feld', attribute: 'Attribut', image: 'Bild', shape: 'Form' }[element.type] || element.type }}
    </div>

    <!-- Resize handles (only when selected) -->
    <template v-if="selected">
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
