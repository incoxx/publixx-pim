/**
 * Thin HTTP client for the PIM API.
 * Reads PIM_BASE_URL and PIM_API_KEY from environment.
 */

const BASE_URL = (process.env.PIM_BASE_URL ?? 'http://localhost:8000').replace(/\/+$/, '')
const API_KEY  = process.env.PIM_API_KEY ?? ''

if (!API_KEY) {
  process.stderr.write('[pim-mcp] WARNING: PIM_API_KEY is not set\n')
}

async function request<T>(
  method: 'GET' | 'POST',
  path: string,
  body?: unknown,
): Promise<T> {
  const url = `${BASE_URL}${path}`
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Api-Key': API_KEY,
  }

  const response = await fetch(url, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (!response.ok) {
    const text = await response.text().catch(() => '')
    throw new Error(`PIM API ${method} ${path} → HTTP ${response.status}: ${text.slice(0, 200)}`)
  }

  return response.json() as Promise<T>
}

// ── Public helpers ─────────────────────────────────────────────────────────

export interface PimTemplate {
  id: string
  name: string
  slug: string
  output_format: 'json' | 'graphql'
  direction: 'export' | 'import' | 'bidirectional'
  language: string | null
  is_active: boolean
}

export async function listTemplates(): Promise<PimTemplate[]> {
  const data = await request<{ data: PimTemplate[] }>('GET', '/api/v1/api-templates')
  return (data.data ?? []).filter((t) => t.is_active)
}

export interface StreamParams {
  limit?: number
  offset?: number
  since?: string
  sort?: string
  order?: 'asc' | 'desc'
  fields?: string
  lang?: string
}

export async function streamProducts(slug: string, params: StreamParams = {}): Promise<unknown> {
  const qs = new URLSearchParams()
  if (params.limit)  qs.set('limit',  String(params.limit))
  if (params.offset) qs.set('offset', String(params.offset))
  if (params.since)  qs.set('since',  params.since)
  if (params.sort)   qs.set('sort',   params.sort)
  if (params.order && params.order !== 'asc') qs.set('order', params.order)
  if (params.fields) qs.set('fields', params.fields)
  if (params.lang)   qs.set('lang',   params.lang)

  const query = qs.toString()
  return request('GET', `/api/v1/api-streams/${slug}${query ? '?' + query : ''}`)
}

export async function graphqlQuery(
  slug: string,
  query: string,
  variables?: Record<string, unknown>,
): Promise<unknown> {
  return request('POST', `/api/v1/api-streams/${slug}`, { query, variables })
}

export async function graphqlMutate(
  slug: string,
  mutation: string,
  variables?: Record<string, unknown>,
): Promise<unknown> {
  return request('POST', `/api/v1/api-streams/${slug}/import`, { query: mutation, variables })
}

export async function getSchema(templateId: string): Promise<unknown> {
  return request('POST', `/api/v1/api-templates/${templateId}/schema-preview`, {})
}
