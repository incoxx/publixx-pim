# anyPIM — Excel Import

> **Purpose:** Excel-based data import. Use this skill when implementing the import engine, validation, template generation, and import UI.

---

## Principle

Data comes from departments without JSON expertise. Excel is the universal exchange format. One central template with 14 sheets — not all need to be filled in, each sheet can be imported individually.

---

## Template: 14 Sheets

| No | Sheet | Purpose | Depends on | Source |
|----|-------|---------|------------|--------|
| 01 | Produkttypen | Define types | - | PIM Team |
| 02 | Attributgruppen | Attribute types | - | Data Steward |
| 03 | Einheiten | Groups + units | - | Data Steward |
| 04 | Wertelisten | Lists + values | - | Data Steward |
| 05 | Attribute | Attribute definitions | 02, 03, 04 | Data Steward |
| 06 | Hierarchien | Tree structures | - | Data Steward |
| 07 | Hierarchie_Attribute | Attribute → node | 05, 06 | Data Steward |
| 08 | Produkte | Create products | 01 | PM |
| 09 | Produktwerte | Attribute values | 05, 08 | PM |
| 10 | Varianten | Variants | 08 | PM |
| 11 | Produkt_Hierarchien | Product → node | 06, 08 | PM |
| 12 | Produktbeziehungen | Accessories etc. | 08 | PM |
| 13 | Preise | Prices | 08 | Sales |
| 14 | Medien | Media assignment | 08 | Marketing |

---

## Sheet Specifications (Columns)

### 05_Attribute (Core sheet)

| Column | Header | Required | Type | Description |
|--------|--------|----------|------|-------------|
| A | Technischer Name* | Yes | String | Unique |
| B | Name (Deutsch)* | Yes | String | Display name |
| C | Name (Englisch) | No | String | |
| D | Beschreibung | No | Text | |
| E | Datentyp* | Yes | Enum | String/Number/Float/Date/Flag/Selection/Dictionary/Collection |
| F | Attributgruppe | No | String | Technical name from sheet 02 |
| G | Werteliste | No | String | Technical name from sheet 04 |
| H | Einheitengruppe | No | String | Technical name from sheet 03 |
| I | Standard-Einheit | No | String | Abbreviation (e.g. kg) |
| J | Vermehrbar | No | Yes/No | |
| K | Max. Vermehrungen | No | Number | |
| L | Übersetzbar | No | Yes/No | |
| M | Pflicht | No | Optional/Required | |
| N | Eindeutig | No | Yes/No | |
| O | Suchbar | No | Yes/No | |
| P | Vererbbar | No | Yes/No | |
| Q | Übergeordnetes Attribut | No | String | Technical name of parent attribute |
| R | Quellsystem | No | String | PIM/SAP ERP/Other |
| S | Sichten | No | String | Comma-separated |

### 06_Hierarchien

| Column | Header | Description |
|--------|--------|-------------|
| A | Hierarchie* | Technical name |
| B | Typ* | master / output |
| C-H | Ebene 1-6 | Deepest populated column = the node |

### 08_Produkte

| Column | Header | Required | Description |
|--------|--------|----------|-------------|
| A | SKU* | Yes | Article number (identifier) |
| B | Produktname* | Yes | |
| C | Produktname (EN) | No | |
| D | Produkttyp* | Yes | Technical name |
| E | EAN | No | Only for physical products |
| F | Status | No | draft/active/inactive |

### 09_Produktwerte

| Column | Header | Required | Description |
|--------|--------|----------|-------------|
| A | SKU* | Yes | Product reference |
| B | Attribut* | Yes | Technical name OR display name |
| C | Wert* | Yes | Matching the data type |
| D | Einheit | No | Abbreviation (numeric only) |
| E | Sprache | No | ISO code (translatable only) |
| F | Index | No | For multiplied attributes |

---

## Import Workflow (3 Phases)

### Phase 1: Upload
```
POST /api/v1/imports (multipart/form-data: file)
→ Save file
→ Detect sheets
→ Status: "uploaded"
```

### Phase 2: Validation
```
GET /api/v1/imports/{id} (automatically after upload)
→ Schema check (required fields, data types, enums)
→ Reference resolution (technical names → UUIDs)
→ Dependency check (do referenced entities exist?)
→ Duplicate detection (create vs. update)
→ Fuzzy matching on non-resolution
→ Status: "validated"
```

### Phase 3: Execution
```
GET /api/v1/imports/{id}/preview → Diff preview
POST /api/v1/imports/{id}/execute → Confirm
→ Async via Laravel Queue (for > 100 rows)
→ Status: "executing" → "completed"
GET /api/v1/imports/{id}/result → Report
```

---

## Validation Response

```json
{
  "import_id": "uuid",
  "status": "validated",
  "sheets_found": ["05_Attribute", "08_Produkte", "09_Produktwerte"],
  "summary": {
    "05_Attribute": { "total": 250, "valid": 247, "errors": 3, "creates": 180, "updates": 67 },
    "08_Produkte": { "total": 1200, "valid": 1198, "errors": 2, "creates": 800, "updates": 398 }
  },
  "errors": [
    {
      "sheet": "05_Attribute", "row": 45, "column": "E", "field": "Datentyp",
      "value": "Texxt",
      "error": "Invalid data type. Allowed: String, Number, Float, ...",
      "suggestion": null
    },
    {
      "sheet": "09_Produktwerte", "row": 8401, "column": "B", "field": "Attribut",
      "value": "Gwicht",
      "error": "Attribute not found.",
      "suggestion": "Gewicht"
    }
  ]
}
```

---

## Smart Matching (Fuzzy Resolution)

- Levenshtein distance (threshold: 85% similarity)
- Case-insensitive: "gewicht" = "Gewicht" = "GEWICHT"
- Trim + normalization (whitespace)
- Suggestions: "Did you mean: ...?"
- Strict mode disables fuzzy

---

## Update Logic (Upsert)

| Entity | Identified by | On existence |
|--------|--------------|-------------|
| Produkttyp | technical_name | Update |
| Attribut | technical_name | Update |
| Einheitengruppe | technical_name | Update |
| Werteliste | technical_name | Update |
| Hierarchieknoten | Path (levels) | Skip |
| Produkt | SKU | Update |
| Produktwert | SKU + Attribut + Sprache + Index | Update |
| Preis | SKU + Preisart + Währung + Gültigkeit | Update |

---

## Laravel Classes

```php
App\Services\Import\ImportService          // Orchestration
App\Services\Import\SheetParser            // Excel → structured data
App\Services\Import\SheetValidator         // Validation + errors
App\Services\Import\ReferenceResolver      // Technical names → UUIDs
App\Services\Import\FuzzyMatcher           // Typo detection
App\Services\Import\ImportExecutor         // Write data (queue job)
App\Services\Import\TemplateGenerator      // Generate empty template
```
