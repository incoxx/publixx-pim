---
title: Copilot (AI Assistant)
---

# Copilot — the AI Assistant in the PIM

The **anyPIM Copilot** is a conversational assistant shown directly inside the PIM. You ask questions in natural language, and the Copilot searches your product data, answers questions and — on request — proposes changes that you confirm with a click.

## What the Copilot can do

**Read (automatic):**

- Search products and display individual products
- List attributes (technical names, IDs, units, translations)
- Browse hierarchies/classifications (nodes, assigned attributes and products)
- Run GraphQL queries against API templates and inspect schemas

**Write (only after confirmation):**

- Change individual attribute values (incl. language, unit, selection value)
- Run GraphQL mutations against bidirectional API templates

::: warning Write actions are always confirmed
The Copilot **cannot** change data on its own. Every write action appears as a proposal with the exact changes (product, attribute, new value, language, unit). Only after **"Execute"** is the change carried out server-side and logged to the [Journal](/en/administration/journal).
:::

## Usage

Open the Copilot via the **"Copilot"** button (sparkles icon ✨) in the top-right header. A panel slides in on the right.

1. **Type a question** — e.g. in the *"Message to the Copilot…"* input field. `Enter` sends, `Shift+Enter` inserts a new line.
2. **Read the response** — The reply streams in live as formatted text. While the Copilot works, a status line shows what is happening (e.g. *"Searching products…"*).
3. **Open results** — If the Copilot found products, a *"Show results in PIM"* button appears, leading directly to quick search.
4. **Confirm changes** — For a change proposal, a yellow-highlighted confirmation area appears with **"Execute"** and **"Deny"** buttons.

### Example prompts

- *Which products have protection class IP55?*
- *Show me the product with SKU 10001*
- *Which classifications (hierarchies) exist in the PIM?*
- *List attributes containing "weight" with their IDs*

::: tip Context awareness
The Copilot knows the currently open page, the currently open product (SKU + name) and your UI language. This lets it correctly resolve questions like *"Set this product's weight to 1.8 kg"* against the current context. The history is kept only within the current session.
:::

## Architecture

anyPIM acts as an **MCP host**: the server calls the Anthropic Messages API and integrates its own anyPIM MCP endpoint via the MCP connector, so the model can use the read-only PIM tools autonomously.

- **Read tools** run automatically via the MCP connector (`search_products`, `stream_products`, `graphql_query`, `get_schema`, `list_attributes`, `list_hierarchies`, `list_hierarchy_nodes`, `list_node_attributes`, `list_node_products`, `list_templates`).
- **Write tools** (`graphql_mutate`, `update_product_attribute`) are deliberately **not** exposed via the connector. They are handled as client-side tools: the model proposes the action, the frontend shows the confirmation dialog, and only after approval does the server execute the mutation.
- **Streaming:** The response is passed through live to the frontend as Server-Sent Events (SSE).

```
User → Copilot panel
     → POST /api/v1/copilot/chat   (requires copilot.use)
     → CopilotService (MCP host)
     → Anthropic Messages API  ──► read-only MCP tools (automatic)
                               ──► mutation proposal (stop)
     → confirmation dialog in the frontend
     → POST /api/v1/copilot/execute-tool  (requires copilot.execute)
     → mutation + journal entry
```

## Permissions

| Permission | Meaning |
|---|---|
| `copilot.use` | Open the Copilot, ask questions, deny/confirm proposals |
| `copilot.execute` | Actually execute confirmed changes (write) |

By default Sysadmin, Admin, Data Steward and Product Manager receive both permissions; roles such as Export Manager, API Designer, Project Management and Viewer receive only `copilot.use`. The mapping is configurable under [Roles & Permissions](/en/administration/roles).

## Configuration

The Copilot requires an Anthropic API key and a reachable MCP endpoint. The following environment variables control its behaviour:

```bash
# API key — defaults to CLAUDE_AI_API_KEY
# COPILOT_API_KEY=

# Claude model for the Copilot
COPILOT_MODEL=claude-sonnet-4-6

# Maximum tokens per response
COPILOT_MAX_TOKENS=8192

# MCP endpoint (default: APP_URL/api/v1/mcp)
# COPILOT_MCP_URL=https://pim.example.com/api/v1/mcp

# Bearer token the connector uses to reach the MCP endpoint (required)
MCP_AUTH_TOKEN=
```

::: warning MCP endpoint must be reachable
For the MCP connector to work, the Anthropic servers must be able to reach the endpoint at `APP_URL/api/v1/mcp` over HTTPS. `MCP_AUTH_TOKEN` must be set — if it is missing, the Copilot reports that it cannot retrieve PIM data. If no API key is configured, the button is visible but calls return an error message.
:::

::: tip Data privacy
To answer requests, prompts and the data retrieved via MCP are transmitted to the Anthropic API. Review your internal data-protection requirements before production use.
:::
