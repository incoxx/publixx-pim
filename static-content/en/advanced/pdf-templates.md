---
title: PDF Templates
---

# PDF Templates

The PDF Templates module in anyPIM provides a visual template designer for creating professionally formatted product data sheets, catalogs, and other print-ready documents. Templates use placeholders that are automatically filled with product attribute values during generation, allowing you to produce consistent, branded output for any product in your catalog.

## Template Designer Overview

Navigate to **PDF Templates** in the sidebar to access the template management. The overview shows all existing templates:

| Column | Description |
|---|---|
| **Name** | The template name (e.g., "Product Data Sheet", "Catalog Page") |
| **Page Format** | The configured page size (A4, Letter, etc.) |
| **Orientation** | Portrait or Landscape |
| **Last Modified** | Timestamp of the last modification |
| **Products Generated** | Number of PDFs generated using this template |

Click on a template to open the visual designer, or click **+ New Template** to create one from scratch.

## Creating a Template

When creating a new template, you first configure the basic page settings:

| Setting | Description | Default |
|---|---|---|
| **Name** | A descriptive name for the template | -- |
| **Page Format** | Paper size: A4, A3, Letter, Legal, or Custom | A4 |
| **Orientation** | Portrait or Landscape | Portrait |
| **Margins** | Top, bottom, left, and right margins in millimeters | 15 mm |

After saving the initial settings, the visual designer opens.

## Visual Designer

The visual designer presents a WYSIWYG canvas that represents the PDF page. You build the template by placing and arranging elements on the canvas.

### Available Elements

| Element | Description |
|---|---|
| **Text Block** | Static or dynamic text with formatting options (font, size, color, alignment) |
| **Image Placeholder** | Displays a product image or a static image (e.g., company logo) |
| **Table** | A structured table for presenting attribute values in rows and columns |
| **Horizontal Line** | A visual separator between sections |
| **Barcode / QR Code** | Generates a barcode or QR code from a product attribute (e.g., EAN, SKU) |
| **Spacer** | An invisible element that adds vertical spacing between other elements |

### Placing Elements

1. Select an element type from the toolbar on the left.
2. Click on the canvas where you want to place the element.
3. The element appears on the canvas and can be resized by dragging its handles.
4. Use the properties panel on the right to configure the element's settings.

### Element Properties

Each element type has specific properties. For example, a Text Block element offers:

- **Content** -- The text to display, which can include placeholders.
- **Font** -- Font family, size, weight, and style.
- **Color** -- Text color and background color.
- **Alignment** -- Left, center, right, or justified.
- **Padding** -- Inner spacing around the text.

## Placeholders

Placeholders are the key mechanism for injecting product data into templates. They use a double-brace syntax: `{{ attribute_key }}`.

### Placeholder Syntax

| Placeholder | Resolves To |
|---|---|
| `{{ sku }}` | The product's SKU |
| `{{ name }}` | The product name in the current language |
| `{{ status }}` | The product status |
| `{{ product_type }}` | The name of the assigned product type |
| `{{ attribute_key }}` | The value of any custom attribute (use the technical name) |
| `{{ manufacturer.name }}` | The name of the assigned manufacturer |
| `{{ manufacturer.logo }}` | The manufacturer's logo image |
| `{{ price.default }}` | The default price value |

::: tip Note
Placeholder names correspond to the technical names of your attributes. You can browse available placeholders in the designer by clicking the **Insert Placeholder** button, which opens a searchable list of all attributes.
:::

### Conditional Blocks

You can wrap sections of the template in conditional blocks that only render when a placeholder has a value:

```
{% if battery_type %}
Battery Type: {{ battery_type }}
Battery Capacity: {{ battery_capacity_mah }} mAh
{% endif %}
```

This prevents empty labels from appearing in the generated PDF when a product does not have a value for the referenced attribute.

## Multi-Page Layouts

Templates can span multiple pages. The designer supports the following multi-page features:

- **Page breaks** -- Insert explicit page breaks to control where content moves to the next page.
- **Automatic overflow** -- When content exceeds the available space on a page, it automatically continues on the next page.
- **Different first page** -- Optionally define a different layout for the first page (for example, a cover page with a large product image) while subsequent pages use a standard layout.

## Headers and Footers

Each template can have a **header** and **footer** that repeat on every page:

### Header

The header area appears at the top of each page. Typical content includes:

- Company logo (static image)
- Document title
- Product SKU or name

### Footer

The footer area appears at the bottom of each page. Typical content includes:

- Page number (`{{ page_number }}` / `{{ total_pages }}`)
- Generation date (`{{ generated_at }}`)
- Disclaimer or confidentiality notice
- Company address

::: warning Warning
Header and footer content reduces the available space for the main body on each page. Keep headers and footers compact to maximize the usable content area.
:::

## Preview with Sample Data

The designer includes a **Preview** function that renders the template with real product data:

1. Click **Preview** in the designer toolbar.
2. A product search dialog opens. Select a product to use as sample data.
3. The preview renders the template as a PDF in a new browser tab, with all placeholders replaced by the selected product's attribute values.

Use the preview to verify layout, spacing, and placeholder resolution before publishing the template for production use.

## Managing Templates

### Duplicating a Template

To create a variation of an existing template, click **Duplicate** in the template overview. The copy is created with the suffix "(Copy)" appended to the name. This is useful for creating format-specific variations (for example, an A4 version and a Letter version of the same data sheet).

### Deleting a Template

Click **Delete** to remove a template. This action does not affect previously generated PDFs, but the template will no longer be available for future generation.

## Generating PDFs

Once a template is configured, you can generate PDFs from the product detail view:

1. Open the product detail view.
2. Navigate to the **Preview** tab.
3. Select the desired PDF template from the dropdown.
4. Click **Generate PDF**.
5. The PDF is generated and downloaded to your browser.

PDF generation is also available as a bulk action from the product list: select multiple products and choose **Generate PDF** from the bulk actions menu. This creates one PDF per product using the selected template.

## Next Steps

- Learn about [Reports](./reports) for tabular data exports.
- Explore [Export Jobs](./export-jobs) to automate PDF generation and delivery.
- Review [Products](../usage/products) to understand the attributes available for placeholders.
