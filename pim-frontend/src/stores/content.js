import { defineStore } from 'pinia'
import { ref } from 'vue'
import contentApi from '@/api/content'

/**
 * Store für strukturierten Content (Seiten + Sektionen).
 * Spiegelt das Produkt-Store-Muster: items (Liste) + current (Detail).
 */
export const useContentStore = defineStore('content', () => {
  const items = ref([])
  const current = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })
  const filters = ref({})
  const sort = ref({ field: 'updated_at', order: 'desc' })
  const search = ref('')

  // Referenzdaten (Typen) — selten geändert, einmal laden
  const contentTypes = ref([])
  const sectionTypes = ref([])

  async function fetchList(options = {}) {
    loading.value = true
    error.value = null
    try {
      const params = {
        page: meta.value.current_page,
        perPage: meta.value.per_page,
        sort: sort.value.field,
        order: sort.value.order,
        filters: filters.value,
        search: search.value || undefined,
        ...options,
      }
      const { data } = await contentApi.list(params)
      items.value = data.data ?? data
      if (data.meta) meta.value = data.meta
      return items.value
    } catch (e) {
      error.value = e
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id, options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await contentApi.get(id, {
        include: 'contentType,sections,sections.sectionType',
        ...options,
      })
      current.value = data.data ?? data
      return current.value
    } catch (e) {
      error.value = e
      throw e
    } finally {
      loading.value = false
    }
  }

  async function create(payload) {
    const { data } = await contentApi.create(payload)
    return data.data ?? data
  }

  async function update(id, payload) {
    const { data } = await contentApi.update(id, payload)
    const updated = data.data ?? data
    if (current.value?.id === id) current.value = { ...current.value, ...updated }
    return updated
  }

  async function remove(id) {
    await contentApi.delete(id)
    items.value = items.value.filter((p) => p.id !== id)
  }

  // ─── Sektionen ────────────────────────────────────────────────
  async function addSection(pageId, payload) {
    const { data } = await contentApi.createSection(pageId, payload)
    return data.data ?? data
  }

  async function updateSection(sectionId, payload) {
    const { data } = await contentApi.updateSection(sectionId, payload)
    return data.data ?? data
  }

  async function moveSection(sectionId, payload) {
    const { data } = await contentApi.moveSection(sectionId, payload)
    return data.data ?? data
  }

  async function removeSection(sectionId) {
    await contentApi.deleteSection(sectionId)
  }

  // ─── Referenzdaten ────────────────────────────────────────────
  async function loadContentTypes(force = false) {
    if (contentTypes.value.length && !force) return contentTypes.value
    const { data } = await contentApi.listTypes({ perPage: 200 })
    contentTypes.value = data.data ?? data
    return contentTypes.value
  }

  async function loadSectionTypes(force = false) {
    if (sectionTypes.value.length && !force) return sectionTypes.value
    const { data } = await contentApi.listSectionTypes({ perPage: 200 })
    sectionTypes.value = data.data ?? data
    return sectionTypes.value
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

  return {
    items, current, loading, error, meta, filters, sort, search,
    contentTypes, sectionTypes,
    fetchList, fetchOne, create, update, remove,
    addSection, updateSection, moveSection, removeSection,
    loadContentTypes, loadSectionTypes,
    setSort, setSearch, setPage, setFilters,
  }
})
