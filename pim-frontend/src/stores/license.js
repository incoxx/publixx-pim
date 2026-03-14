import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import licenseApi from '@/api/license'

export const useLicenseStore = defineStore('license', () => {
  const data = ref(null)
  const loading = ref(false)
  const loaded = ref(false)

  const isEnterprise = computed(() => data.value?.edition === 'enterprise' && data.value?.valid)
  const modules = computed(() => data.value?.modules || {})
  const customer = computed(() => data.value?.customer || null)
  const expiresAt = computed(() => data.value?.expires_at || null)
  const daysRemaining = computed(() => data.value?.days_remaining)
  const maxUsers = computed(() => data.value?.max_users || 0)
  const currentUsers = computed(() => data.value?.current_users || 0)

  function isModuleActive(key) {
    return modules.value[key]?.licensed === true
  }

  async function fetchLicense() {
    if (loading.value) return
    loading.value = true
    try {
      const response = await licenseApi.get()
      data.value = response.data.data || response.data
      loaded.value = true
    } catch {
      // License endpoint might not be available
      data.value = null
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function activateLicense(key) {
    const response = await licenseApi.activate(key)
    data.value = response.data.data || response.data
    return data.value
  }

  function reset() {
    data.value = null
    loaded.value = false
  }

  return {
    data,
    loading,
    loaded,
    isEnterprise,
    modules,
    customer,
    expiresAt,
    daysRemaining,
    maxUsers,
    currentUsers,
    isModuleActive,
    fetchLicense,
    activateLicense,
    reset,
  }
})
