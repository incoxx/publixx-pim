---
title: Sheet Designer (Excel)
---

# Sheet Designer (Excel Templates)

The **Sheet Designer** is a graphical editor for Excel export templates. You design multi-sheet `.xlsx` exports with freely chosen columns, grouping and formatting, and control the product scope via a search profile.

## Creating a template

1. Navigation: **Sheet Designer** → list of your own and shared templates.
2. Create a **"New Template"**.
3. In the editor, assemble columns — the field picker offers:
   - **Base fields** (SKU, name, EAN …)
   - **Attributes** (by data type)
   - **Prices** (by price type)
   - **Media** (by usage type)
   - **Relations** (by relation type)
4. Optionally define multiple **sheets** and a **grouping**.
5. Link a **search profile** to filter the product scope.

## Preview, export & import

- **Preview** renders a mini table with a few products.
- **Download** generates the Excel file. Large data volumes run as an **asynchronous export job** with a progress indicator and a cancel option; the finished file is then made available for download.
- **Import** reads an existing `.xlsx` structure and automatically creates a template from it.

::: tip Related features
For plain data hand-offs see [JSON Export](/en/export/json-export); for order data the [Publixx Export](/en/export/publixx-export).
:::

## Permissions

Dedicated rights govern viewing, creating, editing and deleting templates. Non-shared templates are visible only to their creator. Configuration is under [Roles & Permissions](/en/administration/roles).
