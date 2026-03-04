---
title: Export - Overview
---

# Export

The export module of the Publixx PIM provides product data in a structured form for external systems. It supports both generic JSON exports and the specialized Publixx integration via PXF records (Publixx Exchange Format).

## Export Formats

The Publixx PIM offers two export channels:

| Channel | Format | Target Audience | Description |
|---|---|---|---|
| **JSON Export** | JSON | Developers, systems | Generic export with configurable filters and formats |
| **Publixx Export** | PXF-JSON | Publixx platform | Specialized export with mapping configuration for Publixx catalogs |

## Export Pipeline

The export process follows a clearly defined pipeline:

<svg viewBox="0 0 900 380" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto; margin: 2rem 0;">
  <defs>
    <marker id="arrow-export" viewBox="0 0 10 7" refX="10" refY="3.5" markerWidth="10" markerHeight="7" orient="auto-start-reverse">
      <path d="M 0 0 L 10 3.5 L 0 7 z" fill="#0891b2"/>
    </marker>
    <filter id="shadow-export" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.1"/>
    </filter>
  </defs>

  <!-- Step 1: Request -->
  <rect x="20" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="20" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="20" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="100" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Request</text>
  <text x="100" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">API endpoint or</text>
  <text x="100" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">PQL query with</text>
  <text x="100" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">filters and options</text>

  <!-- Arrow 1→2 -->
  <line x1="185" y1="95" x2="215" y2="95" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 2: Filtering -->
  <rect x="225" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="225" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="225" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="305" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Filtering</text>
  <text x="305" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Status, hierarchy,</text>
  <text x="305" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">attributes, delta</text>
  <text x="305" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">timestamp</text>

  <!-- Arrow 2→3 -->
  <line x1="390" y1="95" x2="420" y2="95" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 3: Enrichment -->
  <rect x="430" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="430" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="430" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="510" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Enrichment</text>
  <text x="510" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Attribute values, media,</text>
  <text x="510" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">prices, relations,</text>
  <text x="510" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">load variants</text>

  <!-- Arrow 3→4 -->
  <line x1="595" y1="95" x2="625" y2="95" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 4: Transformation -->
  <rect x="635" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="635" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="635" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="715" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Transformation</text>
  <text x="715" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Apply mapping,</text>
  <text x="715" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">choose format</text>
  <text x="715" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">(flat/nested/publixx)</text>

  <!-- Output branches -->
  <!-- Arrow down from Transform -->
  <line x1="715" y1="165" x2="715" y2="200" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 5: Output -->
  <rect x="430" y="210" width="160" height="110" rx="12" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="430" y="210" width="160" height="40" rx="12" fill="#16a34a"/>
  <rect x="430" y="238" width="160" height="12" fill="#16a34a"/>
  <text x="510" y="236" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">JSON Output</text>
  <text x="510" y="275" text-anchor="middle" fill="#14532d" font-size="11" font-family="system-ui, sans-serif">Generic export</text>
  <text x="510" y="295" text-anchor="middle" fill="#14532d" font-size="11" font-family="system-ui, sans-serif">for external systems</text>

  <rect x="635" y="210" width="160" height="110" rx="12" fill="#fefce8" stroke="#eab308" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="635" y="210" width="160" height="40" rx="12" fill="#eab308"/>
  <rect x="635" y="238" width="160" height="12" fill="#eab308"/>
  <text x="715" y="236" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Publixx PXF</text>
  <text x="715" y="275" text-anchor="middle" fill="#713f12" font-size="11" font-family="system-ui, sans-serif">Publixx records</text>
  <text x="715" y="295" text-anchor="middle" fill="#713f12" font-size="11" font-family="system-ui, sans-serif">for catalogs</text>

  <!-- Connecting lines to outputs -->
  <line x1="715" y1="200" x2="510" y2="210" stroke="#16a34a" stroke-width="2"/>
  <line x1="715" y1="200" x2="715" y2="210" stroke="#eab308" stroke-width="2"/>
</svg>

### 1. Request

The export is triggered via an API endpoint or a PQL query. The caller defines filters, include options, and the desired output format.

### 2. Filtering

The product set is narrowed down based on the specified criteria:

- **Status** -- Only products with a specific status (e.g., `active`)
- **Hierarchy** -- Products of a specific hierarchy node or path
- **Attributes** -- Filtering by attribute values
- **Attribute views** -- Restriction to specific views
- **Output hierarchy** -- Structuring by an output hierarchy
- **Delta timestamp** -- Only products changed since a specific point in time (`updated_after`)

### 3. Data Enrichment

The filtered products are enriched with the requested supplementary data:

- Attribute values (with inheritance resolution for variants)
- Media (images, documents, videos)
- Prices (by currency and validity)
- Relations (accessories, spare parts, cross-references)
- Variants (with their own attribute values)

### 4. Transformation

The enriched data is transformed into the desired format:

| Format | Description |
|---|---|
| `flat` | Flat structure with all attributes as key-value pairs |
| `nested` | Nested structure, grouped by attribute groups |
| `publixx` | Publixx-specific format with mapping transformation |

## Delta Export

The delta export enables efficient incremental synchronizations. Using the `updated_after` parameter, only products that have changed since the specified point in time are exported:

```
GET /api/v1/export/products?updated_after=2025-06-15T10:00:00Z
```

The timestamp considers changes to:

- Product master data (name, SKU, status)
- Attribute values
- Media assignments
- Prices
- Variants and their attribute values
- Relations

## Further Documentation

- [JSON Export](/en/export/json-export) -- Endpoints, filters, formats, and pagination
- [Publixx Export](/en/export/publixx-export) -- Mapping configuration and PXF integration
