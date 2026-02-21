---
title: API-Referenz - Übersicht
---

# API-Referenz

Das Publixx PIM bietet eine vollständige REST-API mit über 90 Endpunkten, die sämtliche Funktionen des Systems programmatisch zugänglich machen. Die API folgt konsistenten Konventionen und unterstützt JSON als Datenformat.

## Basis-URL

Alle API-Endpunkte befinden sich unter dem Pfad:

```
https://pim.ihre-domain.de/api/v1
```

Für die lokale Entwicklung:

```
http://localhost:8000/api/v1
```

## Authentifizierung

Die API verwendet **Bearer-Token-Authentifizierung** über Laravel Sanctum. Bei jedem Aufruf muss ein gültiges Token im `Authorization`-Header mitgesendet werden:

```
Authorization: Bearer {token}
```

Details zur Authentifizierung finden Sie unter [Authentifizierung](./authentifizierung).

## Request-Flow

Der Weg einer API-Anfrage durch das System:

<svg viewBox="0 0 900 200" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto; margin: 2rem 0;">
  <defs>
    <marker id="arrow-api" viewBox="0 0 10 7" refX="10" refY="3.5" markerWidth="10" markerHeight="7" orient="auto-start-reverse">
      <path d="M 0 0 L 10 3.5 L 0 7 z" fill="#6366f1"/>
    </marker>
    <filter id="shadow-api" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.1"/>
    </filter>
  </defs>

  <!-- Client -->
  <rect x="10" y="50" width="120" height="80" rx="12" fill="#f0f0ff" stroke="#6366f1" stroke-width="2" filter="url(#shadow-api)"/>
  <text x="70" y="85" text-anchor="middle" fill="#6366f1" font-size="13" font-weight="bold" font-family="system-ui, sans-serif">Client</text>
  <text x="70" y="105" text-anchor="middle" fill="#4b5563" font-size="11" font-family="system-ui, sans-serif">HTTP-Request</text>

  <!-- Arrow -->
  <line x1="135" y1="90" x2="165" y2="90" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-api)"/>

  <!-- Auth Middleware -->
  <rect x="175" y="50" width="130" height="80" rx="12" fill="#fefce8" stroke="#eab308" stroke-width="2" filter="url(#shadow-api)"/>
  <text x="240" y="85" text-anchor="middle" fill="#a16207" font-size="13" font-weight="bold" font-family="system-ui, sans-serif">Auth</text>
  <text x="240" y="105" text-anchor="middle" fill="#4b5563" font-size="11" font-family="system-ui, sans-serif">Sanctum Token</text>

  <!-- Arrow -->
  <line x1="310" y1="90" x2="340" y2="90" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-api)"/>

  <!-- Controller -->
  <rect x="350" y="50" width="130" height="80" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-api)"/>
  <text x="415" y="85" text-anchor="middle" fill="#0891b2" font-size="13" font-weight="bold" font-family="system-ui, sans-serif">Controller</text>
  <text x="415" y="105" text-anchor="middle" fill="#4b5563" font-size="11" font-family="system-ui, sans-serif">Validierung</text>

  <!-- Arrow -->
  <line x1="485" y1="90" x2="515" y2="90" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-api)"/>

  <!-- Service -->
  <rect x="525" y="50" width="130" height="80" rx="12" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#shadow-api)"/>
  <text x="590" y="85" text-anchor="middle" fill="#16a34a" font-size="13" font-weight="bold" font-family="system-ui, sans-serif">Service</text>
  <text x="590" y="105" text-anchor="middle" fill="#4b5563" font-size="11" font-family="system-ui, sans-serif">Geschäftslogik</text>

  <!-- Arrow -->
  <line x1="660" y1="90" x2="690" y2="90" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-api)"/>

  <!-- Response -->
  <rect x="700" y="50" width="130" height="80" rx="12" fill="#fdf2f8" stroke="#db2777" stroke-width="2" filter="url(#shadow-api)"/>
  <text x="765" y="85" text-anchor="middle" fill="#db2777" font-size="13" font-weight="bold" font-family="system-ui, sans-serif">Response</text>
  <text x="765" y="105" text-anchor="middle" fill="#4b5563" font-size="11" font-family="system-ui, sans-serif">JSON-Antwort</text>

  <!-- Labels -->
  <text x="450" y="30" text-anchor="middle" fill="#6b7280" font-size="12" font-family="system-ui, sans-serif">API Request Flow</text>
  <text x="450" y="175" text-anchor="middle" fill="#6b7280" font-size="11" font-family="system-ui, sans-serif">Client → Authentifizierung → Eingabevalidierung → Geschäftslogik → JSON-Response</text>
</svg>

## Konventionen

### Datenformat

Alle Anfragen und Antworten verwenden **JSON**. Setzen Sie die entsprechenden Header:

```
Content-Type: application/json
Accept: application/json
```

### Paginierung

Listen-Endpunkte unterstützen Paginierung über Query-Parameter:

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `page` | Integer | `1` | Aktuelle Seite |
| `per_page` | Integer | `25` | Einträge pro Seite (max. 100) |

### Sortierung

Sortierung wird über den `sort`-Parameter gesteuert. Aufsteigende Sortierung ist Standard, ein Minus-Präfix sortiert absteigend:

```
GET /api/v1/products?sort=name          # Aufsteigend nach Name
GET /api/v1/products?sort=-created_at   # Absteigend nach Erstelldatum
```

### Filterung

Filter werden als Query-Parameter übergeben:

```
GET /api/v1/products?filter[status]=active&filter[product_type]=simple
```

### Includes (Eager Loading)

Verwandte Ressourcen können über den `include`-Parameter mitgeladen werden:

```
GET /api/v1/products?include=variants,media,prices
```

### Sparse Fields

Mit dem `fields`-Parameter können Sie die zurückgegebenen Felder einschränken:

```
GET /api/v1/products?fields=id,sku,name,status
```

### Suche

Freitextsuche über den `search`-Parameter:

```
GET /api/v1/products?search=Bohrmaschine
```

### Sprachauswahl

Bei mehrsprachigen Ressourcen bestimmt der `lang`-Parameter die Sprache:

```
GET /api/v1/products?lang=de
```

## Fehlerformat

Fehler werden im Format **RFC 7807 (Problem Details for HTTP APIs)** zurückgegeben:

```json
{
  "type": "https://pim.example.com/docs/errors/validation-error",
  "title": "Validierungsfehler",
  "status": 422,
  "detail": "Die übermittelten Daten sind ungültig.",
  "errors": {
    "sku": ["Das Feld 'sku' ist ein Pflichtfeld."],
    "name_de": ["Das Feld 'name_de' darf maximal 255 Zeichen lang sein."]
  }
}
```

### HTTP-Statuscodes

| Code | Bedeutung |
|---|---|
| `200` | Erfolgreiche Anfrage |
| `201` | Ressource erfolgreich erstellt |
| `204` | Erfolgreich, kein Inhalt (z. B. nach Löschen) |
| `400` | Ungültige Anfrage |
| `401` | Nicht authentifiziert |
| `403` | Keine Berechtigung |
| `404` | Ressource nicht gefunden |
| `422` | Validierungsfehler |
| `429` | Rate Limit erreicht |
| `500` | Interner Serverfehler |

## Rate Limiting

Die API ist durch Rate Limiting geschützt:

| Kategorie | Limit | Beschreibung |
|---|---|---|
| **Standard** | 60 Anfragen/Minute | Allgemeine API-Endpunkte |
| **Export** | 600 Anfragen/Minute | Export-Endpunkte (höheres Limit) |

Bei Überschreitung wird ein `429 Too Many Requests`-Status mit folgenden Headern zurückgegeben:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
Retry-After: 45
```

## Endpunkt-Gruppen

Die API gliedert sich in folgende Bereiche:

### [Authentifizierung](./authentifizierung)

Login, Logout, Token-Verwaltung und Benutzerinformationen.

### [Produkte](./produkte)

CRUD-Operationen für Produkte, Attributwerte, Varianten, Medien, Preise und Relationen.

### [Attribute](./attribute)

Verwaltung von Attributen, Attributtypen, Einheitengruppen, Einheiten, Wertelisten, Wertelisteneinträgen und Attributansichten.

### [Hierarchien](./hierarchien)

Verwaltung von Hierarchien, Baumstrukturen, Knoten und knotenspezifischen Attributzuordnungen.

### [PQL](./pql)

PQL-Abfragen ausführen, validieren und analysieren.

### Export

Export-Endpunkte für JSON und Publixx-Format. Dokumentiert unter [JSON-Export](/de/export/json-export) und [Publixx-Export](/de/export/publixx-export).

### Import

Import-Endpunkte für Upload, Validierung und Ausführung. Dokumentiert unter [Import](/de/import/).

### Benutzerverwaltung

CRUD-Operationen für Benutzer, Rollen und Berechtigungen.

### Sprachen

Verwaltung der verfügbaren Inhaltssprachen.

### Medien

Upload, Verwaltung und Zuordnung von Medien.

### Preise

Verwaltung von Preisarten, Währungen und Produktpreisen.
