import client from './client'
import { useAuthStore } from '@/stores/auth'

const apiBaseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export default {
  /**
   * BMEcat-XML-Datei importieren (Standard JSON response).
   */
  importFile(file, mode = 'update', productType = null) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)
    if (productType) {
      formData.append('product_type', productType)
    }
    return client.post('/bmecat-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 600000,
    })
  },

  /**
   * BMEcat-XML-Datei importieren mit SSE-Progress-Streaming.
   *
   * @param {File} file
   * @param {string} mode
   * @param {string|null} productType
   * @param {(event: {phase: string, message: string, current: number, total: number, stats?: object}) => void} onProgress
   * @returns {Promise<object>} Final import result
   */
  importFileWithProgress(file, mode = 'update', productType = null, onProgress = null) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)
    if (productType) {
      formData.append('product_type', productType)
    }

    const auth = useAuthStore()
    const token = auth.token

    // CSRF-Token aus Cookie lesen (Sanctum stateful requests)
    const xsrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    return new Promise((resolve, reject) => {
      fetch(`${apiBaseURL}/bmecat-import`, {
        method: 'POST',
        headers: {
          Accept: 'text/event-stream',
          Authorization: token ? `Bearer ${token}` : '',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        credentials: 'same-origin',
        body: formData,
      })
        .then(async (response) => {
          if (!response.ok) {
            const text = await response.text()
            try {
              const json = JSON.parse(text)
              reject(
                new Error(
                  json.error ||
                    json.message ||
                    (json.details ? JSON.stringify(json.details) : null) ||
                    `Import fehlgeschlagen (HTTP ${response.status})`,
                ),
              )
            } catch {
              // Non-JSON response (e.g. HTML error page) — include status
              const snippet = text.length > 200 ? text.slice(0, 200) + '…' : text
              reject(new Error(`Import fehlgeschlagen (HTTP ${response.status}): ${snippet}`))
            }
            return
          }

          const contentType = response.headers.get('content-type') || ''

          // Non-SSE response (validation error returned as JSON)
          if (!contentType.includes('text/event-stream')) {
            const json = await response.json()
            if (json.error) {
              reject(new Error(json.error))
            } else {
              resolve(json.data || json)
            }
            return
          }

          // Parse SSE stream
          const reader = response.body.getReader()
          const decoder = new TextDecoder()
          let buffer = ''
          let settled = false
          const settledResolve = (v) => {
            if (!settled) {
              settled = true
              resolve(v)
            }
          }
          const settledReject = (e) => {
            if (!settled) {
              settled = true
              reject(e)
            }
          }

          const processLines = () => {
            const lines = buffer.split('\n')
            buffer = lines.pop() // Keep incomplete line in buffer

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
                    settledReject(
                      new Error(parsed.message || 'Import wurde abgebrochen.'),
                    )
                  } else if (currentEvent === 'error') {
                    settledReject(
                      new Error(parsed.error || parsed.message || 'Import fehlgeschlagen'),
                    )
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
            // Process remaining buffer
            if (buffer.trim()) {
              buffer += '\n'
              processLines()
            }
            // Stream ended without complete/error event
            if (!settled) {
              settledReject(
                new Error(
                  'Import-Verbindung wurde unerwartet beendet. Bitte Import-Log prüfen.',
                ),
              )
            }
          }

          read().catch(settledReject)
        })
        .catch(reject)
    })
  },

  /**
   * Laufenden BMEcat-Import abbrechen.
   */
  cancelImport(importId) {
    return client.post('/bmecat-import/cancel', { import_id: importId })
  },

  /**
   * BMEcat-XML-Datei validieren (ohne Import).
   */
  validateFile(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/bmecat-import/validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  /**
   * PIM-Daten als BMEcat-XML exportieren.
   * @param {object} params
   * @param {(progress: {loaded: number, total: number, percent: number}) => void} onProgress
   */
  exportFile(params = {}, onProgress = null) {
    return client.post('/bmecat-export', params, {
      responseType: 'blob',
      timeout: 300000,
      onDownloadProgress: onProgress
        ? (progressEvent) => {
            const total = progressEvent.total || 0
            const loaded = progressEvent.loaded || 0
            const percent = total > 0 ? Math.round((loaded / total) * 100) : 0
            onProgress({ loaded, total, percent })
          }
        : undefined,
    })
  },
}
