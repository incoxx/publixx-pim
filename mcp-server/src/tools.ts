/**
 * MCP Tool definitions and handlers for the PIM API.
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'
import {
  listTemplates,
  streamProducts,
  graphqlQuery,
  graphqlMutate,
  getSchema,
} from './pim-client.js'

export function registerTools(server: McpServer): void {

  // ── list_templates ────────────────────────────────────────────────────────
  server.tool(
    'list_templates',
    'Listet alle aktiven API-Templates des PIM. Gibt Slug, Name, Format (json/graphql) und Richtung zurück.',
    {},
    async () => {
      const templates = await listTemplates()
      const text = templates.length === 0
        ? 'Keine aktiven Templates gefunden.'
        : templates.map(t =>
            `• ${t.slug} — ${t.name} [${t.output_format}, ${t.direction}${t.language ? ', ' + t.language : ''}]`
          ).join('\n')
      return { content: [{ type: 'text', text }] }
    },
  )

  // ── stream_products ───────────────────────────────────────────────────────
  server.tool(
    'stream_products',
    'Ruft Produkte aus einem JSON-API-Template ab. Unterstützt Pagination, Delta-Sync und Sprachauswahl.',
    {
      slug:   z.string().describe('URL-Slug des API-Templates'),
      limit:  z.number().int().positive().optional().describe('Max. Anzahl Produkte (Standard: alle)'),
      offset: z.number().int().min(0).optional().describe('Start-Record für Pagination'),
      since:  z.string().optional().describe('ISO-8601 Timestamp — nur Produkte die seit diesem Datum geändert wurden'),
      sort:   z.enum(['sku', 'name', 'status', 'created_at', 'updated_at']).optional().describe('Sortierfeld'),
      order:  z.enum(['asc', 'desc']).optional().describe('Sortierrichtung (Standard: asc)'),
      fields: z.string().optional().describe('Kommaliste von JSON-Keys — nur diese Felder zurückgeben (z.B. "sku,name,price")'),
      lang:   z.string().optional().describe('Sprachcode (z.B. "de", "en") — überschreibt Template-Sprache'),
    },
    async ({ slug, ...params }) => {
      const data = await streamProducts(slug, params)
      return {
        content: [{
          type: 'text',
          text: JSON.stringify(data, null, 2),
        }],
      }
    },
  )

  // ── graphql_query ─────────────────────────────────────────────────────────
  server.tool(
    'graphql_query',
    'Führt eine GraphQL-Query gegen ein GraphQL-API-Template aus (Daten lesen).',
    {
      slug:      z.string().describe('URL-Slug des GraphQL-API-Templates'),
      query:     z.string().describe('GraphQL Query-String, z.B. "{ total groups { products { sku name } } }"'),
      variables: z.record(z.string(), z.unknown()).optional().describe('GraphQL-Variablen als Objekt'),
    },
    async ({ slug, query, variables }) => {
      const data = await graphqlQuery(slug, query, variables)
      return {
        content: [{
          type: 'text',
          text: JSON.stringify(data, null, 2),
        }],
      }
    },
  )

  // ── graphql_mutate ────────────────────────────────────────────────────────
  server.tool(
    'graphql_mutate',
    'Führt eine GraphQL-Mutation gegen ein bidirektionales API-Template aus (Daten schreiben/importieren). Nur für Templates mit direction "import" oder "bidirectional".',
    {
      slug:      z.string().describe('URL-Slug des GraphQL-API-Templates'),
      mutation:  z.string().describe('GraphQL Mutation-String'),
      variables: z.record(z.string(), z.unknown()).optional().describe('GraphQL-Variablen als Objekt'),
    },
    async ({ slug, mutation, variables }) => {
      const data = await graphqlMutate(slug, mutation, variables)
      return {
        content: [{
          type: 'text',
          text: JSON.stringify(data, null, 2),
        }],
      }
    },
  )

  // ── get_schema ────────────────────────────────────────────────────────────
  server.tool(
    'get_schema',
    'Gibt das GraphQL-Schema (SDL) eines API-Templates zurück. Nützlich um zu verstehen welche Felder und Typen verfügbar sind.',
    {
      slug: z.string().describe('URL-Slug des API-Templates'),
    },
    async ({ slug }) => {
      // Schema-Preview braucht die Template-ID, nicht den Slug
      // → erst Templates laden um ID zu finden
      const templates = await listTemplates()
      const template = templates.find(t => t.slug === slug)
      if (!template) {
        return {
          content: [{ type: 'text', text: `Template mit Slug "${slug}" nicht gefunden oder inaktiv.` }],
          isError: true,
        }
      }
      const data = await getSchema(template.id)
      return {
        content: [{
          type: 'text',
          text: typeof data === 'string' ? data : JSON.stringify(data, null, 2),
        }],
      }
    },
  )
}
