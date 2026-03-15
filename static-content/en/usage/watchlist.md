---
title: Watchlist
---

# Watchlist

The Watchlist is a personal collection of products that you want to monitor or access quickly. Unlike search results or filtered views, the watchlist is persistent and tied to your user account. It is ideal for keeping track of products that require attention, are awaiting review, or need to be compared side by side.

## Adding Products to the Watchlist

You can add products to your watchlist from several locations in anyPIM:

- **Product list** -- Select one or more products using the checkboxes, then click the **Add to Watchlist** button in the bulk actions toolbar.
- **Product detail view** -- Click the **star icon** in the product header to toggle the product on or off your watchlist.
- **Search results** -- After performing a search, use the context menu on any result to add it to the watchlist.

When a product is on your watchlist, the star icon appears filled in both the product list and the detail view, giving you a visual indicator at a glance.

## Viewing the Watchlist

Navigate to **Watchlist** in the sidebar to see all products currently on your personal watchlist. The watchlist view displays a table with the following columns:

| Column | Description |
|---|---|
| **SKU** | Unique article number |
| **Name** | Product name in the current language |
| **Status** | Current product status (Draft, Active, Inactive) |
| **Added On** | Date when the product was added to your watchlist |
| **Notes** | A preview of the personal note attached to the product (if any) |

### Sorting and Filtering

The watchlist supports sorting by any column header. You can also use the search bar at the top to filter your watchlist by SKU or product name. This is helpful when your watchlist contains a large number of products.

## Removing Products from the Watchlist

To remove products from your watchlist:

- **Single product** -- Click the **star icon** on the product row or in the product detail view to remove it.
- **Multiple products** -- Select the products using checkboxes and click **Remove from Watchlist** in the bulk actions toolbar.
- **Clear all** -- Use the **Clear Watchlist** button to remove all products at once. A confirmation dialog appears before the action is executed.

::: warning Warning
Clearing the entire watchlist cannot be undone. Make sure you no longer need the list before confirming.
:::

## Product Notes

Each product on your watchlist can have a **personal note** attached to it. Notes are visible only to you and are not shared with other users.

To add or edit a note:

1. Click on the **Notes** cell in the watchlist table, or open the product and click the **Edit Note** icon.
2. Enter your note text in the text area that appears.
3. Click **Save** to store the note.

Notes are useful for documenting why a product was added to the watchlist, what action needs to be taken, or any other context that is relevant to you personally.

### Example Use Cases for Notes

- "Waiting for updated images from the supplier."
- "Price needs to be reviewed before next export."
- "Compare dimensions with SKU-56789."

## Product Comparison

The watchlist includes a **comparison feature** that lets you view multiple products side by side. This is particularly useful when evaluating similar products or verifying consistency across related items.

### Starting a Comparison

1. In the watchlist view, select two or more products using the checkboxes.
2. Click the **Compare** button that appears in the toolbar.
3. A comparison view opens, displaying the selected products in adjacent columns.

### Comparison View Layout

The comparison view presents product data in a structured table:

| Row | Content |
|---|---|
| **Header** | SKU and product name for each selected product |
| **Status** | Current status of each product |
| **Attributes** | All attributes are listed row by row, with values shown side by side |
| **Differences** | Cells where values differ between products are highlighted |

::: tip Note
The comparison view highlights differences using color coding. Matching values appear in the default style, while differing values are highlighted to draw your attention.
:::

### Comparison Limitations

- You can compare up to **five products** at a time.
- Only attributes that are assigned to at least one of the selected products are shown.
- The comparison uses the attribute values in the currently selected language.

## Exporting the Watchlist

You can export the contents of your watchlist for use outside of anyPIM. Click the **Export** button at the top of the watchlist view and choose an output format:

| Format | Description |
|---|---|
| **CSV** | Comma-separated values file, suitable for spreadsheet applications |
| **Excel (XLSX)** | Microsoft Excel workbook with formatted headers |
| **PDF** | Printable document with product details in tabular layout |

The export includes all products currently visible in the watchlist (respecting any active filters). Personal notes are included as an additional column in the exported file.

## Watchlist Scope

The watchlist is private to your user account. Other users cannot see your watchlist or your notes. If you need to share a set of products with colleagues, consider using the [Workflow](./workflow) feature to create tasks assigned to specific users, or use the export function to send the list externally.

## Next Steps

- Use the [Dashboard](./dashboard) to see a preview of your watchlist items.
- Create [Workflow](./workflow) tasks from products on your watchlist.
- Learn about [Product Relations](../usage/relation-types) to understand how products are connected.
