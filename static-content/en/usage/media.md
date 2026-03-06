---
title: Media
---

# Media

The media management of the anyPIM provides a central library for all product-related files. Here you upload images, documents, and videos, organize them, and assign them to your products.

## Media Library

You can access the media library via the **Media** menu item in the sidebar. It provides an overview of all uploaded files with preview, filtering, and search functionality.

### Upload

Files can be uploaded in two ways:

1. **Upload button** -- Click **+ Upload Media** and select one or more files from the file dialog.
2. **Drag-and-Drop** -- Drag files directly from the desktop or file manager into the upload area of the media library.

After uploading, preview images (thumbnails) are automatically generated. The upload progress is displayed per file.

::: tip Note
Large files are processed asynchronously. For extensive uploads, thumbnail generation may take a few seconds. However, the files are available immediately after upload.
:::

### Supported Media Types

| Category | Formats | Description |
|---|---|---|
| **Images** | JPG, PNG, GIF, SVG, WebP | Product photos, illustrations, graphics |
| **Documents** | PDF, DOCX, XLSX | Data sheets, manuals, specifications |
| **Videos** | MP4, WebM | Product videos, tutorials |

### Browsing and Filtering

The media library offers the following search and filter options:

- **Full-text search** -- Searches file names and metadata.
- **Type filter** -- Restricts the display to a specific category (Images, Documents, Videos).
- **Sorting** -- Sorts by file name, upload date, or file size.

## Assigning Media to a Product

The assignment of media to products takes place in the **product detail view** in the **Media** tab.

### Via Drag-and-Drop

1. Open the product detail view and switch to the **Media** tab.
2. Drag files from the media library or directly from the desktop into the assignment area.
3. The file is assigned to the product and displayed in the media list.

### Via Selection Dialog

1. Click **+ Assign Medium** in the Media tab.
2. A selection dialog with the media library opens.
3. Search for and select the desired files.
4. Confirm the assignment.

### Setting the Order

The order of assigned media can be changed via drag-and-drop. The first image in the list typically serves as the **main image** (teaser) of the product.

## Media Metadata

Each medium has editable metadata that is important for output and discoverability:

| Field | Description |
|---|---|
| **File Name** | Original name of the uploaded file |
| **Alt Text** | Alternative text for accessibility and SEO (translatable) |
| **Title** | Display title of the medium (translatable) |
| **Usage Type** | Type of usage (see below) |
| **File Size** | Automatically determined size in KB/MB |
| **Dimensions** | Width and height in pixels (images only) |
| **MIME Type** | Technical file type (e.g., `image/jpeg`) |

### Usage Types

The usage type defines the context in which a medium is used:

| Usage Type | Description |
|---|---|
| **Teaser** | Main image for product overviews, list views, and preview images |
| **Gallery** | Additional images for the product image gallery |
| **Data Sheet** | Technical data sheet or specification (typically: PDF) |
| **Manual** | Operating instructions or assembly guide |
| **Video** | Product video or application video |
| **Other** | All other media types |

The usage type is set when assigning the medium to the product and can be changed afterwards. It serves as a filter during export, for example to export only teaser images for an online shop.

## Editing and Deleting Media

### Editing Metadata

Click on a medium in the media library to open its detail view. There you can edit the alt text, title, and usage type. The alt text and title fields are **translatable** and can be maintained in German and English.

### Deleting a Medium

Click **Delete** in the detail view to remove a medium from the library.

::: danger Warning
Deleting a medium also removes it from all product assignments. Before deleting, check which products use the medium.
:::

### Removing an Assignment

To detach a medium from a product without deleting it from the library, click the remove icon next to the corresponding medium in the product's Media tab.

## Delivery via the API

Uploaded media are served via the REST API and can be retrieved by external systems:

- **Original file** -- Access to the original file in full resolution.
- **Preview image** -- Automatically generated thumbnails for quick preview.
- **Metadata** -- The media metadata (alt text, title, usage type) is available via the API as JSON.

For details on API integration, please refer to the [API documentation](/en/api/).

## Best Practices

- **File names** -- Use descriptive file names that describe the product and content (e.g., `SKU12345_frontal.jpg` instead of `IMG_001.jpg`).
- **Alt texts** -- Maintain alt texts for all images. They are not only important for accessibility but also improve discoverability and SEO.
- **Usage types** -- Set the usage type consistently so that the right media end up in the right places during export.
- **File formats** -- Use WebP or JPG in sufficient resolution for product photos. PDF is suitable for data sheets and manuals.

## Next Steps

- Learn how to create [Products](./products) and assign media in the "Media" tab.
- Get to know the [API documentation](/en/api/) to retrieve media programmatically.
