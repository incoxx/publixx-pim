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
 * Format a composite attribute summary from its children values.
 *
 * @param {object} options
 * @param {string|null} options.compositeFormat - Format template, e.g. "{0} x {1} mm"
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
    let result = compositeFormat
    children.forEach((_, i) => {
      const val = values[i]
      result = result.replace(`{${i}}`, val != null && val !== '' ? String(val) : placeholder)
    })
    return result.trim() || null
  }

  const filled = values.filter(v => v != null && v !== '')
  return filled.length > 0 ? filled.join(separator) : null
}
