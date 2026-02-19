import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import productsApi from '@/api/products'

export const useProductStore = defineStore('products', () => {
  const items = ref([])
  const current = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
  const filters = ref({})
  const sort = ref({ field: 'updated_at', order: 'desc' })
  const search = ref('')

  const isEmpty = computed(() => items.value.length === 0 && !loading.value)

  async function fetchList(options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await productsApi.list({
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: sort.value.field,
        order: sort.value.order,
        search: search.value || undefined,
        filters: filters.value,
        include: 'productType',
        ...options,
      })
      items.value = data.data
      if (data.meta) meta.value = data.meta
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id, options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await productsApi.get(id, {
        include: 'attributeValues,variants,media,prices,productType',
        ...options,
      })
      current.value = data.data || data
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  async function create(productData) {
    const { data } = await productsApi.create(productData)
    return data.data || data
  }

  async function update(id, productData) {
    const { data } = await productsApi.update(id, productData)
    // Optimistic update
    if (current.value?.id === id) {
      Object.assign(current.value, data.data || data)
    }
    return data.data || data
  }

  async function remove(id) {
    await productsApi.delete(id)
    items.value = items.value.filter(p => p.id !== id)
    if (current.value?.id === id) current.value = null
  }

  async function saveAttributeValues(id, values) {
    await productsApi.saveAttributeValues(id, values)
  }

  function setSort(field, order) {
    sort.value = { field, order }
  }

  function setSearch(term) {
    search.value = term
    meta.value.current_page = 1
  }

  function setPage(page) {
    meta.value.current_page = page
  }

  function setFilters(newFilters) {
    filters.value = newFilters
    meta.value.current_page = 1
  }

  function $reset() {
    items.value = []
    current.value = null
    loading.value = false
    error.value = null
    meta.value = { current_page: 1, last_page: 1, total: 0, per_page: 50 }
  }

  return {
    items, current, loading, error, meta, filters, sort, search, isEmpty,
    fetchList, fetchOne, create, update, remove, saveAttributeValues,
    setSort, setSearch, setPage, setFilters, $reset,
  }
})
