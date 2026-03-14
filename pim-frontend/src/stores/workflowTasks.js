import { defineStore } from 'pinia'
import { ref } from 'vue'
import workflowTasksApi from '@/api/workflowTasks'

export const useWorkflowTasksStore = defineStore('workflowTasks', () => {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
  const filters = ref({})
  const sort = ref({ field: 'created_at', order: 'desc' })
  const search = ref('')

  async function fetchList(options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await workflowTasksApi.list({
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: sort.value.field,
        order: sort.value.order,
        search: search.value || undefined,
        filters: filters.value,
        ...options,
      })
      items.value = data.data || []
      if (data.meta) {
        meta.value = { ...meta.value, ...data.meta }
      }
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  async function createTask(data) {
    const response = await workflowTasksApi.create(data)
    await fetchList()
    return response
  }

  async function updateTask(id, data) {
    const response = await workflowTasksApi.update(id, data)
    await fetchList()
    return response
  }

  async function deleteTask(id) {
    await workflowTasksApi.delete(id)
    await fetchList()
  }

  function setSearch(val) { search.value = val }
  function setFilters(f) { filters.value = f }
  function setPage(page) { meta.value.current_page = page }
  function setSort(field, order) { sort.value = { field, order } }

  return {
    items, loading, error, meta, filters, sort, search,
    fetchList, createTask, updateTask, deleteTask,
    setSearch, setFilters, setPage, setSort,
  }
})
