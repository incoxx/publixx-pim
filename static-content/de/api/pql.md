---
title: PQL-Abfragesprache
---

# PQL-Abfragesprache (Product Query Language)

PQL ist eine domänenspezifische Abfragesprache für das anyPIM, die es ermöglicht, Produkte über beliebige Attributkombinationen zu filtern und zu durchsuchen.

## API-Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `POST` | `/api/v1/pql/query` | PQL-Abfrage ausführen |
| `POST` | `/api/v1/pql/query/count` | Nur die Trefferanzahl ermitteln |
| `POST` | `/api/v1/pql/query/validate` | PQL-Ausdruck validieren |
| `POST` | `/api/v1/pql/query/explain` | Query-Plan (AST, SQL) ausgeben |

## Abfrage ausführen

```
POST /api/v1/pql/query
```

**Request Body:**

```json
{
  "pql": "status = 'active' AND kategorie IN ('Werkzeuge', 'Maschinen') AND preis BETWEEN 50 AND 200 ORDER BY updated_at DESC",
  "lang": ["de"],
  "limit": 25,
  "offset": 0
}
```

| Feld | Typ | Beschreibung |
|---|---|---|
| `pql` | String (Pflicht) | Der PQL-Ausdruck (5–5000 Zeichen). Sortierung über `ORDER BY` im Ausdruck. |
| `lang` | String-Array | Sprachcodes (je 2 Zeichen), Standard `["de"]` |
| `limit` | Integer | 1–500, Standard 50 |
| `offset` | Integer | ≥ 0, Standard 0 |
| `mapping_id` | UUID | optionales Export-Mapping |

### Antwort (200 OK)

```json
{
  "meta": {
    "total": 42,
    "returned": 25,
    "offset": 0,
    "query_time_ms": 12,
    "cache_hit": false,
    "pql_parsed": "status = 'active' AND ..."
  },
  "data": [
    {
      "id": "uuid-1",
      "sku": "WRK-001",
      "name": "Bohrmaschine Pro",
      "status": "active"
    }
  ]
}
```

## PQL-Ausdruck validieren

Prüft einen PQL-Ausdruck auf syntaktische und semantische Korrektheit, ohne ihn auszuführen:

```
POST /api/v1/pql/query/validate
```

**Request Body:**

```json
{
  "pql": "status = 'active' AND preis > 100"
}
```

### Antwort (200 OK)

```json
{
  "valid": true,
  "ast": { }
}
```

Der `ast`-Schlüssel enthält den geparsten Abfragebaum. Bei einem ungültigen Ausdruck antwortet der Endpunkt mit `422` und enthält zusätzlich ein `errors`-Array; `ast` ist dann `null`.

### Antwort bei Fehler (422)

```json
{
  "valid": false,
  "errors": [
    { "error": "Unbekannter Operator 'CONTAINS'. Verwenden Sie 'LIKE', 'SOUNDS_LIKE' oder 'FUZZY'." }
  ],
  "ast": null
}
```

## Query-Plan ausgeben

Gibt den Abfragebaum (AST), das generierte SQL inkl. Bindings sowie eine Kostenschätzung zurück, ohne die Abfrage auszuführen:

```
POST /api/v1/pql/query/explain
```

**Request Body:**

```json
{
  "pql": "kategorie = 'Werkzeuge' AND preis < 500"
}
```

### Antwort (200 OK)

```json
{
  "ast": { },
  "sql": "SELECT ... FROM products WHERE ...",
  "bindings": ["Werkzeuge", 500],
  "validation": { "valid": true },
  "estimated_cost": 127
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
| `LIKE` | Text-Muster (Platzhalter `%`) | `name LIKE '%Bohr%'` |
| `SOUNDS_LIKE` | Phonetische Ähnlichkeit | `name SOUNDS_LIKE 'Bohrmaschine'` |
| `FUZZY` | Unscharfe Suche (Schwellwert 0–1) | `FUZZY(name, 'Bohrmaschine', 0.8)` |

Alle Textoperatoren lassen sich mit `NOT` negieren (z. B. `name NOT LIKE '%alt%'`). Für die Suche über mehrere Felder gleichzeitig steht `SEARCH_FIELDS` zur Verfügung.

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
