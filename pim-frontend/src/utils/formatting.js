/**
 * Format bytes into a human-readable file size string.
 */
export function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}

/**
 * Löst benannte und positionelle Platzhalter in einem composite_format-String auf.
 *
 * Unterstützt:
 * - Benannte Platzhalter: {breite}, {hoehe} → Suffix-Match auf child.technical_name
 * - Positionelle Platzhalter: {0}, {1}, {2} → Index-basiert (Rückwärtskompatibilität)
 *
 * @param {string} format - z.B. "{breite} × {hoehe} × {tiefe} mm"
 * @param {Array} children - Geordnete Kind-Attribute (mit technical_name Property)
 * @param {Array} values - Geordnete Werte (gleiche Reihenfolge wie children)
 * @param {string} [fallback=''] - Fallback für fehlende Werte
 * @returns {string} Aufgelöster String, z.B. "210 × 75 × 230 mm"
 */
export function resolveCompositeFormat(format, children, values, fallback = '') {
  if (!format || !children) return ''
  let result = format

  // 1. Benannte Platzhalter auflösen
  const placeholders = [...format.matchAll(/\{([^}]+)\}/g)]
  for (const [full, name] of placeholders) {
    if (/^\d+$/.test(name)) continue // positionell → später
    const idx = children.findIndex(c => {
      const tn = c.technical_name || ''
      return tn === name || tn.endsWith('-' + name)
    })
    if (idx !== -1) {
      const val = values[idx]
      result = result.replace(full, val != null && val !== '' ? String(val) : fallback)
    }
  }

  // 2. Positionelle Platzhalter auflösen (Fallback)
  children.forEach((_, i) => {
    const val = values[i]
    result = result.replace(`{${i}}`, val != null && val !== '' ? String(val) : fallback)
  })

  return result.trim()
}

/**
 * Format a composite attribute summary from its children values.
 *
 * @param {object} options
 * @param {string|null} options.compositeFormat - Format template, e.g. "{breite} x {hoehe} mm" or "{0} x {1} mm"
 * @param {Array} options.children - Array of child items (order matters for format placeholders)
 * @param {function} options.getValue - Function to extract value from a child item
 * @param {string} [options.placeholder=''] - Placeholder for missing values
 * @param {string} [options.separator=' × '] - Separator when no format is defined
 * @returns {string|null} Formatted summary or null if no values
 */
export function formatCompositeSummary({ compositeFormat, children, getValue, placeholder = '', separator = ' × ' }) {
  if (!children || children.length === 0) return null
  const values = children.map(getValue)

  if (compositeFormat) {
    const result = resolveCompositeFormat(compositeFormat, children, values, placeholder)
    return result || null
  }

  const filled = values.filter(v => v != null && v !== '')
  return filled.length > 0 ? filled.join(separator) : null
}
