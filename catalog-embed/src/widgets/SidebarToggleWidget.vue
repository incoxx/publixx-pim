<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()
let sidebarEl = null

onMounted(() => {
  sidebarEl = document.querySelector('[data-catalog-sidebar]')
  if (sidebarEl) {
    sidebarEl.classList.add('pxc-sidebar')
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
