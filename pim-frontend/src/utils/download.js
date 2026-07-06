/**
 * Trigger a browser download from a Blob.
 */
export function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 200)
}

/**
 * Extracts the backend error message from an axios error whose request used
 * `responseType: 'blob'` — in that case `error.response.data` is a Blob even for
 * JSON error bodies, so `.message` is normally lost. Falls back to `error.message`.
 */
export async function blobErrorMessage(error) {
  const data = error?.response?.data
  if (data instanceof Blob && data.type?.includes('json')) {
    try {
      const parsed = JSON.parse(await data.text())
      if (parsed?.message) return parsed.message
    } catch { /* not JSON, fall through */ }
  }
  return error?.response?.data?.message || error?.message || 'Unbekannter Fehler'
}
