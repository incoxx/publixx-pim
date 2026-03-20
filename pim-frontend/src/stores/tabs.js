import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import router from '@/router'

const MAX_TABS = 10
const STORAGE_KEY = 'pim_open_tabs'

// Map route names to component names for keep-alive include
const COMPONENT_NAMES = {
  'product-detail': 'ProductDetailView',
  'report-designer': 'ReportDesignerView',
  'connector-connection': 'ConnectorConnectionView',
  'api-designer-edit': 'ApiDesignerView',
  'pdf-template-designer': 'PdfTemplateDesignerView',
  'catalog-template-designer': 'CatalogTemplateDesignerView',
}

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

  const cachedComponentNames = computed(() =>
    tabs.value.map(t => t.componentName).filter(Boolean)
  )

  function getTabId(route) {
    return `${route.name}-${route.params.id}`
  }

  function openTab(route) {
    const id = getTabId(route)
    const existing = tabs.value.find(t => t.id === id)

    if (existing) {
      activeTabId.value = id
      return
    }

    const componentName = COMPONENT_NAMES[route.name]
    if (!componentName) return

    const tabTitle = route.meta.tabTitle
      ? `${route.meta.tabTitle} #${route.params.id}`
      : `${route.meta.title} #${route.params.id}`

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
      componentName,
    })

    activeTabId.value = id
    saveTabs(tabs.value)
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
    if (!route.params?.id) {
      activeTabId.value = null
      return
    }
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
    cachedComponentNames,
    openTab,
    closeTab,
    closeOtherTabs,
    closeAllTabs,
    setActiveByRoute,
    updateTabTitle,
  }
})
