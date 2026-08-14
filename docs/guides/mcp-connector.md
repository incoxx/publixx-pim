# anyPIM als MCP-Server (Claude Connector)

anyPIM stellt den **API Designer** direkt als [MCP](https://modelcontextprotocol.io)-Server
bereit. Damit kann Claude (claude.ai, Claude Desktop, Cowork) ohne separaten Node.js-Prozess
auf die API-Templates zugreifen — alles läuft über Laravel/Apache.

## Architektur

```
Claude (claude.ai)  ──HTTPS POST /api/v1/mcp──►  Laravel McpController
                                                   │ JSON-RPC 2.0
                                                   ▼
                                       ApiDesigner-Services (bestehend)
                                       └─ list_templates / stream_products
                                          graphql_query / graphql_mutate / get_schema
```

Kein nginx, kein npm, kein Node.js nötig. Der Endpoint ist eine normale Laravel-Route.

## Verfügbare Tools

| Tool | Beschreibung | Basiert auf |
|------|--------------|-------------|
| `list_templates` | Aktive API-Templates auflisten | `ApiTemplate::active()` |
| `stream_products` | JSON-Template abrufen (Pagination, Delta-Sync, Sprache) | `ApiDataCollector` + `JsonWriter` |
| `search_products` | Volltextsuche über Produkte | `ProductSearchIndex` |
| `graphql_query` | GraphQL-Query ausführen | `GraphqlDesignerService::execute()` |
| `graphql_mutate` | GraphQL-Mutation (nur import/bidirectional) | `GraphqlDesignerService::execute()` |
| `get_schema` | GraphQL-Schema (SDL) abrufen | `GraphqlDesignerService::schemaPreview()` |

### PIM-Struktur-Tools

Lesezugriff auf das PIM-Datenmodell (liefern UUIDs, u.a. für gezielte Updates):

| Tool | Beschreibung | Parameter |
|------|--------------|-----------|
| `list_attributes` | Attribut-Definitionen inkl. `id` (UUID), data_type, is_translatable, value_list_id | `search`, `data_type`, `source_system`, `status`, `limit`, `offset` |
| `list_hierarchies` | Hierarchien inkl. `id` und `hierarchy_type` (master/output) | `type` |
| `list_hierarchy_nodes` | Knoten einer Hierarchie inkl. `id`, `path`, `depth` | `hierarchy_id` (Pflicht), `parent_node_id`, `search`, `limit`, `offset` |
| `list_node_attributes` | Zugeordnete Attribute eines Knotens inkl. Attribut-UUID, Pflicht-/Zugriffs-Flags | `node_id` (Pflicht) |
| `list_node_products` | Produkte eines Knotens (master + output) | `node_id` (Pflicht), `type` (master/output/both), `limit`, `offset` |

### Schreib-Tool

| Tool | Beschreibung | Parameter |
|------|--------------|-----------|
| `update_product_attribute` | Setzt **einen** Produktattribut-Wert (skalar/Selection; kein Composite) | `product` (UUID/SKU), `attribute` (UUID/technical_name), `value`, `language` (bei übersetzbar), `value_selection_id` (bei Selection) |

> Im **In-App-Copilot** ist `update_product_attribute` ein bestätigungspflichtiges
> Client-Tool (Human-in-the-loop); über den reinen MCP-Adapter (externe Clients)
> wird es direkt ausgeführt und im Audit-Journal protokolliert.

**Passende REST-Endpunkte** (alle unter `auth:sanctum`): `GET /attributes`,
`GET /hierarchies`, `GET /hierarchies/{id}/nodes`, `GET /hierarchy-nodes/{id}/attributes`,
`GET /hierarchy-nodes/{id}/products` (master+output, neu), `PUT /products/{id}/attribute-values`.

## Einrichtung

### 1. Token setzen (`.env`)

```env
MCP_AUTH_TOKEN=<langes-zufalls-secret>
```

Ist der Token leer, antwortet der Endpoint mit `503` (fail closed).

```bash
php artisan config:cache   # nach .env-Änderung
```

### 2. In claude.ai eintragen

Der "Custom Connector"-Dialog von claude.ai bietet **nur** Name, URL und (optional) OAuth —
**kein Feld für einen Bearer-Token-Header**. Deshalb wird das Token in die **URL** gelegt:

**Customize → Connectors → + → Benutzerdefinierten Connector hinzufügen**

| Feld | Wert |
|------|------|
| Name | `anyPIM` |
| Remote MCP Server URL | `https://ihre-domain.de/api/v1/mcp/<MCP_AUTH_TOKEN>` |
| OAuth (Erweitert) | leer lassen |

Die Verbindung synchronisiert auf Mobile und Desktop.

## Authentifizierung

Der `/mcp`-Endpoint nutzt einen **globalen** Token (`MCP_AUTH_TOKEN`), nicht die
pro-Template `auth_type`-Einstellung. Wer den Token kennt, sieht alle aktiven Templates.

Das Token wird aus drei Quellen akzeptiert (erste nicht-leere gewinnt):

1. **URL-Pfad** — `/api/v1/mcp/<token>` (claude.ai Custom Connector)
2. **Bearer-Header** — `Authorization: Bearer <token>` (Claude Desktop, curl)
3. **Query-Param** — `?token=<token>` (Fallback)

> **Sicherheitshinweis:** Sobald der Endpoint öffentlich erreichbar ist, kann jeder mit dem
> Token auf die PIM-Daten zugreifen. Den Token wie ein Passwort behandeln. Da das Token bei
> claude.ai in der URL steht, kann es in Proxy-/Server-Logs auftauchen — bei Produktdaten
> meist verschmerzbar. Mittelfristig ist OAuth 2.1 die sauberere Lösung; der Token-in-URL-
> Ansatz ist der pragmatische Einstieg, weil der claude.ai-Dialog kein Header-Feld bietet.

## Claude Desktop (stdio, optional)

Für lokale Nutzung ohne öffentlichen Endpoint existiert weiter der stdio-Server unter
`mcp-server/` (`.mcp.json`). Dieser ruft die HTTP-API von außen auf und braucht einen
Template-API-Key. Für claude.ai wird er **nicht** benötigt — dort genügt der Laravel-Endpoint.
