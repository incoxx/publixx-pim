# Publixx PIM — PQL Engine

> **Zweck:** PQL-zu-SQL-Transpiler im PIM-Backend. Verwende diesen Skill beim Implementieren des PQL-Parsers, der SQL-Generierung und der PQL-API-Endpunkte.

---

## Überblick

Publixx PQL (Publixx Query Language) ist eine SQL-ähnliche Abfragesprache. Im PIM wird PQL serverseitig geparst und in optimiertes MySQL übersetzt. Publixx kann PQL-Queries per API an das PIM senden und JSON-Datasets zurückbekommen.

```
Publixx → POST /api/v1/pql/query { "pql": "SELECT * WHERE ..." }
       → PIM parst → transpiliert → MySQL → JSON Response
```

---

## Architektur

```
1. API Controller       → Entgegennehmen, Rate-Limit, Auth
2. PqlParser (Pratt)    → PQL-String → AST (Abstract Syntax Tree)
3. PqlValidator         → Felder gegen Attributschema validieren
4. PqlSqlGenerator      → AST → parametrisiertes SQL (Query Builder)
5. Execution Engine     → MySQL ausführen, Redis-Cache prüfen/schreiben
6. ResponseFormatter    → Ergebnis als JSON-Dataset + Score formatieren
```

---

## PQL-Syntax (vollständig)

```sql
SELECT [felder] FROM [quelle] WHERE [bedingungen] [ORDER BY SCORE ASC|DESC]
```

- `FROM data` ist optional (Default)
- `SELECT *` ist Standard
- Keine Klammern — Auswertung links nach rechts
- Felder mit Dot-Notation: `specs.weight.value`
- Strings in einfachen oder doppelten Anführungszeichen
- Zahlen ohne Anführungszeichen
- Boolean: `true` / `false`

---

## Operatoren → MySQL-Mapping

### Standard-Operatoren

| PQL | MySQL | Index |
|-----|-------|-------|
| `=` | `WHERE pav.value_string = ?` | B-Tree |
| `!=` / `<>` | `WHERE pav.value_string != ?` | B-Tree |
| `>` `<` `>=` `<=` | `WHERE pav.value_number >= ?` | B-Tree |
| `LIKE '%text%'` | `MATCH(value_string) AGAINST(? IN BOOLEAN MODE)` wenn `%x%`, sonst `LIKE` | FULLTEXT / B-Tree |
| `NOT LIKE` | `NOT LIKE ?` oder `NOT MATCH AGAINST` | |
| `IN ('a','b')` | `WHERE value_string IN (?, ?)` | B-Tree |
| `NOT IN` | `WHERE value_string NOT IN (?, ?)` | |
| `EXISTS` | `LEFT JOIN ... IS NOT NULL` | Index auf attribute_id |
| `NOT EXISTS` | `LEFT JOIN ... IS NULL` | |
| `BETWEEN x AND y` | `WHERE value_number BETWEEN ? AND ?` | B-Tree Range |
| `NOT BETWEEN` | `WHERE value_number NOT BETWEEN ? AND ?` | |

### Erweiterte Operatoren

| PQL | MySQL-Strategie | Details |
|-----|-----------------|---------|
| `FUZZY 'text' [threshold]` | 1. FULLTEXT MATCH AGAINST als Vorfilter 2. PHP-seitig: Levenshtein (60%) + Trigram (40%) auf Vorauswahl 3. Filter auf Threshold (Default 0.7) | Threshold 0.0-1.0 |
| `NOT FUZZY` | Inverse des FUZZY-Ergebnisses | |
| `SOUNDS_LIKE 'text'` | `SOUNDEX(value_string) = SOUNDEX(?)` + Kölner Phonetik via phonetik_de-Spalte oder PHP UDF | Kölner Phonetik primär (DE), Soundex Fallback (EN) |
| `NOT SOUNDS_LIKE` | Inverse | |
| `SEARCH_FIELDS(a^3, b^2, c)` | UNION / OR über mehrere Attribute mit gewichteter Score-Berechnung | Boost-Faktor als Multiplikator |
| `ORDER BY SCORE DESC` | `ORDER BY _pqlScore DESC` (computed column) | Kein Index (berechnet) |

---

## PQL-zu-SQL Transpilation: Beispiel

### Eingabe
```sql
SELECT * WHERE SEARCH_FIELDS(productName^3, description) FUZZY 'Bohrmaschine' 0.7
  AND price BETWEEN 50 AND 500
  AND status = 'active'
  ORDER BY SCORE DESC
```

### Generiertes SQL
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

Danach: PHP-seitig Levenshtein+Trigram auf `productName` und `description` der Vorauswahl anwenden, unter Threshold 0.7 filtern.

---

## Feld-Auflösung

PQL-Felder werden auf PIM-Attribute gemappt:

```
"productName"         → attributes.technical_name = 'productName' → product_attribute_values
"price"               → attributes.technical_name = 'price' → value_number
"status"              → products.status (Grunddatum, nicht in EAV)
"sku"                 → products.sku
"hierarchy"           → products_search_index.hierarchy_path
"specs.weight.value"  → Hierarchisches Attribut, Dot-Notation aufgelöst
```

Grunddatenfelder (sku, ean, status, name) werden direkt auf `products` / `products_search_index` gemappt. Attributfelder gehen über die EAV-Tabelle `product_attribute_values`.

---

## API

### POST /api/v1/pql/query

```json
// Request
{
  "pql": "SELECT * WHERE status = 'active' AND productImage EXISTS",
  "mapping_id": "uuid-mapping",       // Optional: Export-Mapping für JSON-Struktur
  "lang": ["de", "en"],               // Sprachen
  "limit": 50,                        // Max. Ergebnisse (Default: 50, Max: 500)
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
    { "position": 22, "field": "unknownField", "error": "Unbekanntes Feld. Verfügbar: productName, status, sku, ..." }
  ]
}
```

---

## Caching

- Cache-Key: `pql:hash:{sha256(pql + mapping_id + lang + limit + offset)}`
- TTL: 15 Minuten
- Invalidierung: TTL-basiert (kein Event-Trigger, da PQL-Queries nicht vorhersagbar)
- Warm-up: Keine (Queries zu variabel)

---

## Performance-Ziele

| Query-Typ | Ziel-Latenz |
|-----------|-------------|
| Einfach (=, IN, BETWEEN) | < 50ms |
| LIKE / FULLTEXT | < 80ms |
| FUZZY (mit PHP-Nachfilterung) | < 200ms |
| SOUNDS_LIKE | < 100ms |
| SEARCH_FIELDS + ORDER BY SCORE | < 200ms |

---

## PHP-Hilfsklassen

```php
// Hauptklassen:
App\Services\Pql\PqlParser           // PQL → AST
App\Services\Pql\PqlValidator         // AST Felder validieren
App\Services\Pql\PqlSqlGenerator     // AST → SQL (Query Builder)
App\Services\Pql\PqlExecutor         // Ausführen + Cache + Formatting
App\Services\Pql\FuzzyMatcher        // Levenshtein + Trigram (PHP-seitig)
App\Services\Pql\PhoneticMatcher     // Kölner Phonetik + Soundex

// Kölner Phonetik Algorithmus:
// Wandelt deutschen Text in phonetischen Code um
// "Maier" → "67", "Meyer" → "67", "Schmidt" → "862", "Schmitt" → "862"
// "Müller" → "657", "Mueller" → "657"
```
