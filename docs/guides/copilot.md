# In-App Copilot (Chat-Fenster im PIM)

Der **Copilot** ist ein Chat-Assistent direkt in der anyPIM-Oberfläche. Er hilft
Anwendern, Produktdaten zu finden, zu verstehen und zu pflegen — und kann über die
bestehenden MCP-Tools echte PIM-Daten abrufen statt zu raten.

Geöffnet wird er über den **Copilot**-Button (Sparkles-Icon) oben rechts im Header.

## Demo-Prompts

Beispiele, die die Werkzeuge sinnvoll kombinieren (im Leerzustand des Panels sind die
ersten als klickbare Chips hinterlegt):

| Prompt | Aufgerufene Tools (Erwartung) |
|--------|-------------------------------|
| „Welche Produkte haben Schutzart IP55?" | `search_products` |
| „Zeige mir das Produkt mit der SKU 10001" | `search_products` |
| „Welche Klassifikationen (Hierarchien) gibt es im PIM?" | `list_hierarchies` |
| „Liste Attribute, die ‚gewicht' enthalten, mit ihren IDs" | `list_attributes` |
| „Welche Kategorien hat die Hierarchie X?" | `list_hierarchies` → `list_hierarchy_nodes` |
| „Welche Attribute sind der Kategorie X zugeordnet?" | `list_hierarchies` → `list_hierarchy_nodes` → `list_node_attributes` |
| „Welche Produkte hängen an Knoten X?" | `list_hierarchy_nodes` → `list_node_products` |
| „Setze beim Produkt SKU 10001 das Gewicht auf 500 g" | `list_attributes` → **(Bestätigung)** → `update_product_attribute` |

**Schreibendes Beispiel im Detail** — „beim Produkt SKU 10001 das Gewicht auf 500 setzen":

1. `list_attributes` mit `search="gewicht"` → liefert `technical_name`, `data_type`,
   `has_unit`, `default_unit`. Daran erkennt die KI, dass eine Einheit nötig ist.
2. `update_product_attribute` `{ product: "10001", attribute: "gewicht-netto", value: 500, unit: "g" }`
   — erst **nach Bestätigung** im Dialog.

Verzweigungen, die der Copilot beachtet: mehrdeutige Produkte (mehrere/„alle …")
→ erst `search_products` + Rückfrage; mehrere „Gewicht"-Attribute → Rückfrage;
fehlende Einheit ohne Standardeinheit → Rückfrage statt Raten.

## Architektur

Beim [MCP-Connector](mcp-connector.md) ruft eine *externe* Claude-Instanz anyPIM auf.
Der Copilot dreht die Richtung um: **anyPIM ist der MCP-Host** und ruft die Anthropic
Messages API auf — diese bindet wiederum den eigenen `/api/v1/mcp`-Endpoint via
MCP-Connector ein. So werden die vorhandenen Tools wiederverwendet, ohne sie ein
zweites Mal zu implementieren.

```
Vue: CopilotPanel ──POST /api/v1/copilot/chat (Sanctum)──► CopilotController
                                                             │ SSE-Proxy
                                                             ▼
                                                   Anthropic Messages API
                                                   (stream + mcp_servers)
                                                             │ MCP-Connector ruft
                                                             ▼ den eigenen Endpoint
                                              Laravel McpController (/api/v1/mcp)
                                              └─ search_products / graphql_query / …
```

- **Lesende Tools** (`list_templates`, `stream_products`, `search_products`,
  `graphql_query`, `get_schema`) führt Claude über den Connector **automatisch** aus.
- **Schreibende Aktionen** (`graphql_mutate`) laufen als **client-seitiges Tool** und
  werden dem Nutzer vor der Ausführung **immer zur Bestätigung** vorgelegt
  (Human-in-the-loop). Bestätigte Mutationen landen im Audit-Journal (`audit_logs`,
  `auditableType = "Copilot"`).
- Der **Chat-Verlauf** lebt clientseitig pro Browser-Sitzung (keine DB-Persistenz).

## Voraussetzungen

| Voraussetzung | Warum |
|---------------|-------|
| `CLAUDE_AI_API_KEY` (oder `COPILOT_API_KEY`) | Aufruf der Anthropic Messages API |
| `MCP_AUTH_TOKEN` | Der Copilot reicht diesen als Bearer an den eigenen MCP-Endpoint weiter |
| **Öffentlich erreichbarer MCP-Endpoint** | Der MCP-Connector wird **von Anthropic-Servern** aufgerufen — `COPILOT_MCP_URL` muss von außen erreichbar sein |
| Mind. ein API-Template mit **„Für Claude freigeben"** (`is_mcp_enabled`) | Sonst liefern die Tools keine Daten |

> ⚠️ **Lokale Entwicklung:** Mit `APP_URL=http://localhost:8000` kann Anthropic den
> MCP-Endpoint **nicht** erreichen. Der Chat selbst funktioniert, aber die PIM-Tools
> liefern nichts. Für einen echten Durchstich braucht es eine öffentliche URL (Staging/
> Prod) oder einen Tunnel (z. B. ngrok) und ein entsprechend gesetztes `COPILOT_MCP_URL`.

## Einrichtung

### 1. `.env` setzen

```env
# Anthropic-Key (wird mit der Fehlerklassifikation geteilt)
CLAUDE_AI_API_KEY=sk-ant-...

# MCP-Endpoint-Token (siehe docs/guides/mcp-connector.md)
MCP_AUTH_TOKEN=<langes-zufalls-secret>

# Copilot (optional — sinnvolle Defaults sind gesetzt)
# COPILOT_API_KEY=               # eigener Key, sonst CLAUDE_AI_API_KEY
COPILOT_MODEL=claude-sonnet-4-6
COPILOT_MAX_TOKENS=4096
# COPILOT_MCP_URL=https://pim.example.com/api/v1/mcp   # Default: APP_URL/api/v1/mcp
```

```bash
php artisan config:cache   # nach .env-Änderung im Produktionsbetrieb
```

### 2. Templates freigeben

Im **API Designer** je Template **„Für Claude freigeben"** (`is_mcp_enabled`) aktivieren.
Nur freigegebene, aktive Templates sind für den Copilot sichtbar.

### 3. Schreibrechte (optional)

`graphql_mutate` wirkt nur auf Templates mit `direction = import` oder `bidirectional`.
Ohne solche Templates kann der Copilot nur lesen — der Bestätigungs-Dialog erscheint dann
gar nicht.

## Konfigurationsreferenz

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `COPILOT_API_KEY` | = `CLAUDE_AI_API_KEY` | Eigener Anthropic-Key für den Copilot |
| `COPILOT_MODEL` | `claude-sonnet-4-6` | Modell-ID (Tool-Use-fähig, niedrige Latenz) |
| `COPILOT_MAX_TOKENS` | `4096` | Maximale Tokens pro Antwort |
| `COPILOT_MCP_URL` | `APP_URL/api/v1/mcp` | MCP-Endpoint, den Anthropic aufruft (öffentlich!) |

Zentral gebündelt in `config/connectors.php` unter `copilot`.

## API-Endpunkte

Beide Routen liegen hinter `auth:sanctum` + `throttle.pim` (nur eingeloggte Nutzer).

| Methode | Pfad | Zweck |
|---------|------|-------|
| `POST` | `/api/v1/copilot/chat` | Streamt die Claude-Antwort als Server-Sent Events |
| `POST` | `/api/v1/copilot/execute-tool` | Führt eine bestätigte `graphql_mutate`-Aktion aus |

**`/copilot/chat` Request:**

```json
{
  "messages": [
    { "role": "user", "content": "Welche Produkte haben Schutzart IP55?" }
  ],
  "context": {
    "route": "Produkte",
    "locale": "de",
    "productSku": "AKKU-1800",
    "productName": "Akkubohrer Professional"
  }
}
```

- `messages` — vollständiger Verlauf im Anthropic-Message-Format (das Frontend hält ihn).
- `context` — optionaler UI-Kontext, fließt in den System-Prompt (macht den Chat
  kontextbewusst: aktuelle Ansicht, geöffnetes Produkt, Sprache).

Die Antwort ist ein SSE-Stream (`text/event-stream`) und wird 1:1 von der Messages API
durchgereicht. Ruft Claude `graphql_mutate` auf, endet der Turn mit
`stop_reason: tool_use`; das Frontend zeigt den Bestätigungs-Dialog und ruft nach Freigabe
`/copilot/execute-tool` auf, bevor das Gespräch fortgesetzt wird.

## Sicherheit

- Beide Endpunkte erfordern eine gültige Sanctum-Sitzung — nur authentifizierte Nutzer.
- `MCP_AUTH_TOKEN` und der Anthropic-Key bleiben **serverseitig**; das Frontend sieht sie nie.
- Schreibende Aktionen erfordern eine explizite Nutzer-Bestätigung und werden auditiert.
- Der MCP-Connector beschränkt sich serverseitig per `allowed_tools` auf die Lese-Tools;
  `graphql_mutate` ist dort bewusst nicht enthalten.

## Fehlerbilder

| Symptom | Ursache / Lösung |
|---------|------------------|
| „Copilot ist nicht konfiguriert" | `CLAUDE_AI_API_KEY`/`COPILOT_API_KEY` fehlt |
| „MCP-Endpoint ist nicht konfiguriert" | `MCP_AUTH_TOKEN` fehlt |
| Chat antwortet, findet aber nie Produkte | `COPILOT_MCP_URL` nicht öffentlich erreichbar, oder kein Template freigegeben |
| `HTTP 401` im Chat | Sanctum-Sitzung abgelaufen — neu anmelden |
