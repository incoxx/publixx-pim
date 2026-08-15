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
  const unitsLoading = ref(false)
  const unitLoading = ref(false)
  const statsLoading = ref(false)
  const missingLoading = ref(false)
  const error = ref(null)

  async function fetchUnits(params = {}) {
    unitsLoading.value = true
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
      error.value = e.response?.data?.message || 'Fehler beim Laden der Begriffe'
    } finally {
      unitsLoading.value = false
    }
  }

  async function fetchUnit(id) {
    unitLoading.value = true
    error.value = null
    try {
      const { data } = await translationsApi.getUnit(id)
      currentUnit.value = data
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden der Übersetzungseinheit'
    } finally {
      unitLoading.value = false
    }
  }

  async function fetchStats() {
    statsLoading.value = true
    error.value = null
    try {
      const { data } = await translationsApi.getStats()
      stats.value = data
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden der Statistiken'
    } finally {
      statsLoading.value = false
    }
  }

  async function fetchMissing(params = {}) {
    missingLoading.value = true
    error.value = null
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
      error.value = e.response?.data?.message || 'Fehler beim Laden fehlender Übersetzungen'
    } finally {
      missingLoading.value = false
    }
  }

  async function updateTranslation(unitId, lang, translation) {
    await translationsApi.updateTranslation(unitId, lang, { translation })
    if (currentUnit.value?.id === unitId) {
      await fetchUnit(unitId)
    }
  }

  async function retranslate(unitIds, targetLangs = null) {
    const payload = { unit_ids: unitIds }
    if (targetLangs) payload.target_langs = targetLangs
    return translationsApi.retranslate(payload)
  }

  /**
   * Rollt eine Zielsprache ueber den gesamten Bestand aus.
   *
   * Der Endpunkt ist gedeckelt, damit die Queue bei grossen Bestaenden nicht
   * in einem Rutsch geflutet wird. Wir schleifen hier, bis nichts mehr offen
   * ist, und melden den Fortschritt ueber onProgress zurueck.
   */
  async function translateMissing(lang, onProgress = null) {
    const MAX_ROUNDS = 1000
    let total = 0

    for (let round = 0; round < MAX_ROUNDS; round++) {
      const { data } = await translationsApi.translateMissing(lang)

      // Fehler liefert der Proxy als leeres Objekt — nicht als "fertig" werten
      if (typeof data?.dispatched !== 'number') {
        throw new Error('Keine gültige Antwort vom TMS erhalten.')
      }

      total += data.dispatched
      if (onProgress) onProgress(total, data.remaining ?? 0)

      if (data.dispatched === 0 || (data.remaining ?? 0) === 0) break
    }

    return total
  }

  async function importGlossary(file) {
    return translationsApi.importGlossary(file)
  }

  async function triggerIngest() {
    return translationsApi.triggerIngest()
  }

  async function syncToDatabase() {
    return translationsApi.syncToDatabase()
  }

  async function deleteAllTranslations(targetLang = null) {
    return translationsApi.deleteAllTranslations(targetLang)
  }

  async function purgeAllUnits() {
    return translationsApi.purgeAllUnits()
  }

  return {
    units, unitsPagination, currentUnit, stats, missingUnits, missingPagination,
    unitsLoading, unitLoading, statsLoading, missingLoading, error,
    fetchUnits, fetchUnit, fetchStats, fetchMissing, updateTranslation, retranslate, translateMissing, importGlossary, triggerIngest, syncToDatabase, deleteAllTranslations, purgeAllUnits,
  }
})
