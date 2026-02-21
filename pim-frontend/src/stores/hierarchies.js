import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import hierarchiesApi from '@/api/hierarchies'

export const useHierarchyStore = defineStore('hierarchies', () => {
  const hierarchies = ref([])
  const currentHierarchy = ref(null)
  const tree = ref([])
  const selectedNode = ref(null)
  const expandedNodes = ref(new Set())
  const loading = ref(false)
  const error = ref(null)

  const isMasterHierarchy = computed(() => currentHierarchy.value?.hierarchy_type === 'master')

  async function fetchHierarchies(options = {}) {
    loading.value = true
    try {
      const { data } = await hierarchiesApi.list(options)
      hierarchies.value = data.data || data
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler'
    } finally {
      loading.value = false
    }
  }

  async function fetchTree(hierarchyId, options = {}) {
    loading.value = true
    try {
      const { data } = await hierarchiesApi.getTree(hierarchyId, options)
      tree.value = data.data || data
      currentHierarchy.value = hierarchies.value.find(h => h.id === hierarchyId) || null
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler'
    } finally {
      loading.value = false
    }
  }

  async function createHierarchy(data) {
    const { data: resp } = await hierarchiesApi.create(data)
    return resp.data || resp
  }

  async function updateHierarchy(id, data) {
    const { data: resp } = await hierarchiesApi.update(id, data)
    return resp.data || resp
  }

  async function deleteHierarchy(id) {
    await hierarchiesApi.delete(id)
  }

  async function createNode(hierarchyId, nodeData) {
    const { data } = await hierarchiesApi.createNode(hierarchyId, nodeData)
    return data.data || data
  }

  async function updateNode(nodeId, nodeData) {
    const { data } = await hierarchiesApi.updateNode(nodeId, nodeData)
    return data.data || data
  }

  async function deleteNode(nodeId) {
    await hierarchiesApi.deleteNode(nodeId)
  }

  async function moveNode(nodeId, targetParentId, sortOrder) {
    await hierarchiesApi.moveNode(nodeId, {
      parent_node_id: targetParentId,
      sort_order: sortOrder,
    })
  }

  async function duplicateNode(nodeId) {
    const { data } = await hierarchiesApi.duplicateNode(nodeId)
    return data.data || data
  }

  function selectNode(node) {
    selectedNode.value = node
  }

  function toggleExpanded(nodeId) {
    if (expandedNodes.value.has(nodeId)) {
      expandedNodes.value.delete(nodeId)
    } else {
      expandedNodes.value.add(nodeId)
    }
  }

  function isExpanded(nodeId) {
    return expandedNodes.value.has(nodeId)
  }

  return {
    hierarchies, currentHierarchy, tree, selectedNode, expandedNodes, loading, error,
    isMasterHierarchy,
    fetchHierarchies, fetchTree,
    createHierarchy, updateHierarchy, deleteHierarchy,
    createNode, updateNode, deleteNode, moveNode, duplicateNode,
    selectNode, toggleExpanded, isExpanded,
  }
})
