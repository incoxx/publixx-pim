# anyPIM — PQL Engine

> **Purpose:** PQL-to-SQL transpiler in the PIM backend. Use this skill when implementing the PQL parser, SQL generation, and the PQL API endpoints.

---

## Overview

Publixx PQL (Publixx Query Language) is an SQL-like query language. In the PIM, PQL is parsed server-side and translated into optimized MySQL. Publixx can send PQL queries to the PIM via API and receive JSON datasets in return.

```
Publixx → POST /api/v1/pql/query { "pql": "SELECT * WHERE ..." }
       → PIM parses → transpiles → MySQL → JSON Response
```

---

## Architecture

```
1. API Controller       → Receive, rate-limit, auth
2. PqlParser (Pratt)    → PQL string → AST (Abstract Syntax Tree)
3. PqlValidator         → Validate fields against attribute schema
4. PqlSqlGenerator      → AST → parameterized SQL (Query Builder)
5. Execution Engine     → Execute MySQL, check/write Redis cache
6. ResponseFormatter    → Format result as JSON dataset + score
```

---

## PQL Syntax (complete)

```sql
SELECT [fields] FROM [source] WHERE [conditions] [ORDER BY SCORE ASC|DESC]
```

- `FROM data` is optional (default)
- `SELECT *` is the default
- No parentheses — evaluation left to right
- Fields with dot notation: `specs.weight.value`
- Strings in single or double quotes
- Numbers without quotes
- Boolean: `true` / `false`

---

## Operators → MySQL Mapping

### Standard Operators

| PQL | MySQL | Index |
|-----|-------|-------|
| `=` | `WHERE pav.value_string = ?` | B-Tree |
| `!=` / `<>` | `WHERE pav.value_string != ?` | B-Tree |
| `>` `<` `>=` `<=` | `WHERE pav.value_number >= ?` | B-Tree |
| `LIKE '%text%'` | `MATCH(value_string) AGAINST(? IN BOOLEAN MODE)` when `%x%`, otherwise `LIKE` | FULLTEXT / B-Tree |
| `NOT LIKE` | `NOT LIKE ?` or `NOT MATCH AGAINST` | |
| `IN ('a','b')` | `WHERE value_string IN (?, ?)` | B-Tree |
| `NOT IN` | `WHERE value_string NOT IN (?, ?)` | |
| `EXISTS` | `LEFT JOIN ... IS NOT NULL` | Index on attribute_id |
| `NOT EXISTS` | `LEFT JOIN ... IS NULL` | |
| `BETWEEN x AND y` | `WHERE value_number BETWEEN ? AND ?` | B-Tree Range |
| `NOT BETWEEN` | `WHERE value_number NOT BETWEEN ? AND ?` | |

### Extended Operators

| PQL | MySQL Strategy | Details |
|-----|----------------|---------|
| `FUZZY 'text' [threshold]` | 1. FULLTEXT MATCH AGAINST as pre-filter 2. PHP-side: Levenshtein (60%) + Trigram (40%) on pre-selection 3. Filter on threshold (default 0.7) | Threshold 0.0-1.0 |
| `NOT FUZZY` | Inverse of FUZZY result | |
| `SOUNDS_LIKE 'text'` | `SOUNDEX(value_string) = SOUNDEX(?)` + Cologne phonetics via phonetik_de column or PHP UDF | Cologne phonetics primary (DE), Soundex fallback (EN) |
| `NOT SOUNDS_LIKE` | Inverse | |
| `SEARCH_FIELDS(a^3, b^2, c)` | UNION / OR across multiple attributes with weighted score calculation | Boost factor as multiplier |
| `ORDER BY SCORE DESC` | `ORDER BY _pqlScore DESC` (computed column) | No index (computed) |

---

## PQL-to-SQL Transpilation: Example

### Input
```sql
SELECT * WHERE SEARCH_FIELDS(productName^3, description) FUZZY 'Bohrmaschine' 0.7
  AND price BETWEEN 50 AND 500
  AND status = 'active'
  ORDER BY SCORE DESC
```

### Generated SQL
```sql
WITH fuzzy_candidates AS (
  SELECT p.id, p.sku,
    psi.name_de AS productName,
    psi.description_de AS description,
    MATCH(psi.name_de) AGAINST('Bohrmaschine' IN BOOLEAN MODE) * 3 AS name_score,
    MATCH(psi.description_de) AGAINST('Bohrmaschine' IN BOOLEAN MODE) * 1 AS desc_score
  FROM products p
  JOIN products_search_index psi ON p.id = psi.product_id
  WHERE p.status = 'active'
    AND psi.list_price BETWEEN 50 AND 500
    AND (MATCH(psi.name_de) AGAINST('Bohrmaschine*' IN BOOLEAN MODE)
      OR MATCH(psi.description_de) AGAINST('Bohrmaschine*' IN BOOLEAN MODE))
)
SELECT *, (name_score + desc_score) AS _pqlScore
FROM fuzzy_candidates
ORDER BY _pqlScore DESC
LIMIT 50;
```

Afterwards: Apply PHP-side Levenshtein+Trigram on `productName` and `description` of the pre-selection, filter below threshold 0.7.

---

## Field Resolution

PQL fields are mapped to PIM attributes:

```
"productName"         → attributes.technical_name = 'productName' → product_attribute_values
"price"               → attributes.technical_name = 'price' → value_number
"status"              → products.status (base field, not in EAV)
"sku"                 → products.sku
"hierarchy"           → products_search_index.hierarchy_path
"specs.weight.value"  → Hierarchical attribute, dot notation resolved
```

Base data fields (sku, ean, status, name) are mapped directly to `products` / `products_search_index`. Attribute fields go through the EAV table `product_attribute_values`.

---

## API

### POST /api/v1/pql/query

```json
// Request
{
  "pql": "SELECT * WHERE status = 'active' AND productImage EXISTS",
  "mapping_id": "uuid-mapping",       // Optional: export mapping for JSON structure
  "lang": ["de", "en"],               // Languages
  "limit": 50,                        // Max. results (default: 50, max: 500)
  "offset": 0                         // Pagination
}

// Response
{
  "meta": {
    "total": 1247,
    "returned": 50,
    "offset": 0,
    "query_time_ms": 23,
    "cache_hit": true,
    "pql_parsed": "SELECT * WHERE status = 'active' AND productImage EXISTS"
  },
  "data": [ { ... }, { ... } ]
}
```

### POST /api/v1/pql/query/validate

```json
// Request
{ "pql": "SELECT * WHERE unknownField > 5" }

// Response 422
{
  "valid": false,
  "errors": [
    { "position": 22, "field": "unknownField", "error": "Unknown field. Available: productName, status, sku, ..." }
  ]
}
```

---

## Caching

- Cache key: `pql:hash:{sha256(pql + mapping_id + lang + limit + offset)}`
- TTL: 15 minutes
- Invalidation: TTL-based (no event trigger, since PQL queries are not predictable)
- Warm-up: None (queries too variable)

---

## Performance Targets

| Query Type | Target Latency |
|------------|----------------|
| Simple (=, IN, BETWEEN) | < 50ms |
| LIKE / FULLTEXT | < 80ms |
| FUZZY (with PHP post-filtering) | < 200ms |
| SOUNDS_LIKE | < 100ms |
| SEARCH_FIELDS + ORDER BY SCORE | < 200ms |

---

## PHP Helper Classes

```php
// Main classes:
App\Services\Pql\PqlParser           // PQL → AST
App\Services\Pql\PqlValidator         // Validate AST fields
App\Services\Pql\PqlSqlGenerator     // AST → SQL (Query Builder)
App\Services\Pql\PqlExecutor         // Execute + cache + formatting
App\Services\Pql\FuzzyMatcher        // Levenshtein + Trigram (PHP-side)
App\Services\Pql\PhoneticMatcher     // Cologne phonetics + Soundex

// Cologne Phonetics algorithm:
// Converts German text to phonetic code
// "Maier" → "67", "Meyer" → "67", "Schmidt" → "862", "Schmitt" → "862"
// "Müller" → "657", "Mueller" → "657"
```
