import { defineStore } from 'pinia'
import { ref } from 'vue'
import translationsApi from '@/api/translations'

export const useTranslationsStore = defineStore('translations', () => {
  const units = ref([])
  const unitsPagination = ref({})
  const currentUnit = ref(null)
  const stats = ref(null)
  const missingUnits = ref([])
  const missingPagination = ref({})
  const loading = ref(false)
  const error = ref(null)

  async function fetchUnits(params = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await translationsApi.getUnits(params)
      units.value = data.data || []
      unitsPagination.value = {
        currentPage: data.current_page,
        lastPage: data.last_page,
        perPage: data.per_page,
        total: data.total,
      }
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  async function fetchUnit(id) {
    loading.value = true
    try {
      const { data } = await translationsApi.getUnit(id)
      currentUnit.value = data
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  async function fetchStats() {
    try {
      const { data } = await translationsApi.getStats()
      stats.value = data
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    }
  }

  async function fetchMissing(params = {}) {
    loading.value = true
    try {
      const { data } = await translationsApi.getMissing(params)
      missingUnits.value = data.data || []
      missingPagination.value = {
        currentPage: data.current_page,
        lastPage: data.last_page,
        perPage: data.per_page,
        total: data.total,
      }
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden'
    } finally {
      loading.value = false
    }
  }

  async function updateTranslation(unitId, lang, translation) {
    try {
      await translationsApi.updateTranslation(unitId, lang, { translation })
      if (currentUnit.value?.id === unitId) {
        await fetchUnit(unitId)
      }
    } catch (e) {
      throw e
    }
  }

  async function retranslate(unitIds, targetLangs = null) {
    const payload = { unit_ids: unitIds }
    if (targetLangs) payload.target_langs = targetLangs
    return translationsApi.retranslate(payload)
  }

  async function triggerIngest() {
    return translationsApi.triggerIngest()
  }

  async function syncToDatabase() {
    return translationsApi.syncToDatabase()
  }

  return {
    units, unitsPagination, currentUnit, stats, missingUnits, missingPagination, loading, error,
    fetchUnits, fetchUnit, fetchStats, fetchMissing, updateTranslation, retranslate, triggerIngest, syncToDatabase,
  }
})
