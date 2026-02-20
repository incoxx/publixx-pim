import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 30000,
})

// Request interceptor — attach token + locale
client.interceptors.request.use((config) => {
  const authStore = useAuthStore()

  if (authStore.token) {
    config.headers.Authorization = `Bearer ${authStore.token}`
  }

  if (authStore.locale) {
    config.headers['Accept-Language'] = authStore.locale
  }

  return config
})

// Response interceptor — handle errors
client.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status

    if (status === 401) {
      const authStore = useAuthStore()
      // Clear local state directly — don't call authStore.logout() which
      // would POST to /auth/logout, triggering another 401 → infinite loop
      authStore.token = null
      authStore.user = null
      localStorage.removeItem('pim_token')
      router.push({ name: 'login' })
    }

    if (status === 403) {
      console.warn('Permission denied:', error.response?.data)
    }

    if (status === 422) {
      // Validation errors — pass through
      return Promise.reject(error)
    }

    if (status === 429) {
      console.warn('Rate limited')
    }

    if (status >= 500) {
      console.error('Server error:', error.response?.data)
    }

    return Promise.reject(error)
  }
)

export default client

// Helper: build query params from options
export function buildParams(options = {}) {
  const params = {}

  if (options.page) params.page = options.page
  if (options.perPage) params.per_page = options.perPage
  if (options.sort) params.sort = options.sort
  if (options.order) params.order = options.order
  if (options.search) params.search = options.search
  if (options.include) params.include = options.include
  if (options.fields) params.fields = options.fields
  if (options.lang) params.lang = options.lang

  if (options.filters) {
    for (const [key, value] of Object.entries(options.filters)) {
      params[`filter[${key}]`] = value
    }
  }

  return params
}
