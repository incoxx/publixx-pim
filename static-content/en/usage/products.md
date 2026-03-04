---
title: Products
---

# Products

Product management is the core of Publixx PIM. Here you create products, maintain attribute values in different languages, manage variants and relations, and control the entire product lifecycle.

## Product List

After clicking on **Products** in the sidebar, you will reach the product list. This displays all products in a tabular overview with the following columns:

| Column | Description |
|---|---|
| **SKU** | Unique article number (displayed in monospace) |
| **Name** | Product name in the current language |
| **Type** | Assigned product type |
| **Status** | Current status (Draft, Active, Inactive) |
| **Modified** | Timestamp of the last modification |

### Filtering and Searching

Above the table is the **filter bar** (FilterBar), which allows you to narrow down the product list:

- **Full-text search** -- Enter a search term to filter products by SKU, name, or other searchable attributes.
- **Status filter** -- Restrict the display to products with a specific status.
- **Product type filter** -- Show only products of a specific type.
- **Active filters** are displayed as chips above the table and can be removed individually or reset entirely.

### Sorting

Click on a sortable column header (SKU, Name, Status, Modified) to sort the list in ascending order. Clicking again reverses the sort direction.

### Pagination

The product list is organized into pages. At the bottom of the table you will find the page navigation, which allows you to switch between pages. The number of entries per page can be configured in the settings.

## Creating a Product

Click the **+ New Product** button above the product list. A side panel (ProductCreatePanel) opens with the following required fields:

| Field | Description | Required |
|---|---|---|
| **SKU** | Unique article number | Yes |
| **Name** | Product name | Yes |
| **Product Type** | Selection of the product type that determines the attribute schema | Yes |
| **EAN** | European Article Number | No |
| **Status** | Initial status (Default: Draft) | Yes |

After saving, the product is created and you are automatically redirected to the product detail view.

::: tip Note
The product type determines which attributes are available for the product. Choose the type carefully, as it defines the entire attribute schema.
:::

## Product Detail View

The detail view of a product is divided into a **header area** and **tabs**:

<svg viewBox="0 0 800 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .pd-frame { fill: #ffffff; stroke: #e2e8f0; stroke-width: 2; rx: 8; }
      .pd-header { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 1; rx: 8 8 0 0; }
      .pd-tab-bar { fill: #f1f5f9; }
      .pd-tab { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1; rx: 6 6 0 0; }
      .pd-tab-active { fill: #ffffff; stroke: #6366f1; stroke-width: 2; rx: 6 6 0 0; }
      .pd-content { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1; rx: 0 0 8 8; }
      .pd-title { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 18px; font-weight: 700; }
      .pd-subtitle { fill: #64748b; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-label { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-value { fill: #475569; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-tab-text { fill: #64748b; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-tab-text-active { fill: #6366f1; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-btn { fill: #6366f1; rx: 6; }
      .pd-btn-text { fill: #ffffff; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-btn-back { fill: #f1f5f9; stroke: #e2e8f0; stroke-width: 1; rx: 6; }
      .pd-btn-back-text { fill: #475569; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-status { fill: #dcfce7; rx: 10; }
      .pd-status-text { fill: #166534; font-family: system-ui, sans-serif; font-size: 11px; font-weight: 600; }
      .pd-group-header { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 1; rx: 4; }
      .pd-group-text { fill: #334155; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-input { fill: #f8fafc; stroke: #cbd5e1; stroke-width: 1; rx: 4; }
      .pd-input-text { fill: #475569; font-family: system-ui, sans-serif; font-size: 11px; }
      .pd-section-label { fill: #94a3b8; font-family: system-ui, sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
    </style>
  </defs>
  <!-- Frame -->
  <rect class="pd-frame" x="0" y="0" width="800" height="520" />

  <!-- Header area -->
  <rect class="pd-header" x="0" y="0" width="800" height="80" />
  <!-- Back button -->
  <rect class="pd-btn-back" x="16" y="16" width="80" height="30" />
  <text class="pd-btn-back-text" x="32" y="36">Back</text>
  <!-- Title -->
  <text class="pd-title" x="16" y="68">SKU-12345 — Sample Product Premium</text>
  <!-- Status badge -->
  <rect class="pd-status" x="420" y="52" width="55" height="22" />
  <text class="pd-status-text" x="432" y="67">Active</text>
  <!-- Save button -->
  <rect class="pd-btn" x="700" y="16" width="84" height="30" />
  <text class="pd-btn-text" x="718" y="36">Save</text>

  <!-- Tab bar -->
  <rect class="pd-tab-bar" x="0" y="80" width="800" height="36" />
  <rect class="pd-tab-active" x="16" y="82" width="80" height="34" />
  <text class="pd-tab-text-active" x="30" y="103">Attributes</text>
  <rect class="pd-tab" x="100" y="82" width="110" height="34" />
  <text class="pd-tab-text" x="110" y="103">Var. Attributes</text>
  <rect class="pd-tab" x="214" y="82" width="75" height="34" />
  <text class="pd-tab-text" x="222" y="103">Variants</text>
  <rect class="pd-tab" x="293" y="82" width="65" height="34" />
  <text class="pd-tab-text" x="302" y="103">Media</text>
  <rect class="pd-tab" x="362" y="82" width="60" height="34" />
  <text class="pd-tab-text" x="372" y="103">Prices</text>
  <rect class="pd-tab" x="426" y="82" width="80" height="34" />
  <text class="pd-tab-text" x="436" y="103">Relations</text>
  <rect class="pd-tab" x="510" y="82" width="75" height="34" />
  <text class="pd-tab-text" x="519" y="103">Preview</text>
  <rect class="pd-tab" x="589" y="82" width="80" height="34" />
  <text class="pd-tab-text" x="598" y="103">Versions</text>

  <!-- Content area -->
  <rect class="pd-content" x="0" y="116" width="800" height="404" />

  <!-- Attribute group 1 -->
  <rect class="pd-group-header" x="16" y="130" width="768" height="28" />
  <text class="pd-group-text" x="28" y="149">Master Data</text>

  <!-- Attribute rows -->
  <text class="pd-label" x="28" y="182">Product Name</text>
  <rect class="pd-input" x="160" y="168" width="300" height="24" />
  <text class="pd-input-text" x="168" y="184">Sample Product Premium</text>
  <text class="pd-section-label" x="480" y="182">DE | EN</text>

  <text class="pd-label" x="28" y="216">Description</text>
  <rect class="pd-input" x="160" y="200" width="300" height="40" />
  <text class="pd-input-text" x="168" y="220">Detailed description of the</text>
  <text class="pd-input-text" x="168" y="234">product with all details...</text>

  <text class="pd-label" x="28" y="264">Weight</text>
  <rect class="pd-input" x="160" y="250" width="120" height="24" />
  <text class="pd-input-text" x="168" y="266">2.500</text>
  <text class="pd-value" x="290" y="266">kg</text>

  <text class="pd-label" x="28" y="298">Color</text>
  <rect class="pd-input" x="160" y="284" width="200" height="24" />
  <text class="pd-input-text" x="168" y="300">Anthracite</text>

  <!-- Attribute group 2 -->
  <rect class="pd-group-header" x="16" y="324" width="768" height="28" />
  <text class="pd-group-text" x="28" y="343">Technical Data</text>

  <text class="pd-label" x="28" y="376">Material</text>
  <rect class="pd-input" x="160" y="362" width="200" height="24" />
  <text class="pd-input-text" x="168" y="378">Stainless Steel V2A</text>

  <text class="pd-label" x="28" y="410">Certification</text>
  <rect class="pd-input" x="160" y="396" width="200" height="24" />
  <text class="pd-input-text" x="168" y="412">CE, TÜV</text>

  <text class="pd-label" x="28" y="444">Available</text>
  <rect fill="#6366f1" x="160" y="432" width="18" height="18" rx="3" />
  <text fill="#ffffff" x="165" y="446" font-family="system-ui, sans-serif" font-size="12" font-weight="700">✓</text>

  <!-- Footer hint -->
  <text class="pd-section-label" x="28" y="490">Attributes are displayed sorted by groups. Translatable fields can be edited per language.</text>
</svg>

### Header Area

In the header area you can see:
- **Back button** -- Navigates back to the product list
- **SKU and product name** -- Identification of the current product
- **Status display** -- Color-coded badge (Draft/Active/Inactive)
- **Save button** -- Saves all changes (`Ctrl + S`)

### Tabs

The product detail view has the following tabs:

| Tab | Content |
|---|---|
| **Attributes** | Regular product attributes sorted by groups |
| **Variant Attributes** | Attributes that serve as variant differentiators |
| **Variants** | List of product variants with inheritance status |
| **Media** | Assigned images, documents, and videos |
| **Prices** | Prices by price type and currency |
| **Relations** | Linked products (accessories, cross-sell, etc.) |
| **Preview** | Rendering of the product in the output format |
| **Versions** | Version history with diff view |

## Editing Attribute Values

In the **Attributes** tab, attribute values are displayed organized by attribute groups. The input field appearance depends on the attribute's data type:

| Data Type | Input Field | Description |
|---|---|---|
| **String** | Text field | Single-line or multi-line text |
| **Number** | Number field | Integers |
| **Float** | Decimal field | Floating-point numbers with unit |
| **Date** | Date picker | Calendar widget |
| **Flag** | Checkbox | Yes/No value |
| **Selection** | Dropdown | Choice from a value list |
| **Dictionary** | JSON editor | Key-value pairs |
| **Collection** | JSON editor | Structured collections |

### Translatable Attributes

For translatable attributes, a language switcher appears next to the input field (e.g., DE | EN). You can maintain the value for each configured language separately. The currently edited language is highlighted.

### Required Fields

Required attributes are marked with an asterisk (*). A product can only be set to the **Active** status when all required attributes are filled in.

### Attribute Groups (Collection Groups)

Attributes are displayed in logical groups separated by group headers. These groups are determined by the assignment in the hierarchy or product type and can contain Collection Groups -- repeatable blocks of related attributes.

## Product Variants

Variants are variations of a parent product that differ in specific attributes (e.g., color, size). The variant system is based on an **inheritance mechanism**.

### Creating Variants

1. Navigate to the **Variants** tab in the product detail view.
2. Click **+ Create Variant**.
3. Enter the SKU and name of the variant.
4. The variant automatically inherits all attribute values from the parent product.

### Inheritance Rules

Each attribute of a variant is subject to one of two inheritance modes:

| Mode | Behavior |
|---|---|
| **Inherit** | The value is taken from the parent product. Changes to the parent product are automatically propagated. The field is read-only in the variant. |
| **Override** | The variant uses its own value, which is maintained independently of the parent product. |

::: info Inheritance Principle
When creating a variant, all attributes are initially set to **Inherit**. Only when you explicitly change a value in the variant does the attribute switch to **Override**. This behavior can be reset per attribute to use the inherited value again.
:::

### Variant Attributes

In the **Variant Attributes** tab, you define which attributes should be considered variant-specific (e.g., color, size, finish). These attributes are displayed as columns in the variant list and serve for quick differentiation.

## Product Relations

In the **Relations** tab, you can establish relationships between products:

| Relation Type | Description |
|---|---|
| **Accessories** | Complementary products that go with the main product |
| **Cross-Sell** | Related products for cross-selling |
| **Up-Sell** | Higher-value alternatives |
| **Spare Parts** | Replacement parts for the main product |

To add a relation:
1. Select the relation type.
2. Search for the target product by SKU or name.
3. Confirm the assignment.

Relations are directional: if Product A is defined as an accessory of Product B, this link only applies in that direction.

## Product Status and Workflow

Every product goes through a defined status workflow:

```
┌──────────┐      ┌──────────┐      ┌──────────┐
│  Draft   │ ───> │  Active  │ ───> │ Inactive │
│ (draft)  │      │ (active) │      │(inactive)│
└──────────┘      └──────────┘      └──────────┘
      ^                                   │
      └───────────────────────────────────┘
```

| Status | Meaning |
|---|---|
| **Draft** | Product is being edited and not yet approved. Not exportable. |
| **Active** | Product is fully maintained and approved for export. All required attributes must be filled in. |
| **Inactive** | Product is deactivated and no longer exported. Can be reactivated or set back to Draft at any time. |

::: warning Status Change to Active
The transition from **Draft** to **Active** is only possible when all required attributes of the assigned product type are filled in. Otherwise, the system displays an error message listing the missing attributes.
:::

## Bulk Operations

For efficient editing of large product inventories, bulk operations are available:

1. **Multi-selection** -- Enable checkboxes in the product list to select multiple products.
2. **Bulk actions** -- After selection, action buttons appear:
   - **Change status** -- Sets the status of all selected products.
   - **Delete** -- Removes the selected products after confirmation.

::: danger Warning
Deleting products is irreversible. Deleted products cannot be restored. Use the **Inactive** status instead if you want to temporarily remove products from export.
:::

## Version History

In the **Versions** tab, you can track all changes to a product:

- **Version list** -- Shows all saved versions with timestamp and user.
- **Diff view** (ProductVersionDiff) -- Compares two versions and highlights differences with colors (green = added, red = removed).

## Next Steps

- Learn more about the [Attributes](./attributes) that structure your products.
- Learn how [Hierarchies](./hierarchies) assign attributes to your products.
- Assign [Media](./media) and [Prices](./pricing) to your products.
