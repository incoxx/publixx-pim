import axios from 'axios'

const catalogClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
})

// Key für das Freigabelink-Access-Token (von /catalog/share/{token} ausgegeben).
// sessionStorage statt localStorage: gilt nur für den aktuellen Tab/die Sitzung des
// Empfängers und wird nicht dauerhaft auf fremden Geräten gespeichert.
export const CATALOG_SHARE_KEY = 'pim_catalog_share_access'

// Attach auth token when available (needed for login-protected catalog mode)
catalogClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('pim_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Freigabelink-Zugang: erlaubt Katalogzugriff ohne PIM-Login.
  const shareAccess = sessionStorage.getItem(CATALOG_SHARE_KEY)
  if (shareAccess) {
    config.headers['X-Catalog-Share'] = shareAccess
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
    // Abgelaufenes/widerrufenes Freigabelink-Token nicht in der Sitzung liegen lassen.
    if (status === 401) {
      sessionStorage.removeItem(CATALOG_SHARE_KEY)
    }
    if (status >= 500) {
      console.error('[Catalog] Server error:', error.response?.data)
    }
    return Promise.reject(error)
  },
)

export default catalogClient
