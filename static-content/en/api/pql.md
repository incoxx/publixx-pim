---
title: PQL Query Language
---

# PQL Query Language (Product Query Language)

PQL is a domain-specific query language for anyPIM that allows you to filter and search products using arbitrary attribute combinations.

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/pql/query` | Execute PQL query |
| `POST` | `/api/v1/pql/query/count` | Return only the hit count |
| `POST` | `/api/v1/pql/query/validate` | Validate PQL expression |
| `POST` | `/api/v1/pql/query/explain` | Return the query plan (AST, SQL) |

## Execute Query

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

| Field | Type | Description |
|---|---|---|
| `pql` | string (required) | The PQL expression (5–5000 chars). Sorting via `ORDER BY` inside the expression. |
| `lang` | string array | Language codes (2 chars each), default `["de"]` |
| `limit` | integer | 1–500, default 50 |
| `offset` | integer | ≥ 0, default 0 |
| `mapping_id` | UUID | optional export mapping |

### Response (200 OK)

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

## Validate PQL Expression

Checks a PQL expression for syntactic and semantic correctness without executing it:

```
POST /api/v1/pql/query/validate
```

**Request Body:**

```json
{
  "pql": "status = 'active' AND preis > 100"
}
```

### Response (200 OK)

```json
{
  "valid": true,
  "ast": { }
}
```

The `ast` key holds the parsed query tree. For an invalid expression the endpoint responds with `422` and additionally includes an `errors` array; `ast` is then `null`.

### Error Response (422)

```json
{
  "valid": false,
  "errors": [
    { "error": "Unknown operator 'CONTAINS'. Use 'LIKE', 'SOUNDS_LIKE' or 'FUZZY'." }
  ],
  "ast": null
}
```

## Explain Query Plan

Returns the query tree (AST), the generated SQL incl. bindings and a cost estimate, without executing the query:

```
POST /api/v1/pql/query/explain
```

**Request Body:**

```json
{
  "pql": "kategorie = 'Werkzeuge' AND preis < 500"
}
```

### Response (200 OK)

```json
{
  "ast": { },
  "sql": "SELECT ... FROM products WHERE ...",
  "bindings": ["Werkzeuge", 500],
  "validation": { "valid": true },
  "estimated_cost": 127
}
```

## PQL Syntax

### Comparison Operators

| Operator | Description | Example |
|---|---|---|
| `=` | Equal | `status = 'active'` |
| `!=` | Not equal | `status != 'draft'` |
| `>` | Greater than | `preis > 100` |
| `>=` | Greater than or equal | `preis >= 100` |
| `<` | Less than | `preis < 500` |
| `<=` | Less than or equal | `preis <= 500` |

### Range and Set Operators

| Operator | Description | Example |
|---|---|---|
| `IN` | In value list | `farbe IN ('rot', 'blau', 'grün')` |
| `NOT IN` | Not in value list | `status NOT IN ('draft', 'deleted')` |
| `BETWEEN` | Value range | `preis BETWEEN 50 AND 200` |
| `IS NULL` | Value is empty | `beschreibung IS NULL` |
| `IS NOT NULL` | Value exists | `sku IS NOT NULL` |

### Text Operators

| Operator | Description | Example |
|---|---|---|
| `LIKE` | Text pattern (wildcard `%`) | `name LIKE '%Bohr%'` |
| `SOUNDS_LIKE` | Phonetic similarity | `name SOUNDS_LIKE 'Bohrmaschine'` |
| `FUZZY` | Fuzzy search (threshold 0–1) | `FUZZY(name, 'Bohrmaschine', 0.8)` |

All text operators can be negated with `NOT` (e.g. `name NOT LIKE '%old%'`). To search across multiple fields at once, `SEARCH_FIELDS` is available.

### Logical Operators

| Operator | Description | Example |
|---|---|---|
| `AND` | Conjunction | `status = 'active' AND preis > 100` |
| `OR` | Disjunction | `farbe = 'rot' OR farbe = 'blau'` |
| `NOT` | Negation | `NOT status = 'draft'` |
| `()` | Grouping | `(farbe = 'rot' OR farbe = 'blau') AND preis < 500` |

### Sorting

```
ORDER BY preis ASC
ORDER BY updated_at DESC
ORDER BY SCORE    -- Relevance for text search
```

## Complex Examples

### Filter products by multiple criteria

```sql
status = 'active'
  AND kategorie IN ('Werkzeuge', 'Maschinen')
  AND preis BETWEEN 50 AND 500
  AND FUZZY(name, 'Bohrmaschine', 0.8)
  AND beschreibung IS NOT NULL
ORDER BY SCORE
```

### Nested conditions

```sql
status = 'active'
  AND (
    (kategorie = 'Elektrowerkzeuge' AND preis < 1000)
    OR (kategorie = 'Handwerkzeuge' AND preis < 200)
  )
  AND lagerbestand > 0
ORDER BY preis ASC
```
