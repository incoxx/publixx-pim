import client from './client'
import { useAuthStore } from '@/stores/auth'

const apiBaseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

// Dateien > 50MB werden per Chunked Upload hochgeladen
const CHUNK_THRESHOLD = 50 * 1024 * 1024 // 50 MB
const CHUNK_SIZE = 5 * 1024 * 1024 // 5 MB

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
   * Große Dateien (>50MB) werden automatisch per Chunked Upload hochgeladen.
   *
   * @param {File} file
   * @param {string} mode
   * @param {string|null} productType
   * @param {(event: {phase: string, message: string, current: number, total: number, stats?: object}) => void} onProgress
   * @returns {Promise<object>} Final import result
   */
  importFileWithProgress(file, mode = 'update', productType = null, onProgress = null) {
    if (file.size > CHUNK_THRESHOLD) {
      return this._importChunked(file, mode, productType, onProgress)
    }
    return this._importDirect(file, mode, productType, onProgress)
  },

  /**
   * Direkter Upload (< 50MB) — bestehende Logik.
   */
  _importDirect(file, mode = 'update', productType = null, onProgress = null) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)
    if (productType) {
      formData.append('product_type', productType)
    }

    const auth = useAuthStore()
    const token = auth.token

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
   * Chunked Upload (>= 50MB) — Datei in 5MB-Chunks hochladen, dann Import starten.
   */
  async _importChunked(file, mode = 'update', productType = null, onProgress = null) {
    const auth = useAuthStore()
    const token = auth.token

    const xsrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    const authHeaders = {
      Authorization: token ? `Bearer ${token}` : '',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
    }

    // 1. Upload initialisieren
    if (onProgress) {
      onProgress({
        phase: 'upload',
        message: 'Upload wird vorbereitet...',
        current: 0,
        total: file.size,
      })
    }

    const initResponse = await fetch(`${apiBaseURL}/bmecat-import/upload-init`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...authHeaders,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        filename: file.name,
        filesize: file.size,
        chunk_size: CHUNK_SIZE,
      }),
    })

    if (!initResponse.ok) {
      const text = await initResponse.text()
      throw new Error(`Upload-Init fehlgeschlagen (HTTP ${initResponse.status}): ${text.slice(0, 200)}`)
    }

    const { upload_id } = await initResponse.json()

    // 2. Chunks hochladen
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE)
    let uploadedBytes = 0

    for (let i = 0; i < totalChunks; i++) {
      const start = i * CHUNK_SIZE
      const end = Math.min(start + CHUNK_SIZE, file.size)
      const chunk = file.slice(start, end)

      const chunkForm = new FormData()
      chunkForm.append('upload_id', upload_id)
      chunkForm.append('chunk_index', i.toString())
      chunkForm.append('chunk', chunk, `chunk_${i}`)

      const chunkResponse = await fetch(`${apiBaseURL}/bmecat-import/upload-chunk`, {
        method: 'POST',
        headers: authHeaders,
        credentials: 'same-origin',
        body: chunkForm,
      })

      if (!chunkResponse.ok) {
        const text = await chunkResponse.text()
        throw new Error(`Chunk ${i + 1}/${totalChunks} fehlgeschlagen (HTTP ${chunkResponse.status}): ${text.slice(0, 200)}`)
      }

      uploadedBytes += (end - start)

      if (onProgress) {
        const percent = Math.round((uploadedBytes / file.size) * 100)
        const uploadedMb = (uploadedBytes / 1024 / 1024).toFixed(1)
        const totalMb = (file.size / 1024 / 1024).toFixed(1)
        onProgress({
          phase: 'upload',
          message: `Hochladen: ${uploadedMb} / ${totalMb} MB (${percent}%)`,
          current: uploadedBytes,
          total: file.size,
        })
      }
    }

    // 3. Upload abschließen und Import starten (SSE)
    if (onProgress) {
      onProgress({
        phase: 'assembling',
        message: 'Datei wird zusammengeführt und Import gestartet...',
        current: 0,
        total: 0,
      })
    }

    return new Promise((resolve, reject) => {
      const body = new URLSearchParams()
      body.append('upload_id', upload_id)
      body.append('mode', mode)
      if (productType) {
        body.append('product_type', productType)
      }

      fetch(`${apiBaseURL}/bmecat-import/upload-complete`, {
        method: 'POST',
        headers: {
          Accept: 'text/event-stream',
          'Content-Type': 'application/x-www-form-urlencoded',
          ...authHeaders,
        },
        credentials: 'same-origin',
        body: body.toString(),
      })
        .then(async (response) => {
          if (!response.ok) {
            const text = await response.text()
            try {
              const json = JSON.parse(text)
              reject(new Error(json.error || json.message || `Import fehlgeschlagen (HTTP ${response.status})`))
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
   * Gemeinsame SSE-Response-Verarbeitung.
   */
  _handleSseResponse(response, onProgress, resolve, reject) {
    const contentType = response.headers.get('content-type') || ''

    // Non-SSE response (validation error returned as JSON)
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

    // Parse SSE stream
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
