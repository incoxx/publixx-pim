import { defineStore } from 'pinia'
import { ref } from 'vue'
import translationJobsApi from '@/api/translationJobs'

export const useTranslationJobsStore = defineStore('translationJobs', () => {
  const jobs = ref([])
  const currentJob = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })

  async function fetchJobs(params = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await translationJobsApi.list(params)
      jobs.value = data.data || []
      meta.value = data.meta || meta.value
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden der Jobs'
    } finally {
      loading.value = false
    }
  }

  async function fetchJob(id, params = {}) {
    loading.value = true
    error.value = null
    try {
      const { data } = await translationJobsApi.get(id, params)
      currentJob.value = data
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Fehler beim Laden des Jobs'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function createJob(payload) {
    const { data } = await translationJobsApi.create(payload)
    return data.data || data
  }

  async function submitJob(id) {
    const { data } = await translationJobsApi.submit(id)
    return data.data || data
  }

  async function approveJob(id) {
    const { data } = await translationJobsApi.approve(id)
    return data
  }

  async function cancelJob(id) {
    const { data } = await translationJobsApi.cancel(id)
    return data.data || data
  }

  async function retryJob(id) {
    const { data } = await translationJobsApi.retry(id)
    return data.data || data
  }

  async function removeJob(id) {
    await translationJobsApi.remove(id)
    jobs.value = jobs.value.filter(j => j.id !== id)
  }

  return {
    jobs,
    currentJob,
    loading,
    error,
    meta,
    fetchJobs,
    fetchJob,
    createJob,
    submitJob,
    approveJob,
    cancelJob,
    retryJob,
    removeJob,
  }
})
