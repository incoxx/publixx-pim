import { defineStore } from 'pinia'
import { ref } from 'vue'
import auditLogsApi from '@/api/auditLogs'

export const useJournalStore = defineStore('journal', () => {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
  const filters = ref({})
  const sort = ref({ field: 'created_at', order: 'desc' })
  const search = ref('')

  async function fetchList(options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await auditLogsApi.list({
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

  async function exportLogs() {
    const params = {}
    if (search.value) params.search = search.value
    for (const [key, value] of Object.entries(filters.value)) {
      if (value) params[`filter[${key}]`] = value
    }
    const { data } = await auditLogsApi.export(params)
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `audit-log-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  async function deleteAll(dateBefore = null) {
    const params = {}
    if (dateBefore) params['filter[date_before]'] = dateBefore
    await auditLogsApi.deleteAll(params)
    await fetchList()
  }

  function setSearch(val) { search.value = val }
  function setFilters(f) { filters.value = f }
  function setPage(page) { meta.value.current_page = page }
  function setSort(field, order) { sort.value = { field, order } }

  return {
    items, loading, error, meta, filters, sort, search,
    fetchList, exportLogs, deleteAll, setSearch, setFilters, setPage, setSort,
  }
})
