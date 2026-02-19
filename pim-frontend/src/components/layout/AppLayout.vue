<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'

const authStore = useAuthStore()

const mainClass = computed(() => ({
  'ml-[240px]': !authStore.sidebarCollapsed,
  'ml-[56px]': authStore.sidebarCollapsed,
  'mr-[360px]': authStore.panelOpen,
}))
</script>

<template>
  <div v-if="authStore.isAuthenticated" class="min-h-screen bg-[var(--color-bg)]">
    <!-- Sidebar -->
    <AppSidebar />

    <!-- Main content -->
    <div :class="mainClass" class="transition-all duration-200 ease-out">
      <AppHeader />
      <main class="p-6">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>

    <!-- Right Panel (on-demand) -->
    <transition name="slide-right">
      <div
        v-if="authStore.panelOpen"
        class="fixed top-0 right-0 w-[360px] h-screen bg-[var(--color-surface)] border-l border-[var(--color-border)] shadow-lg z-30 overflow-y-auto"
      >
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
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
        <component
          v-if="authStore.panelComponent"
          :is="authStore.panelComponent"
          v-bind="authStore.panelProps"
        />
      </div>
    </transition>
  </div>

  <!-- Login page -->
  <div v-else class="min-h-screen bg-[var(--color-bg)]">
    <router-view />
  </div>
</template>
