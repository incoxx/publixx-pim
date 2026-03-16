import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import attributesApi, { attributeTypes, valueLists, productTypes } from '@/api/attributes'
import { unitGroups as unitGroupsApi } from '@/api/units'

export const useAttributeStore = defineStore('attributes', () => {
  const items = ref([])
  const allItems = ref([])
  const types = ref([])
  const lists = ref([])
  const prodTypes = ref([])
  const unitGroupsList = ref([])
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })

  const dataTypes = computed(() => [
    'text', 'textarea', 'richtext', 'number', 'decimal', 'boolean',
    'date', 'datetime', 'select', 'multiselect', 'url', 'email', 'json',
  ])

  async function fetchAttributes(options = {}) {
    loading.value = true
    try {
      const { data } = await attributesApi.list({
        perPage: meta.value.per_page,
        page: meta.value.current_page,
        include: 'valueList,unitGroup,children',
        ...options,
      })
      items.value = data.data
      if (data.meta) meta.value = data.meta
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler'
    } finally {
      loading.value = false
    }
  }

  async function fetchAllAttributes() {
    try {
      const { data } = await attributesApi.list({ perPage: 1000, include: 'children' })
      allItems.value = data.data
    } catch (e) {
      console.error('Failed to fetch all attributes', e)
    }
  }

  async function fetchTypes() {
    try {
      const { data } = await attributeTypes.list()
      types.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch attribute types', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Typen'
    }
  }

  async function fetchValueLists() {
    try {
      const { data } = await valueLists.list({ include: 'entries' })
      lists.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch value lists', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Wertelisten'
    }
  }

  async function fetchProductTypes() {
    try {
      const { data } = await productTypes.list()
      prodTypes.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch product types', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Produkttypen'
    }
  }

  async function fetchUnitGroups() {
    try {
      const { data } = await unitGroupsApi.list({ include: 'units' })
      unitGroupsList.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch unit groups', e)
    }
  }

  async function createAttribute(attrData) {
    const { data } = await attributesApi.create(attrData)
    return data.data || data
  }

  async function updateAttribute(id, attrData) {
    const { data } = await attributesApi.update(id, attrData)
    return data.data || data
  }

  async function copyAttribute(id) {
    const { data } = await attributesApi.copy(id)
    return data.data || data
  }

  async function deleteAttribute(id, { force = false } = {}) {
    await attributesApi.delete(id, { force })
    items.value = items.value.filter(a => a.id !== id)
  }

  function setPage(page) {
    meta.value = { ...meta.value, current_page: page }
  }

  async function bulkUpdate(ids, fields) {
    const { data } = await attributesApi.bulkUpdate(ids, fields)
    return data
  }

  return {
    items, allItems, types, lists, prodTypes, unitGroupsList, loading, error, meta, dataTypes,
    fetchAttributes, fetchAllAttributes, fetchTypes, fetchValueLists, fetchProductTypes, fetchUnitGroups,
    createAttribute, updateAttribute, copyAttribute, deleteAttribute, setPage, bulkUpdate,
  }
})
