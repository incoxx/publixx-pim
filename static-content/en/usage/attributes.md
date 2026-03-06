---
title: Attributes
---

# Attributes

Attributes form the data model of the anyPIM. Thanks to the EAV architecture (Entity-Attribute-Value), you can define any number of attributes without having to modify the database schema. This chapter describes the management of attributes, their data types, properties, and the associated configuration areas for attribute groups, value lists, units, and attribute views.

## Attribute Management Overview

You can access the attribute management via the **Attributes** menu item in the sidebar. There you will see a tabular list of all defined attributes with the following columns:

| Column | Description |
|---|---|
| **Technical Name** | Unique identifier in the system (snake_case) |
| **Display Name** | Human-readable label (DE/EN) |
| **Data Type** | Type of stored value |
| **Translatable** | Whether the value is maintained per language |
| **Required** | Whether the attribute must be filled in |

Use the **+ New Attribute** button to open the form panel (AttributeFormPanel) for creating a new attribute.

## Data Types

The anyPIM supports eight data types that determine the input field and validation:

<svg viewBox="0 0 800 440" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .dt-bg { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 2; rx: 12; }
      .dt-card { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1.5; rx: 8; }
      .dt-card:hover { stroke: #6366f1; }
      .dt-icon-bg { rx: 6; }
      .dt-title { font-family: system-ui, sans-serif; font-size: 12px; font-weight: 700; fill: #1e293b; }
      .dt-desc { font-family: system-ui, sans-serif; font-size: 10px; fill: #64748b; }
      .dt-icon { font-family: system-ui, sans-serif; font-size: 18px; fill: #ffffff; font-weight: 700; }
      .dt-heading { font-family: system-ui, sans-serif; font-size: 16px; font-weight: 700; fill: #1e293b; }
    </style>
  </defs>
  <rect class="dt-bg" x="0" y="0" width="800" height="440" />
  <text class="dt-heading" x="24" y="32">Attribute Data Types</text>

  <!-- Row 1 -->
  <!-- String -->
  <rect class="dt-card" x="16" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="32" y="68" width="36" height="36" fill="#6366f1" rx="8" />
  <text class="dt-icon" x="41" y="93">Aa</text>
  <text class="dt-title" x="32" y="124">String</text>
  <text class="dt-desc" x="32" y="140">Single-line or multi-line</text>
  <text class="dt-desc" x="32" y="154">text. For names, descrip-</text>
  <text class="dt-desc" x="32" y="168">tions and free text fields.</text>

  <!-- Number -->
  <rect class="dt-card" x="210" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="226" y="68" width="36" height="36" fill="#0ea5e9" rx="8" />
  <text class="dt-icon" x="233" y="93">123</text>
  <text class="dt-title" x="226" y="124">Number</text>
  <text class="dt-desc" x="226" y="140">Integers without decimal</text>
  <text class="dt-desc" x="226" y="154">places. For quantities,</text>
  <text class="dt-desc" x="226" y="168">counts and integer values.</text>

  <!-- Float -->
  <rect class="dt-card" x="404" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="420" y="68" width="36" height="36" fill="#14b8a6" rx="8" />
  <text class="dt-icon" x="427" y="93">1.5</text>
  <text class="dt-title" x="420" y="124">Float</text>
  <text class="dt-desc" x="420" y="140">Floating-point numbers with</text>
  <text class="dt-desc" x="420" y="154">optional unit. For weight,</text>
  <text class="dt-desc" x="420" y="168">dimensions and prices.</text>

  <!-- Date -->
  <rect class="dt-card" x="598" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="614" y="68" width="36" height="36" fill="#f59e0b" rx="8" />
  <text class="dt-icon" x="619" y="92">📅</text>
  <text class="dt-title" x="614" y="124">Date</text>
  <text class="dt-desc" x="614" y="140">Date in YYYY-MM-DD format.</text>
  <text class="dt-desc" x="614" y="154">For release dates,</text>
  <text class="dt-desc" x="614" y="168">validity periods and deadlines.</text>

  <!-- Row 2 -->
  <!-- Flag -->
  <rect class="dt-card" x="16" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="32" y="214" width="36" height="36" fill="#22c55e" rx="8" />
  <text class="dt-icon" x="41" y="239">✓</text>
  <text class="dt-title" x="32" y="270">Flag</text>
  <text class="dt-desc" x="32" y="286">Yes/No value (Boolean).</text>
  <text class="dt-desc" x="32" y="300">For toggles like "Available",</text>
  <text class="dt-desc" x="32" y="314">"Featured" etc.</text>

  <!-- Selection -->
  <rect class="dt-card" x="210" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="226" y="214" width="36" height="36" fill="#a855f7" rx="8" />
  <text class="dt-icon" x="233" y="238">▼</text>
  <text class="dt-title" x="226" y="270">Selection</text>
  <text class="dt-desc" x="226" y="286">Selection from a value list.</text>
  <text class="dt-desc" x="226" y="300">For predefined options</text>
  <text class="dt-desc" x="226" y="314">like color, material etc.</text>

  <!-- Dictionary -->
  <rect class="dt-card" x="404" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="420" y="214" width="36" height="36" fill="#ec4899" rx="8" />
  <text class="dt-icon" x="426" y="238">{..}</text>
  <text class="dt-title" x="420" y="270">Dictionary</text>
  <text class="dt-desc" x="420" y="286">Key-value pairs as</text>
  <text class="dt-desc" x="420" y="300">JSON structure. For flexible</text>
  <text class="dt-desc" x="420" y="314">additional data and mappings.</text>

  <!-- Collection -->
  <rect class="dt-card" x="598" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="614" y="214" width="36" height="36" fill="#f43f5e" rx="8" />
  <text class="dt-icon" x="621" y="238">[..]</text>
  <text class="dt-title" x="614" y="270">Collection</text>
  <text class="dt-desc" x="614" y="286">Structured collections</text>
  <text class="dt-desc" x="614" y="300">as JSON array. For repeat-</text>
  <text class="dt-desc" x="614" y="314">able data blocks.</text>

  <!-- Legend -->
  <rect fill="#f1f5f9" x="16" y="350" width="764" height="76" rx="8" />
  <text class="dt-title" x="32" y="374">Input Note</text>
  <text class="dt-desc" x="32" y="392">String, Number, Float and Date are rendered as native HTML input fields. Flag as a checkbox.</text>
  <text class="dt-desc" x="32" y="408">Selection shows a dropdown with values from the linked value list. Dictionary and Collection use a JSON editor.</text>
</svg>

### Detailed Type Description

#### String
For single-line and multi-line texts. Ideal for product names, descriptions, short texts, and technical designations. String attributes can be marked as **translatable**, so that a separate text is maintained per language.

#### Number
Stores integers without decimal places. Suitable for quantity specifications, piece counts, inventory values, and integer metrics.

#### Float
Floating-point numbers with configurable precision. Can be linked with a **unit** from a unit group (e.g., weight in kg, length in mm). The unit is displayed next to the input field.

#### Date
Date values in ISO format (YYYY-MM-DD). The input field shows a calendar widget. Typical use cases: release date, validity start, expiration date.

#### Flag
Boolean value (Yes/No), displayed as a checkbox. For binary properties such as "Available", "Featured", "Hazardous Material", or "New Arrival".

#### Selection
Selection of a single value from a linked **value list**. The input field shows a dropdown menu. The available options are centrally maintained in the value list and can be translated.

#### Dictionary
Structured key-value pairs as a JSON object. The system provides a JSON editor. Application examples: Technical data sheets with variable fields, key-value mappings for external systems.

#### Collection
JSON arrays with repeatable structures. Enables storing lists of similar records within a single attribute. Application example: Multiple certification entries, each with name, number, and validity date.

## Attribute Properties

When creating or editing an attribute, the following properties can be configured:

| Property | Description |
|---|---|
| **Technical Name** | Unique system identifier in snake_case (e.g., `product_name`). Cannot be changed after creation. |
| **Display Name (DE/EN)** | Human-readable name in German and English |
| **Data Type** | One of the eight supported types (see above) |
| **Translatable** (`is_translatable`) | When enabled, the attribute value can be maintained separately per language |
| **Required** (`is_mandatory`) | When enabled, the attribute must be filled in before a product can be set to "Active" |
| **Unique** (`is_unique`) | The value must be system-wide unique (e.g., for EAN numbers) |
| **Searchable** (`is_searchable`) | The attribute is included in full-text search and PQL queries |
| **Inheritable** (`is_inheritable`) | Variants can inherit the value from the parent product |
| **Variant Attribute** (`is_variant_attribute`) | Marks the attribute as variant-specific (differentiates variants from each other) |
| **Repeatable** (`is_repeatable`) | Allows multiple values for the same attribute (Collection Groups) |
| **Value List** | Link to a value list (only for Selection type) |
| **Unit Group** | Link to a unit group (typical for Float type) |

::: tip Best Practice
Choose the technical name carefully -- it serves as the API key and cannot be changed afterwards. Use descriptive, English snake_case identifiers (e.g., `net_weight`, `short_description`).
:::

## Attribute Groups (AttributeTypes)

Attribute groups -- referred to as **AttributeTypes** in the system -- serve the logical organization of attributes. They group thematically related attributes and determine their order in the product detail view.

### Management

Via the **Attribute Groups** menu item in the sidebar, you can access the group overview. There you can:

- **Create a new group** -- Assign a technical name as well as display names in DE and EN.
- **Assign attributes** -- Assign existing attributes to the group and define the order.
- **Edit groups** -- Change the display name or the order of the contained attributes.
- **Delete groups** -- Removes the group. The contained attributes are preserved but lose their group assignment.

### Example Groups

| Group | Contained Attributes |
|---|---|
| Master Data | Product name, Description, Short description, EAN |
| Technical Data | Weight, Dimensions, Material, Protection class |
| Logistics | Packaging unit, Carton contents, Customs tariff number |
| Marketing | Advertising slogan, Bullet points, SEO title |

## Unit Groups and Units

Unit groups combine physical units of a category. They are assigned to attributes of type **Float**, so that the user can select the appropriate unit when filling in data.

### Creating a Unit Group

1. Navigate to **Attributes** > **Unit Groups** (or via the attribute management).
2. Click **+ New Unit Group**.
3. Assign a name (e.g., "Weight").
4. Add units.

### Example: Unit Group "Weight"

| Unit | Abbreviation | Factor to Base Unit |
|---|---|---|
| Kilogram | kg | 1 (Base) |
| Gram | g | 0.001 |
| Pound (lb) | lb | 0.453592 |
| Ton | t | 1000 |

### Example: Unit Group "Length"

| Unit | Abbreviation | Factor to Base Unit |
|---|---|---|
| Meter | m | 1 (Base) |
| Centimeter | cm | 0.01 |
| Millimeter | mm | 0.001 |
| Inch | in | 0.0254 |

Units enable automatic conversion during data maintenance and export.

## Value Lists

Value lists define the available options for attributes of type **Selection**. Via the **Value Lists** menu item in the sidebar, you can access the management.

### Creating a Value List

1. Click **+ New Value List**.
2. Assign a technical name and display name.
3. Add values -- each value has:
   - **Technical key** (e.g., `color_red`)
   - **Display name DE** (e.g., "Rot")
   - **Display name EN** (e.g., "Red")
   - **Position** (order in the selection list)

### Example: Value List "Colors"

| Key | Display (DE) | Display (EN) |
|---|---|---|
| `color_red` | Rot | Red |
| `color_blue` | Blau | Blue |
| `color_green` | Grün | Green |
| `color_black` | Schwarz | Black |
| `color_white` | Weiß | White |

::: warning Note
Deleting a value from a value list may cause products to contain an invalid value. Before deleting, check which products use the affected value.
:::

## Attribute Views

Attribute views define **subsets** of available attributes for specific usage contexts. They allow restricting the display and access to attributes based on their intended use.

### Typical Use Cases

| View | Description |
|---|---|
| **E-Shop** | Only attributes relevant for the online shop (name, description, images, price) |
| **Print** | Attributes for the print catalog (technical data, article number, dimensions) |
| **Logistics** | Logistics-relevant attributes (weight, packaging, customs tariff number) |
| **Minimal** | Only the most important core attributes for quick editing |

### Configuring a View

1. Navigate to the attribute views management.
2. Create a new view with a descriptive name.
3. Select the attributes that should be visible in this view.
4. Optionally define the order.

Attribute views can also be used as a **permission boundary** in user management. A user can be restricted to specific views, so they can only see and edit the attributes defined there. For more details, see the [Users](./users) section.

## Parent-Child Attributes (Hierarchical Attributes)

Attributes can be in a parent-child relationship with each other. A **parent attribute** serves as a logical container for its **child attributes**, which are only displayed when the parent attribute has a certain value.

### Example

- **Parent attribute:** `has_battery` (Flag)
- **Child attributes:** `battery_type` (Selection), `battery_capacity_mah` (Number)

When `has_battery` is set to "Yes", the child attributes `battery_type` and `battery_capacity_mah` are shown in the product detail view. Otherwise, they remain hidden.

This concept reduces the complexity of product maintenance by only displaying context-relevant attributes.

## Next Steps

- Learn how [Hierarchies](./hierarchies) assign attribute groups to product categories.
- Get to know the [Product management](./products), where attribute values are maintained.
- Configure [User permissions](./users) based on attribute views.
