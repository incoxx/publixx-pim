---
title: PQL Query Language
---

# PQL Query Language (Product Query Language)

PQL is a domain-specific query language for anyPIM that allows you to filter and search products using arbitrary attribute combinations.

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/pql/query` | Execute PQL query |
| `POST` | `/api/v1/pql/validate` | Validate PQL expression |
| `POST` | `/api/v1/pql/analyze` | Analyze PQL expression |

## Execute Query

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

### Response (200 OK)

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

## Validate PQL Expression

Checks a PQL expression for syntactic and semantic correctness without executing it:

```
POST /api/v1/pql/validate
```

**Request Body:**

```json
{
  "query": "status = 'active' AND preis > 100"
}
```

### Response (200 OK)

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

### Error Response (422)

```json
{
  "valid": false,
  "errors": [
    {
      "position": 15,
      "message": "Unknown operator 'LIKE'. Use 'CONTAINS' or 'FUZZY'."
    }
  ]
}
```

## Analyze PQL Expression

Returns information about the referenced attributes and estimated result count:

```
POST /api/v1/pql/analyze
```

**Request Body:**

```json
{
  "query": "kategorie = 'Werkzeuge' AND preis < 500"
}
```

### Response (200 OK)

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
| `CONTAINS` | Contains text | `name CONTAINS 'Bohr'` |
| `STARTS WITH` | Starts with | `sku STARTS WITH 'WRK-'` |
| `ENDS WITH` | Ends with | `sku ENDS WITH '-PRO'` |
| `FUZZY` | Fuzzy search | `FUZZY(name, 'Bohrmaschine', 0.8)` |

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
