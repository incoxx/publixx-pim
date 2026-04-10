#!/usr/bin/env node
/**
 * anyPIM MCP Server
 *
 * Exposes PIM API templates as MCP tools so Claude and other AI agents
 * can read and write product data directly.
 *
 * Environment variables:
 *   PIM_BASE_URL  — PIM API base URL (default: http://localhost:8000)
 *   PIM_API_KEY   — API key for authentication (required)
 *
 * Usage in .mcp.json:
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
import { registerTools } from './tools.js'

const server = new McpServer({
  name: 'anyPIM',
  version: '1.0.0',
})

registerTools(server)

const transport = new StdioServerTransport()
await server.connect(transport)

process.stderr.write('[pim-mcp] Server started\n')
