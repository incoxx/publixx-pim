/**
 * PXF Data Resolver
 * Resolves dot-notation bindings and applies text transforms,
 * formatting rules, and visibility conditions.
 */

export function resolveBinding(data, bindPath) {
  if (!data || !bindPath) return undefined
  return bindPath.split('.').reduce((obj, key) => obj?.[key], data)
}

export function applyNumberFormat(value, config) {
  if (!config?.enabled || value == null) return value
  const num = parseFloat(value)
  if (isNaN(num)) return value
  const { decimals = 2, thousandsSeparator = '.', decimalSeparator = ',' } = config
  const fixed = num.toFixed(decimals)
  const [intPart, decPart] = fixed.split('.')
  const formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator)
  return decPart ? `${formatted}${decimalSeparator}${decPart}` : formatted
}

export function applyDateFormat(value, config) {
  if (!config?.enabled || !value) return value
  try {
    const date = new Date(value)
    const { locale = 'de-DE' } = config
    return date.toLocaleDateString(locale, { year: 'numeric', month: '2-digit', day: '2-digit' })
  } catch {
    return value
  }
}

export function applyTextTransform(value, rules) {
  if (!rules?.enabled || !rules.rules) return value
  let result = String(value ?? '')
  for (const rule of rules.rules) {
    switch (rule.type) {
      case 'uppercase': result = result.toUpperCase(); break
      case 'lowercase': result = result.toLowerCase(); break
      case 'capitalize': result = result.replace(/\b\w/g, c => c.toUpperCase()); break
      case 'capitalizeFirst': result = result.charAt(0).toUpperCase() + result.slice(1); break
      case 'trim': result = result.trim(); break
      case 'normalizeSpace': result = result.replace(/\s+/g, ' ').trim(); break
      case 'replace': result = result.split(rule.search || '').join(rule.replace || ''); break
      case 'remove': result = result.split(rule.search || '').join(''); break
      case 'truncate': {
        const len = rule.length || 50
        if (result.length > len) result = result.substring(0, len) + (rule.ellipsis || '...')
        break
      }
      case 'prefix': result = (rule.value || '') + result; break
      case 'suffix': result = result + (rule.value || ''); break
      case 'slugify': result = result.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); break
      case 'stripHtml': result = result.replace(/<[^>]*>/g, ''); break
    }
  }
  return result
}

export function checkVisibility(data, visibility) {
  if (!visibility?.enabled || !visibility.rules?.length) return true
  const mode = visibility.mode || 'all'
  const results = visibility.rules.map(rule => evaluateRule(data, rule))
  return mode === 'all' ? results.every(Boolean) : results.some(Boolean)
}

function evaluateRule(data, rule) {
  const value = resolveBinding(data, rule.field)
  const target = rule.value
  const cs = rule.caseSensitive !== false

  switch (rule.operator) {
    case 'exists': return value != null && value !== ''
    case 'notExists': return value == null || value === ''
    case 'equals': return cs ? String(value) === String(target) : String(value).toLowerCase() === String(target).toLowerCase()
    case 'notEquals': return cs ? String(value) !== String(target) : String(value).toLowerCase() !== String(target).toLowerCase()
    case 'contains': return cs ? String(value).includes(target) : String(value).toLowerCase().includes(String(target).toLowerCase())
    case 'notContains': return !(cs ? String(value).includes(target) : String(value).toLowerCase().includes(String(target).toLowerCase()))
    case 'greaterThan': return parseFloat(value) > parseFloat(target)
    case 'lessThan': return parseFloat(value) < parseFloat(target)
    default: return true
  }
}

export function applyFormattingRules(data, formattingRules) {
  if (!formattingRules?.enabled || !formattingRules.rules?.length) return {}
  const mode = formattingRules.mode || 'first'
  let merged = {}
  for (const rule of formattingRules.rules) {
    if (evaluateRule(data, rule.condition)) {
      if (mode === 'first') return rule.styles || {}
      merged = { ...merged, ...(rule.styles || {}) }
    }
  }
  return merged
}

export function resolveAssetUrl(path, assetBase) {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path
  return (assetBase || '') + path
}
