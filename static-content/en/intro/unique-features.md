---
title: Unique Features
---

# Unique Features of Publixx PIM

Publixx PIM fundamentally differs from conventional PIM systems through a series of architectural and functional design decisions. This chapter presents the key differentiators and explains why they matter for productive use.

## Overview of Core Features

<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="shadow" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="1" dy="2" stdDeviation="3" flood-opacity="0.15"/>
    </filter>
    <linearGradient id="hubGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#1d4ed8;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#60a5fa;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#34d399;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#059669;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad3" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#f59e0b;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#d97706;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad4" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad5" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#fb7185;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#e11d48;stop-opacity:1"/>
    </linearGradient>
  </defs>

  <!-- Connecting Lines -->
  <line x1="400" y1="280" x2="160" y2="100" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="640" y2="100" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="100" y2="310" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="700" y2="310" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="160" y2="500" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="400" y2="530" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="640" y2="500" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>

  <!-- Hub -->
  <circle cx="400" cy="280" r="70" fill="url(#hubGrad)" filter="url(#shadow)"/>
  <text x="400" y="272" text-anchor="middle" fill="white" font-size="15" font-weight="bold">Publixx</text>
  <text x="400" y="294" text-anchor="middle" fill="white" font-size="15" font-weight="bold">PIM</text>

  <!-- Spoke 1: EAV -->
  <rect x="70" y="62" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad1)" filter="url(#shadow)"/>
  <text x="160" y="92" text-anchor="middle" fill="white" font-size="13" font-weight="bold">EAV Architecture</text>
  <text x="160" y="112" text-anchor="middle" fill="white" font-size="11">Flexible Attributes</text>

  <!-- Spoke 2: Inheritance -->
  <rect x="550" y="62" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad2)" filter="url(#shadow)"/>
  <text x="640" y="92" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Inheritance System</text>
  <text x="640" y="112" text-anchor="middle" fill="white" font-size="11">Hierarchy + Variants</text>

  <!-- Spoke 3: PQL -->
  <rect x="10" y="275" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad3)" filter="url(#shadow)"/>
  <text x="100" y="305" text-anchor="middle" fill="white" font-size="13" font-weight="bold">PQL Query Language</text>
  <text x="100" y="325" text-anchor="middle" fill="white" font-size="11">FUZZY, SOUNDS_LIKE</text>

  <!-- Spoke 4: Import/Export -->
  <rect x="610" y="275" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad4)" filter="url(#shadow)"/>
  <text x="700" y="305" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Import / Export</text>
  <text x="700" y="325" text-anchor="middle" fill="white" font-size="11">Excel + Publixx PXF</text>

  <!-- Spoke 5: RBAC -->
  <rect x="70" y="462" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad5)" filter="url(#shadow)"/>
  <text x="160" y="492" text-anchor="middle" fill="white" font-size="13" font-weight="bold">RBAC</text>
  <text x="160" y="512" text-anchor="middle" fill="white" font-size="11">Attribute &amp; Node Permissions</text>

  <!-- Spoke 6: i18n -->
  <rect x="310" y="495" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad1)" filter="url(#shadow)"/>
  <text x="400" y="525" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Multilingual</text>
  <text x="400" y="545" text-anchor="middle" fill="white" font-size="11">Unlimited Languages</text>

  <!-- Spoke 7: Hierarchies -->
  <rect x="550" y="462" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad2)" filter="url(#shadow)"/>
  <text x="640" y="492" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Hierarchies</text>
  <text x="640" y="512" text-anchor="middle" fill="white" font-size="11">Master + Output</text>
</svg>

---

## 1. EAV Architecture — Unlimited Flexible Attributes

Conventional PIM systems store product properties as fixed columns in a database table. Every new attribute requires a schema migration, deployment cycles, and potentially downtime. Publixx PIM takes a fundamentally different approach.

The **Entity-Attribute-Value model** (EAV) decouples the product structure from the database schema. Attributes are defined as independent entities and their values are stored in a separate mapping table (`product_attribute_values`). This means:

- **New attributes** are created through the UI or API — without migration, without deployment
- **Attribute types** can be freely defined: Text, Number, Date, Select, Multi-select, Media, References, and more
- **No column limit**: While relational tables hit boundaries at a few hundred columns, EAV scales to thousands of attributes per product
- **Multi-tenancy**: Different product categories can use completely different attribute sets without affecting each other

```
Product (Entity)     Attribute (Attribute)      Value (Value)
───────────────     ──────────────────────    ─────────────────
Article-4711   ──>  Color (select)        ──>  "Blue"
Article-4711   ──>  Weight (decimal)      ──>  2.45
Article-4711   ──>  Description (text)    ──>  "Ergonomic..."
```

The performance challenge typical of EAV systems (many JOINs during queries) is compensated by a **materialized search index** (`products_search_index`). This denormalizes relevant attribute values into a flat structure optimized for full-text search and filtering.

---

## 2. Two-Level Inheritance System

Publixx PIM implements inheritance on two levels that complement each other:

### Hierarchy Inheritance (Node to Product)

Products are organized in hierarchy nodes. Each node can assign attributes that its products receive. Since nodes are organized in a tree structure, child nodes inherit the attribute assignments of their ancestors. A product in a leaf node automatically receives the attributes of all parent nodes.

### Variant Inheritance (Parent Product to Variant)

Product variants inherit attribute values from their parent product. For each attribute, it is configurable whether a value should be inherited (`inherit`) or independently overridden (`override`). When an inherited value changes on the parent product, the change automatically propagates to all variants.

The **resolution order** is clearly defined:

1. The variant's own value (if override is active)
2. Inherited value from the parent product
3. Default value from the hierarchy
4. `null` (no value present)

This system drastically reduces redundancy: Common attributes like brand name, manufacturer, or material descriptions are maintained only once and automatically inherited by hundreds of variants.

---

## 3. PQL — Publixx Query Language

PQL is a standalone, SQL-like query language specifically designed for searching and filtering products across any attribute combination. It goes beyond the capabilities of typical filter interfaces:

| Operator | Description | Example |
|---|---|---|
| `=`, `!=`, `<`, `>` | Standard comparisons | `price > 100` |
| `LIKE` | Pattern search with wildcards | `name LIKE "%Screw%"` |
| `IN` | Value list | `color IN ("Red", "Blue")` |
| `FUZZY` | Fuzzy search (Levenshtein distance) | `name FUZZY "Screw"` |
| `SOUNDS_LIKE` | Phonetic search | `manufacturer SOUNDS_LIKE "Mayer"` |
| `SEARCH_FIELDS` | Full-text search across multiple fields | `SEARCH_FIELDS("Valve DN50")` |
| `AND`, `OR`, `NOT` | Logical operators | `color = "Red" AND price < 50` |

PQL queries are translated server-side into optimized SQL queries, leveraging the materialized search index. The language is available both through the REST API and the user interface.

```
-- Find all red screws under $5 with fuzzy name matching
name FUZZY "Screw" AND color = "Red" AND price < 5.00
```

---

## 4. Excel Import with 14-Tab Structure

The import process is designed for maximum user-friendliness while maintaining data integrity. A single Excel file with **14 specialized worksheets** maps the entire product data model:

**Three-phase import:**

1. **Upload and parsing** — The file is uploaded, each worksheet is read in a structured manner
2. **Validation and fuzzy matching** — Attribute names, hierarchy paths, and references are matched against existing data. Typos are detected via fuzzy matching and presented as correction suggestions
3. **Execution** — After confirmation by the user, data is imported transactionally

The **fuzzy matching** for column-to-attribute mapping is particularly valuable: Instead of aborting the entire import for a typo like "Wieght" instead of "Weight", the system recognizes the similarity and suggests the correct mapping.

---

## 5. Configurable Export with Publixx Integration

The export system works with **configurable mapping templates** (PXF format). Each template defines:

- Which attributes to export
- How attribute names should be mapped in the target format
- Which transformations to apply
- Which channel the export is intended for (e.g., Publixx catalog, webshop, marketplace)

The **Publixx catalog integration** enables direct export of product data to the Publixx catalog system. Changes to products can be transmitted automatically or manually.

---

## 6. Fine-Grained Permission System (RBAC)

The permission system goes far beyond typical roles like "Admin", "Editor", and "Viewer". Building on Spatie Permission, Publixx PIM implements **two additional granularity levels**:

### Attribute View Restrictions

User roles can be restricted to specific **attribute views**. An attribute view defines a subset of all available attributes. A user with a restricted view sees only their assigned attributes — all others are neither visible nor accessible via the API.

### Hierarchy Node Restrictions

Users can be restricted to specific **subtrees of the hierarchy**. A user with access only to the "Electronics" node exclusively sees products in that subtree and its child nodes.

Both restrictions work cumulatively: A user can, for example, only see and edit the marketing attributes of products in the Electronics category.

---

## 7. Integrated Multilingual Support

Multilingual support is not an add-on but an integral part of the EAV architecture. Every attribute value can be maintained in **any number of languages**. The language version is stored directly in the value table, so no additional translation tables are needed.

- Languages are defined system-wide and are immediately available for all attributes
- The API supports language-specific queries (`?locale=de`, `?locale=en`)
- In the frontend, users switch the editing language via dropdown
- PQL queries can filter by language

---

## 8. Hierarchical Product Classification

The hierarchy system distinguishes two types:

### Master Hierarchy

The primary product structure. Every product is assigned to exactly one node in the master hierarchy. The master hierarchy determines which attributes a product possesses (via hierarchy inheritance).

### Output Hierarchies

Additional hierarchies for channel-specific structures. A product can be referenced in multiple output hierarchies simultaneously, for example in a webshop category and a catalog structure.

This separation allows decoupling the internal product organization (by product groups, materials, manufacturers) from the external presentation (by customer needs, use cases, channels).

---

## 9. Collection Attributes (Repeatable Attribute Groups)

Collection attributes enable modeling **repeating structured data** within a product. A classic example:

- A product has a collection "Certifications" with attributes "Standard", "Number", "Valid Until", and "Document"
- Any number of entries can exist in this collection per product

Collections support:
- **Sorting** via `collection_sort` (order of entries) and `attribute_sort` (order of attributes within a collection) — in increments of 10
- **Inheritance**: Collection entries can also be inherited from parent products
- **Validation**: Each attribute within the collection is validated individually

---

## 10. Materialized Search Index

The `products_search_index` is a denormalized table that consolidates the most important product data in a flat, search-optimized structure. This index is automatically updated when product data changes, providing:

- **MySQL FULLTEXT indexes** for performant free-text search
- **Pre-aggregated attribute values** — no EAV JOINs needed for read operations
- **PQL support** — the PQL engine uses the index as its primary data source
- **Automatic invalidation** — changes to products, attributes, or inheritance values trigger a recalculation of the affected index entry

Through this strategy, Publixx PIM achieves the flexibility of an EAV system with query performance comparable to fixed schema designs.
