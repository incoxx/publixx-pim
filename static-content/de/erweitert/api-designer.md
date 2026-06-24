---
title: API-Designer
---

# API-Designer

Der **API-Designer** ist ein No-Code-Werkzeug, mit dem Sie eigene Datenendpunkte für Produktdaten zusammenstellen — ohne zu programmieren. Sie wählen Felder aus, legen Filter und Ausgabeformat fest und erhalten einen sofort nutzbaren Endpunkt.

## API-Template anlegen

1. Navigation: **API-Designer → „Neues API-Template"**, Namen vergeben.
2. Im Editor Felder aus der **Feld-Palette** auswählen:
   - **Basisfelder** (SKU, Name, EAN, Status, Produkttyp, Datumsangaben …)
   - **Attribute** (eigene Merkmale)
   - **Preise** (nach Preistyp)
   - **Medien** (nach Verwendungstyp)
   - **Beziehungen** (nach Beziehungstyp)
3. Optional nach Produkttyp, Kategorie oder Status **gruppieren**.
4. Über ein verknüpftes **Suchprofil** festlegen, welche Produkte ausgegeben werden.
5. **Ausgabeformat** wählen: **JSON** oder **GraphQL**.

## Testen & Veröffentlichen

- **Vorschau** zeigt eine Beispielausgabe mit wenigen Produkten; bei GraphQL zusätzlich Schema (SDL) und Beispielabfrage.
- Mit *aktiv* schalten Sie das Template scharf; *geteilt* macht es für andere sichtbar.
- Der Endpunkt ist anschließend unter `…/api-streams/{slug}` erreichbar.

## Authentifizierung & Limits

| Einstellung | Optionen |
|---|---|
| **Auth-Typ** | keine · Bearer-Token · API-Key (eigener Header) |
| **Rate-Limit** | Anfragen pro Minute begrenzen |
| **Schlüssel** | API-Key bei Bedarf neu generieren (alter wird ungültig) |

::: tip MCP für Claude
Templates lassen sich optional als **MCP-Endpunkt** aktivieren und damit als Custom Connector in Claude einbinden — Claude kann dann live auf die definierten Produktdaten zugreifen.
:::

## Abgrenzung zur JSON-API

Der API-Designer erzeugt **maßgeschneiderte** Endpunkte für einzelne Anwendungsfälle. Die allgemeine, vollständige Schnittstelle ist unter [JSON API](/de/api/) dokumentiert.

## Berechtigungen & Modul

Der API-Designer ist über das Modul `api_designer` freigeschaltet. Nicht-geteilte Templates sind nur für ihren Ersteller zugänglich. Konfiguration unter [Rollen & Berechtigungen](/de/administration/rollen).
