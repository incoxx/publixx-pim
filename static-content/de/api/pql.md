---
title: PQL-Abfragesprache
---

# PQL-Abfragesprache (Product Query Language)

PQL ist eine domänenspezifische Abfragesprache für das Publixx PIM, die es ermöglicht, Produkte über beliebige Attributkombinationen zu filtern und zu durchsuchen.

## API-Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `POST` | `/api/v1/pql/query` | PQL-Abfrage ausführen |
| `POST` | `/api/v1/pql/validate` | PQL-Ausdruck validieren |
| `POST` | `/api/v1/pql/analyze` | PQL-Ausdruck analysieren |

## Abfrage ausführen

```
POST /api/v1/pql/query
```

**Request Body:**

```json
{
  "query": "status = 'active' AND kategorie IN ('Werkzeuge', 'Maschinen') AND preis BETWEEN 50 AND 200",
  "lang": "de",
  "page": 1,
  "per_page": 25,
  "sort": "-updated_at"
}
```

### Antwort (200 OK)

```json
{
  "data": [
    {
      "id": "uuid-1",
      "sku": "WRK-001",
      "name": "Bohrmaschine Pro",
      "status": "active",
      "score": 0.95
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 42,
    "query_time_ms": 12
  }
}
```

## PQL-Ausdruck validieren

Prüft einen PQL-Ausdruck auf syntaktische und semantische Korrektheit, ohne ihn auszuführen:

```
POST /api/v1/pql/validate
```

**Request Body:**

```json
{
  "query": "status = 'active' AND preis > 100"
}
```

### Antwort (200 OK)

```json
{
  "valid": true,
  "parsed": {
    "type": "AND",
    "conditions": [
      { "field": "status", "operator": "=", "value": "active" },
      { "field": "preis", "operator": ">", "value": 100 }
    ]
  }
}
```

### Antwort bei Fehler (422)

```json
{
  "valid": false,
  "errors": [
    {
      "position": 15,
      "message": "Unbekannter Operator 'LIKE'. Verwenden Sie 'CONTAINS' oder 'FUZZY'."
    }
  ]
}
```

## PQL-Ausdruck analysieren

Gibt Informationen über die referenzierten Attribute und geschätzte Ergebnismenge zurück:

```
POST /api/v1/pql/analyze
```

**Request Body:**

```json
{
  "query": "kategorie = 'Werkzeuge' AND preis < 500"
}
```

### Antwort (200 OK)

```json
{
  "valid": true,
  "referenced_attributes": [
    { "code": "kategorie", "type": "select", "indexed": true },
    { "code": "preis", "type": "number", "indexed": true }
  ],
  "estimated_results": 127
}
```

## PQL-Syntax

### Vergleichsoperatoren

| Operator | Beschreibung | Beispiel |
|---|---|---|
| `=` | Gleich | `status = 'active'` |
| `!=` | Ungleich | `status != 'draft'` |
| `>` | Größer als | `preis > 100` |
| `>=` | Größer oder gleich | `preis >= 100` |
| `<` | Kleiner als | `preis < 500` |
| `<=` | Kleiner oder gleich | `preis <= 500` |

### Bereichs- und Mengenoperatoren

| Operator | Beschreibung | Beispiel |
|---|---|---|
| `IN` | In Werteliste | `farbe IN ('rot', 'blau', 'grün')` |
| `NOT IN` | Nicht in Werteliste | `status NOT IN ('draft', 'deleted')` |
| `BETWEEN` | Wertebereich | `preis BETWEEN 50 AND 200` |
| `IS NULL` | Wert ist leer | `beschreibung IS NULL` |
| `IS NOT NULL` | Wert vorhanden | `sku IS NOT NULL` |

### Textoperatoren

| Operator | Beschreibung | Beispiel |
|---|---|---|
| `CONTAINS` | Enthält Text | `name CONTAINS 'Bohr'` |
| `STARTS WITH` | Beginnt mit | `sku STARTS WITH 'WRK-'` |
| `ENDS WITH` | Endet mit | `sku ENDS WITH '-PRO'` |
| `FUZZY` | Unscharfe Suche | `FUZZY(name, 'Bohrmaschine', 0.8)` |

### Logische Operatoren

| Operator | Beschreibung | Beispiel |
|---|---|---|
| `AND` | Und-Verknüpfung | `status = 'active' AND preis > 100` |
| `OR` | Oder-Verknüpfung | `farbe = 'rot' OR farbe = 'blau'` |
| `NOT` | Negation | `NOT status = 'draft'` |
| `()` | Gruppierung | `(farbe = 'rot' OR farbe = 'blau') AND preis < 500` |

### Sortierung

```
ORDER BY preis ASC
ORDER BY updated_at DESC
ORDER BY SCORE    -- Relevanz bei Textsuche
```

## Komplexe Beispiele

### Produkte nach mehreren Kriterien filtern

```sql
status = 'active'
  AND kategorie IN ('Werkzeuge', 'Maschinen')
  AND preis BETWEEN 50 AND 500
  AND FUZZY(name, 'Bohrmaschine', 0.8)
  AND beschreibung IS NOT NULL
ORDER BY SCORE
```

### Verschachtelte Bedingungen

```sql
status = 'active'
  AND (
    (kategorie = 'Elektrowerkzeuge' AND preis < 1000)
    OR (kategorie = 'Handwerkzeuge' AND preis < 200)
  )
  AND lagerbestand > 0
ORDER BY preis ASC
```
