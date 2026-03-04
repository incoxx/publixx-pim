---
title: Validation
---

# Validation

Validation is the central safety layer of the import process. It ensures that only consistent and correct data is written to the database. This section describes the individual validation stages, fuzzy matching, the response format, and the preview function.

## Validation Stages

Validation goes through several successive stages. Each stage builds on the results of the previous one.

### 1. Schema Validation

Schema validation checks the structural correctness of each row:

| Check | Description | Example Error |
|---|---|---|
| **Required fields** | All columns marked as required must be filled | `Row 5: Field 'sku' is a required field` |
| **Data types** | Values must match the expected data type | `Row 8: 'abc' is not a valid value for type 'number'` |
| **Enum values** | For enumeration fields, the values must be valid | `Row 12: 'aktiv' is not a valid status (expected: draft, active, inactive)` |
| **Formats** | Dates, EAN numbers, etc. must conform to the format | `Row 3: 'abc123' is not a valid EAN` |
| **Lengths** | Strings must not exceed the maximum length | `Row 7: 'technical_name' must not exceed 255 characters` |

### 2. Reference Resolution

In this stage, all textual references (technical names) are resolved to the corresponding UUIDs:

- **Attribute groups** -- Technical name is resolved to the attribute group UUID
- **Unit groups and units** -- Assignment of the unit to the correct group
- **Value lists** -- Verification that the referenced value list exists
- **Hierarchy nodes** -- Resolution of the path to the node UUID
- **Products (SKU)** -- Assignment of the SKU to the product UUID

The resolution considers both data already present in the system and data defined in the current import file. This allows new entities to be both defined and referenced in the same file.

### 3. Dependency Check

The dependency check ensures that all referenced entities actually exist:

| Check | Example Error |
|---|---|
| Referenced attribute group does not exist | `Row 3, Tab 'Attributes': Attribute group 'technische_datten' not found` |
| Referenced unit group does not exist | `Row 5, Tab 'Attributes': Unit group 'laenge' not found` |
| Referenced product does not exist | `Row 12, Tab 'Product Values': SKU 'BM-999' not found` |
| Referenced attribute does not exist | `Row 8, Tab 'Product Values': Attribute 'gewicht_neto' not found` |
| Unit does not match the unit group | `Row 4, Tab 'Product Values': Unit 'kg' does not belong to group 'laenge'` |

### 4. Duplicate Detection

Duplicate detection determines whether a record should be newly created or updated:

| Situation | Action | Label |
|---|---|---|
| Identifier does **not** exist in the system and **not** in the file | **Create** | `CREATE` |
| Identifier **already** exists in the system | **Update** | `UPDATE` |
| Identifier appears **multiple times** in the file | **Error** | `DUPLICATE_ERROR` |
| Identifier exists in the system, but no changes | **Skip** | `SKIP` |

## Fuzzy Matching

The fuzzy matching system automatically detects typos in reference fields and offers correction suggestions. This significantly reduces the error rate during import.

### Algorithm

The matching uses the following strategy:

1. **Exact comparison** -- If the value is found exactly, no correction is needed.
2. **Case-insensitive comparison** -- Capitalization is ignored (`Gewicht_Netto` finds `gewicht_netto`).
3. **Whitespace trimming** -- Leading and trailing spaces are removed.
4. **Levenshtein distance** -- If there is no exact match, the normalized Levenshtein similarity is calculated. If it is **85% or higher**, the best match is displayed as a suggestion.

### Example

| Input (incorrect) | Best Match | Similarity | Suggestion |
|---|---|---|---|
| `gewicht_neto` | `gewicht_netto` | 93% | Did you mean `gewicht_netto`? |
| `techniche_daten` | `technische_daten` | 94% | Did you mean `technische_daten`? |
| `Bohrmaschne` | `Bohrmaschine` | 92% | Did you mean `Bohrmaschine`? |
| `xyz_unbekannt` | -- | < 85% | No suggestion (error) |

::: info Note
Fuzzy matching suggestions are only displayed in the validation response. They are **not** automatically applied. The user must make the corrections in the Excel file themselves and re-upload the file.
:::

## Validation Response

The validation API returns a structured JSON response that summarizes all results per tab.

### Successful Validation

```json
{
  "status": "valid",
  "summary": {
    "total_rows": 245,
    "creates": 180,
    "updates": 60,
    "skips": 5,
    "errors": 0,
    "warnings": 3
  },
  "tabs": {
    "05_Attribute": {
      "rows": 42,
      "creates": 35,
      "updates": 7,
      "skips": 0,
      "errors": [],
      "warnings": []
    },
    "08_Produkte": {
      "rows": 120,
      "creates": 95,
      "updates": 25,
      "skips": 0,
      "errors": [],
      "warnings": []
    }
  }
}
```

### Validation with Errors

```json
{
  "status": "invalid",
  "summary": {
    "total_rows": 245,
    "creates": 0,
    "updates": 0,
    "skips": 0,
    "errors": 4,
    "warnings": 2
  },
  "tabs": {
    "09_Produktwerte": {
      "rows": 83,
      "errors": [
        {
          "row": 12,
          "column": "attribute",
          "value": "gewicht_neto",
          "code": "REFERENCE_NOT_FOUND",
          "message": "Attribut 'gewicht_neto' nicht gefunden.",
          "suggestion": {
            "match": "gewicht_netto",
            "similarity": 0.93,
            "message": "Meinten Sie 'gewicht_netto'?"
          }
        },
        {
          "row": 45,
          "column": "sku",
          "value": "UNBEKANNT-999",
          "code": "REFERENCE_NOT_FOUND",
          "message": "Produkt mit SKU 'UNBEKANNT-999' nicht gefunden.",
          "suggestion": null
        }
      ],
      "warnings": [
        {
          "row": 67,
          "column": "unit",
          "value": "",
          "code": "MISSING_OPTIONAL_UNIT",
          "message": "Attribut 'gewicht_netto' hat eine Einheitengruppe, aber keine Einheit wurde angegeben. Standardeinheit 'kg' wird verwendet."
        }
      ]
    }
  }
}
```

### Error Codes

| Code | Description |
|---|---|
| `REQUIRED_FIELD_MISSING` | Required field is not filled |
| `INVALID_DATA_TYPE` | Value does not match the expected data type |
| `INVALID_ENUM_VALUE` | Value is not a valid enum value |
| `INVALID_FORMAT` | Value does not conform to the expected format |
| `MAX_LENGTH_EXCEEDED` | String exceeds the maximum length |
| `REFERENCE_NOT_FOUND` | Referenced entity not found |
| `DEPENDENCY_MISSING` | Dependent entity does not exist |
| `DUPLICATE_IN_FILE` | Identifier appears multiple times in the file |
| `UNIT_GROUP_MISMATCH` | Unit does not match the unit group of the attribute |
| `VALUE_NOT_UNIQUE` | Value violates a uniqueness constraint |

## Preview (Diff View)

After successful validation, the system offers a preview of the planned changes. The diff view clearly shows for each tab which records will be created, updated, or skipped.

### Preview Categories

| Category | Symbol | Description |
|---|---|---|
| **Create** | `CREATE` | New records that do not yet exist in the system |
| **Update** | `UPDATE` | Existing records where at least one field changes |
| **Skip** | `SKIP` | Existing records without changes |

### Detail View for Updates

For updates, the preview shows the changed fields in comparison:

```json
{
  "action": "UPDATE",
  "identifier": "BM-2000-PRO",
  "changes": [
    {
      "field": "name_de",
      "old_value": "Bohrmaschine Pro 2000",
      "new_value": "Bohrmaschine Pro 2000 (Neuauflage)"
    },
    {
      "field": "status",
      "old_value": "draft",
      "new_value": "active"
    }
  ]
}
```

This preview allows the user to review the impact of the import before execution is started. Unexpected changes can be detected early and the Excel file can be corrected if needed.

## Workflow in the User Interface

1. **Upload file** -- Upload the Excel file via drag-and-drop or file selection.
2. **Start validation** -- Validation is started automatically after the upload.
3. **Review results** -- Errors and warnings are displayed in tabular form.
4. **Make corrections** (in case of errors) -- Correct the Excel file and re-upload.
5. **Review preview** (on successful validation) -- Diff view of the planned changes.
6. **Execute import** -- Confirmation by the user starts the transaction-safe execution.

## Further Documentation

- [Import Overview](/en/import/) -- Process overview and concept
- [Excel Format](/en/import/excel-format) -- Detailed column documentation for all 14 tabs
