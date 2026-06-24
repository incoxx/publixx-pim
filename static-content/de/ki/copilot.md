---
title: Copilot (KI-Assistent)
---

# Copilot — der KI-Assistent im PIM

Der **anyPIM Copilot** ist ein dialogbasierter Assistent, der direkt im PIM eingeblendet wird. Sie stellen Fragen in natürlicher Sprache und der Copilot durchsucht Ihre Produktdaten, beantwortet Fragen und schlägt — auf Wunsch — Änderungen vor, die Sie per Klick bestätigen.

## Was der Copilot kann

**Lesend (automatisch):**

- Produkte suchen und einzelne Produkte anzeigen
- Attribute auflisten (technische Namen, IDs, Einheiten, Übersetzungen)
- Hierarchien/Klassifikationen durchsuchen (Knoten, zugeordnete Attribute und Produkte)
- GraphQL-Abfragen gegen API-Templates ausführen und Schemata einsehen

**Schreibend (nur nach Bestätigung):**

- Einzelne Attributwerte ändern (inkl. Sprache, Einheit, Auswahlwert)
- GraphQL-Mutationen gegen bidirektionale API-Templates ausführen

::: warning Schreibende Aktionen werden immer bestätigt
Der Copilot kann Daten **nicht eigenmächtig** verändern. Jede schreibende Aktion erscheint als Vorschlag mit den exakten Änderungen (Produkt, Attribut, neuer Wert, Sprache, Einheit). Erst nach **„Ausführen"** wird die Änderung serverseitig durchgeführt und im [Journal](/de/administration/journal) protokolliert.
:::

## Bedienung

Der Copilot wird über die Schaltfläche **„Copilot"** (Funken-Symbol ✨) oben rechts in der Kopfzeile geöffnet. Es erscheint ein Panel am rechten Bildschirmrand.

1. **Frage eingeben** — z. B. im Eingabefeld *„Nachricht an den Copilot…"*. `Enter` sendet, `Shift+Enter` fügt eine neue Zeile ein.
2. **Antwort lesen** — Die Antwort wird live (gestreamt) als formatierter Text dargestellt. Während der Copilot arbeitet, zeigt eine Statuszeile, was gerade passiert (z. B. *„Durchsucht Produkte…"*).
3. **Treffer öffnen** — Hat der Copilot Produkte gefunden, erscheint die Schaltfläche *„Treffer im PIM anzeigen"*, die direkt zur Schnellsuche führt.
4. **Änderungen bestätigen** — Bei einem Änderungsvorschlag erscheint ein gelb markierter Bestätigungsbereich mit den Buttons **„Ausführen"** und **„Ablehnen"**.

### Beispiel-Eingaben

- *Welche Produkte haben Schutzart IP55?*
- *Zeige mir das Produkt mit der SKU 10001*
- *Welche Klassifikationen (Hierarchien) gibt es im PIM?*
- *Liste Attribute, die „gewicht" enthalten, mit ihren IDs*

::: tip Kontextbewusstsein
Der Copilot kennt die aktuell geöffnete Seite, das aktuell geöffnete Produkt (SKU + Name) und Ihre Oberflächensprache. Dadurch kann er Fragen wie *„Setze das Gewicht dieses Produkts auf 1,8 kg"* korrekt auf den aktuellen Kontext beziehen. Der Verlauf bleibt nur in der aktuellen Sitzung erhalten.
:::

## Architektur

anyPIM tritt als **MCP-Host** auf: Der Server ruft die Anthropic Messages API auf und bindet den eigenen anyPIM-MCP-Endpoint über den MCP-Connector ein. So kann das Modell die lesenden PIM-Werkzeuge eigenständig nutzen.

- **Lesende Werkzeuge** laufen automatisch über den MCP-Connector (`search_products`, `stream_products`, `graphql_query`, `get_schema`, `list_attributes`, `list_hierarchies`, `list_hierarchy_nodes`, `list_node_attributes`, `list_node_products`, `list_templates`).
- **Schreibende Werkzeuge** (`graphql_mutate`, `update_product_attribute`) sind bewusst **nicht** im Connector freigegeben. Sie werden als clientseitige Werkzeuge behandelt: Das Modell schlägt die Aktion vor, das Frontend zeigt den Bestätigungsdialog, und erst nach Freigabe führt der Server die Mutation aus.
- **Streaming:** Die Antwort wird als Server-Sent-Events (SSE) live an das Frontend durchgereicht.

```
Nutzer → Copilot-Panel
       → POST /api/v1/copilot/chat   (benötigt copilot.use)
       → CopilotService (MCP-Host)
       → Anthropic Messages API  ──► lesende MCP-Tools (automatisch)
                                 ──► Mutations-Vorschlag (stop)
       → Bestätigungsdialog im Frontend
       → POST /api/v1/copilot/execute-tool  (benötigt copilot.execute)
       → Mutation + Journal-Eintrag
```

## Berechtigungen

| Berechtigung | Bedeutung |
|---|---|
| `copilot.use` | Copilot öffnen, Fragen stellen, Vorschläge ablehnen/bestätigen |
| `copilot.execute` | Bestätigte Änderungen tatsächlich ausführen (schreibend) |

Standardmäßig erhalten Sysadmin, Admin, Data Steward und Product Manager beide Berechtigungen; Rollen wie Export Manager, API Designer, Projektmanagement und Viewer erhalten nur `copilot.use`. Die Zuordnung ist unter [Rollen & Berechtigungen](/de/administration/rollen) anpassbar.

## Konfiguration

Der Copilot benötigt einen Anthropic API-Key und einen erreichbaren MCP-Endpoint. Die folgenden Umgebungsvariablen steuern das Verhalten:

```bash
# API-Key — nutzt standardmäßig CLAUDE_AI_API_KEY
# COPILOT_API_KEY=

# Claude-Modell für den Copilot
COPILOT_MODEL=claude-sonnet-4-6

# Maximale Tokens pro Antwort
COPILOT_MAX_TOKENS=8192

# MCP-Endpoint (Default: APP_URL/api/v1/mcp)
# COPILOT_MCP_URL=https://pim.example.com/api/v1/mcp

# Bearer-Token, mit dem der Connector den MCP-Endpoint erreicht (erforderlich)
MCP_AUTH_TOKEN=
```

::: warning MCP-Endpoint muss erreichbar sein
Damit der MCP-Connector funktioniert, müssen die Anthropic-Server den Endpoint unter `APP_URL/api/v1/mcp` per HTTPS erreichen können. `MCP_AUTH_TOKEN` muss gesetzt sein — fehlt es, meldet der Copilot, dass keine PIM-Daten abgerufen werden können. Ist kein API-Key konfiguriert, ist die Schaltfläche zwar sichtbar, der Aufruf liefert aber eine Fehlermeldung.
:::

::: tip Datenschutz
Für die Beantwortung werden Anfragen und die per MCP abgerufenen Daten an die Anthropic-API übermittelt. Prüfen Sie vor dem Produktiveinsatz Ihre internen Datenschutzvorgaben.
:::
