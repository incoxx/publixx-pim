import { defineStore } from 'pinia'
import { ref } from 'vue'
import navigationApi from '@/api/navigation'

/**
 * Store für den Navigationsbaum / Sitemap.
 * Spiegelt das Hierarchie-Store-Muster (Liste + aktueller Baum + UI-State).
 */
export const useNavigationStore = defineStore('navigation', () => {
  const navigations = ref([])
  const currentId = ref(null)
  const tree = ref([])
  const expandedIds = ref(new Set())
  const selectedNode = ref(null)
  const loading = ref(false)

  async function fetchNavigations() {
    const { data } = await navigationApi.list({ perPage: 100 })
    navigations.value = data.data ?? data
    return navigations.value
  }

  async function fetchTree(navigationId) {
    if (navigationId) currentId.value = navigationId
    if (!currentId.value) return []
    loading.value = true
    try {
      const { data } = await navigationApi.getTree(currentId.value)
      tree.value = data.data ?? data
      return tree.value
    } finally {
      loading.value = false
    }
  }

  async function createNavigation(payload) {
    const { data } = await navigationApi.create(payload)
    await fetchNavigations()
    return data.data ?? data
  }

  // ─── Knoten ────────────────────────────────────────────────────
  async function createNode(payload) {
    const { data } = await navigationApi.createNode(currentId.value, payload)
    await fetchTree()
    return data.data ?? data
  }

  async function updateNode(nodeId, payload) {
    const { data } = await navigationApi.updateNode(nodeId, payload)
    await fetchTree()
    return data.data ?? data
  }

  async function moveNode(nodeId, payload) {
    await navigationApi.moveNode(nodeId, payload)
    await fetchTree()
  }

  async function deleteNode(nodeId) {
    await navigationApi.deleteNode(nodeId)
    if (selectedNode.value?.id === nodeId) selectedNode.value = null
    await fetchTree()
  }

  function toggleExpand(nodeId) {
    const next = new Set(expandedIds.value)
    next.has(nodeId) ? next.delete(nodeId) : next.add(nodeId)
    expandedIds.value = next
  }

  function selectNode(node) {
    selectedNode.value = node
  }

  return {
    navigations, currentId, tree, expandedIds, selectedNode, loading,
    fetchNavigations, fetchTree, createNavigation,
    createNode, updateNode, moveNode, deleteNode,
    toggleExpand, selectNode,
  }
})
