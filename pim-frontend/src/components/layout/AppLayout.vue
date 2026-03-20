<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTabStore } from '@/stores/tabs'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
import AppTabBar from './AppTabBar.vue'

const authStore = useAuthStore()
const tabStore = useTabStore()
const route = useRoute()

// Close panel when navigating to a different route (but not when switching between open tabs)
watch(() => route.path, (newPath, oldPath) => {
  if (newPath !== oldPath) {
    authStore.closePanel()
  }
})

// On mobile (<768px), sidebar is an overlay — no margin needed
const isMobile = ref(false)

function checkMobile() {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

const mainStyle = computed(() => ({
  marginLeft: isMobile.value ? '0' : (authStore.sidebarCollapsed ? '56px' : authStore.sidebarWidth + 'px'),
  marginRight: authStore.panelOpen && !isMobile.value ? '360px' : '0',
}))
</script>

<template>
  <div v-if="authStore.isAuthenticated" class="min-h-screen bg-[var(--color-bg)]">
    <!-- Sidebar -->
    <AppSidebar />

    <!-- Main content -->
    <div :style="mainStyle" class="transition-all duration-200 ease-out">
      <AppHeader />
      <AppTabBar />
      <main class="p-6">
        <router-view v-slot="{ Component }">
          <keep-alive :include="tabStore.cachedComponentNames">
            <component :is="Component" :key="route.fullPath" />
          </keep-alive>
        </router-view>
      </main>
    </div>

    <!-- Right Panel (on-demand) -->
    <transition name="slide-right">
      <div
        v-if="authStore.panelOpen"
        class="fixed top-0 right-0 w-[360px] h-screen bg-[var(--color-surface)] border-l border-[var(--color-border)] shadow-lg z-30 flex flex-col"
      >
        <div class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <span class="text-sm font-medium text-[var(--color-text-primary)]">Detail</span>
          <button
            class="pim-btn-ghost p-1 rounded"
            @click="authStore.closePanel()"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto">
          <component
            v-if="authStore.panelComponent"
            :is="authStore.panelComponent"
            v-bind="authStore.panelProps"
            :key="Object.values(authStore.panelProps).map(p => p?.id).join('-') || 'new'"
          />
        </div>
      </div>
    </transition>
  </div>

  <!-- Login page -->
  <div v-else class="min-h-screen bg-[var(--color-bg)]">
    <router-view />
  </div>
</template>
