import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import attributesApi, { attributeTypes, valueLists, productTypes } from '@/api/attributes'

export const useAttributeStore = defineStore('attributes', () => {
  const items = ref([])
  const types = ref([])
  const lists = ref([])
  const prodTypes = ref([])
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

  async function fetchTypes() {
    const { data } = await attributeTypes.list()
    types.value = data.data || data
  }

  async function fetchValueLists() {
    const { data } = await valueLists.list({ include: 'entries' })
    lists.value = data.data || data
  }

  async function fetchProductTypes() {
    const { data } = await productTypes.list()
    prodTypes.value = data.data || data
  }

  async function createAttribute(attrData) {
    const { data } = await attributesApi.create(attrData)
    return data.data || data
  }

  async function updateAttribute(id, attrData) {
    const { data } = await attributesApi.update(id, attrData)
    return data.data || data
  }

  async function deleteAttribute(id) {
    await attributesApi.delete(id)
    items.value = items.value.filter(a => a.id !== id)
  }

  return {
    items, types, lists, prodTypes, loading, error, meta, dataTypes,
    fetchAttributes, fetchTypes, fetchValueLists, fetchProductTypes,
    createAttribute, updateAttribute, deleteAttribute,
  }
})
