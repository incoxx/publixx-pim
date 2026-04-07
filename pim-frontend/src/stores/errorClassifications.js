import { defineStore } from 'pinia'
import { ref } from 'vue'
import errorClassificationsApi from '@/api/errorClassifications'

export const useErrorClassificationsStore = defineStore('errorClassifications', () => {
  const items = ref([])
  const loading = ref(false)
  const classifying = ref(false)
  const hasApiKey = ref(true)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
  const filters = ref({})
  const sort = ref({ field: 'last_seen_at', order: 'desc' })

  async function fetchStatus() {
    try {
      const { data } = await errorClassificationsApi.status()
      hasApiKey.value = data.has_claude_api_key
    } catch {
      // Status-Check scheitert still — Buttons bleiben aktiv
    }
  }

  async function fetchList(options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await errorClassificationsApi.list({
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: sort.value.field,
        order: sort.value.order,
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

  async function runClassification(limit = 50, dryRun = false) {
    classifying.value = true
    error.value = null
    try {
      const { data } = await errorClassificationsApi.classify({ limit, dry_run: dryRun })
      if (!dryRun) await fetchList()
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Klassifikation fehlgeschlagen'
      return null
    } finally {
      classifying.value = false
    }
  }

  async function updateRecord(id, payload) {
    const { data } = await errorClassificationsApi.update(id, payload)
    const idx = items.value.findIndex(i => i.id === id)
    if (idx !== -1) {
      items.value[idx] = data
    }
    return data
  }

  async function deleteAll(params = {}) {
    await errorClassificationsApi.deleteAll(params)
    await fetchList()
  }

  async function exportExcel() {
    const { data } = await errorClassificationsApi.exportExcel({
      sort: sort.value.field,
      order: sort.value.order,
      filters: filters.value,
    })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `fehler-export-${new Date().toISOString().slice(0, 10)}.xlsx`
    a.click()
    URL.revokeObjectURL(url)
  }

  function setFilters(f) { filters.value = f; meta.value.current_page = 1 }
  function setPage(page) { meta.value.current_page = page }
  function setSort(field, order) { sort.value = { field, order } }

  return {
    items, loading, classifying, hasApiKey, error, meta, filters, sort,
    fetchStatus, fetchList, runClassification, updateRecord, deleteAll, exportExcel,
    setFilters, setPage, setSort,
  }
})
