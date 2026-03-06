---
title: Inheritance System
---

# Inheritance System

The inheritance system is one of the central architectural elements of anyPIM. It reduces redundancy, ensures consistency, and enables efficient maintenance of large product assortments with shared properties. The system operates on two independent levels that complement each other.

## Overview of Inheritance Types

| Characteristic | Hierarchy Inheritance | Variant Inheritance |
|---|---|---|
| **Direction** | Hierarchy node to product | Parent product to variant |
| **Subject** | Attribute assignments (which attributes a product has) | Attribute values (which values a variant carries) |
| **Control** | Automatic via node position in the tree | Configurable per attribute (inherit/override) |
| **Propagation** | Change on a node affects all child nodes and their products | Change on the parent product affects all inheriting variants |

---

## Hierarchy Inheritance

Hierarchy inheritance determines **which attributes** a product possesses. It operates at the level of attribute assignments, not at the level of values.

### Mechanism

Hierarchy nodes are organized in a tree structure. Attributes can be assigned to each node. A product assigned to a node automatically receives all attributes of that node as well as all attributes of the parent nodes up to the root.

<svg viewBox="0 0 820 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="inhShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2" flood-opacity="0.1"/>
    </filter>
    <marker id="inhArrow" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#64748b"/>
    </marker>
    <marker id="inhArrowGreen" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#16a34a"/>
    </marker>
  </defs>

  <!-- Title -->
  <text x="410" y="28" text-anchor="middle" fill="#1e293b" font-size="15" font-weight="bold">Hierarchy Inheritance: Attribute Flow from Nodes to Products</text>

  <!-- Root Node -->
  <rect x="300" y="50" width="220" height="55" rx="10" fill="#1e40af" filter="url(#inhShadow)"/>
  <text x="410" y="73" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Root: All Products</text>
  <text x="410" y="92" text-anchor="middle" fill="#bfdbfe" font-size="11">Attributes: Name, SKU, Status</text>

  <!-- Level 1 Nodes -->
  <rect x="80" y="160" width="220" height="55" rx="10" fill="#2563eb" filter="url(#inhShadow)"/>
  <text x="190" y="183" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Electronics</text>
  <text x="190" y="202" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Voltage, Power</text>

  <rect x="520" y="160" width="220" height="55" rx="10" fill="#2563eb" filter="url(#inhShadow)"/>
  <text x="630" y="183" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Clothing</text>
  <text x="630" y="202" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Size, Material, Color</text>

  <!-- Level 2 Nodes -->
  <rect x="30" y="275" width="200" height="55" rx="10" fill="#3b82f6" filter="url(#inhShadow)"/>
  <text x="130" y="298" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Cables</text>
  <text x="130" y="317" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Length, Connector Type</text>

  <rect x="250" y="275" width="200" height="55" rx="10" fill="#3b82f6" filter="url(#inhShadow)"/>
  <text x="350" y="298" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Displays</text>
  <text x="350" y="317" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Resolution, Diagonal</text>

  <!-- Products -->
  <rect x="20" y="400" width="180" height="100" rx="8" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#inhShadow)"/>
  <text x="110" y="422" text-anchor="middle" fill="#166534" font-size="12" font-weight="bold">USB-C Cable 2m</text>
  <text x="30" y="442" fill="#166534" font-size="10">Inherits from Root:</text>
  <text x="35" y="455" fill="#15803d" font-size="10" font-style="italic">Name, SKU, Status</text>
  <text x="30" y="470" fill="#166534" font-size="10">Inherits from Electronics:</text>
  <text x="35" y="483" fill="#15803d" font-size="10" font-style="italic">Voltage, Power</text>
  <text x="30" y="496" fill="#166534" font-size="10">Inherits from Cables:</text>
  <text x="35" y="509" fill="#15803d" font-size="10" font-style="italic">Length, Connector Type</text>

  <rect x="620" y="400" width="180" height="80" rx="8" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#inhShadow)"/>
  <text x="710" y="422" text-anchor="middle" fill="#166534" font-size="12" font-weight="bold">T-Shirt Classic</text>
  <text x="630" y="442" fill="#166534" font-size="10">Inherits from Root:</text>
  <text x="635" y="455" fill="#15803d" font-size="10" font-style="italic">Name, SKU, Status</text>
  <text x="630" y="470" fill="#166534" font-size="10">Inherits from Clothing:</text>
  <text x="635" y="483" fill="#15803d" font-size="10" font-style="italic">Size, Material, Color</text>

  <!-- Connecting Lines (Node tree) -->
  <line x1="360" y1="105" x2="240" y2="158" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>
  <line x1="460" y1="105" x2="580" y2="158" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>
  <line x1="160" y1="215" x2="140" y2="273" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>
  <line x1="220" y1="215" x2="330" y2="273" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>

  <!-- Connecting Lines (Node to Product - green dashed) -->
  <line x1="110" y1="330" x2="110" y2="398" stroke="#16a34a" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#inhArrowGreen)"/>
  <line x1="660" y1="215" x2="700" y2="398" stroke="#16a34a" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#inhArrowGreen)"/>

  <!-- Legend -->
  <rect x="560" y="285" width="220" height="60" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
  <line x1="575" y1="308" x2="615" y2="308" stroke="#64748b" stroke-width="2"/>
  <text x="625" y="312" fill="#475569" font-size="10">Node Hierarchy</text>
  <line x1="575" y1="330" x2="615" y2="330" stroke="#16a34a" stroke-width="2" stroke-dasharray="6,3"/>
  <text x="625" y="334" fill="#475569" font-size="10">Attribute Inheritance</text>
</svg>

### The `dont_inherit` Flag

In some cases, an attribute should only apply to products of a specific node, but not to products in child nodes. The `dont_inherit` flag in the `hierarchy_node_attribute` table exists for this purpose.

| Scenario | `dont_inherit` | Behavior |
|---|---|---|
| Attribute should be passed down to child nodes | `false` (default) | All child nodes and their products inherit the attribute |
| Attribute applies only to this node | `true` | Only products of this node receive the attribute, child nodes do not |

**Usage example:** The node "Clearance Items" has an attribute "Clearance Price" with `dont_inherit = true`. Only products directly assigned to "Clearance Items" receive this attribute. Products in child nodes of "Clearance Items" (e.g., "Clearance Items > Electronics") do not receive it.

---

## Variant Inheritance

Variant inheritance determines **which values** a product variant carries. It operates at the level of concrete attribute values.

### Mechanism

A parent product can have any number of variants. For each combination of variant and attribute, an **inheritance rule** is defined:

- **`inherit`**: The variant adopts the value from the parent product. The value is read-only in the variant and is automatically updated when changed on the parent product.
- **`override`**: The variant has an independent value that is maintained independently of the parent product.

<svg viewBox="0 0 820 480" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="varShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2" flood-opacity="0.1"/>
    </filter>
    <marker id="varArrowBlue" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#2563eb"/>
    </marker>
    <marker id="varArrowOrange" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#ea580c"/>
    </marker>
  </defs>

  <!-- Title -->
  <text x="410" y="28" text-anchor="middle" fill="#1e293b" font-size="15" font-weight="bold">Variant Inheritance: Parent Product to Variants</text>

  <!-- Parent Product -->
  <rect x="250" y="50" width="320" height="170" rx="10" fill="white" stroke="#2563eb" stroke-width="2" filter="url(#varShadow)"/>
  <rect x="250" y="50" width="320" height="38" rx="10" fill="#2563eb"/>
  <rect x="250" y="80" width="320" height="8" fill="#2563eb"/>
  <text x="410" y="75" text-anchor="middle" fill="white" font-size="14" font-weight="bold">Parent Product: T-Shirt Classic</text>

  <text x="270" y="110" fill="#1e293b" font-size="12" font-family="monospace">Brand      = "FashionBrand"</text>
  <text x="270" y="130" fill="#1e293b" font-size="12" font-family="monospace">Material   = "100% Cotton"</text>
  <text x="270" y="150" fill="#1e293b" font-size="12" font-family="monospace">Care Instr = "Wash at 30 degrees"</text>
  <text x="270" y="170" fill="#1e293b" font-size="12" font-family="monospace">Color      = "White"</text>
  <text x="270" y="190" fill="#1e293b" font-size="12" font-family="monospace">Size       = "M"</text>
  <text x="270" y="210" fill="#1e293b" font-size="12" font-family="monospace">Price      = 29.90</text>

  <!-- Variant 1 -->
  <rect x="30" y="300" width="320" height="170" rx="10" fill="white" stroke="#16a34a" stroke-width="2" filter="url(#varShadow)"/>
  <rect x="30" y="300" width="320" height="38" rx="10" fill="#16a34a"/>
  <rect x="30" y="330" width="320" height="8" fill="#16a34a"/>
  <text x="190" y="325" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Variant: T-Shirt Red/L</text>

  <text x="50" y="358" fill="#2563eb" font-size="12" font-family="monospace">Brand      = "FashionBrand"</text>
  <rect x="280" y="346" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="359" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="50" y="378" fill="#2563eb" font-size="12" font-family="monospace">Material   = "100% Cotton"</text>
  <rect x="280" y="366" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="379" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="50" y="398" fill="#2563eb" font-size="12" font-family="monospace">Care Instr = "Wash at 30 degrees"</text>
  <rect x="280" y="386" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="399" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="50" y="418" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Color      = "Red"</text>
  <rect x="272" y="406" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="307" y="419" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="50" y="438" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Size       = "L"</text>
  <rect x="272" y="426" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="307" y="439" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="50" y="458" fill="#2563eb" font-size="12" font-family="monospace">Price      = 29.90</text>
  <rect x="280" y="446" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="459" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <!-- Variant 2 -->
  <rect x="470" y="300" width="320" height="170" rx="10" fill="white" stroke="#16a34a" stroke-width="2" filter="url(#varShadow)"/>
  <rect x="470" y="300" width="320" height="38" rx="10" fill="#16a34a"/>
  <rect x="470" y="330" width="320" height="8" fill="#16a34a"/>
  <text x="630" y="325" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Variant: T-Shirt Blue/S</text>

  <text x="490" y="358" fill="#2563eb" font-size="12" font-family="monospace">Brand      = "FashionBrand"</text>
  <rect x="720" y="346" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="749" y="359" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="490" y="378" fill="#2563eb" font-size="12" font-family="monospace">Material   = "100% Cotton"</text>
  <rect x="720" y="366" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="749" y="379" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="490" y="398" fill="#2563eb" font-size="12" font-family="monospace">Care Instr = "Wash at 30 degrees"</text>
  <rect x="720" y="386" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="749" y="399" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="490" y="418" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Color      = "Blue"</text>
  <rect x="712" y="406" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="747" y="419" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="490" y="438" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Size       = "S"</text>
  <rect x="712" y="426" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="747" y="439" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="490" y="458" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Price      = 24.90</text>
  <rect x="712" y="446" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="747" y="459" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <!-- Arrows from Parent to Variants -->
  <line x1="340" y1="220" x2="200" y2="298" stroke="#2563eb" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#varArrowBlue)"/>
  <line x1="480" y1="220" x2="620" y2="298" stroke="#2563eb" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#varArrowBlue)"/>

  <!-- Legend -->
  <rect x="30" y="48" width="200" height="55" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
  <rect x="42" y="60" width="40" height="14" rx="3" fill="#dbeafe"/>
  <text x="62" y="71" text-anchor="middle" fill="#1e40af" font-size="8" font-weight="bold">INHERIT</text>
  <text x="92" y="71" fill="#475569" font-size="10">Value from parent</text>
  <rect x="42" y="80" width="46" height="14" rx="3" fill="#fed7aa"/>
  <text x="65" y="91" text-anchor="middle" fill="#9a3412" font-size="8" font-weight="bold">OVERRIDE</text>
  <text x="98" y="91" fill="#475569" font-size="10">Own value</text>
</svg>

### Resolution Order

When an attribute value is requested for a product (or variant), the `InheritanceService` traverses the following cascade:

```
1. Does the product/variant have its own value?
   └─ YES ──> Return value (origin: "own")
   └─ NO  ──> Continue to step 2

2. Is the product a variant AND inheritance rule = "inherit"?
   └─ YES ──> Return parent product's value (origin: "parent")
   └─ NO  ──> Continue to step 3

3. Does a default value from the hierarchy exist?
   └─ YES ──> Return default value (origin: "hierarchy")
   └─ NO  ──> Return null (origin: "none")
```

The **origin information** is returned together with the value and used in the frontend to visually mark inherited fields.

---

## Collection Sorting

Collections (repeatable attribute groups) use a two-level sorting model:

### `collection_sort` -- Order of Entries

Determines the order of individual entries within a collection instance. Values are assigned in **increments of ten** (10, 20, 30, ...) to allow subsequent insertion without renumbering.

| Entry | `collection_sort` |
|---|---|
| Certification ISO 9001 | 10 |
| Certification CE | 20 |
| Certification TUeV | 30 |

### `attribute_sort` -- Order of Attributes

Determines the order of attributes within a collection entry. Also in increments of ten.

| Attribute | `attribute_sort` |
|---|---|
| Standard | 10 |
| Number | 20 |
| Valid Until | 30 |
| Document | 40 |

The increments of ten make it possible to insert a new attribute with `attribute_sort = 15` between "Standard" and "Number" without having to adjust existing sort orders.

---

## Cache Invalidation on Inheritance Changes

Inheritance changes can have far-reaching effects on the cache. The system handles various scenarios:

### Scenario 1: Value Change on Parent Product

```
Parent product "T-Shirt Classic" changes Material from "Cotton" to "Organic Cotton"
  |
  ├─> Invalidate cache for "T-Shirt Classic"
  ├─> Identify all variants with inherit rule for "Material"
  │     ├─> Variant "Red/L" inherits Material ──> Invalidate cache
  │     ├─> Variant "Blue/S" inherits Material ──> Invalidate cache
  │     └─> Variant "Black/XL" has override ──> No action needed
  ├─> Update search index for affected products (async)
  └─> Trigger ProductValueChanged event
```

### Scenario 2: Moving a Hierarchy Node

```
Node "Displays" is moved from "Electronics" to "Office Equipment"
  |
  ├─> Identify previous attributes (Electronics: Voltage, Power)
  ├─> Identify new attributes (Office Equipment: Weight, Dimensions)
  ├─> For all products in node "Displays":
  │     ├─> Remove Voltage, Power (if no own values)
  │     ├─> Add Weight, Dimensions
  │     └─> Update cache and search index
  └─> Trigger HierarchyNodeMoved event
```

### Scenario 3: Changing an Inheritance Rule

```
Variant "Red/L" changes rule for "Price" from "inherit" to "override"
  |
  ├─> Variant's own price becomes editable
  ├─> Invalidate cache for variant "Red/L"
  ├─> Update search index for "Red/L" (async)
  └─> Trigger VariantInheritanceChanged event
```

---

## UI Representation

Inheritance is transparently visualized in the frontend so that users can always understand where a value originates from.

### Inherited Fields

Fields with inherited values are displayed as **read-only** and marked with a badge indicating the origin:

| Badge | Meaning | Display |
|---|---|---|
| `Inherited from parent product` | Value comes from variant inheritance | Field grayed out, blue badge |
| `Hierarchy default` | Value comes from the hierarchy assignment | Field grayed out, green badge |
| `Own value` | Value was set directly on the product | Field editable, no badge |

### Toggling the Inheritance Rule

For variants, the user can toggle between `inherit` and `override` per attribute:

- **Switch to override**: The field becomes editable. The previous inherited value is entered as the initial value but can be freely changed.
- **Switch to inherit**: The field becomes read-only. Any existing own value is discarded and replaced by the parent value. The system displays a warning message before an own value is overwritten by the toggle.

### Real-Time Change Propagation

When a user changes a value on the parent product and simultaneously has a variant open, the change is visually highlighted in the variant view so that the user can follow the propagation.
