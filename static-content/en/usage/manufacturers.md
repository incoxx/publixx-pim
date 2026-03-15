---
title: Manufacturers
---

# Manufacturers

The Manufacturers module in anyPIM provides centralized management of manufacturer master data. By maintaining manufacturer records separately from product data, you ensure consistency across your entire product catalog -- every product references the same, authoritative manufacturer information rather than duplicating it in individual product records.

## Manufacturer List

Navigate to **Manufacturers** in the sidebar to access the manufacturer overview. The list displays all registered manufacturers in a table with the following columns:

| Column | Description |
|---|---|
| **Name** | The official manufacturer name |
| **Logo** | A thumbnail preview of the manufacturer's logo (if uploaded) |
| **Website** | The manufacturer's website URL |
| **Products** | The number of products assigned to this manufacturer |
| **Modified** | Timestamp of the last modification |

### Searching and Filtering

Use the search bar above the table to filter manufacturers by name. The list supports pagination for large datasets, and you can sort by any column header.

## Creating a Manufacturer

Click the **+ New Manufacturer** button to open the creation panel. The following fields are available:

| Field | Description | Required |
|---|---|---|
| **Name** | Official company name of the manufacturer | Yes |
| **Logo** | Upload or select a logo image from the media library | No |
| **Website** | URL of the manufacturer's website | No |
| **Email** | General contact email address | No |
| **Phone** | Contact phone number | No |
| **Address** | Street address, city, postal code, and country | No |
| **Description** | Internal notes or description of the manufacturer | No |

After saving, the manufacturer record is created and available for assignment to products.

::: tip Note
The manufacturer logo is stored in the media library and can be reused across exports and PDF templates. Upload logos in PNG or SVG format for the best quality across different output channels.
:::

## Editing a Manufacturer

Click on a manufacturer in the list to open the detail view. All fields can be edited inline. Changes are saved when you click the **Save** button.

### Contact Information

The contact fields (email, phone, address) are intended for internal reference. They are not exported with product data by default but can be included in custom export profiles if needed.

### Manufacturer Logo

To update the logo:

1. Click the current logo thumbnail (or the placeholder icon if no logo is set).
2. A media picker opens, allowing you to select an existing image from the library or upload a new one.
3. Select the desired image and confirm.

The logo is displayed as a thumbnail in the manufacturer list and can be used in PDF templates and product data sheets.

## Assigning Manufacturers to Products

There are two ways to associate a manufacturer with a product:

### From the Product Detail View

1. Open the product in the detail view.
2. In the **Attributes** tab, locate the manufacturer field (typically in the Master Data group).
3. Select the manufacturer from the dropdown list.
4. Save the product.

### From the Manufacturer Detail View

1. Open the manufacturer record.
2. Scroll to the **Assigned Products** section at the bottom.
3. Click **+ Add Products** to open a product search dialog.
4. Search for products by SKU or name and select them.
5. Confirm the assignment.

::: warning Warning
Each product can be assigned to exactly one manufacturer. Assigning a new manufacturer replaces the previous one.
:::

## Filtering Products by Manufacturer

The manufacturer acts as a filter criterion in the product list. To filter products by manufacturer:

1. Navigate to **Products** in the sidebar.
2. Open the filter bar and select the **Manufacturer** filter.
3. Choose one or more manufacturers from the dropdown.
4. The product list updates to show only products from the selected manufacturers.

This is particularly useful for reviewing all products from a specific supplier, or for preparing manufacturer-specific exports.

## Deleting a Manufacturer

To delete a manufacturer, open the manufacturer detail view and click the **Delete** button. A confirmation dialog will appear.

::: danger Warning
Deleting a manufacturer removes the association from all assigned products. The products themselves are not deleted, but their manufacturer field will be cleared. Consider whether you should reassign the products to another manufacturer before deleting.
:::

## Import and Export

Manufacturer data can be included in product imports and exports:

- **Import** -- When importing products via Excel or CSV, the manufacturer can be specified by name. If the manufacturer does not exist yet, anyPIM will create it automatically during the import process.
- **Export** -- Manufacturer name, logo URL, and website can be included as columns in export profiles. This allows downstream systems to receive complete manufacturer information alongside product data.

## Next Steps

- Learn how to manage [Products](./products) and assign manufacturers during product creation.
- Use [Relation Types](./relation-types) to define connections between products from different manufacturers.
- Explore [Reports](../advanced/reports) to generate manufacturer-specific product reports.
