import { ref } from 'vue'
import pqlApi from '@/api/pql'

export function usePql() {
  const query = ref('')
  const results = ref([])
  const count = ref(0)
  const loading = ref(false)
  const error = ref(null)
  const validationResult = ref(null)
  const explainResult = ref(null)

  async function execute(pql, options = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await pqlApi.query(pql || query.value, options)
      results.value = data.data || data
      count.value = data.meta?.total || results.value.length
    } catch (e) {
      error.value = e.response?.data?.detail || 'PQL-Fehler'
    } finally {
      loading.value = false
    }
  }

  async function validate(pql) {
    try {
      const { data } = await pqlApi.validate(pql || query.value)
      validationResult.value = data
      return data.valid
    } catch (e) {
      validationResult.value = { valid: false, errors: [e.response?.data?.detail] }
      return false
    }
  }

  async function explain(pql) {
    try {
      const { data } = await pqlApi.explain(pql || query.value)
      explainResult.value = data
    } catch (e) {
      error.value = e.response?.data?.detail || 'Fehler'
    }
  }

  return { query, results, count, loading, error, validationResult, explainResult, execute, validate, explain }
}
