import { defineStore } from 'pinia'
import { ref } from 'vue'
import dictionaryApi from '@/api/dictionary'

export const useDictionaryStore = defineStore('dictionary', () => {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })

  async function fetchEntries(options = {}) {
    loading.value = true
    try {
      const { data } = await dictionaryApi.list({
        perPage: meta.value.per_page,
        page: meta.value.current_page,
        ...options,
      })
      items.value = data.data
      if (data.meta) meta.value = data.meta
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler beim Laden der Wörterbucheinträge'
    } finally {
      loading.value = false
    }
  }

  async function createEntry(entryData) {
    const { data } = await dictionaryApi.create(entryData)
    return data.data || data
  }

  async function updateEntry(id, entryData) {
    const { data } = await dictionaryApi.update(id, entryData)
    return data.data || data
  }

  async function deleteEntry(id) {
    await dictionaryApi.delete(id)
    items.value = items.value.filter(e => e.id !== id)
  }

  return {
    items, loading, error, meta,
    fetchEntries, createEntry, updateEntry, deleteEntry,
  }
})
