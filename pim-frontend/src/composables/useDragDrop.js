import { ref } from 'vue'

export function useDragDrop(onReorder) {
  const dragging = ref(false)
  const dragIndex = ref(null)

  function onDragStart(index) {
    dragging.value = true
    dragIndex.value = index
  }

  function onDragEnd() {
    dragging.value = false
    dragIndex.value = null
  }

  function onDrop(items, event) {
    if (typeof onReorder === 'function') {
      onReorder(items)
    }
    onDragEnd()
  }

  return { dragging, dragIndex, onDragStart, onDragEnd, onDrop }
}
