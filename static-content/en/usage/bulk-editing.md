---
title: Bulk Editing
---

# Bulk Editing

For maintaining many products at once, anyPIM offers two complementary tools: the **Bulk Editor** for direct, table-based editing and **Bulk Update** for rule- or filter-based changes with a preview.

## Bulk Editor

The Bulk Editor shows several products and selected attributes in a table (products × attributes) and lets you edit the values directly — similar to a spreadsheet.

### Workflow

1. Select products in the product list (multi-select) and open the **Bulk Editor**.
2. **Step 1 — Select attributes:** Choose which attributes to edit (grouped by data type).
3. **Step 2 — Edit:** The table shows the products (SKU/name) on the left and one attribute per column. Edit values directly in the cells. Changed cells are highlighted; the footer shows the number of pending changes.
4. The language selector switches the editing language for translatable attributes.
5. **"Save all"** writes all changes back in a single operation.

::: tip
The Bulk Editor works with direct attribute values (not inherited ones) and supports translatable attributes per language.
:::

## Bulk Update

Bulk Update changes many products based on a selection or a filter and covers far more object types than the Bulk Editor. The operations are organised in tabs:

| Tab | Effect |
|---|---|
| **Attributes** | Set values — modes *overwrite*, *fill empty* or *clear* |
| **Relations** | Add/remove product relations |
| **Output hierarchy** | Add/remove node assignments |
| **Status** | Set product status |
| **Master hierarchy** | Set/remove the master node |
| **Manufacturer** | Assign/remove a manufacturer |
| **Media** | Assign/remove media (optionally with a usage type) |

### Workflow

1. Select products **or** apply a filter (filter-based).
2. Configure the desired operations via the tabs.
3. **"Preview & check"** shows the expected number of affected objects per operation (dry run, no changes).
4. **"Execute"** applies all operations. Very large sets are processed in chunks with a progress indicator.

::: warning
Bulk Update is powerful and affects many products at once. Use the preview and verify the filter result before executing.
:::

## Permissions

The Bulk Editor uses the standard product rights (view/edit products). Bulk Update requires the separate mass-update right. Which role may use these tools is configurable under [Roles & Permissions](/en/administration/roles).
