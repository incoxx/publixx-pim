<script setup>
import { ref, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useTabStore } from '@/stores/tabs'
import { X, ListX } from 'lucide-vue-next'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const tabStore = useTabStore()

// Context menu state
const contextMenu = ref({ visible: false, x: 0, y: 0, tabId: null })

function switchTab(tab) {
  router.push(tab.routeFullPath)
}

function closeTab(e, tabId) {
  e.stopPropagation()
  tabStore.closeTab(tabId)
}

function openContextMenu(e, tabId) {
  e.preventDefault()
  contextMenu.value = { visible: true, x: e.clientX, y: e.clientY, tabId }
  nextTick(() => {
    document.addEventListener('click', closeContextMenu, { once: true })
  })
}

function closeContextMenu() {
  contextMenu.value.visible = false
}

function contextCloseOthers() {
  tabStore.closeOtherTabs(contextMenu.value.tabId)
  closeContextMenu()
}

function contextCloseAll() {
  tabStore.closeAllTabs()
  closeContextMenu()
}
</script>

<template>
  <div
    v-if="tabStore.tabs.length > 0"
    class="flex items-center gap-0.5 px-2 py-1 bg-[var(--color-surface)] border-b border-[var(--color-border)] overflow-x-auto"
  >
    <!-- Tabs -->
    <div
      v-for="tab in tabStore.tabs"
      :key="tab.id"
      class="group flex items-center gap-1 px-3 py-1.5 rounded-md text-xs cursor-pointer whitespace-nowrap select-none transition-colors"
      :class="tabStore.activeTabId === tab.id
        ? 'bg-[var(--color-bg)] text-[var(--color-text-primary)] font-medium shadow-sm'
        : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'"
      @click="switchTab(tab)"
      @contextmenu="openContextMenu($event, tab.id)"
    >
      <span>{{ tab.title }}</span>
      <button
        class="p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-[var(--color-border)] transition-opacity"
        :class="{ 'opacity-100': tabStore.activeTabId === tab.id }"
        @click="closeTab($event, tab.id)"
      >
        <X class="w-3 h-3" />
      </button>
    </div>

    <!-- Spacer -->
    <div class="flex-1" />

    <!-- Close all tabs button -->
    <button
      class="p-1.5 rounded text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)] transition-colors shrink-0"
      :title="t('Alle Tabs schließen')"
      @click="tabStore.closeAllTabs()"
    >
      <ListX class="w-4 h-4" />
    </button>

    <!-- Context Menu -->
    <Teleport to="body">
      <div
        v-if="contextMenu.visible"
        class="fixed z-50 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 min-w-[160px]"
        :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
      >
        <button
          class="w-full px-3 py-1.5 text-xs text-left text-[var(--color-text-primary)] hover:bg-[var(--color-bg)] transition-colors"
          @click="contextCloseOthers"
        >
          {{ t('Andere schließen') }}
        </button>
        <button
          class="w-full px-3 py-1.5 text-xs text-left text-[var(--color-text-primary)] hover:bg-[var(--color-bg)] transition-colors"
          @click="contextCloseAll"
        >
          {{ t('Alle schließen') }}
        </button>
      </div>
    </Teleport>
  </div>
</template>
