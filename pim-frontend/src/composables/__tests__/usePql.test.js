import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock the PQL API
vi.mock('@/api/pql', () => ({
  default: {
    query: vi.fn(),
    validate: vi.fn(),
    explain: vi.fn(),
  },
}))

import pqlApi from '@/api/pql'
import { usePql } from '../usePql'

describe('usePql', () => {
  let pql

  beforeEach(() => {
    vi.clearAllMocks()
    pql = usePql()
  })

  describe('execute', () => {
    it('stores results and total count from meta', async () => {
      pqlApi.query.mockResolvedValue({
        data: { data: [{ id: 'p1' }, { id: 'p2' }], meta: { total: 42 } },
      })

      await pql.execute('sku = "X"')

      expect(pql.results.value).toHaveLength(2)
      expect(pql.count.value).toBe(42)
      expect(pql.loading.value).toBe(false)
      expect(pql.error.value).toBeNull()
    })

    it('falls back to result length when meta is missing', async () => {
      pqlApi.query.mockResolvedValue({ data: { data: [{ id: 'p1' }] } })

      await pql.execute('sku = "X"')

      expect(pql.count.value).toBe(1)
    })

    it('uses the query ref when no PQL string is passed', async () => {
      pqlApi.query.mockResolvedValue({ data: { data: [] } })
      pql.query.value = 'name CONTAINS "Bohrer"'

      await pql.execute()

      expect(pqlApi.query).toHaveBeenCalledWith('name CONTAINS "Bohrer"', {})
    })

    it('sets error detail on failure', async () => {
      pqlApi.query.mockRejectedValue({ response: { data: { detail: 'Syntaxfehler in Zeile 1' } } })

      await pql.execute('kaputt ===')

      expect(pql.error.value).toBe('Syntaxfehler in Zeile 1')
      expect(pql.loading.value).toBe(false)
    })

    it('falls back to generic PQL error message', async () => {
      pqlApi.query.mockRejectedValue(new Error('Network'))

      await pql.execute('x')

      expect(pql.error.value).toBe('PQL-Fehler')
    })
  })

  describe('validate', () => {
    it('returns true and stores the result for valid queries', async () => {
      pqlApi.validate.mockResolvedValue({ data: { valid: true, errors: [] } })

      const valid = await pql.validate('sku = "X"')

      expect(valid).toBe(true)
      expect(pql.validationResult.value).toEqual({ valid: true, errors: [] })
    })

    it('returns false for invalid queries', async () => {
      pqlApi.validate.mockResolvedValue({ data: { valid: false, errors: ['Unbekanntes Feld'] } })

      const valid = await pql.validate('foo = 1')

      expect(valid).toBe(false)
    })

    it('returns false and builds an error result on API failure', async () => {
      pqlApi.validate.mockRejectedValue({ response: { data: { detail: 'Server down' } } })

      const valid = await pql.validate('x')

      expect(valid).toBe(false)
      expect(pql.validationResult.value).toEqual({ valid: false, errors: ['Server down'] })
    })
  })

  describe('explain', () => {
    it('stores the explain result', async () => {
      pqlApi.explain.mockResolvedValue({ data: { sql: 'SELECT ...' } })

      await pql.explain('sku = "X"')

      expect(pql.explainResult.value).toEqual({ sql: 'SELECT ...' })
    })

    it('sets error on failure', async () => {
      pqlApi.explain.mockRejectedValue({ response: { data: { detail: 'Explain fehlgeschlagen' } } })

      await pql.explain('x')

      expect(pql.error.value).toBe('Explain fehlgeschlagen')
    })
  })
})
