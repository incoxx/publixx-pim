---
title: Import - Overview
---

# Import

The import module of the Publixx PIM enables bulk ingestion of product data via standardized Excel files. It is designed for product managers and data stewards who want to efficiently load large volumes of data into the system -- without requiring technical API knowledge.

## Concept

The import follows the principle of **"validation before execution"**: data is never written directly to the database without being fully validated first. This ensures that faulty records do not cause inconsistent states.

## Three-Phase Import Process

The import goes through three clearly separated phases:

<svg viewBox="0 0 900 320" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto; margin: 2rem 0;">
  <defs>
    <marker id="arrow-import" viewBox="0 0 10 7" refX="10" refY="3.5" markerWidth="10" markerHeight="7" orient="auto-start-reverse">
      <path d="M 0 0 L 10 3.5 L 0 7 z" fill="#6366f1"/>
    </marker>
    <filter id="shadow-import" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.1"/>
    </filter>
  </defs>

  <!-- Phase 1: Upload -->
  <rect x="20" y="40" width="240" height="240" rx="16" fill="#f0f0ff" stroke="#6366f1" stroke-width="2" filter="url(#shadow-import)"/>
  <rect x="20" y="40" width="240" height="50" rx="16" fill="#6366f1"/>
  <rect x="20" y="74" width="240" height="16" fill="#6366f1"/>
  <text x="140" y="72" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="system-ui, sans-serif">Phase 1: Upload</text>

  <text x="140" y="120" text-anchor="middle" fill="#1e1b4b" font-size="14" font-family="system-ui, sans-serif">Upload Excel file</text>
  <text x="140" y="145" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">14-tab template with</text>
  <text x="140" y="163" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">master and product data</text>

  <rect x="60" y="185" width="160" height="36" rx="8" fill="white" stroke="#6366f1" stroke-width="1"/>
  <text x="140" y="208" text-anchor="middle" fill="#6366f1" font-size="12" font-family="system-ui, sans-serif">File is saved</text>

  <rect x="60" y="230" width="160" height="36" rx="8" fill="white" stroke="#6366f1" stroke-width="1"/>
  <text x="140" y="253" text-anchor="middle" fill="#6366f1" font-size="12" font-family="system-ui, sans-serif">Tabs are detected</text>

  <!-- Arrow 1→2 -->
  <line x1="270" y1="160" x2="320" y2="160" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-import)"/>

  <!-- Phase 2: Validation -->
  <rect x="330" y="40" width="240" height="240" rx="16" fill="#fefce8" stroke="#eab308" stroke-width="2" filter="url(#shadow-import)"/>
  <rect x="330" y="40" width="240" height="50" rx="16" fill="#eab308"/>
  <rect x="330" y="74" width="240" height="16" fill="#eab308"/>
  <text x="450" y="72" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="system-ui, sans-serif">Phase 2: Validation</text>

  <text x="450" y="120" text-anchor="middle" fill="#713f12" font-size="14" font-family="system-ui, sans-serif">Data verification</text>
  <text x="450" y="145" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">Schema, references,</text>
  <text x="450" y="163" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">dependencies, duplicates</text>

  <rect x="370" y="185" width="160" height="36" rx="8" fill="white" stroke="#eab308" stroke-width="1"/>
  <text x="450" y="208" text-anchor="middle" fill="#a16207" font-size="12" font-family="system-ui, sans-serif">Errors + suggestions</text>

  <rect x="370" y="230" width="160" height="36" rx="8" fill="white" stroke="#eab308" stroke-width="1"/>
  <text x="450" y="253" text-anchor="middle" fill="#a16207" font-size="12" font-family="system-ui, sans-serif">Preview (diff view)</text>

  <!-- Arrow 2→3 -->
  <line x1="580" y1="160" x2="630" y2="160" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-import)"/>

  <!-- Phase 3: Execution -->
  <rect x="640" y="40" width="240" height="240" rx="16" fill="#f0fdf4" stroke="#22c55e" stroke-width="2" filter="url(#shadow-import)"/>
  <rect x="640" y="40" width="240" height="50" rx="16" fill="#22c55e"/>
  <rect x="640" y="74" width="240" height="16" fill="#22c55e"/>
  <text x="760" y="72" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="system-ui, sans-serif">Phase 3: Execution</text>

  <text x="760" y="120" text-anchor="middle" fill="#14532d" font-size="14" font-family="system-ui, sans-serif">Write data</text>
  <text x="760" y="145" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">Upsert logic: create</text>
  <text x="760" y="163" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">or update</text>

  <rect x="680" y="185" width="160" height="36" rx="8" fill="white" stroke="#22c55e" stroke-width="1"/>
  <text x="760" y="208" text-anchor="middle" fill="#15803d" font-size="12" font-family="system-ui, sans-serif">Transaction-safe</text>

  <rect x="680" y="230" width="160" height="36" rx="8" fill="white" stroke="#22c55e" stroke-width="1"/>
  <text x="760" y="253" text-anchor="middle" fill="#15803d" font-size="12" font-family="system-ui, sans-serif">Result report</text>
</svg>

### Phase 1: Upload

The user uploads an Excel file via the import interface. The system automatically detects the contained worksheets (tabs) and maps them to the internal data types. The file is stored server-side and is ready for validation.

### Phase 2: Validation

During the validation phase, the system checks each record for:

- **Schema conformity** -- Required fields, data types, valid enum values
- **Reference resolution** -- Technical names are resolved to UUIDs
- **Dependencies** -- Referenced entities must exist (in the file or in the system)
- **Duplicate detection** -- Already existing records are marked for upsert
- **Fuzzy matching** -- Typos in references are detected and correction suggestions are displayed

The result is a detailed validation report with errors, warnings, and a preview of the planned changes (diff view: create, update, skip).

### Phase 3: Execution

Only after successful validation and explicit confirmation by the user are the data written to the database. The execution is **transaction-safe** -- either all records of a tab are successfully processed or the entire tab is rolled back. After completion, the user receives a result report.

## Excel Template: 14 Tabs

The import file consists of 14 worksheets that are processed in a defined order and dependency structure:

| No. | Tab | Description |
|---|---|---|
| 01 | `Languages` | Available content languages |
| 02 | `Attribute Groups` | Logical grouping of attributes |
| 03 | `Unit Groups` | Groups of physical units |
| 04 | `Units` | Individual units of measurement with conversion factors |
| 05 | `Attributes` | Attribute definitions (19 columns) |
| 06 | `Hierarchies` | Master and output hierarchies with up to 6 levels |
| 07 | `Value Lists` | Predefined selection values for attributes |
| 08 | `Products` | Product master data (SKU, name, type, status) |
| 09 | `Product Values` | Attribute values per product, language, and repetition |
| 10 | `Variants` | Product variants with assignment to parent product |
| 11 | `Variant Values` | Attribute values of variants |
| 12 | `Media` | Media assignments to products |
| 13 | `Prices` | Price information with currency and validity |
| 14 | `Relations` | Relationships between products |

::: tip Note
Not all tabs need to be filled. You can selectively use only the tabs that are relevant for your import. Dependencies are resolved both within the file and against the existing data inventory.
:::

## Upsert Logic

The import operates on the **upsert principle** (Update or Insert):

- **New record**: Is created if no record with the same identifier (e.g., technical name, SKU) exists.
- **Existing record**: Is updated if a record with the same identifier already exists. Only the fields contained in the import file are overwritten.

This behavior enables both initial data migrations and incremental updates using the same import file.

## Smart Matching

The system uses intelligent matching to automatically detect typos in references:

- **Levenshtein distance** with a threshold of 85%
- **Case-insensitive** comparison
- **Whitespace trimming** before comparison

If a similar but not exactly matching value is found, the system displays a correction suggestion in the validation preview.

## Further Documentation

- [Excel Format](/en/import/excel-format) -- Detailed column documentation for all 14 tabs
- [Validation](/en/import/validation) -- Validation rules, error messages, and preview view
