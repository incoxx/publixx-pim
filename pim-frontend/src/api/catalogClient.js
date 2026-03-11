import axios from 'axios'

const catalogClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
})

// Attach auth token when available (needed for login-protected catalog mode)
catalogClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('pim_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

catalogClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    if (status === 429) {
      console.warn('[Catalog] Rate limited')
    }
    if (status >= 500) {
      console.error('[Catalog] Server error:', error.response?.data)
    }
    return Promise.reject(error)
  },
)

export default catalogClient
