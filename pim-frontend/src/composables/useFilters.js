import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'

export function useFilters(fetchFn, debounceMs = 250) {
  const search = ref('')
  const filters = ref({})
  const activeFilters = ref([])

  const debouncedFetch = useDebounceFn(() => {
    fetchFn()
  }, debounceMs)

  function setSearch(term) {
    search.value = term
    debouncedFetch()
  }

  function addFilter(key, value, label) {
    filters.value[key] = value
    activeFilters.value.push({ key, value, label: label || `${key}: ${value}` })
    fetchFn()
  }

  function removeFilter(key) {
    delete filters.value[key]
    activeFilters.value = activeFilters.value.filter(f => f.key !== key)
    fetchFn()
  }

  function clearFilters() {
    filters.value = {}
    activeFilters.value = []
    search.value = ''
    fetchFn()
  }

  return { search, filters, activeFilters, setSearch, addFilter, removeFilter, clearFilters }
}
