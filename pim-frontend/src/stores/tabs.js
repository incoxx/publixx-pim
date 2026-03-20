import { defineStore } from 'pinia'
import { ref } from 'vue'
import router from '@/router'

const MAX_TABS = 10
const STORAGE_KEY = 'pim_open_tabs'

function loadTabs() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function saveTabs(tabs) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(tabs))
}

export const useTabStore = defineStore('tabs', () => {
  const tabs = ref(loadTabs())
  const activeTabId = ref(null)

  function getTabId(route) {
    return route.params?.id ? `${route.name}-${route.params.id}` : `${route.name}`
  }

  function openTab(route, label) {
    const id = getTabId(route)
    const existing = tabs.value.find(t => t.id === id)

    if (existing) {
      activeTabId.value = id
      return
    }

    const tabTitle = label
      || route.meta.tabTitle
      || route.meta.title
      || route.name

    // Enforce max limit — remove oldest tab
    if (tabs.value.length >= MAX_TABS) {
      tabs.value.splice(0, 1)
    }

    tabs.value.push({
      id,
      routeName: route.name,
      routeParams: { ...route.params },
      routeFullPath: route.fullPath,
      title: tabTitle,
      pinned: !route.meta.tabable, // manually pinned tabs are marked
    })

    activeTabId.value = id
    saveTabs(tabs.value)
  }

  function pinCurrentRoute(route) {
    const id = getTabId(route)
    const existing = tabs.value.find(t => t.id === id)
    if (existing) {
      // Already pinned — remove it (unpin)
      closeTab(id)
      return false
    }
    openTab(route)
    return true
  }

  function isRoutePinned(route) {
    const id = getTabId(route)
    return tabs.value.some(t => t.id === id)
  }

  function closeTab(tabId) {
    const idx = tabs.value.findIndex(t => t.id === tabId)
    if (idx === -1) return

    const wasActive = activeTabId.value === tabId
    tabs.value.splice(idx, 1)
    saveTabs(tabs.value)

    if (wasActive && tabs.value.length > 0) {
      // Navigate to the next closest tab
      const nextTab = tabs.value[Math.min(idx, tabs.value.length - 1)]
      activeTabId.value = nextTab.id
      router.push(nextTab.routeFullPath)
    } else if (tabs.value.length === 0) {
      activeTabId.value = null
    }
  }

  function closeOtherTabs(tabId) {
    tabs.value = tabs.value.filter(t => t.id === tabId)
    activeTabId.value = tabId
    saveTabs(tabs.value)
  }

  function closeAllTabs() {
    tabs.value = []
    activeTabId.value = null
    saveTabs(tabs.value)
  }

  function setActiveByRoute(route) {
    const id = getTabId(route)
    if (tabs.value.find(t => t.id === id)) {
      activeTabId.value = id
    } else {
      activeTabId.value = null
    }
  }

  function updateTabTitle(route, title) {
    const id = getTabId(route)
    const tab = tabs.value.find(t => t.id === id)
    if (tab) {
      tab.title = title
      saveTabs(tabs.value)
    }
  }

  return {
    tabs,
    activeTabId,
    openTab,
    pinCurrentRoute,
    isRoutePinned,
    closeTab,
    closeOtherTabs,
    closeAllTabs,
    setActiveByRoute,
    updateTabTitle,
  }
})
