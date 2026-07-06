import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const apiBaseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

const client = axios.create({
  baseURL: apiBaseURL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 30000,
})

// Request interceptor — attach token + locale
client.interceptors.request.use((config) => {
  const authStore = useAuthStore()

  // config.anonymous = true → ohne Auth-Header senden (echte öffentliche
  // Anfrage, z. B. die Public-Site-Seite → nutzt den anonymen Cache).
  if (authStore.token && !config.anonymous) {
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
  async (error) => {
    const status = error.response?.status

    if (status === 401) {
      const authStore = useAuthStore()
      // Clear local state directly — don't call authStore.logout() which
      // would POST to /auth/logout, triggering another 401 → infinite loop
      authStore.token = null
      authStore.user = null
      localStorage.removeItem('pim_token')

      // Auf öffentlichen Routen (z. B. /site/...) und bei bewusst anonymen
      // Anfragen NICHT zur Login-Seite umleiten — sonst kapert ein 401 einer
      // Hintergrund-/optionalen Anfrage die öffentliche Seite. Die jeweilige
      // View entscheidet selbst (z. B. „Anmeldung erforderlich").
      const isPublicRoute = router.currentRoute.value?.meta?.public
      if (!isPublicRoute && !error.config?.anonymous) {
        router.push({ name: 'login' })
      }
    }

    if (status === 403) {
      console.warn('Permission denied:', error.response?.data)
    }

    if (status === 422) {
      // Validation errors — pass through
      return Promise.reject(error)
    }

    if (status === 429) {
      console.warn('Rate limited — retrying after delay')
      const retryCount = error.config._retryCount || 0
      if (retryCount < 3) {
        error.config._retryCount = retryCount + 1
        const retryAfter = error.response?.headers?.['retry-after']
        const delay = retryAfter ? parseInt(retryAfter, 10) * 1000 : (retryCount + 1) * 1000
        await new Promise(resolve => setTimeout(resolve, delay))
        return client.request(error.config)
      }
    }

    if (status >= 500) {
      console.error('Server error:', error.response?.data)
    }

    return Promise.reject(error)
  }
)

export default client

/**
 * Resolves a full, absolute URL for an API path (e.g. "export-profiles/uuid/stream").
 * Respects VITE_API_BASE_URL so sub-directory deployments (e.g. /web) work correctly.
 */
export function resolveApiUrl(path) {
  // apiBaseURL is e.g. "/web/api/v1" or "/api/v1"
  const base = apiBaseURL.replace(/\/+$/, '')
  const clean = path.replace(/^\/+/, '')
  const fullPath = `${base}/${clean}`
  // If baseURL is already absolute (https://...), return as-is
  if (/^https?:\/\//.test(fullPath)) return fullPath
  return `${window.location.origin}${fullPath}`
}

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

  if (options.inherited) params.inherited = 'true'
  if (options.attribute_columns) params.attribute_columns = options.attribute_columns
  if (options.language) params.language = options.language
  if (options.include_thumbnail) params.include_thumbnail = '1'
  if (options.include_descendants !== undefined) params.include_descendants = options.include_descendants ? '1' : '0'
  if (options.include_renditions !== undefined) params.include_renditions = options.include_renditions ? '1' : '0'

  return params
}
