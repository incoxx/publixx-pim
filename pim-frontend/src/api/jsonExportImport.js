import client from './client'
import { useAuthStore } from '@/stores/auth'

const apiBaseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

export default {
  // --- JSON Export ---

  /**
   * Vollexport als JSON-Download.
   */
  exportAll(params = {}) {
    return client.get('/json-export', {
      params,
      responseType: 'blob',
    })
  },

  /**
   * Gefilterter Export (Download oder inline).
   */
  exportFiltered(data) {
    return client.post('/json-export', data, {
      responseType: data.inline ? 'json' : 'blob',
    })
  },

  /**
   * Verfügbare Sektionen auflisten.
   */
  sections() {
    return client.get('/json-export/sections')
  },

  /**
   * Async-Export starten — gibt sofort {export_key} zurück.
   */
  startAsync(data) {
    return client.post('/json-export/start', data)
  },

  /**
   * Fortschritt eines laufenden Async-Exports abfragen.
   */
  exportProgress(exportKey) {
    return client.get(`/json-export/progress/${exportKey}`)
  },

  /**
   * Laufenden Async-Export abbrechen.
   */
  cancelExport(exportKey) {
    return client.post(`/json-export/cancel/${exportKey}`)
  },

  /**
   * Fertige Export-Datei herunterladen.
   */
  downloadExport(exportKey) {
    return client.get(`/json-export/download/${exportKey}`, { responseType: 'blob' })
  },

  // --- JSON Import ---

  /**
   * JSON-Datei importieren.
   */
  importFile(file, mode = 'update') {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)
    return client.post('/json-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    })
  },

  /**
   * JSON-Datei importieren mit SSE-Progress-Streaming und Abbruch-Möglichkeit.
   *
   * @param {File} file
   * @param {string} mode
   * @param {(event: object) => void} onProgress
   * @returns {Promise<object>}
   */
  async importFileWithProgress(file, mode = 'update', onProgress = null) {
    const text = await file.text()
    let data
    try {
      data = JSON.parse(text)
    } catch {
      throw new Error('Ungültiges JSON in der Datei.')
    }
    return this.importDataWithProgress(data, mode, onProgress)
  },

  /**
   * JSON-Daten direkt importieren.
   */
  importData(data, mode = 'update') {
    return client.post(`/json-import?mode=${mode}`, data, {
      timeout: 120000,
    })
  },

  /**
   * JSON-Daten importieren mit SSE-Progress-Streaming (pro Sektion) und Abbruch-Möglichkeit.
   *
   * @param {object} data  Import-JSON (inkl. _meta + Sektionen)
   * @param {string} mode
   * @param {(event: {phase: string, message: string, current: number, total: number, stats?: object, import_id?: string}) => void} onProgress
   * @returns {Promise<object>} Final import result
   */
  importDataWithProgress(data, mode = 'update', onProgress = null) {
    const auth = useAuthStore()
    const token = auth.token

    const xsrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    return new Promise((resolve, reject) => {
      fetch(`${apiBaseURL}/json-import?mode=${mode}`, {
        method: 'POST',
        headers: {
          Accept: 'text/event-stream',
          'Content-Type': 'application/json',
          Authorization: token ? `Bearer ${token}` : '',
          ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(data),
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
              const snippet = text.length > 200 ? text.slice(0, 200) + '…' : text
              reject(new Error(`Import fehlgeschlagen (HTTP ${response.status}): ${snippet}`))
            }
            return
          }

          this._handleSseResponse(response, onProgress, resolve, reject)
        })
        .catch(reject)
    })
  },

  /**
   * Gemeinsame SSE-Response-Verarbeitung (identisch zum BMEcat-Import-Client).
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
              settledReject(new Error(parsed.error || parsed.message || 'Import fehlgeschlagen'))
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
        settledReject(new Error('Import-Verbindung wurde unerwartet beendet. Bitte Import-Log prüfen.'))
      }
    }

    read().catch(settledReject)
  },

  /**
   * Laufenden JSON-Import abbrechen.
   */
  cancelImport(importId) {
    return client.post('/json-import/cancel', { import_id: importId })
  },

  /**
   * JSON-Datei validieren (ohne Import).
   */
  validateFile(file) {
    const formData = new FormData()
    formData.append('file', file)
    return client.post('/json-import/validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
