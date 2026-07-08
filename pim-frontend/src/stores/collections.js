import { defineStore } from 'pinia'
import { ref } from 'vue'
import { collections as collectionsApi, collectionTypes as collectionTypesApi, collectionItems as collectionItemsApi } from '@/api/collections'

export const useCollectionsStore = defineStore('collections', () => {
  const items = ref([])
  const types = ref([])
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })

  const currentItems = ref([])
  const currentItemsLoading = ref(false)

  async function fetchCollections(options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await collectionsApi.list({
        include: 'collectionType,organization',
        ...options,
      })
      items.value = data.data
      if (data.meta) meta.value = data.meta
    } catch (e) {
      error.value = e.response?.data?.title || e.response?.data?.detail || 'Fehler beim Laden der Collections'
    } finally {
      loading.value = false
    }
  }

  async function fetchTypes() {
    try {
      const { data } = await collectionTypesApi.list({ filters: { is_active: true }, perPage: 100 })
      types.value = data.data
    } catch (e) {
      console.error('Failed to fetch collection types', e)
    }
  }

  async function createCollection(data) {
    const { data: res } = await collectionsApi.create(data)
    return res.data
  }

  async function updateCollection(id, data) {
    const { data: res } = await collectionsApi.update(id, data)
    return res.data
  }

  async function deleteCollection(id) {
    await collectionsApi.delete(id)
    items.value = items.value.filter((c) => c.id !== id)
  }

  async function fetchItems(collectionId) {
    currentItemsLoading.value = true
    try {
      const { data } = await collectionItemsApi.list(collectionId, { include: 'product,unit' })
      currentItems.value = data.data
    } finally {
      currentItemsLoading.value = false
    }
  }

  async function addItem(collectionId, data) {
    const { data: res } = await collectionItemsApi.create(collectionId, data)
    await fetchItems(collectionId)
    return res.data
  }

  async function updateItem(collectionId, itemId, data) {
    await collectionItemsApi.update(collectionId, itemId, data)
    await fetchItems(collectionId)
  }

  async function removeItem(collectionId, itemId) {
    await collectionItemsApi.delete(collectionId, itemId)
    currentItems.value = currentItems.value.filter((i) => i.id !== itemId)
  }

  async function reorderItems(collectionId, itemIds) {
    // Optimistisch: Reihenfolge sofort lokal setzen, Server bestaetigt per 10er-Schritten.
    currentItems.value = itemIds.map((id) => currentItems.value.find((i) => i.id === id)).filter(Boolean)
    await collectionItemsApi.reorder(collectionId, itemIds)
    await fetchItems(collectionId)
  }

  return {
    items, types, loading, error, meta,
    currentItems, currentItemsLoading,
    fetchCollections, fetchTypes,
    createCollection, updateCollection, deleteCollection,
    fetchItems, addItem, updateItem, removeItem, reorderItems,
  }
})
