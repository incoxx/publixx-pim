#!/usr/bin/env node
/**
 * anyPIM MCP Server
 *
 * Exposes PIM API templates as MCP tools so Claude and other AI agents
 * can read and write product data directly.
 *
 * Environment variables:
 *   PIM_BASE_URL    — PIM API base URL (default: http://localhost:8000)
 *   PIM_API_KEY     — API key for PIM authentication (required)
 *
 * --- stdio mode (Claude Desktop / .mcp.json) ---
 *   node dist/index.js
 *
 * --- HTTP mode (claude.ai Custom Connector / remote agents) ---
 *   MCP_HTTP_PORT=3100 MCP_AUTH_TOKEN=<secret> node dist/index.js
 *
 *   Endpoint:  POST https://your-domain.example/mcp  (or wherever nginx proxies it)
 *   Auth:      Authorization: Bearer <MCP_AUTH_TOKEN>
 *
 * Usage in .mcp.json (stdio):
 *   {
 *     "mcpServers": {
 *       "pim": {
 *         "command": "node",
 *         "args": ["./mcp-server/dist/index.js"],
 *         "env": { "PIM_BASE_URL": "...", "PIM_API_KEY": "$PIM_API_KEY" }
 *       }
 *     }
 *   }
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js'
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js'
import { registerTools } from './tools.js'
import http from 'node:http'

const HTTP_PORT  = process.env.MCP_HTTP_PORT  ? parseInt(process.env.MCP_HTTP_PORT, 10) : null
const AUTH_TOKEN = process.env.MCP_AUTH_TOKEN ?? ''

// ── HTTP-Modus ────────────────────────────────────────────────────────────────

if (HTTP_PORT) {
  if (!AUTH_TOKEN) {
    process.stderr.write('[pim-mcp] FEHLER: MCP_AUTH_TOKEN muss gesetzt sein im HTTP-Modus\n')
    process.exit(1)
  }

  const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined })

  const server = new McpServer({ name: 'anyPIM', version: '1.0.0' })
  registerTools(server)
  await server.connect(transport)

  const httpServer = http.createServer(async (req, res) => {
    // CORS — erlaubt claude.ai und lokale Entwicklung
    res.setHeader('Access-Control-Allow-Origin', '*')
    res.setHeader('Access-Control-Allow-Methods', 'POST, GET, DELETE, OPTIONS')
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id')

    if (req.method === 'OPTIONS') {
      res.writeHead(204)
      res.end()
      return
    }

    // Bearer-Token prüfen
    const authHeader = req.headers['authorization'] ?? ''
    const token = authHeader.startsWith('Bearer ') ? authHeader.slice(7) : ''
    if (token !== AUTH_TOKEN) {
      res.writeHead(401, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ error: 'Unauthorized' }))
      return
    }

    // Nur /mcp bedienen
    const url = new URL(req.url ?? '/', `http://localhost:${HTTP_PORT}`)
    if (url.pathname !== '/mcp') {
      res.writeHead(404, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ error: 'Not found — use POST /mcp' }))
      return
    }

    await transport.handleRequest(req, res)
  })

  httpServer.listen(HTTP_PORT, () => {
    process.stderr.write(`[pim-mcp] HTTP-Server läuft auf Port ${HTTP_PORT} → POST /mcp\n`)
  })

} else {

  // ── stdio-Modus (Claude Desktop / .mcp.json) ────────────────────────────────

  const server = new McpServer({ name: 'anyPIM', version: '1.0.0' })
  registerTools(server)

  const transport = new StdioServerTransport()
  await server.connect(transport)

  process.stderr.write('[pim-mcp] Server gestartet (stdio)\n')
}
