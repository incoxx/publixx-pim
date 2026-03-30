/**
 * Portal-Embed API Client — Fetch-basiert, analog zu catalog-embed/api.js.
 */

let _baseUrl = '/api/v1'
let _token = null
let _timeout = 15000

export function configureApi({ baseUrl, token, timeout }) {
  if (baseUrl) _baseUrl = baseUrl.replace(/\/+$/, '')
  if (token) _token = token
  if (timeout) _timeout = timeout
}

async function request(path) {
  const url = `${_baseUrl}${path}`
  const headers = { Accept: 'application/json' }
  if (_token) headers.Authorization = `Bearer ${_token}`

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), _timeout)

  try {
    const res = await fetch(url, { headers, signal: controller.signal })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    return await res.json()
  } finally {
    clearTimeout(timer)
  }
}

export const portalApi = {
  getConfig: (slug) => request(`/portal/${slug}/config`),
  getFilterValues: (slug) => request(`/portal/${slug}/filter-values`),
}
