import { describe, it, expect } from 'vitest'
import { formatFileSize, formatCompositeSummary, resolveCompositeFormat } from '../formatting'

describe('formatFileSize', () => {
  it('returns empty string for falsy values', () => {
    expect(formatFileSize(0)).toBe('')
    expect(formatFileSize(null)).toBe('')
    expect(formatFileSize(undefined)).toBe('')
  })

  it('formats bytes', () => {
    expect(formatFileSize(500)).toBe('500 B')
    expect(formatFileSize(1)).toBe('1 B')
    expect(formatFileSize(1023)).toBe('1023 B')
  })

  it('formats kilobytes', () => {
    expect(formatFileSize(1024)).toBe('1.0 KB')
    expect(formatFileSize(1536)).toBe('1.5 KB')
    expect(formatFileSize(1024 * 100)).toBe('100.0 KB')
  })

  it('formats megabytes', () => {
    expect(formatFileSize(1024 * 1024)).toBe('1.0 MB')
    expect(formatFileSize(1024 * 1024 * 5.5)).toBe('5.5 MB')
  })
})

describe('formatCompositeSummary', () => {
  const getValue = (child) => child.value

  it('returns null for empty children', () => {
    expect(formatCompositeSummary({ children: [], getValue })).toBeNull()
    expect(formatCompositeSummary({ children: null, getValue })).toBeNull()
  })

  it('joins values with default separator', () => {
    const children = [{ value: '100' }, { value: '200' }, { value: '50' }]
    expect(formatCompositeSummary({ children, getValue })).toBe('100 × 200 × 50')
  })

  it('uses custom separator', () => {
    const children = [{ value: 'A' }, { value: 'B' }]
    expect(formatCompositeSummary({ children, getValue, separator: ' - ' })).toBe('A - B')
  })

  it('filters null and empty values', () => {
    const children = [{ value: '100' }, { value: null }, { value: '50' }]
    expect(formatCompositeSummary({ children, getValue })).toBe('100 × 50')
  })

  it('uses format template with placeholders', () => {
    const children = [{ value: '100' }, { value: '200' }, { value: '50' }]
    expect(formatCompositeSummary({
      compositeFormat: '{0} x {1} x {2} mm',
      children,
      getValue,
    })).toBe('100 x 200 x 50 mm')
  })

  it('uses placeholder for missing values in template', () => {
    const children = [{ value: '100' }, { value: null }]
    expect(formatCompositeSummary({
      compositeFormat: '{0} x {1}',
      children,
      getValue,
      placeholder: '–',
    })).toBe('100 x –')
  })

  it('returns null when all values empty without format', () => {
    const children = [{ value: null }, { value: '' }]
    expect(formatCompositeSummary({ children, getValue })).toBeNull()
  })

  it('resolves named placeholders by technical_name suffix', () => {
    const children = [
      { value: '210', technical_name: 'dim-breite' },
      { value: '75', technical_name: 'dim-hoehe' },
      { value: '230', technical_name: 'dim-tiefe' },
    ]
    expect(formatCompositeSummary({
      compositeFormat: '{breite} × {hoehe} × {tiefe} mm',
      children,
      getValue,
    })).toBe('210 × 75 × 230 mm')
  })

  it('resolves named placeholders by exact technical_name', () => {
    const children = [
      { value: 'DIN EN 60745', technical_name: 'zert-name' },
      { value: 'DE-2024-0815', technical_name: 'zert-nummer' },
    ]
    expect(formatCompositeSummary({
      compositeFormat: '{zert-name} ({zert-nummer})',
      children,
      getValue,
    })).toBe('DIN EN 60745 (DE-2024-0815)')
  })

  it('mixes named and positional placeholders', () => {
    const children = [
      { value: '100', technical_name: 'width' },
      { value: '200', technical_name: 'height' },
    ]
    expect(formatCompositeSummary({
      compositeFormat: '{width} x {1}',
      children,
      getValue,
    })).toBe('100 x 200')
  })
})

describe('resolveCompositeFormat', () => {
  it('resolves named placeholders with suffix match', () => {
    const children = [
      { technical_name: 'dim-breite' },
      { technical_name: 'dim-hoehe' },
    ]
    expect(resolveCompositeFormat('{breite} × {hoehe}', children, ['10', '20'])).toBe('10 × 20')
  })

  it('resolves positional placeholders as fallback', () => {
    const children = [
      { technical_name: 'a' },
      { technical_name: 'b' },
    ]
    expect(resolveCompositeFormat('{0} - {1}', children, ['X', 'Y'])).toBe('X - Y')
  })

  it('uses fallback for missing values', () => {
    const children = [{ technical_name: 'dim-w' }]
    expect(resolveCompositeFormat('{w} mm', children, [null], '–')).toBe('– mm')
  })

  it('returns empty string for null format', () => {
    expect(resolveCompositeFormat(null, [], [])).toBe('')
  })
})
