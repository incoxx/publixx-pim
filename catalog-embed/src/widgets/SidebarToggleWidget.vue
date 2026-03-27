<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()
let sidebarEl = null
let resizeHandle = null

function createResizeHandle() {
  if (!sidebarEl || resizeHandle) return

  // Sidebar muss relativ positioniert sein für den absoluten Handle
  sidebarEl.style.position = 'relative'

  resizeHandle = document.createElement('div')
  resizeHandle.className = 'pxc-sidebar-resize'
  resizeHandle.innerHTML = '<div class="pxc-sidebar-resize__indicator"></div>'
  sidebarEl.appendChild(resizeHandle)

  resizeHandle.addEventListener('mousedown', startResize)
}

function startResize(e) {
  e.preventDefault()
  const startX = e.clientX
  const startWidth = sidebarEl.offsetWidth

  function onMouseMove(e) {
    const delta = e.clientX - startX
    const newWidth = Math.max(200, Math.min(500, startWidth + delta))
    sidebarEl.style.width = newWidth + 'px'
  }

  function onMouseUp() {
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }

  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

function removeResizeHandle() {
  if (resizeHandle && resizeHandle.parentNode) {
    resizeHandle.removeEventListener('mousedown', startResize)
    resizeHandle.parentNode.removeChild(resizeHandle)
    resizeHandle = null
  }
}

onMounted(() => {
  // Try explicit sidebar marker first
  sidebarEl = document.querySelector('[data-catalog-sidebar]')

  // Fallback: find the parent of the categories widget
  if (!sidebarEl) {
    const categoriesEl = document.querySelector('[data-catalog="categories"]')
    if (categoriesEl) {
      sidebarEl = categoriesEl.parentElement
    }
  }

  if (sidebarEl) {
    sidebarEl.classList.add('pxc-sidebar')
    // Resize-Handle auf Desktop hinzufügen
    if (window.innerWidth >= 768) {
      createResizeHandle()
    }
  }
})

watch(() => state.sidebarOpen, (open) => {
  if (!sidebarEl) return
  if (open) {
    sidebarEl.classList.add('pxc-sidebar--open')
    document.body.style.overflow = 'hidden'
  } else {
    sidebarEl.classList.remove('pxc-sidebar--open')
    document.body.style.overflow = ''
  }
})

// Auto-close sidebar when navigating categories on mobile
watch(() => state.selectedCategoryId, () => {
  if (state.sidebarOpen) actions.closeSidebar()
})

onUnmounted(() => {
  removeResizeHandle()
  if (sidebarEl) {
    sidebarEl.classList.remove('pxc-sidebar', 'pxc-sidebar--open')
  }
  document.body.style.overflow = ''
})
</script>

<template>
  <button class="pxc-sidebar-toggle" @click="actions.toggleSidebar()">
    <span v-html="icons.menu"></span>
  </button>

  <Teleport to="body">
    <transition name="pxc-fade">
      <div
        v-if="state.sidebarOpen"
        class="pxc-sidebar-overlay"
        @click="actions.closeSidebar()"
      ></div>
    </transition>
  </Teleport>
</template>
