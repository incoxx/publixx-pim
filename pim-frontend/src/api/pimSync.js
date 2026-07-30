import client from './client'
import { useAuthStore } from '@/stores/auth'

const apiBaseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export default {
  // =====================================================================
  // User-API-Keys (eigene Keys verwalten)
  // =====================================================================

  listApiKeys() {
    return client.get('/user/api-keys')
  },

  createApiKey(data) {
    return client.post('/user/api-keys', data)
  },

  deleteApiKey(tokenId) {
    return client.delete(`/user/api-keys/${tokenId}`)
  },

  // Admin: Alle API-Keys
  adminListApiKeys() {
    return client.get('/admin/api-keys')
  },

  adminUserApiKeys(userId) {
    return client.get(`/admin/users/${userId}/api-keys`)
  },

  adminCreateApiKey(userId, data) {
    return client.post(`/admin/users/${userId}/api-keys`, data)
  },

  adminDeleteApiKey(tokenId) {
    return client.delete(`/admin/api-keys/${tokenId}`)
  },

  // =====================================================================
  // anyPIM Connector-Erweiterungen (Pull / Bidirektional)
  // =====================================================================

  pullProducts(connectionId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/pull-products`, options)
  },

  pullTranslations(connectionId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/pull-translations`, options)
  },

  syncBidirectional(connectionId, options = {}) {
    return client.post(`/connectors/connections/${connectionId}/sync-bidirectional`, options)
  },

  testConnection(connectionId) {
    return client.post(`/connectors/connections/${connectionId}/test-connection`)
  },

  // =====================================================================
  // anyPIM Konfigurations-Sync
  // =====================================================================

  /**
   * Verfügbare Konfigurations-Sektionen auflisten (alles außer Produktdaten).
   */
  configSections() {
    return client.get('/connectors/config-sections')
  },

  /**
   * Konfiguration von der Remote-Instanz holen und lokal importieren,
   * mit SSE-Progress-Streaming und Abbruch-Möglichkeit.
   *
   * @param {string} connectionId
   * @param {string[]} sections  Leer = alle Konfigurations-Sektionen
   * @param {(event: object) => void} onProgress
   * @returns {Promise<object>}
   */
  pullConfigWithProgress(connectionId, sections = [], onProgress = null) {
    const auth = useAuthStore()
    const token = auth.token

    const xsrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    return new Promise((resolve, reject) => {
      fetch(`${apiBaseURL}/connectors/connections/${connectionId}/pull-config`, {
        method: 'POST',
        headers: {
          Accept: 'text/event-stream',
          'Content-Type': 'application/json',
          Authorization: token ? `Bearer ${token}` : '',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ sections }),
      })
        .then(async (response) => {
          if (!response.ok) {
            const text = await response.text()
            try {
              const json = JSON.parse(text)
              reject(new Error(json.error || json.message || `Konfigurations-Pull fehlgeschlagen (HTTP ${response.status})`))
            } catch {
              const snippet = text.length > 200 ? text.slice(0, 200) + '…' : text
              reject(new Error(`Konfigurations-Pull fehlgeschlagen (HTTP ${response.status}): ${snippet}`))
            }
            return
          }

          this._handleSseResponse(response, onProgress, resolve, reject)
        })
        .catch(reject)
    })
  },

  /**
   * Gemeinsame SSE-Response-Verarbeitung (identisch zum JSON-/BMEcat-Import-Client).
   */
  _handleSseResponse(response, onProgress, resolve, reject) {
    const contentType = response.headers.get('content-type') || ''

    if (!contentType.includes('text/event-stream')) {
      response.json().then((json) => {
        if (json.error) {
          reject(new Error(json.error))
        } else {
          resolve(json.data || json)
        }
      }).catch(reject)
      return
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    let settled = false
    const settledResolve = (v) => {
      if (!settled) { settled = true; resolve(v) }
    }
    const settledReject = (e) => {
      if (!settled) { settled = true; reject(e) }
    }

    const processLines = () => {
      const lines = buffer.split('\n')
      buffer = lines.pop()

      let currentEvent = null
      for (const line of lines) {
        if (line.startsWith('event: ')) {
          currentEvent = line.slice(7).trim()
        } else if (line.startsWith('data: ')) {
          const data = line.slice(6)
          try {
            const parsed = JSON.parse(data)
            if (currentEvent === 'progress' && onProgress) {
              onProgress(parsed)
            } else if (currentEvent === 'complete') {
              settledResolve(parsed.data || parsed)
            } else if (currentEvent === 'cancelled') {
              settledReject(new Error(parsed.message || 'Import wurde abgebrochen.'))
            } else if (currentEvent === 'error') {
              settledReject(new Error(parsed.error || parsed.message || 'Konfigurations-Pull fehlgeschlagen'))
            }
          } catch {
            // Skip malformed JSON
          }
          currentEvent = null
        }
      }
    }

    const read = async () => {
      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })
        processLines()
      }
      if (buffer.trim()) {
        buffer += '\n'
        processLines()
      }
      if (!settled) {
        settledReject(new Error('Verbindung wurde unerwartet beendet. Bitte Log prüfen.'))
      }
    }

    read().catch(settledReject)
  },

  /**
   * Laufenden Konfigurations-Pull abbrechen (nutzt denselben Cancel-Kanal wie der JSON-Import).
   */
  cancelConfigPull(importId) {
    return client.post('/json-import/cancel', { import_id: importId })
  },

  // =====================================================================
  // anyPIM Fehlende-Medien-Sync
  // =====================================================================

  /**
   * Anzahl lokaler Media-Einträge ohne physische Datei ermitteln.
   */
  missingMediaCount(connectionId) {
    return client.get(`/connectors/connections/${connectionId}/missing-media-count`)
  },

  /**
   * Fehlende Mediendateien von der Remote-Instanz nachladen,
   * mit SSE-Progress-Streaming und Abbruch-Möglichkeit (identisch zu pullConfigWithProgress).
   *
   * @param {string} connectionId
   * @param {(event: object) => void} onProgress
   * @returns {Promise<object>}
   */
  pullMissingMediaWithProgress(connectionId, onProgress = null) {
    const auth = useAuthStore()
    const token = auth.token

    const xsrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    return new Promise((resolve, reject) => {
      fetch(`${apiBaseURL}/connectors/connections/${connectionId}/pull-missing-media`, {
        method: 'POST',
        headers: {
          Accept: 'text/event-stream',
          'Content-Type': 'application/json',
          Authorization: token ? `Bearer ${token}` : '',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
      })
        .then(async (response) => {
          if (!response.ok) {
            const text = await response.text()
            try {
              const json = JSON.parse(text)
              reject(new Error(json.error || json.message || `Media-Pull fehlgeschlagen (HTTP ${response.status})`))
            } catch {
              const snippet = text.length > 200 ? text.slice(0, 200) + '…' : text
              reject(new Error(`Media-Pull fehlgeschlagen (HTTP ${response.status}): ${snippet}`))
            }
            return
          }

          this._handleSseResponse(response, onProgress, resolve, reject)
        })
        .catch(reject)
    })
  },

  /**
   * Laufenden Media-Pull abbrechen (nutzt denselben Cancel-Kanal wie der JSON-Import).
   */
  cancelMissingMediaPull(importId) {
    return client.post('/json-import/cancel', { import_id: importId })
  },

  // =====================================================================
  // anyPIM Suchprofil-Push
  // =====================================================================

  /**
   * Alle Produkte, die einem gespeicherten Suchprofil entsprechen, zur
   * Remote-Instanz pushen, mit SSE-Progress-Streaming und Abbruch-Möglichkeit
   * (identisch zu pullConfigWithProgress).
   *
   * @param {string} connectionId
   * @param {string} searchProfileId
   * @param {(event: object) => void} onProgress
   * @returns {Promise<object>}
   */
  pushBySearchProfileWithProgress(connectionId, searchProfileId, onProgress = null) {
    const auth = useAuthStore()
    const token = auth.token

    const xsrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    return new Promise((resolve, reject) => {
      fetch(`${apiBaseURL}/connectors/connections/${connectionId}/push-search-profile`, {
        method: 'POST',
        headers: {
          Accept: 'text/event-stream',
          'Content-Type': 'application/json',
          Authorization: token ? `Bearer ${token}` : '',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ search_profile_id: searchProfileId }),
      })
        .then(async (response) => {
          if (!response.ok) {
            const text = await response.text()
            try {
              const json = JSON.parse(text)
              reject(new Error(json.error || json.message || `Suchprofil-Push fehlgeschlagen (HTTP ${response.status})`))
            } catch {
              const snippet = text.length > 200 ? text.slice(0, 200) + '…' : text
              reject(new Error(`Suchprofil-Push fehlgeschlagen (HTTP ${response.status}): ${snippet}`))
            }
            return
          }

          this._handleSseResponse(response, onProgress, resolve, reject)
        })
        .catch(reject)
    })
  },

  /**
   * Laufenden Suchprofil-Push abbrechen (nutzt denselben Cancel-Kanal wie der JSON-Import).
   */
  cancelSearchProfilePush(importId) {
    return client.post('/json-import/cancel', { import_id: importId })
  },
}
